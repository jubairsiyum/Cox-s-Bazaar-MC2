<!DOCTYPE html>
<html lang="en">
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
            background-color: #00a65a;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #008d4c;
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
            background-color: #1e3a5f !important;
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
        tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            thead th {
                background-color: #1e3a5f !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                margin: 12mm;
                size: A4 portrait;
            }
            tr {
                page-break-inside: avoid;
            }
            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>
    <div class="action-bar">
        <h2>Customers List Preview</h2>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download PDF
            </button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
    </div>

    <div class="container">
        <h1 class="sheet-title">Customers List</h1>

        <table>
            <thead>
                <tr>
                    @foreach($columns as $key => $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr>
                        @foreach($columns as $key => $label)
                            <td>
                                @if($key === 'customer_id')
                                    <strong>{{ $customer->customer_id }}</strong>
                                @elseif($key === 'name')
                                    {{ $customer->name }}
                                @elseif($key === 'mobile')
                                    {{ $customer->mobile ?: '—' }}
                                @elseif($key === 'phone')
                                    {{ $customer->phone ?: '—' }}
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
                        <td colspan="{{ count($columns) }}" style="text-align: center; padding: 24px; color: #64748b;">
                            No customers found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
