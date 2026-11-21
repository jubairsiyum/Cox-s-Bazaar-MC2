@extends('layouts.app')

@section('title', 'New Transfer — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Transfers')

@section('content')

<div class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Finance</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('account-transfers.index') }}" class="hover:text-brand-500 transition-colors">Transfers</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">New Transfer</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">New Account Transfer</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    Cash &amp; Bank Movement
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Saved as pending — once approved, money leaves the source account and lands in the destination atomically.</p>
        </div>

        <a href="{{ route('account-transfers.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Transfers</span>
        </a>
    </div>

    {{-- Validation Summary --}}
    @if ($errors->any())
        <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 flex items-start gap-3">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-danger/15 text-danger">
                <x-icon name="alert-triangle" class="size-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-danger">Please Enter Valid Data</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5 text-xs text-ink/90">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Main Form Card --}}
    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8 shadow-card">
        <div class="flex items-center gap-3 pb-5 mb-6 border-b border-line">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="arrow-up-right" class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Transfer Details</h2>
                <p class="text-xs text-muted mt-0.5">The source account must hold enough balance when the transfer is approved.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('account-transfers.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- From Account --}}
                <div>
                    <label for="from_account_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        From Account <span class="text-danger">*</span>
                    </label>
                    <select id="from_account_id" name="from_account_id" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('from_account_id') border-danger @enderror">
                        <option value="">Please Select</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('from_account_id') == $account->id)>
                                {{ $account->name }} ({{ $account->type->label() }} — {{ \App\Helpers\Money::format($account->current_balance) }})
                            </option>
                        @endforeach
                    </select>
                    @error('from_account_id')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- To Account --}}
                <div>
                    <label for="to_account_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        To Account <span class="text-danger">*</span>
                    </label>
                    <select id="to_account_id" name="to_account_id" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('to_account_id') border-danger @enderror">
                        <option value="">Please Select</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('to_account_id') == $account->id)>
                                {{ $account->name }} ({{ $account->type->label() }} — {{ \App\Helpers\Money::format($account->current_balance) }})
                            </option>
                        @endforeach
                    </select>
                    @error('to_account_id')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Amount --}}
                <div>
                    <label for="amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Amount ({{ \App\Models\Setting::get('currency_code', 'BDT') }}) <span class="text-danger">*</span>
                    </label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" required
                           placeholder="0.00"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('amount') border-danger @enderror">
                    @error('amount')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Transfer Date --}}
                <div>
                    <label for="transfer_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Transfer Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" id="transfer_date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('transfer_date') border-danger @enderror">
                    @error('transfer_date')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Method (populated from the Method reference data) --}}
                <div>
                    <label for="method" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Method
                    </label>
                    <select id="method" name="method"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('method') border-danger @enderror">
                        <option value="">Please Select</option>
                        @foreach($methods as $value => $label)
                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('method')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Reference No --}}
                <div>
                    <label for="reference_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Reference No.
                    </label>
                    <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no') }}"
                           placeholder="Cheque / slip no. (auto-generated when blank)"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reference_no') border-danger @enderror">
                    @error('reference_no')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Note --}}
                <div class="md:col-span-2">
                    <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Note
                    </label>
                    <textarea id="note" name="note" rows="3"
                              placeholder="Why is this money moving? (optional)"
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror">{{ old('note') }}</textarea>
                    @error('note')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form Footer Actions --}}
            <div class="pt-5 border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route('account-transfers.index') }}"
                   class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Save Transfer</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
