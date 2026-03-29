<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Receipt - {{ $receiptNumber }}</title>
    <style>
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 11px;
            line-height: 1.45;
            background: #ffffff;
        }

        .sheet {
            border: 1px solid #d1d5db;
            background: #ffffff;
        }

        .header-table,
        .info-table,
        .line-table,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-band {
            padding: 16px 20px 14px;
            border-bottom: 5px solid #d72638;
            background: #ffffff;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-kicker {
            margin: 0 0 4px;
            color: #d72638;
            font-size: 10px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .document-title {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .document-copy {
            margin: 0;
            color: #6b7280;
            font-size: 10px;
        }

        .meta-table {
            margin-left: auto;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 2px 0 2px 10px;
            font-size: 10px;
        }

        .meta-label {
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .meta-value {
            color: #111827;
            font-weight: 700;
            text-align: right;
        }

        .divider {
            margin: 0 20px;
            border-top: 1px solid #e5e7eb;
        }

        .section {
            padding: 14px 20px;
        }

        .section-title {
            margin: 0 0 8px;
            color: #111827;
            font-size: 9px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            font-weight: 700;
            border-left: 4px solid #d72638;
            padding-left: 8px;
        }

        .info-table td {
            width: 50%;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
            background: #fafafa;
        }

        .field-label {
            display: block;
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .field-value {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
        }

        .field-copy {
            margin-top: 3px;
            color: #6b7280;
            font-size: 10px;
        }

        .line-table th,
        .line-table td {
            padding: 7px 8px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        .line-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .line-table .amount {
            text-align: right;
        }

        .item-name {
            font-weight: 700;
            color: #111827;
        }

        .item-copy {
            margin-top: 2px;
            color: #6b7280;
            font-size: 10px;
        }

        .summary-wrap {
            margin-left: auto;
            width: 280px;
        }

        .summary-table td {
            padding: 7px 8px;
            border: 1px solid #e5e7eb;
        }

        .summary-label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .summary-value {
            text-align: right;
            font-weight: 700;
            color: #111827;
        }

        .summary-total td {
            background: #f9fafb;
            font-size: 12px;
            font-weight: 800;
        }

        .note-box {
            margin-top: 12px;
            border: 1px solid #e5e7eb;
            background: #fafafa;
            padding: 10px 12px;
        }

        .note-label {
            display: block;
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .note-copy {
            color: #374151;
            font-size: 10px;
        }
    </style>
</head>

@php
    $logoData = '';
    $logoPath = public_path('logo.png');

    if (file_exists($logoPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
@endphp

<body>
    <div class="sheet">
        <div class="header-band">
            <table class="header-table">
                <tr>
                    <td>
                        @if ($logoData)
                            <img src="{{ $logoData }}" alt="JPRIME FITNESS Logo" style="width: 28px; height: 28px; display: block; margin-bottom: 6px;">
                        @endif
                        <h1 class="document-title">Sales Receipt</h1>
                        <p class="document-copy">POS receipt for inventory products, memberships, PT packages, and walk-in access.</p>
                    </td>
                    <td style="width: 230px;">
                        <table class="meta-table">
                            <tr>
                                <td class="meta-label">Receipt No.</td>
                                <td class="meta-value">{{ $receiptNumber }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Sold At</td>
                                <td class="meta-value">{{ $saleTransaction->sold_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Type</td>
                                <td class="meta-value">{{ str((string) $saleTransaction->type)->replace('_', ' ')->title() }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2 class="section-title">Receipt Details</h2>
            <table class="info-table">
                <tr>
                    <td>
                        <span class="field-label">Branch</span>
                        <div class="field-value">{{ $saleTransaction->branch?->name }}</div>
                        <div class="field-copy">{{ collect([$saleTransaction->branch?->city, $saleTransaction->branch?->province])->filter()->join(', ') }}</div>
                    </td>
                    <td>
                        <span class="field-label">Processed By</span>
                        <div class="field-value">{{ $saleTransaction->processedBy?->name ?? 'Former Staff' }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="field-label">Customer</span>
                        <div class="field-value">{{ $saleTransaction->customer_name ?: $saleTransaction->member?->name ?: 'Walk-in Customer' }}</div>
                        @if ($saleTransaction->member?->email || $saleTransaction->member?->phone)
                            <div class="field-copy">{{ collect([$saleTransaction->member?->email, $saleTransaction->member?->phone])->filter()->join(' · ') }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="field-label">Payment</span>
                        <div class="field-value">{{ \App\Models\SaleTransaction::paymentMethodLabel($saleTransaction->payment_method) }}</div>
                        @if ($payment['reference'])
                            <div class="field-copy">Reference: {{ $payment['reference'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="divider"></div>

        <div class="section">
            <h2 class="section-title">Items</h2>
            <table class="line-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="amount">Qty</th>
                        <th class="amount">Price</th>
                        <th class="amount">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineItems as $lineItem)
                        <tr>
                            <td>
                                <div class="item-name">{{ $lineItem['name'] }}</div>
                                @if ($lineItem['description'])
                                    <div class="item-copy">{{ $lineItem['description'] }}</div>
                                @endif
                            </td>
                            <td class="amount">
                                {{ number_format((float) $lineItem['quantity'], fmod((float) $lineItem['quantity'], 1.0) === 0.0 ? 0 : 2) }}
                                @if ($lineItem['unit'])
                                    {{ $lineItem['unit'] }}
                                @endif
                            </td>
                            <td class="amount">₱{{ number_format((float) $lineItem['unit_price'], 2) }}</td>
                            <td class="amount">₱{{ number_format((float) $lineItem['line_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="summary-wrap">
                <table class="summary-table">
                    <tr>
                        <td class="summary-label">Subtotal</td>
                        <td class="summary-value">₱{{ number_format((float) $saleTransaction->total, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="summary-label">Amount Received</td>
                        <td class="summary-value">₱{{ number_format((float) $payment['amount_received'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="summary-label">Change</td>
                        <td class="summary-value">₱{{ number_format((float) $payment['change_amount'], 2) }}</td>
                    </tr>
                    <tr class="summary-total">
                        <td>Total</td>
                        <td class="summary-value">₱{{ number_format((float) $saleTransaction->total, 2) }}</td>
                    </tr>
                </table>
            </div>

            @if (data_get($saleTransaction->details, 'notes'))
                <div class="note-box">
                    <span class="note-label">Notes</span>
                    <div class="note-copy">{{ data_get($saleTransaction->details, 'notes') }}</div>
                </div>
            @endif
        </div>
    </div>
</body>

</html>
