<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers List - Print</title>
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
            flex-wrap: wrap;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 7px 12px;
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
            font-size: 11.5px;
        }

        thead th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #1e293b;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        tbody td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            vertical-align: middle;
            color: #0f172a;
        }

        tbody tr:nth-child(even) td {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        tfoot td {
            border: 1px solid #cbd5e1;
            background-color: #f1f5f9 !important;
            padding: 8px 10px;
            font-weight: 800;
            color: #0f172a;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .num-cell {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .due-alert {
            color: #b91c1c;
            font-weight: 700;
        }

        .return-alert {
            color: #b45309;
            font-weight: 700;
        }

        .code-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            color: #1e3a8a;
            font-size: 11px;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 700;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .status-active {
            background-color: #dcfce7;
            color: #15803d;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
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
                margin: 12mm 14mm;
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
        $currencySymbol = \App\Models\Setting::get('currency_symbol', '৳');

        $totalInReport = count($suppliers);
        $totalDueInReport = (float) $suppliers->sum('purchase_due');
        $totalReturnDueInReport = (float) $suppliers->sum('purchase_return_due');
        $hasFinancialCols = isset($columns['purchase_due']) || isset($columns['purchase_return_due']);
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
            <div class="doc-title">Suppliers List</div>
            <div class="doc-detail">Date: <strong>{{ now()->format('d M, Y · h:i A') }}</strong></div>
            <div class="doc-detail">User: <strong>{{ auth()->user()?->name ?? 'Admin' }}</strong></div>
        </div>
    </div>

    {{-- Summary Bar --}}
    <div class="summary-bar">
        <div>
            Total Records: <strong>{{ number_format($totalInReport) }}</strong> {{ Str::plural('supplier', $totalInReport) }}
            · Purchase Due: <strong>{{ $currencySymbol }}{{ number_format($totalDueInReport, 2) }}</strong>
            · Return Due: <strong>{{ $currencySymbol }}{{ number_format($totalReturnDueInReport, 2) }}</strong>
            @if(!empty($search))
                · Search: <strong>"{{ $search }}"</strong>
            @endif
            @if(!empty($status))
                · Status: <strong>{{ ucfirst($status) }}</strong>
            @endif
        </div>
        <div>
            Official Party Directory Record
        </div>
    </div>

    {{-- Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 38px; text-align: center;">#</th>
                @foreach($columns as $key => $label)
                    <th class="{{ in_array($key, ['purchase_due', 'purchase_return_due']) ? 'num-cell' : '' }}"
                        style="{{ $key === 'status' ? 'text-align: center;' : '' }}">
                        {{ $label }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($suppliers as $index => $supplier)
                <tr>
                    <td style="text-align: center; color: #64748b; font-size: 10.5px;">
                        {{ $index + 1 }}
                    </td>
                    @foreach($columns as $key => $label)
                        <td class="{{ in_array($key, ['purchase_due', 'purchase_return_due']) ? 'num-cell' : '' }}"
                            style="{{ $key === 'status' ? 'text-align: center;' : '' }}">
                            @if($key === 'supplier_id')
                                <span class="code-pill">{{ $supplier->supplier_id }}</span>
                            @elseif($key === 'name')
                                <strong>{{ $supplier->name }}</strong>
                            @elseif($key === 'mobile')
                                {{ $supplier->mobile ?: '—' }}
                            @elseif($key === 'email')
                                {{ $supplier->email ?: '—' }}
                            @elseif($key === 'tel_no' || $key === 'phone')
                                {{ $supplier->tel_no ?: '—' }}
                            @elseif($key === 'purchase_due')
                                <span class="{{ (float) $supplier->purchase_due > 0 ? 'due-alert' : '' }}">
                                    {{ $currencySymbol }}{{ number_format((float) $supplier->purchase_due, 2) }}
                                </span>
                            @elseif($key === 'purchase_return_due')
                                <span class="{{ (float) $supplier->purchase_return_due > 0 ? 'return-alert' : '' }}">
                                    {{ $currencySymbol }}{{ number_format((float) $supplier->purchase_return_due, 2) }}
                                </span>
                            @elseif($key === 'status')
                                <span class="status-badge {{ $supplier->status === 'active' ? 'status-active' : 'status-inactive' }}">
                                    {{ ucfirst($supplier->status) }}
                                </span>
                            @else
                                {{ $supplier->{$key} ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" style="text-align: center; padding: 24px; color: #64748b;">
                        No suppliers found.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($hasFinancialCols && $totalInReport > 0)
            <tfoot>
                <tr>
                    <td style="text-align: center;"></td>
                    @foreach($columns as $key => $label)
                        @if($key === 'purchase_due')
                            <td class="num-cell due-alert">{{ $currencySymbol }}{{ number_format($totalDueInReport, 2) }}</td>
                        @elseif($key === 'purchase_return_due')
                            <td class="num-cell return-alert">{{ $currencySymbol }}{{ number_format($totalReturnDueInReport, 2) }}</td>
                        @elseif($loop->first)
                            <td style="text-transform: uppercase; letter-spacing: 0.04em; font-size: 10.5px;">Total</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            </tfoot>
        @endif
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
