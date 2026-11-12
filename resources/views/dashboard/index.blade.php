@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Welcome & Quick Banner --}}
    <div class="rounded-2xl border border-gray-200 bg-gradient-to-r from-brand-500/10 via-brand-500/5 to-transparent p-6 dark:border-gray-800 dark:from-brand-500/15">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-title-sm sm:text-title-md font-bold text-gray-900 dark:text-white">
                    Welcome back, {{ auth()->user()->name }} 👋
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Cox's Bazaar Mobile Distribution ERP System · TailAdmin Dashboard
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('ecommerce') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 transition-colors">
                    <x-svg.bar-chart class="size-4" />
                    <span>View Ecommerce Analytics</span>
                </a>
                <a href="{{ route('calendar') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5 transition-colors">
                    <x-svg.calendar class="size-4" />
                    <span>Calendar</span>
                </a>
            </div>
        </div>
    </div>

    {{-- TailAdmin Metric Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
        <!-- Metric 1: Total Users -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3 shadow-theme-xs">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                <x-svg.users class="size-6" />
            </div>
            <div class="mt-5 flex items-end justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</span>
                    <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $totalUsers }}</h4>
                </div>
                <x-ui.badge variant="light" color="brand" size="sm">Registered</x-ui.badge>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">All registered system accounts</p>
        </div>

        <!-- Metric 2: Active Users -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3 shadow-theme-xs">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400">
                <x-svg.check-circle class="size-6" />
            </div>
            <div class="mt-5 flex items-end justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Users</span>
                    <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $activeUsers }}</h4>
                </div>
                <x-ui.badge variant="light" color="success" size="sm">Online Ready</x-ui.badge>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Currently enabled in system</p>
        </div>

        <!-- Metric 3: Roles -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3 shadow-theme-xs">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400">
                <x-svg.key class="size-6" />
            </div>
            <div class="mt-5 flex items-end justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Roles & Access</span>
                    <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $totalRoles }}</h4>
                </div>
                <x-ui.badge variant="light" color="blue-light" size="sm">RBAC</x-ui.badge>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Access control groups configured</p>
        </div>

        <!-- Metric 4: Current Session -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3 shadow-theme-xs">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400">
                <x-svg.user-circle class="size-6" />
            </div>
            <div class="mt-5 flex items-end justify-between">
                <div class="min-w-0 pr-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Signed In As</span>
                    <h4 class="mt-2 truncate text-lg font-bold text-gray-800 dark:text-white/90">{{ auth()->user()->name }}</h4>
                </div>
                <x-ui.badge variant="light" color="warning" size="sm">Active</x-ui.badge>
            </div>
            <p class="mt-3 truncate text-xs text-gray-500 dark:text-gray-400">
                {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'Standard User' }}
            </p>
        </div>
    </div>

    {{-- Quick Action Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:gap-4">
        <a href="{{ route('products.index') }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-xs hover:border-brand-500 dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-500 transition-all">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-brand-500/20 dark:group-hover:text-brand-400 transition-colors">
                <x-svg.box class="size-5" />
            </div>
            <div>
                <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">Products</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Catalog & items</span>
            </div>
        </a>

        <a href="{{ route('inventory.index') }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-xs hover:border-brand-500 dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-500 transition-all">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-brand-500/20 dark:group-hover:text-brand-400 transition-colors">
                <x-svg.table class="size-5" />
            </div>
            <div>
                <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">Inventory</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Stock & warehouses</span>
            </div>
        </a>

        <a href="{{ route('pos.index') }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-xs hover:border-brand-500 dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-500 transition-all">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-brand-500/20 dark:group-hover:text-brand-400 transition-colors">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <div>
                <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">POS Terminal</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Quick retail sales</span>
            </div>
        </a>

        <a href="{{ route('users.index') }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-xs hover:border-brand-500 dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-500 transition-all">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-700 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-brand-500/20 dark:group-hover:text-brand-400 transition-colors">
                <x-svg.user-circle class="size-5" />
            </div>
            <div>
                <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">User Accounts</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Manage team</span>
            </div>
        </a>
    </div>

    {{-- Recently Added Users Table --}}
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 shadow-theme-xs">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">
                    Recently Added Users
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Latest accounts registered in the platform</p>
            </div>
            <a href="{{ route('users.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors">
                View All Users &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3.5 font-semibold">User</th>
                        <th class="px-6 py-3.5 font-semibold">Email</th>
                        <th class="px-6 py-3.5 font-semibold">Roles</th>
                        <th class="px-6 py-3.5 font-semibold">Status</th>
                        <th class="px-6 py-3.5 font-semibold">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($recentUsers as $user)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </span>
                                    <span>{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <x-ui.badge variant="light" color="brand" size="sm">{{ $role->name }}</x-ui.badge>
                                    @empty
                                        <span class="text-xs text-gray-400">No role</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <x-status-badge :status="$user->status->value" />
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                {{ $user->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                No users recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
