<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Payout;
use App\Models\Payroll;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReportsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return view('panel.reports.payroll');
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return response()->json($this->reportPayload($request));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $report = $this->reportPayload($request);
        $dateSuffix = now()->format('Ymd_His');
        $fileName = "payroll-report-{$dateSuffix}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Payroll Reports']);
            fputcsv($handle, ['Location', $report['scope']['location']['name'] ?? '-']);
            fputcsv($handle, ['Period End From', $report['filters']['date_from'] ?: '-']);
            fputcsv($handle, ['Period End To', $report['filters']['date_to'] ?: '-']);
            fputcsv($handle, ['Status', $report['filters']['status_label'] ?: 'All Active Statuses']);
            fputcsv($handle, ['Pay Frequency', $report['filters']['pay_frequency_label'] ?: 'All Frequencies']);
            fputcsv($handle, ['Payout Scope', 'Current payout progress for payrolls ending within the selected period']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Payroll Runs', $report['summary']['payroll_count']]);
            fputcsv($handle, ['Gross Payroll', $report['summary']['gross_payroll']]);
            fputcsv($handle, ['Bonuses', $report['summary']['total_bonus']]);
            fputcsv($handle, ['Income Tax', $report['summary']['income_tax']]);
            fputcsv($handle, ['Employee Government Contributions', $report['summary']['employee_government_contributions']]);
            fputcsv($handle, ['Employer Government Contributions', $report['summary']['employer_government_contributions']]);
            fputcsv($handle, ['Total Deductions', $report['summary']['total_deductions']]);
            fputcsv($handle, ['Net Payroll', $report['summary']['net_payroll']]);
            fputcsv($handle, ['Paid Out To Date', $report['summary']['total_paid']]);
            fputcsv($handle, ['Outstanding Balance To Date', $report['summary']['outstanding_balance']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Status Breakdown']);
            fputcsv($handle, ['Status', 'Payroll Runs', 'Net Payroll']);
            foreach ($report['status_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['payroll_count'], $row['net_payroll']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Pay Frequency Breakdown']);
            fputcsv($handle, ['Frequency', 'Payroll Runs', 'Net Payroll']);
            foreach ($report['pay_frequency_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['payroll_count'], $row['net_payroll']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Location Totals']);
            fputcsv($handle, ['Location', 'Payroll Runs', 'Net Payroll', 'Paid Out', 'Outstanding']);
            foreach ($report['location_breakdown'] as $row) {
                fputcsv($handle, [
                    $row['location_name'],
                    $row['payroll_count'],
                    $row['net_payroll'],
                    $row['total_paid'],
                    $row['outstanding_balance'],
                ]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Payout Methods for Selected Payrolls']);
            fputcsv($handle, ['Method', 'Payouts', 'Total Paid']);
            foreach ($report['payout_method_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['payout_count'], $row['total_paid']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Payroll Trend']);
            fputcsv($handle, ['Period End', 'Payroll Runs', 'Net Payroll']);
            foreach ($report['payroll_trend'] as $row) {
                fputcsv($handle, [$row['period_end'], $row['payroll_count'], $row['net_payroll']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent Payrolls']);
            fputcsv($handle, ['Employee', 'Location', 'Period', 'Pay Frequency', 'Status', 'Gross', 'Net', 'Paid Out', 'Outstanding', 'Approved By']);
            foreach ($report['recent_payrolls'] as $row) {
                fputcsv($handle, [
                    $row['employee_name'],
                    $row['location_name'],
                    $row['period_label'],
                    $row['pay_frequency_label'],
                    $row['status_label'],
                    $row['gross_amount'],
                    $row['net_amount'],
                    $row['total_paid'],
                    $row['outstanding_balance'],
                    $row['approved_by_name'],
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(Request $request): array
    {
        $data = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    Payroll::STATUS_DRAFT,
                    Payroll::STATUS_APPROVED,
                    Payroll::STATUS_PARTIALLY_PAID,
                    Payroll::STATUS_PAID,
                    Payroll::STATUS_CANCELED,
                ]),
            ],
            'pay_frequency' => [
                'nullable',
                Rule::in([
                    'monthly',
                    'semi_monthly',
                ]),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $location = BusinessProfile::current()->locationSummary();

        $payrollQuery = $this->payrollQuery($data);
        $payoutQuery = $this->payoutQuery($data);

        $grossPayroll = round((float) (clone $payrollQuery)->sum('gross_amount'), 2);
        $totalBonus = round((float) (clone $payrollQuery)->sum('bonus'), 2);
        $incomeTax = round((float) (clone $payrollQuery)->sum('income_tax'), 2);
        $manualDeductions = round((float) (clone $payrollQuery)->sum('manual_deductions'), 2);
        $payrollContributionSnapshots = (clone $payrollQuery)
            ->select([
                'id',
                'employee_contributions',
                'employer_contributions',
            ])
            ->get();
        $employeeGovernmentContributions = round(
            $payrollContributionSnapshots->sum(fn (Payroll $payroll): float => $payroll->employeeContributionsTotal()),
            2
        );
        $employerGovernmentContributions = round(
            $payrollContributionSnapshots->sum(fn (Payroll $payroll): float => $payroll->employerContributionsTotal()),
            2
        );
        $totalDeductions = round(
            $incomeTax + $manualDeductions + $employeeGovernmentContributions,
            2
        );
        $netPayroll = round((float) (clone $payrollQuery)->sum('net_amount'), 2);
        $totalPaid = round((float) (clone $payoutQuery)->sum('payouts.amount'), 2);
        $payrollCount = (int) (clone $payrollQuery)->count();
        $outstandingBalance = round(max(0, $netPayroll - $totalPaid), 2);

        return [
            'scope' => [
                'location' => [
                    'id' => $location['id'],
                    'name' => $location['name'],
                ],
            ],
            'filters' => [
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'status' => $data['status'] ?? null,
                'status_label' => ($data['status'] ?? null) ? $this->statusLabel($data['status']) : null,
                'pay_frequency' => $data['pay_frequency'] ?? null,
                'pay_frequency_label' => ($data['pay_frequency'] ?? null) ? $this->payFrequencyLabel($data['pay_frequency']) : null,
            ],
            'summary' => [
                'payroll_count' => $payrollCount,
                'gross_payroll' => $grossPayroll,
                'total_bonus' => $totalBonus,
                'income_tax' => $incomeTax,
                'employee_government_contributions' => $employeeGovernmentContributions,
                'employer_government_contributions' => $employerGovernmentContributions,
                'total_deductions' => $totalDeductions,
                'net_payroll' => $netPayroll,
                'total_paid' => $totalPaid,
                'outstanding_balance' => $outstandingBalance,
            ],
            'status_breakdown' => $this->statusBreakdown(clone $payrollQuery),
            'pay_frequency_breakdown' => $this->payFrequencyBreakdown(clone $payrollQuery),
            'location_breakdown' => [[
                'location_id' => $location['id'],
                'location_name' => $location['name'],
                'payroll_count' => $payrollCount,
                'net_payroll' => $netPayroll,
                'total_paid' => $totalPaid,
                'outstanding_balance' => $outstandingBalance,
            ]],
            'payout_method_breakdown' => $this->payoutMethodBreakdown(clone $payoutQuery),
            'payroll_trend' => $this->payrollTrend(clone $payrollQuery),
            'recent_payrolls' => $this->recentPayrolls(clone $payrollQuery, $location['name']),
        ];
    }

    private function payrollQuery(array $filters): Builder
    {
        return Payroll::query()
            ->when($filters['status'] ?? null, fn ($query) => $query->where('status', $filters['status']), fn ($query) => $query->where('status', '!=', Payroll::STATUS_CANCELED))
            ->when($filters['pay_frequency'] ?? null, fn ($query) => $query->where('pay_frequency', $filters['pay_frequency']))
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('period_end', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('period_end', '<=', $filters['date_to']));
    }

    private function payoutQuery(array $filters): Builder
    {
        return Payout::query()
            ->join('payrolls', 'payrolls.id', '=', 'payouts.payroll_id')
            ->when($filters['status'] ?? null, fn ($query) => $query->where('payrolls.status', $filters['status']), fn ($query) => $query->where('payrolls.status', '!=', Payroll::STATUS_CANCELED))
            ->when($filters['pay_frequency'] ?? null, fn ($query) => $query->where('payrolls.pay_frequency', $filters['pay_frequency']))
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('payrolls.period_end', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('payrolls.period_end', '<=', $filters['date_to']));
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function statusBreakdown(Builder $query): array
    {
        $rows = collect((clone $query)
            ->select('status')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as net_payroll')
            ->groupBy('status')
            ->get())
            ->keyBy('status');

        return collect([
            Payroll::STATUS_DRAFT,
            Payroll::STATUS_APPROVED,
            Payroll::STATUS_PARTIALLY_PAID,
            Payroll::STATUS_PAID,
            Payroll::STATUS_CANCELED,
        ])->map(function (string $status) use ($rows): array {
            $row = $rows->get($status);

            return [
                'status' => $status,
                'label' => $this->statusLabel($status),
                'payroll_count' => (int) ($row->payroll_count ?? 0),
                'net_payroll' => round((float) ($row->net_payroll ?? 0), 2),
            ];
        })->filter(fn (array $row) => $row['payroll_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function payFrequencyBreakdown(Builder $query): array
    {
        $rows = collect((clone $query)
            ->select('pay_frequency')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as net_payroll')
            ->groupBy('pay_frequency')
            ->get())
            ->keyBy('pay_frequency');

        return collect(['semi_monthly', 'monthly'])
            ->map(function (string $payFrequency) use ($rows): array {
                $row = $rows->get($payFrequency);

                return [
                    'pay_frequency' => $payFrequency,
                    'label' => $this->payFrequencyLabel($payFrequency),
                    'payroll_count' => (int) ($row->payroll_count ?? 0),
                    'net_payroll' => round((float) ($row->net_payroll ?? 0), 2),
                ];
            })->filter(fn (array $row) => $row['payroll_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function payoutMethodBreakdown(Builder $query): array
    {
        return collect((clone $query)
            ->select('payouts.method')
            ->selectRaw('COUNT(*) as payout_count')
            ->selectRaw('COALESCE(SUM(payouts.amount), 0) as total_paid')
            ->groupBy('payouts.method')
            ->orderByDesc('total_paid')
            ->get())
            ->map(fn ($row) => [
                'method' => $row->method,
                'label' => $this->payoutMethodLabel($row->method),
                'payout_count' => (int) $row->payout_count,
                'total_paid' => round((float) $row->total_paid, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function payrollTrend(Builder $query): array
    {
        return collect((clone $query)
            ->selectRaw('DATE(period_end) as period_end')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as net_payroll')
            ->groupByRaw('DATE(period_end)')
            ->orderBy('period_end')
            ->get())
            ->map(function ($row): array {
                $periodEnd = $row->period_end;

                if ($periodEnd instanceof \DateTimeInterface) {
                    $periodEnd = $periodEnd->format('Y-m-d');
                }

                return [
                    'period_end' => $periodEnd,
                    'payroll_count' => (int) $row->payroll_count,
                    'net_payroll' => round((float) $row->net_payroll, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function recentPayrolls(Builder $query, string $locationName): array
    {
        return (clone $query)
            ->with([
                'employee:id,name',
                'approvedBy:id,name',
            ])
            ->withSum('payouts as total_paid', 'amount')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Payroll $payroll) use ($locationName): array {
                $totalPaid = round((float) ($payroll->total_paid ?? 0), 2);

                return [
                    'id' => $payroll->id,
                    'employee_name' => $payroll->employee?->name,
                    'location_name' => $locationName,
                    'period_start' => $payroll->period_start?->toDateString(),
                    'period_end' => $payroll->period_end?->toDateString(),
                    'period_label' => $payroll->period_start?->format('Y-m-d').' – '.$payroll->period_end?->format('Y-m-d'),
                    'pay_frequency' => $payroll->pay_frequency,
                    'pay_frequency_label' => $this->payFrequencyLabel($payroll->pay_frequency),
                    'status' => $payroll->status,
                    'status_label' => $this->statusLabel($payroll->status),
                    'gross_amount' => round((float) $payroll->gross_amount, 2),
                    'bonus' => round((float) $payroll->bonus, 2),
                    'total_deductions' => $payroll->employeeDeductionsTotal(),
                    'net_amount' => round((float) $payroll->net_amount, 2),
                    'total_paid' => $totalPaid,
                    'outstanding_balance' => round(max(0, (float) $payroll->net_amount - $totalPaid), 2),
                    'approved_by_name' => $payroll->approvedBy?->name,
                    'approved_at' => $payroll->approved_at?->toISOString(),
                ];
            })
            ->values()
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Payroll::STATUS_DRAFT => 'Draft',
            Payroll::STATUS_APPROVED => 'Approved',
            Payroll::STATUS_PARTIALLY_PAID => 'Partially Paid',
            Payroll::STATUS_PAID => 'Paid',
            Payroll::STATUS_CANCELED => 'Canceled',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function payFrequencyLabel(?string $payFrequency): string
    {
        return match ($payFrequency) {
            'monthly' => 'Monthly',
            'semi_monthly' => 'Semi Monthly',
            default => '-',
        };
    }

    private function payoutMethodLabel(string $method): string
    {
        return match ($method) {
            Payout::METHOD_CASH => 'Cash',
            Payout::METHOD_BANK_TRANSFER => 'Bank Transfer',
            Payout::METHOD_ONLINE_PAYMENT => 'Online Payment',
            default => str($method)->replace('_', ' ')->title()->toString(),
        };
    }
}
