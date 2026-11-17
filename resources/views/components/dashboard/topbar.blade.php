@props(['user' => null])

@php
  $user = $user ?? auth()->user();
@endphp

<header class="flex items-center gap-3">
  {{-- Mobile sidebar button --}}
  <button @click="typeof sidebar !== 'undefined' ? (sidebar = true) : $store.sidebar.toggleMobileOpen()"
          class="grid size-11 shrink-0 place-items-center rounded-full bg-surface text-muted shadow-card transition hover:text-ink lg:hidden"
          aria-label="Menu">
    <x-icon name="menu" class="size-5" />
  </button>

  {{-- Search pill --}}
  <label class="flex h-11 w-full max-w-xs items-center gap-3 rounded-full bg-surface px-4 text-muted shadow-card transition focus-within:ring-2 focus-within:ring-brand-500/20">
    <x-icon name="search" class="size-4.5 shrink-0 text-muted" />
    <input type="search"
           placeholder="Search..."
           class="w-full bg-transparent text-sm text-ink outline-none placeholder:text-muted">
  </label>

  <div class="ml-auto flex items-center gap-2.5 sm:gap-3">
    {{-- Dark mode toggle --}}
    <button @click="$store.theme.toggle()"
            class="grid size-11 place-items-center rounded-full bg-surface text-muted shadow-card transition hover:text-ink hover:scale-105"
            aria-label="Toggle theme">
      <span x-show="!$store.theme.dark && $store.theme.resolvedTheme !== 'dark'"><x-icon name="moon" class="size-5" /></span>
      <span x-show="$store.theme.dark || $store.theme.resolvedTheme === 'dark'" x-cloak><x-icon name="sun" class="size-5 text-warning-500" /></span>
    </button>

    {{-- Notification button --}}
    <div class="relative" x-data="{ notifyOpen: false }" @click.outside="notifyOpen = false">
      <button @click="notifyOpen = !notifyOpen"
              class="relative grid size-11 place-items-center rounded-full bg-surface text-muted shadow-card transition hover:text-ink"
              aria-label="Notifications">
        <x-icon name="bell" class="size-5" />
        <span class="absolute right-3 top-3 size-2 rounded-full bg-danger animate-pulse"></span>
      </button>

      <div x-show="notifyOpen" x-cloak x-transition.origin.top.right
           class="absolute right-0 z-50 mt-2 w-72 rounded-2xl border border-line bg-surface p-3 shadow-card">
        <div class="flex items-center justify-between border-b border-line pb-2 px-1">
          <p class="text-xs font-bold uppercase tracking-wider text-ink">Notifications</p>
          <span class="rounded-full bg-soft px-2 py-0.5 text-[10px] font-semibold text-brand-500">2 New</span>
        </div>
        <div class="mt-2 space-y-1 text-xs">
          <a href="#" class="block rounded-xl p-2 transition hover:bg-soft">
            <p class="font-semibold text-ink">Delivery completed</p>
            <p class="text-muted text-[11px]">Trip #FR156KL89K delivered safely</p>
          </a>
          <a href="#" class="block rounded-xl p-2 transition hover:bg-soft">
            <p class="font-semibold text-ink">New fleet added</p>
            <p class="text-muted text-[11px]">Vehicle DH-124 registered to fleet</p>
          </a>
        </div>
      </div>
    </div>

    {{-- Profile dropdown --}}
    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
      <button @click="open = !open"
              class="flex items-center gap-2 rounded-full bg-surface p-1.5 pr-3 shadow-card transition hover:shadow-md">
        @if ($user && method_exists($user, 'avatarUrl') && $user->avatarUrl())
          <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="size-8 rounded-full object-cover">
        @else
          <div class="grid size-8 place-items-center rounded-full bg-brand-500 text-xs font-bold text-on-brand uppercase">
            {{ substr($user?->name ?? 'A', 0, 1) }}
          </div>
        @endif
        <span class="hidden sm:inline text-xs font-semibold text-ink max-w-[100px] truncate">{{ $user?->name ?? 'Admin' }}</span>
        <x-icon name="chevron" class="size-3.5 text-muted transition duration-200" ::class="open && 'rotate-180'" />
      </button>

      <div x-show="open" x-cloak x-transition.origin.top.right
           class="absolute right-0 z-50 mt-2 w-52 rounded-2xl border border-line bg-surface p-2 shadow-card">
        <div class="px-3 py-2 border-b border-line mb-1">
          <p class="text-sm font-semibold text-ink truncate">{{ $user?->name ?? 'Admin' }}</p>
          <p class="text-xs text-muted truncate">{{ $user?->email ?? 'admin@erp.local' }}</p>
        </div>

        <a href="{{ Route::has('profile.edit') ? route('profile.edit') : '#' }}"
           class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-muted transition hover:bg-soft hover:text-ink">
          <x-icon name="drivers" class="size-4" /> Profile
        </a>

        @if (Route::has('admin.settings.index'))
          <a href="{{ route('admin.settings.index') }}"
             class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-muted transition hover:bg-soft hover:text-ink">
            <x-icon name="settings" class="size-4" /> Settings
          </a>
        @endif

        <div class="border-t border-line my-1 pt-1">
          <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-danger transition hover:bg-danger/10">
              <x-icon name="logout" class="size-4" /> Log Out
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>
