<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\SalesOrder\ConfirmSalesOrderPaymentRequest;
use App\Http\Requests\SalesOrder\StoreSalesOrderRequest;
use App\Http\Requests\SalesOrder\UpdateSalesOrderRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CustomerResolver;
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

class SalesOrderController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['order_date', 'order_no', 'status', 'grand_total', 'expected_date'];

    /**
     * Display the sales order register.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesOrder::class);

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

        return view('sales-orders.index', [
            'orders' => $orders,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $this->totals($request),
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => SalesOrderStatus::options(),
        ]);
    }

    /**
     * Show the sales order creation form.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', SalesOrder::class);

        return view('sales-orders.create', [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'rows' => $this->oldItems(),
            'defaultLocationId' => old('location_id') ?: Auth::user()?->branch_id,
        ]);
    }

    /**
     * Store a newly created sales order.
     *
     * Saving the order only captures it: no invoice exists yet and no stock
     * moves. The sale takes effect on the "Confirm Payment & Generate
     * Invoice" step — the business never sells on credit, so the invoice is
     * generated (and its stock-out posted) exactly once, when the payment
     * has been confirmed in full.
     */
    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only create orders for your own branch/location.');
        }

        $order = DB::transaction(function () use ($request, $validated) {
            $customer = CustomerResolver::resolveOrCreate(
                self::customerId($validated),
                $validated['customer_name'] ?? null,
                $validated['mobile_no'] ?? null,
                $validated['customer_email'] ?? null,
                $request->user()?->id
            );

            $order = SalesOrder::create([
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'customer_id' => $customer->id,
                'location_id' => $validated['location_id'],
                // The creator's branch: the branch of their assigned outlet.
                'branch_id' => $request->user()?->branch?->branch_id,
                'status' => SalesOrderStatus::Draft,
                'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $this->saveItems($order, $validated['items']);
            $this->recalculate($order, (float) ($validated['tax_rate'] ?? 0));

            return $order;
        });

        AuditService::created($order, "Sales order '{$order->order_no}' created.");

        return redirect()->route('sales-orders.show', $order)
            ->with('success', "Sales order '{$order->order_no}' created. Confirm the payment to generate the invoice.");
    }

    /**
     * Display the specified sales order.
     */
    public function show(SalesOrder $salesOrder): View
    {
        $this->authorize('view', $salesOrder);

        $salesOrder->load(['items.unit', 'customer', 'location', 'branch', 'creator', 'invoices']);

        return view('sales-orders.show', [
            'order' => $salesOrder,
        ]);
    }

    /**
     * Show the form for editing a sales order.
     */
    public function edit(SalesOrder $salesOrder): View|RedirectResponse
    {
        $this->authorize('update', $salesOrder);

        if (! $salesOrder->status->isEditable()) {
            return redirect()->route('sales-orders.show', $salesOrder)
                ->with('error', "Sales order '{$salesOrder->order_no}' can no longer be edited.");
        }

        $salesOrder->load('items');

        return view('sales-orders.edit', [
            'order' => $salesOrder,
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'rows' => $this->oldItems($salesOrder),
        ]);
    }

    /**
     * Update a sales order.
     */
    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        if (! $salesOrder->status->isEditable()) {
            return redirect()->route('sales-orders.show', $salesOrder)
                ->with('error', "Sales order '{$salesOrder->order_no}' can no longer be edited.");
        }

        $original = $salesOrder->getOriginal();
        $validated = $request->validated();

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only keep orders on your own branch/location.');
        }

        DB::transaction(function () use ($salesOrder, $validated, $request) {
            $customer = CustomerResolver::resolveOrCreate(
                self::customerId($validated),
                $validated['customer_name'] ?? null,
                $validated['mobile_no'] ?? null,
                $validated['customer_email'] ?? null,
                $request->user()?->id
            );

            $salesOrder->items()->delete();
            $this->saveItems($salesOrder, $validated['items']);

            $salesOrder->update([
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'customer_id' => $customer->id,
                'location_id' => $validated['location_id'],
                'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
                'note' => $validated['note'] ?? null,
            ]);

            $this->recalculate($salesOrder, (float) ($validated['tax_rate'] ?? 0));
        });

        AuditService::updated($salesOrder, $original, "Sales order '{$salesOrder->order_no}' updated.");

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', "Sales order '{$salesOrder->order_no}' updated successfully.");
    }

    /**
     * Remove a sales order that was never invoiced.
     */
    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('delete', $salesOrder);

        if ($salesOrder->status === SalesOrderStatus::Invoiced) {
            return back()->with('error', "Sales order '{$salesOrder->order_no}' has an invoice and cannot be deleted.");
        }

        DB::transaction(function () use ($salesOrder) {
            $salesOrder->items()->delete();
            $salesOrder->delete();
        });

        AuditService::deleted($salesOrder, "Sales order '{$salesOrder->order_no}' deleted.");

        return redirect()->route('sales-orders.index')
            ->with('success', "Sales order '{$salesOrder->order_no}' deleted successfully.");
    }

    /**
     * Confirm the payment and generate the invoice — the order's "Confirm
     * Payment & Generate Invoice" step. The payment must cover the order
     * total in full ({@see ConfirmSalesOrderPaymentRequest}); only then is
     * the invoice generated, settled as PAID (due = 0) and posted, which is
     * the sale's single stock effect. Until this step the order holds no
     * invoice and never touches stock.
     */
    public function confirm(ConfirmSalesOrderPaymentRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('update', $salesOrder);

        if (! in_array($salesOrder->status, [SalesOrderStatus::Draft, SalesOrderStatus::Confirmed], true)) {
            return back()->with('error', "Sales order '{$salesOrder->order_no}' is already invoiced or cancelled.");
        }

        $validated = $request->validated();
        $original = $salesOrder->getOriginal();

        try {
            [$order, $invoice] = DB::transaction(function () use ($salesOrder, $validated, $request) {
                $invoice = SalesInvoiceService::generateFromOrder($salesOrder, $request->user()?->id);

                $salesOrder->update([
                    'payment_method' => $validated['payment_method'],
                    'paid_at' => now(),
                ]);

                return [$salesOrder, $invoice];
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::updated($order, $original, "Payment confirmed for sales order '{$order->order_no}'.");
        AuditService::created($invoice, "Sales invoice '{$invoice->invoice_no}' generated from sales order '{$order->order_no}'.");
        AuditService::posted($invoice, "Sales invoice '{$invoice->invoice_no}' posted. Stock issued.");

        return redirect()->route('sales-orders.show', $order)
            ->with('success', "Payment confirmed. Invoice '{$invoice->invoice_no}' generated — paid in full.");
    }

    /**
     * Cancel an order: its generated invoice is voided and the stock comes
     * back. A cancelled sale leaves nothing behind — no open invoice, no
     * issued stock.
     */
    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('update', $salesOrder);

        if ($salesOrder->status === SalesOrderStatus::Cancelled) {
            return back()->with('error', "Sales order '{$salesOrder->order_no}' is already cancelled.");
        }

        $original = $salesOrder->getOriginal();

        DB::transaction(function () use ($salesOrder) {
            $salesOrder->update(['status' => SalesOrderStatus::Cancelled]);

            // Posted invoices bring the stock back on void; draft ones never
            // touched it and are simply settled.
            foreach ($salesOrder->invoices as $invoice) {
                if ($invoice->status !== SalesInvoiceStatus::Voided) {
                    SalesInvoiceService::void($invoice, "Sales order {$salesOrder->order_no} cancelled.");
                }
            }
        });

        AuditService::updated($salesOrder, $original, "Sales order '{$salesOrder->order_no}' cancelled.");

        $message = $salesOrder->invoices()->exists()
            ? "Sales order '{$salesOrder->order_no}' cancelled. Its invoice was voided and stock restored."
            : "Sales order '{$salesOrder->order_no}' cancelled.";

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', $message);
    }

    /**
     * Product search for the order item picker: matches product name,
     * product code, model and barcode (same search as the purchase form),
     * scoped to the stock available at the selected business location.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesOrder::class);

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $units = Unit::query()
            ->with('product')
            ->search($request->string('search')->toString())
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->limit(20)
            ->get();

        $quantities = StockService::scopedQuantitiesFor($units, $request->user());

        return response()->json([
            'success' => true,
            'units' => $units->map(fn (Unit $unit) => [
                'id' => (int) $unit->id,
                'display_name' => $unit->displayName(),
                'unit' => $unit->unit,
                'quantity' => $locationId !== null
                    ? self::resolveLocationStock($unit, $locationId)
                    : (float) ($quantities[$unit->id] ?? 0),
                'sale_price' => (float) ($unit->product?->sale_price ?? 0),
                'barcode' => $unit->product?->barcode,
            ])->values()->all(),
        ]);
    }

    /**
     * Resolve the available quantity of a unit at a given business location.
     */
    public static function resolveLocationStock(Unit $unit, ?int $locationId): int
    {
        if ($locationId === null) {
            return (int) $unit->quantity;
        }

        $location = Location::query()->find($locationId);
        $isWarehouse = $location?->type === LocationType::Warehouse;

        // Check whether any location-specific movements exist for this unit across the system
        $hasAnyLocationMovements = StockMovement::query()
            ->where('unit_id', $unit->id)
            ->whereNotNull('location_id')
            ->exists();

        // If no location-specific transfers/movements have been posted yet across the system,
        // fallback to the global opening stock on the unit.
        if (! $hasAnyLocationMovements) {
            return (int) $unit->quantity;
        }

        // Location-specific movements exist:
        // For a warehouse, sum warehouse movements plus unassigned opening movements.
        // For non-warehouse locations, sum movements for this specific location.
        $hasLocationMovements = StockMovement::query()
            ->where('unit_id', $unit->id)
            ->when($isWarehouse, function ($query) use ($locationId) {
                $query->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                });
            }, function ($query) use ($locationId) {
                $query->where('location_id', $locationId);
            })
            ->exists();

        if (! $hasLocationMovements) {
            return $isWarehouse ? (int) $unit->quantity : 0;
        }

        $stock = (int) StockMovement::query()
            ->where('unit_id', $unit->id)
            ->when($isWarehouse, function ($query) use ($locationId) {
                $query->where(function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                });
            }, function ($query) use ($locationId) {
                $query->where('location_id', $locationId);
            })
            ->sum('quantity');

        return max(0, $stock);
    }

    /**
     * Customer lookup for the order form's customer picker: matches name,
     * phone or customer code and returns each phone number and email so the
     * form can autofill them. Typed names that match nothing become new
     * customers on submit (see {@see CustomerResolver}).
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesOrder::class);

        $term = $request->string('q')->toString();

        $customers = Customer::query()
            ->active()
            ->when($term !== '', fn (Builder $query) => $query->search($term))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'mobile', 'tel_no', 'email']);

        return response()->json([
            'success' => true,
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'tel_no' => $customer->tel_no,
                'email' => $customer->email,
            ])->values()->all(),
        ]);
    }

    /**
     * Base query for the register with all filters applied.
     * Outlet Managers only see orders of their branch/location.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = SalesOrder::query()
            ->with(['customer', 'location', 'branch'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('order_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('order_date', '<=', $request->string('date_to')->toString()));

        $query = SalesScope::scopeQuery($query, $request->user());

        return $query
            ->when($request->filled('location_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('location_id', (int) $request->input('location_id')))
            ->when($request->filled('branch_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')));
    }

    /**
     * Summary numbers for the register cards.
     *
     * @return array{total: int, draft: int, confirmed: int, invoiced: int, cancelled: int, grand_total: float}
     */
    private function totals(Request $request): array
    {
        $base = $this->filteredQuery($request);

        $counts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $grandTotal = (float) (clone $base)->toBase()->sum('grand_total');

        return [
            'total' => (int) $counts->sum(),
            'draft' => (int) ($counts['draft'] ?? 0),
            'confirmed' => (int) ($counts['confirmed'] ?? 0),
            'invoiced' => (int) ($counts['invoiced'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * Selected customer id of the form payload, or null when the customer is
     * being typed in as a new name.
     *
     * @param  array<string, mixed>  $validated
     */
    private static function customerId(array $validated): ?int
    {
        return (int) ($validated['customer_id'] ?? 0) ?: null;
    }

    /**
     * Persist the order line items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(SalesOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = round((float) $item['unit_price'], 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);

            $order->items()->create([
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'subtotal' => round(max(0, $quantity * $unitPrice - $discount), 2),
            ]);
        }
    }

    /**
     * Recompute the order totals from its stored line items.
     */
    private function recalculate(SalesOrder $order, float $taxRate): void
    {
        $order->load('items');

        $discount = round((float) $order->items->sum('discount'), 2);
        $subtotal = round((float) $order->items->sum('subtotal'), 2);
        $taxAmount = round($subtotal * $taxRate / 100, 2);

        $order->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'grand_total' => round($subtotal + $taxAmount, 2),
        ]);
    }

    /**
     * Items submitted before a validation redirect, or the stored items of an
     * order being edited, shaped for the form grid.
     *
     * @return array<int, array<string, mixed>>
     */
    private function oldItems(?SalesOrder $order = null): array
    {
        $items = old('items');

        if (is_array($items) && $items !== []) {
            $locationId = old('location_id') ? (int) old('location_id') : null;

            return array_values(array_map(function (array $row) use ($locationId) {
                $unit = Unit::query()->find((int) ($row['unit_id'] ?? 0));

                return [
                    'unit_id' => (int) ($row['unit_id'] ?? 0),
                    'product_name' => $unit?->displayName() ?? 'Unknown product',
                    'unit' => $unit?->unit ?? 'Pcs',
                    'stock' => $unit ? self::resolveLocationStock($unit, $locationId) : 0,
                    'quantity' => (int) ($row['quantity'] ?? 1),
                    'unit_price' => (float) ($row['unit_price'] ?? 0),
                    'discount' => (float) ($row['discount'] ?? 0),
                ];
            }, $items));
        }

        if ($order !== null) {
            return $order->items->map(fn ($item) => [
                'unit_id' => (int) $item->unit_id,
                'product_name' => $item->unit?->displayName() ?? 'Unknown product',
                'unit' => $item->unit?->unit ?? 'Pcs',
                'stock' => $item->unit ? self::resolveLocationStock($item->unit, (int) $order->location_id) : 0,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
            ])->values()->all();
        }

        return [];
    }
}
