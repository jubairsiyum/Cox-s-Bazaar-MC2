<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustments List - PDF</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 24px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .action-bar {
            max-width: 1100px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .action-bar h2 {
            font-size: 16px;
            font-weight: 600;
            color: #334155;
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
            background-color: #4F46E5;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #4338CA;
        }
        .btn-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
        }
        .sheet-title {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
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
            background-color: #4F46E5 !important;
            color: #ffffff !important;
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
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }

        @media print {
            body {
                background: #ffffff !important;
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
                background-color: #4F46E5 !important;
                color: #ffffff !important;
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
        <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary">
            &larr; Back to Stock Adjustments List
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
        <h1 class="sheet-title">Stock Adjustments List</h1>

        <table>
            <thead>
                <tr>
                    @foreach($columns as $key => $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adjustment)
                    <tr>
                        @foreach($columns as $key => $label)
                            <td>
                                @switch($key)
                                    @case('adjustment_date')
                                        {{ $adjustment->adjustment_date->format('d/m/Y H:i') }}
                                        @break
                                    @case('location')
                                        {{ $adjustment->location?->name ?? '—' }}
                                        @break
                                    @case('type')
                                        {{ $adjustment->type->label() }}
                                        @break
                                    @case('total_amount')
                                        {{ \App\Helpers\Money::format($adjustment->total_amount) }}
                                        @break
                                    @case('total_amount_recovered')
                                        {{ \App\Helpers\Money::format($adjustment->total_amount_recovered) }}
                                        @break
                                    @case('reason')
                                        {{ $adjustment->reason ?: '—' }}
                                        @break
                                    @case('creator')
                                        {{ $adjustment->creator?->name ?? '—' }}
                                        @break
                                    @default
                                        {{ $adjustment->{$key} ?? '—' }}
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" style="text-align: center; padding: 24px; color: #64748b;">
                            No stock adjustments found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
