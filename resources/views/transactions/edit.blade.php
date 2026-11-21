@extends('layouts.app')

@section('title', 'Edit Transaction — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Transactions')

@section('content')
@php
    $isCompleted = $transaction->status === \App\Enums\AccountEntryStatus::Completed;
@endphp

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
                <a href="{{ route('transactions.index') }}" class="hover:text-brand-500 transition-colors">Transactions</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Entry</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Transaction</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    {{ $transaction->reference_no ?: '#' . $transaction->id }}
                </span>
                <x-status-badge :status="$transaction->status->value" :label="$transaction->status->label()" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                @if($isCompleted)
                    This entry is completed — its balance effect is reversed and re-applied when you save.
                @else
                    This entry has no balance effect until it is approved.
                @endif
            </p>
        </div>

        <a href="{{ route('transactions.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Transactions</span>
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
                <x-icon name="taka" class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Entry Details</h2>
                <p class="text-xs text-muted mt-0.5 tabular-nums">Recorded by {{ $transaction->creator?->name ?? 'Unknown' }} · {{ $transaction->transaction_date->format('d M Y') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="space-y-6"
              x-data="{ entryType: @js(old('type', $transaction->type->value) ?: '') }">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Type --}}
                <div>
                    <label for="type" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Entry Type <span class="text-danger">*</span>
                    </label>
                    <select id="type" name="type" required x-model="entryType"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('type') border-danger @enderror">
                        @foreach(\App\Enums\AccountTransactionType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $transaction->type->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Account --}}
                <div>
                    <label for="account_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Account <span class="text-danger">*</span>
                    </label>
                    <select id="account_id" name="account_id" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('account_id') border-danger @enderror">
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('account_id', $transaction->account_id) == $account->id)>
                                {{ $account->name }} ({{ $account->type->label() }} — {{ \App\Helpers\Money::format($account->current_balance) }})
                            </option>
                        @endforeach
                    </select>
                    @error('account_id')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Category (populated from Transaction Category reference data) --}}
                <div>
                    <label for="category" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Category <span class="text-danger">*</span>
                    </label>
                    @php $selectedCategory = old('category', $transaction->category); @endphp
                    @if($categories->isNotEmpty())
                        <select id="category" name="category" required
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('category') border-danger @enderror">
                            @if(filled($selectedCategory) && ! $categories->contains('name', $selectedCategory))
                                <option value="{{ $selectedCategory }}" selected>{{ $selectedCategory }} (stored)</option>
                            @endif
                            @foreach($categories as $category)
                                <option value="{{ $category->name }}" @selected($selectedCategory === $category->name)
                                        :hidden="(entryType === 'income' && '{{ $category->type->value }}' === 'expense') || (entryType === 'expense' && '{{ $category->type->value }}' === 'income')">
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" id="category" name="category" value="{{ $selectedCategory }}" required maxlength="50"
                               placeholder="No categories configured yet — type one"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('category') border-danger @enderror">
                    @endif
                    @error('category')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Amount --}}
                <div>
                    <label for="amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Amount ({{ \App\Models\Setting::get('currency_code', 'BDT') }}) <span class="text-danger">*</span>
                    </label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount', number_format((float) $transaction->amount, 2, '.', '')) }}" required
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('amount') border-danger @enderror">
                    @error('amount')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Transaction Date --}}
                <div>
                    <label for="transaction_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Transaction Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" id="transaction_date" name="transaction_date" value="{{ old('transaction_date', $transaction->transaction_date->toDateString()) }}" required
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('transaction_date') border-danger @enderror">
                    @error('transaction_date')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Method (populated from the Method reference data) --}}
                <div>
                    <label for="method" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Method
                    </label>
                    @php $selectedMethod = old('method', $transaction->method); @endphp
                    <select id="method" name="method"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('method') border-danger @enderror">
                        <option value="">Please Select</option>
                        @if(filled($selectedMethod) && ! array_key_exists($selectedMethod, $methods))
                            <option value="{{ $selectedMethod }}" selected>{{ $selectedMethod }} (stored)</option>
                        @endif
                        @foreach($methods as $value => $label)
                            <option value="{{ $value }}" @selected($selectedMethod === $value)>{{ $label }}</option>
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
                    <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $transaction->reference_no) }}"
                           placeholder="Auto-generated when left blank"
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
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror">{{ old('note', $transaction->note) }}</textarea>
                    @error('note')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form Footer Actions --}}
            <div class="pt-5 border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route('transactions.index') }}"
                   class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Update Entry</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
