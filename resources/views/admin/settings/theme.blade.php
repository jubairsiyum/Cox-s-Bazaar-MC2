@extends('layouts.app')

@section('title', 'Choose Theme — Settings')
@section('page_title', 'Choose Theme')

@section('content')
<div class="page-header mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="page-header-title">Appearance & Themes</div>
            <div class="page-header-sub">Configure visual theme, color scheme, and workspace preferences</div>
        </div>
        <div>
            <a href="{{ route('settings.index') }}" class="btn btn-secondary inline-flex items-center gap-2">
                <span>← All Settings</span>
            </a>
        </div>
    </div>
</div>

@include('admin.settings.partials.theme-tab')
@endsection
