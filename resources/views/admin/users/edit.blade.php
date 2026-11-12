@extends('layouts.app')

@section('title', 'Edit User')
@section('page_title', 'Edit User')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Edit User</div>
        <div class="page-header-sub">{{ $user->name }} — {{ $user->email }}</div>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-secondary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PATCH')

            <div class="grid-2">
                <div class="form-group">
                    <label for="name" class="form-label">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                           required class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                           required class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="status" class="form-label">Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="active" @selected(old('status', $user->status->value) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $user->status->value) === 'inactive')>Inactive</option>
                    </select>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">New Password <span style="color:var(--color-text-muted);font-weight:400;">(leave blank to keep current)</span></label>
                    <input type="password" id="password" name="password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="branch_id" class="form-label">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                        <option value="">— Select —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Roles</label>
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                    @foreach($roles as $role)
                        <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="form-checkbox"
                                   @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->all())))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('roles')<div class="form-error">{{ $message }}</div>@enderror
                @error('roles.*')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Update User</button>
        </form>
    </div>
</div>
@endsection
