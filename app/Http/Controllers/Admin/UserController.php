<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
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
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->withTrashed()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        AuditService::created($user, 'User account created.');

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $original = $user->getOriginal();

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
}
