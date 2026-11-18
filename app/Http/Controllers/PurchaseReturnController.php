<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseStatus;
use App\Exceptions\InsufficientStockException;
use App\Exports\PurchaseReturnExport;
use App\Http\Requests\PurchaseReturn\StorePurchaseReturnRequest;
use App\Http\Requests\PurchaseReturn\UpdatePurchaseReturnRequest;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LocationScope;
use App\Services\PurchaseApprovalNotifier;
use App\Services\PurchaseReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseReturnController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['return_date', 'reference_no', 'reason', 'payment_status', 'grand_total', 'paid_amount'];

    /**
     * Display a listing of purchase returns.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'return_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $returns = $this->filteredQuery($request)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('purchase-returns.index', [
            'returns' => $returns,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $this->totals($request),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the "Apply Return" form, optionally prefilled from a parent purchase.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseReturn::class);

        $parent = PurchaseOrder::query()->with('items.unit')
            ->find(old('purchase_order_id', $request->input('purchase_order')));

        return view('purchase-returns.create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'parentOrder' => $parent,
            'purchases' => $this->supplierPurchasesList((int) (old('supplier_id') ?: $parent?->supplier_id) ?: null, Auth::user()),
            'rows' => $this->returnRows($parent, $this->oldQuantities()),
        ]);
    }

    /**
     * Store a newly created purchase return in storage.
     */
    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $order = PurchaseOrder::query()->with('items')->findOrFail($validated['purchase_order_id']);
        $items = $this->returnableItems($validated['items']);

        try {
            $return = DB::transaction(function () use ($request, $validated, $order, $items) {
                $return = PurchaseReturn::create([
                    'reference_no' => $validated['reference_no'] ?? null,
                    'purchase_order_id' => $order->id,
                    'return_date' => $validated['return_date'],
                    'supplier_id' => $validated['supplier_id'],
                    'location_id' => $order->location_id,
                    'reason' => $validated['reason'],
                    'approval_status' => ApprovalStatus::Pending,
                    'subtotal' => 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'grand_total' => 0,
                    'paid_amount' => 0,
                    'document' => $this->storeDocument($request),
                    'created_by' => $request->user()->id,
                ]);

                $this->saveItems($return, $order, $items);
                $this->recalculate($return);

                // Stock and supplier dues only move once the return is approved.
                return $return;
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::created($return, "Purchase return '{$return->reference_no}' created.");
        PurchaseApprovalNotifier::awaitingApproval($return);

        return redirect()->route('purchase-returns.index')
            ->with('success', "Purchase return '{$return->reference_no}' submitted for approval.");
    }

    /**
     * Display the specified purchase return.
     */
    public function show(PurchaseReturn $purchaseReturn): View
    {
        $this->authorize('view', $purchaseReturn);

        $purchaseReturn->load(['items.unit.product', 'purchaseOrder', 'supplier', 'location', 'creator']);

        return view('purchase-returns.show', [
            'return' => $purchaseReturn,
        ]);
    }

    /**
     * Show the form for editing the specified purchase return.
     */
    public function edit(PurchaseReturn $purchaseReturn): View
    {
        $this->authorize('update', $purchaseReturn);

        $purchaseReturn->load('items');

        $order = old('purchase_order_id')
            ? PurchaseOrder::query()->with('items.unit')->find(old('purchase_order_id'))
            : $purchaseReturn->purchaseOrder()->with('items.unit')->first();

        $quantities = old('items')
            ? $this->oldQuantities()
            : $purchaseReturn->items->pluck('quantity', 'unit_id')->all();

        return view('purchase-returns.edit', [
            'purchaseReturn' => $purchaseReturn,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'parentOrder' => $order,
            'purchases' => $this->supplierPurchasesList((int) (old('supplier_id') ?: $purchaseReturn->supplier_id) ?: null, Auth::user()),
            'rows' => $this->returnRows($order, $quantities),
        ]);
    }

    /**
     * Update the specified purchase return in storage.
     */
    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $original = $purchaseReturn->getOriginal();
        $originalQuantities = $purchaseReturn->items->pluck('quantity', 'unit_id')->all();
        $wasApproved = $purchaseReturn->approval_status === ApprovalStatus::Approved;
        $wasRejected = $purchaseReturn->approval_status === ApprovalStatus::Rejected;
        $validated = $request->validated();
        $order = PurchaseOrder::query()->with('items')->findOrFail($validated['purchase_order_id']);
        $items = $this->returnableItems($validated['items']);

        try {
            DB::transaction(function () use ($purchaseReturn, $validated, $order, $items, $request, $original, $originalQuantities, $wasApproved) {
                $purchaseReturn->items()->delete();
                $this->saveItems($purchaseReturn, $order, $items);

                $purchaseReturn->update([
                    'reference_no' => ($validated['reference_no'] ?? null) ?: $purchaseReturn->reference_no,
                    'purchase_order_id' => $order->id,
                    'return_date' => $validated['return_date'],
                    'supplier_id' => $validated['supplier_id'],
                    'location_id' => $order->location_id,
                    'reason' => $validated['reason'],
                    // Editing a rejected return puts it back in the approval queue.
                    'approval_status' => $purchaseReturn->approval_status === ApprovalStatus::Rejected
                        ? ApprovalStatus::Pending
                        : $purchaseReturn->approval_status,
                    'document' => $this->storeDocument($request) ?? $purchaseReturn->document,
                ]);

                $this->recalculate($purchaseReturn);

                // Stock and supplier dues only move for approved returns.
                if ($wasApproved) {
                    PurchaseReturnService::syncStock($purchaseReturn, $originalQuantities);
                    PurchaseReturnService::applySupplierDue($purchaseReturn, $original);
                }
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->deleteReplacedDocument($request, $original);

        AuditService::updated($purchaseReturn, $original, "Purchase return '{$purchaseReturn->reference_no}' updated.");

        // A resubmitted return is back in the approval queue.
        if ($wasRejected && $purchaseReturn->approval_status === ApprovalStatus::Pending) {
            PurchaseApprovalNotifier::awaitingApproval($purchaseReturn);
        }

        return redirect()->route('purchase-returns.index')
            ->with('success', "Purchase return '{$purchaseReturn->reference_no}' updated successfully.");
    }

    /**
     * Remove the specified purchase return from storage.
     */
    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('delete', $purchaseReturn);

        $originalQuantities = $purchaseReturn->items->pluck('quantity', 'unit_id')->all();
        $wasApproved = $purchaseReturn->approval_status === ApprovalStatus::Approved;

        DB::transaction(function () use ($purchaseReturn, $originalQuantities, $wasApproved) {
            PurchaseReturnService::releaseSupplierDue($purchaseReturn);

            if ($purchaseReturn->document) {
                Storage::disk('public')->delete($purchaseReturn->document);
            }

            $purchaseReturn->items()->delete();
            $purchaseReturn->delete();

            // Deleting an approved return restores the stock it had taken out.
            if ($wasApproved) {
                PurchaseReturnService::syncStock($purchaseReturn, $originalQuantities);
            }
        });

        AuditService::deleted($purchaseReturn, "Purchase return '{$purchaseReturn->reference_no}' deleted.");

        return redirect()->route('purchase-returns.index')
            ->with('success', "Purchase return '{$purchaseReturn->reference_no}' deleted successfully.");
    }

    /**
     * Approve a return: the responsible manager signs off and the return
     * takes effect — stock leaves and the supplier return due registers.
     */
    public function approve(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('approve', $purchaseReturn);

        if ($purchaseReturn->approval_status === ApprovalStatus::Approved) {
            return back()->with('error', "Purchase return '{$purchaseReturn->reference_no}' is already approved.");
        }

        $original = $purchaseReturn->getOriginal();

        try {
            DB::transaction(function () use ($purchaseReturn, $original) {
                $purchaseReturn->update([
                    'approval_status' => ApprovalStatus::Approved,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                PurchaseReturnService::syncStock($purchaseReturn, []);
                PurchaseReturnService::applySupplierDue($purchaseReturn, $original);
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditService::updated($purchaseReturn, $original, "Purchase return '{$purchaseReturn->reference_no}' approved.");
        PurchaseApprovalNotifier::decided($purchaseReturn, true);

        return redirect()->route('purchase-returns.index')
            ->with('success', "Purchase return '{$purchaseReturn->reference_no}' approved. Stock and supplier return due updated.");
    }

    /**
     * Reject a return: it never takes effect.
     */
    public function reject(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('reject', $purchaseReturn);

        if ($purchaseReturn->approval_status === ApprovalStatus::Approved) {
            return back()->with('error', "Purchase return '{$purchaseReturn->reference_no}' is already approved. Delete it instead.");
        }

        $original = $purchaseReturn->getOriginal();

        DB::transaction(function () use ($purchaseReturn, $original) {
            $purchaseReturn->update([
                'approval_status' => ApprovalStatus::Rejected,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            PurchaseReturnService::applySupplierDue($purchaseReturn, $original);
        });

        AuditService::updated($purchaseReturn, $original, "Purchase return '{$purchaseReturn->reference_no}' rejected.");
        PurchaseApprovalNotifier::decided($purchaseReturn, false);

        return redirect()->route('purchase-returns.index')
            ->with('success', "Purchase return '{$purchaseReturn->reference_no}' rejected.");
    }

    /**
     * Recent purchases of a distributor, for the return form's invoice reference dropdown.
     */
    public function supplierPurchases(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $supplierId = (int) $request->input('supplier_id');

        return response()->json([
            'success' => true,
            'purchases' => $this->supplierPurchasesList($supplierId ?: null, $request->user()),
        ]);
    }

    /**
     * Full details of a purchase (products, paid and due amounts) for the return form.
     */
    public function purchaseDetails(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $order = PurchaseOrder::query()->with('items.unit')->find($request->input('purchase_order_id'));

        if ($order === null || ! LocationScope::canAccess($request->user(), $order->location_id)) {
            return response()->json(['success' => false, 'message' => 'Purchase not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'purchase' => [
                'id' => $order->id,
                'reference_no' => $order->reference_no,
                'order_date' => $order->order_date->format('d/m/Y H:i'),
                'location' => $order->location?->name ?? '—',
                'grand_total' => (float) $order->grand_total,
                'paid_amount' => (float) $order->paid_amount,
                'payment_due' => (float) $order->payment_due,
            ],
            'items' => $order->items->map(fn ($item) => [
                'unit_id' => (int) $item->unit_id,
                'product_name' => $item->unit?->displayName() ?? 'Unknown product',
                'unit' => $item->unit?->unit ?? '',
                'purchased' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->values()->all(),
        ]);
    }

    /**
     * Recent purchases of a supplier shaped for the invoice reference dropdown.
     * Respects the viewer's location scope.
     *
     * @return array<int, array<string, mixed>>
     */
    private function supplierPurchasesList(?int $supplierId, ?User $user = null): array
    {
        if (! $supplierId) {
            return [];
        }

        $query = PurchaseOrder::query()
            ->with('items')
            ->where('supplier_id', $supplierId)
            ->where('purchase_status', '!=', PurchaseStatus::Cancelled->value)
            ->orderByDesc('order_date')
            ->limit(50);

        if ($user !== null) {
            $query = LocationScope::scopeQuery($query, $user);
        }

        return $query->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'reference_no' => $order->reference_no,
                'order_date' => $order->order_date->format('d/m/Y H:i'),
                'grand_total' => (float) $order->grand_total,
                'paid_amount' => (float) $order->paid_amount,
                'payment_due' => (float) $order->payment_due,
            ])
            ->all();
    }

    /**
     * Old return quantities submitted before a validation redirect, keyed by unit id.
     *
     * @return array<int, int>
     */
    private function oldQuantities(): array
    {
        return collect(old('items', []))
            ->mapWithKeys(fn (array $row) => [(int) ($row['unit_id'] ?? 0) => (int) ($row['quantity'] ?? 0)])
            ->filter()
            ->all();
    }

    /**
     * Products of a purchase shaped for the return grid.
     *
     * @param  array<int, int>  $returnQuantities  Return quantities keyed by unit id.
     * @return array<int, array<string, mixed>>
     */
    private function returnRows(?PurchaseOrder $order, array $returnQuantities): array
    {
        if ($order === null) {
            return [];
        }

        $order->loadMissing('items.unit');

        return $order->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'unit' => $item->unit?->unit ?? '',
            'purchased' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'return_qty' => (int) ($returnQuantities[$item->unit_id] ?? 0),
        ])->values()->all();
    }

    /**
     * Submitted rows with a positive return quantity.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function returnableItems(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $row) => (int) ($row['quantity'] ?? 0) > 0));
    }

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        [$columns, $returns] = $this->exportData($request);

        return view('purchase-returns.pdf', [
            'returns' => $returns,
            'columns' => $columns,
        ]);
    }

    /**
     * Show clean print layout matching the reference layout.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        [$columns, $returns] = $this->exportData($request);

        return view('purchase-returns.print', [
            'returns' => $returns,
            'columns' => $columns,
        ]);
    }

    /**
     * Export purchase returns to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $search = $request->string('search')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'purchase_returns_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new PurchaseReturnExport($search ?: null, $columns ?: null),
            $filename
        );
    }

    /**
     * Base query for the listing with all filters applied.
     * Outlet Managers only see the returns of their assigned location.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = PurchaseReturn::query()
            ->with(['purchaseOrder', 'supplier', 'location'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', (int) $request->input('supplier_id')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('return_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('return_date', '<=', $request->string('date_to')->toString()));

        $query = LocationScope::scopeQuery($query, $request->user());

        return $query->when($request->filled('location_id'), fn ($q) => $q->where('location_id', (int) $request->input('location_id')));
    }

    /**
     * Summary numbers for the table footer.
     *
     * @return array{due: int, partial: int, paid: int, grand_total: float, payment_due: float}
     */
    private function totals(Request $request): array
    {
        $base = $this->filteredQuery($request);

        $counts = (clone $base)
            ->toBase()
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        $sums = (clone $base)
            ->toBase()
            ->selectRaw('coalesce(sum(grand_total), 0) as grand_total, coalesce(sum(paid_amount), 0) as paid_amount')
            ->first();

        return [
            'due' => (int) ($counts['due'] ?? 0),
            'partial' => (int) ($counts['partial'] ?? 0),
            'paid' => (int) ($counts['paid'] ?? 0),
            'grand_total' => round((float) ($sums->grand_total ?? 0), 2),
            'payment_due' => round(max(0, (float) ($sums->grand_total ?? 0) - (float) ($sums->paid_amount ?? 0)), 2),
        ];
    }

    /**
     * Resolve the visible columns and matching returns for PDF / print views.
     *
     * @return array{0: array<string, string>, 1: Collection<int, PurchaseReturn>}
     */
    private function exportData(Request $request): array
    {
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'return_date' => 'Date',
            'reference_no' => 'Reference No',
            'parent_purchase' => 'Parent Purchase',
            'location' => 'Location',
            'supplier' => 'Supplier',
            'reason' => 'Reason',
            'payment_status' => 'Payment Status',
            'grand_total' => 'Grand Total',
            'payment_due' => 'Payment Due',
        ];

        if ($columnsParam !== '') {
            $requested = array_filter(array_map('trim', explode(',', $columnsParam)));
            $columns = [];
            foreach ($requested as $col) {
                if (isset($availableColumns[$col])) {
                    $columns[$col] = $availableColumns[$col];
                }
            }
            if (empty($columns)) {
                $columns = $availableColumns;
            }
        } else {
            $columns = $availableColumns;
        }

        $returns = $this->filteredQuery($request)->orderBy('return_date', 'desc')->get();

        return [$columns, $returns];
    }

    /**
     * Persist the return line items priced from the parent purchase.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(PurchaseReturn $return, PurchaseOrder $order, array $items): void
    {
        $prices = $order->items->pluck('unit_price', 'unit_id');

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitId = (int) $item['unit_id'];
            $unitPrice = round((float) $prices->get($unitId, 0), 2);

            $return->items()->create([
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
            ]);
        }
    }

    /**
     * Recompute subtotal and grand total from the stored line items.
     */
    private function recalculate(PurchaseReturn $return): void
    {
        $subtotal = round((float) $return->items()->sum('subtotal'), 2);

        $return->update([
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'grand_total' => $subtotal,
        ]);
    }

    /**
     * Store the uploaded document and return its public path.
     */
    private function storeDocument(Request $request): ?string
    {
        return $request->hasFile('document')
            ? $request->file('document')->store('purchase-documents', 'public')
            : null;
    }

    /**
     * Remove the previous document when a replacement was uploaded.
     *
     * @param  array<string, mixed>  $original
     */
    private function deleteReplacedDocument(Request $request, array $original): void
    {
        $previous = $original['document'] ?? null;

        if ($previous && $request->hasFile('document')) {
            Storage::disk('public')->delete($previous);
        }
    }
}
