@props([
    'title',
    'mode' => 'pdf', // 'pdf' | 'print'
    'backUrl' => null,
    'backLabel' => null,
    'downloadLabel' => 'Download / Save as PDF',
    'showClose' => false,
    'recordLabel' => 'Official ERP Record',
    'footerRight' => 'Confidential · Page 1 of 1',
    'landscape' => false,
    'maxWidth' => null,
    'compact' => false,
])

@php
    $isPrint = $mode === 'print';
    $companyName = \App\Models\Setting::get('company_name', config('app.name', "Cox's Bazaar Mobile Center"));
    $companyAddress = \App\Models\Setting::get('company_address', "Cox's Bazaar, Chittagong, Bangladesh");
    $companyPhone = \App\Models\Setting::get('company_phone', '+880 1800-000000');
    $sheetMaxWidth = $maxWidth ?? ($landscape ? '1120px' : '960px');
    $resolvedBackLabel = $backLabel ?? "Back to {$title}";
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ $isPrint ? 'Print' : 'PDF' }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            background-color: {{ $isPrint ? '#ffffff' : '#f1f5f9' }};
            padding: {{ $isPrint ? '24px 30px' : '24px 16px' }};
            font-size: {{ $compact ? '11.5px' : '12px' }};
            line-height: 1.45;
        }

        /* Screen Action Bar (PDF mode) */
        .action-bar {
            max-width: {{ $sheetMaxWidth }};
            margin: 0 auto 18px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .action-group {
            display: flex;
            gap: 10px;
            align-items: center;
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
            @if(!$isPrint)
            max-width: {{ $sheetMaxWidth }};
            margin: 0 auto;
            background: #ffffff;
            padding: 36px 40px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
            @endif
        }

        /* Official Letterhead */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
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
            flex-shrink: 0;
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

        /* Summary Metadata Bar */
        .summary-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
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

        /* Shared Report Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $compact ? '11px' : '12px' }};
        }

        thead {
            display: table-header-group;
        }

        thead th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-size: {{ $compact ? '9.5px' : '10.5px' }};
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: left;
            padding: {{ $compact ? '8px 10px' : '9px 12px' }};
            border: 1px solid #1e293b;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        tbody td {
            padding: {{ $compact ? '7px 10px' : '9px 12px' }};
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #334155;
        }

        tbody tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        tbody tr:nth-child(even) td {
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        tfoot td {
            padding: {{ $compact ? '8px 10px' : '9px 12px' }};
            border: 1px solid #cbd5e1;
            background-color: #f1f5f9 !important;
            font-weight: 700;
            color: #0f172a;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Shared Cell & Badge Helpers */
        .num {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .center {
            text-align: center !important;
        }

        .row-idx {
            width: 38px;
            text-align: center;
            color: #64748b;
            font-size: 10.5px;
        }

        .strong {
            font-weight: 700;
            color: #0f172a;
        }

        .muted {
            color: #64748b;
        }

        .empty-cell {
            text-align: center;
            padding: 24px !important;
            color: #64748b;
        }

        .code-pill {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 10px;
            font-weight: 700;
            color: #1e3a8a;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            padding: 2px 6px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .status-pill {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .unit-pill {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .low-pill {
            display: inline-block;
            margin-left: 5px;
            font-size: 9px;
            font-weight: 800;
            padding: 1px 5px;
            border-radius: 4px;
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            letter-spacing: 0.04em;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .due-alert {
            color: #b91c1c;
            font-weight: 700;
        }

        .return-alert {
            color: #b45309;
            font-weight: 700;
        }

        /* Brand Logo Cell */
        .brand-cell {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .brand-icon-box {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            flex-shrink: 0;
        }

        .brand-icon-box img {
            max-width: 18px;
            max-height: 18px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .brand-initials {
            font-size: 10px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.03em;
        }

        .brand-name {
            font-weight: 700;
            color: #0f172a;
        }

        /* Sheet Footer */
        .sheet-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #64748b;
        }

        @page {
            size: A4 {{ $landscape ? 'landscape' : 'portrait' }};
            margin: 12mm;
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
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
        }

        {{ $styles ?? '' }}
    </style>
</head>
<body>
    @if(!$isPrint)
        <div class="action-bar no-print">
            @if($backUrl)
                <a href="{{ $backUrl }}" class="btn btn-secondary">
                    &larr; {{ $resolvedBackLabel }}
                </a>
            @else
                <button type="button" onclick="window.close()" class="btn btn-secondary">
                    &larr; Close Preview
                </button>
            @endif

            <div class="action-group">
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>{{ $downloadLabel }}</span>
                </button>
                @if($showClose)
                    <button type="button" onclick="window.close()" class="btn btn-secondary">Close</button>
                @endif
            </div>
        </div>
    @endif

    <div class="container">
        <div class="print-header">
            <div class="company-identity">
                <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ $companyName }}" class="company-logo">
                <div class="company-meta">
                    <h1>{{ $companyName }}</h1>
                    <div class="tagline">Mobile Distribution &amp; Retail Management System</div>
                    <div class="contact">{{ $companyAddress }} &middot; Tel: {{ $companyPhone }}</div>
                </div>
            </div>

            <div class="doc-meta">
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-detail">Date: <strong>{{ now()->format('d M, Y · h:i A') }}</strong></div>
                <div class="doc-detail">User: <strong>{{ auth()->user()?->name ?? 'Admin' }}</strong></div>
            </div>
        </div>

        @isset($summary)
            <div class="summary-bar">
                <div>{{ $summary }}</div>
                <div>{{ $recordLabel }}</div>
            </div>
        @endisset

        {{ $slot }}

        <div class="sheet-footer">
            <div>Generated from {{ $companyName }} ERP &middot; {{ now()->format('d M, Y') }}</div>
            <div>{{ $footerRight }}</div>
        </div>
    </div>

    @if($isPrint)
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 250);
            });
        </script>
    @endif
</body>
</html>
