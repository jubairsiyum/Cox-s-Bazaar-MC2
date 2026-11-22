<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BranchTarget;
use App\Models\Location;
use App\Services\BranchScope;
use App\Services\TargetService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchTargetController extends Controller
{
    /**
     * Display the monthly target management and tracking interface.
     * Super Admin sees the full network overview and can configure targets.
     * Branch Managers see their assigned branch's targets and Day-over-Day pacing.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $monthInput = $request->query('month');
        $month = (is_string($monthInput) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthInput))
            ? $monthInput
            : now()->format('Y-m');

        $activeDate = Carbon::createFromFormat('Y-m', $month);

        if ($isSuperAdmin) {
            $overview = TargetService::getNetworkOverview($month);
            $locations = Location::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'type']);

            return view('branch-targets.index', [
                'isSuperAdmin' => true,
                'activeMonth' => $month,
                'activeDate' => $activeDate,
                'overview' => $overview,
                'locations' => $locations,
                'branchPerformance' => null,
            ]);
        }

        // Restricted role (e.g., Branch Manager)
        $assignedLocationId = BranchScope::locationId($user);
        $assignedLocation = $assignedLocationId ? Location::find($assignedLocationId) : null;
        $branchPerformance = $assignedLocationId
            ? TargetService::getBranchPerformance($assignedLocationId, $month)
            : null;

        return view('branch-targets.index', [
            'isSuperAdmin' => false,
            'activeMonth' => $month,
            'activeDate' => $activeDate,
            'assignedLocation' => $assignedLocation,
            'branchPerformance' => $branchPerformance,
            'overview' => null,
            'locations' => collect(),
        ]);
    }

    /**
     * Store or update a branch monthly sales target (Super Admin only).
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can assign or update branch monthly sales targets.');
        }

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'target_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'target_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        BranchTarget::updateOrCreate(
            [
                'location_id' => (int) $validated['location_id'],
                'target_month' => $validated['target_month'],
            ],
            [
                'target_amount' => $validated['target_amount'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        );

        return redirect()
            ->route('branch-targets.index', ['month' => $validated['target_month']])
            ->with('status', 'Monthly sales target saved successfully.');
    }

    /**
     * Delete an existing branch monthly sales target (Super Admin only).
     */
    public function destroy(Request $request, BranchTarget $branchTarget): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can remove branch sales targets.');
        }

        $month = $branchTarget->target_month;
        $branchTarget->delete();

        return redirect()
            ->route('branch-targets.index', ['month' => $month])
            ->with('status', 'Monthly target removed.');
    }
}
