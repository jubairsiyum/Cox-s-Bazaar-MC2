<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function index(): View
    {
        return view('dashboard.index', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'active')->count(),
            'totalRoles' => Role::count(),
            'recentUsers' => User::with('roles')->latest()->take(5)->get(),
        ]);
    }
}
