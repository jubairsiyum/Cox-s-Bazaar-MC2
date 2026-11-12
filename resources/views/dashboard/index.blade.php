@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<div class="grid-4">
    <div class="stat-card">
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value">{{ $totalUsers }}</div>
        <div class="stat-card-sub">All registered accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Active Users</div>
        <div class="stat-card-value">{{ $activeUsers }}</div>
        <div class="stat-card-sub">Currently enabled</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Roles</div>
        <div class="stat-card-value">{{ $totalRoles }}</div>
        <div class="stat-card-sub">Access control groups</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Signed in as</div>
        <div class="stat-card-value" style="font-size:1rem;">{{ auth()->user()->name }}</div>
        <div class="stat-card-sub">{{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'No role' }}</div>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <span class="card-title">Recently Added Users</span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentUsers as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach($user->roles as $role)
                                    <span class="badge badge-primary">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td><x-status-badge :status="$user->status->value" /></td>
                            <td>{{ $user->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state message="No users yet." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
