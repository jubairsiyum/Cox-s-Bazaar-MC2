@extends('layouts.app')

@section('title', 'Edit Customer — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Customers')

@section('content')
@php
    $savedFields = old('custom_fields', $customer->custom_fields ?? []);
    $initialFields = [];
    if (is_array($savedFields)) {
        foreach ($savedFields as $sf) {
            if (!empty($sf['label'])) {
                $initialFields[] = [
                    'label' => trim((string) $sf['label']),
                    'value' => $sf['value'] ?? '',
                ];
            }
        }
    }

    $fieldDefinitionsData = $customFieldDefinitions->map(fn ($cf) => [
        'id' => $cf->id,
        'name' => $cf->name,
    ])->values();
@endphp

<div x-data="customerEditForm()" class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Parties</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('customers.index') }}" class="hover:text-brand-500 transition-colors">Customers List</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Customer</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Customers</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    Add/Update Customers
                </span>
                <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line font-mono">
                    {{ $customer->customer_id }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold {{ $customer->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                    <span class="size-1.5 rounded-full {{ $customer->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                    <span>{{ ucfirst($customer->status) }}</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Update profile details, contact information, and custom attributes for <strong class="text-ink">{{ $customer->name }}</strong>.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ route('customers.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors shadow-2xs">
                <x-svg.arrow-left class="size-4 text-muted" />
                <span>Back to Customers List</span>
            </a>
        </div>
    </div>

    {{-- Main Form --}}
    <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Section 1: Identity & Contact --}}
            <div class="lg:col-span-6 rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5">
                <div class="flex items-center gap-3 pb-4 border-b border-line">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="drivers" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Identity &amp; Contact</h2>
                        <p class="text-xs text-muted">Primary customer name, phone numbers, and remarks.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Customer Name<span class="text-danger">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}" required
                               placeholder="Enter customer full name"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                        @error('name')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="mobile" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Mobile No.<span class="text-danger">*</span>
                            </label>
                            <input type="text" id="mobile" name="mobile" value="{{ old('mobile', $customer->mobile) }}" required
                                   placeholder="e.g. 01700000000"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all tabular-nums @error('mobile') border-danger @enderror">
                            @error('mobile')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tel_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Tel No.
                            </label>
                            <input type="text" id="tel_no" name="tel_no" value="{{ old('tel_no', $customer->tel_no) }}"
                                   placeholder="e.g. 034164200 / Landline"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all tabular-nums @error('tel_no') border-danger @enderror">
                            @error('tel_no')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}"
                               placeholder="customer@example.com"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('email') border-danger @enderror">
                        @error('email')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Note
                        </label>
                        <textarea id="note" name="note" rows="3"
                                  placeholder="Customer notes, preferences, or remarks..."
                                  class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror">{{ old('note', $customer->note) }}</textarea>
                        @error('note')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Section 2: Address & Location --}}
            <div class="lg:col-span-6 rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5">
                <div class="flex items-center gap-3 pb-4 border-b border-line">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                        <x-icon name="store" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Address &amp; Location</h2>
                        <p class="text-xs text-muted">Regional details for invoices and delivery coordination.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="country" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Country
                            </label>
                            <select id="country" name="country"
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('country') border-danger @enderror">
                                <option value="Bangladesh" @selected(old('country', $customer->country) === 'Bangladesh')>Bangladesh</option>
                                <option value="India" @selected(old('country', $customer->country) === 'India')>India</option>
                                <option value="United Arab Emirates" @selected(old('country', $customer->country) === 'United Arab Emirates')>United Arab Emirates</option>
                                <option value="Saudi Arabia" @selected(old('country', $customer->country) === 'Saudi Arabia')>Saudi Arabia</option>
                                <option value="United States" @selected(old('country', $customer->country) === 'United States')>United States</option>
                            </select>
                            @error('country')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                State / Division
                            </label>
                            <input type="text" id="state" name="state" value="{{ old('state', $customer->state ?? 'Chittagong') }}"
                                   placeholder="State / Division"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('state') border-danger @enderror">
                            @error('state')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                City
                            </label>
                            <input type="text" id="city" name="city" value="{{ old('city', $customer->city ?? "Cox's Bazar") }}"
                                   placeholder="City name"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('city') border-danger @enderror">
                            @error('city')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="postcode" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Postcode
                            </label>
                            <input type="text" id="postcode" name="postcode" value="{{ old('postcode', $customer->postcode) }}"
                                   placeholder="e.g. 4700"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all tabular-nums @error('postcode') border-danger @enderror">
                            @error('postcode')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Full Street Address
                        </label>
                        <textarea id="address" name="address" rows="4"
                                  placeholder="Shop / Building, Road, Area, Landmark..."
                                  class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('address') border-danger @enderror">{{ old('address', $customer->address) }}</textarea>
                        @error('address')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: Custom Fields --}}
        <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-line">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                        <x-icon name="sparkles" class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Custom Fields</h3>
                        <p class="text-xs text-muted">
                            Add any additional customer information or attributes (e.g. NID, Trade License, Reference). Saved fields can be reused across customers.
                        </p>
                    </div>
                </div>

                <button type="button" @click="openAddFieldModal()"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-brand-500 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer shrink-0">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Field</span>
                </button>
            </div>

            {{-- Quick-add chips for existing saved custom field definitions --}}
            <div x-show="savedDefinitions.length > 0" x-cloak class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Quick Add:</span>
                <template x-for="def in savedDefinitions" :key="def.id">
                    <div class="inline-flex items-center rounded-full border border-line bg-soft/70 text-xs font-medium text-ink overflow-hidden">
                        <button type="button"
                                @click="attachDefinition(def)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 hover:bg-brand-500/10 hover:text-brand-500 transition-colors cursor-pointer"
                                :title="`Add ${def.name} to this customer`">
                            <x-icon name="plus" class="size-3" />
                            <span x-text="def.name"></span>
                        </button>
                        <button type="button"
                                @click="deleteDefinition(def)"
                                class="px-2 py-1 border-l border-line text-muted hover:text-danger hover:bg-danger/10 transition-colors cursor-pointer"
                                :title="`Delete ${def.name} definition permanently`">
                            <x-svg.x class="size-3" />
                        </button>
                    </div>
                </template>
            </div>

            {{-- Active Custom Field Rows --}}
            <div class="space-y-3">
                <template x-for="(field, index) in customFields" :key="index">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center p-3.5 rounded-2xl bg-canvas/50 border border-line">
                        <div class="sm:col-span-4 flex items-center gap-2">
                            <span class="size-2 rounded-full bg-brand-500 shrink-0"></span>
                            <label class="text-xs font-bold uppercase tracking-wider text-ink truncate" x-text="field.label"></label>
                            <input type="hidden" :name="`custom_fields[${index}][label]`" :value="field.label">
                        </div>

                        <div class="sm:col-span-7">
                            <input type="text"
                                   :name="`custom_fields[${index}][value]`"
                                   x-model="field.value"
                                   :placeholder="`Enter ${field.label}`"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                        </div>

                        <div class="sm:col-span-1 text-right sm:text-center">
                            <button type="button"
                                    @click="removeCustomField(index)"
                                    class="inline-flex items-center justify-center rounded-xl p-2 text-muted hover:text-danger hover:bg-danger/10 transition-colors cursor-pointer"
                                    title="Remove field from form">
                                <x-svg.trash-bin class="size-4" />
                            </button>
                        </div>
                    </div>
                </template>

                <div x-show="customFields.length === 0"
                     class="rounded-2xl border border-dashed border-line bg-canvas/30 px-4 py-6 text-center">
                    <p class="text-xs text-muted">
                        No custom fields added yet. Click <strong class="text-ink font-semibold">Add Field</strong> above to add dynamic custom fields.
                    </p>
                </div>
            </div>
        </div>

        {{-- Form Footer --}}
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 rounded-3xl border border-line bg-surface px-6 py-4 shadow-card">
            <a href="{{ route('customers.index') }}"
               class="btn-scale w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-line bg-surface px-6 py-2.5 text-sm font-semibold text-ink hover:bg-soft transition-colors">
                Close
            </a>
            <button type="submit"
                    class="btn-scale w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-7 py-2.5 text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                <x-icon name="check" class="size-4" />
                <span>Update</span>
            </button>
        </div>
    </form>

    {{-- Add Custom Field Modal --}}
    <div x-show="showAddFieldModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-overlay backdrop-blur-xs p-4"
         @keydown.escape.window="showAddFieldModal = false">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface p-6 shadow-2xl"
             @click.outside="showAddFieldModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            <div class="flex items-start justify-between pb-4 border-b border-line">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="plus" class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Add New Custom Field</h3>
                        <p class="text-xs text-muted mt-0.5">Saved dynamically to reuse on future customers.</p>
                    </div>
                </div>
                <button type="button" @click="showAddFieldModal = false"
                        class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                        aria-label="Close modal">
                    <x-svg.x class="size-5" />
                </button>
            </div>

            <div class="py-5">
                <label for="new-custom-field-name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Field Name<span class="text-danger">*</span>
                </label>
                <input type="text" id="new-custom-field-name" list="existing-custom-fields" x-model="newFieldName"
                       @keydown.enter.prevent="saveNewField()"
                       placeholder="e.g. NID, Trade License, Alternate Contact"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                <datalist id="existing-custom-fields">
                    @foreach($customFieldDefinitions as $cf)
                        <option value="{{ $cf->name }}"></option>
                    @endforeach
                </datalist>
                <p x-show="fieldError" x-text="fieldError" class="text-xs font-medium text-danger mt-1.5"></p>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                <button type="button" @click="showAddFieldModal = false"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="button" @click="saveNewField()"
                        :disabled="isSubmittingField"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm transition-colors cursor-pointer disabled:opacity-60">
                    <x-icon name="check" class="size-3.5" />
                    <span x-show="!isSubmittingField">Add Field</span>
                    <span x-show="isSubmittingField">Adding...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function customerEditForm() {
    return {
        customFields: @json($initialFields),
        savedDefinitions: @json($fieldDefinitionsData),
        showAddFieldModal: false,
        newFieldName: '',
        fieldError: '',
        isSubmittingField: false,

        openAddFieldModal() {
            this.newFieldName = '';
            this.fieldError = '';
            this.showAddFieldModal = true;
        },

        attachDefinition(def) {
            if (this.customFields.some(f => f.label.toLowerCase() === def.name.toLowerCase())) {
                return;
            }
            this.customFields.push({
                id: def.id,
                label: def.name,
                value: ''
            });
        },

        async deleteDefinition(def) {
            if (!confirm(`Delete "${def.name}" from saved custom field definitions?`)) {
                return;
            }
            try {
                const response = await fetch(`/customers/custom-fields/${def.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    this.savedDefinitions = this.savedDefinitions.filter(item => item.id !== def.id);
                }
            } catch (e) {}
        },

        async saveNewField() {
            const name = this.newFieldName.trim();
            if (!name) {
                this.fieldError = 'Field name is required.';
                return;
            }

            if (this.customFields.some(f => f.label.toLowerCase() === name.toLowerCase())) {
                this.fieldError = 'This field already exists.';
                return;
            }

            this.isSubmittingField = true;
            this.fieldError = '';

            try {
                const response = await fetch('{{ route('customers.custom-fields.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ name: name })
                });

                const data = await response.json();
                if (data.success && data.custom_field) {
                    this.customFields.push({
                        id: data.custom_field.id,
                        label: data.custom_field.name,
                        value: ''
                    });
                    if (!this.savedDefinitions.some(d => d.id === data.custom_field.id)) {
                        this.savedDefinitions.push({
                            id: data.custom_field.id,
                            name: data.custom_field.name
                        });
                    }
                    this.newFieldName = '';
                    this.showAddFieldModal = false;
                } else {
                    this.fieldError = data.message || 'Failed to save field.';
                }
            } catch (err) {
                this.customFields.push({
                    id: null,
                    label: name,
                    value: ''
                });
                this.newFieldName = '';
                this.showAddFieldModal = false;
            } finally {
                this.isSubmittingField = false;
            }
        },

        removeCustomField(index) {
            this.customFields.splice(index, 1);
        }
    };
}
</script>
@endsection
