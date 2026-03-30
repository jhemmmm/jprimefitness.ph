<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SaleTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportsController extends Controller
{
    public function index(): View
    {
        return view('panel.reports.sales');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->reportPayload($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->reportPayload($request);
        $dateSuffix = now()->format('Ymd_His');
        $fileName = "sales-report-{$dateSuffix}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Sales Reports']);
            fputcsv($handle, ['Branch', $report['scope']['branch']['name'] ?? 'All Accessible Branches']);
            fputcsv($handle, ['Date From', $report['filters']['date_from'] ?: '—']);
            fputcsv($handle, ['Date To', $report['filters']['date_to'] ?: '—']);
            fputcsv($handle, ['Sale Type', $report['filters']['type'] ?: 'All Types']);
            fputcsv($handle, ['Payment Method', $report['filters']['payment_method_label'] ?: 'All Methods']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Sales', $report['summary']['total_sales']]);
            fputcsv($handle, ['Transactions', $report['summary']['transaction_count']]);
            fputcsv($handle, ['Average Sale', $report['summary']['average_sale']]);
            fputcsv($handle, ['Cash Collected', $report['summary']['cash_sales']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Sales by Type']);
            fputcsv($handle, ['Type', 'Transactions', 'Total Sales']);
            foreach ($report['type_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['transaction_count'], $row['total_sales']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Payment Methods']);
            fputcsv($handle, ['Method', 'Transactions', 'Total Sales']);
            foreach ($report['payment_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['transaction_count'], $row['total_sales']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Branch Breakdown']);
            fputcsv($handle, ['Branch', 'Transactions', 'Total Sales']);
            foreach ($report['branch_breakdown'] as $row) {
                fputcsv($handle, [$row['branch_name'], $row['transaction_count'], $row['total_sales']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Daily Sales Trend']);
            fputcsv($handle, ['Date', 'Transactions', 'Total Sales']);
            foreach ($report['daily_trend'] as $row) {
                fputcsv($handle, [$row['sale_date'], $row['transaction_count'], $row['total_sales']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Top Items']);
            fputcsv($handle, ['Item', 'Type', 'Quantity', 'Total Sales']);
            foreach ($report['top_items'] as $row) {
                fputcsv($handle, [$row['name'], $row['type'], $row['quantity'], $row['total_sales']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent Transactions']);
            fputcsv($handle, ['Branch', 'Customer', 'Item', 'Type', 'Payment Method', 'Total', 'Processed By', 'Sold At']);
            foreach ($report['recent_transactions'] as $row) {
                fputcsv($handle, [
                    $row['branch_name'],
                    $row['customer_name'],
                    $row['item_name'],
                    $row['type'],
                    $row['payment_method_label'],
                    $row['total'],
                    $row['processed_by'],
                    $row['sold_at'],
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
            'type' => ['nullable', Rule::in([
                SaleTransaction::TYPE_INVENTORY,
                SaleTransaction::TYPE_MEMBERSHIP,
                SaleTransaction::TYPE_PT_PACKAGE,
                SaleTransaction::TYPE_WALK_IN,
            ])],
            'payment_method' => ['nullable', Rule::in([
                ...SaleTransaction::supportedPaymentMethods(),
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $selectedBranch = ($data['branch'] ?? null) ? $this->findAccessibleBranch((int) $data['branch']) : null;
        $accessibleBranchIds = $this->accessibleBranchIds();

        $salesQuery = SaleTransaction::query()
            ->whereIn('branch_id', $accessibleBranchIds)
            ->when($selectedBranch, fn ($query) => $query->where('branch_id', $selectedBranch->id))
            ->when($request->type, fn ($query) => $query->where('type', $request->type))
            ->when($request->payment_method, fn ($query) => $query->where('payment_method', $request->payment_method))
            ->when($request->date_from, fn ($query) => $query->whereDate('sold_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($query) => $query->whereDate('sold_at', '<=', $request->date_to));

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
                'type' => $data['type'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_method_label' => filled($data['payment_method'] ?? null)
                    ? SaleTransaction::paymentMethodLabel($data['payment_method'])
                    : null,
            ],
            'summary' => $this->summary((clone $salesQuery)->toBase()),
            'type_breakdown' => $this->typeBreakdown((clone $salesQuery)->toBase()),
            'payment_breakdown' => $this->paymentBreakdown((clone $salesQuery)->toBase()),
            'daily_trend' => $this->dailyTrend((clone $salesQuery)->toBase()),
            'top_items' => $this->topItems((clone $salesQuery)->get()),
            'branch_breakdown' => $this->branchBreakdown((clone $salesQuery)->toBase()),
            'recent_transactions' => $this->recentTransactions((clone $salesQuery)->with(['branch:id,name', 'processedBy:id,name'])->orderByDesc('sold_at')->limit(10)->get()),
        ];
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
     * @param  Builder  $query
     * @return array<string, float|int>
     */
    private function summary($query): array
    {
        $summary = $query
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(AVG(total), 0) as average_sale')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_method = ? THEN total ELSE 0 END), 0) as cash_sales', [SaleTransaction::PAYMENT_METHOD_CASH])
            ->first();

        return [
            'total_sales' => round((float) ($summary->total_sales ?? 0), 2),
            'transaction_count' => (int) ($summary->transaction_count ?? 0),
            'average_sale' => round((float) ($summary->average_sale ?? 0), 2),
            'cash_sales' => round((float) ($summary->cash_sales ?? 0), 2),
        ];
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function typeBreakdown($query): array
    {
        $rows = collect($query
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
        ])->map(function (string $type) use ($rows): array {
            $row = $rows->get($type);

            return [
                'type' => $type,
                'label' => str($type)->replace('_', ' ')->title()->toString(),
                'transaction_count' => (int) ($row->transaction_count ?? 0),
                'total_sales' => round((float) ($row->total_sales ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function paymentBreakdown($query): array
    {
        $rows = collect($query
            ->select('payment_method')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->groupBy('payment_method')
            ->get())
            ->keyBy('payment_method');

        return collect(SaleTransaction::supportedPaymentMethods())
            ->map(function (string $paymentMethod) use ($rows): array {
                $row = $rows->get($paymentMethod);

                return [
                    'payment_method' => $paymentMethod,
                    'label' => SaleTransaction::paymentMethodLabel($paymentMethod),
                    'transaction_count' => (int) ($row->transaction_count ?? 0),
                    'total_sales' => round((float) ($row->total_sales ?? 0), 2),
                ];
            })
            ->filter(fn (array $row) => $row['transaction_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function dailyTrend($query): array
    {
        return collect($query
            ->selectRaw('DATE(sold_at) as sale_date')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->orderBy('sale_date')
            ->get())
            ->map(function ($row): array {
                return [
                    'sale_date' => $row->sale_date,
                    'transaction_count' => (int) $row->transaction_count,
                    'total_sales' => round((float) $row->total_sales, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function branchBreakdown($query): array
    {
        return collect($query
            ->join('branches', 'branches.id', '=', 'sale_transactions.branch_id')
            ->select('sale_transactions.branch_id', 'branches.name')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(SUM(sale_transactions.total), 0) as total_sales')
            ->groupBy('sale_transactions.branch_id', 'branches.name')
            ->orderByDesc('total_sales')
            ->get())
            ->map(function ($row): array {
                return [
                    'branch_id' => (int) $row->branch_id,
                    'branch_name' => $row->name,
                    'transaction_count' => (int) $row->transaction_count,
                    'total_sales' => round((float) $row->total_sales, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SaleTransaction>  $transactions
     * @return array<int, array<string, mixed>>
     */
    private function topItems(Collection $transactions): array
    {
        return $transactions
            ->reduce(function (Collection $carry, SaleTransaction $transaction): Collection {
                $lineItems = collect(Arr::wrap(data_get($transaction->details, 'line_items', [])));

                if ($transaction->type === SaleTransaction::TYPE_INVENTORY && $lineItems->isNotEmpty()) {
                    $lineItems->each(function (array $lineItem) use ($carry): void {
                        $name = trim((string) ($lineItem['name'] ?? 'Inventory Item'));
                        $existing = $carry->get($name, [
                            'name' => $name,
                            'type' => SaleTransaction::TYPE_INVENTORY,
                            'quantity' => 0,
                            'total_sales' => 0,
                        ]);

                        $existing['quantity'] += (float) ($lineItem['quantity'] ?? 0);
                        $existing['total_sales'] += (float) ($lineItem['line_total'] ?? 0);

                        $carry->put($name, $existing);
                    });

                    return $carry;
                }

                $name = trim((string) ($transaction->item_name ?: 'Sale Item'));
                $existing = $carry->get($name, [
                    'name' => $name,
                    'type' => $transaction->type,
                    'quantity' => 0,
                    'total_sales' => 0,
                ]);

                $existing['quantity'] += 1;
                $existing['total_sales'] += (float) $transaction->total;

                $carry->put($name, $existing);

                return $carry;
            }, collect())
            ->sortByDesc('total_sales')
            ->take(8)
            ->map(function (array $row): array {
                return [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'quantity' => round((float) $row['quantity'], 2),
                    'total_sales' => round((float) $row['total_sales'], 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SaleTransaction>  $transactions
     * @return array<int, array<string, mixed>>
     */
    private function recentTransactions(Collection $transactions): array
    {
        return $transactions
            ->sortByDesc('sold_at')
            ->take(10)
            ->map(function (SaleTransaction $transaction): array {
                return [
                    'id' => $transaction->id,
                    'branch_name' => $transaction->branch?->name,
                    'customer_name' => $transaction->customer_name,
                    'item_name' => $transaction->item_name,
                    'type' => $transaction->type,
                    'payment_method' => $transaction->payment_method,
                    'payment_method_label' => SaleTransaction::paymentMethodLabel($transaction->payment_method),
                    'total' => round((float) $transaction->total, 2),
                    'processed_by' => $transaction->processedBy?->name,
                    'sold_at' => $transaction->sold_at?->toISOString(),
                ];
            })
            ->values()
            ->all();
    }
}
