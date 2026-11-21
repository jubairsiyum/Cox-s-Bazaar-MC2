@php
    $canEditOwnProfile = (auth()->user()?->isSupplierPortalUser() ?? false) && auth()->user()->can('updateOwnProfile', $supplier);
@endphp

<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card"
     x-data="{ editing: {{ $errors->getBag('updateSupplierDetails')->any() ? 'true' : 'false' }} }">
    <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
            <x-icon name="store" class="size-5" />
        </div>
        <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-bold text-ink">Supplier Details</h2>
            <p class="text-xs text-muted">Identity, contact and address on file for {{ $supplier->name }}.</p>
        </div>
        @if($canEditOwnProfile)
            <button type="button" x-show="! editing" x-on:click="editing = true"
                    class="btn-scale ml-auto inline-flex shrink-0 items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                <span>Edit Details</span>
            </button>
        @endif
    </div>

    <div x-show="! editing">
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <dl class="space-y-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-muted">Identity</div>
                @foreach($details['identity'] as $row)
                    <div>
                        <dt class="text-[11px] text-muted">{{ $row['label'] }}</dt>
                        <dd class="text-sm font-semibold text-ink break-words">{{ $row['value'] ?: '—' }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-[11px] text-muted">Status</dt>
                    <dd class="mt-0.5"><x-status-badge :status="$supplier->status" /></dd>
                </div>
            </dl>

            <dl class="space-y-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-muted">Contact</div>
                @foreach($details['contact'] as $row)
                    <div>
                        <dt class="text-[11px] text-muted">{{ $row['label'] }}</dt>
                        <dd class="text-sm font-semibold text-ink break-words">{{ $row['value'] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            <dl class="space-y-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-muted">Address</div>
                <div>
                    <dt class="text-[11px] text-muted">Location</dt>
                    <dd class="text-sm font-semibold text-ink break-words">{{ $details['address'] ?: '—' }}</dd>
                </div>
            </dl>

            @if($details['ledger'] !== null)
                <dl class="space-y-3">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-muted">Ledger Balances</div>
                    @foreach($details['ledger'] as $row)
                        <div>
                            <dt class="text-[11px] text-muted">{{ $row['label'] }}</dt>
                            <dd class="text-sm font-semibold text-ink tabular-nums">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    @if($canEditOwnProfile)
        @php
            $detailFields = [
                ['name' => 'name', 'label' => 'Supplier Name', 'required' => true],
                ['name' => 'tax_number', 'label' => 'Tax / TIN'],
                ['name' => 'mobile', 'label' => 'Mobile', 'required' => true],
                ['name' => 'tel_no', 'label' => 'Telephone'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'country', 'label' => 'Country'],
                ['name' => 'state', 'label' => 'State'],
                ['name' => 'city', 'label' => 'City'],
                ['name' => 'postcode', 'label' => 'Postcode'],
            ];
        @endphp

        <form x-show="editing" x-cloak method="POST" action="{{ route('suppliers.my-profile.update', $supplier) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            @if($errors->getBag('updateSupplierDetails')->any())
                <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4">
                    <ul class="list-disc list-inside space-y-0.5 text-xs text-ink/90">
                        @foreach ($errors->getBag('updateSupplierDetails')->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($detailFields as $field)
                    <div>
                        <label for="details_{{ $field['name'] }}" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            {{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif
                        </label>
                        <input type="{{ $field['type'] ?? 'text' }}" id="details_{{ $field['name'] }}" name="{{ $field['name'] }}"
                               value="{{ old($field['name'], $supplier->{$field['name']}) }}"
                               @if($field['required'] ?? false) required @endif
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error($field['name'], 'updateSupplierDetails') border-danger @enderror">
                    </div>
                @endforeach
                <div class="sm:col-span-2 xl:col-span-3">
                    <label for="details_address" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Address</label>
                    <textarea id="details_address" name="address" rows="2"
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none">{{ old('address', $supplier->address) }}</textarea>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" x-on:click="editing = false"
                        class="inline-flex items-center gap-2 rounded-2xl border border-line bg-surface hover:bg-soft px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink transition-colors cursor-pointer">
                    <span>Cancel</span>
                </button>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Save Details</span>
                </button>
            </div>
        </form>
    @endif
</div>
