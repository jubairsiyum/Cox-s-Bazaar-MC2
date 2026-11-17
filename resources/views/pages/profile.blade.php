@extends('layouts.app')

@section('content')
    <div>
        <x-common.page-breadcrumb pageTitle="Profile" />
        <div class="rounded-2xl border border-line bg-surface p-5 lg:p-6">
            <h3 class="mb-5 text-lg font-semibold text-ink lg:mb-7">Profile</h3>
            <div class="space-y-6">
                <x-main.profile.personal-information />
                <x-main.profile.address />
                <x-main.profile.security />
                <x-main.profile.danger />
            </div>
        </div>
    </div>
@endsection
