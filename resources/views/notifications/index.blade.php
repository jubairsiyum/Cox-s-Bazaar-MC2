@extends('layouts.app')

@section('title', 'Notifications — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Notifications')

@section('content')
@php
    /**
     * Visual style per notification type (GenericDatabaseNotification):
     * warning = awaiting approval, success = approved, error = rejected,
     * info = general.
     */
    $typeStyles = [
        'warning' => ['chip' => 'bg-warning/10 text-warning ring-warning/20', 'icon' => 'alert-triangle', 'label' => 'Awaiting approval'],
        'success' => ['chip' => 'bg-success/10 text-success ring-success/20', 'icon' => 'check', 'label' => 'Approved'],
        'error' => ['chip' => 'bg-danger/10 text-danger ring-danger/20', 'icon' => 'x', 'label' => 'Rejected'],
        'info' => ['chip' => 'bg-brand-500/10 text-brand-500 ring-brand-500/20', 'icon' => 'bell', 'label' => 'Info'],
    ];
@endphp

<div class="space-y-6">

    {{-- Top Breadcrumb & Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route(auth()->user()?->isSupplierPortalUser() ? 'suppliers.my-profile' : 'dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                @unless(auth()->user()?->isSupplierPortalUser())
                    <span class="text-muted/60">/</span>
                    <span class="text-muted">Administration</span>
                @endunless
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Notifications</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Notifications</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($unreadCount) }} unread
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Transfer requests, approval decisions and stock updates for your locations.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft transition-colors">
                        <x-icon name="check" class="size-4 text-success" />
                        <span>Mark all as read</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Filter pills --}}
    <div class="flex items-center gap-1.5 flex-wrap">
        <a href="{{ route('notifications.index', ['filter' => 'all', 'per_page' => $perPage]) }}"
           class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $filter === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
            <span>All</span>
        </a>
        <a href="{{ route('notifications.index', ['filter' => 'unread', 'per_page' => $perPage]) }}"
           class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer {{ $filter === 'unread' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
            <span>Unread</span>
            <span class="rounded-full px-1.5 py-0.5 text-[10px] {{ $filter === 'unread' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-ink' }}">{{ $unreadCount }}</span>
        </a>
    </div>

    {{-- Notification list --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="divide-y divide-line">
            @forelse($notifications as $notification)
                @php
                    $type = $notification->data['type'] ?? 'info';
                    $style = $typeStyles[$type] ?? $typeStyles['info'];
                    $url = $notification->data['url'] ?? null;
                @endphp
                <div class="flex items-start gap-3.5 px-5 sm:px-6 py-4 transition-colors {{ $notification->read_at ? '' : 'bg-brand-500/5' }}">
                    <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-2xl ring-1 {{ $style['chip'] }}">
                        @if($type === 'error')
                            <x-svg.x class="size-5" />
                        @else
                            <x-icon :name="$style['icon']" class="size-5" />
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-bold text-ink">
                                @if($url)
                                    <a href="{{ $url }}" class="hover:text-brand-500 transition-colors">{{ $notification->data['title'] ?? 'Notification' }}</a>
                                @else
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                @endif
                            </h3>
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 {{ $style['chip'] }}">
                                {{ $style['label'] }}
                            </span>
                            @unless($notification->read_at)
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-semibold text-on-brand">
                                    Unread
                                </span>
                            @endunless
                        </div>

                        @if(! empty($notification->data['body']))
                            <p class="mt-1 text-xs text-muted leading-relaxed">{{ $notification->data['body'] }}</p>
                        @endif

                        <div class="mt-2 flex items-center gap-3 text-[11px] text-muted">
                            <span>{{ $notification->created_at->format('d M Y, H:i') }}</span>
                            <span class="text-muted/60">·</span>
                            <span>{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($url)
                            <a href="{{ $url }}"
                               class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                <x-svg.eye class="size-3.5 text-muted" />
                                <span>Open</span>
                            </a>
                        @endif
                        @unless($notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-success hover:text-success hover:bg-soft transition-colors"
                                        title="Mark as read">
                                    <x-icon name="check" class="size-3.5" />
                                    <span>Mark read</span>
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="py-16 px-6 text-center">
                    <div class="mx-auto flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                        <x-icon name="bell" class="size-8" />
                    </div>
                    <h3 class="text-base font-bold text-ink">{{ $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' }}</h3>
                    <p class="text-xs text-muted mt-1.5 leading-relaxed max-w-sm mx-auto">
                        Transfer requests, approval decisions and stock updates for your locations will appear here.
                    </p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
