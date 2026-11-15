@extends('layouts.app')

@section('title', 'Dashboard — Quantora ERP')
@section('page_title', 'Dashboard')

@php
    // Safe backend data extraction: use real model data if ready, or gracefully fallback
    $totalSuppliersCount = $totalSuppliers ?? (\class_exists(\App\Models\Supplier::class) ? rescue(fn () => \App\Models\Supplier::count(), 6987) : 6987);
    $totalCustomersCount = $totalCustomers ?? (\class_exists(\App\Models\Customer::class) ? rescue(fn () => \App\Models\Customer::count(), 4896) : 4896);
    $totalOrdersCount = $totalOrders ?? 567;

    $totalSalesValue = $totalSalesValue ?? 48988;
    $salesReturnValue = $salesReturnValue ?? 16478;
    $totalPurchaseValue = $totalPurchaseValue ?? 24145;
    $purchaseReturnValue = $purchaseReturnValue ?? 18458;

    // Top Selling Products dataset
    $topSellingProducts = [
        [
            'name' => 'Charger Cable - Lighting',
            'details' => '$187 • 247+ Sales',
            'change' => '25%',
            'direction' => 'up',
            'color' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'icon' => 'box',
        ],
        [
            'name' => 'Yves Saint Eau De Parfum',
            'details' => '$145 • 350+ Sales',
            'change' => '20%',
            'direction' => 'down',
            'color' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
            'icon' => 'tag',
        ],
        [
            'name' => 'Apple AirPods 2 Wireless',
            'details' => '$199 • 180+ Sales',
            'change' => '18%',
            'direction' => 'up',
            'color' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
            'icon' => 'apple',
        ],
        [
            'name' => 'Leather Wallet & Cardholder',
            'details' => '$89 • 120+ Sales',
            'change' => '12%',
            'direction' => 'up',
            'color' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
            'icon' => 'box',
        ],
    ];

    // Recent Transactions dataset
    $recentTransactions = [
        [
            'id' => '476-803',
            'product' => 'Premium T-Shirt',
            'details' => '1 Pcs • Size M',
            'status' => 'Success',
            'status_color' => 'success',
            'amount' => '$24.51',
            'avatar_bg' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
        ],
        [
            'id' => '476-894',
            'product' => 'KitchenAid Stand Mixer',
            'details' => '1 Pcs • 5.5 Quart',
            'status' => 'Pending',
            'status_color' => 'warning',
            'amount' => '$24.51',
            'avatar_bg' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
        ],
        [
            'id' => '476-921',
            'product' => 'Ergonomic Desk Chair',
            'details' => '1 Pcs • Black Mesh',
            'status' => 'Success',
            'status_color' => 'success',
            'amount' => '$89.00',
            'avatar_bg' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/15 dark:text-purple-400',
        ],
        [
            'id' => '476-955',
            'product' => 'Fast Charging PowerBank',
            'details' => '2 Pcs • 20000mAh',
            'status' => 'Success',
            'status_color' => 'success',
            'amount' => '$45.20',
            'avatar_bg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
        ],
    ];
@endphp

@section('content')
<div class="space-y-6">

    {{-- Top Header Row: Dashboard Title & Action Buttons --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Dashboard
            </h1>
        </div>

        <div class="flex items-center gap-3">
            {{-- + Add New Dropdown Button --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button"
                        @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-500/20 transition-all">
                    <x-svg.plus class="size-4" />
                    <span>Add New</span>
                    <x-svg.chevron-down class="size-3.5 opacity-80" />
                </button>

                <div x-show="open"
                     @click.outside="open = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 z-30 mt-2 w-48 rounded-xl border border-gray-100 bg-white p-1.5 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
                     x-cloak>
                    <a href="{{ Route::has('sales-invoices.index') ? route('sales-invoices.index') : '#' }}"
                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                        <x-svg.bar-chart class="size-4 text-brand-500" />
                        <span>New Sale Invoice</span>
                    </a>
                    <a href="{{ Route::has('purchase-invoices.index') ? route('purchase-invoices.index') : '#' }}"
                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                        <x-svg.box class="size-4 text-orange-500" />
                        <span>New Purchase Order</span>
                    </a>
                    <a href="{{ Route::has('customers.create') ? route('customers.create') : (Route::has('customers.index') ? route('customers.index') : '#') }}"
                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                        <x-svg.users class="size-4 text-emerald-500" />
                        <span>Add Customer</span>
                    </a>
                </div>
            </div>

            {{-- Import Outline Button --}}
            <a href="{{ Route::has('customers.import') ? route('customers.import') : (Route::has('suppliers.import') ? route('suppliers.import') : '#') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-900 dark:border-gray-800 dark:bg-white/3 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white transition-all">
                <svg class="size-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>Import</span>
            </a>
        </div>
    </div>

    {{-- 1. Top Row: 4 KPI Cards (Total Sales, Sales Return, Total Purchase, Purchase Return) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-5">

        {{-- Metric 1: Total Sales --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 hover:shadow-theme-sm transition-all">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total Sales</span>
                <div x-data="{ open: false, selected: 'Weekly' }" class="relative">
                    <button @click="open = !open" type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                        <span x-text="selected"></span>
                        <x-svg.chevron-down class="size-3 text-gray-400" />
                    </button>
                    <div x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-1 w-24 rounded-lg border border-gray-100 bg-white p-1 text-xs shadow-theme-md dark:border-gray-700 dark:bg-gray-800" x-cloak>
                        <button @click="selected = 'Daily'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Daily</button>
                        <button @click="selected = 'Weekly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Weekly</button>
                        <button @click="selected = 'Monthly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Monthly</button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400 shrink-0">
                    <span class="text-base font-bold">@</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <h3 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        ${{ number_format($totalSalesValue) }}
                    </h3>
                    <x-ui.badge color="success" size="sm">
                        <x-svg.arrow-up class="size-3" />
                        25%
                    </x-ui.badge>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                <span class="text-gray-400 dark:text-gray-500">Sales vs last month</span>
                <a href="{{ Route::has('sales-invoices.index') ? route('sales-invoices.index') : '#' }}"
                   class="inline-flex items-center gap-0.5 font-medium text-gray-500 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 transition-colors">
                    <span>View all</span>
                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>

        {{-- Metric 2: Sales Return --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 hover:shadow-theme-sm transition-all">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sales Return</span>
                <div x-data="{ open: false, selected: 'Weekly' }" class="relative">
                    <button @click="open = !open" type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                        <span x-text="selected"></span>
                        <x-svg.chevron-down class="size-3 text-gray-400" />
                    </button>
                    <div x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-1 w-24 rounded-lg border border-gray-100 bg-white p-1 text-xs shadow-theme-md dark:border-gray-700 dark:bg-gray-800" x-cloak>
                        <button @click="selected = 'Daily'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Daily</button>
                        <button @click="selected = 'Weekly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Weekly</button>
                        <button @click="selected = 'Monthly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Monthly</button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-full bg-cyan-50 text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-400 shrink-0">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <div class="flex items-center gap-2.5">
                    <h3 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        ${{ number_format($salesReturnValue) }}
                    </h3>
                    <x-ui.badge color="error" size="sm">
                        <x-svg.arrow-down class="size-3 text-error-500" />
                        25%
                    </x-ui.badge>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                <span class="text-gray-400 dark:text-gray-500">Sales return vs last month</span>
                <a href="{{ Route::has('sales-returns.index') ? route('sales-returns.index') : '#' }}"
                   class="inline-flex items-center gap-0.5 font-medium text-gray-500 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 transition-colors">
                    <span>View all</span>
                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>

        {{-- Metric 3: Total Purchase --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 hover:shadow-theme-sm transition-all">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total Purchase</span>
                <div x-data="{ open: false, selected: 'Weekly' }" class="relative">
                    <button @click="open = !open" type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                        <span x-text="selected"></span>
                        <x-svg.chevron-down class="size-3 text-gray-400" />
                    </button>
                    <div x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-1 w-24 rounded-lg border border-gray-100 bg-white p-1 text-xs shadow-theme-md dark:border-gray-700 dark:bg-gray-800" x-cloak>
                        <button @click="selected = 'Daily'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Daily</button>
                        <button @click="selected = 'Weekly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Weekly</button>
                        <button @click="selected = 'Monthly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Monthly</button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-full bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400 shrink-0">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div class="flex items-center gap-2.5">
                    <h3 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        ${{ number_format($totalPurchaseValue) }}
                    </h3>
                    <x-ui.badge color="success" size="sm">
                        <x-svg.arrow-up class="size-3" />
                        25%
                    </x-ui.badge>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                <span class="text-gray-400 dark:text-gray-500">Purchase vs last month</span>
                <a href="{{ Route::has('purchase-invoices.index') ? route('purchase-invoices.index') : '#' }}"
                   class="inline-flex items-center gap-0.5 font-medium text-gray-500 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 transition-colors">
                    <span>View all</span>
                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>

        {{-- Metric 4: Purchase Return --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 hover:shadow-theme-sm transition-all">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Purchase Return</span>
                <div x-data="{ open: false, selected: 'Weekly' }" class="relative">
                    <button @click="open = !open" type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                        <span x-text="selected"></span>
                        <x-svg.chevron-down class="size-3 text-gray-400" />
                    </button>
                    <div x-show="open" @click.outside="open = false" class="absolute right-0 z-20 mt-1 w-24 rounded-lg border border-gray-100 bg-white p-1 text-xs shadow-theme-md dark:border-gray-700 dark:bg-gray-800" x-cloak>
                        <button @click="selected = 'Daily'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Daily</button>
                        <button @click="selected = 'Weekly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Weekly</button>
                        <button @click="selected = 'Monthly'; open = false" type="button" class="w-full rounded px-2 py-1 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">Monthly</button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400 shrink-0">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m0 0l-6-6m6 6H3" />
                    </svg>
                </div>
                <div class="flex items-center gap-2.5">
                    <h3 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        ${{ number_format($purchaseReturnValue) }}
                    </h3>
                    <x-ui.badge color="success" size="sm">
                        <x-svg.arrow-up class="size-3" />
                        25%
                    </x-ui.badge>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                <span class="text-gray-400 dark:text-gray-500">Purchase return vs last month</span>
                <a href="{{ Route::has('purchase-returns.index') ? route('purchase-returns.index') : '#' }}"
                   class="inline-flex items-center gap-0.5 font-medium text-gray-500 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 transition-colors">
                    <span>View all</span>
                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>

    </div>

    {{-- 2. Middle Row: Sales & Purchase Chart (Left) + Overall Info & Customers Overview (Right) --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- Left: Sales & Purchase Chart with TailAdmin Native Bar & Line Components --}}
        <div class="col-span-12 xl:col-span-7 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 flex flex-col justify-between"
             x-data="{
                chartType: 'bar',
                period: '1Y',
                setChart(type) {
                    this.chartType = type;
                    this.$nextTick(() => {
                        window.dispatchEvent(new Event('resize'));
                    });
                }
             }">

            {{-- Card Header --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Sales & Purchase
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Comprehensive transaction analytics</p>
                    </div>
                </div>

                {{-- Chart Type Switcher & Time Period Filters --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Component Switcher (Bar vs Line Chart) --}}
                    <div class="flex items-center rounded-lg bg-gray-100 p-0.5 text-xs font-semibold dark:bg-gray-800/80">
                        <button type="button"
                                @click="setChart('bar')"
                                :class="chartType === 'bar' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                                class="flex items-center gap-1.5 rounded-md px-2.5 py-1 transition-all">
                            <x-svg.bar-chart class="size-3.5" />
                            <span>Bar</span>
                        </button>
                        <button type="button"
                                @click="setChart('line')"
                                :class="chartType === 'line' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                                class="flex items-center gap-1.5 rounded-md px-2.5 py-1 transition-all">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                            <span>Line</span>
                        </button>
                    </div>

                    {{-- Period Pills (1D, 1W, 1M, 1Y) --}}
                    <div class="flex items-center rounded-lg bg-gray-100 p-0.5 text-xs font-semibold dark:bg-gray-800/80">
                        <button type="button" @click="period = '1D'" :class="period === '1D' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'" class="rounded-md px-2 py-1 transition-all">1D</button>
                        <button type="button" @click="period = '1W'" :class="period === '1W' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'" class="rounded-md px-2 py-1 transition-all">1W</button>
                        <button type="button" @click="period = '1M'" :class="period === '1M' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'" class="rounded-md px-2 py-1 transition-all">1M</button>
                        <button type="button" @click="period = '1Y'" :class="period === '1Y' ? 'bg-gray-900 text-white shadow-theme-xs dark:bg-brand-500 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'" class="rounded-md px-2 py-1 transition-all">1Y</button>
                    </div>
                </div>
            </div>

            {{-- TailAdmin Chart Mount Point --}}
            <div class="mt-5">
                {{-- Native TailAdmin Bar Chart Component --}}
                <div x-show="chartType === 'bar'" x-transition.opacity>
                    <x-main.others.charts.bar.two />
                </div>

                {{-- Native TailAdmin Line Chart Component --}}
                <div x-show="chartType === 'line'" x-cloak x-transition.opacity>
                    <x-main.others.charts.line.two />
                </div>
            </div>
        </div>

        {{-- Right: Overall Info & Customers Overview --}}
        <div class="col-span-12 xl:col-span-5 flex flex-col gap-6">

            {{-- 2A. Overall Information Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3">
                <div class="flex items-center gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div class="flex size-7 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">
                        <x-svg.info-circle class="size-4" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        Overall Information
                    </h3>
                </div>

                {{-- 3 Horizontal Stat Blocks (Matches Reference UI) --}}
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {{-- Block 1: Suppliers (Dark Card) --}}
                    <a href="{{ Route::has('suppliers.index') ? route('suppliers.index') : '#' }}"
                       class="group rounded-xl bg-gray-950 p-3.5 text-white shadow-theme-xs hover:bg-gray-900 dark:bg-brand-500/20 dark:hover:bg-brand-500/30 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-extrabold tracking-tight text-white">
                                {{ number_format($totalSuppliersCount) }}
                            </span>
                            <x-svg.chevron-right class="size-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" />
                        </div>
                        <div class="mt-2 flex items-center gap-1.5 text-xs text-gray-300">
                            <x-svg.users class="size-3.5 text-gray-400" />
                            <span>Suppliers</span>
                        </div>
                    </a>

                    {{-- Block 2: Customers (Light Card) --}}
                    <a href="{{ Route::has('customers.index') ? route('customers.index') : '#' }}"
                       class="group rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 text-gray-900 hover:bg-gray-100 dark:border-gray-800 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                                {{ number_format($totalCustomersCount) }}
                            </span>
                            <x-svg.chevron-right class="size-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" />
                        </div>
                        <div class="mt-2 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-svg.user-circle class="size-3.5 text-gray-400" />
                            <span>Customer</span>
                        </div>
                    </a>

                    {{-- Block 3: Orders (Light Card) --}}
                    <a href="{{ Route::has('sales-invoices.index') ? route('sales-invoices.index') : '#' }}"
                       class="group rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 text-gray-900 hover:bg-gray-100 dark:border-gray-800 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                                {{ number_format($totalOrdersCount) }}
                            </span>
                            <x-svg.chevron-right class="size-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" />
                        </div>
                        <div class="mt-2 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-svg.box class="size-3.5 text-gray-400" />
                            <span>Orders</span>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 2B. Customers Overview Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 flex-1 flex flex-col justify-between">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-7 items-center justify-center rounded-full bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400">
                            <x-svg.users class="size-4" />
                        </div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Customers Overview
                        </h3>
                    </div>
                    <div class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:border-gray-700 dark:text-gray-400">
                        <span>Weekly</span>
                        <x-svg.chevron-down class="size-3 text-gray-400" />
                    </div>
                </div>

                {{-- Visual Representation & Numerical Overview --}}
                <div class="mt-4 flex items-center justify-between gap-6">
                    {{-- Left: Dual Target Container Cards (Green & Coral Red) --}}
                    <div class="flex items-center gap-3">
                        <div class="relative flex h-24 w-18 flex-col items-center justify-between rounded-2xl bg-emerald-500 p-2 text-white shadow-xs">
                            <div class="h-10 w-px border-l-2 border-dashed border-white/60 relative">
                                <span class="absolute -bottom-1 -left-1 size-2 rounded-full bg-white shadow-xs"></span>
                            </div>
                            <span class="text-[10px] font-bold tracking-tight text-white/95">First Time</span>
                        </div>

                        <div class="relative flex h-24 w-18 flex-col items-center justify-between rounded-2xl bg-rose-500 p-2 text-white shadow-xs">
                            <div class="h-8 w-px border-l-2 border-dashed border-white/60 relative">
                                <span class="absolute -bottom-1 -left-1 size-2 rounded-full bg-white shadow-xs"></span>
                            </div>
                            <span class="text-[10px] font-bold tracking-tight text-white/95">Return</span>
                        </div>
                    </div>

                    {{-- Right: Numerical Breakdown with TailAdmin Badge --}}
                    <div class="flex-1 space-y-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-2xl font-extrabold text-gray-900 dark:text-white">5.5K</span>
                                <x-ui.badge color="success" size="sm">
                                    <x-svg.arrow-up class="size-3" />
                                    65%
                                </x-ui.badge>
                            </div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">First Time</span>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-2xl font-extrabold text-gray-900 dark:text-white">3.5K</span>
                            </div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Return</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- 3. Bottom Row: Top Selling Products (Left) + Recent Transaction Table (Right) --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- Left: Top Selling Products (Col-span 12 xl:col-span-5) --}}
        <div class="col-span-12 xl:col-span-5 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-7 items-center justify-center rounded-full bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400">
                            <x-svg.target class="size-4" />
                        </div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Top Selling Products
                        </h3>
                    </div>

                    <div class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:border-gray-700 dark:text-gray-400">
                        <span>Weekly</span>
                        <x-svg.chevron-down class="size-3 text-gray-400" />
                    </div>
                </div>

                {{-- Product List --}}
                <div class="mt-5 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($topSellingProducts as $item)
                        <div class="flex items-center justify-between py-3.5 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-3 min-w-0 pr-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $item['color'] }}">
                                    @if($item['icon'] === 'apple')
                                        <x-svg.apple class="size-5" />
                                    @else
                                        <x-svg.box class="size-5" />
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <h4 class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $item['name'] }}
                                    </h4>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $item['details'] }}
                                    </p>
                                </div>
                            </div>

                            <x-ui.badge :color="$item['direction'] === 'down' ? 'error' : 'success'" size="sm">
                                @if($item['direction'] === 'down')
                                    <x-svg.arrow-down class="size-3 text-error-500" />
                                @else
                                    <x-svg.arrow-up class="size-3" />
                                @endif
                                {{ $item['change'] }}
                            </x-ui.badge>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 border-t border-gray-100 pt-3 text-center dark:border-gray-800">
                <a href="{{ Route::has('products.index') ? route('products.index') : '#' }}"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors">
                    <span>Explore full product catalog</span>
                    <x-svg.arrow-right class="size-3" />
                </a>
            </div>
        </div>

        {{-- Right: Recent Transaction Table (Col-span 12 xl:col-span-7) --}}
        <div class="col-span-12 xl:col-span-7 rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <div class="flex size-7 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                            <x-svg.file class="size-4" />
                        </div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Recent Transaction
                        </h3>
                    </div>

                    <x-ui.button variant="outline" size="sm" class="h-8 gap-1.5 text-xs">
                        <x-svg.horizontal-slide class="size-3.5" />
                        <span>Filter</span>
                    </x-ui.button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50/50 uppercase text-[11px] text-gray-400 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-500 font-semibold">
                            <tr>
                                <th class="w-12 px-5 py-3.5">
                                    <input type="checkbox" class="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800" />
                                </th>
                                <th class="px-3 py-3.5">ID Transaction</th>
                                <th class="px-5 py-3.5">Product</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($recentTransactions as $tx)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-4">
                                        <input type="checkbox" class="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800" />
                                    </td>
                                    <td class="px-3 py-4 font-semibold text-gray-800 dark:text-gray-200">
                                        {{ $tx['id'] }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tx['avatar_bg'] }}">
                                                <x-svg.box class="size-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <span class="block font-semibold text-gray-900 dark:text-white truncate">
                                                    {{ $tx['product'] }}
                                                </span>
                                                <span class="block text-xs text-gray-400 dark:text-gray-500">
                                                    {{ $tx['details'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <x-ui.badge :color="$tx['status_color']" size="sm">
                                            {{ $tx['status'] }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-gray-900 dark:text-white">
                                        {{ $tx['amount'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-t border-gray-100 p-4 dark:border-gray-800 text-center">
                <a href="{{ Route::has('sales-invoices.index') ? route('sales-invoices.index') : '#' }}"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors">
                    <span>View all transaction history</span>
                    <x-svg.arrow-right class="size-3" />
                </a>
            </div>
        </div>

    </div>

</div>
@endsection
