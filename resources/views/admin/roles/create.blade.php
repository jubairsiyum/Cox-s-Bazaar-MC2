@extends('layouts.app')

@section('title', 'Add Role')
@section('page_title', 'Add Role')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Add Role</div>
        <div class="page-header-sub">Create a role and assign permissions</div>
    </div>
    <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back</a>
</div>

<form method="POST" action="{{ route('roles.store') }}">
    @csrf
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body">
            <div class="form-group" style="max-width:360px;margin-bottom:0;">
                <label for="name" class="form-label">Role Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       required class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Branch Manager">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Permissions</span>
            <span style="font-size:.78rem;color:var(--color-text-muted);">Select what this role can do</span>
        </div>
        <div class="card-body">
            <x-permission-matrix :permissions="$permissions" :selected="old('permissions', [])" />
            @error('permissions.*')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <div class="card-body" style="border-top:1px solid var(--color-border);display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">Create Role</button>
        </div>
    </div>
</form>
@endsection
