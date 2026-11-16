<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Units List - PDF</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--canvas);
            color: var(--ink);
            padding: 24px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--surface);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px var(--overlay), 0 2px 4px -2px var(--overlay);
        }
        .action-bar {
            max-width: 1100px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--overlay);
            border: 1px solid var(--line);
        }
        .action-bar h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background-color: var(--brand-600);
            color: var(--surface);
        }
        .btn-primary:hover {
            background-color: var(--brand-600);
        }
        .btn-secondary {
            background-color: var(--soft);
            color: var(--muted);
            border: 1px solid var(--line);
        }
        .btn-secondary:hover {
            background-color: var(--line);
        }
        .sheet-title {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 28px;
            letter-spacing: -0.02em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }
        thead th {
            background-color: var(--brand-600) !important;
            color: var(--surface) !important;
            font-weight: 600;
            padding: 12px 14px;
            text-align: left;
            font-size: 12px;
            letter-spacing: 0.03em;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--line);
            color: var(--ink);
            vertical-align: middle;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        .num-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        @media print {
            body {
                background: var(--surface) !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .container {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            thead th {
                background-color: var(--brand-600) !important;
                color: var(--surface) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                margin: 15mm;
                size: auto;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    {{-- Action Bar --}}
    <div class="action-bar no-print">
        <a href="{{ route('units.index') }}" class="btn btn-secondary">
            &larr; Back to Units List
        </a>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download / Save as PDF
            </button>
        </div>
    </div>

    {{-- Main Printable Sheet Container --}}
    <div class="container">
        <h1 class="sheet-title">Units List</h1>

        <table>
            <thead>
                <tr>
                    @foreach($columns as $key => $label)
                        <th class="{{ $key === 'quantity' ? 'num-cell' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($units as $unit)
                    <tr>
                        @foreach($columns as $key => $label)
                            <td class="{{ $key === 'quantity' ? 'num-cell' : '' }}">
                                @if($key === 'product_name')
                                    {{ $unit->product_name }}
                                @elseif($key === 'quantity')
                                    {{ number_format((float) $unit->quantity) }}
                                @elseif($key === 'unit')
                                    {{ $unit->unit }}
                                @elseif($key === 'note')
                                    {{ $unit->note ?: '—' }}
                                @else
                                    {{ $unit->{$key} ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" style="text-align: center; padding: 24px; color: var(--muted);">
                            No quantity entries found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
