@extends('layouts.app')

@section('title', 'Permissions')
@section('page_title', 'Permissions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Permissions</div>
        <div class="page-header-sub">All permissions grouped by module</div>
    </div>
    <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back to Roles</a>
</div>

<div class="grid-2">
    @foreach($groups as $module => $permissions)
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ ucwords(str_replace('-', ' ', $module)) }}</span>
                <span class="badge badge-neutral">{{ $permissions->count() }}</span>
            </div>
            <div class="card-body" style="padding:14px 20px;">
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach($permissions as $permission)
                        <span class="badge badge-primary" style="font-weight:500;">{{ $permission->name }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
