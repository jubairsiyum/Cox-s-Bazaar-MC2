@extends('layouts.app')

@section('title', 'Close Business Day — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Close Business Day')

@section('content')
<div class="space-y-6" x-data="{
    systemCash: {{ (float) $summary['system_cash'] }},
    countedCash: '{{ old('counted_cash', $summary['system_cash']) }}',
    get variance() {
        let counted = parseFloat(this.countedCash) || 0;
        return (counted - this.systemCash).toFixed(2);
    },
    get varianceLabel() {
        let v = parseFloat(this.variance);
        if (v === 0) return 'Exact (Zero Difference)';
        if (Math.abs(v) <= 100) return 'Minor Variance';
        return 'Significant Discrepancy';
    },
    get varianceClass() {
        let v = parseFloat(this.variance);
        if (v === 0) return 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20';
        if (Math.abs(v) <= 100) return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
        return 'text-rose-500 bg-rose-500/10 border-rose-500/20';
    }
}">

    {{-- Breadcrumb & Title --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <a href="{{ route('day-closings.index') }}" class="hover:text-brand-500 transition-colors">Daily Cash Close</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">New Day Close</span>
            </nav>

            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Count &amp; Lock Business Day</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">Branch: <strong class="text-ink">{{ $location->name }}</strong> · Date: <strong class="text-ink">{{ \Carbon\Carbon::parse($closingDate)->format('d M Y') }}</strong></p>
        </div>

        <a href="{{ route('day-closings.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Closings</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4">
            <p class="text-sm font-bold text-danger">Please correct errors below:</p>
            <ul class="mt-1 list-disc list-inside text-xs text-ink/90">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($existing && $existing->status === 'closed')
        <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex items-center gap-3">
            <x-icon name="lock" class="size-5 text-emerald-600 shrink-0" />
            <div>
                <p class="text-sm font-bold text-ink">Business Day Already Closed &amp; Locked</p>
                <p class="text-xs text-muted">This business day was closed with physically counted cash of ৳{{ number_format((float) $existing->counted_cash, 2) }}.</p>
            </div>
        </div>
    @endif

    {{-- System Cash Calculation KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl border border-line bg-surface shadow-2xs">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Opening Cash</p>
            <p class="text-lg sm:text-xl font-bold font-mono text-ink mt-1">৳{{ number_format((float) $summary['opening_balance'], 2) }}</p>
        </div>
        <div class="p-4 rounded-2xl border border-line bg-surface shadow-2xs">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Cash Sales (Today)</p>
            <p class="text-lg sm:text-xl font-bold font-mono text-emerald-600 mt-1">+৳{{ number_format((float) $summary['cash_sales'], 2) }}</p>
        </div>
        <div class="p-4 rounded-2xl border border-line bg-surface shadow-2xs">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">Remittances / Outflows</p>
            <p class="text-lg sm:text-xl font-bold font-mono text-rose-600 mt-1">-৳{{ number_format((float) $summary['cash_remittances'] + (float) $summary['cash_expenses'], 2) }}</p>
        </div>
        <div class="p-4 rounded-2xl border border-brand-500/30 bg-brand-500/5 shadow-2xs">
            <p class="text-xs font-semibold text-brand-600 uppercase tracking-wider">Expected System Cash</p>
            <p class="text-lg sm:text-xl font-bold font-mono text-brand-600 mt-1">৳{{ number_format((float) $summary['system_cash'], 2) }}</p>
        </div>
    </div>

    {{-- Physical Count Form --}}
    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8 shadow-card">
        <form method="POST" action="{{ route('day-closings.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <input type="hidden" name="closing_date" value="{{ $closingDate }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="counted_cash" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Physically Counted Cash in Till (৳) <span class="text-danger">*</span>
                    </label>
                    <input type="number" step="0.01" min="0" id="counted_cash" name="counted_cash"
                           x-model="countedCash" required
                           class="w-full rounded-xl border border-line bg-surface px-4 py-3 text-lg font-mono font-bold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                    <p class="text-xs text-muted mt-1.5">Count all banknotes and coins physically present in the branch cash drawer.</p>
                </div>

                {{-- Live Computed Variance Preview --}}
                <div class="flex flex-col justify-center">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">Live Cash Variance</span>
                    <div class="p-4 rounded-2xl border font-mono transition-all flex items-center justify-between"
                         :class="varianceClass">
                        <div>
                            <span class="text-2xs font-semibold uppercase tracking-wider block" x-text="varianceLabel"></span>
                            <span class="text-xl font-extrabold" x-text="(parseFloat(variance) > 0 ? '+' : '') + '৳' + variance"></span>
                        </div>
                        <span class="text-2xl" x-text="parseFloat(variance) === 0 ? '✓' : '⚠️'"></span>
                    </div>
                </div>
            </div>

            <div>
                <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Variance Explanation / Closing Notes
                </label>
                <textarea id="note" name="note" rows="3"
                          placeholder="Provide explanation if counted cash does not match system balance..."
                          class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">{{ old('note') }}</textarea>
            </div>

            <div class="pt-4 border-t border-line flex items-center justify-between">
                <p class="text-xs text-muted max-w-md">
                    @if ($existing && $existing->status === 'closed')
                        🔒 <strong>Status:</strong> This business day is already closed and locked. No further closing entries can be submitted.
                    @else
                        ⚠️ <strong>Warning:</strong> Locking the business day will freeze sales and invoice creation for {{ \Carbon\Carbon::parse($closingDate)->format('d M Y') }}.
                    @endif
                </p>

                @if ($existing && $existing->status === 'closed')
                    <div class="inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-6 py-3 text-sm font-semibold text-muted shadow-2xs cursor-not-allowed">
                        <x-icon name="lock" class="size-4 text-emerald-500" />
                        <span>Business Day Closed (Locked)</span>
                    </div>
                @else
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        <x-icon name="lock" class="size-4" />
                        <span>Confirm &amp; Lock Business Day</span>
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
