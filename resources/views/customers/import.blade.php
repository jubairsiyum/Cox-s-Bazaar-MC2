@extends('layouts.app')

@section('title', 'Import Customers — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Import Customers')

@section('content')
@php
    $importColumns = [
        ['name' => 'Customer Name', 'required' => true, 'note' => 'Full name of the customer'],
        ['name' => 'Mobile No.', 'required' => true, 'note' => 'Primary contact mobile number'],
        ['name' => 'Tel No.', 'required' => false, 'note' => 'Landline or secondary phone'],
        ['name' => 'Email', 'required' => false, 'note' => 'Valid email address'],
        ['name' => 'Country Name', 'required' => false, 'note' => 'Defaults to Bangladesh'],
        ['name' => 'State Name', 'required' => false, 'note' => 'Division or state'],
        ['name' => 'City', 'required' => false, 'note' => 'City or district'],
        ['name' => 'Postcode', 'required' => false, 'note' => 'Postal / ZIP code'],
        ['name' => 'Address', 'required' => false, 'note' => 'Street / shop address'],
        ['name' => 'Note', 'required' => false, 'note' => 'Remarks or account notes'],
    ];
@endphp

<div x-data="{
        dragging: false,
        fileName: '',
        fileSize: '',
        handleFile(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.fileName = file.name;
                this.fileSize = (file.size / 1024).toFixed(1) + ' KB';
            } else {
                this.fileName = '';
                this.fileSize = '';
            }
        },
        handleDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer.files?.[0];
            if (file && this.$refs.fileInput) {
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.fileInput.files = dt.files;
                this.fileName = file.name;
                this.fileSize = (file.size / 1024).toFixed(1) + ' KB';
            }
        },
        clearFile() {
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
            this.fileName = '';
            this.fileSize = '';
        }
    }" class="space-y-6">

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
                <span class="font-medium text-ink">Import Customers</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Import Customers</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    Bulk CSV / Excel Upload
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Upload a spreadsheet to add multiple customers at once. Duplicate entries are automatically skipped.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ route('customers.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors shadow-2xs">
                <x-svg.arrow-left class="size-4 text-muted" />
                <span>Back to Customers List</span>
            </a>
        </div>
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- Left Column: Upload Dropzone Card --}}
        <div class="lg:col-span-5 rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-line">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-svg.upload class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Upload Customer File</h2>
                    <p class="text-xs text-muted">Supports CSV, XLSX, and XLS formats.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('customers.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="file" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                        Import Customers<span class="text-danger">*</span>
                    </label>

                    <div @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="handleDrop($event)"
                         :class="dragging ? 'border-brand-500 bg-brand-500/5 ring-3 ring-brand-500/15' : 'border-line bg-canvas/40 hover:border-brand-500/50 hover:bg-soft/40'"
                         class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-7 text-center transition-all cursor-pointer"
                         @click="$refs.fileInput.click()">
                        <input type="file" id="file" name="file" x-ref="fileInput" required
                               accept=".csv,.txt,.xlsx,.xls"
                               @change="handleFile($event)"
                               class="sr-only">

                        <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 mb-3">
                            <x-svg.upload class="size-6" />
                        </div>

                        <template x-if="!fileName">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-ink">
                                    Drop your file here or <span class="text-brand-500 underline">browse</span>
                                </p>
                                <p class="text-xs text-muted">
                                    Accepted formats: .csv, .xlsx, .xls
                                </p>
                            </div>
                        </template>

                        <template x-if="fileName">
                            <div class="w-full space-y-2" @click.stop>
                                <div class="inline-flex items-center gap-2 rounded-xl bg-success/10 px-3 py-1.5 text-xs font-semibold text-success ring-1 ring-success/20 max-w-full">
                                    <x-icon name="check" class="size-3.5 shrink-0" />
                                    <span class="truncate" x-text="fileName"></span>
                                    <span class="text-[11px] opacity-80 shrink-0" x-text="'(' + fileSize + ')'"></span>
                                </div>
                                <div>
                                    <button type="button" @click="clearFile()"
                                            class="text-xs font-semibold text-danger hover:underline cursor-pointer">
                                        Remove file
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    @error('file')
                        <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Guidance Notice --}}
                <div class="rounded-2xl border border-warning/20 bg-warning/10 px-4 py-3 text-xs text-ink/90 flex items-start gap-2.5">
                    <x-icon name="alert-triangle" class="size-4 text-warning shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <p class="font-semibold text-ink">Before you import</p>
                        <p class="text-muted leading-relaxed">
                            Ensure the header row matches the template column names. Existing customers matching name, mobile, or email will be safely skipped.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-line">
                    <a href="{{ route('customers.index') }}"
                       class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Close
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-svg.upload class="size-4" />
                        <span>Import</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Right Column: Template Download & Column Schema Reference --}}
        <div class="lg:col-span-7 space-y-6">
            {{-- Download Template Banner --}}
            <div class="rounded-3xl border border-brand-500/20 bg-brand-500/5 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-svg.file class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-ink">Download Sample CSV Template</h3>
                        <p class="text-xs text-muted mt-0.5 leading-relaxed">
                            Start with our pre-formatted spreadsheet containing valid column headers and a sample customer row.
                        </p>
                    </div>
                </div>

                <a href="{{ route('customers.import-template') }}"
                   class="btn-scale inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-4 py-2.5 text-xs font-semibold text-on-brand shadow-sm transition-colors shrink-0">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Download Example Format</span>
                </a>
            </div>

            {{-- Column Reference Table --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="px-6 py-4 border-b border-line flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-ink">Import Instructions &amp; Columns</h2>
                        <p class="text-xs text-muted mt-0.5">Columns expected in your CSV or Excel file.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-1 text-[11px] font-semibold text-muted border border-line tabular-nums">
                        {{ count($importColumns) }} columns
                    </span>
                </div>

                <div class="table-wrap max-w-full overflow-x-auto">
                    <table class="data-table w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted uppercase tracking-wider">
                                <th scope="col" class="w-14 py-3 px-5 text-center">#</th>
                                <th scope="col" class="py-3 px-4 font-bold text-ink">Column Name</th>
                                <th scope="col" class="py-3 px-4 font-bold text-ink">Notes</th>
                                <th scope="col" class="py-3 px-5 text-end font-bold text-ink">Requirement</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-surface text-xs sm:text-sm">
                            @foreach($importColumns as $index => $col)
                                <tr class="hover:bg-soft/40 transition-colors">
                                    <td class="py-3 px-5 text-center text-muted font-medium tabular-nums">{{ $index + 1 }}</td>
                                    <td class="py-3 px-4 font-semibold text-ink whitespace-nowrap">{{ $col['name'] }}</td>
                                    <td class="py-3 px-4 text-xs text-muted">{{ $col['note'] }}</td>
                                    <td class="py-3 px-5 text-end whitespace-nowrap">
                                        @if($col['required'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-danger/10 px-2.5 py-0.5 text-[11px] font-semibold text-danger ring-1 ring-danger/20">
                                                Required
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-0.5 text-[11px] font-semibold text-success ring-1 ring-success/20">
                                                Optional
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
