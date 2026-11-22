<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\BranchScope;
use App\Services\LocationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StockController extends Controller
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
     *
     * Strict branch isolation: Super Admin gets the full shared overview
     * (plus the location and branch filters); every other user sees only
     * their own branch's stock — the quantities, valuation and alerts are
     * derived from that branch's movement ledger (StockService), and only
     * stock lines their branch has handled are listed. Restricted users
     * without a branch assignment see nothing.
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

        $user = $request->user();
        $locationFilter = $this->resolveLocationFilter($request);
        $branchLocationIds = $this->resolveBranchLocationIds($request);

        // Branch users read every quantity from their own branch's ledger.
        $branchScoped = BranchScope::isRestricted($user);
        $branchId = BranchScope::locationId($user);
        $qtyExpr = 'units.quantity';

        $base = Unit::query()
            ->with(['product.brand', 'product.category'])
            ->join('products', 'products.id', '=', 'units.product_id')
            ->whereNull('products.deleted_at')
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('products.brand_id', $request->integer('brand_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('products.category_id', $request->integer('category_id')));

        if ($branchScoped) {
            // No branch assignment: no operational data at all.
            if ($branchId === null) {
                $base->whereRaw('1 = 0');
            } else {
                $qtyExpr = 'COALESCE(branch_stock.quantity, 0)';

                $base->joinSub(
                    StockMovement::query()
                        ->selectRaw('unit_id, COALESCE(SUM(quantity), 0) as quantity')
                        ->where('location_id', $branchId)
                        ->groupBy('unit_id'),
                    'branch_stock',
                    fn ($join) => $join->on('branch_stock.unit_id', '=', 'units.id')
                );
            }
        }

        $base
            ->when($request->string('stock_status')->toString() === 'out', fn ($q) => $q->whereRaw("{$qtyExpr} <= 0"))
            ->when($request->string('stock_status')->toString() === 'low', fn ($q) => $q->whereRaw("{$qtyExpr} > 0")->whereRaw("{$qtyExpr} <= ?", [$threshold]))
            ->when($request->string('stock_status')->toString() === 'in', fn ($q) => $q->whereRaw("{$qtyExpr} > ?", [$threshold]));

        $summary = (clone $base)
            ->selectRaw("
                COUNT(*) AS skus,
                COALESCE(SUM({$qtyExpr}), 0) AS total_quantity,
                COALESCE(SUM({$qtyExpr} * COALESCE(products.purchase_price, 0)), 0) AS stock_value,
                SUM(CASE WHEN {$qtyExpr} <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN {$qtyExpr} > 0 AND {$qtyExpr} <= ? THEN 1 ELSE 0 END) AS low_stock
            ", [$threshold])
            ->first();

        $stockLevels = (clone $base)
            ->select('units.*', 'products.purchase_price AS unit_cost')
            ->when($branchScoped && $branchId !== null, fn ($q) => $q->selectRaw("{$qtyExpr} AS quantity"))
            ->tap(fn ($q) => match ($sort) {
                'product_name' => $q->orderBy('products.name', $direction),
                'unit_cost' => $q->orderBy('products.purchase_price', $direction),
                'stock_value' => $q->orderByRaw("{$qtyExpr} * COALESCE(products.purchase_price, 0) ".$direction),
                default => $q->orderByRaw("{$qtyExpr} ".$direction),
            })
            ->paginate($perPage)
            ->withQueryString();

        return view('stocks.index', [
            'stockLevels' => $stockLevels,
            'summary' => $summary,
            'threshold' => $threshold,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'locations' => BranchScope::scopeQuery(Location::query()->active(), $user, 'id')->orderBy('name')->get(),
            'locationFilter' => $locationFilter,
            'branches' => BranchScope::isUnrestricted($user)
                ? Branch::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'scopedMovements' => $this->scopedMovements($locationFilter, $branchLocationIds, $base),
        ]);
    }

    /**
     * Stock ledger history for one product variant (modal data).
     */
    public function movements(Request $request, Unit $unit): JsonResponse
    {
        $this->authorize('inventory.view');

        $locationFilter = $this->resolveLocationFilter($request);
        $branchLocationIds = $this->resolveBranchLocationIds($request);

        $movements = $unit->stockMovements()
            ->latest('id')
            ->when($locationFilter !== null, fn ($q) => $q->where('location_id', $locationFilter))
            ->when($branchLocationIds !== null, fn ($q) => $q->whereIn('location_id', $branchLocationIds))
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

    /**
     * The location the current view is scoped to, or null for the shared
     * overview. Restricted users are locked to their own location; a
     * restricted user without one matches nothing until the branch-aware
     * scope lands centrally.
     */
    private function resolveLocationFilter(Request $request): ?int
    {
        $user = $request->user();

        if (LocationScope::isRestricted($user)) {
            return $user->branch_id !== null ? (int) $user->branch_id : -1;
        }

        return $request->filled('location_id') ? $request->integer('location_id') : null;
    }

    /**
     * Location ids of the branch picked in the overview filter, or null when no
     * branch filter applies. Only unrestricted users may filter by branch;
     * restricted users keep their automatic location scoping. A branch without
     * locations resolves to an empty list and therefore matches nothing.
     *
     * @return array<int, int>|null
     */
    private function resolveBranchLocationIds(Request $request): ?array
    {
        if (LocationScope::isRestricted($request->user()) || ! $request->filled('branch')) {
            return null;
        }

        return Location::query()
            ->where('branch_id', $request->integer('branch'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Recent stock movements of the selected scope (latest first), limited to
     * the stock lines matching the current table filters.
     *
     * @param  Builder<int, Unit>  $stockQuery
     * @param  array<int, int>|null  $branchLocationIds
     * @return Collection<int, StockMovement>
     */
    private function scopedMovements(?int $locationFilter, ?array $branchLocationIds, $stockQuery): Collection
    {
        return StockMovement::query()
            ->with(['unit.product', 'location'])
            ->whereIn('unit_id', (clone $stockQuery)->select('units.id'))
            ->when($locationFilter !== null, fn ($q) => $q->where('location_id', $locationFilter))
            ->when($branchLocationIds !== null, fn ($q) => $q->whereIn('location_id', $branchLocationIds))
            ->latest('id')
            ->limit(15)
            ->get();
    }
}
