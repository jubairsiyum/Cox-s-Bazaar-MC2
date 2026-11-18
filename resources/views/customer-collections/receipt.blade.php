<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $collection->collection_no }} - Payment Receipt</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a; background: #ffffff; padding: 24px 30px; font-size: 12px; line-height: 1.45;
        }
        .print-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 2px solid #15803d; margin-bottom: 14px; }
        .company-meta h1 { font-size: 18px; font-weight: 800; line-height: 1.2; }
        .company-meta .tagline { font-size: 10px; font-weight: 600; color: #15803d; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }
        .company-meta .contact { font-size: 10px; color: #64748b; margin-top: 3px; }
        .doc-meta { text-align: right; }
        .doc-title { font-size: 16px; font-weight: 800; color: #15803d; text-transform: uppercase; letter-spacing: 0.03em; }
        .doc-detail { font-size: 10px; color: #64748b; margin-top: 2px; }
        .doc-detail strong { color: #0f172a; }
        .amount-banner { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px 18px; text-align: center; margin: 16px 0; }
        .amount-banner .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #15803d; }
        .amount-banner .value { font-size: 26px; font-weight: 800; color: #15803d; margin-top: 4px; }
        .party-box { display: flex; justify-content: space-between; gap: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
        .party-box .label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
        .party-box .value { font-size: 12px; font-weight: 700; margin-top: 2px; }
        .party-box .sub { font-size: 10px; color: #64748b; margin-top: 1px; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        thead th { background-color: #1e293b !important; color: #fff; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        thead th.right { text-align: right; }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        tbody tr:nth-child(even) { background-color: #f8fafc; }
        td.right { text-align: right; }
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
        <div class="company-meta">
            <h1>{{ $companyName }}</h1>
            <div class="tagline">Distribution & Brand Shop Management</div>
            <div class="contact">{{ $companyAddress }} · {{ $companyPhone }}</div>
        </div>
        <div class="doc-meta">
            <div class="doc-title">Payment Receipt</div>
            <div class="doc-detail">Receipt No: <strong>{{ $collection->collection_no }}</strong></div>
            <div class="doc-detail">Date: <strong>{{ $collection->collection_date->format('d/m/Y h:i A') }}</strong></div>
            <div class="doc-detail">Method: <strong>{{ $collection->method->label() }}</strong></div>
            @if($collection->reference_no)
                <div class="doc-detail">Reference: <strong>{{ $collection->reference_no }}</strong></div>
            @endif
        </div>
    </div>

    <div class="amount-banner">
        <div class="label">Amount Received</div>
        <div class="value">৳{{ number_format((float) $collection->amount, 2) }}</div>
    </div>

    <div class="party-box">
        <div>
            <div class="label">Received From</div>
            <div class="value">{{ $collection->customer?->name ?? '—' }}</div>
            <div class="sub">{{ $collection->customer?->address ?? '' }}</div>
            <div class="sub">{{ $collection->customer?->mobile ?? '' }}</div>
        </div>
        <div>
            <div class="label">Business Location</div>
            <div class="value">{{ $collection->location?->name ?? '—' }}</div>
            <div class="sub">Recorded by {{ $collection->creator?->name ?? '—' }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 36px;">#</th>
                <th>Invoice Settled</th>
                <th>Invoice Date</th>
                <th class="right" style="width: 120px;">Invoice Total</th>
                <th class="right" style="width: 120px;">Allocated</th>
            </tr>
        </thead>
        <tbody>
            @foreach($collection->allocations as $index => $allocation)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $allocation->salesInvoice?->invoice_no ?? '—' }}</strong></td>
                    <td>{{ $allocation->salesInvoice?->invoice_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="right">৳{{ number_format((float) ($allocation->salesInvoice?->grand_total ?? 0), 2) }}</td>
                    <td class="right"><strong>৳{{ number_format((float) $allocation->amount, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($collection->note)
        <div style="margin-top: 16px; font-size: 10.5px; color: #475569;">
            <strong>Note:</strong> {{ $collection->note }}
        </div>
    @endif

    <div class="signature-row">
        <div class="signature"><div class="line"></div><div class="caption">Received By</div></div>
        <div class="signature"><div class="line"></div><div class="caption">Authorized Signature</div></div>
        <div class="signature"><div class="line"></div><div class="caption">Customer Signature</div></div>
    </div>

    <div class="footer-note">This is a computer-generated receipt · {{ $companyName }} · Printed {{ now()->format('d/m/Y h:i A') }}</div>

    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
