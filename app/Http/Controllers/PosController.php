<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesInvoiceStatus;
use App\Exceptions\DayClosedException;
use App\Exceptions\InsufficientStockException;
use App\Helpers\Money;
use App\Http\Requests\Pos\StorePosSaleRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\CustomerResolver;
use App\Services\DayClosingService;
use App\Services\MoneyMath;
use App\Services\SalesInvoiceService;
use App\Services\SalesScope;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quick-sale terminal: the sale is created AND posted in one step so stock
 * leaves immediately and the customer can walk out with the goods.
 */
class PosController extends Controller
{
    /**
     * Render the POS terminal.
     */
    public function index(Request $request): View
    {
        $this->authorize('create', SalesInvoice::class);

        $user = $request->user();
        $user = $request->user();
        // The Super Admin's terminal is the Main Warehouse, where the whole
        // catalog is stocked. Other users are pinned to their assigned outlet.
        $defaultLocationId = BranchScope::isRestricted($user)
            ? ($user->branch_id ?: Location::query()->active()->orderBy('name')->value('id'))
            : (Location::mainWarehouse()?->id ?? Location::query()->active()->orderBy('name')->value('id'));

        // A rejected sale flashes the submitted input back to the terminal.
        // Only the Super Admin may switch locations, so everyone else is
        // pinned to their assigned outlet even when a different id was posted.
        $restoreLocationId = BranchScope::isRestricted($user)
            ? $defaultLocationId
            : (is_numeric(old('location_id')) ? (int) old('location_id') : $defaultLocationId);

        return view('pos.index', [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'mobile']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'defaultLocationId' => $defaultLocationId,
            'restoreLocationId' => $restoreLocationId,
            'cartRows' => $this->oldCartRows($restoreLocationId),
            'oldPaidAmount' => (float) old('paid_amount', 0),
            'oldCartDiscount' => (float) old('cart_discount', 0),
            'hasOldCartDiscount' => filled(old('cart_discount')),
        ]);
    }

    /**
     * Cart lines to redisplay after the server rejects a sale: the submitted
     * lines enriched with the product name and the stock/branch context of the
     * location they were being sold from.
     *
     * @return array<int, array<string, mixed>>
     */
    private function oldCartRows(?int $locationId): array
    {
        $items = old('items');

        if (! is_array($items) || $items === []) {
            return [];
        }

        $units = Unit::query()
            ->with('product:id,name,storage,color')
            ->whereIn('id', collect($items)->pluck('unit_id')->map(fn (mixed $id): int => (int) $id)->all())
            ->get()
            ->keyBy('id');

        $quantities = $locationId !== null ? StockService::saleableQuantitiesAt($locationId) : [];
        $branchName = $locationId !== null ? Location::query()->whereKey($locationId)->value('name') : null;

        return array_values(array_map(function (array $item) use ($units, $quantities, $branchName, $locationId): array {
            $unitId = (int) ($item['unit_id'] ?? 0);
            $unit = $units->get($unitId);
            $stock = $locationId !== null
                ? (int) ($quantities[$unitId] ?? 0)
                : (int) ($unit?->quantity ?? 0);

            return [
                'unit_id' => $unitId,
                'product_name' => $unit?->displayName() ?? 'Unknown product',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'stock' => $stock,
                'branches' => $branchName === null ? [] : [['name' => $branchName, 'quantity' => $stock]],
            ];
        }, $items));
    }

    /**
     * Record a quick sale: one posted sales invoice with immediate payment.
     */
    public function store(StorePosSaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (BranchScope::isRestricted($user)) {
            $assignedLocationId = BranchScope::locationId($user);
            if ($assignedLocationId === null || (int) $validated['location_id'] !== $assignedLocationId) {
                return back()->withInput()->with('error', 'You can only sell from your own branch/location.');
            }
            $locationId = $assignedLocationId;
        } else {
            $locationId = (int) $validated['location_id'];
            if (! SalesScope::canAccess($user, $locationId)) {
                return back()->withInput()->with('error', 'You can only sell from your own branch/location.');
            }
        }

        try {
            DayClosingService::assertDayNotClosed($locationId, now());
        } catch (DayClosedException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        try {
            [$invoice, $change] = DB::transaction(function () use ($request, $validated, $locationId, $user) {

                // Concurrency: sort unit IDs and lock rows in consistent order to prevent deadlocks & race conditions
                $unitIds = collect($validated['items'])->pluck('unit_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $lockedUnits = Unit::query()
                    ->with('product:id,sale_price')
                    ->whereIn('id', $unitIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $canOverridePrice = $user->isSuperAdmin() || $user->can('pos.override-price');
                $cartDiscount = MoneyMath::clean((string) ($validated['cart_discount'] ?? '0.00'));
                $taxRate = MoneyMath::clean((string) ($validated['tax_rate'] ?? '0.00'));

                // A typed customer name (with or without a mobile number) is
                // resolved or created on the spot, so a new customer always
                // lands in the customer list, like the sales order form.
                $hasCustomerInput = isset($validated['customer_id']) || filled($validated['customer_name'] ?? null);
                $customer = $hasCustomerInput
                    ? CustomerResolver::resolveOrCreate(
                        isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                        $validated['customer_name'] ?? null,
                        $validated['mobile_no'] ?? null,
                        null,
                        $request->user()?->id,
                        'a POS sale'
                    )
                    : null;

                $invoice = SalesInvoice::create([
                    'invoice_date' => now(),
                    'customer_id' => $customer?->id,
                    'location_id' => $locationId,
                    // The creator's branch: the branch of their assigned outlet.
                    'branch_id' => $request->user()?->branch?->branch_id,
                    'status' => SalesInvoiceStatus::Draft,
                    'tax_rate' => $taxRate,
                    'cart_discount' => $cartDiscount,
                    'note' => $validated['note'] ?? 'POS sale',
                    'created_by' => $request->user()?->id,
                ]);

                $runningSubtotal = '0.00';

                foreach ($validated['items'] as $index => $item) {
                    $unitId = (int) $item['unit_id'];
                    $quantity = (int) $item['quantity'];
                    $catalogPrice = (float) ($lockedUnits->get($unitId)?->product?->sale_price ?? 0);
                    $unitPrice = (! $canOverridePrice && $catalogPrice > 0)
                        ? MoneyMath::clean((string) $catalogPrice)
                        : MoneyMath::clean((string) $item['unit_price']);

                    $lineGross = MoneyMath::mul($quantity, $unitPrice);
                    $itemDiscount = MoneyMath::clean((string) ($item['discount'] ?? '0.00'));

                    if (! $canOverridePrice && MoneyMath::gt($itemDiscount, $lineGross)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.discount" => 'Line discount cannot exceed the line amount.',
                        ]);
                    }

                    $itemSubtotal = MoneyMath::max('0.00', MoneyMath::sub($lineGross, $itemDiscount));
                    $runningSubtotal = MoneyMath::add($runningSubtotal, $itemSubtotal);

                    $invoice->items()->create([
                        'unit_id' => $unitId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $itemDiscount,
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                if (! $canOverridePrice && MoneyMath::gt($cartDiscount, $runningSubtotal)) {
                    throw ValidationException::withMessages([
                        'cart_discount' => 'Cart discount cannot exceed the sale subtotal.',
                    ]);
                }

                SalesInvoiceService::recalculate($invoice, (float) $taxRate);

                // POS sales are cash-settled: change is handed back on the spot
                // and never tracked as a customer advance.
                $tendered = MoneyMath::clean((string) $validated['paid_amount']);
                $grandTotal = MoneyMath::clean((string) $invoice->grand_total);
                $change = MoneyMath::max('0.00', MoneyMath::sub($tendered, $grandTotal));
                $paid = MoneyMath::min($tendered, $grandTotal);

                $invoice->update([
                    'paid_amount' => $paid,
                ]);

                SalesInvoiceService::post($invoice);

                return [$invoice, $change];
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::created($invoice, "POS sale '{$invoice->invoice_no}' created.");
        AuditService::posted($invoice, "POS sale '{$invoice->invoice_no}' posted. Stock issued.");

        return redirect()->route('pos.index')
            ->with('success', "Sale {$invoice->invoice_no} completed"
                .((float) $change > 0 ? ' · Change returned: '.Money::format($change) : '')
                .'.');
    }

    /**
     * Product search for the POS picker, branch-isolated: a branch user only
     * sees products stocked at their own branch (with that branch's quantity),
     * while the Super Admin sees every product plus the branches it is stocked
     * at.
     *
     * Sold-out rows (zero quantity at the branch) are returned with
     * `in_stock = false` so the picker can render them disabled instead of
     * hiding them, moving the availability check to selection time.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('create', SalesInvoice::class);

        $term = $request->string('search')->toString();
        $user = $request->user();
        $restricted = BranchScope::isRestricted($user);

        // The branch the sale will be made from: the assigned branch for
        // restricted users, the terminal's selected location for the Super
        // Admin. Availability is judged against it, so "Insufficient Stock"
        // surfaces at selection time instead of at sale time.
        $sellLocationId = $restricted
            ? BranchScope::locationId($user)
            : $this->requestedLocationId($request);

        $query = Unit::query()
            ->with([
                'product:id,name,barcode,sale_price,brand_id,photo',
                'product.brand:id,name,logo',
            ])
            ->search($term)
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->limit($term === '' ? 60 : 20);

        $branchQuantities = [];
        $branchName = null;

        if ($sellLocationId !== null) {
            $branchQuantities = StockService::saleableQuantitiesAt($sellLocationId);
            $branchName = Location::query()->whereKey($sellLocationId)->value('name');

            if ($restricted) {
                // Show every unit stocked at the branch — including sold-out
                // rows (net zero) so the picker can disable them rather than
                // hide them.
                $query->whereIn('units.id', array_keys($branchQuantities));
            }
        }

        $units = $query->get();

        // Per-branch availability shown beside each product: every branch for
        // the Super Admin, the user's own branch for everyone else.
        $availability = $restricted
            ? collect($units)->mapWithKeys(fn (Unit $unit): array => [
                $unit->id => collect([[
                    'location_name' => $branchName ?? 'Unassigned branch',
                    'quantity' => $branchQuantities[$unit->id] ?? 0,
                ]]),
            ])
            : StockService::availabilityAcross($units)->groupBy('unit_id');

        return response()->json([
            'success' => true,
            'units' => $units->map(function (Unit $unit) use ($branchQuantities, $branchName, $sellLocationId, $availability) {
                $quantity = $sellLocationId !== null
                    ? (int) ($branchQuantities[$unit->id] ?? 0)
                    : (int) $unit->quantity;

                $branches = $availability->has($unit->id)
                    ? $availability->get($unit->id)
                        ->map(fn (array $row): array => [
                            'name' => (string) $row['location_name'],
                            'quantity' => (int) $row['quantity'],
                        ])
                        ->values()
                        ->all()
                    : [];

                return [
                    'id' => (int) $unit->id,
                    'display_name' => $unit->displayName(),
                    'unit' => $unit->unit,
                    'quantity' => $quantity,
                    'in_stock' => $quantity > 0,
                    'sale_price' => (float) ($unit->product?->sale_price ?? 0),
                    'barcode' => $unit->product?->barcode,
                    'brand_name' => $unit->product?->brand?->name,
                    'brand_logo' => $unit->product?->brand?->logo_url,
                    'photo' => $unit->product?->photo_url,
                    'sell_location' => $branchName,
                    'branches' => $branches,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * The location the Super Admin is selling from on the terminal, or null
     * when none is selected (availability then falls back to global stock).
     */
    private function requestedLocationId(Request $request): ?int
    {
        $locationId = $request->input('location_id');

        if (! is_numeric($locationId)) {
            return null;
        }

        return Location::query()->active()->find((int) $locationId)?->id;
    }
}
