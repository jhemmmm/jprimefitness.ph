<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\MemberSubscription;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard page - shows the dashboard view with branch highlights and other info.
     */
    public function index(): View
    {
        return view('panel.dashboard');
    }

    /**
     * Return the dashboard payload for the selected scope.
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json($this->dashboardPayload($request));
    }

    /**
     * Build the dashboard payload used by the Vue page.
     *
     * @return array<string, mixed>
     */
    private function dashboardPayload(Request $request): array
    {
        $data = $request->validate([
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $accessibleBranches = $user->getBranches()->values();
        $selectedBranch = ($data['branch'] ?? null)
            ? $accessibleBranches->firstWhere('id', (int) $data['branch'])
            : null;
        $accessibleBranchIds = $selectedBranch
            ? [$selectedBranch->id]
            : $accessibleBranches->pluck('id')->values()->all();
        $scopedBranches = Branch::query()
            ->whereIn('id', $accessibleBranchIds)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
        $canViewFinancialData = $user->hasAnyRole(['super admin', 'admin', 'manager']);
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfDay();
        $branchLoadRows = $this->branchLoadRows($scopedBranches, $todayStart, $todayEnd);

        $payload = [
            'scope' => [
                'branch' => $selectedBranch ? [
                    'id' => $selectedBranch->id,
                    'name' => $selectedBranch->name,
                ] : null,
                'is_all_branches' => $selectedBranch === null,
            ],
            'permissions' => [
                'can_view_financial_data' => $canViewFinancialData,
            ],
            'stats_row_1' => array_merge([
                'total_members' => $this->totalMembers($accessibleBranchIds),
                'check_ins_today' => $this->todayCheckInCount($accessibleBranchIds, $todayStart, $todayEnd),
            ], $canViewFinancialData ? [
                    'revenue_today' => $this->revenueForWindow($accessibleBranchIds, $todayStart, $todayEnd),
                    'revenue_this_month' => $this->revenueForWindow($accessibleBranchIds, $monthStart, $monthEnd),
                ] : []),
            'stats_row_2' => array_merge([
                'active_trainers' => $this->activeTrainerCount($accessibleBranchIds),
                'active_employees' => $this->activeEmployeeCount($accessibleBranchIds),
                'walk_ins_today' => $this->walkInCountForWindow($accessibleBranchIds, $todayStart, $todayEnd),
            ], $canViewFinancialData ? [
                    'pending_payroll_balance' => $this->pendingPayrollBalance($accessibleBranchIds),
                ] : []),
            'peak_hours' => $this->peakHours($accessibleBranchIds, $monthStart, $monthEnd),
            'branch_load' => $branchLoadRows
                ->sort(function (array $left, array $right): int {
                    if ($left['current_occupancy'] === $right['current_occupancy']) {
                        if ($left['today_check_ins'] === $right['today_check_ins']) {
                            return strcmp($left['branch_name'], $right['branch_name']);
                        }

                        return $right['today_check_ins'] <=> $left['today_check_ins'];
                    }

                    return $right['current_occupancy'] <=> $left['current_occupancy'];
                })
                ->values()
                ->all(),
            'branch_status' => $branchLoadRows
                ->sortBy('branch_name')
                ->values()
                ->all(),
            'check_ins_today' => $this->checkInsToday($accessibleBranchIds, $todayStart, $todayEnd),
            'trainers' => $this->trainers($accessibleBranchIds),
            'recent_members' => $this->recentMembers($accessibleBranchIds),
            'recent_sales' => $this->recentSales($accessibleBranchIds),
            'expiring_memberships' => $this->expiringMemberships($accessibleBranchIds, $todayStart, now()->copy()->addDays(7)->endOfDay()),
        ];

        if ($canViewFinancialData) {
            $payload['pending_payrolls'] = $this->pendingPayrolls($accessibleBranchIds);
        }

        return $payload;
    }

    /**
     * Count members assigned to the scoped branches.
     *
     * @param  array<int>  $branchIds
     */
    private function totalMembers(array $branchIds): int
    {
        return User::role('member')
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->count();
    }

    /**
     * Count active coaches in scope.
     *
     * @param  array<int>  $branchIds
     */
    private function activeTrainerCount(array $branchIds): int
    {
        return User::role('coach')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->count();
    }

    /**
     * Count active employees excluding coaches.
     *
     * @param  array<int>  $branchIds
     */
    private function activeEmployeeCount(array $branchIds): int
    {
        return User::role(['employee', 'manager', 'admin', 'staff'])
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->count();
    }

    /**
     * Count today's attendance records for the selected scope.
     *
     * @param  array<int>  $branchIds
     */
    private function todayCheckInCount(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return Attendance::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('checked_in_at', [$start, $end])
            ->count();
    }

    /**
     * Count direct walk-in records for the window.
     *
     * @param  array<int>  $branchIds
     */
    private function walkInCountForWindow(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return WalkIn::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('visited_at', [$start, $end])
            ->count();
    }

    /**
     * Sum dashboard revenue using the financial report rules for direct walk-ins.
     *
     * @param  array<int>  $branchIds
     */
    private function revenueForWindow(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $salesTotal = (float) SaleTransaction::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$start, $end])
            ->sum('total');

        $directWalkInTotal = (float) $this->directWalkInQuery($branchIds, $start, $end)->sum('amount_paid');

        return round($salesTotal + $directWalkInTotal, 2);
    }

    /**
     * Return month-to-date check-ins grouped by hour.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, int|string>>
     */
    private function peakHours(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = Attendance::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('checked_in_at', [$start, $end])
            ->get(['checked_in_at'])
            ->map(fn(Attendance $attendance) => $attendance->checked_in_at ? (int) $attendance->checked_in_at->format('H') : null)
            ->filter(fn(?int $hour) => $hour !== null)
            ->countBy();

        return collect(range(0, 23))
            ->map(function (int $hour) use ($rows): array {
                $hourSlot = str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00';

                return [
                    'hour_number' => $hour,
                    'hour_slot' => $hourSlot,
                    'label' => now()->copy()->startOfDay()->addHours($hour)->format('g A'),
                    'check_in_count' => (int) ($rows->get($hour) ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build the branch occupancy rows used by the dashboard.
     *
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, array<string, int|string>>
     */
    private function branchLoadRows(Collection $branches, \DateTimeInterface $todayStart, \DateTimeInterface $todayEnd): Collection
    {
        $branchIds = $branches->pluck('id')->all();
        $occupancyByBranch = Attendance::query()
            ->whereIn('branch_id', $branchIds)
            ->whereNull('checked_out_at')
            ->select('branch_id')
            ->selectRaw('COUNT(*) as current_occupancy')
            ->groupBy('branch_id')
            ->get()
            ->mapWithKeys(fn(Attendance $attendance) => [
                (int) $attendance->branch_id => (int) $attendance->current_occupancy,
            ]);
        $todayCheckInsByBranch = Attendance::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('checked_in_at', [$todayStart, $todayEnd])
            ->select('branch_id')
            ->selectRaw('COUNT(*) as today_check_ins')
            ->groupBy('branch_id')
            ->get()
            ->mapWithKeys(fn(Attendance $attendance) => [
                (int) $attendance->branch_id => (int) $attendance->today_check_ins,
            ]);

        return $branches->map(function (Branch $branch) use ($occupancyByBranch, $todayCheckInsByBranch): array {
            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'status' => $branch->status,
                'current_occupancy' => (int) ($occupancyByBranch->get($branch->id) ?? 0),
                'today_check_ins' => (int) ($todayCheckInsByBranch->get($branch->id) ?? 0),
            ];
        })->values();
    }

    /**
     * Return the latest attendance feed for today.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function checkInsToday(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return Attendance::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('checked_in_at', [$start, $end])
            ->with([
                'branch:id,name',
                'user.memberSubscriptions' => fn($query) => $query
                    ->with(['ratePlan:id,name', 'branch:id,name'])
                    ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PAUSED])
                    ->orderByDesc('start_date'),
                'user.roles',
                'walkIn.ratePlan:id,name',
            ])
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Attendance $attendance): array {
                $membership = $this->loadedCurrentMembership($attendance->user);
                $employeeRole = $attendance->user?->roles
                    ? $attendance->user->roles->pluck('name')->filter()->implode(', ')
                    : null;

                return [
                    'id' => $attendance->id,
                    'name' => $attendance->name,
                    'attendee_type' => $attendance->attendee_type,
                    'attendee_type_label' => $this->attendanceTypeLabel($attendance->attendee_type),
                    'branch_name' => $attendance->branch?->name,
                    'plan_or_rate' => match ($attendance->attendee_type) {
                        Attendance::TYPE_MEMBER => $membership?->ratePlan?->name ?? 'Membership',
                        Attendance::TYPE_WALK_IN => $attendance->walkIn?->ratePlan?->name ?? 'Walk-in Rate',
                        Attendance::TYPE_EMPLOYEE => $employeeRole ?: 'Employee',
                        default => '-',
                    },
                    'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return active coaches for the scope.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function trainers(array $branchIds): array
    {
        return User::role('coach')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->with(['branches:id,name'])
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(function (User $trainer): array {
                return [
                    'id' => $trainer->id,
                    'name' => $trainer->name,
                    'status' => $trainer->status,
                    'branch_names' => $trainer->branches->pluck('name')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return the latest members in scope.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function recentMembers(array $branchIds): array
    {
        return User::role('member')
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->with([
                'branches:id,name',
                'memberSubscriptions' => fn($query) => $query
                    ->with(['ratePlan:id,name', 'branch:id,name'])
                    ->orderByDesc('start_date'),
            ])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (User $member): array {
                $membership = $this->loadedCurrentMembership($member);
                $branchName = $membership?->branch?->name
                    ?? $member->branches->pluck('name')->first()
                    ?? '-';

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'plan_name' => $membership?->ratePlan?->name ?? '-',
                    'branch_name' => $branchName,
                    'status' => $member->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return the latest sales in scope.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function recentSales(array $branchIds): array
    {
        return SaleTransaction::query()
            ->whereIn('branch_id', $branchIds)
            ->with(['branch:id,name', 'member:id,name'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (SaleTransaction $sale): array {
                return [
                    'id' => $sale->id,
                    'customer_name' => $sale->customer_name ?: $sale->member?->name ?: 'Walk-in Customer',
                    'item_name' => $sale->item_name ?: str($sale->type)->replace('_', ' ')->title()->toString(),
                    'branch_name' => $sale->branch?->name,
                    'total' => round((float) $sale->total, 2),
                    'sold_at' => $sale->sold_at?->toISOString(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return memberships expiring within the requested window.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function expiringMemberships(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return MemberSubscription::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PAUSED])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with(['member:id,name,status', 'ratePlan:id,name', 'branch:id,name'])
            ->orderBy('end_date')
            ->limit(8)
            ->get()
            ->map(function (MemberSubscription $subscription): array {
                return [
                    'id' => $subscription->id,
                    'member_name' => $subscription->member?->name,
                    'plan_name' => $subscription->ratePlan?->name,
                    'branch_name' => $subscription->branch?->name,
                    'end_date' => $subscription->end_date?->toDateString(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return recent payrolls that still have a remaining balance.
     *
     * @param  array<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function pendingPayrolls(array $branchIds): array
    {
        return Payroll::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID])
            ->with([
                'employee:id,name',
                'employee.roles',
                'branch:id,name',
            ])
            ->withSum('payouts as total_paid', 'amount')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(function (Payroll $payroll): array {
                $totalPaid = round((float) ($payroll->total_paid ?? 0), 2);
                $outstandingBalance = round(max(0, (float) $payroll->net_amount - $totalPaid), 2);

                return [
                    'id' => $payroll->id,
                    'employee_name' => $payroll->employee?->name,
                    'employee_role' => $payroll->employee?->roles?->pluck('name')->first() ?? 'Employee',
                    'branch_name' => $payroll->branch?->name,
                    'status' => $payroll->status,
                    'net_amount' => round((float) $payroll->net_amount, 2),
                    'outstanding_balance' => $outstandingBalance,
                    'period_label' => $payroll->period_start?->format('Y-m-d') . ' – ' . $payroll->period_end?->format('Y-m-d'),
                ];
            })
            ->filter(fn(array $payroll) => $payroll['outstanding_balance'] > 0)
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Sum the outstanding balance for payout-eligible payrolls.
     *
     * @param  array<int>  $branchIds
     */
    private function pendingPayrollBalance(array $branchIds): float
    {
        $netPayroll = (float) Payroll::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID])
            ->sum('net_amount');
        $totalPaid = (float) Payout::query()
            ->join('payrolls', 'payrolls.id', '=', 'payouts.payroll_id')
            ->whereIn('payrolls.branch_id', $branchIds)
            ->whereIn('payrolls.status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID])
            ->sum('payouts.amount');

        return round(max(0, $netPayroll - $totalPaid), 2);
    }

    /**
     * Return the direct walk-in query used for non-POS revenue.
     *
     * @param  array<int>  $branchIds
     */
    private function directWalkInQuery(array $branchIds, \DateTimeInterface $start, \DateTimeInterface $end)
    {
        $query = WalkIn::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('visited_at', [$start, $end]);

        $posBackedWalkInIds = SaleTransaction::query()
            ->whereIn('branch_id', $branchIds)
            ->where('type', SaleTransaction::TYPE_WALK_IN)
            ->whereBetween('sold_at', [$start, $end])
            ->get(['details'])
            ->map(fn(SaleTransaction $transaction) => (int) data_get($transaction->details, 'walk_in_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($posBackedWalkInIds !== []) {
            $query->whereNotIn('id', $posBackedWalkInIds);
        }

        return $query;
    }

    /**
     * Resolve the current membership from an already-loaded user relation.
     */
    private function loadedCurrentMembership(?User $user): ?MemberSubscription
    {
        if ($user === null) {
            return null;
        }

        if (!$user->relationLoaded('memberSubscriptions')) {
            return $user->currentMembership();
        }

        return $user->memberSubscriptions
            ->first(fn(MemberSubscription $subscription) => in_array($subscription->status, [
                MemberSubscription::STATUS_ACTIVE,
                MemberSubscription::STATUS_PAUSED,
            ], true));
    }

    /**
     * Human-readable attendance labels.
     */
    private function attendanceTypeLabel(string $type): string
    {
        return match ($type) {
            Attendance::TYPE_MEMBER => 'Member',
            Attendance::TYPE_WALK_IN => 'Walk-in',
            Attendance::TYPE_EMPLOYEE => 'Employee',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }
}
