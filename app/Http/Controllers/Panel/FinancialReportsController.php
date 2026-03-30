<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchCashLedgerEntry;
use App\Models\MemberPtPackage;
use App\Models\Payroll;
use App\Models\SaleTransaction;
use App\Models\WalkIn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportsController extends Controller
{
    public function index(): View
    {
        $this->authorizeFinancialAccess();

        return view('panel.reports.financial');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeFinancialAccess();

        return response()->json($this->reportPayload($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeFinancialAccess();

        $report = $this->reportPayload($request);
        $dateSuffix = now()->format('Ymd_His');
        $fileName = "financial-report-{$dateSuffix}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Financial Reports']);
            fputcsv($handle, ['Branch', $report['scope']['branch']['name'] ?? 'All Accessible Branches']);
            fputcsv($handle, ['Date From', $report['filters']['date_from'] ?: '—']);
            fputcsv($handle, ['Date To', $report['filters']['date_to'] ?: '—']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Gross Revenue', $report['summary']['gross_revenue']]);
            fputcsv($handle, ['PT Commission', $report['summary']['pt_commission']]);
            fputcsv($handle, ['Adjusted Revenue', $report['summary']['adjusted_revenue']]);
            fputcsv($handle, ['Payroll Wages', $report['summary']['payroll_wages']]);
            fputcsv($handle, ['Operating Expenses', $report['summary']['other_operating_expenses']]);
            fputcsv($handle, ['Net Profit', $report['summary']['net_profit']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Revenue Breakdown']);
            fputcsv($handle, ['Type', 'Transactions', 'Total Sales']);
            foreach ($report['revenue_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['transaction_count'], $row['total_sales']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Expense Breakdown']);
            fputcsv($handle, ['Category', 'Description', 'Amount']);
            foreach ($report['expense_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['description'], $row['amount']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Operating Expense Categories']);
            fputcsv($handle, ['Category', 'Entries', 'Total Amount']);
            foreach ($report['operating_expense_categories'] as $row) {
                fputcsv($handle, [$row['title'], $row['entry_count'], $row['total_amount']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent Operating Expenses']);
            fputcsv($handle, ['Branch', 'Title', 'Description', 'Amount', 'Occurred At', 'Created By']);
            foreach ($report['recent_operating_expenses'] as $row) {
                fputcsv($handle, [
                    $row['branch_name'],
                    $row['title'],
                    $row['description'],
                    $row['amount'],
                    $row['occurred_at'],
                    $row['created_by_name'],
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
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $selectedBranch = $request->branch ? $this->findAccessibleBranch((int) $request->branch) : null;
        $accessibleBranchIds = $selectedBranch
            ? [$selectedBranch->id]
            : $this->accessibleBranchIds();

        $salesQuery = $this->salesQuery($accessibleBranchIds, $data);
        $directWalkInQuery = $this->directWalkInQuery($accessibleBranchIds, $data);
        $ptCommissionQuery = $this->ptCommissionQuery($accessibleBranchIds, $data);
        $payrollQuery = $this->payrollQuery($accessibleBranchIds, $data);
        $operatingExpenseQuery = $this->operatingExpenseQuery($accessibleBranchIds, $data);

        $grossRevenue = round(
            (float) (clone $salesQuery)->sum('total')
            + (float) (clone $directWalkInQuery)->sum('amount_paid'),
            2
        );
        $ptCommission = round((float) (clone $ptCommissionQuery)->sum('coach_commission_amount'), 2);
        $payrollWages = round((float) ((clone $payrollQuery)
            ->selectRaw('COALESCE(SUM(gross_amount + bonus - manual_deductions), 0) as total_amount')
            ->value('total_amount') ?? 0), 2);
        $otherOperatingExpenses = round((float) (clone $operatingExpenseQuery)->sum('amount'), 2);
        $adjustedRevenue = round($grossRevenue - $ptCommission, 2);
        $netProfit = round($adjustedRevenue - $payrollWages - $otherOperatingExpenses, 2);

        return [
            'scope' => [
                'branch' => $selectedBranch ? [
                    'id' => $selectedBranch->id,
                    'name' => $selectedBranch->name,
                ] : null,
                'is_all_branches' => ! $selectedBranch,
            ],
            'filters' => [
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
            ],
            'summary' => [
                'gross_revenue' => $grossRevenue,
                'pt_commission' => $ptCommission,
                'adjusted_revenue' => $adjustedRevenue,
                'payroll_wages' => $payrollWages,
                'other_operating_expenses' => $otherOperatingExpenses,
                'net_profit' => $netProfit,
            ],
            'revenue_breakdown' => $this->revenueBreakdown(clone $salesQuery, clone $directWalkInQuery),
            'expense_breakdown' => [
                [
                    'key' => 'pt_commission',
                    'label' => 'PT Commission',
                    'amount' => $ptCommission,
                    'description' => 'Earned coach commissions from completed PT packages.',
                ],
                [
                    'key' => 'payroll_wages',
                    'label' => 'Payroll Wages',
                    'amount' => $payrollWages,
                    'description' => 'Approved payroll wages and bonuses, net of manual deductions.',
                ],
                [
                    'key' => 'other_operating_expenses',
                    'label' => 'Other Operating Expenses',
                    'amount' => $otherOperatingExpenses,
                    'description' => 'Manual branch cash-out entries marked through the cash ledger.',
                ],
            ],
            'operating_expense_categories' => $this->operatingExpenseCategories(clone $operatingExpenseQuery),
            'recent_operating_expenses' => $this->recentOperatingExpenses(clone $operatingExpenseQuery),
        ];
    }

    private function authorizeFinancialAccess(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);
    }

    private function findAccessibleBranch(int $branchId): Branch
    {
        return Branch::query()
            ->when(! auth()->user()->hasRole('super admin'), fn ($query) => $query->whereIn('id', auth()->user()->branches()->pluck('branches.id')))
            ->findOrFail($branchId);
    }

    /**
     * @return array<int>
     */
    private function accessibleBranchIds(): array
    {
        if (auth()->user()->hasRole('super admin')) {
            return Branch::query()->pluck('id')->all();
        }

        return auth()->user()->branches()->pluck('branches.id')->all();
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function salesQuery(array $branchIds, array $filters)
    {
        return SaleTransaction::query()
            ->whereIn('branch_id', $branchIds)
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('sold_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('sold_at', '<=', $filters['date_to']));
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function ptCommissionQuery(array $branchIds, array $filters)
    {
        return MemberPtPackage::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('coach_commission_status', [
                MemberPtPackage::COMMISSION_STATUS_EARNED,
                MemberPtPackage::COMMISSION_STATUS_PAID,
            ])
            ->whereNotNull('coach_commission_earned_at')
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('coach_commission_earned_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('coach_commission_earned_at', '<=', $filters['date_to']));
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function directWalkInQuery(array $branchIds, array $filters)
    {
        $query = WalkIn::query()
            ->whereIn('branch_id', $branchIds)
            ->when($filters['date_from'] ?? null, fn ($builder) => $builder->whereDate('visited_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($builder) => $builder->whereDate('visited_at', '<=', $filters['date_to']));

        $posBackedWalkInIds = $this->posBackedWalkInIds($branchIds, $filters);

        if ($posBackedWalkInIds !== []) {
            $query->whereNotIn('id', $posBackedWalkInIds);
        }

        return $query;
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function payrollQuery(array $branchIds, array $filters)
    {
        return Payroll::query()
            ->whereIn('branch_id', $branchIds)
            ->whereNotIn('status', [Payroll::STATUS_DRAFT, Payroll::STATUS_CANCELED])
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('period_end', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('period_end', '<=', $filters['date_to']));
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function operatingExpenseQuery(array $branchIds, array $filters)
    {
        return BranchCashLedgerEntry::query()
            ->whereIn('branch_id', $branchIds)
            ->where('direction', BranchCashLedgerEntry::DIRECTION_OUT)
            ->where('entry_type', BranchCashLedgerEntry::TYPE_MANUAL_ADJUSTMENT)
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('occurred_at', '<=', $filters['date_to']));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenueBreakdown($query, $directWalkInQuery): array
    {
        $rows = collect((clone $query)
            ->select('type')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->groupBy('type')
            ->get())
            ->keyBy('type');

        return collect([
            SaleTransaction::TYPE_INVENTORY,
            SaleTransaction::TYPE_MEMBERSHIP,
            SaleTransaction::TYPE_PT_PACKAGE,
            SaleTransaction::TYPE_WALK_IN,
        ])->map(function (string $type) use ($rows, $directWalkInQuery): array {
            $row = $rows->get($type);
            $transactionCount = (int) ($row->transaction_count ?? 0);
            $totalSales = round((float) ($row->total_sales ?? 0), 2);

            if ($type === SaleTransaction::TYPE_WALK_IN) {
                $transactionCount += (int) (clone $directWalkInQuery)->count();
                $totalSales = round($totalSales + (float) (clone $directWalkInQuery)->sum('amount_paid'), 2);
            }

            return [
                'type' => $type,
                'label' => str($type)->replace('_', ' ')->title()->toString(),
                'transaction_count' => $transactionCount,
                'total_sales' => $totalSales,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operatingExpenseCategories($query): array
    {
        return (clone $query)
            ->select('title')
            ->selectRaw('COUNT(*) as entry_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('title')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get()
            ->map(function (BranchCashLedgerEntry $entry): array {
                return [
                    'title' => $entry->title ?: 'Manual Expense',
                    'entry_count' => (int) ($entry->entry_count ?? 0),
                    'total_amount' => round((float) ($entry->total_amount ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOperatingExpenses($query): array
    {
        return (clone $query)
            ->with(['branch:id,name', 'createdBy:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (BranchCashLedgerEntry $entry): array {
                return [
                    'id' => $entry->id,
                    'branch_name' => $entry->branch?->name,
                    'title' => $entry->title,
                    'description' => $entry->description,
                    'amount' => round((float) $entry->amount, 2),
                    'occurred_at' => $entry->occurred_at?->toISOString(),
                    'created_by_name' => $entry->createdBy?->name,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return array<int>
     */
    private function posBackedWalkInIds(array $branchIds, array $filters): array
    {
        return SaleTransaction::query()
            ->whereIn('branch_id', $branchIds)
            ->where('type', SaleTransaction::TYPE_WALK_IN)
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('sold_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('sold_at', '<=', $filters['date_to']))
            ->get(['details'])
            ->map(fn (SaleTransaction $transaction) => (int) data_get($transaction->details, 'walk_in_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
