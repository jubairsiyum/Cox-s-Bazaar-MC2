@php
    $user = auth()->user();
    $notifications = $user?->notifications()->latest()->limit(8)->get() ?? collect();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;

    /**
     * Visual style per notification type (GenericDatabaseNotification):
     * warning = awaiting approval, success = approved, error = rejected,
     * info = general.
     */
    $typeStyles = [
        'warning' => ['dot' => 'bg-warning', 'chip' => 'bg-warning/10 text-warning', 'icon' => 'alert-triangle'],
        'success' => ['dot' => 'bg-success', 'chip' => 'bg-success/10 text-success', 'icon' => 'check'],
        'error' => ['dot' => 'bg-danger', 'chip' => 'bg-danger/10 text-danger', 'icon' => 'x'],
        'info' => ['dot' => 'bg-brand-500', 'chip' => 'bg-brand-500/10 text-brand-500', 'icon' => 'bell'],
    ];
@endphp

<div class="relative" x-data="{ notifyOpen: false }" @click.outside="notifyOpen = false">
    <button @click="notifyOpen = !notifyOpen"
            class="relative grid size-11 place-items-center rounded-full bg-surface text-muted shadow-card transition-[transform,background-color] duration-200 hover:text-ink active:scale-[0.97]"
            aria-label="Notifications">
        <x-icon name="bell" class="size-5" />
        @if($unreadCount > 0)
            <span class="absolute right-3 top-3 size-2 rounded-full bg-danger animate-pulse"></span>
        @endif
    </button>

    <div x-show="notifyOpen" x-cloak
         x-transition:enter="transition duration-300 ease-[cubic-bezier(.16,1,.3,1)]"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition duration-150 ease-in"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-2xl border border-line bg-surface p-3 shadow-card">
        <div class="flex items-center justify-between border-b border-line pb-2 px-1">
            <p class="text-xs font-bold uppercase tracking-wider text-ink">Notifications</p>
            @if($unreadCount > 0)
                <span class="rounded-full bg-soft px-2 py-0.5 text-[10px] font-semibold text-brand-500">{{ $unreadCount }} new</span>
            @else
                <span class="rounded-full bg-soft px-2 py-0.5 text-[10px] font-semibold text-muted">All read</span>
            @endif
        </div>

        <div class="mt-2 max-h-96 space-y-1 overflow-y-auto custom-scrollbar text-xs">
            @forelse($notifications as $notification)
                @php
                    $type = $notification->data['type'] ?? 'info';
                    $style = $typeStyles[$type] ?? $typeStyles['info'];
                    $url = $notification->data['url'] ?? route('notifications.index');
                @endphp
                <a href="{{ $url }}"
                   class="flex items-start gap-2.5 rounded-xl p-2 transition-colors hover:bg-soft {{ $notification->read_at ? '' : 'bg-brand-500/5' }}"
                   x-on:click="fetch('{{ route('notifications.read', $notification->id) }}', { method: 'PATCH', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })">
                    <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full {{ $style['chip'] }}">
                        <x-icon :name="$style['icon']" class="size-3.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold leading-tight {{ $notification->read_at ? 'text-muted' : 'text-ink' }}">
                            {{ $notification->data['title'] ?? 'Notification' }}
                        </span>
                        @if(! empty($notification->data['body']))
                            <span class="mt-0.5 block text-[11px] leading-snug text-muted">{{ \Illuminate\Support\Str::limit($notification->data['body'], 90) }}</span>
                        @endif
                        <span class="mt-1 flex items-center gap-1.5 text-[10px] text-muted">
                            <span class="size-1.5 rounded-full {{ $style['dot'] }}"></span>
                            <span>{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </span>
                </a>
            @empty
                <div class="px-2 py-6 text-center">
                    <p class="text-xs font-semibold text-ink">No notifications yet</p>
                    <p class="mt-1 text-[11px] text-muted">Transfer and adjustment updates will appear here.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-3 flex items-center gap-2 border-t border-line pt-3">
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" class="flex-1">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-soft">
                        Mark all as read
                    </button>
                </form>
            @endif
            <a href="{{ route('notifications.index') }}"
               class="{{ $unreadCount > 0 ? '' : 'w-full text-center' }} flex-1 rounded-xl bg-brand-500 px-3 py-2 text-xs font-semibold text-on-brand transition-colors hover:bg-brand-600">
                View all
            </a>
        </div>
    </div>
</div>
