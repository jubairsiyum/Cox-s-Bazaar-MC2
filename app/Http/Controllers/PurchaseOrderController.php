<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseStatus;
use App\Exceptions\InsufficientStockException;
use App\Exports\PurchaseOrderExport;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\LocationScope;
use App\Services\PurchaseApprovalNotifier;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Purchase Orders are the single purchase document in the system.
 *
 * The "Add" form lives in the Purchase Orders section; the register listing
 * the records (with view / edit / delete actions) lives in the Purchase
 * Invoices section, so the index and record routes are mounted there.
 */
class PurchaseOrderController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['order_date', 'reference_no', 'purchase_status', 'payment_status', 'grand_total', 'paid_amount'];

    /**
     * Display the register of purchase records (Purchase Invoices section).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'order_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $orders = $this->filteredQuery($request)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('purchase-invoices.index', [
            'orders' => $orders,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $this->totals($request),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'locations' => $this->scopedLocations(),
        ]);
    }

    /**
     * Show the "New Purchase Order" form (Purchase Orders section).
     */
    public function create(): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('purchase-orders.create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'locations' => $this->scopedLocations(),
            'statuses' => $this->creationStatusOptions(),
            'items' => $this->hydrateItems(collect(old('items', []))->all()),
        ]);
    }

    /**
     * Store a newly created purchase order in storage.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'];

        try {
            $order = DB::transaction(function () use ($request, $validated, $items) {
                $order = PurchaseOrder::create([
                    'reference_no' => $validated['reference_no'] ?? null,
                    'order_date' => $validated['order_date'],
                    'supplier_id' => $validated['supplier_id'],
                    'location_id' => $validated['location_id'],
                    'purchase_status' => $validated['purchase_status'],
                    'subtotal' => 0,
                    'tax_rate' => $this->taxRate($validated),
                    'tax_amount' => 0,
                    'grand_total' => 0,
                    'paid_amount' => round((float) ($validated['paid_amount'] ?? 0), 2),
                    'document' => $this->storeDocument($request),
                    'created_by' => $request->user()->id,
                ]);

                $this->saveItems($order, $items);
                $this->recalculate($order);

                PurchaseOrderService::applyStatusChange($order, null, $order->purchase_status);
                PurchaseOrderService::applySupplierDue($order);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::created($order, "Purchase order '{$order->reference_no}' created.");
        PurchaseApprovalNotifier::awaitingApproval($order);

        return redirect()->route('purchase-invoices.index')
            ->with('success', "Purchase order '{$order->reference_no}' created successfully.");
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['items.unit.product', 'supplier', 'location', 'creator']);

        return view('purchase-invoices.show', [
            'order' => $purchaseOrder,
        ]);
    }

    /**
     * Show the form for editing the specified purchase order.
     */
    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        $itemsEditable = $purchaseOrder->approval_status !== ApprovalStatus::Approved;

        $items = $itemsEditable && $this->oldItems() !== []
            ? $this->hydrateItems($this->oldItems())
            : $this->presentItems($purchaseOrder);

        return view('purchase-invoices.edit', [
            'order' => $purchaseOrder,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'locations' => $this->scopedLocations(),
            'statuses' => $this->editStatusOptions($purchaseOrder),
            'itemsEditable' => $itemsEditable,
            'items' => $items,
        ]);
    }

    /**
     * Update the specified purchase order in storage.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $original = $purchaseOrder->getOriginal();
        $fromStatus = PurchaseStatus::from((string) $purchaseOrder->getRawOriginal('purchase_status'));
        $validated = $request->validated();
        $itemsEditable = $purchaseOrder->approval_status !== ApprovalStatus::Approved;
        $items = $itemsEditable ? $validated['items'] : [];

        try {
            DB::transaction(function () use ($purchaseOrder, $validated, $items, $itemsEditable, $request, $fromStatus, $original) {
                if ($itemsEditable) {
                    $purchaseOrder->items()->delete();
                    $this->saveItems($purchaseOrder, $items);
                }

                $purchaseOrder->update([
                    'reference_no' => ($validated['reference_no'] ?? null) ?: $purchaseOrder->reference_no,
                    'order_date' => $validated['order_date'],
                    'supplier_id' => $validated['supplier_id'],
                    'location_id' => $validated['location_id'],
                    'purchase_status' => $validated['purchase_status'],
                    'tax_rate' => $this->taxRate($validated),
                    'paid_amount' => round((float) ($validated['paid_amount'] ?? 0), 2),
                    'document' => $this->storeDocument($request) ?? $purchaseOrder->document,
                ]);

                $this->recalculate($purchaseOrder);

                PurchaseOrderService::applyStatusChange($purchaseOrder, $fromStatus, $purchaseOrder->purchase_status);
                PurchaseOrderService::applySupplierDue($purchaseOrder, $original);
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->deleteReplacedDocument($request, $original);

        AuditService::updated($purchaseOrder, $original, "Purchase order '{$purchaseOrder->reference_no}' updated.");

        return redirect()->route('purchase-invoices.index')
            ->with('success', "Purchase order '{$purchaseOrder->reference_no}' updated successfully.");
    }

    /**
     * Remove the specified purchase order from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        try {
            DB::transaction(function () use ($purchaseOrder) {
                PurchaseOrderService::applyStatusChange($purchaseOrder, $purchaseOrder->purchase_status, PurchaseStatus::Cancelled);
                PurchaseOrderService::releaseSupplierDue($purchaseOrder);

                if ($purchaseOrder->document) {
                    Storage::disk('public')->delete($purchaseOrder->document);
                }

                $purchaseOrder->delete();
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditService::deleted($purchaseOrder, "Purchase order '{$purchaseOrder->reference_no}' deleted.");

        return redirect()->route('purchase-invoices.index')
            ->with('success', "Purchase order '{$purchaseOrder->reference_no}' deleted successfully.");
    }

    /**
     * Approve (receive) a purchase: the responsible manager signs off and the
     * purchase takes effect — stock is received and the supplier due registers.
     */
    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);

        if ($purchaseOrder->approval_status === ApprovalStatus::Approved) {
            return back()->with('error', "Purchase order '{$purchaseOrder->reference_no}' is already approved.");
        }

        $original = $purchaseOrder->getOriginal();
        $fromStatus = PurchaseStatus::from((string) $purchaseOrder->getRawOriginal('purchase_status'));

        try {
            DB::transaction(function () use ($purchaseOrder, $fromStatus, $original) {
                $purchaseOrder->update([
                    'approval_status' => ApprovalStatus::Approved,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'purchase_status' => PurchaseStatus::Received,
                ]);

                PurchaseOrderService::applyStatusChange($purchaseOrder, $fromStatus, PurchaseStatus::Received);
                PurchaseOrderService::applySupplierDue($purchaseOrder, $original);
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditService::updated($purchaseOrder, $original, "Purchase order '{$purchaseOrder->reference_no}' approved and received.");
        PurchaseApprovalNotifier::decided($purchaseOrder, true);

        return redirect()->route('purchase-invoices.index')
            ->with('success', "Purchase order '{$purchaseOrder->reference_no}' approved. Stock received and supplier due updated.");
    }

    /**
     * Reject a purchase: it never takes effect and is marked cancelled.
     */
    public function reject(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reject', $purchaseOrder);

        if ($purchaseOrder->approval_status === ApprovalStatus::Approved) {
            return back()->with('error', "Purchase order '{$purchaseOrder->reference_no}' is already approved. Cancel it instead.");
        }

        $original = $purchaseOrder->getOriginal();
        $fromStatus = PurchaseStatus::from((string) $purchaseOrder->getRawOriginal('purchase_status'));

        DB::transaction(function () use ($purchaseOrder, $fromStatus, $original) {
            $purchaseOrder->update([
                'approval_status' => ApprovalStatus::Rejected,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'purchase_status' => PurchaseStatus::Cancelled,
            ]);

            PurchaseOrderService::applyStatusChange($purchaseOrder, $fromStatus, PurchaseStatus::Cancelled);
            PurchaseOrderService::applySupplierDue($purchaseOrder, $original);
        });

        AuditService::updated($purchaseOrder, $original, "Purchase order '{$purchaseOrder->reference_no}' rejected.");
        PurchaseApprovalNotifier::decided($purchaseOrder, false);

        return redirect()->route('purchase-invoices.index')
            ->with('success', "Purchase order '{$purchaseOrder->reference_no}' rejected.");
    }

    /**
     * Search stock lines (products) for the purchase form.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return response()->json(['success' => true, 'items' => $this->searchUnitsPayload($request)]);
    }

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        [$columns, $orders] = $this->exportData($request);

        return view('purchase-invoices.pdf', [
            'orders' => $orders,
            'columns' => $columns,
        ]);
    }

    /**
     * Show clean print layout matching the reference layout.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        [$columns, $orders] = $this->exportData($request);

        return view('purchase-invoices.print', [
            'orders' => $orders,
            'columns' => $columns,
        ]);
    }

    /**
     * Export purchase orders to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $search = $request->string('search')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'purchase_orders_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new PurchaseOrderExport($search ?: null, $columns ?: null),
            $filename
        );
    }

    /**
     * Base query for the listing with all filters applied.
     * Outlet Managers only see the purchases of their assigned location.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'location', 'creator'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', (int) $request->input('supplier_id')))
            ->when($request->filled('purchase_status'), fn ($q) => $q->where('purchase_status', $request->string('purchase_status')->toString()))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('order_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('order_date', '<=', $request->string('date_to')->toString()));

        $query = LocationScope::scopeQuery($query, $request->user());

        return $query->when($request->filled('location_id'), fn ($q) => $q->where('location_id', (int) $request->input('location_id')));
    }

    /**
     * Locations the current user may purchase for.
     *
     * @return Collection<int, Location>
     */
    private function scopedLocations(): Collection
    {
        $query = Location::query()->active()->orderBy('name');

        $ids = LocationScope::accessibleLocationIds(Auth::user());

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    /**
     * Purchase statuses selectable when creating a purchase.
     *
     * @return array<string, string>
     */
    private function creationStatusOptions(): array
    {
        return array_filter(
            PurchaseStatus::options(),
            fn (string $value) => in_array($value, [PurchaseStatus::Pending->value, PurchaseStatus::Ordered->value], true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Purchase statuses selectable when editing a purchase. "Received" is only
     * reachable through the approval action.
     *
     * @return array<string, string>
     */
    private function editStatusOptions(PurchaseOrder $order): array
    {
        if ($order->approval_status === ApprovalStatus::Approved) {
            return [
                PurchaseStatus::Received->value => PurchaseStatus::Received->label(),
                PurchaseStatus::Cancelled->value => PurchaseStatus::Cancelled->label(),
            ];
        }

        return array_filter(
            PurchaseStatus::options(),
            fn (string $value) => in_array($value, [
                PurchaseStatus::Pending->value,
                PurchaseStatus::Ordered->value,
                PurchaseStatus::Cancelled->value,
            ], true),
            ARRAY_FILTER_USE_KEY
        );
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
     * Resolve the visible columns and matching orders for PDF / print views.
     *
     * @return array{0: array<string, string>, 1: \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>}
     */
    private function exportData(Request $request): array
    {
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'order_date' => 'Date',
            'reference_no' => 'Reference No',
            'location' => 'Location',
            'supplier' => 'Supplier',
            'purchase_status' => 'Purchase Status',
            'payment_status' => 'Payment Status',
            'grand_total' => 'Grand Total',
            'payment_due' => 'Payment Due',
            'added_by' => 'Added By',
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

        $orders = $this->filteredQuery($request)->orderBy('order_date', 'desc')->get();

        return [$columns, $orders];
    }

    /**
     * Product search results shared by the purchase forms.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function searchUnitsPayload(Request $request): Collection
    {
        return Unit::query()
            ->with('product')
            ->search($request->string('q')->toString())
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->limit(10)
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'product_name' => $unit->displayName(),
                'unit' => $unit->unit,
                'quantity' => $unit->quantity,
                'purchase_price' => (float) ($unit->product?->purchase_price ?? 0),
            ]);
    }

    /**
     * Old line items submitted before a validation redirect.
     *
     * @return array<int, array<string, mixed>>
     */
    private function oldItems(): array
    {
        return collect(old('items', []))->all();
    }

    /**
     * Resolve product names for line item rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateItems(array $rows): array
    {
        $units = Unit::query()
            ->whereIn('id', collect($rows)->pluck('unit_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        return collect($rows)->map(function (array $row) use ($units) {
            $unit = $units->get((int) ($row['unit_id'] ?? 0));

            return [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'product_name' => $unit?->displayName() ?? 'Unknown product',
                'unit' => $unit?->unit ?? '',
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Line items of an existing order shaped for the form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentItems(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'unit' => $item->unit?->unit ?? '',
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
        ])->values()->all();
    }

    /**
     * Persist the line items of an order with computed subtotals.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(PurchaseOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = round((float) $item['unit_price'], 2);

            $order->items()->create([
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
            ]);
        }
    }

    /**
     * Recompute subtotal, tax and grand total from the stored line items.
     */
    private function recalculate(PurchaseOrder $order): void
    {
        $subtotal = round((float) $order->items()->sum('subtotal'), 2);
        $taxAmount = round($subtotal * (float) $order->tax_rate / 100, 2);

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'grand_total' => round($subtotal + $taxAmount, 2),
        ]);
    }

    /**
     * Purchase tax percentage submitted with the form.
     *
     * @param  array<string, mixed>  $validated
     */
    private function taxRate(array $validated): float
    {
        return round((float) ($validated['tax_rate'] ?? 0), 2);
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
