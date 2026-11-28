@php
use App\Helpers\MenuHelper;
$isSupplierPortal = auth()->user()?->isSupplierPortalUser() ?? false;
$sidebarUnreadCount = \App\Services\NotificationService::unreadCountFor(auth()->user());
$sidebarUnreadBadge = $sidebarUnreadCount > 0 ? ($sidebarUnreadCount > 99 ? '99+' : (string) $sidebarUnreadCount) : null;
$menuGroups = $isSupplierPortal ? MenuHelper::getSupplierPortalGroups($sidebarUnreadBadge) : MenuHelper::getMenuGroups();

// Realign navigation for intuitive ERP UX:
// 1. "Inventory" holds operational product & stock items (Products, Stocks, Transfers, Adjustments, Branches)
// 2. "Master Data" is a dedicated section for administrative control of core lookups (Brands, Categories, Units)
// 3. "Notifications" is nested under "Administration"
// 4. Order sections logically: Operations -> Logistics & Partners -> Finance & Reports -> Master Data & Administration
foreach ($menuGroups as &$groupRef) {
  if (($groupRef['title'] ?? '') !== 'Menu' || empty($groupRef['items']) || $isSupplierPortal) {
    continue;
  }

  $itemMap = collect($groupRef['items'])->keyBy('name');

  // Master Data sub-items
  $masterSubMap = collect($itemMap->get('Master Data')['subItems'] ?? [])->keyBy('name');
  // Inventory sub-items
  $invSubMap = collect($itemMap->get('Inventory')['subItems'] ?? [])->keyBy('name');

  // 1. Inventory: Product catalog + physical stock operations & branches
  $inventorySubItems = array_values(array_filter([
    $masterSubMap->get('Products'),
    $invSubMap->get('Stocks'),
    $invSubMap->get('Transfers'),
    $invSubMap->get('Adjustments'),
    $invSubMap->get('Branches'),
  ]));

  $inventoryCanAny = [
    'products.view', 'products.manage',
    'inventory.view',
    'stock-transfers.manage',
    'stock-adjustments.manage',
    'branches.manage',
  ];

  $alignedInventory = [
    'icon' => 'box',
    'name' => 'Inventory',
    'canany' => $inventoryCanAny,
    'subItems' => $inventorySubItems,
  ];

  // 2. Master Data: Administrative control of foundational classifications (Brands, Categories, Units)
  $masterDataSubItems = array_values(array_filter([
    $masterSubMap->get('Brands'),
    $masterSubMap->get('Categories'),
    $masterSubMap->get('Units'),
  ]));

  $masterDataCanAny = [
    'brands.view', 'brands.manage',
    'categories.view', 'categories.manage',
    'units.view', 'units.manage',
  ];

  $alignedMasterData = [
    'icon' => 'tables',
    'name' => 'Master Data',
    'canany' => $masterDataCanAny,
    'subItems' => $masterDataSubItems,
  ];

  // 3. Administration with nested Notifications
  $adminItem = $itemMap->get('Administration');
  $adminSubItems = $adminItem['subItems'] ?? [];

  $notificationSubItem = [
    'name' => 'Notifications',
    'path' => '/notifications',
    'can' => 'dashboard.view',
    'badge' => $sidebarUnreadCount > 0 ? ($sidebarUnreadCount > 99 ? '99+' : (string) $sidebarUnreadCount) : null,
  ];

  $hasNotifications = collect($adminSubItems)->contains(fn ($s) => ($s['path'] ?? '') === '/notifications');
  if (!$hasNotifications) {
    $adminSubItems[] = $notificationSubItem;
  }

  $adminCanAny = array_values(array_unique(array_merge(
    (array) ($adminItem['canany'] ?? []),
    ['dashboard.view']
  )));

  $alignedAdmin = [
    'icon' => $adminItem['icon'] ?? 'authentication',
    'name' => 'Administration',
    'canany' => $adminCanAny,
    'subItems' => $adminSubItems,
  ];

  $user = auth()->user();
  $isBranchOutlet = $user && ($user->hasRole('Outlet Manager') || $user->hasRole('Sales Staff'));

  $partiesItem = $itemMap->get('Parties');
  if ($isBranchOutlet && $partiesItem) {
    // Branch Outlets only interact with Customers; Suppliers are centrally managed by HQ
    $partiesItem['subItems'] = array_values(array_filter(
      $partiesItem['subItems'] ?? [],
      fn ($s) => ($s['name'] ?? '') === 'Customers'
    ));
  }

  $financeItem = $itemMap->get('Finance');
  if ($isBranchOutlet && $financeItem) {
    // Branch Outlets only perform Cash Remittance to HQ and Daily Cash Close
    $financeItem['subItems'] = array_values(array_map(
      function ($s) {
        if (($s['name'] ?? '') === 'Transfer') {
          $s['name'] = 'Cash Remittance';
        }
        return $s;
      },
      array_filter(
        $financeItem['subItems'] ?? [],
        fn ($s) => in_array($s['name'] ?? '', ['Transfer', 'Daily Cash Close', 'Cash Remittance'], true)
      )
    ));
  }

  // Master Data & Purchasing are HQ/Warehouse only — hide for branch outlets
  $masterDataItem = $isBranchOutlet ? null : $alignedMasterData;
  $purchasingItem = $isBranchOutlet ? null : $itemMap->get('Purchasing');

  // Ordered items:
  // For branch outlet: Dashboard > POS > Sales > Inventory > Parties (Customers) > Finance (Remittance & Close) > Reports > Notifications
  // For HQ/Super Admin: Dashboard > Master data > Parties > Inventory > Purchasing > Sales > POS > Finance > Reports > Administration
  if ($isBranchOutlet) {
    $notificationItem = [
      'icon' => 'bell',
      'name' => 'Notifications',
      'path' => '/notifications',
      'can' => 'dashboard.view',
      'badge' => $sidebarUnreadCount > 0 ? ($sidebarUnreadCount > 99 ? '99+' : (string) $sidebarUnreadCount) : null,
    ];

    $alignedItems = array_values(array_filter([
      $itemMap->get('Dashboard'),
      $itemMap->get('POS Terminal'),
      $itemMap->get('Sales'),
      $alignedInventory,
      $partiesItem,
      $financeItem,
      $itemMap->get('Reports'),
      $notificationItem,
    ]));
  } else {
    $alignedItems = array_values(array_filter([
      $itemMap->get('Dashboard'),
      $masterDataItem,
      $partiesItem,
      $alignedInventory,
      $purchasingItem,
      $itemMap->get('Sales'),
      $itemMap->get('POS Terminal'),
      $financeItem,
      $itemMap->get('Reports'),
      $alignedAdmin,
    ]));
  }

  $groupRef['items'] = $alignedItems;
}
unset($groupRef);

$currentPath = '/'.ltrim(request()->path(), '/');
$currentPath = $currentPath === '//' ? '/' : rtrim($currentPath, '/');
if ($currentPath === '') {
    $currentPath = '/';
}
$isDashboard = request()->routeIs('dashboard') || request()->is('dashboard') || $currentPath === '/dashboard' || $currentPath === '/';

$allMenuPaths = [];
foreach ($menuGroups as $group) {
    foreach ($group['items'] as $item) {
        if (!empty($item['path'])) {
            $allMenuPaths[] = $item['path'];
        }
        if (!empty($item['subItems'])) {
            foreach ($item['subItems'] as $sub) {
                if (!empty($sub['path'])) {
                    $allMenuPaths[] = $sub['path'];
                }
                if (!empty($sub['subItems'])) {
                    foreach ($sub['subItems'] as $childSub) {
                        if (!empty($childSub['path'])) {
                            $allMenuPaths[] = $childSub['path'];
                        }
                    }
                }
            }
        }
    }
}
$allMenuPaths = array_values(array_unique(array_filter($allMenuPaths)));

$isMenuPathActive = static function (?string $path) use ($currentPath, $allMenuPaths): bool {
    if (empty($path)) {
        return false;
    }

    if (MenuHelper::isActive($path)) {
        $target = '/'.ltrim(rtrim($path, '/'), '/');
        if ($target === $currentPath) {
            return true;
        }
        foreach ($allMenuPaths as $candidate) {
            $norm = '/'.ltrim(rtrim($candidate, '/'), '/');
            if ($norm === $target) {
                continue;
            }
            if ($currentPath === $norm || (str_starts_with($currentPath, $norm.'/') && strlen($norm) > strlen($target))) {
                return false;
            }
        }
        return true;
    }

    return false;
};

$initialOpenSubmenus = [];
$initialOpenChildSubmenus = [];

foreach ($menuGroups as $gIdx => $g) {
    foreach ($g['items'] as $iIdx => $item) {
        if (!isset($item['subItems'])) {
            continue;
        }
        $subKey = "{$gIdx}-{$iIdx}";
        foreach ($item['subItems'] as $sIdx => $sItem) {
            if (isset($sItem['subItems'])) {
                $childKey = "{$gIdx}-{$iIdx}-{$sIdx}";
                foreach ($sItem['subItems'] as $cSub) {
                    if ($isMenuPathActive($cSub['path'] ?? null)) {
                        $initialOpenSubmenus[$subKey] = true;
                        $initialOpenChildSubmenus[$childKey] = true;
                    }
                }
            } elseif ($isMenuPathActive($sItem['path'] ?? null)) {
                $initialOpenSubmenus[$subKey] = true;
            }
        }
    }
}
@endphp

<div
  class="erp-sidebar-slot contents lg:block lg:sticky lg:top-5 lg:z-40 lg:h-[calc(100vh-2.5rem)] lg:max-h-[calc(100vh-2.5rem)] lg:shrink-0 lg:self-start lg:transition-[width] lg:duration-300 lg:ease-[cubic-bezier(0.22,1,0.36,1)]"
  :class="$store.sidebar.isExpanded ? 'lg:w-64' : 'lg:w-20'">

  {{-- Mobile backdrop --}}
  <div x-show="typeof sidebar !== 'undefined' ? sidebar : $store.sidebar.isMobileOpen"
    x-cloak
    x-transition.opacity
    @click="typeof sidebar !== 'undefined' ? (sidebar = false) : $store.sidebar.setMobileOpen(false)"
    class="fixed inset-0 z-30 bg-overlay lg:hidden"></div>

  <aside
    id="erp-sidebar-aside"
    @mouseenter="$store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)"
    class="{{ $isDashboard ? 'enter mv-left ' : '' }}erp-sidebar-panel fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full flex-col rounded-r-3xl bg-surface p-5 shadow-card transition-[transform,width,padding,box-shadow,border-color] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] lg:absolute lg:inset-y-0 lg:left-0 rtl:lg:left-auto rtl:lg:right-0 lg:h-full lg:translate-x-0 lg:rounded-3xl overflow-hidden"
    @if($isDashboard) style="--i:1" @endif
    :class="[
      (typeof sidebar !== 'undefined' ? sidebar : $store.sidebar.isMobileOpen) ? 'translate-x-0' : '',
      ($store.sidebar.isExpanded || $store.sidebar.isHovered) ? 'lg:w-64 lg:p-5' : 'lg:w-20 lg:px-3 lg:py-5',
      (!$store.sidebar.isExpanded && $store.sidebar.isHovered) ? 'lg:shadow-2xl lg:ring-1 lg:ring-line/80' : ''
    ]"
    x-data="{
      openSubmenus: @js($initialOpenSubmenus),
      openChildSubmenus: @js($initialOpenChildSubmenus),
      serverOpenSubmenus: @js($initialOpenSubmenus),
      serverOpenChildSubmenus: @js($initialOpenChildSubmenus),
      allPaths: @js($allMenuPaths),
      isRailVisible() {
        return $store.sidebar.isExpanded
          || $store.sidebar.isHovered
          || $store.sidebar.isMobileOpen
          || (typeof sidebar !== 'undefined' && sidebar);
      },
      persistState() {
        try {
          var nav = this.$refs.sidebarNav;
          sessionStorage.setItem('cmc2_sidebar_state', JSON.stringify({
            open: this.openSubmenus,
            child: this.openChildSubmenus,
            scroll: nav ? nav.scrollTop : 0
          }));
        } catch (e) {}
      },
      init() {
        try {
          var raw = sessionStorage.getItem('cmc2_sidebar_state');
          if (raw) {
            var parsed = JSON.parse(raw);
            if (parsed && parsed.open && typeof parsed.open === 'object') {
              this.openSubmenus = Object.assign({}, parsed.open, this.serverOpenSubmenus);
            }
            if (parsed && parsed.child && typeof parsed.child === 'object') {
              this.openChildSubmenus = Object.assign({}, parsed.child, this.serverOpenChildSubmenus);
            }
          }
        } catch (e) {}
      },
      toggleSub(g, i) {
        const k = g + '-' + i;
        this.openSubmenus[k] = !this.openSubmenus[k];
        this.persistState();
      },
      isSubOpen(g, i) {
        return !!this.openSubmenus[g + '-' + i];
      },
      toggleChildSub(g, i, s) {
        const k = g + '-' + i + '-' + s;
        this.openChildSubmenus[k] = !this.openChildSubmenus[k];
        this.persistState();
      },
      isChildSubOpen(g, i, s) {
        return !!this.openChildSubmenus[g + '-' + i + '-' + s];
      },
      isActive(path) {
        if (!path) return false;
        const current = window.location.pathname.replace(/\/+$/, '') || '/';
        const target = path.replace(/\/+$/, '') || '/';
        if (current === target) return true;

        if (current.startsWith(target + '/')) {
          const hasBetterMatch = this.allPaths.some(p => {
            const normalized = p.replace(/\/+$/, '') || '/';
            if (normalized === target) return false;
            if (current === normalized) return true;
            if (current.startsWith(normalized + '/') && normalized.length > target.length) return true;
            return false;
          });
          return !hasBetterMatch;
        }

        return false;
      }
    }">

  {{-- Brand Logo --}}
  <div class="shrink-0 min-h-[2.75rem]">
    <a href="{{ route($isSupplierPortal ? 'suppliers.my-profile' : 'dashboard') }}"
      class="erp-sidebar-brand-link flex items-center min-w-0 pt-1 gap-3 px-2"
      :class="isRailVisible() ? 'gap-3 px-2' : 'lg:justify-center px-0'">
      <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name') }}"
        width="36" height="36"
        class="h-9 w-9 shrink-0 rounded-xl object-contain bg-surface p-0.5 shadow-sm ring-1 ring-line/50">
      <div class="erp-sidebar-label min-w-0">
        <span class="block text-base font-extrabold tracking-tight text-ink truncate whitespace-nowrap">Cox's Bazar MC2</span>
        <span class="block text-[11px] font-medium text-muted truncate whitespace-nowrap">Distribution ERP</span>
      </div>
    </a>
  </div>

  {{-- Navigation with Existing Menu Groups --}}
  <nav x-ref="sidebarNav" id="erp-sidebar-nav" class="mt-6 flex-1 space-y-6 overflow-y-auto overflow-x-hidden no-scrollbar pr-0.5 min-h-0">
    @php $navItemCount = 0; @endphp
    @foreach ($menuGroups as $groupIndex => $menuGroup)
    <div>
      <div class="erp-sidebar-group-head min-h-4 flex items-center px-3" :class="isRailVisible() ? 'px-3' : 'lg:justify-center lg:px-0'">
        <p class="erp-sidebar-label text-xs font-semibold uppercase tracking-wider text-ink/70 whitespace-nowrap">{{ __($menuGroup['title']) }}</p>
        <span aria-hidden="true" class="erp-sidebar-group-divider hidden h-px w-6 rounded-full bg-line/80"></span>
      </div>

      <ul class="mt-2.5 space-y-1">
          @foreach ($menuGroup['items'] as $itemIndex => $item)
          @php
          if (!empty($item['can']) && !auth()->user()?->can($item['can'])) {
          continue;
          }
          if (!empty($item['canany']) && !auth()->user()?->hasRole('Super Admin')) {
          $hasAny = false;
          foreach ((array) $item['canany'] as $perm) {
          if (auth()->user()?->can($perm)) { $hasAny = true; break; }
          }
          if (!$hasAny) continue;
          }
          $subKey = $groupIndex . '-' . $itemIndex;
          $isParentOpen = !empty($initialOpenSubmenus[$subKey]);
          $isSingleActive = empty($item['subItems']) && $isMenuPathActive($item['path'] ?? null);
          @endphp

          <li @if($isDashboard) class="enter mv-left" style="--i:{{ min($navItemCount++, 10) }};--d:250ms;--stagger:45ms" @endif>
            @if (isset($item['subItems']))
            {{-- Menu Item with Submenu --}}
            <button type="button"
              @click="toggleSub({{ $groupIndex }}, {{ $itemIndex }})"
              :title="!isRailVisible() ? {{ \Illuminate\Support\Js::from(__($item['name'])) }} : ''"
              :class="[
                isSubOpen({{ $groupIndex }}, {{ $itemIndex }}) ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink',
                isRailVisible() ? 'w-full justify-between gap-3 rounded-full px-3 py-2.5' : 'w-full lg:size-11 lg:mx-auto lg:justify-center lg:px-0 lg:rounded-2xl'
              ]"
              class="erp-sidebar-item flex w-full items-center justify-between gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition-colors duration-150 cursor-pointer {{ $isParentOpen ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink' }}">
              <span class="flex items-center min-w-0 gap-3 truncate" :class="isRailVisible() ? 'gap-3 truncate' : 'lg:justify-center'">
                <span class="size-5 shrink-0 flex items-center justify-center">
                  {!! MenuHelper::getIconSvg($item['icon']) !!}
                </span>
                <span class="erp-sidebar-label truncate whitespace-nowrap">{{ __($item['name']) }}</span>
              </span>
              <x-icon name="chevron" class="erp-sidebar-label size-3.5 shrink-0 transition-transform duration-150 {{ $isParentOpen ? 'rotate-180 text-brand-500' : '' }}"
                ::class="isSubOpen({{ $groupIndex }}, {{ $itemIndex }}) && 'rotate-180 text-brand-500'" />
            </button>

            {{-- Submenu level 1 --}}
            <div data-submenu-key="{{ $subKey }}"
              class="erp-sidebar-submenu"
              x-show="isRailVisible() && isSubOpen({{ $groupIndex }}, {{ $itemIndex }})"
              @if (!$isParentOpen) style="display: none;" @endif>
              <ul class="mt-1 space-y-1 pl-7">
                @foreach ($item['subItems'] as $subIndex => $subItem)
                @php
                if (!empty($subItem['can']) && !auth()->user()?->can($subItem['can'])) continue;
                if (!empty($subItem['canany']) && !auth()->user()?->hasRole('Super Admin')) {
                $hasAnySub = false;
                foreach ((array) $subItem['canany'] as $perm) {
                if (auth()->user()?->can($perm)) { $hasAnySub = true; break; }
                }
                if (!$hasAnySub) continue;
                }
                $childKey = $groupIndex . '-' . $itemIndex . '-' . $subIndex;
                $isChildOpen = !empty($initialOpenChildSubmenus[$childKey]);
                $isSubActive = empty($subItem['subItems']) && $isMenuPathActive($subItem['path'] ?? null);
                @endphp

                @if (isset($subItem['subItems']))
                {{-- Nested Submenu --}}
                <li>
                  <button type="button"
                    @click="toggleChildSub({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }})"
                    class="flex w-full items-center justify-between gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition cursor-pointer {{ $isChildOpen ? 'text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink' }}"
                    :class="isChildSubOpen({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }}) ? 'text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
                    <span class="truncate">{{ __($subItem['name']) }}</span>
                    <x-icon name="chevron" class="size-3 shrink-0 transition-transform {{ $isChildOpen ? 'rotate-180 text-brand-500' : '' }}"
                      ::class="isChildSubOpen({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }}) && 'rotate-180 text-brand-500'" />
                  </button>
                  <div data-child-submenu-key="{{ $childKey }}"
                    x-show="isChildSubOpen({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }})"
                    @if (!$isChildOpen) style="display: none;" @endif>
                    <ul class="mt-1 space-y-1 pl-3">
                      @foreach ($subItem['subItems'] as $childSub)
                      @php
                      if (!empty($childSub['can']) && !auth()->user()?->can($childSub['can'])) continue;
                      $isChildSubActive = $isMenuPathActive($childSub['path'] ?? null);
                      @endphp
                      <li>
                        <a href="{{ $childSub['path'] }}"
                          class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition {{ $isChildSubActive ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink' }}"
                          :class="isActive('{{ $childSub['path'] }}') ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
                          <span>{{ __($childSub['name']) }}</span>
                        </a>
                      </li>
                      @endforeach
                    </ul>
                  </div>
                </li>
                @else
                {{-- Standard Submenu Item --}}
                <li>
                  <a href="{{ $subItem['path'] }}"
                    class="flex items-center justify-between gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition {{ $isSubActive ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink' }}"
                    :class="isActive('{{ $subItem['path'] }}') ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
                    <span class="truncate">{{ __($subItem['name']) }}</span>
                    @if (!empty($subItem['badge']))
                    <span class="rounded-full bg-danger/15 px-1.5 py-0.5 text-[10px] font-bold text-danger tabular-nums leading-none">{{ $subItem['badge'] }}</span>
                    @elseif (!empty($subItem['new']))
                    <span class="rounded-full bg-brand-500/10 px-1.5 py-0.2 text-[9px] font-bold text-brand-500 uppercase">New</span>
                    @endif
                  </a>
                </li>
                @endif
                @endforeach
              </ul>
            </div>
            @else
            {{-- Single Nav Item --}}
            <a href="{{ $item['path'] }}"
              :title="!isRailVisible() ? {{ \Illuminate\Support\Js::from(__($item['name'])) }} : ''"
              :class="[
                isActive('{{ $item['path'] }}') ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink',
                isRailVisible() ? 'w-full justify-between gap-3 rounded-full px-3 py-2.5' : 'w-full lg:size-11 lg:mx-auto lg:justify-center lg:px-0 lg:rounded-2xl'
              ]"
              class="erp-sidebar-item relative flex w-full items-center justify-between gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition-colors duration-150 {{ $isSingleActive ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink' }}">
              <span class="flex items-center min-w-0 gap-3 truncate" :class="isRailVisible() ? 'gap-3 truncate' : 'lg:justify-center'">
                <span class="size-5 shrink-0 flex items-center justify-center">
                  {!! MenuHelper::getIconSvg($item['icon']) !!}
                </span>
                <span class="erp-sidebar-label truncate whitespace-nowrap">{{ __($item['name']) }}</span>
              </span>
              @if (!empty($item['badge']))
              <span class="erp-sidebar-label rounded-full bg-danger/15 px-1.5 py-0.5 text-[10px] font-bold text-danger tabular-nums leading-none">{{ $item['badge'] }}</span>
              <span aria-hidden="true" class="erp-sidebar-collapsed-dot hidden absolute top-2 right-2 size-2 rounded-full bg-danger ring-2 ring-surface"></span>
              @elseif (!empty($item['new']))
              <span class="erp-sidebar-label rounded-full bg-brand-500/10 px-2 py-0.5 text-[10px] font-bold text-brand-500 uppercase">New</span>
              @endif
            </a>
            @endif
          </li>
          @endforeach
          @if ($menuGroup['title'] === 'Menu' && ! $isSupplierPortal)
          @hasSection('sidebar_sections')
          @yield('sidebar_sections')
          @endif
          @endif
        </ul>
    </div>
    @endforeach
  </nav>

  {{-- Bottom Utilities --}}
  <div class="mt-auto shrink-0 space-y-1 border-t border-line pt-4">
    @php
    $sidebarSettingsUrl = Route::has('settings.index') ? route('settings.index') : (Route::has('admin.settings.index') ? route('admin.settings.index') : null);
    $canManageSettings = auth()->user()?->can('settings.manage') ?? false;
    @endphp
    @if ($sidebarSettingsUrl && $canManageSettings)
    <a href="{{ $sidebarSettingsUrl }}"
      :title="!isRailVisible() ? 'Settings' : ''"
      :class="isRailVisible() ? 'w-full gap-3 rounded-full px-3 py-2' : 'w-full lg:size-10 lg:mx-auto lg:justify-center lg:px-0 lg:rounded-2xl'"
      class="erp-sidebar-util flex w-full items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-muted transition-colors duration-150 hover:bg-soft/60 hover:text-ink">
      <x-icon name="settings" class="size-4.5 shrink-0" />
      <span class="erp-sidebar-label truncate whitespace-nowrap">Settings</span>
    </a>
    <a href="{{ $sidebarSettingsUrl }}?tab=theme"
      :title="!isRailVisible() ? 'Themes' : ''"
      :class="isRailVisible() ? 'w-full gap-3 rounded-full px-3 py-2' : 'w-full lg:size-10 lg:mx-auto lg:justify-center lg:px-0 lg:rounded-2xl'"
      class="erp-sidebar-util flex w-full items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-muted transition-colors duration-150 hover:bg-soft/60 hover:text-ink">
      <x-icon name="palette" class="size-4.5 shrink-0 text-brand-500" />
      <span class="erp-sidebar-label truncate whitespace-nowrap">Themes</span>
    </a>
    @endif

    <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
      @csrf
      <button type="submit"
        :title="!isRailVisible() ? 'Log Out' : ''"
        :class="isRailVisible() ? 'w-full gap-3 rounded-full px-3 py-2' : 'w-full lg:size-10 lg:mx-auto lg:justify-center lg:px-0 lg:rounded-2xl'"
        class="erp-sidebar-util flex w-full items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-muted transition-colors duration-150 hover:bg-danger/10 hover:text-danger cursor-pointer">
        <x-icon name="logout" class="size-4.5 shrink-0" />
        <span class="erp-sidebar-label truncate whitespace-nowrap">Log Out</span>
      </button>
    </form>
  </div>
  </aside>

  {{-- Restore saved open groups & sidebar scroll synchronously before first paint --}}
  <script>
    (function () {
      try {
        var raw = sessionStorage.getItem('cmc2_sidebar_state');
        if (raw) {
          var state = JSON.parse(raw);
          if (state && state.open && typeof state.open === 'object') {
            document.querySelectorAll('#erp-sidebar-aside [data-submenu-key]').forEach(function (el) {
              var k = el.getAttribute('data-submenu-key');
              if (el.style.display === 'none' && state.open[k] === true) {
                el.style.display = '';
              }
            });
          }
          if (state && state.child && typeof state.child === 'object') {
            document.querySelectorAll('#erp-sidebar-aside [data-child-submenu-key]').forEach(function (el) {
              var k = el.getAttribute('data-child-submenu-key');
              if (el.style.display === 'none' && state.child[k] === true) {
                el.style.display = '';
              }
            });
          }
          var nav = document.getElementById('erp-sidebar-nav');
          if (nav && typeof state.scroll === 'number' && state.scroll > 0) {
            nav.scrollTop = state.scroll;
          }
        }
        var navEl = document.getElementById('erp-sidebar-nav');
        if (navEl) {
          var saveScroll = function () {
            try {
              var cur = {};
              try { cur = JSON.parse(sessionStorage.getItem('cmc2_sidebar_state') || '{}') || {}; } catch (e) {}
              cur.scroll = navEl.scrollTop;
              sessionStorage.setItem('cmc2_sidebar_state', JSON.stringify(cur));
            } catch (e) {}
          };
          navEl.addEventListener('scroll', saveScroll, { passive: true });
          window.addEventListener('pagehide', saveScroll);
        }
      } catch (e) {}
    })();
  </script>
</div>