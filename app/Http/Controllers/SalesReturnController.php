<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\SalesReturn\StoreSalesReturnRequest;
use App\Http\Requests\SalesReturn\UpdateSalesReturnRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SalesApprovalNotifier;
use App\Services\SalesReturnService;
use App\Services\SalesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesReturnController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['return_date', 'return_no', 'status', 'refund_amount'];

    /**
     * Display the sales return register.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesReturn::class);

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

        return view('sales-returns.index', [
            'returns' => $returns,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $this->totals($request),
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => SalesReturnStatus::options(),
        ]);
    }

    /**
     * Show the "Apply Return" form, optionally prefilled from an invoice.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', SalesReturn::class);

        $invoice = SalesInvoice::query()->with('items.unit')
            ->find(old('sales_invoice_id', $request->input('invoice')));

        return view('sales-returns.create', [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'parentInvoice' => $invoice,
            'invoices' => $this->customerInvoicesList((int) (old('customer_id') ?: $invoice?->customer_id) ?: null, Auth::user()),
            'rows' => $this->returnRows($invoice, $this->oldItems()),
        ]);
    }

    /**
     * Store a newly created return request awaiting branch-manager approval.
     */
    public function store(StoreSalesReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $invoice = SalesInvoice::query()->with('items')->findOrFail($validated['sales_invoice_id']);
        $items = $this->returnableItems($validated['items']);

        $return = DB::transaction(function () use ($request, $validated, $invoice, $items) {
            $return = SalesReturn::create([
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $validated['customer_id'],
                'return_date' => $validated['return_date'],
                'location_id' => $invoice->location_id,
                'branch_id' => $invoice->branch_id ?? $request->user()?->branch?->branch_id,
                'status' => SalesReturnStatus::Pending,
                'reason' => $validated['reason'] ?? null,
                'refund_amount' => 0,
                'created_by' => $request->user()?->id,
            ]);

            $this->saveItems($return, $invoice, $items);
            SalesReturnService::recalculate($return);

            // Stock and invoice credits only move once a manager approves.
            return $return;
        });

        AuditService::created($return, "Sales return '{$return->return_no}' created.");
        SalesApprovalNotifier::awaitingApproval($return);

        return redirect()->route('sales-returns.index')
            ->with('success', "Sales return '{$return->return_no}' submitted for branch manager approval.");
    }

    /**
     * Display the specified sales return.
     */
    public function show(SalesReturn $salesReturn): View
    {
        $this->authorize('view', $salesReturn);

        $salesReturn->load(['items.unit', 'salesInvoice', 'customer', 'location', 'branch', 'creator', 'approver']);

        return view('sales-returns.show', [
            'return' => $salesReturn,
        ]);
    }

    /**
     * Show the form for editing a pending return.
     */
    public function edit(SalesReturn $salesReturn): View|RedirectResponse
    {
        $this->authorize('update', $salesReturn);

        if (! in_array($salesReturn->status, [SalesReturnStatus::Pending, SalesReturnStatus::Rejected], true)) {
            return redirect()->route('sales-returns.show', $salesReturn)
                ->with('error', 'Approved returns cannot be edited. Create a new return instead.');
        }

        $salesReturn->load('items');

        $invoice = old('sales_invoice_id')
            ? SalesInvoice::query()->with('items.unit')->find(old('sales_invoice_id'))
            : $salesReturn->salesInvoice()->with('items.unit')->first();

        return view('sales-returns.edit', [
            'salesReturn' => $salesReturn,
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'parentInvoice' => $invoice,
            'invoices' => $this->customerInvoicesList((int) (old('customer_id') ?: $salesReturn->customer_id) ?: null, Auth::user()),
            'rows' => $this->returnRows($invoice, $this->oldItems($salesReturn)),
        ]);
    }

    /**
     * Update a pending return. Editing a rejected return puts it back in the
     * approval queue.
     */
    public function update(UpdateSalesReturnRequest $request, SalesReturn $salesReturn): RedirectResponse
    {
        if (! in_array($salesReturn->status, [SalesReturnStatus::Pending, SalesReturnStatus::Rejected], true)) {
            return redirect()->route('sales-returns.show', $salesReturn)
                ->with('error', 'Approved returns cannot be edited. Create a new return instead.');
        }

        $original = $salesReturn->getOriginal();
        $validated = $request->validated();
        $invoice = SalesInvoice::query()->with('items')->findOrFail($validated['sales_invoice_id']);
        $items = $this->returnableItems($validated['items']);
        $wasRejected = $salesReturn->status === SalesReturnStatus::Rejected;

        DB::transaction(function () use ($salesReturn, $validated, $invoice, $items) {
            $salesReturn->items()->delete();
            $this->saveItems($salesReturn, $invoice, $items);

            $salesReturn->update([
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $validated['customer_id'],
                'return_date' => $validated['return_date'],
                'location_id' => $invoice->location_id,
                'reason' => $validated['reason'] ?? null,
                'status' => SalesReturnStatus::Pending,
            ]);

            SalesReturnService::recalculate($salesReturn);
        });

        AuditService::updated($salesReturn, $original, "Sales return '{$salesReturn->return_no}' updated.");

        if ($wasRejected) {
            SalesApprovalNotifier::awaitingApproval($salesReturn);
        }

        return redirect()->route('sales-returns.index')
            ->with('success', "Sales return '{$salesReturn->return_no}' updated successfully.");
    }

    /**
     * Remove a pending return request.
     */
    public function destroy(SalesReturn $salesReturn): RedirectResponse
    {
        $this->authorize('delete', $salesReturn);

        if (! in_array($salesReturn->status, [SalesReturnStatus::Pending, SalesReturnStatus::Rejected], true)) {
            return back()->with('error', 'Approved returns cannot be deleted. Create a new return instead.');
        }

        DB::transaction(function () use ($salesReturn) {
            $salesReturn->items()->delete();
            $salesReturn->delete();
        });

        AuditService::deleted($salesReturn, "Sales return '{$salesReturn->return_no}' deleted.");

        return redirect()->route('sales-returns.index')
            ->with('success', "Sales return '{$salesReturn->return_no}' deleted successfully.");
    }

    /**
     * Approve a return: the responsible branch manager signs off, the invoice
     * is credited and restockable lines come back into stock.
     */
    public function approve(SalesReturn $salesReturn): RedirectResponse
    {
        $this->authorize('approve', $salesReturn);

        if ($salesReturn->status !== SalesReturnStatus::Pending) {
            return back()->with('error', "Sales return '{$salesReturn->return_no}' is not awaiting approval.");
        }

        $original = $salesReturn->getOriginal();

        try {
            DB::transaction(function () use ($salesReturn) {
                $salesReturn->update([
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                SalesReturnService::apply($salesReturn);
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditService::updated($salesReturn, $original, "Sales return '{$salesReturn->return_no}' approved.");
        SalesApprovalNotifier::decided($salesReturn->fresh(), true);

        return redirect()->route('sales-returns.index')
            ->with('success', "Sales return '{$salesReturn->return_no}' approved. Invoice credited and stock updated.");
    }

    /**
     * Reject a return: it never takes effect.
     */
    public function reject(SalesReturn $salesReturn): RedirectResponse
    {
        $this->authorize('reject', $salesReturn);

        if ($salesReturn->status !== SalesReturnStatus::Pending) {
            return back()->with('error', "Sales return '{$salesReturn->return_no}' is not awaiting approval.");
        }

        $original = $salesReturn->getOriginal();

        $salesReturn->update([
            'status' => SalesReturnStatus::Rejected,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        AuditService::updated($salesReturn, $original, "Sales return '{$salesReturn->return_no}' rejected.");
        SalesApprovalNotifier::decided($salesReturn->fresh(), false);

        return redirect()->route('sales-returns.index')
            ->with('success', "Sales return '{$salesReturn->return_no}' rejected.");
    }

    /**
     * Posted invoices of a customer, for the return form's invoice dropdown.
     */
    public function customerInvoices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesReturn::class);

        $customerId = (int) $request->input('customer_id');

        return response()->json([
            'success' => true,
            'invoices' => $this->customerInvoicesList($customerId ?: null, $request->user()),
        ]);
    }

    /**
     * Full details of an invoice (products, paid and due amounts) for the
     * return form's interactive grid.
     */
    public function invoiceDetails(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesReturn::class);

        $invoice = SalesInvoice::query()->with('items.unit')->find($request->input('sales_invoice_id'));

        if ($invoice === null || ! SalesScope::canAccessRecord($request->user(), $invoice)) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $invoice->load('returns.items');

        $alreadyReturned = $invoice->returns
            ->whereIn('status', [SalesReturnStatus::Approved, SalesReturnStatus::Completed])
            ->flatMap(fn (SalesReturn $return) => $return->items)
            ->groupBy('unit_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->format('d/m/Y H:i'),
                'location' => $invoice->location?->name ?? '—',
                'grand_total' => (float) $invoice->grand_total,
                'paid_amount' => (float) $invoice->paid_amount,
                'payment_due' => (float) $invoice->payment_due,
            ],
            'items' => $invoice->items->map(fn ($item) => [
                'unit_id' => (int) $item->unit_id,
                'product_name' => $item->unit?->displayName() ?? 'Unknown product',
                'unit' => $item->unit?->unit ?? '',
                'invoiced' => (int) $item->quantity,
                'unit_price' => SalesReturnService::netUnitPrice($invoice, (int) $item->unit_id),
                'already_returned' => (int) ($alreadyReturned[$item->unit_id] ?? 0),
            ])->values()->all(),
        ]);
    }

    /**
     * Posted invoices of a customer shaped for the invoice dropdown.
     * Respects the viewer's branch/location scope.
     *
     * @return array<int, array<string, mixed>>
     */
    private function customerInvoicesList(?int $customerId, ?User $user = null): array
    {
        if (! $customerId) {
            return [];
        }

        $query = SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', SalesInvoiceStatus::Posted)
            ->orderByDesc('invoice_date')
            ->limit(50);

        if ($user !== null) {
            $query = SalesScope::scopeQuery($query, $user);
        }

        return $query->get()
            ->map(fn (SalesInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->format('d/m/Y H:i'),
                'grand_total' => (float) $invoice->grand_total,
                'paid_amount' => (float) $invoice->paid_amount,
                'payment_due' => (float) $invoice->payment_due,
            ])
            ->all();
    }

    /**
     * Old return rows submitted before a validation redirect, keyed by unit id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function oldItems(?SalesReturn $return = null): array
    {
        $items = old('items');

        if (is_array($items) && $items !== []) {
            return array_values(array_map(fn (array $row) => [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'product_name' => Unit::query()->find((int) ($row['unit_id'] ?? 0))?->displayName() ?? 'Unknown product',
                'return_qty' => (int) ($row['quantity'] ?? 0),
                'condition' => (string) ($row['condition'] ?? 'good'),
                'restock' => (bool) ($row['restock'] ?? true),
                'reason' => (string) ($row['reason'] ?? ''),
            ], $items));
        }

        if ($return !== null) {
            return $return->items->map(fn ($item) => [
                'unit_id' => (int) $item->unit_id,
                'product_name' => $item->unit?->displayName() ?? 'Unknown product',
                'return_qty' => (int) $item->quantity,
                'condition' => $item->condition->value,
                'restock' => (bool) $item->restock,
                'reason' => (string) ($item->reason ?? ''),
            ])->values()->all();
        }

        return [];
    }

    /**
     * Products of an invoice shaped for the return grid.
     *
     * @param  array<int, array<string, mixed>>  $returnRows
     * @return array<int, array<string, mixed>>
     */
    private function returnRows(?SalesInvoice $invoice, array $returnRows): array
    {
        if ($returnRows !== []) {
            return $returnRows;
        }

        if ($invoice === null) {
            return [];
        }

        $invoice->loadMissing('items.unit', 'returns.items');

        $alreadyReturned = $invoice->returns
            ->whereIn('status', [SalesReturnStatus::Approved, SalesReturnStatus::Completed])
            ->flatMap(fn (SalesReturn $return) => $return->items)
            ->groupBy('unit_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        return $invoice->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'invoiced' => (int) $item->quantity - (int) ($alreadyReturned[$item->unit_id] ?? 0),
            'unit_price' => SalesReturnService::netUnitPrice($invoice, (int) $item->unit_id),
            'return_qty' => 0,
            'condition' => 'good',
            'restock' => true,
            'reason' => '',
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
     * Persist the return line items priced at the invoice's net prices.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(SalesReturn $return, SalesInvoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = SalesReturnService::netUnitPrice($invoice, (int) $item['unit_id']);

            $return->items()->create([
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
                'condition' => $item['condition'] ?? 'good',
                'restock' => (bool) ($item['restock'] ?? true),
                'reason' => $item['reason'] ?? null,
            ]);
        }
    }

    /**
     * Base query for the register with all filters applied.
     * Outlet Managers only see returns of their branch/location.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = SalesReturn::query()
            ->with(['salesInvoice', 'customer', 'location', 'branch'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('return_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('return_date', '<=', $request->string('date_to')->toString()));

        $query = SalesScope::scopeQuery($query, $request->user());

        return $query
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', (int) $request->input('location_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')));
    }

    /**
     * Summary numbers for the register cards and table footer.
     *
     * @return array{total: int, pending: int, approved: int, completed: int, rejected: int, refund_amount: float}
     */
    private function totals(Request $request): array
    {
        $base = $this->filteredQuery($request);

        $counts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $refund = (float) (clone $base)->toBase()->sum('refund_amount');

        return [
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
            'refund_amount' => round($refund, 2),
        ];
    }
}
