@extends('layouts.app')

@section('title', 'Users')
@section('page_title', 'User Management')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Users</div>
        <div class="page-header-sub">Manage user accounts, roles, and access</div>
    </div>
    @can('users.create')
        <a href="{{ route('users.create') }}" class="btn btn-primary">+ Add User</a>
    @endcan
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('users.index') }}" class="filter-bar" style="margin-bottom:16px;">
            <div class="search-input-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" class="search-input" placeholder="Search name, email, phone...">
            </div>
            <select name="role" class="form-select" onchange="this.form.submit()" style="min-width:160px;">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" onchange="this.form.submit()" style="min-width:140px;">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $user->name }}</div>
                                @if($user->trashed())
                                    <span class="badge badge-danger">Deleted</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone ?: '—' }}</td>
                            <td>
                                @forelse($user->roles as $role)
                                    <span class="badge badge-primary">{{ $role->name }}</span>
                                @empty
                                    <span style="color:var(--color-text-muted);">No role</span>
                                @endforelse
                            </td>
                            <td><x-status-badge :status="$user->status->value" /></td>
                            <td>{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</td>
                            <td style="text-align:right;white-space:nowrap;">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @endcan
                                @if(!$user->trashed())
                                    @can('toggleStatus', $user)
                                        <form method="POST" action="{{ route('users.toggle-status', $user) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $user->isActive() ? 'btn-danger' : 'btn-success' }}">
                                                {{ $user->isActive() ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endcan
                                    @can('delete', $user)
                                        <x-confirm-delete :action="route('users.destroy', $user)"
                                            message="Are you sure you want to delete this user account? This action cannot be undone."
                                            label="Delete" />
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><x-empty-state message="No users found." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$users" />
    </div>
</div>
@endsection
