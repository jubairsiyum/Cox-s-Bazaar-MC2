<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_no }} - Sales Invoice</title>
    <style>
        :root {
            --print-primary: #1e3a8a;
            --print-accent: #2563eb;
            --print-ink: #0f172a;
            --print-muted: #64748b;
            --print-line: #e2e8f0;
            --print-soft: #f8fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--print-ink);
            background: #f1f5f9;
            padding: 24px 16px;
            font-size: 12px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
        }
        .action-bar {
            max-width: 960px;
            margin: 0 auto 18px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 14px;
            border: 1px solid var(--print-line);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        }
        .action-bar-left { display: flex; align-items: center; gap: 12px; }
        .action-bar-title { font-size: 14px; font-weight: 700; color: var(--print-ink); }
        .action-bar-subtitle { font-size: 11.5px; color: var(--print-muted); }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px; font-size: 13px; font-weight: 600;
            border-radius: 10px; text-decoration: none; cursor: pointer; border: none;
            transition: all 0.15s ease;
        }
        .btn-primary { background: var(--print-accent); color: #ffffff; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25); }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #ffffff; color: var(--print-ink); border: 1px solid var(--print-line); }
        .btn-secondary:hover { background: var(--print-soft); color: var(--print-accent); }
        .sheet-container {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 44px;
            border-radius: 16px;
            border: 1px solid var(--print-line);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .print-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 18px; border-bottom: 2px solid var(--print-primary); margin-bottom: 16px; gap: 16px; }
        .company-identity { display: flex; align-items: center; gap: 14px; }
        .company-logo {
            width: 52px; height: 52px; border-radius: 10px; object-fit: contain;
            border: 1px solid var(--print-line); padding: 2px; background: #ffffff; flex-shrink: 0;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
        }
        .company-meta h1 { font-size: 18px; font-weight: 800; line-height: 1.2; letter-spacing: -0.01em; color: var(--print-ink); }
        .company-meta .tagline { font-size: 10px; font-weight: 700; color: var(--print-accent); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }
        .company-meta .contact { font-size: 10.5px; color: var(--print-muted); margin-top: 3px; }
        .doc-meta { text-align: right; border-left: 1px solid var(--print-line); padding-left: 18px; }
        .doc-title { font-size: 16px; font-weight: 800; color: var(--print-primary); text-transform: uppercase; letter-spacing: 0.03em; }
        .doc-detail { font-size: 10.5px; color: var(--print-muted); margin-top: 2px; }
        .doc-detail strong { color: var(--print-ink); }
        .party-box {
            display: flex; justify-content: space-between; gap: 16px;
            background: var(--print-soft); border: 1px solid var(--print-line); border-radius: 8px;
            padding: 12px 16px; margin-bottom: 16px;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
        }
        .party-box .label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--print-muted); }
        .party-box .value { font-size: 12.5px; font-weight: 700; margin-top: 2px; color: var(--print-ink); }
        .party-box .sub { font-size: 10.5px; color: var(--print-muted); margin-top: 1px; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        thead { display: table-header-group; }
        thead th {
            background-color: #1e293b !important; color: #ffffff !important;
            padding: 9px 10px; text-align: left; font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em; border: 1px solid #1e293b;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
        }
        thead th.right { text-align: right; }
        thead th.center { text-align: center; }
        tbody tr { page-break-inside: avoid; break-inside: avoid; }
        tbody td { padding: 8px 10px; border: 1px solid var(--print-line); color: #334155; vertical-align: middle; }
        tbody tr:nth-child(even) td {
            background-color: #f8fafc;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
        }
        td.right { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        td.center { text-align: center; font-variant-numeric: tabular-nums; }
        .totals-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
        .totals-table { width: 290px; }
        .totals-table td { padding: 5px 10px; border: none; font-size: 11.5px; color: var(--print-ink); }
        .totals-table .grand td { border-top: 2px solid var(--print-primary); font-weight: 800; font-size: 13px; color: var(--print-primary); padding-top: 8px; }
        .totals-table .due td { color: #b91c1c; font-weight: 700; }
        .totals-table .paid td { color: #15803d; font-weight: 700; }
        .signature-row { display: flex; justify-content: space-between; gap: 36px; margin-top: 48px; }
        .signature { flex: 1; text-align: center; }
        .signature .line { border-top: 1px dashed #94a3b8; margin-bottom: 6px; }
        .signature .caption { font-size: 10px; font-weight: 700; color: var(--print-ink); text-transform: uppercase; letter-spacing: 0.05em; }
        .footer-note { margin-top: 28px; padding-top: 12px; border-top: 1px solid var(--print-line); text-align: center; font-size: 9.5px; color: #64748b; }
        @media print {
            @page { margin: 12mm 15mm 15mm 15mm; size: A4 portrait; }
            body { background: #ffffff !important; padding: 0 !important; margin: 0 !important; }
            .no-print { display: none !important; }
            .sheet-container { box-shadow: none !important; border: none !important; padding: 0 !important; max-width: 100% !important; border-radius: 0 !important; }
            thead th {
                background-color: #1e293b !important; color: #ffffff !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            tbody tr:nth-child(even) td {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', "Cox's Bazaar Mobile Center"));
        $companyAddress = \App\Models\Setting::get('company_address', "Cox's Bazaar, Chittagong, Bangladesh");
        $companyPhone = \App\Models\Setting::get('company_phone', '+880 1800-000000');
    @endphp

    <div class="action-bar no-print">
        <div class="action-bar-left">
            <a href="{{ route('sales-invoices.show', $invoice) }}" class="btn btn-secondary">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Back to Invoice</span>
            </a>
            <div>
                <div class="action-bar-title">{{ $invoice->invoice_no }} · Sales Invoice</div>
                <div class="action-bar-subtitle">Official Sales Record · Light-paper print preview</div>
            </div>
        </div>
        <button type="button" onclick="window.print()" class="btn btn-primary">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Print / Save as PDF</span>
        </button>
    </div>

    <div class="sheet-container">
    <div class="print-header">
        <div class="company-identity">
            <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ $companyName }}" class="company-logo" onerror="this.onerror=null;this.src='{{ asset('images/logo.svg') }}'">
            <div class="company-meta">
                <h1>{{ $companyName }}</h1>
                <div class="tagline">Distribution & Brand Shop Management</div>
                <div class="contact">{{ $companyAddress }} · {{ $companyPhone }}</div>
            </div>
        </div>
        <div class="doc-meta">
            <div class="doc-title">Sales Invoice</div>
            <div class="doc-detail">Invoice No: <strong>{{ $invoice->invoice_no }}</strong></div>
            <div class="doc-detail">Date: <strong>{{ $invoice->invoice_date->format('d/m/Y h:i A') }}</strong></div>
            <div class="doc-detail">Status: <strong>{{ $invoice->status->label() }}</strong></div>
            <div class="doc-detail">Location: <strong>{{ $invoice->location?->name ?? '—' }}</strong></div>
            @if($invoice->salesOrder?->expected_date)
                <div class="doc-detail">Expected: <strong>{{ $invoice->salesOrder->expected_date->format('d/m/Y') }}</strong></div>
            @endif
        </div>
    </div>

    <div class="party-box">
        <div>
            <div class="label">Billed To</div>
            <div class="value">{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</div>
            <div class="sub">{{ $invoice->customer?->address ?? '' }}</div>
            <div class="sub">{{ $invoice->customer?->mobile ?? '' }}</div>
        </div>
        <div>
            <div class="label">Payment Status</div>
            <div class="value">{{ $invoice->payment_status->label() }}</div>
            <div class="sub">Due: ৳{{ number_format((float) $invoice->payment_due, 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 36px;">#</th>
                <th>Product</th>
                <th class="center" style="width: 70px;">Qty</th>
                <th class="right" style="width: 100px;">Rate</th>
                <th class="right" style="width: 100px;">Discount</th>
                <th class="right" style="width: 110px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->unit?->displayName() ?? 'Unknown product' }}</strong>
                        @if($item->unit?->unit)
                            <span style="color:#64748b;">({{ $item->unit->unit }})</span>
                        @endif
                    </td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">৳{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">৳{{ number_format((float) $item->discount, 2) }}</td>
                    <td class="right"><strong>৳{{ number_format((float) $item->subtotal, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrap">
        <table class="totals-table">
            <tr><td>Subtotal</td><td class="right">৳{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            <tr><td>Discount</td><td class="right">৳{{ number_format((float) $invoice->discount_amount, 2) }}</td></tr>
            <tr><td>Tax ({{ $invoice->tax_rate }}%)</td><td class="right">৳{{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
            @if((float) $invoice->credit_amount > 0)
                <tr><td>Return Credits</td><td class="right">−৳{{ number_format((float) $invoice->credit_amount, 2) }}</td></tr>
            @endif
            <tr class="grand"><td>Grand Total</td><td class="right">৳{{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
            <tr class="paid"><td>Paid</td><td class="right">৳{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
            <tr class="due"><td>Due</td><td class="right">৳{{ number_format((float) $invoice->payment_due, 2) }}</td></tr>
        </table>
    </div>

    @if($invoice->note)
        <div style="margin-top: 16px; font-size: 10.5px; color: #475569;">
            <strong>Note:</strong> {{ $invoice->note }}
        </div>
    @endif

    <div class="signature-row">
        <div class="signature"><div class="line"></div><div class="caption">Prepared By</div></div>
        <div class="signature"><div class="line"></div><div class="caption">Authorized Signature</div></div>
        <div class="signature"><div class="line"></div><div class="caption">Customer Signature</div></div>
    </div>

    <div class="footer-note">This is a computer-generated invoice · {{ $companyName }} · Printed {{ now()->format('d/m/Y h:i A') }}</div>
    </div>

    @if(request()->boolean('autoprint'))
        <script>
            window.addEventListener('load', function () { window.print(); });
        </script>
    @endif
</body>
</html>
