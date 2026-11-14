@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();

    // Get current path
    $currentPath = request()->path();

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
                }
            }
        }
    }
@endphp

<script>
function sidebarComponent() {
    return {
        openSubmenus: {},
        allPaths: @json($allMenuPaths),
        init() {
            this.initializeActiveMenus();
        },
        initializeActiveMenus() {
            const currentPath = '{{ $currentPath }}';

            @foreach ($menuGroups as $groupIndex => $menuGroup)
                @foreach ($menuGroup['items'] as $itemIndex => $item)
                    @if (isset($item['subItems']))
                        @foreach ($item['subItems'] as $subItem)
                            if (currentPath === '{{ ltrim($subItem['path'], '/') }}' ||
                                window.location.pathname === '{{ $subItem['path'] }}' ||
                                (window.location.pathname.startsWith('{{ $subItem['path'] }}/'))) {
                                this.openSubmenus['{{ $groupIndex }}-{{ $itemIndex }}'] = true;
                            }
                        @endforeach
                    @endif
                @endforeach
            @endforeach
        },
        toggleSubmenu(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            if (newState) {
                this.openSubmenus = {};
            }

            this.openSubmenus[key] = newState;
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            return this.openSubmenus[key] || false;
        },
        isActive(path) {
            if (!path) return false;
            const current = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
            const target = path.replace(/\/+$/, '') || '/';

            // Exact match has highest priority
            if (current === target) {
                return true;
            }

            // Treat supplier edit (/suppliers/*/edit) as the Add/Update Suppliers ("New Supplier") section
            if (/\/suppliers\/\d+\/edit$/.test(current)) {
                if (target === '/suppliers/create') return true;
                if (target === '/suppliers') return false;
            }

            // Treat customer edit (/customers/*/edit) as the Add/Update Customers ("New Customer") section
            if (/\/customers\/\d+\/edit$/.test(current)) {
                if (target === '/customers/create') return true;
                if (target === '/customers') return false;
            }

            // If current is a deeper path under target (e.g. /suppliers/5/edit under /suppliers)
            if (current.startsWith(target + '/')) {
                const hasBetterMatch = this.allPaths.some(p => {
                    const other = p.replace(/\/+$/, '') || '/';
                    return other !== target && (current === other || (current.startsWith(other + '/') && other.length > target.length));
                });
                return !hasBetterMatch;
            }

            return false;
        }
    };
}
</script>

<aside id="sidebar"
    class="fixed flex flex-col mt-0 top-0 px-5 start-0 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out max-xl:duration-200 max-xl:w-[290px] z-50 max-xl:z-101 ltr:border-r rtl:border-l border-gray-200 w-[90px] [.sidebar-expanded_&]:min-w-[290px]"
    x-data="sidebarComponent()"
    :class="{
        'translate-x-0': $store.sidebar.isMobileOpen,
        'max-xl:-translate-x-full max-xl:rtl:translate-x-full': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">

    <!-- Logo Section -->
    <div class="py-6 flex items-center gap-2 border-b border-gray-100 dark:border-gray-800" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : 'justify-between'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name') }}" class="h-10 w-10 shrink-0 rounded-xl object-contain bg-white p-0.5 shadow-theme-xs">
            <div class="hidden [.sidebar-expanded_&]:block">
                <span class="block text-base font-bold text-gray-900 dark:text-white leading-tight">
                    {{ config('app.name', 'Cox\'s Bazaar') }}
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Distribution ERP</span>
            </div>
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar pt-4">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        <!-- Menu Group Title -->
                        <h2 class="mb-3 text-xs uppercase flex font-semibold tracking-wider text-gray-400 dark:text-gray-500"
                            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                            'lg:justify-center' : 'justify-start'">
                            <template
                                x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                                <span>{{ __($menuGroup['title']) }}</span>
                            </template>
                            <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z" fill="currentColor"/>
                                </svg>
                            </template>
                        </h2>

                        <!-- Menu Items -->
                        <ul class="flex flex-col gap-1">
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
                                @endphp
                                <li>
                                    @if (isset($item['subItems']))
                                        <!-- Menu Item with Submenu -->
                                        <button @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                            class="menu-item group w-full"
                                            :class="[
                                                isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                'menu-item-active' : 'menu-item-inactive',
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                                                'xl:justify-center' : 'xl:justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                    'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex items-center gap-2">
                                                {{ __($item['name']) }}
                                                @if (!empty($item['new']))
                                                    <span class="absolute ltr:right-10 rtl:left-10"
                                                        :class="isActive('{{ $item['path'] ?? '' }}') ?
                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                        {{ __('new') }}
                                                    </span>
                                                @endif
                                            </span>

                                            <!-- Chevron Down Icon -->
                                            <x-svg.chevron-down x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="ms-auto h-5 w-5 transition-transform duration-200"
                                                x-bind:class="{ 'rotate-180 text-brand-500': isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) }" />
                                        </button>

                                        <!-- Submenu -->
                                        <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)" x-collapse>
                                            <ul class="mt-2 space-y-1 ltr:ml-9 rtl:mr-9">
                                                @foreach ($item['subItems'] as $subItem)
                                                    @php
                                                        if (!empty($subItem['can']) && !auth()->user()?->can($subItem['can'])) {
                                                            continue;
                                                        }
                                                    @endphp
                                                    <li>
                                                        <a href="{{ $subItem['path'] }}" class="menu-dropdown-item"
                                                            :class="isActive('{{ $subItem['path'] }}') ?
                                                                'menu-dropdown-item-active' :
                                                                'menu-dropdown-item-inactive'">
                                                            {{ __($subItem['name']) }}
                                                            <span class="flex items-center gap-1 ltr:ml-auto rtl:mr-auto">
                                                                @if (!empty($subItem['new']))
                                                                    <span
                                                                        :class="isActive('{{ $subItem['path'] }}') ?
                                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                                        {{ __('new') }}
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <!-- Simple Menu Item -->
                                        <a href="{{ $item['path'] }}" class="menu-item group"
                                            :class="[
                                                isActive('{{ $item['path'] }}') ? 'menu-item-active' :
                                                'menu-item-inactive',
                                                (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                                                'xl:justify-center' :
                                                'justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span
                                                :class="isActive('{{ $item['path'] }}') ? 'menu-item-icon-active' :
                                                    'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex items-center gap-2">
                                                {{ __($item['name']) }}
                                                @if (!empty($item['new']))
                                                    <span
                                                        class="ltr:ml-2 rtl:mr-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-500 text-white">
                                                        {{ __('new') }}
                                                    </span>
                                                @endif
                                            </span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <!-- Sidebar Widget -->
        <div x-data x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-transition class="mt-auto">
            @include('layouts.sidebar-widget')
        </div>

    </div>
</aside>
