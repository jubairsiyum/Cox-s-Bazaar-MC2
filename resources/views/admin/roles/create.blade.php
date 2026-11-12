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
    <div class="card">
        <div class="card-body">
            <div class="form-group" style="max-width:400px;">
                <label for="name" class="form-label">Role Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       required class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Branch Manager">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <span class="card-title">Permissions</span>
            <span style="font-size:.78rem;color:var(--color-text-muted);">Select the permissions this role grants</span>
        </div>
        <div class="card-body">
            @php
                $groups = $permissions->groupBy(fn ($p) => str($p->name)->before('.')->toString());
                $selected = old('permissions', []);
            @endphp

            @foreach($groups as $module => $items)
                <div class="form-group" style="border-bottom:1px solid var(--color-border);padding-bottom:14px;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:.85rem;margin-bottom:8px;">
                        <input type="checkbox" class="form-checkbox" data-group-toggle data-module="{{ $module }}"
                               style="width:auto;">
                        {{ ucwords(str_replace('-', ' ', $module)) }}
                    </label>
                    <div style="display:flex;flex-wrap:wrap;gap:14px;" data-group="{{ $module }}">
                        @foreach($items as $permission)
                            <label style="display:flex;align-items:center;gap:6px;font-size:.83rem;">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" class="form-checkbox"
                                       @checked(in_array($permission->name, $selected))>
                                {{ str($permission->name)->after('.')->replace('-', ' ')->title() }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @error('permissions.*')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <div class="card-body" style="border-top:1px solid var(--color-border);">
            <button type="submit" class="btn btn-primary">Create Role</button>
        </div>
    </div>
</form>

@push('scripts')
<script>
    document.querySelectorAll('[data-group-toggle]').forEach(function (master) {
        master.addEventListener('change', function () {
            var module = this.dataset.module;
            var checked = this.checked;
            document.querySelectorAll('[data-group="' + module + '"] input[name="permissions[]"]').forEach(function (box) {
                box.checked = checked;
            });
        });
    });
</script>
@endpush
@endsection
