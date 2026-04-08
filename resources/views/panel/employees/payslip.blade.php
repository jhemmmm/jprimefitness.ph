<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee->name }}</title>
    <style>
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.45;
            background: #ffffff;
        }

        .sheet {
            padding: 0;
            border: 1px solid #d1d5db;
        }

        .header-table,
        .info-table,
        .summary-table,
        .breakdown-table,
        .payout-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-band {
            background: #ffffff;
            border-bottom: 6px solid #d72638;
            padding: 16px 20px 14px;
        }

        .header-left {
            color: #111827;
        }

        .brand-table {
            border-collapse: collapse;
        }

        .brand-table td {
            vertical-align: middle;
        }

        .brand-mark {
            width: 28px;
            height: 28px;
            display: block;
            margin-right: 8px;
        }

        .header-kicker {
            margin: 2px 0 2px;
            color: #d72638;
            font-size: 10px;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .brand-copy {
            margin: 0;
            color: #6b7280;
            max-width: 300px;
            font-size: 11px;
            line-height: 1.35;
        }

        .document-title {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            text-align: right;
            color: #111827;
        }

        .meta-table {
            margin-left: auto;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 2px 0 2px 12px;
            font-size: 10px;
            color: #111827;
        }

        .meta-label {
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            background: #ecfdf5;
            color: #166534;
        }

        .status-pill--draft,
        .status-pill--canceled {
            background: #f3f4f6;
            color: #374151;
        }

        .status-pill--partially_paid {
            background: #fff7ed;
            color: #b45309;
        }

        .divider {
            margin: 0 20px;
            border-top: 1px solid #e5e7eb;
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
            padding: 7px 9px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .field-label {
            display: block;
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .field-value {
            font-size: 12px;
            font-weight: 700;
        }

        .summary-table td {
            width: 25%;
            padding: 9px;
            border: 1px solid #e5e7eb;
            background: #fbfbfc;
            vertical-align: top;
        }

        .summary-label {
            color: #6b7280;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .summary-value {
            margin-top: 5px;
            font-size: 17px;
            font-weight: 800;
        }

        .summary-value--success {
            color: #15803d;
        }

        .summary-value--warning {
            color: #b45309;
        }

        .box {
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
            background: #ffffff;
        }

        .box-heading {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 800;
        }

        .breakdown-table td {
            padding: 5px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .breakdown-table tr:last-child td {
            border-bottom: 0;
        }

        .breakdown-label {
            color: #4b5563;
        }

        .breakdown-amount {
            text-align: right;
            font-weight: 700;
        }

        .amount-positive {
            color: #15803d;
        }

        .amount-negative {
            color: #d72638;
        }

        .notes-copy {
            color: #4b5563;
            min-height: 54px;
            font-size: 11px;
            line-height: 1.35;
        }

        .meta-note {
            margin-top: 7px;
            font-size: 11px;
        }

        .payout-table th,
        .payout-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            text-align: left;
            font-size: 11px;
        }

        .payout-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .payout-table th:last-child,
        .payout-table td:last-child {
            text-align: right;
        }

        .signature-table td {
            width: 33.33%;
            padding-top: 18px;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            padding-top: 6px;
            font-size: 10px;
        }

        .footer-note {
            margin-top: 12px;
            color: #6b7280;
            font-size: 9px;
            text-align: center;
        }

        .content {
            padding: 14px 20px 14px;
        }
    </style>
</head>

@php
    $totalPaid = $payroll->totalPaid();
    $remainingBalance = $payroll->remainingBalance();
    $totalEarnings = $payroll->totalEarnings();
    $locationName = $businessProfile->name ?? null;
    $roleNames = $employee->roles->pluck('name')->map(fn($role) => ucfirst($role))->join(', ');
    $statusClass = 'status-pill--' . $payroll->status;
    $methodLabels = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'online_payment' => 'Online Payment',
    ];
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
                    <td style="width: 58%;" class="header-left">
                        <table class="brand-table">
                            <tr>
                                <td style="width: 36px;">
                                    @if ($logoData)
                                        <img src="{{ $logoData }}" alt="JPRIME FITNESS Logo" class="brand-mark">
                                    @endif
                                </td>
                                <td>
                                    <div class="header-kicker">Official Payroll Document</div>
                                    <p class="brand-copy">Employee compensation statement with payroll totals, payouts,
                                        deductions, and approval trail.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 42%;">
                        <h1 class="document-title">Payslip</h1>
                        <table class="meta-table">
                            <tr>
                                <td class="meta-label">Payroll ID</td>
                                <td><strong>#{{ $payroll->id }}</strong></td>
                            </tr>
                            <tr>
                                <td class="meta-label">Pay Period</td>
                                <td><strong>{{ $payroll->period_start->format('M d, Y') }} -
                                        {{ $payroll->period_end->format('M d, Y') }}</strong></td>
                            </tr>
                            <tr>
                                <td class="meta-label">Generated</td>
                                <td><strong>{{ $payroll->created_at->format('M d, Y h:i A') }}</strong></td>
                            </tr>
                            <tr>
                                <td class="meta-label">Status</td>
                                <td><span
                                        class="status-pill {{ $statusClass }}">{{ str_replace('_', ' ', ucfirst($payroll->status)) }}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content">
            <div class="divider"></div>

            <div class="section-title">Employee Details</div>
            <table class="info-table">
                <tr>
                    <td>
                        <span class="field-label">Employee Name</span>
                        <span class="field-value">{{ $employee->name }}</span>
                    </td>
                    <td>
                        <span class="field-label">Location</span>
                        <span class="field-value">{{ $locationName ?: '-' }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="field-label">Email Address</span>
                        <span class="field-value">{{ $employee->email }}</span>
                    </td>
                    <td>
                        <span class="field-label">Role</span>
                        <span class="field-value">{{ $roleNames ?: 'Employee' }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="field-label">Phone</span>
                        <span class="field-value">{{ $employee->phone ?: '-' }}</span>
                    </td>
                </tr>
            </table>

            <div style="height: 12px;"></div>

            <div class="section-title">Payroll Summary</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="summary-label">Total Earnings</div>
                        <div class="summary-value">PHP {{ number_format($totalEarnings, 2) }}</div>
                    </td>
                    <td>
                        <div class="summary-label">Net Pay</div>
                        <div class="summary-value summary-value--success">PHP
                            {{ number_format((float) $payroll->net_amount, 2) }}</div>
                    </td>
                    <td>
                        <div class="summary-label">Paid To Date</div>
                        <div class="summary-value">PHP {{ number_format($totalPaid, 2) }}</div>
                    </td>
                    <td>
                        <div class="summary-label">Outstanding</div>
                        <div
                            class="summary-value {{ $remainingBalance > 0 ? 'summary-value--warning' : 'summary-value--success' }}">
                            {{ $remainingBalance > 0 ? 'PHP ' . number_format($remainingBalance, 2) : 'Settled' }}
                        </div>
                    </td>
                </tr>
            </table>

            <div style="height: 12px;"></div>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 54%; padding-right: 8px; vertical-align: top;">
                        <div class="box">
                            <div class="box-heading">Compensation Breakdown</div>
                            <table class="breakdown-table">
                                <tr>
                                    <td class="breakdown-label">Gross amount</td>
                                    <td class="breakdown-amount">PHP
                                        {{ number_format((float) $payroll->gross_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">Bonus</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->bonus > 0 ? 'amount-positive' : '' }}">
                                        {{ (float) $payroll->bonus > 0 ? '+ PHP ' . number_format((float) $payroll->bonus, 2) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">PT commission</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->pt_commission_amount > 0 ? 'amount-positive' : '' }}">
                                        {{ (float) $payroll->pt_commission_amount > 0 ? '+ PHP ' . number_format((float) $payroll->pt_commission_amount, 2) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">Membership commission</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->membership_commission_amount > 0 ? 'amount-positive' : '' }}">
                                        {{ (float) $payroll->membership_commission_amount > 0 ? '+ PHP ' . number_format((float) $payroll->membership_commission_amount, 2) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">Income tax</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->income_tax > 0 ? 'amount-negative' : '' }}">
                                        {{ (float) $payroll->income_tax > 0 ? '- PHP ' . number_format((float) $payroll->income_tax, 2) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">Other deductions</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->manual_deductions > 0 ? 'amount-negative' : '' }}">
                                        {{ (float) $payroll->manual_deductions > 0 ? '- PHP ' . number_format((float) $payroll->manual_deductions, 2) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">Cash advance deduction</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->cash_advance_deduction > 0 ? 'amount-negative' : '' }}">
                                        {{ (float) $payroll->cash_advance_deduction > 0 ? '- PHP ' . number_format((float) $payroll->cash_advance_deduction, 2) : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label"><strong>Net pay</strong></td>
                                    <td class="breakdown-amount amount-positive">PHP
                                        {{ number_format((float) $payroll->net_amount, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                    <td style="width: 46%; padding-left: 8px; vertical-align: top;">
                        <div class="box">
                            <div class="box-heading">Notes & Approval</div>
                            <div class="notes-copy">{{ $payroll->notes ?: 'No additional payroll notes recorded.' }}
                            </div>
                            <div class="meta-note"><strong>Prepared By:</strong>
                                {{ $payroll->generatedBy?->name ?: 'System' }}</div>
                            <div class="meta-note"><strong>Approved By:</strong>
                                {{ $payroll->approvedBy?->name ?: 'Pending approval' }}</div>
                            <div class="meta-note"><strong>Approved At:</strong>
                                {{ $payroll->approved_at?->format('M d, Y h:i A') ?: 'Pending approval' }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="height: 12px;"></div>

            <div class="section-title">Payout History</div>
            <table class="payout-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Released By</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payroll->payouts as $payout)
                        <tr>
                            <td>{{ $payout->paid_at?->format('M d, Y h:i A') ?: '-' }}</td>
                            <td>{{ $methodLabels[$payout->method] ?? ucfirst(str_replace('_', ' ', $payout->method)) }}
                            </td>
                            <td>{{ $payout->reference_number ?: '-' }}</td>
                            <td>{{ $payout->releasedBy?->name ?: '-' }}</td>
                            <td>PHP {{ number_format((float) $payout->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #6b7280;">No payout has been recorded
                                for this payroll yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div style="height: 18px;"></div>

            <div class="section-title">Acknowledgement</div>
            <table class="signature-table">
                <tr>
                    <td style="padding-right: 16px;">
                        <div class="signature-line">
                            <strong>{{ $employee->name }}</strong><br>
                            Employee Signature
                        </div>
                    </td>
                    <td style="padding: 18px 8px 0;">
                        <div class="signature-line">
                            <strong>{{ $payroll->approvedBy?->name ?: 'Pending approval' }}</strong><br>
                            Approved By
                        </div>
                    </td>
                    <td style="padding-left: 16px;">
                        <div class="signature-line">
                            <strong>{{ $payroll->generatedBy?->name ?: 'Payroll Officer' }}</strong><br>
                            Prepared By
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-note">
                This payslip is generated from the JPRIME FITNESS panel and is intended for payroll documentation and
                employee payout reference.
            </div>
        </div>
    </div>
</body>

</html>
