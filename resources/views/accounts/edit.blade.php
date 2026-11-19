@extends('layouts.app')

@section('title', 'Edit ' . ($type->value === 'cash' ? 'Cash' : 'Bank') . ' Account — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', $type->value === 'cash' ? 'Cash Accounts' : 'Bank Accounts')

@section('content')
@php
    $isCash = $type === \App\Enums\AccountType::Cash;
    $sectionTitle = $isCash ? 'Cash Accounts' : 'Bank Accounts';
    $indexRoute = $isCash ? 'cash-accounts.index' : 'bank-accounts.index';
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
                <a href="{{ route($indexRoute) }}" class="hover:text-brand-500 transition-colors">{{ $sectionTitle }}</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Account</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Account</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    {{ $account->name }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Changing the opening balance shifts the current balance by the same amount so totals stay consistent.</p>
        </div>

        <a href="{{ route($indexRoute) }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to {{ $sectionTitle }}</span>
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
                <x-icon :name="$isCash ? 'wallet' : 'payments'" class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Account Details</h2>
                <p class="text-xs text-muted mt-0.5 tabular-nums">Current balance: {{ \App\Helpers\Money::format($account->current_balance) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route($isCash ? 'cash-accounts.update' : 'bank-accounts.update', $account) }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Account Name --}}
                <div>
                    <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Account Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $account->name) }}" required
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                    @error('name')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Account Number --}}
                <div>
                    <label for="account_number" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Account Number
                    </label>
                    <input type="text" id="account_number" name="account_number" value="{{ old('account_number', $account->account_number) }}"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('account_number') border-danger @enderror">
                    @error('account_number')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                @if(! $isCash)
                    {{-- Bank Name --}}
                    <div>
                        <label for="bank_name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Bank Name
                        </label>
                        <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('bank_name') border-danger @enderror">
                        @error('bank_name')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Bank Branch --}}
                    <div>
                        <label for="bank_branch" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Bank Branch
                        </label>
                        <input type="text" id="bank_branch" name="bank_branch" value="{{ old('bank_branch', $account->bank_branch) }}"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('bank_branch') border-danger @enderror">
                        @error('bank_branch')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Opening Balance --}}
                <div>
                    <label for="opening_balance" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Opening Balance ({{ \App\Models\Setting::get('currency_code', 'BDT') }})
                    </label>
                    <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', number_format((float) $account->opening_balance, 2, '.', '')) }}"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('opening_balance') border-danger @enderror">
                    @error('opening_balance')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Status
                    </label>
                    <select id="status" name="status"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('status') border-danger @enderror">
                        <option value="active" @selected(old('status', $account->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $account->status) === 'inactive')>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Location --}}
                <div>
                    <label for="location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Linked Location
                    </label>
                    <select id="location_id" name="location_id"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('location_id') border-danger @enderror">
                        <option value="">None (head office)</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $account->location_id) == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('location_id')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Note --}}
                <div class="md:col-span-2">
                    <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Note
                    </label>
                    <textarea id="note" name="note" rows="3"
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror">{{ old('note', $account->note) }}</textarea>
                    @error('note')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form Footer Actions --}}
            <div class="pt-5 border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route($indexRoute) }}"
                   class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Update Account</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
