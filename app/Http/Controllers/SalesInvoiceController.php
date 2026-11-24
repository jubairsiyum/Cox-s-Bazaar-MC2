<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesInvoiceStatus;
use App\Exceptions\DayClosedException;
use App\Exceptions\InsufficientStockException;
use App\Exports\SalesInvoiceExport;
use App\Http\Requests\SalesInvoice\StoreSalesInvoiceRequest;
use App\Http\Requests\SalesInvoice\UpdateSalesInvoiceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\SalesInvoiceService;
use App\Services\SalesScope;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesInvoiceController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['invoice_date', 'invoice_no', 'payment_status', 'grand_total', 'paid_amount', 'due_amount'];

    /**
     * Display the sales invoice register.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesInvoice::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'invoice_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $invoices = $this->filteredQuery($request)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('sales-invoices.index', [
            'invoices' => $invoices,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $this->totals($request),
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => SalesInvoiceStatus::options(),
        ]);
    }

    /**
     * Collections — every distributor invoice in one list: generated from a
     * sales order or rung up on the POS alike, scoped to the user's own
     * branch/location like the rest of the sales section.
     */
    public function distributorIndex(Request $request): View
    {
        $this->authorize('viewAny', SalesInvoice::class);

        $invoices = $this->distributorQuery($request)
            ->orderBy('invoice_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('collections.index', [
            'invoices' => $invoices,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => SalesInvoiceStatus::options(),
            'canFilterBranch' => ! SalesScope::isRestricted($request->user()),
        ]);
    }

    /**
     * Show the invoice creation form, optionally prefilled from a sales order.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', SalesInvoice::class);

        $order = SalesOrder::query()->with('items.unit')
            ->find(old('sales_order_id', $request->input('sales_order')));

        return view('sales-invoices.create', [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'salesOrder' => $order,
            'rows' => $this->itemRows($order, $this->oldItems()),
            'defaultLocationId' => old('location_id') ?: Auth::user()?->branch_id,
        ]);
    }

    /**
     * Store a newly created sales invoice.
     */
    public function store(StoreSalesInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only create invoices for your own branch/location.');
        }

        $invoice = DB::transaction(function () use ($request, $validated) {
            $invoice = SalesInvoice::create([
                'invoice_date' => $validated['invoice_date'],
                'customer_id' => $validated['customer_id'],
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'location_id' => $validated['location_id'],
                // The creator's branch: the branch of their assigned outlet.
                'branch_id' => $request->user()?->branch?->branch_id,
                'status' => SalesInvoiceStatus::Draft,
                'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
                'paid_amount' => (float) ($validated['paid_amount'] ?? 0),
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $this->saveItems($invoice, $validated['items']);
            SalesInvoiceService::recalculate($invoice, (float) ($validated['tax_rate'] ?? 0));
            SalesInvoiceService::syncLinkedOrder($invoice);

            return $invoice;
        });

        AuditService::created($invoice, "Sales invoice '{$invoice->invoice_no}' created.");

        return redirect()->route('sales-invoices.show', $invoice)
            ->with('success', "Sales invoice '{$invoice->invoice_no}' created as draft. Post it to issue the stock-out.");
    }

    /**
     * Display the specified sales invoice.
     */
    public function show(SalesInvoice $salesInvoice): View
    {
        $this->authorize('view', $salesInvoice);

        $salesInvoice->load(['items.unit', 'customer', 'location', 'branch', 'creator', 'salesOrder', 'returns', 'allocations.collection']);

        return view('sales-invoices.show', [
            'invoice' => $salesInvoice,
        ]);
    }

    /**
     * Show the printable invoice document.
     */
    public function print(SalesInvoice $salesInvoice): View
    {
        $this->authorize('view', $salesInvoice);

        $salesInvoice->load(['items.unit', 'customer', 'location', 'branch', 'creator']);

        return view('sales-invoices.print', [
            'invoice' => $salesInvoice,
        ]);
    }

    /**
     * Show the form for editing a draft invoice.
     */
    public function edit(SalesInvoice $salesInvoice): View
    {
        $this->authorize('update', $salesInvoice);

        if (! $salesInvoice->status->isEditable()) {
            abort(403, 'Only draft invoices can be edited.');
        }

        $salesInvoice->load('items');

        $order = old('sales_order_id')
            ? SalesOrder::query()->with('items.unit')->find(old('sales_order_id'))
            : $salesInvoice->salesOrder()->with('items.unit')->first();

        return view('sales-invoices.edit', [
            'invoice' => $salesInvoice,
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'salesOrder' => $order,
            'rows' => $this->itemRows($order, $this->oldItems($salesInvoice)),
        ]);
    }

    /**
     * Update a draft invoice.
     */
    public function update(UpdateSalesInvoiceRequest $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        if (! $salesInvoice->status->isEditable()) {
            abort(403, 'Only draft invoices can be edited.');
        }

        $original = $salesInvoice->getOriginal();
        $validated = $request->validated();

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only keep invoices on your own branch/location.');
        }

        DB::transaction(function () use ($salesInvoice, $validated) {
            $salesInvoice->items()->delete();
            $this->saveItems($salesInvoice, $validated['items']);

            $salesInvoice->update([
                'invoice_date' => $validated['invoice_date'],
                'customer_id' => $validated['customer_id'],
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'location_id' => $validated['location_id'],
                'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
                'paid_amount' => (float) ($validated['paid_amount'] ?? 0),
                'note' => $validated['note'] ?? null,
            ]);

            SalesInvoiceService::recalculate($salesInvoice, (float) ($validated['tax_rate'] ?? 0));
        });

        AuditService::updated($salesInvoice, $original, "Sales invoice '{$salesInvoice->invoice_no}' updated.");

        return redirect()->route('sales-invoices.show', $salesInvoice)
            ->with('success', "Sales invoice '{$salesInvoice->invoice_no}' updated successfully.");
    }

    /**
     * Delete a draft invoice.
     */
    public function destroy(SalesInvoice $salesInvoice): RedirectResponse
    {
        $this->authorize('delete', $salesInvoice);

        if (! $salesInvoice->status->isEditable()) {
            abort(403, 'Only draft invoices can be deleted.');
        }

        DB::transaction(function () use ($salesInvoice) {
            $salesInvoice->items()->delete();
            $salesInvoice->delete();
            SalesInvoiceService::syncLinkedOrder($salesInvoice);
        });

        AuditService::deleted($salesInvoice, "Sales invoice '{$salesInvoice->invoice_no}' deleted.");

        return redirect()->route('sales-invoices.index')
            ->with('success', "Sales invoice '{$salesInvoice->invoice_no}' deleted successfully.");
    }

    /**
     * Post a draft invoice: the stock-out takes effect.
     */
    public function post(SalesInvoice $salesInvoice): RedirectResponse
    {
        $this->authorize('post', $salesInvoice);

        if ($salesInvoice->status !== SalesInvoiceStatus::Draft) {
            return back()->with('error', "Sales invoice '{$salesInvoice->invoice_no}' can only be posted from draft.");
        }

        try {
            SalesInvoiceService::post($salesInvoice);
        } catch (InsufficientStockException|DayClosedException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditService::posted($salesInvoice, "Sales invoice '{$salesInvoice->invoice_no}' posted. Stock issued.");

        return redirect()->route('sales-invoices.show', $salesInvoice)
            ->with('success', "Sales invoice '{$salesInvoice->invoice_no}' posted. Stock has been issued.");
    }

    /**
     * Void a posted invoice: the stock-out is reversed.
     */
    public function void(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $this->authorize('void', $salesInvoice);

        if ($salesInvoice->status !== SalesInvoiceStatus::Posted) {
            return back()->with('error', 'Only posted invoices can be voided.');
        }

        $reason = $request->string('void_reason')->toString();

        SalesInvoiceService::void($salesInvoice, $reason ?: null);

        AuditService::voided($salesInvoice, "Sales invoice '{$salesInvoice->invoice_no}' voided. Stock restored.");

        return redirect()->route('sales-invoices.show', $salesInvoice)
            ->with('success', "Sales invoice '{$salesInvoice->invoice_no}' voided. Stock has been restored.");
    }

    /**
     * Product search for the invoice/POS item picker.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesInvoice::class);

        $term = $request->string('search')->toString();

        $units = Unit::query()
            ->with('product:id,name,barcode,sale_price')
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $q) use ($term) {
                    $q->where('product_name', 'like', "%{$term}%")
                        ->orWhere('units.note', 'like', "%{$term}%")
                        ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%"));
                });
            })
            ->orderBy('product_name')
            ->limit(20)
            ->get();

        $quantities = StockService::scopedQuantitiesFor($units, $request->user());

        return response()->json([
            'success' => true,
            'units' => $units->map(fn (Unit $unit) => [
                'id' => (int) $unit->id,
                'display_name' => $unit->displayName(),
                'unit' => $unit->unit,
                'quantity' => (float) ($quantities[$unit->id] ?? 0),
                'sale_price' => (float) ($unit->product?->sale_price ?? 0),
                'barcode' => $unit->product?->barcode,
            ])->values()->all(),
        ]);
    }

    /**
     * Export the filtered sales invoice register to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', SalesInvoice::class);

        $search = $request->string('search')->toString();
        $columns = $request->string('columns')->toString();

        $filters = array_filter([
            'customer_id' => $request->input('customer_id'),
            'status' => $request->string('status')->toString(),
            'payment_status' => $request->string('payment_status')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'location_id' => $request->input('location_id'),
            'branch_id' => $request->input('branch_id'),
        ], fn ($value) => $value !== null && $value !== '');

        $filename = 'sales_invoices_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new SalesInvoiceExport($search ?: null, $columns ?: null, $filters, $request->user()),
            $filename
        );
    }

    /**
     * Base query for the register with all filters applied.
     * The register only lists order-generated invoices — POS/direct sales
     * are shown on the Collections page. Outlet Managers only see invoices
     * of their branch/location.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = SalesInvoice::query()
            ->with(['customer', 'location', 'branch'])
            ->whereNotNull('sales_order_id')
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->string('date_to')->toString()));

        $query = SalesScope::scopeQuery($query, $request->user());

        return $query
            ->when($request->filled('location_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('location_id', (int) $request->input('location_id')))
            ->when($request->filled('branch_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')));
    }

    /**
     * Base query for the Collections list: all distributor invoices —
     * order-generated and POS/direct alike — with the simple page filters
     * applied and the usual branch/location scoping for restricted users.
     */
    private function distributorQuery(Request $request): Builder
    {
        $query = SalesInvoice::query()
            ->with(['customer' => fn ($q) => $q->withTrashed(), 'location', 'items.unit.product'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()));

        return SalesScope::scopeQuery($query, $request->user())
            ->when($request->filled('branch_id') && ! SalesScope::isRestricted($request->user()), function (Builder $q) use ($request): void {
                // POS/walk-in invoices carry no branch of their own: their
                // location's branch counts as the branch for filtering.
                $branchId = (int) $request->input('branch_id');

                $q->where(function (Builder $inner) use ($branchId): void {
                    $inner->where('branch_id', $branchId)
                        ->orWhereIn(
                            'location_id',
                            Location::query()->where('branch_id', $branchId)->pluck('id')
                        );
                });
            });
    }

    /**
     * Summary numbers for the register cards and table footer.
     *
     * @return array{total: int, draft: int, posted: int, voided: int, due: int, partial: int, paid: int, grand_total: float, paid_amount: float, due_amount: float}
     */
    private function totals(Request $request): array
    {
        $base = $this->filteredQuery($request);

        $statusCounts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $paymentCounts = (clone $base)->toBase()
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        $sums = (clone $base)->toBase()
            ->selectRaw('coalesce(sum(grand_total), 0) as grand_total, coalesce(sum(paid_amount), 0) as paid_amount, coalesce(sum(due_amount), 0) as due_amount')
            ->first();

        return [
            'total' => (int) $statusCounts->sum(),
            'draft' => (int) ($statusCounts['draft'] ?? 0),
            'posted' => (int) ($statusCounts['posted'] ?? 0),
            'voided' => (int) ($statusCounts['voided'] ?? 0),
            'due' => (int) ($paymentCounts['due'] ?? 0),
            'partial' => (int) ($paymentCounts['partial'] ?? 0),
            'paid' => (int) ($paymentCounts['paid'] ?? 0),
            'grand_total' => round((float) ($sums->grand_total ?? 0), 2),
            'paid_amount' => round((float) ($sums->paid_amount ?? 0), 2),
            'due_amount' => round((float) ($sums->due_amount ?? 0), 2),
        ];
    }

    /**
     * Persist the invoice line items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(SalesInvoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = round((float) $item['unit_price'], 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);

            $invoice->items()->create([
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'subtotal' => round(max(0, $quantity * $unitPrice - $discount), 2),
            ]);
        }
    }

    /**
     * Items submitted before a validation redirect, or the stored items of an
     * invoice being edited, shaped for the form grid.
     *
     * @param  array<int, array<string, mixed>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    private function oldItems(?SalesInvoice $invoice = null): array
    {
        $items = old('items');

        if (is_array($items) && $items !== []) {
            return array_values(array_map(fn (array $row) => [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'product_name' => $this->unitName((int) ($row['unit_id'] ?? 0)),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'discount' => (float) ($row['discount'] ?? 0),
            ], $items));
        }

        if ($invoice !== null) {
            return $invoice->items->map(fn ($item) => [
                'unit_id' => (int) $item->unit_id,
                'product_name' => $item->unit?->displayName() ?? 'Unknown product',
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
            ])->values()->all();
        }

        return [];
    }

    /**
     * Products of a sales order shaped as prefilled invoice lines.
     *
     * @param  array<int, array<string, mixed>>  $overrideRows
     * @return array<int, array<string, mixed>>
     */
    private function itemRows(?SalesOrder $order, array $overrideRows = []): array
    {
        if ($overrideRows !== []) {
            return $overrideRows;
        }

        if ($order === null) {
            return [];
        }

        $order->loadMissing('items.unit');

        return $order->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount' => (float) $item->discount,
        ])->values()->all();
    }

    /**
     * Display name of a stock row for redisplayed old input.
     */
    private function unitName(int $unitId): string
    {
        return Unit::query()->find($unitId)?->displayName() ?? 'Unknown product';
    }
}
