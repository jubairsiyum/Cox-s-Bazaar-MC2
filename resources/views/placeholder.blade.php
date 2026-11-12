@extends('layouts.app')

@section('title', $module)
@section('page_title', $module)

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">{{ $module }}</div>
        <div class="page-header-sub">This module is planned and will be implemented in a future phase.</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <x-empty-state
            message="The {{ $module }} module has not been built yet. See docs/modules.md for the implementation roadmap."
            icon="default" />
    </div>
</div>
@endsection
