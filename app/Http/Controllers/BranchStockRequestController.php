<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockTransferStatus;
use App\Http\Requests\StockTransfer\StoreBranchStockRequestRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\StockRequestNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A branch asks another location (the warehouse or a brand shop) for stock.
 * The request is a stock transfer in the "requested" state: it moves no stock
 * until the source approves it through the regular transfer approval flow.
 */
class BranchStockRequestController extends Controller
{
    /**
     * Show the request form, optionally starting with the product the user
     * came from (?product=ID).
     */
    public function create(Request $request): View
    {
        $this->authorize('createRequest', StockTransfer::class);

        $branchId = BranchScope::locationId($request->user());
        abort_if($branchId === null, 403);

        return view('stock-transfers.request', [
            'branchName' => $request->user()->branch?->name ?? 'Your branch',
            'sources' => Location::query()
                ->active()
                ->when($branchId !== null, fn ($query) => $query->where('id', '!=', $branchId))
                ->orderBy('name')
                ->get(),
            'items' => old('items') !== null
                ? $this->hydrateItems(old('items'))
                : $this->initialItems($request->integer('product')),
        ]);
    }

    /**
     * Record the request: the requesting branch is always the acting user's
     * own location.
     */
    public function store(StoreBranchStockRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $branchId = BranchScope::locationId($request->user());

        $transfer = DB::transaction(function () use ($request, $validated, $branchId) {
            $transfer = StockTransfer::create([
                'transfer_date' => now(),
                'status' => StockTransferStatus::Requested,
                'is_request' => true,
                'from_location_id' => $validated['from_location_id'],
                'to_location_id' => $branchId,
                'shipping_charges' => 0,
                'total_amount' => 0,
                'additional_notes' => $validated['additional_notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $total = 0.0;

            foreach ($validated['items'] as $item) {
                $unit = Unit::query()->with('product')->findOrFail((int) $item['unit_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = round((float) ($unit->product?->purchase_price ?? 0), 2);
                $subtotal = round($quantity * $unitPrice, 2);

                $transfer->items()->create([
                    'unit_id' => $unit->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $transfer->update(['total_amount' => round($total, 2)]);

            return $transfer;
        });

        AuditService::created($transfer, "Stock request '{$transfer->reference_no}' created.");

        StockRequestNotifier::submitted($transfer);

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', "Stock request '{$transfer->reference_no}' sent. Stock moves once {$transfer->fromLocation?->name} approves it.");
    }

    /**
     * The starting line for a product chosen on the products page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialItems(int $productId): array
    {
        $product = $productId > 0 ? Product::query()->with('stockRow')->find($productId) : null;
        $unit = $product?->stockRow;

        if ($unit === null) {
            return [];
        }

        return [$this->presentItem($unit, 1)];
    }

    /**
     * Resolve product names for the request line rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateItems(array $rows): array
    {
        $units = Unit::query()
            ->with('product')
            ->whereIn('id', collect($rows)->pluck('unit_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        return collect($rows)
            ->map(fn (array $row) => $this->presentItem($units->get((int) ($row['unit_id'] ?? 0)), (int) ($row['quantity'] ?? 1)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(?Unit $unit, int $quantity): array
    {
        return [
            'unit_id' => $unit?->id ?? 0,
            'product_name' => $unit?->displayName() ?? 'Unknown product',
            'unit' => $unit?->unit ?? '',
            'quantity' => $quantity,
        ];
    }
}
