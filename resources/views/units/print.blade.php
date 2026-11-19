<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Units List - Print</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            padding: 24px 30px;
            font-size: 12px;
            line-height: 1.45;
        }

        /* Official Letterhead */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 16px;
            border-bottom: 2px solid #1e3a8a;
            margin-bottom: 14px;
        }

        .company-identity {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .company-logo {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: contain;
            border: 1px solid #e2e8f0;
            padding: 2px;
            background: #ffffff;
            flex-shrink: 0;
        }

        .company-meta h1 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .company-meta .tagline {
            font-size: 10px;
            font-weight: 600;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }

        .company-meta .contact {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .doc-detail {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-detail strong {
            color: #0f172a;
        }

        .summary-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 12px;
            margin-bottom: 14px;
            font-size: 11px;
            color: #64748b;
        }

        .summary-bar strong {
            color: #0f172a;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        thead th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #1e293b;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        tbody td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            vertical-align: middle;
            color: #0f172a;
        }

        tbody tr:nth-child(even) td {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .num-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }

        .product-name {
            font-weight: 600;
            color: #0f172a;
        }

        .unit-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            background-color: #eff6ff;
            color: #1e3a8a;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .low-pill {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
            background-color: #fef3c7;
            color: #92400e;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .print-footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #94a3b8;
        }

        @media print {
            body {
                padding: 0;
            }

            @page {
                margin: 12mm 15mm;
                size: auto;
            }

            tr {
                page-break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', "Cox's Bazaar Mobile Center"));
        $companyAddress = \App\Models\Setting::get('company_address', "Cox's Bazaar, Chittagong, Bangladesh");
        $companyPhone = \App\Models\Setting::get('company_phone', "+880 1800-000000");

        $totalQuantityInReport = (int) $units->sum('quantity');
        $lowStockInReport = $units->filter(fn ($u) => $u->isLowStock())->count();
    @endphp

    {{-- Official Letterhead --}}
    <div class="print-header">
        <div class="company-identity">
            <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ $companyName }}" class="company-logo">
            <div class="company-meta">
                <h1>{{ $companyName }}</h1>
                <div class="tagline">Mobile Distribution &amp; Retail Management System</div>
                <div class="contact">{{ $companyAddress }} · Tel: {{ $companyPhone }}</div>
            </div>
        </div>

        <div class="doc-meta">
            <div class="doc-title">Units List</div>
            <div class="doc-detail">Date: <strong>{{ now()->format('d M, Y · h:i A') }}</strong></div>
            <div class="doc-detail">User: <strong>{{ auth()->user()?->name ?? 'Admin' }}</strong></div>
        </div>
    </div>

    {{-- Summary Bar --}}
    <div class="summary-bar">
        <div>
            Total Records: <strong>{{ count($units) }}</strong> {{ Str::plural('entry', count($units)) }}
            · Total Qty: <strong>{{ number_format($totalQuantityInReport) }}</strong>
            @if($lowStockInReport > 0)
                · Low Stock: <strong>{{ number_format($lowStockInReport) }}</strong>
            @endif
            @if(request('search'))
                · Filter: <strong>"{{ request('search') }}"</strong>
            @endif
        </div>
        <div>
            Official Stock Quantity Record
        </div>
    </div>

    {{-- Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">#</th>
                @foreach($columns as $key => $label)
                    <th class="{{ $key === 'quantity' ? 'num-cell' : '' }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($units as $index => $unit)
                <tr>
                    <td style="text-align: center; color: #64748b; font-size: 11px;">
                        {{ $index + 1 }}
                    </td>
                    @foreach($columns as $key => $label)
                        <td class="{{ $key === 'quantity' ? 'num-cell' : '' }}">
                            @if($key === 'product_name')
                                <span class="product-name">{{ $unit->displayName() }}</span>
                            @elseif($key === 'quantity')
                                {{ number_format((int) $unit->quantity) }}
                                @if($unit->isLowStock())
                                    <span class="low-pill">LOW</span>
                                @endif
                            @elseif($key === 'unit')
                                <span class="unit-pill">{{ $unit->unit }}</span>
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
                    <td colspan="{{ count($columns) + 1 }}" style="text-align: center; padding: 24px; color: #64748b;">
                        No quantity entries found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <div class="print-footer">
        <div>
            Generated from {{ $companyName }} ERP · {{ now()->format('d M, Y') }}
        </div>
        <div>
            Confidential • Page 1 of 1
        </div>
    </div>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
