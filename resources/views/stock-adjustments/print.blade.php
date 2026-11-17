<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustments List</title>
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
    <h1>Stock Adjustments List</h1>

    <table>
        <thead>
            <tr>
                @foreach($columns as $key => $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($adjustments as $adjustment)
                <tr>
                    @foreach($columns as $key => $label)
                        <td>
                            @switch($key)
                                @case('adjustment_date')
                                    {{ $adjustment->adjustment_date->format('d/m/Y H:i') }}
                                    @break
                                @case('type')
                                    {{ $adjustment->type->label() }}
                                    @break
                                @case('total_amount')
                                    {{ \App\Helpers\Money::format($adjustment->total_amount) }}
                                    @break
                                @case('reason')
                                    {{ $adjustment->reason ?: '—' }}
                                    @break
                                @case('creator')
                                    {{ $adjustment->creator?->name ?? '—' }}
                                    @break
                                @default
                                    {{ $adjustment->{$key} ?? '—' }}
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align: center; padding: 20px; color: #666;">
                        No stock adjustments found.
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
