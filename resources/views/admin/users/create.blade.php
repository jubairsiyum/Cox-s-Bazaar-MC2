@extends('layouts.app')

@section('title', 'Add User')
@section('page_title', 'Add User')

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Add User</div>
        <div class="page-header-sub">Create a new user account and assign roles</div>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-secondary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div class="grid-2">
                <div class="form-group">
                    <label for="name" class="form-label">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           required class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           required class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                           class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="status" class="form-label">Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="branch_id" class="form-label">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                        <option value="">— Select —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
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
                                   @checked(in_array($role->name, old('roles', [])))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('roles')<div class="form-error">{{ $message }}</div>@enderror
                @error('roles.*')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
</div>
@endsection
