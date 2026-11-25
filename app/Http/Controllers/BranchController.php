<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\BranchProfileData;
use App\Services\BranchScope;
use App\Services\DuplicateCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['name', 'code', 'type', 'status'];

    /**
     * Display a listing of business locations.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Location::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $locations = BranchScope::scopeQuery(Location::query(), $request->user(), 'id')
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('branches.index', [
            'locations' => $locations,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Show the form for creating a new location.
     */
    public function create(): View
    {
        $this->authorize('create', Location::class);

        return view('branches.create');
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        // Re-using the code of an archived location restores that record
        // instead of failing on the unique code.
        $reusable = DuplicateCheckService::reusableRecord(Location::class, $validated);

        if ($reusable instanceof Location) {
            DuplicateCheckService::purgeTrashConflicts(Location::class, $validated, $reusable->id);

            $reusable->fill($validated);
            $reusable->restore();

            AuditService::created($reusable, "Location '{$reusable->name}' restored and updated.");

            return redirect()->route('branches.index')
                ->with('success', "Location '{$reusable->name}' restored and updated successfully.");
        }

        $location = Location::create($validated);

        AuditService::created($location, "Location '{$location->name}' created.");

        return redirect()->route('branches.index')
            ->with('success', "Location '{$location->name}' created successfully.");
    }

    /**
     * Display a branch's operational profile.
     */
    public function show(Request $request, Location $location): View
    {
        $this->authorize('view', $location);

        // The LocationPolicy grants `branches.manage` to everyone who holds
        // it, so enforce strict own-branch isolation here for non-Super-Admins.
        abort_unless(BranchScope::canAccess($request->user(), $location->id), 403);

        return view('branches.show', [
            'location' => $location,
            'data' => BranchProfileData::for($location, $request->user()),
        ]);
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Request $request, Location $location): View
    {
        $this->authorize('update', $location);
        abort_unless(BranchScope::canAccess($request->user(), $location->id), 403);

        return view('branches.edit', ['location' => $location]);
    }

    /**
     * Update the specified location in storage.
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        abort_unless(BranchScope::canAccess($request->user(), $location->id), 403);

        $original = $location->getOriginal();
        $validated = $request->validated();

        DuplicateCheckService::purgeTrashConflicts(Location::class, $validated, $location->id);

        $location->update($validated);

        AuditService::updated($location, $original, "Location '{$location->name}' updated.");

        return redirect()->route('branches.index')
            ->with('success', "Location '{$location->name}' updated successfully.");
    }

    /**
     * Remove the specified location from storage.
     */
    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('delete', $location);
        abort_unless(BranchScope::canAccess($request->user(), $location->id), 403);

        $location->delete();

        AuditService::deleted($location, "Location '{$location->name}' deleted.");

        return redirect()->route('branches.index')
            ->with('success', "Location '{$location->name}' deleted successfully.");
    }
}
