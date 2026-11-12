@extends('layouts.app')

@section('title', 'Settings')
@section('page_title', 'Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Settings</div>
        <div class="page-header-sub">Configure company, numbering, and system preferences</div>
    </div>
</div>

<div x-data="{ tab: 'company' }">
    {{-- Tabs --}}
    <div class="tabs">
        @foreach($tabs as $key => $label)
            <button type="button" class="tab" :class="{ 'active': tab === '{{ $key }}' }"
                    @click="tab = '{{ $key }}'" x-text="'{{ $label }}'"></button>
        @endforeach
    </div>

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PATCH')

        @foreach($tabs as $key => $label)
            <div x-show="tab === '{{ $key }}'" x-cloak>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">{{ $label }}</span>
                    </div>
                    <div class="card-body">
                        @php $groupSettings = $groups->get($key, collect()); @endphp

                        @if($key === 'company')
                            <div class="grid-2">
                                @foreach($groupSettings as $setting)
                                    <div class="setting-row">
                                        <label class="form-label" for="{{ $setting->key }}">{{ $setting->label }}</label>
                                        @if($setting->key === 'company_address')
                                            <textarea id="{{ $setting->key }}" name="settings[{{ $setting->key }}]" rows="2"
                                                      class="form-textarea">{{ old("settings.{$setting->key}", $setting->value) }}</textarea>
                                        @else
                                            <input type="{{ $setting->key === 'company_email' ? 'email' : 'text' }}"
                                                   id="{{ $setting->key }}" name="settings[{{ $setting->key }}]"
                                                   value="{{ old("settings.{$setting->key}", $setting->value) }}"
                                                   class="form-control">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif($key === 'numbering')
                            <div class="grid-3">
                                @foreach($groupSettings as $setting)
                                    <div class="setting-row">
                                        <label class="form-label" for="{{ $setting->key }}">{{ $setting->label }}</label>
                                        <input type="text" id="{{ $setting->key }}" name="settings[{{ $setting->key }}]"
                                               value="{{ old("settings.{$setting->key}", $setting->value) }}"
                                               class="form-control" maxlength="20">
                                    </div>
                                @endforeach
                            </div>
                        @else
                            @foreach($groupSettings as $setting)
                                @if($setting->key === 'allow_negative_stock')
                                    <div class="setting-row" style="flex-direction:row;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--color-border);">
                                        <div>
                                            <div class="form-label" style="margin-bottom:2px;">{{ $setting->label }}</div>
                                            <div class="form-hint">Permit stock quantities to go below zero during sales</div>
                                        </div>
                                        <label class="switch">
                                            <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                            <input type="checkbox" name="settings[{{ $setting->key }}]" value="1"
                                                   @checked(old("settings.{$setting->key}", $setting->value) == 1)>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                @else
                                    <div class="setting-row" style="margin-bottom:14px;max-width:320px;">
                                        <label class="form-label" for="{{ $setting->key }}">{{ $setting->label }}</label>
                                        <input type="{{ $setting->key === 'low_stock_threshold' ? 'number' : 'text' }}"
                                               id="{{ $setting->key }}" name="settings[{{ $setting->key }}]"
                                               value="{{ old("settings.{$setting->key}", $setting->value) }}"
                                               class="form-control">
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        @error("settings.{$key}")<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div style="margin-top:16px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </div>
        @endforeach
    </form>
</div>

@push('head')
<style>[x-cloak] { display: none !important; }</style>
@endpush
@endsection
