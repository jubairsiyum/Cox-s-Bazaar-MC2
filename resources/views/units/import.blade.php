@extends('layouts.app')

@section('title', 'Import Quantities — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Import Quantities')

@section('content')
<div x-data="unitImportPage()" class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Master Data</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('units.index') }}" class="hover:text-brand-500 transition-colors">Units</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Import Quantities</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Import Quantities</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <x-svg.upload class="size-3.5" />
                    <span>Bulk CSV / Excel Upload</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Bulk-add or update product variant stock quantities in a single upload. Existing variants update cleanly without duplicates.</p>
        </div>

        <a href="{{ route('units.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0 self-start sm:self-auto">
            <x-svg.arrow-left class="size-4 text-muted" />
            <span>Back to Units</span>
        </a>
    </div>

    {{-- Validation Alert Banner --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-4 text-danger">
            <x-icon name="alert-triangle" class="size-5 shrink-0 mt-0.5" />
            <div class="text-xs sm:text-sm">
                <p class="font-bold">Import Validation Failed</p>
                <p class="mt-0.5 opacity-90">Please review the file format and error details below, then upload again.</p>
            </div>
        </div>
    @endif

    {{-- Top Grid: Dropzone Upload Card + Template Card --}}
    <div class="grid gap-6 lg:grid-cols-3 items-start">

        {{-- Upload Form Card --}}
        <div class="lg:col-span-2 rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-svg.upload class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Upload Spreadsheet File</h2>
                    <p class="text-xs text-muted mt-0.5">Select or drop a formatted CSV or Excel file containing your stock rows.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('units.import.store') }}" enctype="multipart/form-data" class="p-6 space-y-5">
                @csrf

                <div>
                    <label for="file" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                        Import File<span class="text-danger ml-0.5">*</span>
                    </label>

                    {{-- Interactive Drag & Drop Zone --}}
                    <div @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="handleDrop($event)"
                         @click="$refs.fileInput.click()"
                         :class="dragging ? 'border-brand-500 bg-brand-500/5 ring-3 ring-brand-500/20' : (fileName ? 'border-success/50 bg-success/5' : 'border-line bg-canvas/40 hover:border-brand-500/50 hover:bg-soft/40')"
                         class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-all cursor-pointer">

                        <input type="file"
                               id="file"
                               name="file"
                               x-ref="fileInput"
                               required
                               accept=".csv,.txt,.xlsx,.xls"
                               @change="handleSelect($event)"
                               class="sr-only">

                        <div class="flex size-14 items-center justify-center rounded-2xl mb-3 transition-colors"
                             :class="fileName ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20'">
                            <template x-if="!fileName">
                                <x-svg.upload class="size-7" />
                            </template>
                            <template x-if="fileName">
                                <x-icon name="check" class="size-7" />
                            </template>
                        </div>

                        <template x-if="!fileName">
                            <div>
                                <p class="text-sm font-semibold text-ink">
                                    Drop your CSV / Excel file here, or <span class="text-brand-500 underline">browse</span>
                                </p>
                                <p class="text-xs text-muted mt-1">
                                    Accepted formats: <span class="font-mono text-ink">.csv</span>, <span class="font-mono text-ink">.xlsx</span>, <span class="font-mono text-ink">.xls</span>
                                </p>
                            </div>
                        </template>

                        <template x-if="fileName">
                            <div class="space-y-1.5">
                                <p class="text-sm font-bold text-ink" x-text="fileName"></p>
                                <p class="text-xs text-muted tabular-nums" x-text="fileSize"></p>
                                <button type="button"
                                        @click.stop="clearFile()"
                                        class="mt-2 inline-flex items-center gap-1 rounded-full bg-surface border border-line px-3 py-1 text-[11px] font-semibold text-danger hover:bg-danger/10 transition-colors cursor-pointer">
                                    <x-svg.x class="size-3" />
                                    <span>Remove file</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    @error('file')
                        <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Footer Action Bar --}}
                <div class="pt-5 border-t border-line flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ route('units.index') }}"
                       class="btn-scale inline-flex items-center justify-center rounded-xl border border-line bg-surface px-5 py-2.5 text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        <x-svg.upload class="size-4" />
                        <span>Import Quantities</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Right Sidebar: Template Download + Accepted Units --}}
        <div class="space-y-5">
            {{-- Template Download Card --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                        <x-svg.file class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-ink">Starter CSV Template</h3>
                        <p class="text-xs text-muted">Pre-formatted with sample rows</p>
                    </div>
                </div>
                <p class="text-xs text-muted leading-relaxed">
                    Download the ready-to-use CSV template with exact column headers and realistic variant examples so your import succeeds on the first try.
                </p>
                <a href="{{ route('units.import-template') }}"
                   class="btn-scale w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Download Example Format</span>
                </a>
            </div>

            {{-- Valid Units Reference Card --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="sparkles" class="size-4" />
                    </div>
                    <h3 class="text-sm font-bold text-ink">Allowed Unit Values</h3>
                </div>
                <p class="text-xs text-muted leading-relaxed">
                    Use any of the standard measurement units below in the <strong class="text-ink">Unit</strong> column:
                </p>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    @foreach (\App\Models\Unit::UNITS as $unitOption)
                        <span class="inline-flex items-center rounded-full bg-brand-500/10 px-2.5 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                            {{ $unitOption }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Card: Step-by-Step Import Instructions --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-line px-6 py-5 bg-canvas/30">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="enquiries" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Import Instructions</h2>
                    <p class="text-xs text-muted mt-0.5">Ensure your spreadsheet matches the column definitions below.</p>
                </div>
            </div>
        </div>

        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th class="w-16 py-3.5 px-5 text-center font-bold text-ink">#</th>
                        <th class="py-3.5 px-5 font-bold text-ink">Column Name</th>
                        <th class="py-3.5 px-5 font-bold text-ink">Requirement</th>
                        <th class="py-3.5 px-5 font-bold text-ink">Format &amp; Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface text-xs sm:text-sm">
                    <tr class="hover:bg-soft/40 transition-colors">
                        <td class="py-3.5 px-5 text-center font-semibold text-muted tabular-nums">1</td>
                        <td class="py-3.5 px-5 font-semibold text-ink">Product Name</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-0.5 text-[11px] font-semibold text-success ring-1 ring-success/20">
                                Required
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-muted">Primary product name (e.g., <span class="text-ink font-medium">iPhone 13</span>, <span class="text-ink font-medium">Phone Charger 20W</span>).</td>
                    </tr>
                    <tr class="hover:bg-soft/40 transition-colors">
                        <td class="py-3.5 px-5 text-center font-semibold text-muted tabular-nums">2</td>
                        <td class="py-3.5 px-5 font-semibold text-ink">Storage</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-[11px] font-semibold text-muted border border-line">
                                Optional
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-muted">Capacity variant (e.g., <span class="text-ink font-medium">128GB</span>, <span class="text-ink font-medium">256GB</span>). Leave blank for accessories.</td>
                    </tr>
                    <tr class="hover:bg-soft/40 transition-colors">
                        <td class="py-3.5 px-5 text-center font-semibold text-muted tabular-nums">3</td>
                        <td class="py-3.5 px-5 font-semibold text-ink">Color</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-[11px] font-semibold text-muted border border-line">
                                Optional
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-muted">Color variant (e.g., <span class="text-ink font-medium">Black</span>, <span class="text-ink font-medium">Natural Titanium</span>).</td>
                    </tr>
                    <tr class="hover:bg-soft/40 transition-colors">
                        <td class="py-3.5 px-5 text-center font-semibold text-muted tabular-nums">4</td>
                        <td class="py-3.5 px-5 font-semibold text-ink">Quantity</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-0.5 text-[11px] font-semibold text-success ring-1 ring-success/20">
                                Required
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-muted">Whole number &ge; 0. Matching variants automatically update through the stock ledger.</td>
                    </tr>
                    <tr class="hover:bg-soft/40 transition-colors">
                        <td class="py-3.5 px-5 text-center font-semibold text-muted tabular-nums">5</td>
                        <td class="py-3.5 px-5 font-semibold text-ink">Unit</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-0.5 text-[11px] font-semibold text-success ring-1 ring-success/20">
                                Required
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-muted">Standard unit: {{ implode(', ', \App\Models\Unit::UNITS) }}.</td>
                    </tr>
                    <tr class="hover:bg-soft/40 transition-colors">
                        <td class="py-3.5 px-5 text-center font-semibold text-muted tabular-nums">6</td>
                        <td class="py-3.5 px-5 font-semibold text-ink">Note</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-[11px] font-semibold text-muted border border-line">
                                Optional
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-muted">Packaging remarks or stock context (e.g., <span class="text-ink font-medium">10 pcs per box</span>).</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function unitImportPage() {
    return {
        dragging: false,
        fileName: '',
        fileSize: '',

        handleSelect(event) {
            const file = event.target.files?.[0];
            this.setFileMeta(file);
        },

        handleDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;
            this.setFileMeta(file);
        },

        setFileMeta(file) {
            if (!file) {
                this.fileName = '';
                this.fileSize = '';
                return;
            }
            this.fileName = file.name;
            const kb = file.size / 1024;
            this.fileSize = kb < 1024 ? `${kb.toFixed(1)} KB` : `${(kb / 1024).toFixed(2)} MB`;
        },

        clearFile() {
            this.$refs.fileInput.value = '';
            this.fileName = '';
            this.fileSize = '';
        }
    };
}
</script>
@endsection
