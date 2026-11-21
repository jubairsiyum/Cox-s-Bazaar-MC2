<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesInvoiceStatus;
use App\Exceptions\InsufficientStockException;
use App\Helpers\Money;
use App\Http\Requests\Pos\StorePosSaleRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
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

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only sell from your own branch/location.');
        }

        try {
            [$invoice, $change] = DB::transaction(function () use ($request, $validated) {
                $invoice = SalesInvoice::create([
                    'invoice_date' => now(),
                    'customer_id' => $validated['customer_id'] ?? null,
                    'location_id' => $validated['location_id'],
                    // The creator's branch: the branch of their assigned outlet.
                    'branch_id' => $request->user()?->branch?->branch_id,
                    'status' => SalesInvoiceStatus::Draft,
                    'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
                    'note' => $validated['note'] ?? 'POS sale',
                    'created_by' => $request->user()?->id,
                ]);

                foreach ($validated['items'] as $item) {
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

                SalesInvoiceService::recalculate($invoice, (float) ($validated['tax_rate'] ?? 0));

                // POS sales are cash-settled: change is handed back on the spot
                // and never tracked as a customer advance.
                $tendered = round((float) $validated['paid_amount'], 2);
                $change = round(max(0, $tendered - (float) $invoice->grand_total), 2);

                $invoice->update([
                    'paid_amount' => round(min($tendered, (float) $invoice->grand_total), 2),
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
                .($change > 0 ? ' · Change returned: '.Money::format($change) : '')
                .'.');
    }

    /**
     * Product search for the POS picker: name, variant, barcode and price.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('create', SalesInvoice::class);

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
}
