<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Document') — {{ config('app.name', "Cox's Bazaar Mobile Center") }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --print-primary: #1e3a8a;
            --print-accent: #2563eb;
            --print-ink: #0f172a;
            --print-muted: #64748b;
            --print-line: #e2e8f0;
            --print-bg-soft: #f8fafc;
            --print-paper: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: var(--print-ink);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            padding: 24px 16px;
        }

        /* Screen Action Bar */
        .action-bar {
            max-width: 960px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 14px;
            border: 1px solid var(--print-line);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        }

        .action-bar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .action-bar-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--print-ink);
        }

        .action-bar-subtitle {
            font-size: 12px;
            color: var(--print-muted);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: var(--print-accent);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #ffffff;
            color: var(--print-ink);
            border: 1px solid var(--print-line);
        }

        .btn-secondary:hover {
            background-color: #f8fafc;
            color: var(--print-accent);
        }

        /* Paper Sheet */
        .sheet-container {
            max-width: 960px;
            margin: 0 auto;
            background: var(--print-paper);
            padding: 44px 48px;
            border-radius: 16px;
            border: 1px solid var(--print-line);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            position: relative;
        }

        /* Header & Letterhead */
        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--print-primary);
            position: relative;
        }

        .company-brand {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .company-logo-img {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            object-fit: contain;
            border: 1px solid var(--print-line);
            padding: 2px;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            flex-shrink: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .company-info h2 {
            font-size: 20px;
            font-weight: 800;
            color: var(--print-ink);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .company-info .company-tagline {
            font-size: 11px;
            font-weight: 600;
            color: var(--print-accent);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 2px;
        }

        .company-details {
            font-size: 11px;
            color: var(--print-muted);
            margin-top: 4px;
            line-height: 1.45;
        }

        .document-meta-box {
            text-align: right;
            border-left: 1px solid var(--print-line);
            padding-left: 20px;
        }

        .document-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--print-primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .doc-meta-item {
            font-size: 11px;
            color: var(--print-muted);
            margin-top: 3px;
        }

        .doc-meta-item strong {
            color: var(--print-ink);
            font-weight: 600;
        }

        .doc-badge {
            display: inline-block;
            background: var(--print-bg-soft);
            color: var(--print-accent);
            border: 1px solid var(--print-line);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 6px;
        }

        /* Summary / Filter Strip */
        .meta-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--print-bg-soft);
            border: 1px solid var(--print-line);
            border-radius: 10px;
            padding: 10px 16px;
            margin: 20px 0;
            font-size: 12px;
        }

        .meta-strip-left {
            color: var(--print-muted);
        }

        .meta-strip-left strong {
            color: var(--print-ink);
        }

        .meta-strip-right {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--print-muted);
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 12px;
        }

        thead th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 14px;
            text-align: left;
            border: 1px solid #1e293b;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        thead th:first-child {
            border-top-left-radius: 8px;
        }

        thead th:last-child {
            border-top-right-radius: 8px;
        }

        tbody td {
            padding: 10px 14px;
            border: 1px solid var(--print-line);
            color: var(--print-ink);
            vertical-align: middle;
        }

        tbody tr:nth-child(even) td {
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .badge-mono {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background-color: #e0e7ff;
            color: #3730a3;
            font-weight: 700;
            font-size: 10px;
            margin-right: 8px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .brand-cell {
            display: flex;
            align-items: center;
        }

        .brand-name-text {
            font-weight: 600;
            color: var(--print-ink);
        }

        /* Signature Block */
        .signature-block {
            margin-top: 48px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            padding-top: 16px;
        }

        .sig-item {
            text-align: center;
        }

        .sig-line {
            border-top: 1px dashed #94a3b8;
            margin-bottom: 8px;
            height: 1px;
        }

        .sig-role {
            font-size: 11px;
            font-weight: 700;
            color: var(--print-ink);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sig-sub {
            font-size: 10px;
            color: var(--print-muted);
            margin-top: 2px;
        }

        /* Footer */
        .sheet-footer {
            margin-top: 36px;
            padding-top: 14px;
            border-top: 1px solid var(--print-line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #94a3b8;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .sheet-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }

            @page {
                margin: 12mm 15mm 15mm 15mm;
                size: A4 portrait;
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

            .badge-mono {
                background-color: #e0e7ff !important;
                color: #3730a3 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .company-logo-img {
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
    @stack('styles')
</head>

<body>

    @php
    $companyName = \App\Models\Setting::get('company_name', config('app.name', "Cox's Bazaar Mobile Center"));
    $companyAddress = \App\Models\Setting::get('company_address', "Cox's Bazaar, Chittagong, Bangladesh");
    $companyPhone = \App\Models\Setting::get('company_phone', "+880 1800-000000");
    $companyEmail = \App\Models\Setting::get('company_email', "support@coxsbazaarmc.com");
    @endphp

    {{-- Screen Action Toolbar --}}
    <div class="action-bar no-print">
        <div class="action-bar-left">
            <a href="@yield('back_url', url()->previous())" class="btn btn-secondary">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>@yield('back_label', 'Back')</span>
            </a>

            <div>
                <div class="action-bar-title">@yield('title', 'Document Preview')</div>
                <div class="action-bar-subtitle">Enterprise Distribution ERP · Official Report</div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>@yield('print_button_text', 'Download / Save as PDF')</span>
            </button>
        </div>
    </div>

    {{-- Printable Paper Container --}}
    <div class="sheet-container">
        {{-- Enterprise Letterhead Header --}}
        <header class="letterhead">
            <div class="company-brand">
                <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ $companyName }}" class="company-logo-img">
                <div class="company-info">
                    <h2>{{ $companyName }}</h2>
                    <div class="company-tagline">Mobile Distribution & Retail Management System</div>
                    <div class="company-details">
                        {{ $companyAddress }}<br>
                        Tel: {{ $companyPhone }} · Email: {{ $companyEmail }}
                    </div>
                </div>
            </div>

            <div class="document-meta-box">
                <h1 class="document-title">@yield('sheet_title', 'Report')</h1>
                <div class="doc-meta-item">
                    Doc Ref: <strong>@yield('doc_ref', 'DOC-' . strtoupper(now()->format('Ymd-Hi')))</strong>
                </div>
                <div class="doc-meta-item">
                    Date: <strong>{{ now()->format('d M, Y · h:i A') }}</strong>
                </div>
                <div class="doc-meta-item">
                    Printed By: <strong>{{ auth()->user()?->name ?? 'Administrator' }}</strong>
                </div>
                <div class="doc-badge">Official Record</div>
            </div>
        </header>

        {{-- Meta / Filter Strip --}}
        @yield('meta_strip')

        {{-- Main Document Content --}}
        <main>
            @yield('content')
        </main>

        {{-- Signature Block --}}
        <div class="signature-block">
            <div class="sig-item">
                <div class="sig-line"></div>
                <div class="sig-role">Prepared By</div>
                <div class="sig-sub">{{ auth()->user()?->name ?? 'Inventory Executive' }}</div>
            </div>
            <div class="sig-item">
                <div class="sig-line"></div>
                <div class="sig-role">Verified By</div>
                <div class="sig-sub">Department Supervisor</div>
            </div>
            <div class="sig-item">
                <div class="sig-line"></div>
                <div class="sig-role">Authorized Seal</div>
                <div class="sig-sub">General Manager</div>
            </div>
        </div>

        {{-- Sheet Footer --}}
        <footer class="sheet-footer">
            <div>
                Generated on {{ now()->format('l, F d, Y at h:i A') }} · {{ config('app.name', "Cox's Bazaar Mobile Center") }}
            </div>
            <div>
                Confidential • Internal Distribution Only
            </div>
        </footer>
    </div>

    @yield('scripts')

</body>

</html>