<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\CashLedgerEntry;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\Payroll;
use App\Models\SaleTransaction;
use App\Models\WalkIn;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return view('panel.reports.financial');
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
        $fileName = "financial-report-{$dateSuffix}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Financial Reports']);
            fputcsv($handle, ['Location', $report['scope']['location']['name'] ?? '-']);
            fputcsv($handle, ['Date From', $report['filters']['date_from'] ?: '-']);
            fputcsv($handle, ['Date To', $report['filters']['date_to'] ?: '-']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Gross Revenue', $report['summary']['gross_revenue']]);
            fputcsv($handle, ['PT Commission', $report['summary']['pt_commission']]);
            fputcsv($handle, ['Membership Commission', $report['summary']['membership_commission']]);
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
            fputcsv($handle, ['Location', 'Title', 'Description', 'Amount', 'Occurred At', 'Created By']);
            foreach ($report['recent_operating_expenses'] as $row) {
                fputcsv($handle, [
                    $row['location_name'],
                    $row['title'],
                    $row['description'],
                    $row['amount'],
                    $row['occurred_at'],
                    $row['created_by_name'],
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $location = BusinessProfile::current()->locationSummary();

        $salesQuery = $this->salesQuery($data);
        $directWalkInQuery = $this->directWalkInQuery($data);
        $ptCommissionQuery = $this->ptCommissionQuery($data);
        $membershipCommissionQuery = $this->membershipCommissionQuery($data);
        $payrollQuery = $this->payrollQuery($data);
        $operatingExpenseQuery = $this->operatingExpenseQuery($data);

        $grossRevenue = round(
            (float) (clone $salesQuery)->sum('total')
            + (float) (clone $directWalkInQuery)->sum('amount_paid'),
            2
        );
        $ptCommission = round((float) (clone $ptCommissionQuery)->sum('coach_commission_amount'), 2);
        $membershipCommission = round((float) (clone $membershipCommissionQuery)->sum('manager_commission_amount'), 2);
        $payrollWages = round((float) ((clone $payrollQuery)
            ->selectRaw('COALESCE(SUM(gross_amount + bonus - manual_deductions), 0) as total_amount')
            ->value('total_amount') ?? 0), 2);
        $otherOperatingExpenses = round((float) (clone $operatingExpenseQuery)->sum('amount'), 2);
        $adjustedRevenue = round($grossRevenue - $ptCommission - $membershipCommission, 2);
        $netProfit = round($adjustedRevenue - $payrollWages - $otherOperatingExpenses, 2);

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
            ],
            'summary' => [
                'gross_revenue' => $grossRevenue,
                'pt_commission' => $ptCommission,
                'membership_commission' => $membershipCommission,
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
                    'key' => 'membership_commission',
                    'label' => 'Membership Commission',
                    'amount' => $membershipCommission,
                    'description' => 'Earned manager commissions from membership sales.',
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
                    'description' => 'Manual cash-out entries recorded in the cash ledger.',
                ],
            ],
            'operating_expense_categories' => $this->operatingExpenseCategories(clone $operatingExpenseQuery),
            'recent_operating_expenses' => $this->recentOperatingExpenses(clone $operatingExpenseQuery, $location['name']),
        ];
    }

    private function salesQuery(array $filters): Builder
    {
        return SaleTransaction::query()
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('sold_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('sold_at', '<=', $filters['date_to']));
    }

    private function ptCommissionQuery(array $filters): Builder
    {
        return MemberPtPackage::query()
            ->whereIn('coach_commission_status', [
                MemberPtPackage::COMMISSION_STATUS_EARNED,
                MemberPtPackage::COMMISSION_STATUS_PAID,
            ])
            ->whereNotNull('coach_commission_earned_at')
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('coach_commission_earned_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('coach_commission_earned_at', '<=', $filters['date_to']));
    }

    private function membershipCommissionQuery(array $filters): Builder
    {
        return MemberSubscription::query()
            ->whereIn('manager_commission_status', [
                MemberSubscription::COMMISSION_STATUS_EARNED,
                MemberSubscription::COMMISSION_STATUS_PAID,
            ])
            ->whereNotNull('manager_commission_earned_at')
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('manager_commission_earned_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('manager_commission_earned_at', '<=', $filters['date_to']));
    }

    private function directWalkInQuery(array $filters): Builder
    {
        $query = WalkIn::query()
            ->when($filters['date_from'] ?? null, fn ($builder) => $builder->whereDate('visited_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($builder) => $builder->whereDate('visited_at', '<=', $filters['date_to']));

        $posBackedWalkInIds = $this->posBackedWalkInIds($filters);

        if ($posBackedWalkInIds !== []) {
            $query->whereNotIn('id', $posBackedWalkInIds);
        }

        return $query;
    }

    private function payrollQuery(array $filters): Builder
    {
        return Payroll::query()
            ->whereNotIn('status', [Payroll::STATUS_DRAFT, Payroll::STATUS_CANCELED])
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('period_end', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('period_end', '<=', $filters['date_to']));
    }

    private function operatingExpenseQuery(array $filters): Builder
    {
        return CashLedgerEntry::query()
            ->where('direction', CashLedgerEntry::DIRECTION_OUT)
            ->where('entry_type', CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT)
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('occurred_at', '<=', $filters['date_to']));
    }

    /**
     * @param  Builder  $query
     * @param  Builder  $directWalkInQuery
     * @return array<int, array<string, mixed>>
     */
    private function revenueBreakdown(Builder $query, Builder $directWalkInQuery): array
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
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function operatingExpenseCategories(Builder $query): array
    {
        return (clone $query)
            ->select('title')
            ->selectRaw('COUNT(*) as entry_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('title')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get()
            ->map(fn (CashLedgerEntry $entry) => [
                'title' => $entry->title ?: 'Manual Expense',
                'entry_count' => (int) ($entry->entry_count ?? 0),
                'total_amount' => round((float) ($entry->total_amount ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function recentOperatingExpenses(Builder $query, string $locationName): array
    {
        return (clone $query)
            ->with('createdBy:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (CashLedgerEntry $entry) => [
                'id' => $entry->id,
                'location_name' => $locationName,
                'title' => $entry->title,
                'description' => $entry->description,
                'amount' => round((float) $entry->amount, 2),
                'occurred_at' => $entry->occurred_at?->toISOString(),
                'created_by_name' => $entry->createdBy?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    private function posBackedWalkInIds(array $filters): array
    {
        return SaleTransaction::query()
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
