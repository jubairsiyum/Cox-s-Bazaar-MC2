<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard. Supplier logins land on their own
     * supplier profile instead: the dashboard is not theirs.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isSupplierPortalUser()) {
            return redirect()->route('suppliers.my-profile');
        }

        return view('dashboard.index', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'active')->count(),
            'totalRoles' => Role::count(),
            'recentUsers' => User::with('roles')->latest()->take(5)->get(),
        ]);
    }
}
