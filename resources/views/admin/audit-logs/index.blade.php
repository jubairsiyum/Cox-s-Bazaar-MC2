@extends('layouts.app')

@section('title', 'Audit Logs')
@section('page_title', 'Audit Logs')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Audit Logs</div>
        <div class="page-header-sub">Track user activity and system changes</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="filter-bar">
            <div class="search-input-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" class="search-input" placeholder="Search description or user...">
            </div>
            <select name="event" class="form-select" onchange="this.form.submit()" style="min-width:150px;">
                <option value="">All Events</option>
                @foreach($events as $event)
                    <option value="{{ $event }}" @selected(request('event') === $event)>{{ ucfirst($event) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
        </form>

        <div class="table-wrap">
            <table class="data-table table-responsive">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Event</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td data-label="Time" style="white-space:nowrap;">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td data-label="User">
                                <div class="cell-user">
                                    <x-avatar :name="$log->user?->name ?? 'System'" size="sm" />
                                    <span class="cell-title" style="font-weight:500;">{{ $log->user?->name ?? 'System' }}</span>
                                </div>
                            </td>
                            <td data-label="Event">
                                @php
                                    $eventColors = [
                                        'created' => 'badge-success', 'updated' => 'badge-info',
                                        'deleted' => 'badge-danger', 'login' => 'badge-primary',
                                        'logout' => 'badge-neutral', 'posted' => 'badge-success',
                                        'voided' => 'badge-warning',
                                    ];
                                @endphp
                                <span class="badge {{ $eventColors[$log->event] ?? 'badge-neutral' }}">{{ $log->event }}</span>
                            </td>
                            <td data-label="Description">{{ $log->description }}</td>
                            <td data-label="IP Address">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" data-label=""><x-empty-state message="No activity recorded yet." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$logs" />
    </div>
</div>
@endsection
