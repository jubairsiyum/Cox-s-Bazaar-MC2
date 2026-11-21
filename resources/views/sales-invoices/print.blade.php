<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_no }} - Sales Invoice</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a; background: #ffffff; padding: 24px 30px; font-size: 12px; line-height: 1.45;
        }
        .print-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 2px solid #1e3a8a; margin-bottom: 14px; }
        .company-identity { display: flex; align-items: center; gap: 14px; }
        .company-logo { width: 48px; height: 48px; border-radius: 8px; object-fit: contain; border: 1px solid #e2e8f0; padding: 2px; background: #fff; flex-shrink: 0; }
        .company-meta h1 { font-size: 18px; font-weight: 800; line-height: 1.2; letter-spacing: -0.01em; }
        .company-meta .tagline { font-size: 10px; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }
        .company-meta .contact { font-size: 10px; color: #64748b; margin-top: 3px; }
        .doc-meta { text-align: right; }
        .doc-title { font-size: 16px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.03em; }
        .doc-detail { font-size: 10px; color: #64748b; margin-top: 2px; }
        .doc-detail strong { color: #0f172a; }
        .party-box { display: flex; justify-content: space-between; gap: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
        .party-box .label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
        .party-box .value { font-size: 12px; font-weight: 700; margin-top: 2px; }
        .party-box .sub { font-size: 10px; color: #64748b; margin-top: 1px; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        thead th { background-color: #1e293b !important; color: #ffffff; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        thead th.right { text-align: right; }
        thead th.center { text-align: center; }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        tbody tr:nth-child(even) { background-color: #f8fafc; }
        td.right { text-align: right; }
        td.center { text-align: center; }
        .totals-wrap { display: flex; justify-content: flex-end; margin-top: 14px; }
        .totals-table { width: 280px; }
        .totals-table td { padding: 5px 10px; border: none; font-size: 11.5px; }
        .totals-table .grand td { border-top: 2px solid #1e3a8a; font-weight: 800; font-size: 13px; color: #1e3a8a; }
        .totals-table .due td { color: #b91c1c; font-weight: 700; }
        .totals-table .paid td { color: #15803d; font-weight: 700; }
        .signature-row { display: flex; justify-content: space-between; gap: 40px; margin-top: 48px; }
        .signature { flex: 1; text-align: center; }
        .signature .line { border-top: 1px solid #94a3b8; margin-bottom: 6px; }
        .signature .caption { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .footer-note { margin-top: 24px; text-align: center; font-size: 9px; color: #94a3b8; }
        @media print { body { padding: 12px; } }
    </style>
</head>
<body>
    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name', "Cox's Bazaar Mobile Center"));
        $companyAddress = \App\Models\Setting::get('company_address', "Cox's Bazaar, Chittagong, Bangladesh");
        $companyPhone = \App\Models\Setting::get('company_phone', '+880 1800-000000');
    @endphp

    <div class="print-header">
        <div class="company-identity">
            <img src="{{ asset('images/logo.svg') }}" alt="{{ $companyName }}" class="company-logo" onerror="this.style.display='none'">
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

    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
