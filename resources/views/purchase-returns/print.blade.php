<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Returns List - Print</title>
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
            padding: 8px 12px;
            margin-bottom: 14px;
            font-size: 11px;
            color: #64748b;
        }

        .summary-bar strong {
            color: #0f172a;
        }

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
            padding: 9px 10px;
            background-color: #f1f5f9 !important;
            font-weight: 700;
            color: #0f172a;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
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
        $companyPhone = \App\Models\Setting::get('company_phone', '+880 1800-000000');
        $reportGrandTotal = (float) $returns->sum('grand_total');
        $reportPaymentDue = (float) $returns->sum('payment_due');
    @endphp

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
            <div class="doc-title">Purchase Returns List</div>
            <div class="doc-detail">Date: <strong>{{ now()->format('d M, Y · h:i A') }}</strong></div>
            <div class="doc-detail">User: <strong>{{ auth()->user()?->name ?? 'Admin' }}</strong></div>
        </div>
    </div>

    <div class="summary-bar">
        <div>
            Total Records: <strong>{{ count($returns) }}</strong> purchase {{ Str::plural('return', count($returns)) }}
            · Return Total: <strong>{{ \App\Helpers\Money::format($reportGrandTotal) }}</strong>
            · Return Due: <strong>{{ \App\Helpers\Money::format($reportPaymentDue) }}</strong>
            @if(request('search'))
                · Filter: <strong>"{{ request('search') }}"</strong>
            @endif
        </div>
        <div>Official Purchase Returns Register</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 36px; text-align: center;">#</th>
                @foreach($columns as $key => $label)
                    <th class="{{ in_array($key, ['grand_total', 'payment_due'], true) ? 'num' : '' }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($returns as $index => $return)
                <tr>
                    <td style="text-align: center; color: #64748b; font-size: 10.5px;">{{ $index + 1 }}</td>
                    @foreach($columns as $key => $label)
                        <td class="{{ in_array($key, ['grand_total', 'payment_due'], true) ? 'num' : '' }}">
                            @switch($key)
                                @case('return_date')
                                    {{ $return->return_date->format('d/m/Y H:i') }}
                                    @break
                                @case('reference_no')
                                    <strong>{{ $return->reference_no }}</strong>
                                    @break
                                @case('parent_purchase')
                                    {{ $return->purchaseOrder?->reference_no ?? '—' }}
                                    @break
                                @case('reason')
                                    {{ $return->reason ?: '—' }}
                                    @break
                                @case('location')
                                    {{ $return->location?->name ?? '—' }}
                                    @break
                                @case('supplier')
                                    {{ $return->supplier?->name ?? '—' }}
                                    @break
                                @case('approval_status')
                                    {{ $return->approval_status->label() }}
                                    @break
                                @case('payment_status')
                                    {{ $return->payment_status->label() }}
                                    @break
                                @case('grand_total')
                                    <strong>{{ \App\Helpers\Money::format($return->grand_total) }}</strong>
                                    @break
                                @case('payment_due')
                                    {{ \App\Helpers\Money::format($return->payment_due) }}
                                    @break
                                @default
                                    {{ $return->{$key} ?? '—' }}
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" style="text-align: center; padding: 24px; color: #64748b;">
                        No purchase returns found.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($returns) > 0)
            <tfoot>
                <tr>
                    <td></td>
                    @foreach($columns as $key => $label)
                        @if($loop->first)
                            <td>Totals ({{ count($returns) }} records)</td>
                        @elseif($key === 'grand_total')
                            <td class="num">{{ \App\Helpers\Money::format($reportGrandTotal) }}</td>
                        @elseif($key === 'payment_due')
                            <td class="num">{{ \App\Helpers\Money::format($reportPaymentDue) }}</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="print-footer">
        <div>Generated from {{ $companyName }} ERP · {{ now()->format('d M, Y') }}</div>
        <div>Confidential • Official Purchase Returns Statement</div>
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
