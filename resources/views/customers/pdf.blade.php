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
            background-color: var(--canvas);
            color: var(--ink);
            padding: 24px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--surface);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px var(--overlay), 0 2px 4px -2px var(--overlay);
        }
        .action-bar {
            max-width: 1100px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--overlay);
            border: 1px solid var(--line);
        }
        .action-bar h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
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
            background-color: var(--success);
            color: var(--surface);
        }
        .btn-primary:hover {
            background-color: var(--success);
        }
        .btn-secondary {
            background-color: var(--soft);
            color: var(--muted);
            border: 1px solid var(--line);
        }
        .btn-secondary:hover {
            background-color: var(--line);
        }
        .sheet-title {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
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
            background-color: var(--ink) !important;
            color: var(--surface) !important;
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
            border-bottom: 1px solid var(--line);
            color: var(--ink);
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td {
            background-color: var(--canvas);
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active {
            background-color: var(--soft);
            color: var(--success);
        }
        .status-inactive {
            background-color: var(--soft);
            color: var(--danger);
        }
        @media print {
            body {
                background: var(--surface);
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
                background-color: var(--ink) !important;
                color: var(--surface) !important;
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
                        <td colspan="{{ count($columns) }}" style="text-align: center; padding: 24px; color: var(--muted);">
                            No customers found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
