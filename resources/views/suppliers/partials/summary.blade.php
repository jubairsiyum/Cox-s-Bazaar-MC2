<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Purchased</span>
                <p class="text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ \App\Helpers\Money::format($summary['purchased']) }}</p>
            </div>
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="truck" class="size-5" />
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">Grand total of {{ $summary['live_orders'] }} live {{ Str::plural('order', $summary['live_orders']) }}, cancelled excluded</p>
    </div>

    <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Paid</span>
                <p class="text-2xl font-bold tracking-tight text-success mt-1.5 tabular-nums">{{ \App\Helpers\Money::format($summary['paid']) }}</p>
            </div>
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                <x-icon name="payments" class="size-5" />
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">Applied against purchase invoices</p>
    </div>

    <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Outstanding Due</span>
                <p class="text-2xl font-bold tracking-tight text-danger mt-1.5 tabular-nums">{{ \App\Helpers\Money::format($summary['due']) }}</p>
            </div>
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                <x-icon name="wallet" class="size-5" />
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">Purchased minus paid, across all branches in view</p>
    </div>

    <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Returned</span>
                <p class="text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ \App\Helpers\Money::format($summary['returned']) }}</p>
            </div>
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                <x-icon name="warehouse" class="size-5" />
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">Value of approved purchase returns</p>
    </div>

    <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Open Orders</span>
                <p class="text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ $summary['open_orders'] }}</p>
            </div>
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="enquiries" class="size-5" />
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">Not yet delivered and not cancelled</p>
    </div>

    <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Branches With Dues</span>
                <p class="text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ $summary['branches_with_dues'] }} <span class="text-sm font-medium text-muted">/ {{ $summary['branch_count'] }}</span></p>
            </div>
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                <x-icon name="store" class="size-5" />
            </div>
        </div>
        <p class="mt-3 text-xs text-muted">
            <span class="font-semibold text-success">{{ $summary['branch_count'] - $summary['branches_with_dues'] }} cleared</span>
            · <span class="font-semibold text-warning">{{ $summary['branches_with_dues'] }} still owing</span>
        </p>
    </div>
</div>
