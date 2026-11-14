<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers List</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 30px;
            color: #111;
            background: #ffffff;
        }
        h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 22px;
            color: #000;
            letter-spacing: -0.01em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
            vertical-align: middle;
            color: #222;
        }
        th {
            font-weight: 700;
            background-color: #ffffff;
            color: #000;
        }
        .status-text {
            font-weight: 600;
        }
        @media print {
            body {
                padding: 0;
            }
            @page {
                margin: 15mm;
                size: auto;
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
    <h1>Suppliers List</h1>

    <table>
        <thead>
            <tr>
                @foreach($columns as $key => $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($suppliers as $supplier)
                <tr>
                    @foreach($columns as $key => $label)
                        <td>
                            @if($key === 'supplier_id')
                                {{ $supplier->supplier_id }}
                            @elseif($key === 'name')
                                {{ $supplier->name }}
                            @elseif($key === 'mobile')
                                {{ $supplier->mobile ?: '—' }}
                            @elseif($key === 'email')
                                {{ $supplier->email ?: '—' }}
                            @elseif($key === 'purchase_due')
                                {{ number_format((float) $supplier->purchase_due, 2) }}
                            @elseif($key === 'purchase_return_due')
                                {{ number_format((float) $supplier->purchase_return_due, 2) }}
                            @elseif($key === 'status')
                                <span class="status-text">{{ ucfirst($supplier->status) }}</span>
                            @else
                                {{ $supplier->{$key} ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align: center; padding: 20px; color: #666;">
                        No suppliers found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>