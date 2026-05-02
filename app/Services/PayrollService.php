<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\CashAdvance;
use App\Models\EmployeeProfile;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Payroll\Contracts\PayrollTaxProfile;
use App\Services\Payroll\Profiles\NullPayrollTaxProfile;
use Carbon\Carbon;

class PayrollService
{
    private const PH_NON_TAXABLE_BONUS_CAP = 90000.0;

    private const STANDARD_WORKDAY_HOURS = 8.0;

    private const STANDARD_WORKDAY_MINUTES = 480;

    public function __construct(
        private AuditHistoryService $auditHistoryService,
    ) {}

    /**
     * Compute net_amount from payroll components.
     */
    public function computeNet(
        float $gross,
        float $bonus,
        float $ptCommission,
        float $manualDed,
        float $caDed,
        float $membershipCommission = 0,
        float $incomeTax = 0,
        float $employeeContributionTotal = 0
    ): float {
        return round(
            max(
                0,
                $this->totalEarnings(
                    $gross,
                    $bonus,
                    $ptCommission,
                    $membershipCommission
                )
                - $this->totalEmployeeDeductionsBeforeCashAdvance(
                    $incomeTax,
                    $manualDed,
                    $employeeContributionTotal
                )
                - $caDed
            ),
            2
        );
    }

    public function totalEmployeeDeductionsBeforeCashAdvance(
        float $incomeTax,
        float $manualDed,
        float $employeeContributionTotal = 0
    ): float
    {
        return round(
            max(0, $incomeTax)
            + max(0, $manualDed)
            + max(0, $employeeContributionTotal),
            2
        );
    }

    public function totalEarnings(
        float $gross,
        float $bonus,
        float $ptCommission,
        float $membershipCommission = 0
    ): float {
        return round(
            max(
                0,
                $gross
                + $bonus
                + $ptCommission
                + $membershipCommission
            ),
            2
        );
    }

    public function taxableEarnings(
        float $gross,
        float $taxableBonus,
        float $ptCommission,
        float $membershipCommission = 0
    ): float {
        return round(
            max(
                0,
                $gross
                + $taxableBonus
                + $ptCommission
                + $membershipCommission
            ),
            2
        );
    }

    /**
     * Calculate the final payroll figures after applying the country tax profile.
     *
     * @param  array<string, mixed>  $contextOverrides
     * @return array{
     *     bonus_non_taxable_amount: float,
     *     bonus_taxable_amount: float,
     *     employee_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employee_contributions_total: float,
     *     employee_deductions_total: float,
     *     employer_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employer_contributions_total: float,
     *     income_tax: float,
     *     net_amount: float,
     *     remaining_bonus_exemption: float,
     *     taxable_earnings: float
     * }
     */
    public function calculatePayrollTotals(
        ?string $countryCode,
        ?string $payFrequency,
        float $gross,
        float $bonus,
        float $ptCommission,
        float $manualDed,
        float $caDed,
        float $membershipCommission = 0,
        array $context = []
    ): array {
        $bonusBreakdown = $this->bonusTaxBreakdown($countryCode, $bonus, $context);
        $taxProfile = $this->resolveTaxProfile($countryCode);
        $payrollCalculationSettings = $this->payrollCalculationSettings($context);
        $taxableEarnings = $this->taxableEarnings(
            $gross,
            $bonusBreakdown['bonus_taxable_amount'],
            $ptCommission,
            $membershipCommission
        );
        $governmentContributions = $payrollCalculationSettings['payroll_government_contributions_enabled']
            ? $taxProfile->calculateGovernmentContributions(
                $payFrequency,
                $this->governmentContributionInputs($countryCode, $payFrequency, $context)
            )
            : $this->emptyGovernmentContributions();
        $taxableEarnings = round(
            max(0, $taxableEarnings - $governmentContributions['employee_contributions_total']),
            2
        );
        $incomeTax = $payrollCalculationSettings['payroll_income_tax_enabled']
            ? $taxProfile->calculateIncomeTax($payFrequency, $taxableEarnings)
            : 0.0;
        $employeeDeductionsTotal = $this->totalEmployeeDeductionsBeforeCashAdvance(
            $incomeTax,
            $manualDed,
            $governmentContributions['employee_contributions_total']
        );

        return [
            'bonus_non_taxable_amount' => $bonusBreakdown['bonus_non_taxable_amount'],
            'bonus_taxable_amount' => $bonusBreakdown['bonus_taxable_amount'],
            'employee_contributions' => $governmentContributions['employee_contributions'],
            'employee_contributions_total' => $governmentContributions['employee_contributions_total'],
            'employer_contributions' => $governmentContributions['employer_contributions'],
            'employer_contributions_total' => $governmentContributions['employer_contributions_total'],
            'taxable_earnings' => $taxableEarnings,
            'income_tax' => $incomeTax,
            'employee_deductions_total' => $employeeDeductionsTotal,
            'remaining_bonus_exemption' => $bonusBreakdown['remaining_bonus_exemption'],
            'net_amount' => $this->computeNet(
                $gross,
                $bonus,
                $ptCommission,
                $manualDed,
                $caDed,
                $membershipCommission,
                $incomeTax,
                $governmentContributions['employee_contributions_total']
            ),
        ];
    }

    /**
     * Refresh a payroll's stored tax and net amount from its current inputs.
     *
     * @return array{
     *     bonus_non_taxable_amount: float,
     *     bonus_taxable_amount: float,
     *     employee_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employee_contributions_total: float,
     *     employee_deductions_total: float,
     *     employer_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employer_contributions_total: float,
     *     income_tax: float,
     *     net_amount: float,
     *     remaining_bonus_exemption: float,
     *     taxable_earnings: float
     * }
     */
    public function syncCalculatedAmounts(Payroll $payroll, array $contextOverrides = []): array
    {
        $payroll->loadMissing('employee.employeeProfile');

        $context = array_merge([
            'employee_id' => $payroll->employee_id,
            'employee_profile' => $this->employeeContributionProfile($payroll->employee?->employeeProfile),
            'business_profile' => $this->businessProfilePayrollSettings(BusinessProfile::current()),
            'payroll_id' => $payroll->id,
            'exclude_payroll_id' => $payroll->id,
            'period_start' => $payroll->period_start?->toDateString(),
            'period_end' => $payroll->period_end?->toDateString(),
        ], $contextOverrides);

        $totals = $this->calculatePayrollTotals(
            BusinessProfile::current()->country_code,
            $payroll->pay_frequency,
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
            (float) $payroll->pt_commission_amount,
            (float) $payroll->manual_deductions,
            (float) $payroll->cash_advance_deduction,
            (float) $payroll->membership_commission_amount,
            $context
        );

        $payroll->income_tax = $totals['income_tax'];
        $payroll->employee_contributions = $totals['employee_contributions'];
        $payroll->employer_contributions = $totals['employer_contributions'];
        $payroll->net_amount = $totals['net_amount'];
        $payroll->save();

        return $totals;
    }

    /**
     * Limit cash advance deduction to the payroll amount that remains payable after taxes.
     */
    public function maxCashAdvanceDeduction(
        int $employeeId,
        ?string $countryCode,
        ?string $payFrequency,
        float $gross,
        float $bonus,
        float $ptCommission,
        float $manualDed,
        float $membershipCommission = 0,
        array $context = []
    ): float {
        $payableBeforeCa = $this->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommission,
            $manualDed,
            0,
            $membershipCommission,
            $context
        );
        $pendingCa = $this->pendingCaTotal($employeeId);

        return round(min($payableBeforeCa['net_amount'], $pendingCa), 2);
    }

    /**
     * Clamp the stored cash advance deduction and recompute the payroll totals.
     */
    public function normalizePayrollCashAdvanceDeduction(Payroll $payroll): float
    {
        $maxDeduction = $this->maxCashAdvanceDeduction(
            $payroll->employee_id,
            BusinessProfile::current()->country_code,
            $payroll->pay_frequency,
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
            (float) $payroll->pt_commission_amount,
            (float) $payroll->manual_deductions,
            (float) $payroll->membership_commission_amount,
            [
                'employee_id' => $payroll->employee_id,
                'exclude_payroll_id' => $payroll->id,
                'period_end' => $payroll->period_end?->toDateString(),
            ]
        );

        $actualDeduction = round(min((float) $payroll->cash_advance_deduction, $maxDeduction), 2);

        if ((float) $payroll->cash_advance_deduction !== $actualDeduction) {
            $payroll->cash_advance_deduction = $actualDeduction;
        }

        $this->syncCalculatedAmounts($payroll);

        return $actualDeduction;
    }

    /**
     * Suggest gross amount and CA deduction based on attendance in a period.
     *
     * @return array{
     *     daily_rate: float,
     *     days_worked: int,
     *     gross_amount: float,
     *     open_attendance_count: int,
     *     overwork_hours: float,
     *     overwork_pay_amount: float,
     *     regular_hours: float,
     *     regular_pay_amount: float,
     *     suggested_ca: float
     * }
     */
    public function suggestFromAttendance(
        User $employee,
        string $periodStart,
        string $periodEnd,
        bool $payOverworkHours = false
    ): array {
        $employee->loadMissing('employeeProfile');

        $attendanceRecords = Attendance::query()
            ->where('attendee_type', Attendance::TYPE_EMPLOYEE)
            ->where('user_id', $employee->id)
            ->whereDate('checked_in_at', '>=', $periodStart)
            ->whereDate('checked_in_at', '<=', $periodEnd)
            ->get(['checked_in_at', 'checked_out_at']);

        $workedMinutesByDate = [];
        $openAttendanceCount = 0;

        foreach ($attendanceRecords as $attendance) {
            if (! $attendance->checked_in_at || ! $attendance->checked_out_at) {
                $openAttendanceCount++;

                continue;
            }

            $workDate = $attendance->checked_in_at->toDateString();
            $workedMinutesByDate[$workDate] = ($workedMinutesByDate[$workDate] ?? 0)
                + max(0, $attendance->checked_in_at->diffInMinutes($attendance->checked_out_at));
        }

        $regularMinutes = 0;
        $overworkMinutes = 0;

        foreach ($workedMinutesByDate as $workedMinutes) {
            $regularMinutes += min($workedMinutes, self::STANDARD_WORKDAY_MINUTES);

            if ($payOverworkHours) {
                $overworkMinutes += max(0, $workedMinutes - self::STANDARD_WORKDAY_MINUTES);
            }
        }

        $dailyRate = (float) ($employee->employeeProfile?->daily_rate ?? 0);
        $daysWorked = count($workedMinutesByDate);
        $regularHours = round($regularMinutes / 60, 2);
        $overworkHours = round($overworkMinutes / 60, 2);
        $regularPayAmount = round(
            $dailyRate * ($regularMinutes / self::STANDARD_WORKDAY_MINUTES),
            2
        );
        $overworkPayAmount = round(
            $dailyRate * ($overworkMinutes / self::STANDARD_WORKDAY_MINUTES),
            2
        );
        $gross = round($regularPayAmount + $overworkPayAmount, 2);
        $suggestedCa = $this->pendingCaTotal($employee->id);

        return [
            'days_worked' => $daysWorked,
            'daily_rate' => $dailyRate,
            'regular_hours' => $regularHours,
            'regular_pay_amount' => $regularPayAmount,
            'overwork_hours' => $overworkHours,
            'overwork_pay_amount' => $overworkPayAmount,
            'open_attendance_count' => $openAttendanceCount,
            'gross_amount' => $gross,
            'suggested_ca' => $suggestedCa,
        ];
    }

    public function manualGrossAdjustmentAmount(
        float $grossAmount,
        float $regularPayAmount,
        float $overworkPayAmount
    ): float {
        return round($grossAmount - $regularPayAmount - $overworkPayAmount, 2);
    }

    /**
     * Return the total remaining cash advance amount for an employee.
     * Used as the suggested deduction when creating a payroll.
     */
    public function pendingCaTotal(int $employeeId): float
    {
        return (float) CashAdvance::where('employee_id', $employeeId)
            ->whereIn('status', [CashAdvance::STATUS_RELEASED, CashAdvance::STATUS_PARTIALLY_PAID])
            ->sum('remaining_amount');
    }

    /**
     * Apply cash advance deductions when a payroll is approved.
     * Deducts from oldest advances first (FIFO), up to cash_advance_deduction.
     */
    public function applyAdvances(Payroll $payroll): void
    {
        $limit = (float) $payroll->cash_advance_deduction;
        if ($limit <= 0) {
            return;
        }

        $advances = CashAdvance::where('employee_id', $payroll->employee_id)
            ->whereIn('status', [CashAdvance::STATUS_RELEASED, CashAdvance::STATUS_PARTIALLY_PAID])
            ->with('employee:id,name')
            ->orderBy('requested_at')
            ->get();

        $remaining = $limit;

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $remainingBefore = (float) $advance->remaining_amount;
            $processedAt = now();
            $deduct = min($remainingBefore, $remaining);

            $advance->remaining_amount = round($remainingBefore - $deduct, 2);
            $advance->status = $advance->remaining_amount <= 0
                ? CashAdvance::STATUS_PAID
                : CashAdvance::STATUS_PARTIALLY_PAID;
            $advance->paid_at = $advance->status === CashAdvance::STATUS_PAID
                ? ($advance->paid_at ?? $processedAt)
                : null;
            $advance->save();

            $this->auditHistoryService->recordSubjectEvent(
                AuditEvent::SUBJECT_CASH_ADVANCE,
                $advance->id,
                $advance->status,
                [
                    'id' => $advance->id,
                    'employee_id' => $advance->employee_id,
                    'employee_name' => $advance->employee?->name ?? 'Unknown Employee',
                    'amount' => round((float) $advance->amount, 2),
                ],
                [
                    'source' => 'payroll',
                    'source_id' => $payroll->id,
                    'deducted_amount' => round($deduct, 2),
                    'remaining_before' => round($remainingBefore, 2),
                    'remaining_after' => round((float) $advance->remaining_amount, 2),
                ],
                auth()->id() ?? $payroll->approved_by,
                auth()->user()?->name,
                $processedAt,
            );
            $remaining -= $deduct;
        }
    }

    /**
     * Sync payroll status based on total payouts vs net_amount.
     * Only transitions from approved/partially_paid/paid states.
     */
    public function syncStatus(Payroll $payroll): void
    {
        if (in_array($payroll->status, [Payroll::STATUS_DRAFT, Payroll::STATUS_CANCELED], true)) {
            return;
        }

        $previousStatus = $payroll->status;
        $totalPaid = (float) $payroll->payouts()->sum('amount');

        $payroll->status = match (true) {
            (float) $payroll->net_amount <= 0 => Payroll::STATUS_PAID,
            $totalPaid <= 0 => Payroll::STATUS_APPROVED,
            $totalPaid >= (float) $payroll->net_amount => Payroll::STATUS_PAID,
            default => Payroll::STATUS_PARTIALLY_PAID,
        };

        $payroll->save();

        if ($payroll->status === Payroll::STATUS_PAID && $previousStatus !== Payroll::STATUS_PAID) {
            $this->markPtCommissionsAsPaid($payroll);
            $this->markMembershipCommissionsAsPaid($payroll);
        }
    }

    /**
     * @return array{amount: float, items: array<int, array<string, mixed>>}
     */
    public function previewPtCommissions(
        User $employee,
        string $periodStart,
        string $periodEnd,
        ?Payroll $payroll = null
    ): array {
        $packages = $this->ptCommissionPackageQuery($employee, $periodStart, $periodEnd, $payroll)
            ->with(['member:id,name', 'ptProduct:id,name'])
            ->orderBy('coach_commission_earned_at')
            ->get();

        return [
            'amount' => round($packages->sum('coach_commission_amount'), 2),
            'items' => $packages->map(fn (MemberPtPackage $package) => $this->serializePtCommissionItem($package))->values()->all(),
        ];
    }

    public function syncPtCommissions(Payroll $payroll): void
    {
        $payroll->loadMissing('employee');

        if (! $payroll->employee) {
            return;
        }

        $this->releasePtCommissions($payroll);

        $summary = $this->previewPtCommissions(
            $payroll->employee,
            $payroll->period_start->format('Y-m-d'),
            $payroll->period_end->format('Y-m-d'),
            $payroll
        );

        $packageIds = collect($summary['items'])
            ->pluck('package_id')
            ->filter()
            ->all();

        if ($packageIds !== []) {
            MemberPtPackage::query()
                ->whereIn('id', $packageIds)
                ->update([
                    'commission_payroll_id' => $payroll->id,
                ]);
        }

        $payroll->pt_commission_amount = $summary['amount'];
        $payroll->pt_commission_items = $summary['items'];
        $this->syncCalculatedAmounts($payroll);
    }

    public function syncMembershipCommissions(Payroll $payroll): void
    {
        $payroll->loadMissing('employee');

        if (! $payroll->employee) {
            return;
        }

        $this->releaseMembershipCommissions($payroll);

        $summary = $this->previewMembershipCommissions(
            $payroll->employee,
            $payroll->period_start->format('Y-m-d'),
            $payroll->period_end->format('Y-m-d'),
            $payroll
        );

        $subscriptionIds = collect($summary['items'])
            ->pluck('subscription_id')
            ->filter()
            ->all();

        if ($subscriptionIds !== []) {
            MemberSubscription::query()
                ->whereIn('id', $subscriptionIds)
                ->update([
                    'commission_payroll_id' => $payroll->id,
                ]);
        }

        $payroll->membership_commission_amount = $summary['amount'];
        $payroll->membership_commission_items = $summary['items'];
        $this->syncCalculatedAmounts($payroll);
    }

    public function syncMonthlyGovernmentContributionAllocation(Payroll $payroll): void
    {
        if ($payroll->pay_frequency !== 'semi_monthly' || ! $payroll->period_end) {
            return;
        }

        $periodEnd = $payroll->period_end->copy();
        $periodStartDate = $periodEnd->copy()->startOfMonth()->toDateString();
        $periodEndDate = $periodEnd->copy()->endOfMonth()->toDateString();

        $latestPayroll = Payroll::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('pay_frequency', 'semi_monthly')
            ->where('status', '!=', Payroll::STATUS_CANCELED)
            ->whereDate('period_end', '>=', $periodStartDate)
            ->whereDate('period_end', '<=', $periodEndDate)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->first(['id', 'status', 'period_end']);

        $allowFirstHalfFallback = $payroll->status === Payroll::STATUS_CANCELED;

        Payroll::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('pay_frequency', 'semi_monthly')
            ->where('status', Payroll::STATUS_DRAFT)
            ->whereDate('period_end', '>=', $periodStartDate)
            ->whereDate('period_end', '<=', $periodEndDate)
            ->with('employee.employeeProfile')
            ->orderBy('period_end')
            ->orderBy('id')
            ->get()
            ->each(function (Payroll $monthlyPayroll) use ($allowFirstHalfFallback, $latestPayroll): void {
                $forceGovernmentContributions = $allowFirstHalfFallback
                    && $latestPayroll?->status === Payroll::STATUS_DRAFT
                    && $monthlyPayroll->is($latestPayroll);

                $this->syncCalculatedAmounts($monthlyPayroll, [
                    'force_government_contributions' => $forceGovernmentContributions,
                ]);
            });
    }

    public function releasePtCommissions(Payroll $payroll): void
    {
        MemberPtPackage::query()
            ->where('commission_payroll_id', $payroll->id)
            ->whereIn('coach_commission_status', [
                MemberPtPackage::COMMISSION_STATUS_EARNED,
                MemberPtPackage::COMMISSION_STATUS_PENDING,
            ])
            ->update([
                'commission_payroll_id' => null,
            ]);
    }

    public function markPtCommissionsAsPaid(Payroll $payroll): void
    {
        MemberPtPackage::query()
            ->where('commission_payroll_id', $payroll->id)
            ->where('coach_commission_status', MemberPtPackage::COMMISSION_STATUS_EARNED)
            ->update([
                'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_PAID,
            ]);
    }

    /**
     * @return array{amount: float, items: array<int, array<string, mixed>>}
     */
    public function previewMembershipCommissions(
        User $employee,
        string $periodStart,
        string $periodEnd,
        ?Payroll $payroll = null
    ): array {
        $subscriptions = $this->membershipCommissionSubscriptionQuery($employee, $periodStart, $periodEnd, $payroll)
            ->with(['member:id,name', 'ratePlan:id,name'])
            ->orderBy('manager_commission_earned_at')
            ->get();

        return [
            'amount' => round($subscriptions->sum('manager_commission_amount'), 2),
            'items' => $subscriptions->map(
                fn (MemberSubscription $subscription) => $this->serializeMembershipCommissionItem($subscription)
            )->values()->all(),
        ];
    }

    public function releaseMembershipCommissions(Payroll $payroll): void
    {
        MemberSubscription::query()
            ->where('commission_payroll_id', $payroll->id)
            ->where('manager_commission_status', MemberSubscription::COMMISSION_STATUS_EARNED)
            ->update([
                'commission_payroll_id' => null,
            ]);
    }

    public function markMembershipCommissionsAsPaid(Payroll $payroll): void
    {
        MemberSubscription::query()
            ->where('commission_payroll_id', $payroll->id)
            ->where('manager_commission_status', MemberSubscription::COMMISSION_STATUS_EARNED)
            ->update([
                'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_PAID,
            ]);
    }

    private function ptCommissionPackageQuery(
        User $employee,
        string $periodStart,
        string $periodEnd,
        ?Payroll $payroll = null
    ) {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        return MemberPtPackage::query()
            ->where('coach_id', $employee->id)
            ->where('coach_commission_status', MemberPtPackage::COMMISSION_STATUS_EARNED)
            ->whereBetween('coach_commission_earned_at', [$start, $end])
            ->where(function ($query) use ($payroll) {
                $query->whereNull('commission_payroll_id');

                if ($payroll) {
                    $query->orWhere('commission_payroll_id', $payroll->id);
                }
            });
    }

    private function membershipCommissionSubscriptionQuery(
        User $employee,
        string $periodStart,
        string $periodEnd,
        ?Payroll $payroll = null
    ) {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        return MemberSubscription::query()
            ->where('manager_id', $employee->id)
            ->where('manager_commission_status', MemberSubscription::COMMISSION_STATUS_EARNED)
            ->whereBetween('manager_commission_earned_at', [$start, $end])
            ->where(function ($query) use ($payroll) {
                $query->whereNull('commission_payroll_id');

                if ($payroll) {
                    $query->orWhere('commission_payroll_id', $payroll->id);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePtCommissionItem(MemberPtPackage $package): array
    {
        return [
            'package_id' => $package->id,
            'member_id' => $package->user_id,
            'member_name' => $package->member?->name,
            'product_name' => $package->ptProduct?->name,
            'earned_at' => $package->coach_commission_earned_at?->toISOString(),
            'sold_price' => round((float) $package->sold_price, 2),
            'commission_rate' => round((float) $package->coach_commission_rate, 2),
            'commission_amount' => round((float) $package->coach_commission_amount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMembershipCommissionItem(MemberSubscription $subscription): array
    {
        return [
            'subscription_id' => $subscription->id,
            'member_id' => $subscription->user_id,
            'member_name' => $subscription->member?->name,
            'product_name' => $subscription->ratePlan?->name,
            'earned_at' => $subscription->manager_commission_earned_at?->toISOString(),
            'sold_price' => round((float) $subscription->sold_price, 2),
            'commission_rate' => round((float) $subscription->manager_commission_rate, 2),
            'commission_amount' => round((float) $subscription->manager_commission_amount, 2),
        ];
    }

    /**
     * @param  array{
     *     employee_id?: int,
     *     employee_profile?: array{
     *         sss_covered?: bool,
     *         sss_monthly_compensation?: float|int|string|null,
     *         philhealth_covered?: bool,
     *         philhealth_monthly_basic_salary?: float|int|string|null,
     *         pagibig_covered?: bool,
     *         pagibig_monthly_compensation?: float|int|string|null
     *     }|null,
     *     payroll_id?: int,
     *     exclude_payroll_id?: int,
     *     force_government_contributions?: bool,
     *     period_start?: string|null,
     *     period_end?: string|null
     * }  $context
     * @return array{
     *     apply: bool,
     *     sss_covered: bool,
     *     sss_monthly_compensation: float,
     *     philhealth_covered: bool,
     *     philhealth_monthly_basic_salary: float,
     *     pagibig_covered: bool,
     *     pagibig_monthly_compensation: float
     * }
     */
    private function governmentContributionInputs(?string $countryCode, ?string $payFrequency, array $context = []): array
    {
        $employeeProfile = $context['employee_profile'] ?? $this->employeeContributionProfile(
            EmployeeProfile::query()
                ->where('user_id', $context['employee_id'] ?? 0)
                ->first()
        );

        return [
            'apply' => $this->shouldApplyGovernmentContributions($countryCode, $payFrequency, $context),
            'sss_covered' => (bool) ($employeeProfile['sss_covered'] ?? false),
            'sss_monthly_compensation' => round((float) ($employeeProfile['sss_monthly_compensation'] ?? 0), 2),
            'philhealth_covered' => (bool) ($employeeProfile['philhealth_covered'] ?? false),
            'philhealth_monthly_basic_salary' => round((float) ($employeeProfile['philhealth_monthly_basic_salary'] ?? 0), 2),
            'pagibig_covered' => (bool) ($employeeProfile['pagibig_covered'] ?? false),
            'pagibig_monthly_compensation' => round((float) ($employeeProfile['pagibig_monthly_compensation'] ?? 0), 2),
        ];
    }

    /**
     * @param  array{
     *     business_profile?: array{payroll_income_tax_enabled?: bool, payroll_government_contributions_enabled?: bool}|BusinessProfile|null
     * }  $context
     * @return array{payroll_income_tax_enabled: bool, payroll_government_contributions_enabled: bool}
     */
    private function payrollCalculationSettings(array $context = []): array
    {
        $businessProfile = $context['business_profile'] ?? null;

        if ($businessProfile instanceof BusinessProfile) {
            return $this->businessProfilePayrollSettings($businessProfile);
        }

        if (! is_array($businessProfile)) {
            return $this->businessProfilePayrollSettings(BusinessProfile::current());
        }

        return [
            'payroll_income_tax_enabled' => (bool) ($businessProfile['payroll_income_tax_enabled'] ?? false),
            'payroll_government_contributions_enabled' => (bool) ($businessProfile['payroll_government_contributions_enabled'] ?? false),
        ];
    }

    /**
     * @return array{payroll_income_tax_enabled: bool, payroll_government_contributions_enabled: bool}
     */
    private function businessProfilePayrollSettings(?BusinessProfile $businessProfile): array
    {
        $profile = $businessProfile ?? BusinessProfile::current();

        return [
            'payroll_income_tax_enabled' => (bool) $profile->payroll_income_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $profile->payroll_government_contributions_enabled,
        ];
    }

    /**
     * @return array{
     *     employee_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employer_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employee_contributions_total: float,
     *     employer_contributions_total: float
     * }
     */
    private function emptyGovernmentContributions(): array
    {
        return [
            'employee_contributions' => [],
            'employer_contributions' => [],
            'employee_contributions_total' => 0.0,
            'employer_contributions_total' => 0.0,
        ];
    }

    /**
     * @param  array{employee_id?: int, payroll_id?: int, exclude_payroll_id?: int, force_government_contributions?: bool, period_start?: string|null, period_end?: string|null}  $context
     */
    private function shouldApplyGovernmentContributions(?string $countryCode, ?string $payFrequency, array $context = []): bool
    {
        if (strtoupper((string) $countryCode) !== BusinessProfile::COUNTRY_PHILIPPINES) {
            return true;
        }

        if ($payFrequency !== 'semi_monthly') {
            return true;
        }

        if (empty($context['employee_id']) || empty($context['period_end'])) {
            return false;
        }

        $periodEnd = Carbon::parse((string) $context['period_end']);

        if ((bool) ($context['force_government_contributions'] ?? false)) {
            return true;
        }

        $employeeId = (int) $context['employee_id'];
        $excludePayrollId = $context['payroll_id'] ?? $context['exclude_payroll_id'] ?? null;

        if ($periodEnd->day < 16) {
            return $this->shouldApplyFirstHalfGovernmentContributionFallback(
                $employeeId,
                $periodEnd,
                $excludePayrollId
            );
        }

        if ($this->hasFinalizedGovernmentContributionPayrollInMonth($employeeId, $periodEnd, $excludePayrollId)) {
            return false;
        }

        return ! $this->hasLaterSemiMonthlyPayrollInMonth(
            $employeeId,
            $periodEnd,
            $excludePayrollId
        );
    }

    private function shouldApplyFirstHalfGovernmentContributionFallback(
        int $employeeId,
        Carbon $periodEnd,
        ?int $excludePayrollId = null
    ): bool {
        if ($this->hasLaterSemiMonthlyPayrollInMonth($employeeId, $periodEnd, $excludePayrollId)) {
            return false;
        }

        return Payroll::query()
            ->where('employee_id', $employeeId)
            ->where('pay_frequency', 'semi_monthly')
            ->where('status', Payroll::STATUS_CANCELED)
            ->whereDate('period_end', '>', $periodEnd->toDateString())
            ->whereDate('period_end', '<=', $periodEnd->copy()->endOfMonth()->toDateString())
            ->exists();
    }

    private function hasFinalizedGovernmentContributionPayrollInMonth(
        int $employeeId,
        Carbon $periodEnd,
        ?int $excludePayrollId = null
    ): bool {
        return Payroll::query()
            ->where('employee_id', $employeeId)
            ->where('pay_frequency', 'semi_monthly')
            ->whereNotIn('status', [Payroll::STATUS_DRAFT, Payroll::STATUS_CANCELED])
            ->when($excludePayrollId, fn ($query) => $query->whereKeyNot($excludePayrollId))
            ->whereDate('period_end', '>=', $periodEnd->copy()->startOfMonth()->toDateString())
            ->whereDate('period_end', '<=', $periodEnd->copy()->endOfMonth()->toDateString())
            ->get(['id', 'employee_contributions'])
            ->contains(fn (Payroll $payroll): bool => $payroll->employeeContributionsTotal() > 0);
    }

    private function hasLaterSemiMonthlyPayrollInMonth(
        int $employeeId,
        Carbon $periodEnd,
        ?int $excludePayrollId = null
    ): bool {
        return Payroll::query()
            ->where('employee_id', $employeeId)
            ->where('pay_frequency', 'semi_monthly')
            ->where('status', '!=', Payroll::STATUS_CANCELED)
            ->when($excludePayrollId, fn ($query) => $query->whereKeyNot($excludePayrollId))
            ->whereDate('period_end', '>=', $periodEnd->copy()->startOfMonth()->toDateString())
            ->whereDate('period_end', '<=', $periodEnd->copy()->endOfMonth()->toDateString())
            ->where(function ($query) use ($periodEnd, $excludePayrollId): void {
                $query->whereDate('period_end', '>', $periodEnd->toDateString())
                    ->orWhere(function ($sameDateQuery) use ($periodEnd, $excludePayrollId): void {
                        $sameDateQuery->whereDate('period_end', '=', $periodEnd->toDateString());

                        if ($excludePayrollId) {
                            $sameDateQuery->where(function ($tieQuery) use ($excludePayrollId): void {
                                $tieQuery->where('status', '!=', Payroll::STATUS_DRAFT);
                                $tieQuery->orWhere('id', '>', $excludePayrollId);
                            });
                        } else {
                            $sameDateQuery->where('status', '!=', Payroll::STATUS_DRAFT);
                        }
                    });
            })
            ->exists();
    }

    /**
     * @return array{
     *     sss_covered: bool,
     *     sss_monthly_compensation: float,
     *     philhealth_covered: bool,
     *     philhealth_monthly_basic_salary: float,
     *     pagibig_covered: bool,
     *     pagibig_monthly_compensation: float
     * }|null
     */
    private function employeeContributionProfile(?EmployeeProfile $employeeProfile): ?array
    {
        if (! $employeeProfile) {
            return null;
        }

        return [
            'sss_covered' => (bool) $employeeProfile->sss_covered,
            'sss_monthly_compensation' => round((float) ($employeeProfile->sss_monthly_compensation ?? 0), 2),
            'philhealth_covered' => (bool) $employeeProfile->philhealth_covered,
            'philhealth_monthly_basic_salary' => round((float) ($employeeProfile->philhealth_monthly_basic_salary ?? 0), 2),
            'pagibig_covered' => (bool) $employeeProfile->pagibig_covered,
            'pagibig_monthly_compensation' => round((float) ($employeeProfile->pagibig_monthly_compensation ?? 0), 2),
        ];
    }

    /**
     * Apply the Philippine 13th month and other benefits exemption cap to payroll bonuses.
     *
     * @param  array{employee_id?: int, exclude_payroll_id?: int, period_end?: string|null}  $context
     * @return array{bonus_non_taxable_amount: float, bonus_taxable_amount: float, remaining_bonus_exemption: float}
     */
    private function bonusTaxBreakdown(?string $countryCode, float $bonus, array $context = []): array
    {
        $normalizedBonus = round(max(0, $bonus), 2);
        $isPhilippines = strtoupper((string) $countryCode) === BusinessProfile::COUNTRY_PHILIPPINES;

        if (! $isPhilippines) {
            return [
                'bonus_non_taxable_amount' => 0.0,
                'bonus_taxable_amount' => $normalizedBonus,
                'remaining_bonus_exemption' => 0.0,
            ];
        }

        $priorBonusUsage = $this->philippinesBonusUsageForYear(
            $context['employee_id'] ?? null,
            $context['period_end'] ?? null,
            $context['exclude_payroll_id'] ?? null
        );
        $remainingBonusExemption = round(max(0, self::PH_NON_TAXABLE_BONUS_CAP - $priorBonusUsage), 2);

        if ($normalizedBonus <= 0) {
            return [
                'bonus_non_taxable_amount' => 0.0,
                'bonus_taxable_amount' => 0.0,
                'remaining_bonus_exemption' => $remainingBonusExemption,
            ];
        }
        $nonTaxableBonus = round(min($normalizedBonus, $remainingBonusExemption), 2);

        return [
            'bonus_non_taxable_amount' => $nonTaxableBonus,
            'bonus_taxable_amount' => round($normalizedBonus - $nonTaxableBonus, 2),
            'remaining_bonus_exemption' => $remainingBonusExemption,
        ];
    }

    private function philippinesBonusUsageForYear(?int $employeeId, ?string $periodEnd, ?int $excludePayrollId = null): float
    {
        if (! $employeeId || ! $periodEnd) {
            return 0.0;
        }

        $periodEndDate = Carbon::parse($periodEnd)->endOfDay();

        return round((float) Payroll::query()
            ->where('employee_id', $employeeId)
            ->where('status', '!=', Payroll::STATUS_CANCELED)
            ->when($excludePayrollId, fn ($query) => $query->whereKeyNot($excludePayrollId))
            ->whereDate('period_end', '>=', $periodEndDate->copy()->startOfYear()->toDateString())
            ->whereDate('period_end', '<=', $periodEndDate->toDateString())
            ->sum('bonus'), 2);
    }

    /**
     * Resolve the tax profile configured for the business country code.
     */
    private function resolveTaxProfile(?string $countryCode): PayrollTaxProfile
    {
        $profileClass = config('payroll.tax_profiles.'.strtoupper((string) $countryCode))
            ?? config('payroll.default_tax_profile', NullPayrollTaxProfile::class);

        if (! is_string($profileClass) || ! class_exists($profileClass) || ! is_a($profileClass, PayrollTaxProfile::class, true)) {
            $profileClass = NullPayrollTaxProfile::class;
        }

        return new $profileClass;
    }
}
