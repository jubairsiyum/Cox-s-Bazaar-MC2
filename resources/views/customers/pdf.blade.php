<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers List - PDF</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 24px 16px;
            font-size: 12px;
            line-height: 1.45;
        }

        /* Screen Action Bar */
        .action-bar {
            max-width: 1040px;
            margin: 0 auto 18px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #f8fafc;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }

        /* Document Paper Sheet */
        .container {
            max-width: 1040px;
            margin: 0 auto;
            background: #ffffff;
            padding: 36px 40px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
        }

        /* Official Letterhead Header */
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

        .sheet-footer {
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
                background: #ffffff !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
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

            thead th {
                background-color: #1e293b !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            tbody tr:nth-child(even) td {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', "Cox's Bazaar Mobile Center"));
        $companyAddress = \App\Models\Setting::get('company_address', "Cox's Bazaar, Chittagong, Bangladesh");
        $companyPhone = \App\Models\Setting::get('company_phone', "+880 1800-000000");

        $totalInReport = count($customers);
        $activeInReport = $customers->where('status', 'active')->count();
        $inactiveInReport = $totalInReport - $activeInReport;
    @endphp

    {{-- Screen Action Bar --}}
    <div class="action-bar no-print">
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">
            &larr; Back to Customers List
        </a>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span>Download PDF</span>
            </button>
            <button type="button" onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
    </div>

    {{-- Main Printable Sheet Container --}}
    <div class="container">
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
                <div class="doc-title">Customers List</div>
                <div class="doc-detail">Date: <strong>{{ now()->format('d M, Y · h:i A') }}</strong></div>
                <div class="doc-detail">User: <strong>{{ auth()->user()?->name ?? 'Admin' }}</strong></div>
            </div>
        </div>

        {{-- Summary Bar --}}
        <div class="summary-bar">
            <div>
                Showing <strong>{{ number_format($totalInReport) }}</strong> {{ Str::plural('customer', $totalInReport) }}
                · Active: <strong>{{ number_format($activeInReport) }}</strong>
                · Inactive: <strong>{{ number_format($inactiveInReport) }}</strong>
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
                        <th style="{{ $key === 'status' ? 'text-align: center;' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $index => $customer)
                    <tr>
                        <td style="text-align: center; color: #64748b; font-size: 10.5px;">
                            {{ $index + 1 }}
                        </td>
                        @foreach($columns as $key => $label)
                            <td style="{{ $key === 'status' ? 'text-align: center;' : '' }}">
                                @if($key === 'customer_id')
                                    <span class="code-pill">{{ $customer->customer_id }}</span>
                                @elseif($key === 'name')
                                    <strong>{{ $customer->name }}</strong>
                                @elseif($key === 'mobile')
                                    {{ $customer->mobile ?: '—' }}
                                @elseif($key === 'tel_no' || $key === 'phone')
                                    {{ $customer->tel_no ?: '—' }}
                                @elseif($key === 'email')
                                    {{ $customer->email ?: '—' }}
                                @elseif($key === 'city')
                                    {{ $customer->city ?: '—' }}
                                @elseif($key === 'status')
                                    <span class="status-badge {{ $customer->status === 'active' ? 'status-active' : 'status-inactive' }}">
                                        {{ ucfirst($customer->status) }}
                                    </span>
                                @else
                                    {{ $customer->{$key} ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" style="text-align: center; padding: 24px; color: #64748b;">
                            No customers found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Footer --}}
        <div class="sheet-footer">
            <div>
                Generated from {{ $companyName }} ERP · {{ now()->format('d M, Y') }}
            </div>
            <div>
                Confidential • Page 1 of 1
            </div>
        </div>
    </div>
</body>
</html>
