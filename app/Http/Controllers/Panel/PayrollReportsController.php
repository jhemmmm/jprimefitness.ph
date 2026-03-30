<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payout;
use App\Models\Payroll;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReportsController extends Controller
{
    /**
     * Payroll Reports Index
     * @return View
     */
    public function index(): View
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return view('panel.reports.payroll');
    }

    /**
     * Generate payroll report data based on filters
     * @param Request $request
     * @return JsonResponse
     */
    public function data(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return response()->json($this->reportPayload($request));
    }

    /**
     * Export payroll report as CSV based on filters
     * @param Request $request
     * @return StreamedResponse
     */
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
            fputcsv($handle, ['Branch', $report['scope']['branch']['name'] ?? 'All Accessible Branches']);
            fputcsv($handle, ['Period End From', $report['filters']['date_from'] ?: '—']);
            fputcsv($handle, ['Period End To', $report['filters']['date_to'] ?: '—']);
            fputcsv($handle, ['Status', $report['filters']['status_label'] ?: 'All Active Statuses']);
            fputcsv($handle, ['Pay Frequency', $report['filters']['pay_frequency_label'] ?: 'All Frequencies']);
            fputcsv($handle, ['Payout Scope', 'Current payout progress for payrolls ending within the selected period']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Payroll Runs', $report['summary']['payroll_count']]);
            fputcsv($handle, ['Gross Payroll', $report['summary']['gross_payroll']]);
            fputcsv($handle, ['Bonuses', $report['summary']['total_bonus']]);
            fputcsv($handle, ['PT Commission', $report['summary']['pt_commission']]);
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

            fputcsv($handle, ['Branch Breakdown']);
            fputcsv($handle, ['Branch', 'Payroll Runs', 'Net Payroll', 'Paid Out', 'Outstanding']);
            foreach ($report['branch_breakdown'] as $row) {
                fputcsv($handle, [
                    $row['branch_name'],
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
            fputcsv($handle, ['Employee', 'Branch', 'Period', 'Pay Frequency', 'Status', 'Gross', 'Net', 'Paid Out', 'Outstanding', 'Approved By']);
            foreach ($report['recent_payrolls'] as $row) {
                fputcsv($handle, [
                    $row['employee_name'],
                    $row['branch_name'],
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
     * Report payload
     * @param Request $request
     * @return array{branch_breakdown: array, filters: array{date_from: mixed, date_to: mixed, pay_frequency: mixed, pay_frequency_label: string|null, status: mixed, status_label: string|null, pay_frequency_breakdown: array, payout_method_breakdown: array, payroll_trend: array, recent_payrolls: array, scope: array, status_breakdown: array, summary: array{gross_payroll: float, net_payroll: float, outstanding_balance: float, payroll_count: int, pt_commission: float, total_bonus: float, total_deductions: float, total_paid: float}}}
     */
    private function reportPayload(Request $request): array
    {
        $data = $request->validate([
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => [
                'nullable',
                Rule::in([
                    Payroll::STATUS_DRAFT,
                    Payroll::STATUS_APPROVED,
                    Payroll::STATUS_PARTIALLY_PAID,
                    Payroll::STATUS_PAID,
                    Payroll::STATUS_CANCELED,
                ])
            ],
            'pay_frequency' => [
                'nullable',
                Rule::in([
                    Branch::PAYROLL_FREQUENCY_MONTHLY,
                    Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
                ])
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $branches = auth()->user()->getBranches();
        $selectedBranch = ($data['branch'] ?? null) ? $branches->find((int) $data['branch']) : null;
        $accessibleBranchIds = $selectedBranch
            ? [$selectedBranch->id]
            : $branches->pluck('id')->all();

        $payrollQuery = $this->payrollQuery($accessibleBranchIds, $request);
        $payoutQuery = $this->payoutQuery($accessibleBranchIds, $request);

        $grossPayroll = round((float) (clone $payrollQuery)->sum('gross_amount'), 2);
        $totalBonus = round((float) (clone $payrollQuery)->sum('bonus'), 2);
        $ptCommission = round((float) (clone $payrollQuery)->sum('pt_commission_amount'), 2);
        $manualDeductions = round((float) (clone $payrollQuery)->sum('manual_deductions'), 2);
        $cashAdvanceDeductions = round((float) (clone $payrollQuery)->sum('cash_advance_deduction'), 2);
        $totalDeductions = round($manualDeductions + $cashAdvanceDeductions, 2);
        $netPayroll = round((float) (clone $payrollQuery)->sum('net_amount'), 2);
        $totalPaid = round((float) (clone $payoutQuery)->sum('payouts.amount'), 2);
        $payrollCount = (int) (clone $payrollQuery)->count();
        $outstandingBalance = round(max(0, $netPayroll - $totalPaid), 2);

        return [
            'scope' => [
                'branch' => $selectedBranch ? [
                    'id' => $selectedBranch->id,
                    'name' => $selectedBranch->name,
                ] : null,
                'is_all_branches' => !$selectedBranch,
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
                'pt_commission' => $ptCommission,
                'total_deductions' => $totalDeductions,
                'net_payroll' => $netPayroll,
                'total_paid' => $totalPaid,
                'outstanding_balance' => $outstandingBalance,
            ],
            'status_breakdown' => $this->statusBreakdown(clone $payrollQuery),
            'pay_frequency_breakdown' => $this->payFrequencyBreakdown(clone $payrollQuery),
            'branch_breakdown' => $this->branchBreakdown(clone $payrollQuery, clone $payoutQuery),
            'payout_method_breakdown' => $this->payoutMethodBreakdown(clone $payoutQuery),
            'payroll_trend' => $this->payrollTrend(clone $payrollQuery),
            'recent_payrolls' => $this->recentPayrolls(clone $payrollQuery),
        ];
    }

    /**
     * Generate payroll query based on filters
     * @param array $branchIds
     * @param Request $request
     * @return \Illuminate\Database\Query\Builder
     */
    private function payrollQuery(array $branchIds, Request $request)
    {
        return Payroll::query()
            ->whereIn('payrolls.branch_id', $branchIds)
            ->when($request->status, fn($query) => $query->where('payrolls.status', $request->status), fn($query) => $query->where('payrolls.status', '!=', Payroll::STATUS_CANCELED))
            ->when($request->pay_frequency, fn($query) => $query->where('payrolls.pay_frequency', $request->pay_frequency))
            ->when($request->date_from, fn($query) => $query->whereDate('payrolls.period_end', '>=', $request->date_from))
            ->when($request->date_to, fn($query) => $query->whereDate('payrolls.period_end', '<=', $request->date_to));
    }

    /**
     * Generate payout query based on filters
     * @param array $branchIds
     * @param Request $request
     * @return \Illuminate\Database\Query\Builder
     */
    private function payoutQuery(array $branchIds, Request $request)
    {
        return Payout::query()
            ->join('payrolls', 'payrolls.id', '=', 'payouts.payroll_id')
            ->whereIn('payrolls.branch_id', $branchIds)
            ->when($request->status, fn($query) => $query->where('payrolls.status', $request->status), fn($query) => $query->where('payrolls.status', '!=', Payroll::STATUS_CANCELED))
            ->when($request->pay_frequency, fn($query) => $query->where('payrolls.pay_frequency', $request->pay_frequency))
            ->when($request->date_from, fn($query) => $query->whereDate('payrolls.period_end', '>=', $request->date_from))
            ->when($request->date_to, fn($query) => $query->whereDate('payrolls.period_end', '<=', $request->date_to));
    }

    /**
     * Generate status breakdown based on payroll query
     * @param mixed $query
     * @return array[]
     */
    private function statusBreakdown($query): array
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
        })->filter(fn(array $row) => $row['payroll_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Generate pay frequency breakdown based on payroll query
     * @param mixed $query
     * @return array[]
     */
    private function payFrequencyBreakdown($query): array
    {
        $rows = collect((clone $query)
            ->select('pay_frequency')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as net_payroll')
            ->groupBy('pay_frequency')
            ->get())
            ->keyBy('pay_frequency');

        return collect([
            Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
            Branch::PAYROLL_FREQUENCY_MONTHLY,
        ])->map(function (string $payFrequency) use ($rows): array {
            $row = $rows->get($payFrequency);

            return [
                'pay_frequency' => $payFrequency,
                'label' => $this->payFrequencyLabel($payFrequency),
                'payroll_count' => (int) ($row->payroll_count ?? 0),
                'net_payroll' => round((float) ($row->net_payroll ?? 0), 2),
            ];
        })->filter(fn(array $row) => $row['payroll_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Generate branch breakdown based on payroll and payout queries
     * @param mixed $payrollQuery
     * @param mixed $payoutQuery
     * @return array[]
     */
    private function branchBreakdown($payrollQuery, $payoutQuery): array
    {
        $payoutTotals = (clone $payoutQuery)
            ->select('payrolls.branch_id')
            ->selectRaw('COALESCE(SUM(payouts.amount), 0) as total_paid')
            ->groupBy('payrolls.branch_id')
            ->get()
            ->keyBy('branch_id');

        return collect((clone $payrollQuery)
            ->join('branches', 'branches.id', '=', 'payrolls.branch_id')
            ->select('payrolls.branch_id', 'branches.name')
            ->selectRaw('COUNT(*) as payroll_count')
            ->selectRaw('COALESCE(SUM(payrolls.net_amount), 0) as net_payroll')
            ->groupBy('payrolls.branch_id', 'branches.name')
            ->orderByDesc('net_payroll')
            ->get())
            ->map(function ($row) use ($payoutTotals): array {
                $paid = round((float) data_get($payoutTotals->get($row->branch_id), 'total_paid', 0), 2);
                $net = round((float) $row->net_payroll, 2);

                return [
                    'branch_id' => (int) $row->branch_id,
                    'branch_name' => $row->name,
                    'payroll_count' => (int) $row->payroll_count,
                    'net_payroll' => $net,
                    'total_paid' => $paid,
                    'outstanding_balance' => round(max(0, $net - $paid), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Generate payout method breakdown based on payout query
     * @param mixed $query
     * @return array[]
     */
    private function payoutMethodBreakdown($query): array
    {
        return collect((clone $query)
            ->select('payouts.method')
            ->selectRaw('COUNT(*) as payout_count')
            ->selectRaw('COALESCE(SUM(payouts.amount), 0) as total_paid')
            ->groupBy('payouts.method')
            ->orderByDesc('total_paid')
            ->get())
            ->map(function ($row): array {
                return [
                    'method' => $row->method,
                    'label' => $this->payoutMethodLabel($row->method),
                    'payout_count' => (int) $row->payout_count,
                    'total_paid' => round((float) $row->total_paid, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Generate payroll trend based on payroll query
     * @param mixed $query
     * @return array[]
     */
    private function payrollTrend($query): array
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
     * Generate recent payrolls based on payroll query
     * @param mixed $query
     * @return array[]
     */
    private function recentPayrolls($query): array
    {
        return (clone $query)
            ->with([
                'employee:id,name',
                'branch:id,name',
                'approvedBy:id,name',
            ])
            ->withSum('payouts as total_paid', 'amount')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Payroll $payroll): array {
                $totalPaid = round((float) ($payroll->total_paid ?? 0), 2);

                return [
                    'id' => $payroll->id,
                    'employee_name' => $payroll->employee?->name,
                    'branch_name' => $payroll->branch?->name,
                    'period_start' => $payroll->period_start?->toDateString(),
                    'period_end' => $payroll->period_end?->toDateString(),
                    'period_label' => $payroll->period_start?->format('Y-m-d') . ' – ' . $payroll->period_end?->format('Y-m-d'),
                    'pay_frequency' => $payroll->pay_frequency,
                    'pay_frequency_label' => $this->payFrequencyLabel($payroll->pay_frequency),
                    'status' => $payroll->status,
                    'status_label' => $this->statusLabel($payroll->status),
                    'gross_amount' => round((float) $payroll->gross_amount, 2),
                    'bonus' => round((float) $payroll->bonus, 2),
                    'pt_commission_amount' => round((float) $payroll->pt_commission_amount, 2),
                    'total_deductions' => round((float) $payroll->manual_deductions + (float) $payroll->cash_advance_deduction, 2),
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

    /**
     * Generate status label based on status code
     * @param string $status
     * @return string
     */
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

    /**
     * Generate pay frequency label based on frequency code
     * @param string|null $payFrequency
     * @return string
     */
    private function payFrequencyLabel(?string $payFrequency): string
    {
        return match ($payFrequency) {
            Branch::PAYROLL_FREQUENCY_MONTHLY => 'Monthly',
            Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY => 'Semi Monthly',
            default => '—',
        };
    }

    /**
     * Generate payout method label based on method code
     * @param string $method
     * @return string
     */
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
