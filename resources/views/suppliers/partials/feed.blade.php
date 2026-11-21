<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
    <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
            <x-icon name="bell" class="size-5" />
        </div>
        <div>
            <h2 class="text-base sm:text-lg font-bold text-ink">Notifications &amp; Activity</h2>
            <p class="text-xs text-muted">New purchase orders and every payment recorded against {{ $supplier->name }}, with branch-wise dues cleared / still owing.</p>
        </div>
    </div>

    @if($feed->isEmpty())
        <x-empty-state message="No notifications yet — new purchase orders and recorded payments will appear here." />
    @else
        <div class="space-y-3">
            @foreach($feed as $notification)
                <div class="flex items-start gap-3 rounded-2xl border border-line bg-soft/40 px-4 py-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl
                        @if(($notification->data['type'] ?? 'info') === 'success') bg-success/10 text-success
                        @elseif(($notification->data['type'] ?? 'info') === 'warning') bg-warning/10 text-warning
                        @else bg-brand-500/10 text-brand-500 @endif">
                        <x-icon :name="($notification->data['type'] ?? 'info') === 'success' ? 'payments' : (($notification->data['type'] ?? 'info') === 'warning' ? 'alert-triangle' : 'truck')" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink">{{ $notification->data['title'] ?? 'Notification' }}</p>
                        <p class="text-xs text-muted mt-0.5">{{ $notification->data['body'] ?? '' }}</p>
                    </div>
                    <span class="text-[11px] text-muted whitespace-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
