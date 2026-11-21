@php
    $user = auth()->user();
    $notifications = $user === null
        ? collect()
        : \App\Services\NotificationService::scopeFor($user->notifications(), $user)->latest()->limit(12)->get();
    $unreadCount = $user === null ? 0 : \App\Services\NotificationService::scopeFor($user->unreadNotifications(), $user)->count();

    /**
     * Visual style per notification type (GenericDatabaseNotification):
     * warning = awaiting approval, success = approved, error = rejected,
     * info = general.
     */
    $typeStyles = [
        'warning' => [
            'dot' => 'bg-warning',
            'chip' => 'bg-warning/10 text-warning ring-warning/25',
            'pill' => 'bg-warning/10 text-warning',
            'label' => 'Approval',
        ],
        'success' => [
            'dot' => 'bg-success',
            'chip' => 'bg-success/10 text-success ring-success/25',
            'pill' => 'bg-success/10 text-success',
            'label' => 'Approved',
        ],
        'error' => [
            'dot' => 'bg-danger',
            'chip' => 'bg-danger/10 text-danger ring-danger/25',
            'pill' => 'bg-danger/10 text-danger',
            'label' => 'Rejected',
        ],
        'info' => [
            'dot' => 'bg-brand-500',
            'chip' => 'bg-brand-500/10 text-brand-500 ring-brand-500/25',
            'pill' => 'bg-brand-500/10 text-brand-500',
            'label' => 'Update',
        ],
    ];

    $initialNotificationMeta = $notifications->mapWithKeys(fn ($n) => [
        (string) $n->id => [
            'read' => (bool) $n->read_at,
            'type' => (string) ($n->data['type'] ?? 'info'),
        ],
    ])->all();
@endphp

<div class="relative"
     x-data="{
        notifyOpen: false,
        activeTab: 'all',
        unreadCount: {{ (int) $unreadCount }},
        meta: @js($initialNotificationMeta),
        csrf: @js(csrf_token()),

        isRead(id) {
            return !!this.meta[id]?.read;
        },

        isVisible(id) {
            const item = this.meta[id];
            if (!item) return false;
            if (this.activeTab === 'unread') return !item.read;
            if (this.activeTab === 'approvals') return item.type === 'warning';
            return true;
        },

        visibleCount() {
            return Object.keys(this.meta).filter((id) => this.isVisible(id)).length;
        },

        approvalsCount() {
            return Object.values(this.meta).filter((item) => item.type === 'warning' && !item.read).length;
        },

        markRead(id, readUrl, navigateUrl = null) {
            const wasUnread = this.meta[id] && !this.meta[id].read;
            if (wasUnread) {
                this.meta[id].read = true;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                fetch(readUrl, {
                    method: 'PATCH',
                    keepalive: true,
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'Accept': 'application/json',
                    },
                }).catch(() => {});
            }
            if (navigateUrl) {
                window.location.href = navigateUrl;
            }
        }
     }"
     @click.outside="notifyOpen = false"
     @keydown.escape.window="notifyOpen = false">

    {{-- Topbar Bell Trigger --}}
    <button type="button"
            @click="notifyOpen = !notifyOpen"
            class="relative grid size-11 place-items-center rounded-full bg-surface text-muted shadow-card transition-[transform,background-color,color,box-shadow] duration-200 hover:text-ink active:scale-[0.97] cursor-pointer"
            :class="notifyOpen ? 'ring-2 ring-brand-500/25 text-brand-500' : ''"
            aria-label="Notifications"
            :aria-expanded="notifyOpen ? 'true' : 'false'">
        <x-icon name="bell" class="size-5" />
        <template x-if="unreadCount > 0">
            <span class="absolute -right-0.5 -top-0.5 flex h-[19px] min-w-[19px] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold leading-none text-white ring-2 ring-surface tabular-nums shadow-xs">
                <span x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
            </span>
        </template>
    </button>

    {{-- Polished Notification Panel / Modal --}}
    <div x-show="notifyOpen" x-cloak
         x-transition:enter="transition duration-200 ease-[cubic-bezier(.16,1,.3,1)]"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition duration-150 ease-in"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         role="dialog"
         aria-label="Notifications panel"
         class="fixed right-3 top-[4.75rem] z-50 w-[calc(100vw-1.5rem)] max-w-[420px] origin-top-right overflow-hidden rounded-3xl border border-line bg-surface shadow-2xl sm:absolute sm:right-0 sm:top-auto sm:mt-2.5 sm:w-[410px]">

        {{-- Header --}}
        <div class="border-b border-line bg-canvas/45 px-5 pt-4 pb-3.5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="bell" class="size-4.5" />
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold tracking-tight text-ink">Notifications</h3>
                            <span x-show="unreadCount > 0"
                                  class="inline-flex items-center rounded-full bg-brand-500/10 px-2 py-0.5 text-[10px] font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums"
                                  x-text="unreadCount + ' new'"></span>
                            <span x-show="unreadCount === 0" x-cloak
                                  class="inline-flex items-center rounded-full bg-success/10 px-2 py-0.5 text-[10px] font-semibold text-success">
                                All read
                            </span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-muted truncate">Approvals, transfers &amp; stock activity</p>
                    </div>
                </div>

                <button type="button"
                        @click="notifyOpen = false"
                        class="grid size-8 shrink-0 place-items-center rounded-xl text-muted transition-colors hover:bg-soft hover:text-ink cursor-pointer"
                        aria-label="Close notifications">
                    <x-svg.x class="size-4" />
                </button>
            </div>

            {{-- Instant Filter Tabs --}}
            @if($notifications->isNotEmpty())
                <div class="mt-3.5 grid grid-cols-3 gap-1 rounded-2xl border border-line bg-surface p-1 text-xs">
                    <button type="button"
                            @click="activeTab = 'all'"
                            class="flex items-center justify-center gap-1.5 rounded-xl px-2.5 py-1.5 font-semibold transition-all cursor-pointer"
                            :class="activeTab === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink hover:bg-soft/60'">
                        <span>All</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums"
                              :class="activeTab === 'all' ? 'bg-white/20 text-on-brand' : 'bg-soft text-muted'">
                            {{ $notifications->count() }}
                        </span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'unread'"
                            class="flex items-center justify-center gap-1.5 rounded-xl px-2.5 py-1.5 font-semibold transition-all cursor-pointer"
                            :class="activeTab === 'unread' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink hover:bg-soft/60'">
                        <span>Unread</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums"
                              :class="activeTab === 'unread' ? 'bg-white/20 text-on-brand' : 'bg-soft text-brand-500'"
                              x-text="unreadCount"></span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'approvals'"
                            class="flex items-center justify-center gap-1.5 rounded-xl px-2.5 py-1.5 font-semibold transition-all cursor-pointer"
                            :class="activeTab === 'approvals' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink hover:bg-soft/60'">
                        <span>Approvals</span>
                        <span x-show="approvalsCount() > 0"
                              class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums"
                              :class="activeTab === 'approvals' ? 'bg-white/20 text-on-brand' : 'bg-warning/15 text-warning'"
                              x-text="approvalsCount()"></span>
                    </button>
                </div>
            @endif
        </div>

        {{-- Notification Feed --}}
        <div class="max-h-[380px] overflow-y-auto custom-scrollbar divide-y divide-line/70">
            @forelse($notifications as $notification)
                @php
                    $nid = (string) $notification->id;
                    $type = (string) ($notification->data['type'] ?? 'info');
                    $style = $typeStyles[$type] ?? $typeStyles['info'];
                    $url = $notification->data['url'] ?? route('notifications.index');
                    $readUrl = route('notifications.read', $notification->id);
                @endphp
                <div x-show="isVisible(@js($nid))"
                     class="group relative flex items-start gap-3 px-4 py-3.5 transition-colors hover:bg-soft/50"
                     :class="isRead(@js($nid)) ? 'bg-surface' : 'bg-brand-500/[0.04]'">

                    {{-- Unread left accent bar --}}
                    <span x-show="!isRead(@js($nid))"
                          class="absolute inset-y-3 left-0 w-1 rounded-r-full bg-brand-500"></span>

                    {{-- Type Icon Badge --}}
                    <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-2xl ring-1 {{ $style['chip'] }}">
                        @if($type === 'warning')
                            <x-icon name="alert-triangle" class="size-4.5" />
                        @elseif($type === 'success')
                            <x-icon name="check" class="size-4.5" />
                        @elseif($type === 'error')
                            <x-svg.x class="size-4.5" />
                        @else
                            <x-icon name="bell" class="size-4.5" />
                        @endif
                    </span>

                    {{-- Notification Content --}}
                    <div class="min-w-0 flex-1">
                        <a href="{{ $url }}"
                           @click.prevent="markRead(@js($nid), @js($readUrl), @js($url))"
                           class="block focus:outline-hidden">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[13px] leading-snug transition-colors group-hover:text-brand-500"
                                      :class="isRead(@js($nid)) ? 'font-medium text-muted' : 'font-bold text-ink'">
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                </span>
                                <span x-show="!isRead(@js($nid))"
                                      class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500 ring-2 ring-brand-500/20"></span>
                            </div>

                            @if(! empty($notification->data['body']))
                                <p class="mt-1 text-xs leading-relaxed text-muted line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit($notification->data['body'], 115) }}
                                </p>
                            @endif
                        </a>

                        {{-- Meta & Quick Inline Actions --}}
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 text-[11px] text-muted">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $style['pill'] }}">
                                    <span class="size-1.5 rounded-full {{ $style['dot'] }}"></span>
                                    {{ $style['label'] }}
                                </span>
                                <span class="text-muted/50">&middot;</span>
                                <span>{{ $notification->created_at->diffForHumans() }}</span>
                            </div>

                            <div class="flex items-center gap-1">
                                <button type="button"
                                        x-show="!isRead(@js($nid))"
                                        @click.stop="markRead(@js($nid), @js($readUrl))"
                                        class="inline-flex items-center gap-1 rounded-lg border border-line bg-surface px-2 py-0.5 text-[10px] font-semibold text-muted transition-colors hover:border-success/50 hover:bg-success/10 hover:text-success cursor-pointer"
                                        title="Mark as read">
                                    <x-icon name="check" class="size-3" />
                                    <span>Read</span>
                                </button>
                                <a href="{{ $url }}"
                                   @click.prevent="markRead(@js($nid), @js($readUrl), @js($url))"
                                   class="inline-flex items-center gap-0.5 rounded-lg px-1.5 py-0.5 text-[10px] font-semibold text-brand-500 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-brand-500/10">
                                    <span>Open</span>
                                    <x-icon name="arrow-up-right" class="size-3" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <div class="mx-auto mb-3.5 grid size-14 place-items-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="bell" class="size-7" />
                    </div>
                    <p class="text-sm font-bold text-ink">No notifications yet</p>
                    <p class="mt-1 text-xs text-muted leading-relaxed">
                        Transfer approvals, purchase updates, and stock alerts will appear here.
                    </p>
                </div>
            @endforelse

            {{-- Filtered Empty State (Unread / Approvals tabs) --}}
            @if($notifications->isNotEmpty())
                <div x-show="visibleCount() === 0" x-cloak class="px-6 py-10 text-center">
                    <div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                        <x-icon name="check" class="size-6" />
                    </div>
                    <p class="text-sm font-bold text-ink"
                       x-text="activeTab === 'approvals' ? 'No pending approvals' : 'You are all caught up!'"></p>
                    <p class="mt-1 text-xs text-muted"
                       x-text="activeTab === 'approvals' ? 'All transfer and order approvals are handled.' : 'Every recent notification has been marked as read.'"></p>
                </div>
            @endif
        </div>

        {{-- Footer Actions --}}
        <div class="flex items-center gap-2 border-t border-line bg-canvas/35 px-4 py-3">
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" class="flex-1" x-show="unreadCount > 0">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink shadow-2xs transition-colors hover:bg-soft hover:text-brand-500 cursor-pointer">
                        <x-icon name="check" class="size-3.5 text-success" />
                        <span>Mark all as read</span>
                    </button>
                </form>
            @endif

            <a href="{{ route('notifications.index') }}"
               class="btn-scale inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-3.5 py-2 text-xs font-semibold text-on-brand shadow-xs transition-colors hover:bg-brand-600">
                <span>View all notifications</span>
                <x-icon name="arrow-up-right" class="size-3.5" />
            </a>
        </div>
    </div>
</div>
