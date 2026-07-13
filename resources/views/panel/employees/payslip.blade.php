<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee->name }}</title>
    <style>
        * {
            font-family: 'DejaVu Sans', sans-serif;
        }

        body {
            margin: 0;
            color: #111827;
            font-size: 10px;
            line-height: 1.3;
            background: #ffffff;
        }

        .sheet {
            border: 1px solid #d1d5db;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-band {
            border-bottom: 4px solid #d72638;
            padding: 10px 14px 8px;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand-table {
            width: auto;
        }

        .brand-table td {
            vertical-align: middle;
        }

        .brand-mark {
            width: 26px;
            height: 26px;
            display: block;
            margin-right: 8px;
        }

        .header-kicker {
            margin: 0;
            color: #d72638;
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .brand-name {
            margin: 1px 0 0;
            font-size: 13px;
            font-weight: bold;
        }

        .document-title {
            margin: 0 0 3px;
            font-size: 17px;
            font-weight: bold;
            text-align: right;
        }

        .meta-table {
            width: auto;
            margin-left: auto;
        }

        .meta-table td {
            padding: 1px 0 1px 10px;
            font-size: 8.5px;
        }

        .meta-label {
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: bold;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: bold;
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

        .content {
            padding: 10px 14px;
        }

        .section-title {
            margin: 0 0 5px;
            font-size: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: bold;
            border-left: 3px solid #d72638;
            padding-left: 6px;
        }

        .spacer {
            height: 8px;
        }

        .info-table td {
            padding: 5px 7px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .field-label {
            display: block;
            margin-bottom: 2px;
            color: #6b7280;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: bold;
        }

        .field-value {
            font-size: 10px;
            font-weight: bold;
        }

        .summary-table td {
            width: 25%;
            padding: 6px 7px;
            border: 1px solid #e5e7eb;
            background: #fbfbfc;
            vertical-align: top;
        }

        .summary-label {
            color: #6b7280;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: bold;
        }

        .summary-value {
            margin-top: 3px;
            font-size: 13px;
            font-weight: bold;
        }

        .summary-value--success {
            color: #15803d;
        }

        .summary-value--warning {
            color: #b45309;
        }

        .box {
            border: 1px solid #e5e7eb;
            padding: 7px 9px;
        }

        .box-heading {
            margin: 0 0 5px;
            font-size: 9.5px;
            font-weight: bold;
        }

        .breakdown-table td {
            padding: 3px 0;
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
            font-weight: bold;
        }

        .amount-positive {
            color: #15803d;
        }

        .amount-negative {
            color: #d72638;
        }

        .notes-copy {
            color: #4b5563;
            font-size: 9px;
            line-height: 1.3;
        }

        .meta-note {
            margin-top: 4px;
            font-size: 9px;
        }

        .payout-table th,
        .payout-table td {
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
            text-align: left;
            font-size: 9px;
        }

        .payout-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .payout-table th:last-child,
        .payout-table td:last-child {
            text-align: right;
        }

        .signature-table td {
            width: 33.33%;
            padding-top: 16px;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            padding-top: 4px;
            font-size: 8.5px;
        }

        .footer-note {
            margin-top: 8px;
            color: #6b7280;
            font-size: 7.5px;
            text-align: center;
        }
    </style>
</head>

@php
    $totalPaid = $payroll->totalPaid();
    $remainingBalance = $payroll->remainingBalance();
    $totalEarnings = $payroll->totalEarnings();
    $hasAttendanceBreakdownSnapshot = $payroll->hasAttendanceBreakdownSnapshot();
    $showOverworkBreakdown = $hasAttendanceBreakdownSnapshot
        && (float) $payroll->overwork_pay_amount > 0;
    $manualGrossAdjustmentAmount = $payroll->manualGrossAdjustmentAmount();
    $locationName = $businessProfile->name ?? null;
    $roleNames = $employee->roles->pluck('name')->map(fn($role) => ucfirst($role))->join(', ');
    $statusClass = 'status-pill--' . $payroll->status;
    $methodLabels = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'online_payment' => 'Online Payment',
    ];
    $employeeContributionPrograms = collect($payroll->employee_contributions ?? [])
        ->filter(fn(array $program): bool => (float) ($program['total'] ?? 0) > 0);
    $employerContributionPrograms = collect($payroll->employer_contributions ?? [])
        ->filter(fn(array $program): bool => (float) ($program['total'] ?? 0) > 0);
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
                    <td style="width: 58%;">
                        <table class="brand-table">
                            <tr>
                                <td style="width: 34px;">
                                    @if ($logoData)
                                        <img src="{{ $logoData }}" alt="Logo" class="brand-mark">
                                    @endif
                                </td>
                                <td>
                                    <div class="header-kicker">Official Payroll Document</div>
                                    <p class="brand-name">{{ $locationName ?: 'Payslip' }}</p>
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
            <div class="section-title">Employee Details</div>
            <table class="info-table">
                <tr>
                    <td style="width: 30%;">
                        <span class="field-label">Employee Name</span>
                        <span class="field-value">{{ $employee->name }}</span>
                    </td>
                    <td style="width: 20%;">
                        <span class="field-label">Role</span>
                        <span class="field-value">{{ $roleNames ?: 'Employee' }}</span>
                    </td>
                    <td style="width: 30%;">
                        <span class="field-label">Email Address</span>
                        <span class="field-value">{{ $employee->email }}</span>
                    </td>
                    <td style="width: 20%;">
                        <span class="field-label">Phone</span>
                        <span class="field-value">{{ $employee->phone ?: '-' }}</span>
                    </td>
                </tr>
            </table>

            <div class="spacer"></div>

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

            <div class="spacer"></div>

            <table>
                <tr>
                    <td style="width: 54%; padding-right: 6px; vertical-align: top;">
                        <div class="box">
                            <div class="box-heading">Compensation Breakdown</div>
                            <table class="breakdown-table">
                                @if ($hasAttendanceBreakdownSnapshot)
                                    <tr>
                                        <td class="breakdown-label">Regular pay</td>
                                        <td class="breakdown-amount">
                                            {{ (float) $payroll->regular_pay_amount > 0 ? 'PHP ' . number_format((float) $payroll->regular_pay_amount, 2) . ' (' . number_format((float) $payroll->regular_hours, 2) . ' hrs)' : '-' }}
                                        </td>
                                    </tr>
                                    @if ($showOverworkBreakdown)
                                        <tr>
                                            <td class="breakdown-label">Overwork pay</td>
                                            <td class="breakdown-amount amount-positive">
                                                + PHP {{ number_format((float) $payroll->overwork_pay_amount, 2) }}
                                                ({{ number_format((float) $payroll->overwork_hours, 2) }} hrs)
                                            </td>
                                        </tr>
                                    @endif
                                    @if ($manualGrossAdjustmentAmount !== null && abs($manualGrossAdjustmentAmount) >= 0.01)
                                        <tr>
                                            <td class="breakdown-label">Manual gross adjustment</td>
                                            <td
                                                class="breakdown-amount {{ $manualGrossAdjustmentAmount > 0 ? 'amount-positive' : 'amount-negative' }}">
                                                {{ $manualGrossAdjustmentAmount > 0 ? '+ PHP ' : '- PHP ' }}{{ number_format(abs($manualGrossAdjustmentAmount), 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                                <tr>
                                    <td class="breakdown-label">Gross amount</td>
                                    <td class="breakdown-amount">PHP
                                        {{ number_format((float) $payroll->gross_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="breakdown-label">Withholding tax</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->withholding_tax > 0 ? 'amount-negative' : '' }}">
                                        {{ (float) $payroll->withholding_tax > 0 ? '- PHP ' . number_format((float) $payroll->withholding_tax, 2) : '-' }}
                                    </td>
                                </tr>
                                @foreach ($employeeContributionPrograms as $programKey => $program)
                                    @php
                                        $programLabel = $program['label'] ?? str($programKey)->replace('_', ' ')->title()->toString();
                                        $programLines = collect($program['lines'] ?? [])
                                            ->filter(fn(array $line): bool => (float) ($line['amount'] ?? 0) > 0);
                                    @endphp
                                    @forelse ($programLines as $lineKey => $line)
                                        <tr>
                                            <td class="breakdown-label">
                                                {{ $programLabel }} - {{ $line['label'] ?? str($lineKey)->replace('_', ' ')->title()->toString() }}
                                            </td>
                                            <td class="breakdown-amount amount-negative">
                                                - PHP {{ number_format((float) ($line['amount'] ?? 0), 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="breakdown-label">{{ $programLabel }}</td>
                                            <td class="breakdown-amount amount-negative">
                                                - PHP {{ number_format((float) ($program['total'] ?? 0), 2) }}
                                            </td>
                                        </tr>
                                    @endforelse
                                @endforeach
                                @if ($employeeContributionPrograms->isNotEmpty())
                                    <tr>
                                        <td class="breakdown-label"><strong>Employee government contributions</strong></td>
                                        <td class="breakdown-amount amount-negative">- PHP
                                            {{ number_format($payroll->employeeContributionsTotal(), 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="breakdown-label">Other deductions</td>
                                    <td
                                        class="breakdown-amount {{ (float) $payroll->manual_deductions > 0 ? 'amount-negative' : '' }}">
                                        {{ (float) $payroll->manual_deductions > 0 ? '- PHP ' . number_format((float) $payroll->manual_deductions, 2) : '-' }}
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
                    <td style="width: 46%; padding-left: 6px; vertical-align: top;">
                        <div class="box">
                            <div class="box-heading">Notes &amp; Approval</div>
                            <div class="notes-copy">{{ $payroll->notes ?: 'No additional payroll notes recorded.' }}
                            </div>
                            <div class="meta-note"><strong>Prepared By:</strong>
                                {{ $payroll->generatedBy?->name ?: 'System' }}</div>
                            <div class="meta-note"><strong>Approved By:</strong>
                                {{ $payroll->approvedBy?->name ?: 'Pending approval' }}</div>
                            <div class="meta-note"><strong>Approved At:</strong>
                                {{ $payroll->approved_at?->format('M d, Y h:i A') ?: 'Pending approval' }}</div>
                        </div>
                        <div class="spacer"></div>
                        <div class="box">
                            <div class="box-heading">Employer Contributions</div>
                            <div class="notes-copy" style="margin-bottom: 5px;">Reference only. These employer-share statutory amounts do not reduce employee net pay.</div>
                            <table class="breakdown-table">
                                @forelse ($employerContributionPrograms as $programKey => $program)
                                    @php
                                        $programLabel = $program['label'] ?? str($programKey)->replace('_', ' ')->title()->toString();
                                    @endphp
                                    <tr>
                                        <td class="breakdown-label">{{ $programLabel }}</td>
                                        <td class="breakdown-amount">
                                            PHP {{ number_format((float) ($program['total'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="breakdown-label">No employer contribution snapshot was stored for this payroll.</td>
                                        <td class="breakdown-amount">-</td>
                                    </tr>
                                @endforelse
                                @if ($employerContributionPrograms->isNotEmpty())
                                    <tr>
                                        <td class="breakdown-label"><strong>Total employer contributions</strong></td>
                                        <td class="breakdown-amount">PHP
                                            {{ number_format($payroll->employerContributionsTotal(), 2) }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="spacer"></div>

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

            <div class="section-title" style="margin-top: 10px;">Acknowledgement</div>
            <table class="signature-table">
                <tr>
                    <td style="padding-right: 14px;">
                        <div class="signature-line">
                            <strong>{{ $employee->name }}</strong><br>
                            Employee Signature
                        </div>
                    </td>
                    <td style="padding: 16px 7px 0;">
                        <div class="signature-line">
                            <strong>{{ $payroll->approvedBy?->name ?: 'Pending approval' }}</strong><br>
                            Approved By
                        </div>
                    </td>
                    <td style="padding-left: 14px;">
                        <div class="signature-line">
                            <strong>{{ $payroll->generatedBy?->name ?: 'Payroll Officer' }}</strong><br>
                            Prepared By
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer-note">
                This payslip is generated from the {{ $locationName ?: 'JPrime Fitness' }} panel and is intended for
                payroll documentation and employee payout reference.
            </div>
        </div>
    </div>
</body>

</html>
