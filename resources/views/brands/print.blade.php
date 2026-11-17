@extends('layouts.print')

@section('title', 'Brands List - Print')
@section('sheet_title', 'Brands List')
@section('doc_ref', 'DOC-BRD-' . strtoupper(now()->format('Ymd-His')))
@section('back_url', route('brands.index'))
@section('back_label', 'Back to Brands List')
@section('print_button_text', 'Print Document')

@section('meta_strip')
<div class="meta-strip">
    <div class="meta-strip-left">
        Showing <strong>{{ count($brands) }}</strong> registered manufacturer {{ Str::plural('brand', count($brands)) }}
        @if(request('search'))
            · Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </div>
    <div class="meta-strip-right">
        AUTO-PRINT READY
    </div>
</div>
@endsection

@section('content')
<table>
    <thead>
        <tr>
            <th style="width: 45px; text-align: center;">#</th>
            @foreach($columns as $key => $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($brands as $index => $brand)
            <tr>
                <td style="text-align: center; color: var(--print-muted); font-size: 11px;">
                    {{ $index + 1 }}
                </td>
                @foreach($columns as $key => $label)
                    <td>
                        @if($key === 'name')
                            <div class="brand-cell" style="display: flex; align-items: center; gap: 8px;">
                                <x-brand-logo :name="$brand->name" size="xs" :showContainer="true" containerClass="!size-7 !rounded-lg !p-1 bg-white border border-slate-200" />
                                <span class="brand-name-text" style="font-weight: 600; color: #0f172a;">{{ $brand->name }}</span>
                            </div>
                        @elseif($key === 'note')
                            {{ $brand->note ?: '—' }}
                        @else
                            {{ $brand->{$key} ?? '—' }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" style="text-align: center; padding: 24px; color: var(--print-muted);">
                    No brands found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection

@section('scripts')
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 300);
    });
</script>
@endsection
