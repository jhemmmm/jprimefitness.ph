<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MemberSubscription;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Models\WalkIn;
use App\Services\BusinessProfileContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private BusinessProfileContext $businessProfileContext)
    {
    }

    public function index(): View
    {
        return view('panel.dashboard');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->dashboardPayload());
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPayload(): array
    {
        $location = $this->businessProfileContext->locationSummary();

        /** @var User $user */
        $user = auth()->user();
        $canViewFinancialData = $user->hasAnyRole(['super admin', 'admin', 'manager']);
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfDay();
        $locationLoadRows = $this->locationLoadRows($location, $todayStart, $todayEnd);

        $payload = [
            'scope' => [
                'location' => [
                    'id' => $location['id'],
                    'name' => $location['name'],
                ],
            ],
            'permissions' => [
                'can_view_financial_data' => $canViewFinancialData,
            ],
            'stats_row_1' => array_merge([
                'total_members' => $this->totalMembers(),
                'check_ins_today' => $this->todayCheckInCount($todayStart, $todayEnd),
            ], $canViewFinancialData ? [
                'revenue_today' => $this->revenueForWindow($todayStart, $todayEnd),
                'revenue_this_month' => $this->revenueForWindow($monthStart, $monthEnd),
            ] : []),
            'stats_row_2' => array_merge([
                'active_trainers' => $this->activeTrainerCount(),
                'active_employees' => $this->activeEmployeeCount(),
                'walk_ins_today' => $this->walkInCountForWindow($todayStart, $todayEnd),
            ], $canViewFinancialData ? [
                'pending_payroll_balance' => $this->pendingPayrollBalance(),
            ] : []),
            'peak_hours' => $this->peakHours($monthStart, $monthEnd),
            'location_load' => $locationLoadRows,
            'location_status' => $locationLoadRows,
            'check_ins_today' => $this->checkInsToday($location, $todayStart, $todayEnd),
            'trainers' => $this->trainers($location),
            'recent_members' => $this->recentMembers($location),
            'recent_sales' => $this->recentSales($location),
            'expiring_memberships' => $this->expiringMemberships($location, $todayStart, now()->copy()->addDays(7)->endOfDay()),
        ];

        if ($canViewFinancialData) {
            $payload['pending_payrolls'] = $this->pendingPayrolls($location);
        }

        return $payload;
    }

    private function totalMembers(): int
    {
        return User::role('member')->count();
    }

    private function activeTrainerCount(): int
    {
        return User::role('coach')
            ->where('status', User::STATUS_ACTIVE)
            ->count();
    }

    private function activeEmployeeCount(): int
    {
        return User::role(['employee', 'manager', 'admin', 'staff'])
            ->where('status', User::STATUS_ACTIVE)
            ->count();
    }

    private function todayCheckInCount(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return Attendance::query()
            ->whereBetween('checked_in_at', [$start, $end])
            ->count();
    }

    private function walkInCountForWindow(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return WalkIn::query()
            ->whereBetween('visited_at', [$start, $end])
            ->count();
    }

    private function revenueForWindow(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $salesTotal = (float) SaleTransaction::query()
            ->whereBetween('sold_at', [$start, $end])
            ->sum('total');

        $directWalkInTotal = (float) $this->directWalkInQuery($start, $end)->sum('amount_paid');

        return round($salesTotal + $directWalkInTotal, 2);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function peakHours(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = Attendance::query()
            ->whereBetween('checked_in_at', [$start, $end])
            ->get(['checked_in_at'])
            ->map(fn (Attendance $attendance) => $attendance->checked_in_at ? (int) $attendance->checked_in_at->format('H') : null)
            ->filter(fn (?int $hour) => $hour !== null)
            ->countBy();

        return collect(range(0, 23))
            ->map(function (int $hour) use ($rows): array {
                $hourSlot = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';

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
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, int|string>>
     */
    private function locationLoadRows(array $location, \DateTimeInterface $todayStart, \DateTimeInterface $todayEnd): array
    {
        return [[
            'location_id' => $location['id'],
            'location_name' => $location['name'],
            'status' => $location['status'],
            'current_occupancy' => Attendance::query()->whereNull('checked_out_at')->count(),
            'today_check_ins' => Attendance::query()->whereBetween('checked_in_at', [$todayStart, $todayEnd])->count(),
        ]];
    }

    /**
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, mixed>>
     */
    private function checkInsToday(array $location, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return Attendance::query()
            ->whereBetween('checked_in_at', [$start, $end])
            ->with([
                'user.memberSubscriptions' => fn ($query) => $query
                    ->with(['ratePlan:id,name'])
                    ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PAUSED])
                    ->orderByDesc('start_date'),
                'user.roles',
                'walkIn.ratePlan:id,name',
            ])
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Attendance $attendance) use ($location): array {
                $membership = $this->loadedCurrentMembership($attendance->user);
                $employeeRole = $attendance->user?->roles
                    ? $attendance->user->roles->pluck('name')->filter()->implode(', ')
                    : null;

                return [
                    'id' => $attendance->id,
                    'name' => $attendance->name,
                    'attendee_type' => $attendance->attendee_type,
                    'attendee_type_label' => $this->attendanceTypeLabel($attendance->attendee_type),
                    'location_name' => $location['name'],
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
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, mixed>>
     */
    private function trainers(array $location): array
    {
        return User::role('coach')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (User $trainer) => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'status' => $trainer->status,
                'location_names' => [$location['name']],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, mixed>>
     */
    private function recentMembers(array $location): array
    {
        return User::role('member')
            ->with([
                'memberSubscriptions' => fn ($query) => $query
                    ->with(['ratePlan:id,name'])
                    ->orderByDesc('start_date'),
            ])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (User $member) use ($location): array {
                $membership = $this->loadedCurrentMembership($member);

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'plan_name' => $membership?->ratePlan?->name ?? '-',
                    'location_name' => $location['name'],
                    'status' => $member->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, mixed>>
     */
    private function recentSales(array $location): array
    {
        return SaleTransaction::query()
            ->with(['member:id,name'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (SaleTransaction $sale) => [
                'id' => $sale->id,
                'customer_name' => $sale->customer_name ?: $sale->member?->name ?: 'Walk-in Customer',
                'item_name' => $sale->item_name ?: str($sale->type)->replace('_', ' ')->title()->toString(),
                'location_name' => $location['name'],
                'total' => round((float) $sale->total, 2),
                'sold_at' => $sale->sold_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, mixed>>
     */
    private function expiringMemberships(array $location, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return MemberSubscription::query()
            ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PAUSED])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with(['member:id,name,status', 'ratePlan:id,name'])
            ->orderBy('end_date')
            ->limit(8)
            ->get()
            ->map(fn (MemberSubscription $subscription) => [
                'id' => $subscription->id,
                'member_name' => $subscription->member?->name,
                'plan_name' => $subscription->ratePlan?->name,
                'location_name' => $location['name'],
                'end_date' => $subscription->end_date?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{id:int, name:string, city:?string, province:?string, status:?string}  $location
     * @return array<int, array<string, mixed>>
     */
    private function pendingPayrolls(array $location): array
    {
        return Payroll::query()
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID])
            ->with([
                'employee:id,name',
                'employee.roles',
            ])
            ->withSum('payouts as total_paid', 'amount')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(function (Payroll $payroll) use ($location): array {
                $totalPaid = round((float) ($payroll->total_paid ?? 0), 2);
                $outstandingBalance = round(max(0, (float) $payroll->net_amount - $totalPaid), 2);

                return [
                    'id' => $payroll->id,
                    'employee_name' => $payroll->employee?->name,
                    'employee_role' => $payroll->employee?->roles?->pluck('name')->first() ?? 'Employee',
                    'location_name' => $location['name'],
                    'status' => $payroll->status,
                    'net_amount' => round((float) $payroll->net_amount, 2),
                    'outstanding_balance' => $outstandingBalance,
                    'period_label' => $payroll->period_start?->format('Y-m-d').' – '.$payroll->period_end?->format('Y-m-d'),
                ];
            })
            ->filter(fn (array $payroll) => $payroll['outstanding_balance'] > 0)
            ->take(8)
            ->values()
            ->all();
    }

    private function pendingPayrollBalance(): float
    {
        $netPayroll = (float) Payroll::query()
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID])
            ->sum('net_amount');
        $totalPaid = (float) Payout::query()
            ->join('payrolls', 'payrolls.id', '=', 'payouts.payroll_id')
            ->whereIn('payrolls.status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID])
            ->sum('payouts.amount');

        return round(max(0, $netPayroll - $totalPaid), 2);
    }

    private function directWalkInQuery(\DateTimeInterface $start, \DateTimeInterface $end)
    {
        $query = WalkIn::query()
            ->whereBetween('visited_at', [$start, $end]);

        $posBackedWalkInIds = SaleTransaction::query()
            ->where('type', SaleTransaction::TYPE_WALK_IN)
            ->whereBetween('sold_at', [$start, $end])
            ->get(['details'])
            ->map(fn (SaleTransaction $transaction) => (int) data_get($transaction->details, 'walk_in_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($posBackedWalkInIds !== []) {
            $query->whereNotIn('id', $posBackedWalkInIds);
        }

        return $query;
    }

    private function loadedCurrentMembership(?User $user): ?MemberSubscription
    {
        if ($user === null) {
            return null;
        }

        if (! $user->relationLoaded('memberSubscriptions')) {
            return $user->currentMembership();
        }

        return $user->memberSubscriptions
            ->first(fn (MemberSubscription $subscription) => in_array($subscription->status, [
                MemberSubscription::STATUS_ACTIVE,
                MemberSubscription::STATUS_PAUSED,
            ], true));
    }

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
