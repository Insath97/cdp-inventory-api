<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase Order - {{ config('app.name') }}</title>
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
            background: #111111;
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
            color: #cccccc;
        }

        .body {
            padding: 32px 28px;
        }

        .body h2 {
            font-size: 16px;
            margin: 0 0 6px;
        }

        .body p {
            font-size: 14px;
            line-height: 1.6;
            color: #444444;
            margin: 0 0 20px;
        }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table.details td {
            padding: 10px 0;
            font-size: 14px;
            border-bottom: 1px solid #efefef;
            vertical-align: top;
        }

        table.details td.label {
            color: #777777;
            width: 45%;
        }

        table.details td.value {
            color: #111111;
            font-weight: 600;
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .total-row td {
            font-size: 15px;
            font-weight: 800;
            border-bottom: none;
            padding-top: 14px;
        }

        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999999;
            border-top: 1px solid #efefef;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
            <p>New Purchase Order Created</p>
        </div>

        <div class="body">
            <h2>Purchase Order {{ $purchaseOrder->po_number ?? $purchaseOrder->id }}</h2>
            <p>A new purchase order has been created and is awaiting approval. The details are below.</p>

            <table class="details">
                <tr>
                    <td class="label">PO Number</td>
                    <td class="value">{{ $purchaseOrder->po_number ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td class="value"><span class="badge">{{ ucfirst($purchaseOrder->status ?? 'pending') }}</span></td>
                </tr>
                <tr>
                    <td class="label">Supplier</td>
                    <td class="value">{{ optional($purchaseOrder->supplier)->supplier_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Branch</td>
                    <td class="value">{{ optional($purchaseOrder->branch)->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Created By</td>
                    <td class="value">{{ optional($purchaseOrder->creator)->name ?? 'System' }}</td>
                </tr>
                <tr>
                    <td class="label">Order Date</td>
                    <td class="value">{{ optional($purchaseOrder->order_date)->format('Y-m-d') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Expected Delivery</td>
                    <td class="value">{{ optional($purchaseOrder->expected_delivery_date)->format('Y-m-d') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Items</td>
                    <td class="value">{{ $purchaseOrder->items->count() }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label">Total Amount</td>
                    <td class="value">{{ number_format((float) ($purchaseOrder->total_amount ?? 0), 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            This is an automated message from {{ config('app.name') }}. Please do not reply.
        </div>
    </div>
</body>

</html>
