<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DayClosing;
use App\Models\Location;
use App\Services\BranchScope;
use App\Services\DayClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DayClosingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DayClosing::class);

        $user = $request->user();
        $query = DayClosing::query()->with(['location', 'closedBy', 'reopenedBy']);

        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            $query->where('location_id', $branchId);
        }

        $closings = $query->orderByDesc('closing_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $today = now()->toDateString();
        $todayClosingQuery = DayClosing::query()
            ->whereDate('closing_date', $today)
            ->where('status', 'closed');

        if (BranchScope::isRestricted($user)) {
            $todayClosingQuery->where('location_id', BranchScope::locationId($user));
        }

        $isTodayClosed = $todayClosingQuery->exists();
        $todayClosing = (clone $todayClosingQuery)->with(['closedBy', 'location'])->first();

        $compliance = DayClosingService::getTodayBranchCompliance($today, $user);

        // For branch managers: compute rich single-branch daily summary
        $branchSummary = null;
        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            if ($branchId !== null) {
                $payable = DayClosingService::getPayableToHqSummary($branchId);
                $calcSummary = DayClosingService::calculateSummary($branchId, $today);
                $location = Location::query()->find($branchId);

                $branchSummary = [
                    'location' => $location,
                    'is_closed' => $isTodayClosed,
                    'closing' => $todayClosing,
                    'today_sales' => (float) $calcSummary['cash_sales'],
                    'remitted_amount' => (float) $payable['transferred_to_hq'],
                    'remaining_payable' => (float) $payable['left_amount'],
                    'system_cash' => (float) $calcSummary['system_cash'],
                    'counted_cash' => $todayClosing ? (float) $todayClosing->counted_cash : 0.0,
                    'variance' => $todayClosing ? (float) $todayClosing->variance : 0.0,
                    'variance_status' => $todayClosing ? $todayClosing->variance_status : null,
                    'caption' => $payable['caption'],
                ];
            }
        }

        return view('day-closings.index', [
            'closings' => $closings,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'isTodayClosed' => $isTodayClosed,
            'todayClosing' => $todayClosing,
            'branchSummary' => $branchSummary,
            'compliance' => $compliance,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', DayClosing::class);

        $user = $request->user();

        if ($request->filled('location_id') && ! BranchScope::canAccess($user, $request->input('location_id'))) {
            abort(403, 'You can only close the day for your own branch.');
        }

        $locationId = BranchScope::locationId($user);

        if ($locationId === null && BranchScope::isUnrestricted($user)) {
            $locationId = (int) $request->input('location_id', Location::query()->active()->value('id') ?? 1);
        }

        abort_if($locationId === null, 403, 'You must be assigned to a branch location to close the day.');

        $date = $request->input('closing_date', now()->toDateString());
        $existing = DayClosing::query()->where('location_id', $locationId)->whereDate('closing_date', $date)->first();

        // If today is already closed and locked, redirect back to index
        if ($existing && $existing->status === 'closed' && ! $user->isSuperAdmin()) {
            return redirect()->route('day-closings.index')
                ->with('error', "Business day for {$date} is already closed and locked. Contact Super Admin to reopen.");
        }

        $summary = DayClosingService::calculateSummary($locationId, $date);

        return view('day-closings.create', [
            'location' => Location::query()->findOrFail($locationId),
            'locations' => BranchScope::isUnrestricted($user) ? Location::query()->active()->orderBy('name')->get() : collect(),
            'closingDate' => $date,
            'summary' => $summary,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DayClosing::class);

        $user = $request->user();
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'closing_date' => ['required', 'date'],
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! BranchScope::canAccess($user, $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only close the day for your own branch.');
        }

        $existing = DayClosing::query()
            ->where('location_id', $validated['location_id'])
            ->whereDate('closing_date', $validated['closing_date'])
            ->first();

        if ($existing && $existing->status === 'closed') {
            return redirect()->route('day-closings.index')
                ->with('error', "Transactions on {$validated['closing_date']} are already closed and locked. Contact Super Admin to reopen.");
        }

        $closing = DayClosingService::closeDay(
            (int) $validated['location_id'],
            $validated['closing_date'],
            $validated['counted_cash'],
            $user,
            $validated['note'] ?? null
        );

        $varianceText = (float) $closing->variance != 0.0
            ? ' with variance: ৳'.number_format((float) $closing->variance, 2)
            : ' with exact zero variance.';

        return redirect()->route('day-closings.index')
            ->with('success', "Day {$closing->closing_date->format('d M Y')} closed successfully{$varianceText}");
    }

    public function reopen(Request $request, DayClosing $dayClosing): RedirectResponse
    {
        $this->authorize('reopen', $dayClosing);

        abort_unless($dayClosing->closing_date->isToday(), 403, 'Historical past days are permanently locked and cannot be reopened.');

        $validated = $request->validate([
            'reopen_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DayClosingService::reopenDay($dayClosing, $request->user(), $validated['reopen_reason']);

        return redirect()->route('day-closings.index')
            ->with('success', "Day {$dayClosing->closing_date->format('d M Y')} has been reopened by Super Admin.");
    }
}
