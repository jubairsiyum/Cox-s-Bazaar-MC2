<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Columns that the stock list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['product_name', 'quantity', 'unit_cost', 'stock_value'];

    /**
     * Stock overview: every product variant with its quantity, valuation and
     * low/out-of-stock alerts (threshold from settings).
     */
    public function index(Request $request): View
    {
        $this->authorize('inventory.view');

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $threshold = (int) Setting::get('low_stock_threshold', 5);

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'product_name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $base = Unit::query()
            ->with(['product.brand', 'product.category'])
            ->join('products', 'products.id', '=', 'units.product_id')
            ->whereNull('products.deleted_at')
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('products.brand_id', $request->integer('brand_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('products.category_id', $request->integer('category_id')))
            ->when($request->string('stock_status')->toString() === 'out', fn ($q) => $q->where('units.quantity', '<=', 0))
            ->when($request->string('stock_status')->toString() === 'low', fn ($q) => $q->where('units.quantity', '>', 0)->where('units.quantity', '<=', $threshold))
            ->when($request->string('stock_status')->toString() === 'in', fn ($q) => $q->where('units.quantity', '>', $threshold));

        $summary = (clone $base)
            ->selectRaw('
                COUNT(*) AS skus,
                COALESCE(SUM(units.quantity), 0) AS total_quantity,
                COALESCE(SUM(units.quantity * COALESCE(products.purchase_price, 0)), 0) AS stock_value,
                SUM(CASE WHEN units.quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN units.quantity > 0 AND units.quantity <= ? THEN 1 ELSE 0 END) AS low_stock
            ', [$threshold])
            ->first();

        $stockLevels = (clone $base)
            ->select('units.*', 'products.purchase_price AS unit_cost')
            ->tap(fn ($q) => match ($sort) {
                'product_name' => $q->orderBy('products.name', $direction),
                'unit_cost' => $q->orderBy('products.purchase_price', $direction),
                'stock_value' => $q->orderByRaw('units.quantity * COALESCE(products.purchase_price, 0) '.$direction),
                default => $q->orderBy('units.quantity', $direction),
            })
            ->paginate($perPage)
            ->withQueryString();

        return view('inventory.index', [
            'stockLevels' => $stockLevels,
            'summary' => $summary,
            'threshold' => $threshold,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    /**
     * Stock ledger history for one product variant (modal data).
     */
    public function movements(Unit $unit): JsonResponse
    {
        $this->authorize('inventory.view');

        $movements = $unit->stockMovements()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($movement) => [
                'date' => $movement->created_at->format('d/m/Y H:i'),
                'type' => $movement->type->label(),
                'quantity' => (int) $movement->quantity,
                'quantity_after' => (int) $movement->quantity_after,
                'note' => $movement->note,
            ]);

        return response()->json([
            'success' => true,
            'product_name' => $unit->displayName(),
            'items' => $movements,
        ]);
    }
}
