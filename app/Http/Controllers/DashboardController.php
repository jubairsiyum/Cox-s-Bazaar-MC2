<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BranchScope;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
    ) {}

    /**
     * Display the application dashboard. Supplier logins land on their own
     * supplier profile instead: the dashboard is not theirs.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isSupplierPortalUser()) {
            return redirect()->route('suppliers.my-profile');
        }

        $requestedLocationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        return view('dashboard.index', $this->metrics->forUser($request->user(), $requestedLocationId));
    }

    /**
     * Return scoped dashboard metrics as JSON for live refresh/retry with strict branch isolation.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSupplierPortalUser()) {
            abort(403, 'Suppliers do not have access to dashboard metrics.');
        }

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $requestedLocationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;

        if ($requestedLocationId !== null && BranchScope::isRestricted($user)) {
            $assignedId = BranchScope::locationId($user);
            if ($assignedId === null || $requestedLocationId !== $assignedId) {
                abort(403, 'You cannot access metrics for another branch.');
            }
        }

        $data = $this->metrics->forUser($user, $requestedLocationId);

        return response()
            ->json([
                'scoped_location_id' => $data['scopedLocationId'],
                'is_outlet_scope' => $data['isOutletScope'],
                'today_sales' => $data['todaySales'],
                'today_collection' => $data['todayCollection'],
                'total_stock_value' => $data['totalStockValue'],
                'payable_summary' => $data['payableSummary'],
                'network_payable' => $data['networkPayable'],
                'as_of' => $data['asOf'],
                'as_of_formatted' => $data['asOfFormatted'],
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
