<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\DuplicateCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     *
     * Strict branch isolation: branch users see only their own branch's
     * users and the branch filter is offered to the Super Admin only.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $canFilterBranch = BranchScope::isUnrestricted($request->user());

        $users = BranchScope::scopeQuery(User::query()->withTrashed(), $request->user(), 'branch_id')
            ->with('roles', 'branch')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')->toString()));
            })
            ->when($request->filled('branch_id') && $canFilterBranch, function ($query) use ($request) {
                $query->where('branch_id', (int) $request->input('branch_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'locations' => $this->scopedLocations($request),
            'canFilterBranch' => $canFilterBranch,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'locations' => $this->scopedLocations($request),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict branch isolation: branch users create users for their own
        // branch only (Super Admin may choose any branch, or none).
        if (BranchScope::isRestricted($request->user())) {
            $validated['branch_id'] = BranchScope::locationId($request->user());
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'status' => $validated['status'],
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        if (in_array('Supplier', $validated['roles'] ?? [], true)) {
            $this->linkSupplierProfile($user, $validated['supplier_name'], $request->user());
        }

        AuditService::created($user, 'User account created.');

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load('roles', 'supplier'),
            'roles' => Role::orderBy('name')->get(),
            'locations' => $this->scopedLocations($request),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $original = $user->getOriginal();

        // Strict branch isolation: branch users keep accounts locked to their
        // own branch (Super Admin may choose any branch, or none).
        if (BranchScope::isRestricted($request->user())) {
            $validated['branch_id'] = BranchScope::locationId($request->user());
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles'] ?? []);
        }

        if (in_array('Supplier', $validated['roles'] ?? [], true)) {
            $this->linkSupplierProfile($user, $validated['supplier_name'], $request->user());
        }

        AuditService::updated($user, $original, 'User account updated.');

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Activate or deactivate the specified user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // Hard invariant: no one may deactivate their own account, including
        // Super Admins, whose Gate bypass would otherwise override the policy.
        abort_if($request->user()->is($user), 403, 'You cannot change your own account status.');

        $this->authorize('toggleStatus', $user);

        $user->isActive() ? $user->deactivate() : $user->activate();

        $action = $user->isActive() ? 'activated' : 'deactivated';

        AuditService::log('updated', $user, [], ['status' => $user->status->value], "User {$action}.");

        return redirect()->route('users.index')->with('success', "User {$action} successfully.");
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot delete your own account.');
        abort_if($user->isSuperAdmin(), 403, 'The Super Admin account cannot be deleted.');

        $this->authorize('delete', $user);

        $user->delete();

        AuditService::deleted($user, 'User account deleted.');

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Create the supplier profile a Supplier-role login opens, or refresh the
     * one already linked to the account. Reuses a soft-deleted supplier with
     * the same identity instead of duplicating it, like the supplier form does.
     */
    private function linkSupplierProfile(User $user, string $supplierName, User $actor): void
    {
        $attributes = [
            'user_id' => $user->id,
            'name' => $supplierName,
            'mobile' => $user->phone,
            'email' => $user->email,
            'status' => 'active',
            'created_by' => $actor->id,
        ];

        $supplier = $user->supplier ?? DuplicateCheckService::reusableRecord(Supplier::class, $attributes);

        if ($supplier === null) {
            $supplier = Supplier::create($attributes);

            AuditService::created($supplier, "Supplier '{$supplier->name}' ({$supplier->supplier_id}) created for {$user->email}.");

            return;
        }

        DuplicateCheckService::purgeTrashConflicts(Supplier::class, $attributes, $supplier->id);

        $original = $supplier->getOriginal();

        $supplier->fill($attributes)->restore();

        AuditService::updated($supplier, $original, "Supplier '{$supplier->name}' ({$supplier->supplier_id}) linked to {$user->email}.");
    }

    /**
     * Branch choices for the user forms: their own branch only for branch
     * users, every location for the Super Admin (strict branch isolation).
     *
     * @return Collection<int, Location>
     */
    private function scopedLocations(Request $request): Collection
    {
        return BranchScope::scopeQuery(Location::query(), $request->user(), 'id')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
