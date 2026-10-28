<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO / PWA Meta --}}
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description', 'Cox\'s Bazaar Mobile Distribution ERP')">
    <meta name="theme-color" content="#c0392b">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body>

<div class="app-shell">

    {{-- Sidebar --}}
    <aside class="sidebar" :class="{ 'is-open': sidebarOpen }" id="sidebar">

        {{-- Logo --}}
        <div class="sidebar-logo">
            <div class="sidebar-logo-text">📱 {{ config('app.name') }}</div>
            <div class="sidebar-logo-sub">Distribution ERP · Cox's Bazaar</div>
        </div>

        {{-- Nav --}}
        <nav class="sidebar-nav">

            {{-- Dashboard --}}
            <div class="sidebar-section">
                <a href="{{ route('dashboard') }}"
                   class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
            </div>

            {{-- Master Data --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Master Data</div>

                <div x-data="{ open: {{ request()->routeIs('brands.*','categories.*','units.*','products.*') ? 'true' : 'false' }} }">
                    <button class="nav-group-trigger" @click="open=!open" :aria-expanded="open">
                        <span style="display:flex;align-items:center;gap:10px;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Products
                        </span>
                        <svg class="chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="nav-sub-items" x-show="open" x-collapse.duration.150ms>
                        <a href="{{ route('brands.index') }}" class="{{ request()->routeIs('brands.*') ? 'active' : '' }}">Brands</a>
                        <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Categories</a>
                        <a href="{{ route('units.index') }}" class="{{ request()->routeIs('units.*') ? 'active' : '' }}">Units</a>
                        <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">Products</a>
                    </div>
                </div>
            </div>

            {{-- Parties --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Parties</div>
                <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                    Suppliers
                </a>
                <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Customers
                </a>
            </div>

            {{-- Inventory --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Inventory</div>
                <a href="{{ route('warehouses.index') }}" class="{{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Warehouses
                </a>
                <a href="{{ route('inventory.index') }}" class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Stock Levels
                </a>
                <a href="{{ route('stock-adjustments.index') }}" class="{{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    Adjustments
                </a>
                <a href="{{ route('stock-transfers.index') }}" class="{{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transfers
                </a>
            </div>

            {{-- Purchasing --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Purchasing</div>
                <a href="{{ route('purchase-orders.index') }}" class="{{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Purchase Orders
                </a>
                <a href="{{ route('purchase-invoices.index') }}" class="{{ request()->routeIs('purchase-invoices.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Purchase Invoices
                </a>
                <a href="{{ route('purchase-returns.index') }}" class="{{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    Purchase Returns
                </a>
            </div>

            {{-- Sales --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Sales</div>
                <a href="{{ route('sales-orders.index') }}" class="{{ request()->routeIs('sales-orders.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    Sales Orders
                </a>
                <a href="{{ route('sales-invoices.index') }}" class="{{ request()->routeIs('sales-invoices.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-4-7 4V5a2 2 0 012-2h10a2 2 0 012 2v16z"/></svg>
                    Sales Invoices
                </a>
                <a href="{{ route('sales-returns.index') }}" class="{{ request()->routeIs('sales-returns.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    Sales Returns
                </a>
                <a href="{{ route('customer-collections.index') }}" class="{{ request()->routeIs('customer-collections.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Collections
                </a>
            </div>

            {{-- POS --}}
            @canany(['pos.access'])
            <div class="sidebar-section">
                <div class="sidebar-section-label">Point of Sale</div>
                <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    POS Terminal
                </a>
            </div>
            @endcanany

            {{-- Finance --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Finance</div>

                <div x-data="{ open: {{ request()->routeIs('cash-accounts.*','bank-accounts.*','income-transactions.*','expense-transactions.*','account-transfers.*','supplier-payments.*') ? 'true' : 'false' }} }">
                    <button class="nav-group-trigger" @click="open=!open" :aria-expanded="open">
                        <span style="display:flex;align-items:center;gap:10px;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Finance
                        </span>
                        <svg class="chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="nav-sub-items" x-show="open" x-collapse.duration.150ms>
                        <a href="{{ route('cash-accounts.index') }}" class="{{ request()->routeIs('cash-accounts.*') ? 'active' : '' }}">Cash Accounts</a>
                        <a href="{{ route('bank-accounts.index') }}" class="{{ request()->routeIs('bank-accounts.*') ? 'active' : '' }}">Bank Accounts</a>
                        <a href="{{ route('income-transactions.index') }}" class="{{ request()->routeIs('income-transactions.*') ? 'active' : '' }}">Income</a>
                        <a href="{{ route('expense-transactions.index') }}" class="{{ request()->routeIs('expense-transactions.*') ? 'active' : '' }}">Expenses</a>
                        <a href="{{ route('account-transfers.index') }}" class="{{ request()->routeIs('account-transfers.*') ? 'active' : '' }}">Transfers</a>
                        <a href="{{ route('supplier-payments.index') }}" class="{{ request()->routeIs('supplier-payments.*') ? 'active' : '' }}">Supplier Payments</a>
                    </div>
                </div>
            </div>

            {{-- Reports --}}
            <div class="sidebar-section">
                <div class="sidebar-section-label">Reports</div>
                <a href="{{ route('reports.sales') }}" class="{{ request()->routeIs('reports.sales') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Reports
                </a>
            </div>

            {{-- Admin --}}
            @can('manage-users')
            <div class="sidebar-section">
                <div class="sidebar-section-label">Administration</div>
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Users
                </a>
                <a href="{{ route('roles.index') }}" class="{{ request()->routeIs('roles.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Roles & Permissions
                </a>
                <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Audit Logs
                </a>
                <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Settings
                </a>
            </div>
            @endcan

        </nav>

        {{-- User info at bottom --}}
        <div style="margin-top:auto;padding:14px 20px;border-top:1px solid rgba(255,255,255,.06);">
            <div style="font-size:.78rem;color:#718096;">{{ auth()->user()->name ?? '' }}</div>
            <div style="font-size:.72rem;color:#4a5568;">{{ auth()->user()->email ?? '' }}</div>
        </div>

    </aside>

    {{-- Mobile Overlay --}}
    <div class="sidebar-overlay" :class="{ 'is-open': sidebarOpen }"
         @click="sidebarOpen = false" id="sidebar-overlay"></div>

    {{-- Main --}}
    <div class="main-content">

        {{-- Topbar --}}
        <header class="topbar">
            <button class="sidebar-toggle" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle navigation">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <span class="topbar-title">@yield('page_title', 'Dashboard')</span>

            {{-- Topbar right --}}
            <div style="display:flex;align-items:center;gap:8px;">
                {{-- Online/offline dot --}}
                <span id="connection-dot" style="width:8px;height:8px;border-radius:50%;background:#27ae60;" title="Online"></span>

                {{-- User menu --}}
                <div x-data="{ open: false }" style="position:relative;">
                    <button @click="open=!open" style="display:flex;align-items:center;gap:8px;background:transparent;border:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:.82rem;color:var(--color-text);">
                        <span style="width:28px;height:28px;border-radius:50%;background:var(--color-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                        </span>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" @click.away="open=false"
                         style="position:absolute;right:0;top:calc(100% + 6px);min-width:180px;background:#fff;border:1px solid var(--color-border);border-radius:8px;box-shadow:var(--shadow-dropdown);z-index:50;">
                        <div style="padding:10px 14px;border-bottom:1px solid var(--color-border);">
                            <div style="font-size:.82rem;font-weight:600;">{{ auth()->user()->name ?? '' }}</div>
                            <div style="font-size:.74rem;color:#718096;">{{ auth()->user()->roles->pluck('name')->implode(', ') ?? '' }}</div>
                        </div>
                        <a href="{{ route('profile.edit') }}" style="display:block;padding:9px 14px;font-size:.82rem;color:var(--color-text);text-decoration:none;" @click="open=false">
                            Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" style="width:100%;text-align:left;padding:9px 14px;font-size:.82rem;color:#a93226;background:transparent;border:none;cursor:pointer;border-top:1px solid var(--color-border);">
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash Messages --}}
        <div style="padding:0 24px;margin-top:16px;">
            @if(session('success'))
                <div class="alert alert-success" data-flash-dismiss="4000">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger" data-flash-dismiss="6000">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning" data-flash-dismiss="5000">
                    {{ session('warning') }}
                </div>
            @endif
        </div>

        {{-- Page Content --}}
        <main class="page-content" id="main-content">
            @yield('content')
        </main>

    </div>{{-- /.main-content --}}

</div>{{-- /.app-shell --}}

{{-- Offline Banner --}}
<div class="offline-banner" id="offline-banner">
    ⚠ You are offline. Transaction actions require an internet connection.
</div>

{{-- Connection dot script --}}
<script>
    const dot = document.getElementById('connection-dot');
    if (dot) {
        const update = () => {
            dot.style.background = navigator.onLine ? '#27ae60' : '#c0392b';
            dot.title = navigator.onLine ? 'Online' : 'Offline';
        };
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        update();
    }
</script>

@stack('scripts')
</body>
</html>
