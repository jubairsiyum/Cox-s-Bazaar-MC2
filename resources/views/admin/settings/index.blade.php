@extends('layouts.app')

@section('title', 'Application Settings — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Application Settings')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Administration</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Settings</span>
            </nav>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Application Settings</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    Enterprise Configuration
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Configure company profiles, automatic document numbering prefixes, threshold parameters, and themes.</p>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if (session('success'))
        <div class="rounded-2xl border border-success/30 bg-success/10 p-4 text-sm font-semibold text-success flex items-center gap-2.5 shadow-2xs">
            <span class="grid size-6 place-items-center rounded-full bg-success/20 text-success">
                <x-icon name="check" class="size-3.5" />
            </span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div x-data="{
        tab: new URLSearchParams(window.location.search).get('tab') || (window.location.hash ? window.location.hash.substring(1) : 'company'),
        setTab(t) {
            this.tab = t;
            const url = new URL(window.location);
            url.searchParams.set('tab', t);
            window.history.replaceState({}, '', url);
        }
    }">

        {{-- Segmented Tab Navigation Bar --}}
        <div class="inline-flex flex-wrap items-center gap-1.5 rounded-2xl border border-line bg-surface p-1.5 shadow-card" role="tablist">
            @foreach($tabs as $key => $label)
                <button type="button"
                        role="tab"
                        :aria-selected="tab === '{{ $key }}'"
                        @click="setTab('{{ $key }}')"
                        :class="tab === '{{ $key }}' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink hover:bg-soft/60'"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition-all cursor-pointer">
                    @if($key === 'company')
                        <x-icon name="store" class="size-4" />
                    @elseif($key === 'numbering')
                        <x-icon name="enquiries" class="size-4" />
                    @else
                        <x-icon name="settings" class="size-4" />
                    @endif
                    <span>{{ $label }}</span>
                </button>
            @endforeach

            <button type="button"
                    role="tab"
                    :aria-selected="tab === 'theme'"
                    @click="setTab('theme')"
                    :class="tab === 'theme' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink hover:bg-soft/60'"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition-all cursor-pointer">
                <x-icon name="palette" class="size-4" />
                <span>Theme & Appearance</span>
            </button>
        </div>

        {{-- Form Handling --}}
        <form method="POST" action="{{ route('settings.update') }}" class="mt-6">
            @csrf
            @method('PATCH')

            @foreach($tabs as $key => $label)
                <div x-show="tab === '{{ $key }}'" x-cloak class="space-y-6">
                    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-7 shadow-card">
                        <div class="flex items-center justify-between pb-4 border-b border-line mb-6">
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-ink">{{ $label }}</h3>
                                <p class="text-xs text-muted mt-0.5">
                                    @if($key === 'company')
                                        General company identity, address, and legal invoice contact details.
                                    @elseif($key === 'numbering')
                                        Sequential document numbering prefixes for orders, invoices, transfers, and dues.
                                    @else
                                        Global inventory threshold values, currency, and negative stock controls.
                                    @endif
                                </p>
                            </div>
                        </div>

                        @php $groupSettings = $groups->get($key, collect()); @endphp

                        @if($key === 'company')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                @foreach($groupSettings as $setting)
                                    <div class="{{ $setting->key === 'company_address' ? 'sm:col-span-2' : '' }}">
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5" for="{{ $setting->key }}">
                                            {{ $setting->label }}
                                        </label>
                                        @if($setting->key === 'company_address')
                                            <textarea id="{{ $setting->key }}"
                                                      name="settings[{{ $setting->key }}]"
                                                      rows="3"
                                                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all resize-y">{{ old("settings.{$setting->key}", $setting->value) }}</textarea>
                                        @else
                                            <input type="{{ $setting->key === 'company_email' ? 'email' : 'text' }}"
                                                   id="{{ $setting->key }}"
                                                   name="settings[{{ $setting->key }}]"
                                                   value="{{ old("settings.{$setting->key}", $setting->value) }}"
                                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all">
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                        @elseif($key === 'numbering')
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                @foreach($groupSettings as $setting)
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5" for="{{ $setting->key }}">
                                            {{ $setting->label }}
                                        </label>
                                        <input type="text"
                                               id="{{ $setting->key }}"
                                               name="settings[{{ $setting->key }}]"
                                               value="{{ old("settings.{$setting->key}", $setting->value) }}"
                                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all font-mono"
                                               maxlength="20">
                                    </div>
                                @endforeach
                            </div>

                        @else
                            <div class="space-y-5">
                                @foreach($groupSettings as $setting)
                                    @if($setting->key === 'allow_negative_stock')
                                        <div class="flex items-center justify-between rounded-2xl border border-line bg-soft/30 p-4 transition-colors">
                                            <div>
                                                <div class="text-sm font-bold text-ink">{{ $setting->label }}</div>
                                                <div class="text-xs text-muted mt-0.5">Permit stock quantities to go below zero during POS and sales invoices</div>
                                            </div>
                                            <label class="relative inline-flex cursor-pointer items-center">
                                                <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                                <input type="checkbox"
                                                       name="settings[{{ $setting->key }}]"
                                                       value="1"
                                                       class="peer sr-only"
                                                       @checked(old("settings.{$setting->key}", $setting->value) == 1)>
                                                <div class="h-6 w-11 rounded-full bg-line transition-colors peer-checked:bg-brand-500 peer-focus:ring-2 peer-focus:ring-brand-500/20 after:absolute after:top-0.5 after:left-[2px] after:size-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
                                            </label>
                                        </div>
                                    @else
                                        <div class="max-w-md">
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5" for="{{ $setting->key }}">
                                                {{ $setting->label }}
                                            </label>
                                            <input type="{{ $setting->key === 'low_stock_threshold' ? 'number' : 'text' }}"
                                                   id="{{ $setting->key }}"
                                                   name="settings[{{ $setting->key }}]"
                                                   value="{{ old("settings.{$setting->key}", $setting->value) }}"
                                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all">
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @error("settings.{$key}")
                            <p class="mt-3 text-xs text-danger font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Form Footer Actions --}}
                    <div class="flex justify-end">
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                            <x-icon name="check" class="size-4" />
                            <span>Save Settings</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </form>

        {{-- Theme & Appearance Tab --}}
        <div x-show="tab === 'theme'" x-cloak class="mt-6">
            @include('admin.settings.partials.theme-tab')
        </div>

    </div>

</div>
@endsection
