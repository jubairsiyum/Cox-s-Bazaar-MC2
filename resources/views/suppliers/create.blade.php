@extends('layouts.app')

@section('title', 'Add Supplier')
@section('page_title', 'Suppliers')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex items-baseline gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Suppliers</h1>
            <span class="text-sm text-gray-500 dark:text-gray-400">Add/Update Suppliers</span>
        </div>
    </div>
    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
        <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Home
        </a>
        <span>&rsaquo;</span>
        <a href="{{ route('suppliers.index') }}" class="hover:text-brand-500">Suppliers List</a>
        <span>&rsaquo;</span>
        <span class="text-gray-700 dark:text-gray-300 font-medium">Suppliers</span>
    </div>
</div>

{{-- Main Form Card --}}
<div class="card mb-6 border-t-4 border-t-cyan-500 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('suppliers.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                {{-- Left Column --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="name" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Supplier Name<span class="text-red-500 font-bold">*</span>
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Enter supplier name">
                            @error('name')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="mobile" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Mobile
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="mobile" name="mobile" value="{{ old('mobile') }}"
                                   class="form-control @error('mobile') is-invalid @enderror"
                                   placeholder="e.g. 01700000000">
                            @error('mobile')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="email" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Email
                        </label>
                        <div class="sm:col-span-2">
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="supplier@example.com">
                            @error('email')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="phone" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Phone
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   placeholder="Landline / Alternate phone">
                            @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="tax_number" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            TAX Number
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="tax_number" name="tax_number" value="{{ old('tax_number') }}"
                                   class="form-control @error('tax_number') is-invalid @enderror"
                                   placeholder="TIN / Tax registration number">
                            @error('tax_number')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Right Column --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="opening_balance" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Opening Balance
                        </label>
                        <div class="sm:col-span-2">
                            <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance"
                                   value="{{ old('opening_balance', '0.00') }}"
                                   class="form-control @error('opening_balance') is-invalid @enderror"
                                   placeholder="0.00">
                            @error('opening_balance')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="country" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Country
                        </label>
                        <div class="sm:col-span-2">
                            <select id="country" name="country" class="form-select @error('country') is-invalid @enderror">
                                <option value="Bangladesh" @selected(old('country', 'Bangladesh') === 'Bangladesh')>Bangladesh</option>
                                <option value="India" @selected(old('country') === 'India')>India</option>
                                <option value="China" @selected(old('country') === 'China')>China</option>
                                <option value="United Arab Emirates" @selected(old('country') === 'United Arab Emirates')>United Arab Emirates</option>
                                <option value="Singapore" @selected(old('country') === 'Singapore')>Singapore</option>
                                <option value="United States" @selected(old('country') === 'United States')>United States</option>
                            </select>
                            @error('country')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="state" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            State
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="state" name="state" value="{{ old('state', 'Chittagong') }}"
                                   class="form-control @error('state') is-invalid @enderror"
                                   placeholder="State / Division">
                            @error('state')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="city" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            City
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="city" name="city" value="{{ old('city', "Cox's Bazar") }}"
                                   class="form-control @error('city') is-invalid @enderror"
                                   placeholder="City name">
                            @error('city')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="postcode" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Postcode
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="postcode" name="postcode" value="{{ old('postcode') }}"
                                   class="form-control @error('postcode') is-invalid @enderror"
                                   placeholder="Postal code / Zip">
                            @error('postcode')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-start gap-2">
                        <label for="address" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right pt-2">
                            Address
                        </label>
                        <div class="sm:col-span-2">
                            <textarea id="address" name="address" rows="3"
                                      class="form-control @error('address') is-invalid @enderror"
                                      placeholder="Supplier full street address">{{ old('address') }}</textarea>
                            @error('address')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="mt-8 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-center items-center gap-4">
                <button type="submit"
                        class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                        style="background-color: #00a65a;">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save
                </button>
                <a href="{{ route('suppliers.index') }}"
                   class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                   style="background-color: #f39c12;">
                    Close
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Opening Balance Payments Card --}}
<div class="card border-t-4 border-t-cyan-500 shadow-sm">
    <div class="card-header border-b border-gray-100 dark:border-gray-800">
        <h2 class="text-base font-semibold text-cyan-600 dark:text-cyan-400">Opening Balance Payments</h2>
    </div>
    <div class="card-body p-0">
        <div class="table-wrap border-0 rounded-none">
            <table class="data-table">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <th class="w-12 text-center">#</th>
                        <th>Payment Date</th>
                        <th>Payment</th>
                        <th>Payment Type</th>
                        <th>Payment Note</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center py-6 text-gray-500 dark:text-gray-400 font-medium">
                            No Previous Stock Entry Found!!
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
