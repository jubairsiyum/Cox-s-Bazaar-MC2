<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Display a listing of the permissions grouped by module.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::orderBy('name')->get()
            ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString());

        return view('admin.permissions.index', [
            'groups' => $permissions,
        ]);
    }
}
