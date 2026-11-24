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
use App\Services\DayClosingService;
use App\Services\MoneyMath;
use App\Services\SalesInvoiceService;
use App\Services\SalesScope;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return view('pos.index', [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'mobile']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'defaultLocationId' => Auth::user()?->branch_id ?: Location::query()->active()->orderBy('name')->value('id'),
        ]);
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
            [$invoice, $change] = DB::transaction(function () use ($request, $validated, $locationId) {

                // Concurrency: sort unit IDs and lock rows in consistent order to prevent deadlocks & race conditions
                $unitIds = collect($validated['items'])->pluck('unit_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                Unit::query()->whereIn('id', $unitIds)->orderBy('id')->lockForUpdate()->get();

                $cartDiscount = MoneyMath::clean((string) ($validated['cart_discount'] ?? '0.00'));
                $taxRate = MoneyMath::clean((string) ($validated['tax_rate'] ?? '0.00'));

                $invoice = SalesInvoice::create([
                    'invoice_date' => now(),
                    'customer_id' => $validated['customer_id'] ?? null,
                    'location_id' => $locationId,
                    // The creator's branch: the branch of their assigned outlet.
                    'branch_id' => $request->user()?->branch?->branch_id,
                    'status' => SalesInvoiceStatus::Draft,
                    'tax_rate' => $taxRate,
                    'cart_discount' => $cartDiscount,
                    'note' => $validated['note'] ?? 'POS sale',
                    'created_by' => $request->user()?->id,
                ]);

                foreach ($validated['items'] as $item) {
                    $quantity = (int) $item['quantity'];
                    $unitPrice = MoneyMath::clean((string) $item['unit_price']);
                    $itemDiscount = MoneyMath::clean((string) ($item['discount'] ?? '0.00'));
                    $itemSubtotal = MoneyMath::max('0.00', MoneyMath::sub(MoneyMath::mul($quantity, $unitPrice), $itemDiscount));

                    $invoice->items()->create([
                        'unit_id' => (int) $item['unit_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $itemDiscount,
                        'subtotal' => $itemSubtotal,
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
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('create', SalesInvoice::class);

        $term = $request->string('search')->toString();
        $user = $request->user();
        $restricted = BranchScope::isRestricted($user);

        $query = Unit::query()
            ->with('product:id,name,barcode,sale_price')
            ->search($term)
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->limit(20);

        $branchQuantities = [];

        if ($restricted) {
            $branchId = BranchScope::locationId($user);
            $branchQuantities = $branchId === null ? [] : StockService::quantitiesByUnitAt($branchId);
            $inStock = array_keys(array_filter($branchQuantities, fn (int $qty): bool => $qty > 0));
            $query->whereIn('units.id', $inStock);
        }

        $units = $query->get();

        // Super Admin: total stock plus a per-branch availability breakdown.
        $availability = $restricted
            ? collect()
            : StockService::availabilityAcross($units)->groupBy('unit_id');

        return response()->json([
            'success' => true,
            'units' => $units->map(function (Unit $unit) use ($restricted, $branchQuantities, $availability) {
                $quantity = $restricted
                    ? (int) ($branchQuantities[$unit->id] ?? 0)
                    : (int) $unit->quantity;

                $branches = [];
                if (! $restricted && $availability->has($unit->id)) {
                    $branches = $availability->get($unit->id)
                        ->map(fn (array $row): array => [
                            'name' => $row['location_name'],
                            'quantity' => $row['quantity'],
                        ])
                        ->values()
                        ->all();
                }

                return [
                    'id' => (int) $unit->id,
                    'display_name' => $unit->displayName(),
                    'unit' => $unit->unit,
                    'quantity' => $quantity,
                    'sale_price' => (float) ($unit->product?->sale_price ?? 0),
                    'barcode' => $unit->product?->barcode,
                    'branches' => $branches,
                ];
            })->values()->all(),
        ]);
    }
}
