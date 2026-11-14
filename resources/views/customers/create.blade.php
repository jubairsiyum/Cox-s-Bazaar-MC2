@extends('layouts.app')

@section('title', 'Add Customer')
@section('page_title', 'Customers')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex items-baseline gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Customers</h1>
            <span class="text-sm text-gray-500 dark:text-gray-400">Add/Update Customers</span>
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
        <a href="{{ route('customers.index') }}" class="hover:text-brand-500">Customers List</a>
        <span>&rsaquo;</span>
        <span class="text-gray-700 dark:text-gray-300 font-medium">Customers</span>
    </div>
</div>

{{-- Main Form Card --}}
<div class="card mb-6 border-t-4 border-t-cyan-500 shadow-sm" x-data="customerForm()">
    <div class="card-body">
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                {{-- Left Column --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="name" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Customer Name<span class="text-red-500 font-bold">*</span>
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Enter customer name">
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
                        <label for="phone" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Contact No
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   placeholder="Landline / Alternate contact no">
                            @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="email" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Email
                        </label>
                        <div class="sm:col-span-2">
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="customer@example.com">
                            @error('email')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-start gap-2">
                        <label for="note" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right pt-2">
                            Note
                        </label>
                        <div class="sm:col-span-2">
                            <textarea id="note" name="note" rows="3"
                                      class="form-control @error('note') is-invalid @enderror"
                                      placeholder="Customer notes / remarks">{{ old('note') }}</textarea>
                            @error('note')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- Right Column --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="country" class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Country
                        </label>
                        <div class="sm:col-span-2">
                            <select id="country" name="country" class="form-select @error('country') is-invalid @enderror">
                                <option value="Bangladesh" @selected(old('country', 'Bangladesh') === 'Bangladesh')>Bangladesh</option>
                                <option value="India" @selected(old('country') === 'India')>India</option>
                                <option value="United Arab Emirates" @selected(old('country') === 'United Arab Emirates')>United Arab Emirates</option>
                                <option value="Saudi Arabia" @selected(old('country') === 'Saudi Arabia')>Saudi Arabia</option>
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
                                      placeholder="Customer full street address">{{ old('address') }}</textarea>
                            @error('address')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Custom Fields Section --}}
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Custom Fields</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Add any additional customer information or attributes (e.g. NID, Tax No, Alternate Contact).</p>
                    </div>
                    <button type="button" @click="addCustomField()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer flex items-center gap-1"
                            style="background-color: #00c0ef;">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Field
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(field, index) in customFields" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="`custom_fields[${index}][label]`" x-model="field.label"
                                   placeholder="Field Name (e.g. NID)"
                                   class="form-control text-xs w-1/3">
                            <input type="text" :name="`custom_fields[${index}][value]`" x-model="field.value"
                                   placeholder="Field Value"
                                   class="form-control text-xs flex-1">
                            <button type="button" @click="removeCustomField(index)"
                                    class="p-1.5 text-red-500 hover:text-red-700 rounded transition"
                                    title="Remove field">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                    <p x-show="customFields.length === 0" class="text-xs text-gray-400 italic">No custom fields added yet. Click "+ Add Field" if needed.</p>
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
                <a href="{{ route('customers.index') }}"
                   class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                   style="background-color: #f39c12;">
                    Close
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function customerForm() {
    return {
        customFields: @json(old('custom_fields', [])),
        addCustomField() {
            this.customFields.push({ label: '', value: '' });
        },
        removeCustomField(index) {
            this.customFields.splice(index, 1);
        }
    };
}
</script>
@endsection
