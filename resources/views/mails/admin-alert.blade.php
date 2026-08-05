<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #fafafa;
            color: #111111;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e5e5e5;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .header {
            background: #2563eb;
            padding: 32px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.05em;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 13px;
            color: #e0f2fe;
        }
        .body {
            padding: 32px 28px;
        }
        .body h2 {
            font-size: 16px;
            margin: 0 0 6px;
            color: #1e293b;
        }
        .body p.intro {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin: 0 0 20px;
        }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        table.details td {
            padding: 10px 0;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        table.details td.label {
            color: #64748b;
            width: 45%;
        }
        table.details td.value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e40af;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge.warning {
            background: #fee2e2;
            color: #991b1b;
        }
        .items-header {
            margin-top: 24px;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.items-table th {
            text-align: left;
            font-size: 12px;
            color: #475569;
            font-weight: 700;
            padding: 8px 4px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.items-table td {
            font-size: 13px;
            color: #334155;
            padding: 10px 4px;
            border-bottom: 1px solid #f1f5f9;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
            <p>{{ $title }}</p>
        </div>

        <div class="body">
            <h2>System Admin Alert</h2>
            <p class="intro">{{ $description }}</p>

            <table class="details">
                @foreach($details as $key => $val)
                    <tr>
                        <td class="label">{{ $key }}</td>
                        <td class="value">
                            @if(strtolower($key) === 'status')
                                <span class="badge {{ in_array(strtolower($val), ['failed', 'cancelled', 'rejected', 'stale', 'expired', 'warning', 'active_warning', 'damaged'], true) ? 'warning' : '' }}">
                                    {{ $val }}
                                </span>
                            @else
                                {{ $val }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>

            @if(!empty($items))
                <div class="items-header">Line Items ({{ count($items) }})</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th style="text-align: right;">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ $item['qty'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="footer">
            This is an automated administrative notification from {{ config('app.name') }}. Please do not reply.
        </div>
    </div>
</body>
</html>
