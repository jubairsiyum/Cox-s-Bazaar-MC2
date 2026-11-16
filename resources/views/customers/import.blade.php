@extends('layouts.app')

@section('title', 'Import Customers')
@section('page_title', 'Import Customers')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex items-baseline gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-ink">Import Customers</h1>
            <span class="text-sm text-muted">Add/Update Customers</span>
        </div>
    </div>
    <div class="flex items-center gap-1.5 text-xs text-muted">
        <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Home
        </a>
        <span>&rsaquo;</span>
        <a href="{{ route('customers.index') }}" class="hover:text-brand-500">Customers List</a>
        <span>&rsaquo;</span>
        <span class="text-ink font-medium">Import Customers</span>
    </div>
</div>

{{-- Top Card: File Upload --}}
<div class="card mb-6 border-t-4 border-t-cyan-500 shadow-sm">
    <div class="card-body">
        <p class="text-xs text-muted mb-6 font-medium">
            Please Enter Valid Data
        </p>

        <form method="POST" action="{{ route('customers.import.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="max-w-xl mx-auto space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                    <label for="file" class="text-sm font-semibold text-ink sm:text-right">
                        Import Customers<span class="text-danger font-bold">*</span>
                    </label>
                    <div class="sm:col-span-2 space-y-1">
                        <input type="file" id="file" name="file" required accept=".csv,.txt,.xlsx,.xls"
                               class="block w-full text-xs text-ink file:mr-4 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-soft dark:file:bg-surface file:text-ink dark:file:text-ink hover:file:bg-line border border-line rounded-md p-1 bg-surface">
                        <p class="text-xs text-danger font-medium">
                            Note: File must be in CSV or Excel format.
                        </p>
                        @error('file')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="pt-6 border-t border-line flex justify-center items-center gap-4">
                    <button type="submit"
                            class="px-8 py-2.5 rounded-md text-on-brand font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                            style="background-color: var(--success);">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Import
                    </button>
                    <a href="{{ route('customers.index') }}"
                       class="px-8 py-2.5 rounded-md text-on-brand font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                       style="background-color: var(--warning);">
                        Close
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Bottom Card: Import Instructions --}}
<div class="card border-t-4 border-t-cyan-500 shadow-sm">
    <div class="card-header border-b border-line flex items-center justify-between">
        <h2 class="text-base font-semibold text-ink">Import Instructions</h2>
        <a href="{{ route('customers.import-template') }}"
           class="px-4 py-2 rounded-md text-on-brand font-medium text-xs transition-all shadow-sm flex items-center gap-1.5"
           style="background-color: var(--brand-500);">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Download Example Format
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrap border-0 rounded-none">
            <table class="data-table w-full text-left">
                <thead>
                    <tr class="bg-soft text-ink">
                        <th class="w-16 p-3 text-center">#</th>
                        <th class="p-3">Column Name</th>
                        <th class="p-3 text-center">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-xs sm:text-sm">
                    <tr>
                        <td class="p-3 text-center text-muted">1</td>
                        <td class="p-3 font-semibold text-ink">Customer Name</td>
                        <td class="p-3 text-center text-danger font-bold">Required</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">2</td>
                        <td class="p-3 font-semibold text-ink">Mobile No.</td>
                        <td class="p-3 text-center text-danger font-bold">Required</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">3</td>
                        <td class="p-3 font-semibold text-ink">Tel No.</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">4</td>
                        <td class="p-3 font-semibold text-ink">Email</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">5</td>
                        <td class="p-3 font-semibold text-ink">Country Name</td>
                        <td class="p-3 text-center text-success font-medium">Optional (default: Bangladesh)</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">6</td>
                        <td class="p-3 font-semibold text-ink">State Name</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">7</td>
                        <td class="p-3 font-semibold text-ink">City</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">8</td>
                        <td class="p-3 font-semibold text-ink">Postcode</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">9</td>
                        <td class="p-3 font-semibold text-ink">Address</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                    <tr>
                        <td class="p-3 text-center text-muted">10</td>
                        <td class="p-3 font-semibold text-ink">Note</td>
                        <td class="p-3 text-center text-success font-medium">Optional</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
