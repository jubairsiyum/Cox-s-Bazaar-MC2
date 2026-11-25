<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\StockTransferStatus;
use App\Models\Brand;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Computes and caches dashboard KPIs, charts, alerts, and recent activity.
 *
 * Only primitive arrays and scalar values are stored in Cache so Laravel's
 * hardened `serializable_classes => false` setting works safely across
 * database, file, and Redis cache drivers.
 */
class DashboardMetricsService
{
    private const CACHE_VERSION_KEY = 'dashboard_metrics_version';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Get the current dashboard cache epoch version.
     */
    public static function version(): int
    {
        return (int) Cache::rememberForever(self::CACHE_VERSION_KEY, static fn (): int => 1);
    }

    /**
     * Invalidate all cached dashboard snapshots by bumping the cache epoch.
     */
    public static function flushCache(): void
    {
        Cache::add(self::CACHE_VERSION_KEY, 1);
        Cache::increment(self::CACHE_VERSION_KEY);
    }

    /**
     * Resolve all dashboard view variables for the authenticated user and scope.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $viewer, ?int $requestedLocationId = null): array
    {
        $viewer->loadMissing('roles');
        $roles = $viewer->roles->pluck('name')->all();
        $primaryRole = $roles !== [] ? ucfirst(str_replace(['_', '-'], ' ', $roles[0])) : 'Staff';

        $isSuperAdmin = $viewer->hasRole('Super Admin');
        $isRestricted = BranchScope::isRestricted($viewer);
        $hasOutletRole = $viewer->hasRole('Outlet Manager');
        $assignedLocationId = $isRestricted ? BranchScope::locationId($viewer) : null;

        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $userName = $viewer->name ?? 'Admin';

        $raw = $this->resolveCachedRawMetrics(
            viewer: $viewer,
            isSuperAdmin: $isSuperAdmin,
            isRestricted: $isRestricted,
            hasOutletRole: $hasOutletRole,
            assignedLocationId: $assignedLocationId,
            requestedLocationId: $requestedLocationId,
        );

        return array_merge($this->hydrateMetrics($raw), [
            'primaryRole' => $primaryRole,
            'greeting' => $greeting,
            'userName' => $userName,
        ]);
    }

    /**
     * Resolve primitive metrics through versioned cache with safe fallback on failure.
     *
     * @return array<string, mixed>
     */
    private function resolveCachedRawMetrics(
        User $viewer,
        bool $isSuperAdmin,
        bool $isRestricted,
        bool $hasOutletRole,
        ?int $assignedLocationId,
        ?int $requestedLocationId,
    ): array {
        $scopeTag = $isRestricted
            ? 'restricted:'.($assignedLocationId ?? 'none')
            : ($isSuperAdmin ? 'super:'.($requestedLocationId ?? 'network') : 'unrestricted:'.($requestedLocationId ?? 'network'));

        $cacheKey = sprintf(
            'dashboard.payload.v%d.%s.%s.sa%d.om%d',
            self::version(),
            now()->toDateString(),
            $scopeTag,
            $isSuperAdmin ? 1 : 0,
            $hasOutletRole ? 1 : 0
        );

        try {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_SECONDS,
                fn (): array => $this->computeRawMetrics(
                    viewer: $viewer,
                    isSuperAdmin: $isSuperAdmin,
                    isRestricted: $isRestricted,
                    hasOutletRole: $hasOutletRole,
                    assignedLocationId: $assignedLocationId,
                    requestedLocationId: $requestedLocationId,
                )
            );
        } catch (Throwable) {
            return $this->fallbackRawMetrics(
                isSuperAdmin: $isSuperAdmin,
                isRestricted: $isRestricted,
                assignedLocationId: $assignedLocationId,
            );
        }
    }

    /**
     * Reconstruct lightweight view objects and Collections outside cache storage.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function hydrateMetrics(array $raw): array
    {
        $networkOverview = $raw['networkTargetOverviewData'] ?? null;
        if (is_array($networkOverview)) {
            $networkOverview['branches'] = collect($networkOverview['branches'] ?? []);
        }

        $locationsList = collect(array_map(
            static fn (array $loc): object => (object) $loc,
            $raw['locationsList'] ?? []
        ));

        return [
            'isSuperAdmin' => $raw['isSuperAdmin'],
            'isRestricted' => $raw['isRestricted'],
            'allLocations' => $locationsList,
            'assignedLocation' => is_array($raw['assignedLocationData'] ?? null) ? (object) $raw['assignedLocationData'] : null,
            'targetLocationId' => $raw['targetLocationId'],
            'scopedLocationId' => $raw['scopedLocationId'],
            'activeScopeLocation' => is_array($raw['activeScopeLocationData'] ?? null) ? (object) $raw['activeScopeLocationData'] : null,
            'branchScoped' => $raw['branchScoped'],
            'isOutletScope' => $raw['isOutletScope'],
            'todaySales' => $raw['todaySales'],
            'salesChangePct' => $raw['salesChangePct'],
            'todayCollection' => $raw['todayCollection'],
            'collectionChangePct' => $raw['collectionChangePct'],
            'payableSummary' => $raw['payableSummary'],
            'clearDayUrl' => $raw['clearDayUrl'],
            'clearDayCaption' => $raw['clearDayCaption'],
            'networkPayable' => $raw['networkPayable'],
            'totalStockValue' => $raw['totalStockValue'],
            'stockValueChangePct' => $raw['stockValueChangePct'],
            'primaryWarehouse' => $raw['primaryWarehouse'],
            'todayDispatched' => $raw['todayDispatched'],
            'todayPlaced' => $raw['todayPlaced'],
            'todayInTransit' => $raw['todayInTransit'],
            'activeRoutes' => $raw['activeRoutes'],
            'todaySalesCaption' => $raw['todaySalesCaption'],
            'todayCollectionCaption' => $raw['todayCollectionCaption'],
            'hqCollectionCaption' => $raw['hqCollectionCaption'],
            'stockValueCaption' => $raw['stockValueCaption'],
            'dynamicAlertItems' => $raw['dynamicAlertItems'],
            'dynamicChartPeriods' => $raw['dynamicChartPeriods'],
            'dynamicRecentInvoices' => $raw['dynamicRecentInvoices'],
            'totalRegisteredBrands' => $raw['totalRegisteredBrands'],
            'dynamicBrandShares' => $raw['dynamicBrandShares'],
            'branchTargetPerformance' => $raw['branchTargetPerformanceData'],
            'networkTargetOverview' => $networkOverview,
        ];
    }

    /**
     * Compute all scoped metrics and widgets as cache-safe primitive arrays.
     *
     * @return array<string, mixed>
     */
    private function computeRawMetrics(
        User $viewer,
        bool $isSuperAdmin,
        bool $isRestricted,
        bool $hasOutletRole,
        ?int $assignedLocationId,
        ?int $requestedLocationId,
    ): array {
        $allLocations = $isSuperAdmin
            ? Location::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'type', 'code'])
            : collect();

        $assignedLocation = null;
        $targetLocationId = null;
        $scopedLocationId = null;
        $activeScopeLocation = null;

        if ($isRestricted) {
            $assignedLocation = $assignedLocationId !== null ? Location::query()->find($assignedLocationId) : null;
            $targetLocationId = $assignedLocationId;
            $scopedLocationId = $assignedLocationId;
            $activeScopeLocation = $assignedLocation;
        } elseif ($requestedLocationId !== null && $allLocations->contains('id', $requestedLocationId)) {
            $targetLocationId = $requestedLocationId;
            $scopedLocationId = $requestedLocationId;
            $activeScopeLocation = $allLocations->firstWhere('id', $requestedLocationId);
        }

        $branchScoped = $isRestricted || ($targetLocationId !== null);

        $isWarehouseOrAllBranches = ! $targetLocationId
            || ($activeScopeLocation && (
                $activeScopeLocation->name === 'Main Warehouse'
                || ($activeScopeLocation->type?->value ?? $activeScopeLocation->type) === 'warehouse'
                || $activeScopeLocation->type === LocationType::Warehouse
                || ($activeScopeLocation->code ?? '') === 'WH01'
            ));

        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();
        $yesterdayStart = today()->subDay()->startOfDay();
        $yesterdayEnd = today()->subDay()->endOfDay();

        $salesKpis = $this->computeSalesKpis($viewer, $isRestricted, $targetLocationId, $yesterdayStart, $yesterdayEnd, $todayStart, $todayEnd);

        $isOutletScope = $hasOutletRole || $isRestricted || ($targetLocationId !== null && ! $isWarehouseOrAllBranches);
        $payableLocationId = $targetLocationId ?? ($isRestricted ? $assignedLocationId : null);
        $payableSummary = DayClosingService::getPayableToHqSummary($payableLocationId);
        $isTodayClosed = $payableLocationId !== null && DayClosingService::isDayClosed((int) $payableLocationId, today());
        $clearDayUrl = $isTodayClosed ? route('day-closings.index') : route('day-closings.create');
        $clearDayCaption = $payableSummary['caption'].($isTodayClosed ? ' · Cash locked' : ' · Clear Day →');

        $networkPayable = (! $isOutletScope && $isSuperAdmin)
            ? DayClosingService::getNetworkPayableSummary()
            : null;

        $branchQuantities = (! $isWarehouseOrAllBranches && $targetLocationId !== null && $targetLocationId > 0)
            ? StockService::quantitiesByUnitAt($targetLocationId)
            : [];

        $stockMetrics = $this->computeStockValuation(
            isWarehouseOrAllBranches: $isWarehouseOrAllBranches,
            targetLocationId: $targetLocationId,
            activeScopeLocation: $activeScopeLocation,
            branchQuantities: $branchQuantities,
            todayStart: $todayStart,
            todayEnd: $todayEnd,
        );

        $transferMetrics = $this->computeTransferMetrics(
            viewer: $viewer,
            isRestricted: $isRestricted,
            targetLocationId: $targetLocationId,
            branchScoped: $branchScoped,
            todayStart: $todayStart,
            todayEnd: $todayEnd,
        );

        $dynamicAlertItems = $this->computeLowStockAlerts($isWarehouseOrAllBranches, $branchQuantities);
        $dynamicChartPeriods = $this->computeChartPeriods($viewer, $isRestricted, $targetLocationId, $todayEnd);
        $dynamicRecentInvoices = $this->computeRecentInvoices($viewer, $isRestricted, $targetLocationId);
        [$totalRegisteredBrands, $dynamicBrandShares] = $this->computeBrandShares($isWarehouseOrAllBranches, $targetLocationId);

        [$branchTargetPerformanceData, $networkTargetOverviewData] = $this->computeTargetPacing($isSuperAdmin, $targetLocationId);

        $todaySalesCount = $salesKpis['today_invoices_count'];
        $todayDispatched = $transferMetrics['today_dispatched'];

        $todaySalesCaption = $todaySalesCount > 0
            ? $todaySalesCount.' '.Str::plural('Invoice', $todaySalesCount).' Posted Today'
            : ($todayDispatched > 0 ? $todayDispatched.' Handsets Dispatched' : 'No sales posted today');

        $todayCollectionCaption = $salesKpis['today_collection'] > 0
            ? 'Collected today at '.($activeScopeLocation ? $activeScopeLocation->name : 'outlets')
            : 'No collections today';

        $hqCollectionToday = (float) ($networkPayable['today_sent_to_hq'] ?? 0);
        $hqCollectionCaption = $hqCollectionToday > 0
            ? '৳'.number_format($hqCollectionToday).' remitted to Central HQ today'
            : 'No remittances collected at HQ today';

        $totalStockUnits = $stockMetrics['total_stock_units'];
        $stockValueCaption = $totalStockUnits > 0
            ? number_format($totalStockUnits).' Units · '.$stockMetrics['stock_scope_caption']
            : '0 units registered';

        $serializeLocation = static fn (?Location $loc): ?array => $loc === null ? null : [
            'id' => (int) $loc->id,
            'name' => (string) $loc->name,
            'type' => (string) ($loc->type?->value ?? $loc->type),
            'code' => $loc->code,
        ];

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'isRestricted' => $isRestricted,
            'locationsList' => $allLocations->map(static fn (Location $loc): array => [
                'id' => (int) $loc->id,
                'name' => (string) $loc->name,
                'type' => (string) ($loc->type?->value ?? $loc->type),
                'code' => $loc->code,
            ])->values()->all(),
            'assignedLocationData' => $serializeLocation($assignedLocation),
            'activeScopeLocationData' => $serializeLocation($activeScopeLocation),
            'targetLocationId' => $targetLocationId,
            'scopedLocationId' => $scopedLocationId,
            'branchScoped' => $branchScoped,
            'isOutletScope' => $isOutletScope,
            'todaySales' => $salesKpis['today_sales'],
            'salesChangePct' => $salesKpis['sales_change_pct'],
            'todayCollection' => $salesKpis['today_collection'],
            'collectionChangePct' => $salesKpis['collection_change_pct'],
            'payableSummary' => $payableSummary,
            'clearDayUrl' => $clearDayUrl,
            'clearDayCaption' => $clearDayCaption,
            'networkPayable' => $networkPayable,
            'totalStockValue' => $stockMetrics['total_stock_value'],
            'stockValueChangePct' => $stockMetrics['stock_value_change_pct'],
            'primaryWarehouse' => $stockMetrics['primary_warehouse'],
            'todayDispatched' => $todayDispatched,
            'todayPlaced' => $transferMetrics['today_placed'],
            'todayInTransit' => $transferMetrics['today_in_transit'],
            'activeRoutes' => $transferMetrics['active_routes'],
            'todaySalesCaption' => $todaySalesCaption,
            'todayCollectionCaption' => $todayCollectionCaption,
            'hqCollectionCaption' => $hqCollectionCaption,
            'stockValueCaption' => $stockValueCaption,
            'dynamicAlertItems' => $dynamicAlertItems,
            'dynamicChartPeriods' => $dynamicChartPeriods,
            'dynamicRecentInvoices' => $dynamicRecentInvoices,
            'totalRegisteredBrands' => $totalRegisteredBrands,
            'dynamicBrandShares' => $dynamicBrandShares,
            'branchTargetPerformanceData' => $branchTargetPerformanceData,
            'networkTargetOverviewData' => $networkTargetOverviewData,
        ];
    }

    /**
     * Scope a SalesInvoice query cleanly by user permissions and selected location.
     */
    private function applySalesScope(Builder $query, User $viewer, bool $isRestricted, ?int $targetLocationId): Builder
    {
        if ($isRestricted) {
            return BranchScope::scopeQuery($query, $viewer, 'sales_invoices.location_id');
        }

        if ($targetLocationId !== null) {
            return $query->where('sales_invoices.location_id', $targetLocationId);
        }

        return $query
            ->join('locations', 'locations.id', '=', 'sales_invoices.location_id')
            ->whereNull('locations.deleted_at')
            ->where('locations.type', '!=', LocationType::Warehouse->value);
    }

    /**
     * Scope a StockTransfer query by user permissions and selected location.
     */
    private function applyTransferScope(Builder $query, User $viewer, bool $isRestricted, ?int $targetLocationId): Builder
    {
        if ($isRestricted) {
            return BranchScope::scopeQueryAny($query, $viewer, 'from_location_id', 'to_location_id');
        }

        if ($targetLocationId !== null) {
            return $query->where(function ($q) use ($targetLocationId) {
                $q->where('from_location_id', $targetLocationId)
                    ->orWhere('to_location_id', $targetLocationId);
            });
        }

        return $query;
    }

    /**
     * Compute today/yesterday sales & collections in one indexed range query.
     *
     * @return array{
     *     today_sales: float,
     *     yesterday_sales: float,
     *     sales_change_pct: int,
     *     today_invoices_count: int,
     *     today_collection: float,
     *     yesterday_collection: float,
     *     collection_change_pct: int
     * }
     */
    private function computeSalesKpis(
        User $viewer,
        bool $isRestricted,
        ?int $targetLocationId,
        CarbonInterface $yesterdayStart,
        CarbonInterface $yesterdayEnd,
        CarbonInterface $todayStart,
        CarbonInterface $todayEnd,
    ): array {
        $posted = SalesInvoiceStatus::Posted->value;
        $yStart = $yesterdayStart->toDateTimeString();
        $yEnd = $yesterdayEnd->toDateTimeString();
        $tStart = $todayStart->toDateTimeString();
        $tEnd = $todayEnd->toDateTimeString();

        $row = $this->applySalesScope(SalesInvoice::query(), $viewer, $isRestricted, $targetLocationId)
            ->whereBetween('sales_invoices.invoice_date', [$yStart, $tEnd])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN sales_invoices.status = ? AND sales_invoices.invoice_date BETWEEN ? AND ? THEN sales_invoices.grand_total ELSE 0 END), 0) as today_sales,
                 COALESCE(SUM(CASE WHEN sales_invoices.status = ? AND sales_invoices.invoice_date BETWEEN ? AND ? THEN sales_invoices.grand_total ELSE 0 END), 0) as yesterday_sales,
                 COALESCE(SUM(CASE WHEN sales_invoices.status = ? AND sales_invoices.invoice_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as today_invoices_count,
                 COALESCE(SUM(CASE WHEN sales_invoices.invoice_date BETWEEN ? AND ? THEN sales_invoices.paid_amount ELSE 0 END), 0) as today_collection,
                 COALESCE(SUM(CASE WHEN sales_invoices.invoice_date BETWEEN ? AND ? THEN sales_invoices.paid_amount ELSE 0 END), 0) as yesterday_collection',
                [
                    $posted, $tStart, $tEnd,
                    $posted, $yStart, $yEnd,
                    $posted, $tStart, $tEnd,
                    $tStart, $tEnd,
                    $yStart, $yEnd,
                ]
            )
            ->first();

        $todaySales = (float) ($row->today_sales ?? 0);
        $yesterdaySales = (float) ($row->yesterday_sales ?? 0);
        $todayInvoicesCount = (int) ($row->today_invoices_count ?? 0);
        $todayCollection = (float) ($row->today_collection ?? 0);
        $yesterdayCollection = (float) ($row->yesterday_collection ?? 0);

        return [
            'today_sales' => $todaySales,
            'yesterday_sales' => $yesterdaySales,
            'sales_change_pct' => $this->percentDelta($todaySales, $yesterdaySales),
            'today_invoices_count' => $todayInvoicesCount,
            'today_collection' => $todayCollection,
            'yesterday_collection' => $yesterdayCollection,
            'collection_change_pct' => $this->percentDelta($todayCollection, $yesterdayCollection),
        ];
    }

    /**
     * Compute stock valuation and Day-over-Day movement change using sargable date range.
     *
     * @param  array<int, int>  $branchQuantities
     * @return array{
     *     total_stock_units: int,
     *     total_stock_value: float,
     *     stock_value_change_pct: int,
     *     primary_warehouse: string,
     *     stock_scope_caption: string
     * }
     */
    private function computeStockValuation(
        bool $isWarehouseOrAllBranches,
        ?int $targetLocationId,
        ?Location $activeScopeLocation,
        array $branchQuantities,
        CarbonInterface $todayStart,
        CarbonInterface $todayEnd,
    ): array {
        if ($isWarehouseOrAllBranches) {
            $totalStockUnits = max(0, (int) Unit::sum('quantity'));

            $rawStockValue = DB::table('units')
                ->join('products', 'units.product_id', '=', 'products.id')
                ->whereNull('products.deleted_at')
                ->whereNull('units.deleted_at')
                ->selectRaw('SUM(units.quantity * COALESCE(products.purchase_price, products.sale_price, 0)) as total_val')
                ->value('total_val');

            $totalStockValue = max(0, (float) ($rawStockValue ?? 0));
            $primaryWarehouse = $activeScopeLocation?->name
                ?? Location::where('status', 'active')
                    ->where('type', LocationType::Warehouse->value)
                    ->value('name')
                ?? 'Main Warehouse';
            $stockScopeCaption = 'All branches included';
        } else {
            $cleanBranchQuantities = array_map(static fn (int $q): int => max(0, $q), $branchQuantities);
            $totalStockUnits = max(0, (int) array_sum($cleanBranchQuantities));

            $unitCosts = $cleanBranchQuantities === []
                ? collect()
                : DB::table('units')
                    ->join('products', 'products.id', '=', 'units.product_id')
                    ->whereNull('products.deleted_at')
                    ->whereNull('units.deleted_at')
                    ->whereIn('units.id', array_keys($cleanBranchQuantities))
                    ->selectRaw('units.id as unit_id, COALESCE(products.purchase_price, products.sale_price, 0) as cost')
                    ->get();

            $totalStockValue = max(0, (float) $unitCosts->sum(
                static fn ($row): float => ($cleanBranchQuantities[(int) $row->unit_id] ?? 0) * (float) $row->cost
            ));
            $primaryWarehouse = $activeScopeLocation?->name ?? 'My Branch';
            $stockScopeCaption = $primaryWarehouse;
        }

        $todayMovementsQuery = DB::table('stock_movements')
            ->join('units', 'stock_movements.unit_id', '=', 'units.id')
            ->join('products', 'units.product_id', '=', 'products.id')
            ->whereBetween('stock_movements.created_at', [$todayStart->toDateTimeString(), $todayEnd->toDateTimeString()])
            ->whereNull('products.deleted_at')
            ->whereNull('units.deleted_at');

        if (! $isWarehouseOrAllBranches && $targetLocationId !== null) {
            $todayMovementsQuery->where('stock_movements.location_id', $targetLocationId);
        }

        $todayStockDelta = (float) ($todayMovementsQuery->selectRaw(
            'SUM(stock_movements.quantity * COALESCE(products.purchase_price, products.sale_price, 0)) as val_delta'
        )->value('val_delta') ?? 0);

        $stockValueChangePct = 0;
        if ($todayStockDelta != 0.0) {
            $yesterdayStockValue = $totalStockValue - $todayStockDelta;
            if ($yesterdayStockValue > 0) {
                $stockValueChangePct = (int) round(($todayStockDelta / $yesterdayStockValue) * 100);
            } else {
                $stockValueChangePct = $todayStockDelta > 0 ? 100 : -100;
            }
        }

        return [
            'total_stock_units' => $totalStockUnits,
            'total_stock_value' => $totalStockValue,
            'stock_value_change_pct' => $stockValueChangePct,
            'primary_warehouse' => $primaryWarehouse,
            'stock_scope_caption' => $stockScopeCaption,
        ];
    }

    /**
     * Compute today's transfer counts (placed vs in-transit) in one indexed query.
     *
     * @return array{today_placed: int, today_in_transit: int, today_dispatched: int, active_routes: int}
     */
    private function computeTransferMetrics(
        User $viewer,
        bool $isRestricted,
        ?int $targetLocationId,
        bool $branchScoped,
        CarbonInterface $todayStart,
        CarbonInterface $todayEnd,
    ): array {
        $receivedStatus = StockTransferStatus::Received->value;
        $inTransitStatus = StockTransferStatus::InTransit->value;

        $row = $this->applyTransferScope(StockTransfer::query(), $viewer, $isRestricted, $targetLocationId)
            ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->whereBetween('stock_transfers.transfer_date', [$todayStart->toDateTimeString(), $todayEnd->toDateTimeString()])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN stock_transfers.status = ? THEN stock_transfer_items.quantity ELSE 0 END), 0) as today_placed,
                 COALESCE(SUM(CASE WHEN stock_transfers.status = ? THEN stock_transfer_items.quantity ELSE 0 END), 0) as today_in_transit',
                [$receivedStatus, $inTransitStatus]
            )
            ->first();

        $todayPlaced = (int) ($row->today_placed ?? 0);
        $todayInTransit = (int) ($row->today_in_transit ?? 0);
        $activeRoutes = $branchScoped
            ? ($targetLocationId !== null ? 1 : 0)
            : (int) Location::where('status', 'active')->count();

        return [
            'today_placed' => $todayPlaced,
            'today_in_transit' => $todayInTransit,
            'today_dispatched' => $todayPlaced + $todayInTransit,
            'active_routes' => $activeRoutes,
        ];
    }

    /**
     * Build the low-stock reorder alerts list.
     *
     * @param  array<int, int>  $branchQuantities
     * @return array<int, array<string, mixed>>
     */
    private function computeLowStockAlerts(bool $isWarehouseOrAllBranches, array $branchQuantities): array
    {
        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 10);

        if (! $isWarehouseOrAllBranches) {
            $cleanBranchQuantities = array_map(static fn (int $q): int => max(0, $q), $branchQuantities);
            $lowStockUnitIds = array_keys(array_filter(
                $cleanBranchQuantities,
                static fn (int $quantity): bool => $quantity <= $lowStockThreshold && $quantity >= 0
            ));

            $lowStockProducts = $lowStockUnitIds === []
                ? collect()
                : Product::with(['brand', 'stockRow'])
                    ->where('status', 'active')
                    ->whereHas('stockRow', fn ($q) => $q->whereIn('units.id', $lowStockUnitIds))
                    ->take(5)
                    ->get();

            $alertQuantities = $lowStockProducts
                ->mapWithKeys(fn ($p): array => [$p->id => max(0, (int) ($cleanBranchQuantities[$p->stockRow?->id] ?? 0))])
                ->all();
        } else {
            $lowStockProducts = Product::with(['brand', 'stockRow'])
                ->where('status', 'active')
                ->whereHas('stockRow', fn ($q) => $q->where('quantity', '<=', $lowStockThreshold))
                ->take(5)
                ->get();

            $alertQuantities = $lowStockProducts
                ->mapWithKeys(fn ($p): array => [$p->id => max(0, (int) ($p->stockRow?->quantity ?? 0))])
                ->all();
        }

        return $lowStockProducts->map(function ($p) use ($lowStockThreshold, $alertQuantities): array {
            $stock = (int) ($alertQuantities[$p->id] ?? 0);
            $brandName = $p->brand?->name ?? 'Brand';
            $modelName = $p->model
                ? "{$p->name} ({$p->model})"
                : ($p->storage ? "{$p->name} ({$p->storage})" : $p->name);

            return [
                'sku' => $p->product_id ?? ('PRD-'.str_pad((string) $p->id, 4, '0', STR_PAD_LEFT)),
                'brand' => $brandName,
                'model' => $modelName,
                'color' => $p->color ?? 'Standard',
                'stock' => $stock,
                'min' => $lowStockThreshold,
                'status' => $stock <= 3 ? 'critical' : 'low',
            ];
        })->all();
    }

    /**
     * Build Sales & Collection chart periods (7d, 30d, year) using indexed whereBetween queries.
     *
     * @return array<string, array{label: string, categories: array<int, string>, sales: array<int, float>, collections: array<int, float>}>
     */
    private function computeChartPeriods(
        User $viewer,
        bool $isRestricted,
        ?int $targetLocationId,
        CarbonInterface $todayEnd,
    ): array {
        $start30d = today()->subDays(27)->startOfDay();
        $invoices30d = $this->applySalesScope(SalesInvoice::query(), $viewer, $isRestricted, $targetLocationId)
            ->whereBetween('sales_invoices.invoice_date', [$start30d->toDateTimeString(), $todayEnd->toDateTimeString()])
            ->selectRaw(
                "DATE(sales_invoices.invoice_date) as d,
                 SUM(CASE WHEN sales_invoices.status = 'posted' THEN sales_invoices.grand_total ELSE 0 END) as s_total,
                 SUM(sales_invoices.paid_amount) as c_total"
            )
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $days7Categories = [];
        $days7Sales = [];
        $days7Collections = [];
        for ($i = 6; $i >= 0; $i--) {
            $targetDay = today()->subDays($i);
            $dStr = $targetDay->toDateString();
            $days7Categories[] = $targetDay->format('D');
            $days7Sales[] = round((float) ($invoices30d[$dStr]->s_total ?? 0), 2);
            $days7Collections[] = round((float) ($invoices30d[$dStr]->c_total ?? 0), 2);
        }

        $weeksCategories = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        $weeksSales = [0.0, 0.0, 0.0, 0.0];
        $weeksCollections = [0.0, 0.0, 0.0, 0.0];

        for ($w = 0; $w < 4; $w++) {
            $wStart = today()->subDays(27 - ($w * 7));
            for ($offset = 0; $offset < 7; $offset++) {
                $curDateStr = (clone $wStart)->addDays($offset)->toDateString();
                if (isset($invoices30d[$curDateStr])) {
                    $weeksSales[$w] += (float) $invoices30d[$curDateStr]->s_total;
                    $weeksCollections[$w] += (float) $invoices30d[$curDateStr]->c_total;
                }
            }
            $weeksSales[$w] = round($weeksSales[$w], 2);
            $weeksCollections[$w] = round($weeksCollections[$w], 2);
        }

        $yearMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $yearSales = array_fill(0, 12, 0.0);
        $yearCollections = array_fill(0, 12, 0.0);
        $yearStart = now()->startOfYear()->startOfDay()->toDateTimeString();
        $yearEnd = now()->endOfYear()->endOfDay()->toDateTimeString();
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', sales_invoices.invoice_date) AS INTEGER)"
            : 'MONTH(sales_invoices.invoice_date)';

        $invoicesYear = $this->applySalesScope(SalesInvoice::query(), $viewer, $isRestricted, $targetLocationId)
            ->whereBetween('sales_invoices.invoice_date', [$yearStart, $yearEnd])
            ->selectRaw(
                "{$monthExpr} as m,
                 SUM(CASE WHEN sales_invoices.status = 'posted' THEN sales_invoices.grand_total ELSE 0 END) as s_total,
                 SUM(sales_invoices.paid_amount) as c_total"
            )
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        for ($m = 1; $m <= 12; $m++) {
            if (isset($invoicesYear[$m])) {
                $yearSales[$m - 1] = round((float) $invoicesYear[$m]->s_total, 2);
                $yearCollections[$m - 1] = round((float) $invoicesYear[$m]->c_total, 2);
            }
        }

        return [
            '7d' => [
                'label' => 'Last 7 Days',
                'categories' => $days7Categories,
                'sales' => $days7Sales,
                'collections' => $days7Collections,
            ],
            '30d' => [
                'label' => 'This Month',
                'categories' => $weeksCategories,
                'sales' => $weeksSales,
                'collections' => $weeksCollections,
            ],
            'year' => [
                'label' => 'This Year',
                'categories' => $yearMonths,
                'sales' => $yearSales,
                'collections' => $yearCollections,
            ],
        ];
    }

    /**
     * Fetch the latest 5 invoices within the active scope.
     *
     * @return array<int, array<string, mixed>>
     */
    private function computeRecentInvoices(User $viewer, bool $isRestricted, ?int $targetLocationId): array
    {
        $recentInvoicesList = $this->applySalesScope(SalesInvoice::query(), $viewer, $isRestricted, $targetLocationId)
            ->select('sales_invoices.*')
            ->with(['customer', 'items.unit', 'creator', 'location'])
            ->latest('sales_invoices.invoice_date')
            ->take(5)
            ->get();

        return $recentInvoicesList->map(function ($inv): array {
            $customer = $inv->customer;
            $items = $inv->items;
            $itemsSummary = $items->isEmpty()
                ? '—'
                : $items->take(2)->map(static fn ($it): string => ($it->unit?->displayName() ?? 'Handset').' ('.$it->quantity.'x)')->implode(', ')
                    .($items->count() > 2 ? ' +'.($items->count() - 2).' more' : '');

            return [
                'invoice_no' => $inv->invoice_no,
                'retailer' => $customer?->name ?? 'Walk-in Customer',
                'location' => filled($customer?->city) ? $customer->city : ($inv->location?->name ?? 'Outlet'),
                'items' => $itemsSummary,
                'sr' => $inv->creator?->name ?? 'Staff',
                'amount' => (float) $inv->grand_total,
                'status' => $inv->payment_status?->value ?? 'due',
                'date' => $inv->invoice_date ? $inv->invoice_date->format('d M, h:i A') : 'Today',
                'url' => route('sales-invoices.show', $inv),
            ];
        })->all();
    }

    /**
     * Compute stocked brand shares within the active location or network scope.
     *
     * @return array{0: int, 1: ?array<int, array<string, mixed>>}
     */
    private function computeBrandShares(bool $isWarehouseOrAllBranches, ?int $targetLocationId): array
    {
        $totalRegisteredBrands = (int) Brand::query()->count();

        if (! $isWarehouseOrAllBranches && $targetLocationId !== null && $targetLocationId > 0) {
            $stockedBrandRows = DB::table('brands')
                ->join('products', static function ($join) {
                    $join->on('products.brand_id', '=', 'brands.id')
                        ->whereNull('products.deleted_at');
                })
                ->join('units', static function ($join) {
                    $join->on('units.product_id', '=', 'products.id')
                        ->whereNull('units.deleted_at');
                })
                ->joinSub(
                    StockMovement::query()
                        ->selectRaw('unit_id, COALESCE(SUM(quantity), 0) as quantity')
                        ->where('location_id', $targetLocationId)
                        ->groupBy('unit_id'),
                    'branch_stock',
                    static fn ($join) => $join->on('branch_stock.unit_id', '=', 'units.id')
                )
                ->whereNull('brands.deleted_at')
                ->groupBy('brands.id', 'brands.name')
                ->havingRaw('SUM(branch_stock.quantity) > 0')
                ->selectRaw('brands.id, brands.name, SUM(branch_stock.quantity) as stock_units, COUNT(DISTINCT products.id) as sku_count, SUM(branch_stock.quantity * COALESCE(products.sale_price, products.purchase_price, 0)) as stock_value')
                ->orderByDesc('stock_value')
                ->orderByDesc('stock_units')
                ->get();
        } else {
            $stockedBrandRows = DB::table('brands')
                ->join('products', static function ($join) {
                    $join->on('products.brand_id', '=', 'brands.id')
                        ->whereNull('products.deleted_at');
                })
                ->join('units', static function ($join) {
                    $join->on('units.product_id', '=', 'products.id')
                        ->whereNull('units.deleted_at');
                })
                ->whereNull('brands.deleted_at')
                ->groupBy('brands.id', 'brands.name')
                ->havingRaw('SUM(units.quantity) > 0')
                ->selectRaw('brands.id, brands.name, SUM(units.quantity) as stock_units, COUNT(DISTINCT products.id) as sku_count, SUM(units.quantity * COALESCE(products.sale_price, products.purchase_price, 0)) as stock_value')
                ->orderByDesc('stock_value')
                ->orderByDesc('stock_units')
                ->get();
        }

        $brandPalette = [
            '#465fff', '#f59e0b', '#10b981', '#8b5cf6',
            '#06b6d4', '#ec4899', '#f97316', '#14b8a6',
            '#6366f1', '#84cc16', '#e11d48', '#0ea5e9',
        ];

        $formatCompactBdt = static function (float $amount): string {
            $abs = abs($amount);
            if ($abs >= 10000000) {
                return '৳ '.rtrim(rtrim(number_format($amount / 10000000, 2, '.', ''), '0'), '.').'Cr';
            }
            if ($abs >= 100000) {
                return '৳ '.rtrim(rtrim(number_format($amount / 100000, 1, '.', ''), '0'), '.').'L';
            }
            if ($abs >= 1000) {
                return '৳ '.rtrim(rtrim(number_format($amount / 1000, 1, '.', ''), '0'), '.').'K';
            }

            return '৳ '.number_format($amount, 0);
        };

        $dynamicBrandShares = null;
        if ($stockedBrandRows->isNotEmpty()) {
            $sumValue = (float) $stockedBrandRows->sum('stock_value');
            $sumUnits = (int) $stockedBrandRows->sum('stock_units');
            $useValueMetric = $sumValue > 0;

            $dynamicBrandShares = $stockedBrandRows->values()->map(static function ($row, int $idx) use (
                $sumValue,
                $sumUnits,
                $useValueMetric,
                $brandPalette,
                $formatCompactBdt
            ): array {
                $val = (float) $row->stock_value;
                $units = (int) $row->stock_units;
                $share = $useValueMetric
                    ? round(($val / max(1.0, $sumValue)) * 100, 1)
                    : round(($units / max(1, $sumUnits)) * 100, 1);

                $color = $brandPalette[$idx % count($brandPalette)];

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'share' => $share,
                    'amount' => $useValueMetric ? $formatCompactBdt($val) : number_format($units).' pcs',
                    'units' => $units,
                    'skus' => (int) $row->sku_count,
                    'colorHex' => $color,
                    'shareData' => array_fill(0, 12, $share),
                    'volumeData' => array_fill(0, 12, round($val, 2)),
                ];
            })->all();
        } elseif ($totalRegisteredBrands > 0) {
            $dynamicBrandShares = [];
        }

        return [$totalRegisteredBrands, $dynamicBrandShares];
    }

    /**
     * Resolve monthly target pacing as cache-safe primitive arrays without Eloquent models.
     *
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    private function computeTargetPacing(bool $isSuperAdmin, ?int $targetLocationId): array
    {
        try {
            $branchPerf = $targetLocationId !== null
                ? TargetService::getBranchPerformance($targetLocationId)
                : null;
            if (is_array($branchPerf)) {
                $branchPerf['target_record'] = null;
            }

            $networkOverview = ($targetLocationId === null && $isSuperAdmin)
                ? TargetService::getNetworkOverview()
                : null;
            if (is_array($networkOverview)) {
                $networkOverview['branches'] = collect($networkOverview['branches'])
                    ->map(static function (array $row): array {
                        $row['location'] = null;
                        $row['target_record'] = null;

                        return $row;
                    })
                    ->values()
                    ->all();
            }

            return [$branchPerf, $networkOverview];
        } catch (Throwable) {
            return [null, null];
        }
    }

    /**
     * Safe percentage change calculation between today and yesterday totals.
     */
    private function percentDelta(float $today, float $yesterday): int
    {
        if ($today > 0 && $yesterday > 0) {
            return (int) round((($today - $yesterday) / $yesterday) * 100);
        }

        return $today > 0 ? 100 : 0;
    }

    /**
     * Fallback zeroed dashboard payload when metrics queries cannot execute.
     *
     * @return array<string, mixed>
     */
    private function fallbackRawMetrics(bool $isSuperAdmin, bool $isRestricted, ?int $assignedLocationId): array
    {
        return [
            'isSuperAdmin' => $isSuperAdmin,
            'isRestricted' => $isRestricted,
            'locationsList' => [],
            'assignedLocationData' => null,
            'activeScopeLocationData' => null,
            'targetLocationId' => $assignedLocationId,
            'scopedLocationId' => $assignedLocationId,
            'branchScoped' => $isRestricted,
            'isOutletScope' => $isRestricted,
            'todaySales' => 0.0,
            'salesChangePct' => 0,
            'todayCollection' => 0.0,
            'collectionChangePct' => 0,
            'payableSummary' => [
                'cash_close_amount' => 0.0,
                'transferred_to_hq' => 0.0,
                'pending_transferred' => 0.0,
                'left_amount' => 0.0,
                'progress_pct' => 100,
                'is_all_clear' => true,
                'closing_date' => null,
                'caption' => 'All clear · ৳0 payable to HQ',
            ],
            'clearDayUrl' => route('day-closings.index'),
            'clearDayCaption' => 'All clear · ৳0 payable to HQ',
            'networkPayable' => [
                'total_closings' => 0.0,
                'total_sent_to_hq' => 0.0,
                'total_receivable' => 0.0,
                'progress_pct' => 100,
                'is_all_clear' => true,
                'branches_count' => 0,
                'today_sent_to_hq' => 0.0,
                'caption' => 'All clear · ৳0 receivable from branches',
            ],
            'totalStockValue' => 0.0,
            'stockValueChangePct' => 0,
            'primaryWarehouse' => 'Central Warehouse',
            'todayDispatched' => 0,
            'todayPlaced' => 0,
            'todayInTransit' => 0,
            'activeRoutes' => 1,
            'todaySalesCaption' => 'No sales recorded',
            'todayCollectionCaption' => 'No collections',
            'hqCollectionCaption' => 'No remittances collected at HQ today',
            'stockValueCaption' => '0 Units registered',
            'dynamicAlertItems' => [],
            'dynamicChartPeriods' => null,
            'dynamicRecentInvoices' => [],
            'totalRegisteredBrands' => 0,
            'dynamicBrandShares' => [],
            'branchTargetPerformanceData' => null,
            'networkTargetOverviewData' => null,
        ];
    }
}
