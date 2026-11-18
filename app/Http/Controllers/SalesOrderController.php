<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesOrderStatus;
use App\Http\Requests\SalesOrder\StoreSalesOrderRequest;
use App\Http\Requests\SalesOrder\UpdateSalesOrderRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\SalesScope;
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
            'defaultLocationId' => old('location_id') ?: Auth::user()?->location_id,
        ]);
    }

    /**
     * Store a newly created sales order.
     */
    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only create orders for your own branch/location.');
        }

        $order = DB::transaction(function () use ($request, $validated) {
            $order = SalesOrder::create([
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'customer_id' => $validated['customer_id'],
                'location_id' => $validated['location_id'],
                'branch_id' => $request->user()?->branch_id,
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
            ->with('success', "Sales order '{$order->order_no}' created successfully.");
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

        DB::transaction(function () use ($salesOrder, $validated) {
            $salesOrder->items()->delete();
            $this->saveItems($salesOrder, $validated['items']);

            $salesOrder->update([
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'customer_id' => $validated['customer_id'],
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
     * Confirm a draft order: it becomes ready for invoicing.
     */
    public function confirm(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('update', $salesOrder);

        if ($salesOrder->status !== SalesOrderStatus::Draft) {
            return back()->with('error', 'Only draft orders can be confirmed.');
        }

        $original = $salesOrder->getOriginal();

        $salesOrder->update(['status' => SalesOrderStatus::Confirmed]);

        AuditService::updated($salesOrder, $original, "Sales order '{$salesOrder->order_no}' confirmed.");

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', "Sales order '{$salesOrder->order_no}' confirmed.");
    }

    /**
     * Cancel an order that has no invoice yet.
     */
    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('update', $salesOrder);

        if (! $salesOrder->status->isEditable()) {
            return back()->with('error', "Sales order '{$salesOrder->order_no}' can no longer be cancelled.");
        }

        $original = $salesOrder->getOriginal();

        $salesOrder->update(['status' => SalesOrderStatus::Cancelled]);

        AuditService::updated($salesOrder, $original, "Sales order '{$salesOrder->order_no}' cancelled.");

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', "Sales order '{$salesOrder->order_no}' cancelled.");
    }

    /**
     * Product search for the order item picker (same shape as the invoice one).
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesOrder::class);

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

        return response()->json([
            'success' => true,
            'units' => $units->map(fn (Unit $unit) => [
                'id' => (int) $unit->id,
                'display_name' => $unit->displayName(),
                'unit' => $unit->unit,
                'quantity' => (float) $unit->quantity,
                'sale_price' => (float) ($unit->product?->sale_price ?? 0),
                'barcode' => $unit->product?->barcode,
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
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', (int) $request->input('location_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')));
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
            return array_values(array_map(fn (array $row) => [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'product_name' => Unit::query()->find((int) ($row['unit_id'] ?? 0))?->displayName() ?? 'Unknown product',
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'discount' => (float) ($row['discount'] ?? 0),
            ], $items));
        }

        if ($order !== null) {
            return $order->items->map(fn ($item) => [
                'unit_id' => (int) $item->unit_id,
                'product_name' => $item->unit?->displayName() ?? 'Unknown product',
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
            ])->values()->all();
        }

        return [];
    }
}
