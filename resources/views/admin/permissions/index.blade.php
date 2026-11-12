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

@foreach($groups as $module => $permissions)
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <span class="card-title">{{ ucwords(str_replace('-', ' ', $module)) }}</span>
            <span class="badge badge-neutral">{{ $permissions->count() }}</span>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($permissions as $permission)
                    <span class="badge badge-primary">{{ $permission->name }}</span>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
@endsection
