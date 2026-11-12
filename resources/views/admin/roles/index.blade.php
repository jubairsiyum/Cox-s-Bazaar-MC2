@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('page_title', 'Roles & Permissions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Roles</div>
        <div class="page-header-sub">Manage roles and their permissions</div>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('permissions.index') }}" class="btn btn-secondary">View Permissions</a>
        @can('create', Spatie\Permission\Models\Role::class)
            <a href="{{ route('roles.create') }}" class="btn btn-primary">+ Add Role</a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td style="font-weight:600;">{{ $role->name }}</td>
                            <td>
                                <span class="badge badge-neutral">{{ $role->users_count }} user{{ $role->users_count == 1 ? '' : 's' }}</span>
                            </td>
                            <td>
                                <span class="badge badge-primary">{{ $role->permissions_count }} permission{{ $role->permissions_count == 1 ? '' : 's' }}</span>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                @can('update', $role)
                                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @endcan
                                @can('delete', $role)
                                    <x-confirm-delete :action="route('roles.destroy', $role)"
                                        message="Are you sure you want to delete the &quot;{{ $role->name }}&quot; role?"
                                        label="Delete" />
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"><x-empty-state message="No roles defined." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
