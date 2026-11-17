@php
use App\Helpers\MenuHelper;
$menuGroups = MenuHelper::getMenuGroups();
$currentPath = request()->path();
$isDashboard = request()->routeIs('dashboard') || request()->is('dashboard') || $currentPath === 'dashboard' || $currentPath === '';

$allMenuPaths = [];
$isTailAdminActive = false;
foreach ($menuGroups as $group) {
$isGroupTailAdmin = ($group['title'] === 'TailAdmin Components');
foreach ($group['items'] as $item) {
if (!empty($item['path'])) {
$allMenuPaths[] = $item['path'];
if ($isGroupTailAdmin && MenuHelper::isActive($item['path'])) {
$isTailAdminActive = true;
}
}
if (!empty($item['subItems'])) {
foreach ($item['subItems'] as $sub) {
if (!empty($sub['path'])) {
$allMenuPaths[] = $sub['path'];
if ($isGroupTailAdmin && MenuHelper::isActive($sub['path'])) {
$isTailAdminActive = true;
}
}
if (!empty($sub['subItems'])) {
foreach ($sub['subItems'] as $childSub) {
if (!empty($childSub['path'])) {
$allMenuPaths[] = $childSub['path'];
if ($isGroupTailAdmin && MenuHelper::isActive($childSub['path'])) {
$isTailAdminActive = true;
}
}
}
}
}
}
}
}
@endphp

{{-- Mobile backdrop --}}
<div x-show="typeof sidebar !== 'undefined' ? sidebar : $store.sidebar.isMobileOpen"
  x-cloak
  x-transition.opacity
  @click="typeof sidebar !== 'undefined' ? (sidebar = false) : $store.sidebar.setMobileOpen(false)"
  class="fixed inset-0 z-30 bg-overlay lg:hidden"></div>

<aside
  class="{{ $isDashboard ? 'enter mv-left ' : '' }}fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full flex-col rounded-r-3xl bg-surface p-5 shadow-card transition-transform duration-200 lg:sticky lg:top-5 lg:z-30 lg:h-[calc(100vh-2.5rem)] lg:max-h-[calc(100vh-2.5rem)] lg:w-64 lg:translate-x-0 lg:rounded-3xl self-start overflow-hidden"
  @if($isDashboard) style="--i:1" @endif
  :class="{ 'translate-x-0': typeof sidebar !== 'undefined' ? sidebar : $store.sidebar.isMobileOpen }"
  x-data="{
    openSubmenus: {},
    openChildSubmenus: {},
    showTailAdmin: {{ $isTailAdminActive ? 'true' : 'false' }},
    allPaths: {{ \Illuminate\Support\Js::from(array_values(array_unique(array_filter($allMenuPaths)))) }},
    init() {
      const current = window.location.pathname.replace(/\/+$/, '') || '/';
      @if ($isTailAdminActive)
        this.showTailAdmin = true;
      @else
        const savedTailAdmin = localStorage.getItem('tailadmin_components_open');
        if (savedTailAdmin !== null) {
          this.showTailAdmin = savedTailAdmin === 'true';
        }
      @endif

      @foreach ($menuGroups as $gIdx => $g)
        @foreach ($g['items'] as $iIdx => $item)
          @if (isset($item['subItems']))
            @foreach ($item['subItems'] as $sIdx => $sItem)
              @if (isset($sItem['subItems']))
                @foreach ($sItem['subItems'] as $cSub)
                  if (current === '{{ rtrim($cSub['path'] ?? '', '/') }}' || current.startsWith('{{ rtrim($cSub['path'] ?? '', '/') }}/')) {
                    this.openSubmenus['{{ $gIdx }}-{{ $iIdx }}'] = true;
                    this.openChildSubmenus['{{ $gIdx }}-{{ $iIdx }}-{{ $sIdx }}'] = true;
                  }
                @endforeach
              @else
                if (current === '{{ rtrim($sItem['path'] ?? '', '/') }}' || current.startsWith('{{ rtrim($sItem['path'] ?? '', '/') }}/')) {
                  this.openSubmenus['{{ $gIdx }}-{{ $iIdx }}'] = true;
                }
              @endif
            @endforeach
          @endif
        @endforeach
      @endforeach
    },
    toggleTailAdmin() {
      this.showTailAdmin = !this.showTailAdmin;
      localStorage.setItem('tailadmin_components_open', this.showTailAdmin);
    },
    toggleSub(g, i) {
      const k = g + '-' + i;
      this.openSubmenus[k] = !this.openSubmenus[k];
    },
    isSubOpen(g, i) {
      return !!this.openSubmenus[g + '-' + i];
    },
    toggleChildSub(g, i, s) {
      const k = g + '-' + i + '-' + s;
      this.openChildSubmenus[k] = !this.openChildSubmenus[k];
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
  <div class="shrink-0">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2 pt-1">
      <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name') }}" class="h-9 w-9 shrink-0 rounded-xl object-contain bg-surface p-0.5 shadow-sm">
      <div class="min-w-0">
        <span class="block text-base font-extrabold tracking-tight text-ink truncate">{{ config('app.name', 'Swift Track') }}</span>
        <span class="block text-[11px] font-medium text-muted">Distribution ERP</span>
      </div>
    </a>
  </div>

  {{-- Navigation with Existing Menu Groups --}}
  <nav class="mt-6 flex-1 space-y-6 overflow-y-auto no-scrollbar pr-1 min-h-0">
    @php $navItemCount = 0; @endphp
    @foreach ($menuGroups as $groupIndex => $menuGroup)
    @php
    $isTailAdmin = ($menuGroup['title'] === 'TailAdmin Components');
    @endphp

    <div>
      @if ($isTailAdmin)
      <div @click="toggleTailAdmin()"
        class="flex items-center justify-between px-3 text-xs font-semibold uppercase tracking-wider text-ink/70 hover:text-ink cursor-pointer transition select-none">
        <span>{{ __($menuGroup['title']) }}</span>
        <x-icon name="chevron" class="size-3.5 shrink-0 transition-transform duration-200"
          ::class="showTailAdmin && 'rotate-180'" />
      </div>
      @else
      <p class="px-3 text-xs font-semibold uppercase tracking-wider text-ink/70">{{ __($menuGroup['title']) }}</p>
      @endif

      <div @if($isTailAdmin) x-show="showTailAdmin" x-cloak x-transition @endif>
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
          @endphp

          <li @if($isDashboard) class="enter mv-left" style="--i:{{ min($navItemCount++, 10) }};--d:250ms;--stagger:45ms" @endif>
            @if (isset($item['subItems']))
            {{-- Menu Item with Submenu --}}
            <button type="button"
              @click="toggleSub({{ $groupIndex }}, {{ $itemIndex }})"
              class="flex w-full items-center justify-between gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition-colors duration-200 cursor-pointer"
              :class="isSubOpen({{ $groupIndex }}, {{ $itemIndex }}) ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
              <span class="flex items-center gap-3 truncate">
                <span class="size-5 shrink-0 flex items-center justify-center">
                  {!! MenuHelper::getIconSvg($item['icon']) !!}
                </span>
                <span class="truncate">{{ __($item['name']) }}</span>
              </span>
              <x-icon name="chevron" class="size-3.5 shrink-0 transition-transform duration-200"
                ::class="isSubOpen({{ $groupIndex }}, {{ $itemIndex }}) && 'rotate-180 text-brand-500'" />
            </button>

            {{-- Submenu level 1 --}}
            <div x-show="isSubOpen({{ $groupIndex }}, {{ $itemIndex }})" x-cloak x-transition>
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
                @endphp

                @if (isset($subItem['subItems']))
                {{-- Nested Submenu --}}
                <li>
                  <button type="button"
                    @click="toggleChildSub({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }})"
                    class="flex w-full items-center justify-between gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition cursor-pointer"
                    :class="isChildSubOpen({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }}) ? 'text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
                    <span class="truncate">{{ __($subItem['name']) }}</span>
                    <x-icon name="chevron" class="size-3 shrink-0 transition-transform"
                      ::class="isChildSubOpen({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }}) && 'rotate-180 text-brand-500'" />
                  </button>
                  <div x-show="isChildSubOpen({{ $groupIndex }}, {{ $itemIndex }}, {{ $subIndex }})" x-cloak x-transition>
                    <ul class="mt-1 space-y-1 pl-3">
                      @foreach ($subItem['subItems'] as $childSub)
                      @php
                      if (!empty($childSub['can']) && !auth()->user()?->can($childSub['can'])) continue;
                      @endphp
                      <li>
                        <a href="{{ $childSub['path'] }}"
                          class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition"
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
                    class="flex items-center justify-between gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition"
                    :class="isActive('{{ $subItem['path'] }}') ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
                    <span class="truncate">{{ __($subItem['name']) }}</span>
                    @if (!empty($subItem['new']))
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
              class="flex items-center justify-between gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition-colors duration-200"
              :class="isActive('{{ $item['path'] }}') ? 'bg-soft text-brand-500 font-semibold' : 'text-muted hover:bg-soft/60 hover:text-ink'">
              <span class="flex items-center gap-3 truncate">
                <span class="size-5 shrink-0 flex items-center justify-center">
                  {!! MenuHelper::getIconSvg($item['icon']) !!}
                </span>
                <span class="truncate">{{ __($item['name']) }}</span>
              </span>
              @if (!empty($item['new']))
              <span class="rounded-full bg-brand-500/10 px-2 py-0.5 text-[10px] font-bold text-brand-500 uppercase">New</span>
              @endif
            </a>
            @endif
          </li>
          @endforeach
        </ul>
      </div>
    </div>
    @endforeach
  </nav>

  {{-- Bottom Utilities --}}
  <div class="mt-auto shrink-0 space-y-1 border-t border-line pt-4">
    <!-- @php
      $sidebarSettingsUrl = Route::has('settings.index') ? route('settings.index') : (Route::has('admin.settings.index') ? route('admin.settings.index') : null);
    @endphp
    @if ($sidebarSettingsUrl)
      <a href="{{ $sidebarSettingsUrl }}"
         class="flex items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-muted transition hover:bg-soft/60 hover:text-ink">
        <x-icon name="settings" class="size-4.5" /> Settings
      </a>
      <a href="{{ $sidebarSettingsUrl }}?tab=theme"
         class="flex items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-muted transition hover:bg-soft/60 hover:text-ink">
        <x-icon name="palette" class="size-4.5 text-brand-500" /> Themes
      </a>
    @endif -->

    <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
      @csrf
      <button type="submit"
        class="flex w-full items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-muted transition hover:bg-danger/10 hover:text-danger cursor-pointer">
        <x-icon name="logout" class="size-4.5" /> Log Out
      </button>
    </form>
  </div>
</aside>