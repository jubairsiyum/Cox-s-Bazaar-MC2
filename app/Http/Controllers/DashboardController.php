<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
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
}
