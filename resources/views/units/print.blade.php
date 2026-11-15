<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Units List</title>
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
        .num-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
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
    <h1>Units List</h1>

    <table>
        <thead>
            <tr>
                @foreach($columns as $key => $label)
                    <th class="{{ $key === 'quantity' ? 'num-cell' : '' }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($units as $unit)
                <tr>
                    @foreach($columns as $key => $label)
                        <td class="{{ $key === 'quantity' ? 'num-cell' : '' }}">
                            @if($key === 'product_name')
                                {{ $unit->product_name }}
                            @elseif($key === 'quantity')
                                {{ number_format((float) $unit->quantity) }}
                            @elseif($key === 'unit')
                                {{ $unit->unit }}
                            @elseif($key === 'note')
                                {{ $unit->note ?: '—' }}
                            @else
                                {{ $unit->{$key} ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align: center; padding: 20px; color: #666;">
                        No quantity entries found.
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
