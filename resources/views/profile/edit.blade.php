@extends('layouts.app')

@section('title', 'My Profile — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'My Profile')

@section('content')
@php
    $roles = $user->roles->pluck('name')->all();
    $primaryRole = !empty($roles) ? ucfirst(str_replace(['_', '-'], ' ', $roles[0])) : 'Staff Member';
    $userInitials = strtoupper(substr($user->name ?? 'U', 0, 1));
@endphp

<div class="space-y-6">

    {{-- Breadcrumb & Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Account</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">My Profile</span>
            </nav>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">User Profile</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ $primaryRole }}
                </span>
                @if($user->status)
                    <x-status-badge :status="$user->status instanceof \BackedEnum ? $user->status->value : $user->status" :label="method_exists($user->status, 'label') ? $user->status->label() : null" />
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage your account information, contact credentials, and security password.</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('dashboard') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-icon name="dashboard" class="size-4 text-muted" />
                <span>Return to Dashboard</span>
            </a>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if (session('success'))
        <div class="rounded-2xl border border-success/30 bg-success/10 p-4 text-sm font-semibold text-success flex items-center gap-2.5 shadow-2xs">
            <span class="grid size-6 place-items-center rounded-full bg-success/20 text-success">
                <x-icon name="check" class="size-3.5" />
            </span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 shadow-2xs">
            <div class="flex items-start gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-danger/20 text-danger mt-0.5">
                    <x-icon name="alert-triangle" class="size-3.5" />
                </span>
                <div class="text-xs sm:text-sm text-danger">
                    <p class="font-bold">Please check the submitted form errors:</p>
                    <ul class="mt-1 list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Grid: Left Profile Card + Right Settings Forms --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- Left Column: User Identity Card --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card text-center">
                {{-- Avatar --}}
                <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-brand-500 text-2xl font-bold text-on-brand shadow-md">
                    {{ $userInitials }}
                </div>

                {{-- Names & Roles --}}
                <h2 class="mt-4 text-lg font-bold text-ink">{{ $user->name }}</h2>
                <p class="text-xs text-muted truncate">{{ $user->email }}</p>

                <div class="mt-3 flex items-center justify-center gap-2 flex-wrap">
                    @foreach($user->roles as $role)
                        <span class="inline-flex items-center rounded-full border border-line bg-soft px-3 py-0.5 text-[11px] font-semibold text-ink">
                            {{ ucfirst(str_replace(['_', '-'], ' ', $role->name)) }}
                        </span>
                    @endforeach
                </div>

                <div class="mt-6 border-t border-line pt-5 text-left space-y-3.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-muted">Account Status:</span>
                        <span class="font-semibold text-ink capitalize">{{ $user->status?->value ?? 'Active' }}</span>
                    </div>

                    @if($user->branch)
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Assigned Branch:</span>
                            <span class="font-semibold text-ink">{{ $user->branch->name }}</span>
                        </div>
                    @endif

                    @if($user->location)
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Managed Location:</span>
                            <span class="font-semibold text-ink">{{ $user->location->name }}</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Registered On:</span>
                        <span class="font-medium text-ink">{{ $user->created_at?->format('d M Y') ?? 'N/A' }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Last Login:</span>
                        <span class="font-medium text-ink">{{ $user->last_login_at?->diffForHumans() ?? 'Active session' }}</span>
                    </div>
                </div>
            </div>

            {{-- Quick System Info Card --}}
            <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
                <div class="flex items-center gap-3">
                    <div class="grid size-9 place-items-center rounded-2xl bg-brand-500/10 text-brand-500">
                        <x-icon name="lock" class="size-4" />
                    </div>
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-ink">Security Policy</h4>
                        <p class="text-[11px] text-muted">Passwords are hashed using bcrypt.</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted leading-relaxed">
                    Always use strong credentials containing uppercase, lowercase, numbers, and symbols to protect business records.
                </p>
            </div>
        </div>

        {{-- Right Column: Editable Information & Password Forms --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Card 1: Personal Profile Details --}}
            <div class="rounded-3xl border border-line bg-surface p-6 sm:p-7 shadow-card">
                <div class="flex items-center justify-between pb-4 border-b border-line mb-6">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-ink">Profile Information</h3>
                        <p class="text-xs text-muted mt-0.5">Update your display name, official email, and phone contact.</p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-2xl bg-soft text-brand-500">
                        <x-icon name="user" class="size-4.5" />
                    </span>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Name --}}
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $user->name) }}"
                                   required
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                            @error('name')
                                <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email', $user->email) }}"
                                   required
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('email') border-danger @enderror">
                            @error('email')
                                <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Phone Number
                            </label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   value="{{ old('phone', $user->phone) }}"
                                   placeholder="e.g. +880 1800 000000"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('phone') border-danger @enderror">
                            @error('phone')
                                <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                            <x-icon name="check" class="size-4" />
                            <span>Save Profile Changes</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Card 2: Password & Security --}}
            <div class="rounded-3xl border border-line bg-surface p-6 sm:p-7 shadow-card">
                <div class="flex items-center justify-between pb-4 border-b border-line mb-6">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-ink">Update Password</h3>
                        <p class="text-xs text-muted mt-0.5">Ensure your account is using a long, random password to stay secure.</p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-2xl bg-soft text-brand-500">
                        <x-icon name="key" class="size-4.5" />
                    </span>
                </div>

                <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    {{-- Current Password --}}
                    <div>
                        <label for="current_password" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Current Password <span class="text-danger">*</span>
                        </label>
                        <input type="password"
                               id="current_password"
                               name="current_password"
                               required
                               autocomplete="current-password"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('current_password') border-danger @enderror">
                        @error('current_password')
                            <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- New Password --}}
                        <div>
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                New Password <span class="text-danger">*</span>
                            </label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   required
                                   autocomplete="new-password"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('password') border-danger @enderror">
                            @error('password')
                                <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div>
                            <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Confirm New Password <span class="text-danger">*</span>
                            </label>
                            <input type="password"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all">
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                            <x-icon name="lock" class="size-4" />
                            <span>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>
@endsection
