<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\MemberPtPackage;
use App\Models\Payroll;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\Payroll\Contracts\PayrollTaxProfile;
use App\Services\Payroll\Profiles\NullPayrollTaxProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    private const STANDARD_WORKDAY_HOURS = 8.0;

    private const STANDARD_WORKDAY_MINUTES = 480;

    /**
     * Compute net_amount from payroll components.
     */
    public function computeNet(
        float $gross,
        float $manualDed,
        float $withholdingTax = 0,
        float $employeeContributionTotal = 0
    ): float {
        return round(
            max(
                0,
                $this->totalEarnings($gross)
                - $this->totalEmployeeDeductions(
                    $withholdingTax,
                    $manualDed,
                    $employeeContributionTotal
                )
            ),
            2
        );
    }

    public function totalEmployeeDeductions(
        float $withholdingTax,
        float $manualDed,
        float $employeeContributionTotal = 0
    ): float {
        return round(
            max(0, $withholdingTax)
            + max(0, $manualDed)
            + max(0, $employeeContributionTotal),
            2
        );
    }

    public function totalEarnings(
        float $gross
    ): float {
        return round(max(0, $gross), 2);
    }

    public function taxableEarnings(
        float $gross
    ): float {
        return round(max(0, $gross), 2);
    }

    /**
     * Calculate the final payroll figures after applying the country tax profile.
     *
     * @param  array{employee_profile?: array<string, mixed>|null, business_profile?: array<string, mixed>|BusinessProfile|null}  $context
     * @return array{
     *     employee_contributions: array<string, array{label: string, total: float}>,
     *     employee_contributions_total: float,
     *     employee_deductions_total: float,
     *     employer_contributions: array<string, array{label: string, total: float}>,
     *     employer_contributions_total: float,
     *     withholding_tax: float,
     *     net_amount: float,
     *     taxable_earnings: float
     * }
     */
    public function calculatePayrollTotals(
        ?string $countryCode,
        ?string $payFrequency,
        float $gross,
        float $manualDed,
        array $context = [],
        float $cashAdvanceDed = 0
    ): array {
        $manualDed += max(0, $cashAdvanceDed);
        $taxProfile = $this->resolveTaxProfile($countryCode);
        $payrollCalculationSettings = $this->payrollCalculationSettings($context);
        $taxableEarnings = $this->taxableEarnings($gross);
        $governmentContributions = $payrollCalculationSettings['payroll_government_contributions_enabled']
            ? $taxProfile->calculateGovernmentContributions($payFrequency, $context['employee_profile'] ?? [])
            : $this->emptyGovernmentContributions();
        $taxableEarnings = round(
            max(0, $taxableEarnings - $governmentContributions['employee_contributions_total']),
            2
        );
        $withholdingTax = $payrollCalculationSettings['payroll_withholding_tax_enabled']
            ? $taxProfile->calculateWithholdingTax($payFrequency, $taxableEarnings)
            : 0.0;
        $employeeDeductionsTotal = $this->totalEmployeeDeductions(
            $withholdingTax,
            $manualDed,
            $governmentContributions['employee_contributions_total']
        );

        return [
            'employee_contributions' => $governmentContributions['employee_contributions'],
            'employee_contributions_total' => $governmentContributions['employee_contributions_total'],
            'employer_contributions' => $governmentContributions['employer_contributions'],
            'employer_contributions_total' => $governmentContributions['employer_contributions_total'],
            'taxable_earnings' => $taxableEarnings,
            'withholding_tax' => $withholdingTax,
            'employee_deductions_total' => $employeeDeductionsTotal,
            'net_amount' => $this->computeNet(
                $gross,
                $manualDed,
                $withholdingTax,
                $governmentContributions['employee_contributions_total']
            ),
        ];
    }

    /**
     * Suggest gross amount based on attendance in a period.
     *
     * @return array{
     *     daily_rate: float,
     *     days: list<array{date: string, time_in: ?string, time_out: ?string, scheduled_hours: float, worked_hours: float, paid_hours: float, day_pay_amount: float, status: string, late_minutes: int}>,
     *     days_worked: int,
     *     gross_amount: float,
     *     open_attendance_count: int,
     *     overwork_hours: float,
     *     overwork_pay_amount: float,
     *     regular_hours: float,
     *     regular_pay_amount: float
     * }
     */
    public function suggestFromAttendance(
        User $employee,
        string $periodStart,
        string $periodEnd,
        bool $payOverworkHours = false
    ): array {
        $employee->loadMissing('employeeProfile.scheduleShifts');

        $attendanceRecords = Attendance::query()
            ->where('attendee_type', Attendance::TYPE_EMPLOYEE)
            ->where('user_id', $employee->id)
            ->whereDate('checked_in_at', '>=', $periodStart)
            ->whereDate('checked_in_at', '<=', $periodEnd)
            ->get(['checked_in_at', 'checked_out_at']);

        if ($employee->employeeProfile?->scheduleShifts->isNotEmpty()) {
            return $this->suggestFromScheduledAttendance(
                $employee,
                $attendanceRecords,
                $payOverworkHours,
                $periodStart,
                $periodEnd
            );
        }

        $workedMinutesByDate = [];
        $timesByDate = [];
        $openAttendanceCount = 0;

        foreach ($attendanceRecords as $attendance) {
            if (! $attendance->checked_in_at || ! $attendance->checked_out_at) {
                $openAttendanceCount++;

                continue;
            }

            $workDate = $attendance->checked_in_at->toDateString();
            $workedMinutesByDate[$workDate] = ($workedMinutesByDate[$workDate] ?? 0) + $attendance->workedMinutes();

            $times = $timesByDate[$workDate] ?? null;
            $timesByDate[$workDate] = [
                'in' => $times && $times['in']->lessThan($attendance->checked_in_at) ? $times['in'] : $attendance->checked_in_at,
                'out' => $times && $times['out']->greaterThan($attendance->checked_out_at) ? $times['out'] : $attendance->checked_out_at,
            ];
        }

        ksort($workedMinutesByDate);

        $dailyRate = (float) ($employee->employeeProfile?->daily_rate ?? 0);
        $regularMinutes = 0;
        $overworkMinutes = 0;
        $days = [];

        foreach ($workedMinutesByDate as $workDate => $workedMinutes) {
            $paidMinutes = min($workedMinutes, self::STANDARD_WORKDAY_MINUTES);
            $dayOverworkMinutes = $payOverworkHours ? max(0, $workedMinutes - self::STANDARD_WORKDAY_MINUTES) : 0;
            $regularMinutes += $paidMinutes;
            $overworkMinutes += $dayOverworkMinutes;

            $days[] = $this->dayRow(
                $workDate,
                0,
                $workedMinutes,
                $paidMinutes,
                round($dailyRate * (($paidMinutes + $dayOverworkMinutes) / self::STANDARD_WORKDAY_MINUTES), 2),
                'unscheduled',
                0,
                $timesByDate[$workDate]['in'] ?? null,
                $timesByDate[$workDate]['out'] ?? null
            );
        }

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

        return [
            'days_worked' => $daysWorked,
            'daily_rate' => $dailyRate,
            'regular_hours' => $regularHours,
            'regular_pay_amount' => $regularPayAmount,
            'overwork_hours' => $overworkHours,
            'overwork_pay_amount' => $overworkPayAmount,
            'open_attendance_count' => $openAttendanceCount,
            'gross_amount' => $gross,
            'days' => $days,
        ];
    }

    /**
     * Suggest gross amount from attendance using the employee's saved schedule.
     *
     * A late check-in slides the whole scheduled window forward ("shift refill"):
     * scheduled 06:00-14:00 with a 08:00 check-in is payable until 16:00. Time
     * before the scheduled start is never paid as regular hours.
     * @return array{
     *     daily_rate: float,
     *     days: list<array{date: string, time_in: ?string, time_out: ?string, scheduled_hours: float, worked_hours: float, paid_hours: float, day_pay_amount: float, status: string, late_minutes: int}>,
     *     days_worked: int,
     *     gross_amount: float,
     *     open_attendance_count: int,
     *     overwork_hours: float,
     *     overwork_pay_amount: float,
     *     regular_hours: float,
     *     regular_pay_amount: float
     * }
     *
     * @param  Collection<int, Attendance>  $attendanceRecords
     */
    private function suggestFromScheduledAttendance(
        User $employee,
        Collection $attendanceRecords,
        bool $payOverworkHours,
        string $periodStart,
        string $periodEnd
    ): array {
        $attendanceIntervalsByDate = [];
        $openAttendanceCount = 0;
        $openOnlyDates = [];

        foreach ($attendanceRecords as $attendance) {
            if (! $attendance->checked_in_at || ! $attendance->checked_out_at) {
                $openAttendanceCount++;

                if ($attendance->checked_in_at) {
                    $openOnlyDates[$attendance->checked_in_at->toDateString()] = true;
                }

                continue;
            }

            $workDate = $attendance->checked_in_at->toDateString();
            $attendanceIntervalsByDate[$workDate][] = [
                'start' => $attendance->checked_in_at,
                'end' => $attendance->checked_out_at,
            ];
        }

        $dailyRate = (float) ($employee->employeeProfile?->daily_rate ?? 0);
        $regularMinutes = 0;
        $overworkMinutes = 0;
        $days = [];
        $today = now()->toDateString();

        $cursor = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->startOfDay();

        for (; $cursor->lte($endDate); $cursor->addDay()) {
            $workDate = $cursor->toDateString();
            $scheduleIntervals = $this->scheduleIntervalsForDate($employee, $workDate);
            $attendanceIntervals = $attendanceIntervalsByDate[$workDate] ?? [];

            if ($attendanceIntervals === []) {
                // A day with only an open check-in isn't absent; the
                // open_attendance_count warning already covers it.
                if ($scheduleIntervals !== [] && $workDate <= $today && ! isset($openOnlyDates[$workDate])) {
                    $days[] = $this->dayRow($workDate, $this->sumIntervalMinutes($scheduleIntervals), 0, 0, 0.0, 'absent', 0, null, null);
                }

                continue;
            }

            $workedMinutes = $this->sumIntervalMinutes($attendanceIntervals);

            if ($scheduleIntervals === []) {
                $dayOverworkMinutes = $payOverworkHours ? $workedMinutes : 0;
                $overworkMinutes += $dayOverworkMinutes;

                $days[] = $this->dayRow(
                    $workDate,
                    0,
                    $workedMinutes,
                    0,
                    round($dailyRate * ($dayOverworkMinutes / self::STANDARD_WORKDAY_MINUTES), 2),
                    'unscheduled',
                    0,
                    collect($attendanceIntervals)->min('start'),
                    collect($attendanceIntervals)->max('end')
                );

                continue;
            }

            $firstCheckIn = collect($attendanceIntervals)->min('start');
            $scheduledStart = collect($scheduleIntervals)->min('start');
            $lateMinutes = max(0, (int) floor($scheduledStart->diffInMinutes($firstCheckIn, false)));

            // ponytail: all of the day's shifts slide together by the lateness;
            // per-shift sliding if split-shift lateness ever needs finer handling.
            $slidIntervals = array_map(fn (array $interval): array => [
                'start' => $interval['start']->copy()->addMinutes($lateMinutes),
                'end' => $interval['end']->copy()->addMinutes($lateMinutes),
            ], $scheduleIntervals);

            // Overlapping attendance rows (double punches) can double-count here,
            // but the scheduled-minutes cap below bounds the damage.
            $paidRegularMinutes = 0;

            foreach ($attendanceIntervals as $attendanceInterval) {
                foreach ($slidIntervals as $slidInterval) {
                    $overlapStart = $attendanceInterval['start']->greaterThan($slidInterval['start'])
                        ? $attendanceInterval['start']
                        : $slidInterval['start'];
                    $overlapEnd = $attendanceInterval['end']->lessThan($slidInterval['end'])
                        ? $attendanceInterval['end']
                        : $slidInterval['end'];

                    if ($overlapStart->lessThan($overlapEnd)) {
                        $paidRegularMinutes += (int) floor($overlapStart->diffInMinutes($overlapEnd));
                    }
                }
            }

            $scheduledRegularMinutes = min(
                $this->sumIntervalMinutes($scheduleIntervals),
                self::STANDARD_WORKDAY_MINUTES
            );
            $paidRegularMinutes = min($paidRegularMinutes, $scheduledRegularMinutes);
            $regularMinutes += $paidRegularMinutes;

            $dayOverworkMinutes = $payOverworkHours ? max(0, $workedMinutes - $paidRegularMinutes) : 0;
            $overworkMinutes += $dayOverworkMinutes;

            $status = match (true) {
                $paidRegularMinutes < $scheduledRegularMinutes => 'undertime',
                $lateMinutes > 0 => 'late',
                default => 'full',
            };

            $days[] = $this->dayRow(
                $workDate,
                $this->sumIntervalMinutes($scheduleIntervals),
                $workedMinutes,
                $paidRegularMinutes,
                round($dailyRate * (($paidRegularMinutes + $dayOverworkMinutes) / self::STANDARD_WORKDAY_MINUTES), 2),
                $status,
                $lateMinutes,
                $firstCheckIn,
                collect($attendanceIntervals)->max('end')
            );
        }

        $daysWorked = count($attendanceIntervalsByDate);
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

        return [
            'days_worked' => $daysWorked,
            'daily_rate' => $dailyRate,
            'regular_hours' => $regularHours,
            'regular_pay_amount' => $regularPayAmount,
            'overwork_hours' => $overworkHours,
            'overwork_pay_amount' => $overworkPayAmount,
            'open_attendance_count' => $openAttendanceCount,
            'gross_amount' => $gross,
            'days' => $days,
        ];
    }

    /**
     * @return array{date: string, time_in: ?string, time_out: ?string, scheduled_hours: float, worked_hours: float, paid_hours: float, day_pay_amount: float, status: string, late_minutes: int}
     */
    private function dayRow(
        string $date,
        int $scheduledMinutes,
        int $workedMinutes,
        int $paidMinutes,
        float $payAmount,
        string $status,
        int $lateMinutes,
        ?Carbon $timeIn,
        ?Carbon $timeOut
    ): array {
        return [
            'date' => $date,
            'time_in' => $timeIn?->toIso8601String(),
            'time_out' => $timeOut?->toIso8601String(),
            'scheduled_hours' => round($scheduledMinutes / 60, 2),
            'worked_hours' => round($workedMinutes / 60, 2),
            'paid_hours' => round($paidMinutes / 60, 2),
            'day_pay_amount' => $payAmount,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ];
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function scheduleIntervalsForDate(User $employee, string $workDate): array
    {
        $dayOfWeek = Carbon::parse($workDate)->dayOfWeek;

        return $employee->employeeProfile?->scheduleShifts
            ->where('day_of_week', $dayOfWeek)
            ->map(fn ($shift): array => [
                'start' => Carbon::parse($workDate.' '.$shift->start_time),
                'end' => Carbon::parse($workDate.' '.$shift->end_time),
            ])
            ->values()
            ->all() ?? [];
    }

    /**
     * @param  list<array{start: Carbon, end: Carbon}>  $intervals
     */
    private function sumIntervalMinutes(array $intervals): int
    {
        return array_reduce(
            $intervals,
            fn (int $total, array $interval): int => $total + (int) floor(
                max(0, $interval['start']->diffInMinutes($interval['end']))
            ),
            0
        );
    }

    public function manualGrossAdjustmentAmount(
        float $grossAmount,
        float $regularPayAmount,
        float $overworkPayAmount,
        float $commissionAmount
    ): float {
        return round($grossAmount - $regularPayAmount - $overworkPayAmount - $commissionAmount, 2);
    }

    /**
     * PT plan commissions earned by a coach within a payroll period. One line
     * per completed PT package sale, with the rate read from the employee
     * profile at computation time; the caller freezes the result.
     *
     * @return array{
     *     amount: float,
     *     sales: list<array{sale_transaction_id: int, member_pt_package_id: int, date: ?string, member_name: ?string, plan_name: ?string, sold_price: float, rate: float, amount: float}>
     * }
     */
    public function suggestPtCommissions(User $employee, string $periodStart, string $periodEnd): array
    {
        $rate = round((float) ($employee->employeeProfile?->pt_commission_rate ?? 0), 2);

        if ($rate <= 0) {
            return ['amount' => 0.0, 'sales' => []];
        }

        $sales = MemberPtPackage::query()
            ->with([
                'member:id,name',
                'ptProduct:id,name',
                'saleTransaction:id,status,sold_at',
            ])
            ->where('coach_id', $employee->id)
            ->where('status', '!=', MemberPtPackage::STATUS_CANCELLED)
            ->where('sold_price', '>', 0)
            ->whereHas('saleTransaction', fn ($query) => $query
                ->where('type', SaleTransaction::TYPE_PT_PACKAGE)
                ->where('status', SaleTransaction::STATUS_COMPLETED)
                ->whereDate('sold_at', '>=', $periodStart)
                ->whereDate('sold_at', '<=', $periodEnd))
            ->get()
            ->sortBy(fn (MemberPtPackage $package): ?string => $package->saleTransaction?->sold_at?->toDateTimeString())
            ->map(fn (MemberPtPackage $package): array => [
                'sale_transaction_id' => $package->sale_transaction_id,
                'member_pt_package_id' => $package->id,
                'date' => $package->saleTransaction?->sold_at?->toDateString(),
                'member_name' => $package->member?->name,
                'plan_name' => $package->ptProduct?->name,
                'sold_price' => round((float) $package->sold_price, 2),
                'rate' => $rate,
                'amount' => round((float) $package->sold_price * $rate / 100, 2),
            ])
            ->values()
            ->all();

        return [
            'amount' => round(array_sum(array_column($sales, 'amount')), 2),
            'sales' => $sales,
        ];
    }

    /**
     * Find a non-cancelled payroll that already snapshots this package's commission.
     *
     * The caller must run inside a database transaction so the selected payroll
     * rows remain locked through the cancellation decision.
     *
     * @return ?\App\Models\Payroll
     */
    public function commissionPayrollBlockingCancellation(MemberPtPackage $package): ?Payroll
    {
        if ($package->coach_id === null) {
            return null;
        }

        $package->loadMissing(['saleTransaction:id,sold_at', 'member:id,name', 'ptProduct:id,name']);

        return Payroll::query()
            ->where('employee_id', $package->coach_id)
            ->where('status', '!=', Payroll::STATUS_CANCELED)
            ->where('commission_amount', '>', 0)
            ->lockForUpdate()
            ->get()
            ->first(fn (Payroll $payroll): bool => $this->payrollContainsPtPackage($payroll, $package));
    }

    /**
     * Prevent a stale draft payroll from being approved after a PT sale was voided.
     *
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertPtCommissionSalesRemainEligible(Payroll $payroll): void
    {
        $references = collect($payroll->commission_details ?? [])
            ->filter(fn (mixed $detail): bool => is_array($detail)
                && ((int) ($detail['member_pt_package_id'] ?? 0) > 0
                    || (int) ($detail['sale_transaction_id'] ?? 0) > 0))
            ->values();

        if ($references->isEmpty()) {
            return;
        }

        $packageIds = $references
            ->pluck('member_pt_package_id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $saleTransactionIds = $references
            ->pluck('sale_transaction_id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $packages = MemberPtPackage::query()
            ->with('saleTransaction:id,type,status')
            ->where(function ($query) use ($packageIds, $saleTransactionIds): void {
                if ($packageIds->isNotEmpty()) {
                    $query->whereIn('id', $packageIds);
                }

                if ($saleTransactionIds->isNotEmpty()) {
                    $method = $packageIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('sale_transaction_id', $saleTransactionIds);
                }
            })
            ->get();

        foreach ($references as $reference) {
            $packageId = (int) ($reference['member_pt_package_id'] ?? 0);
            $saleTransactionId = (int) ($reference['sale_transaction_id'] ?? 0);
            $package = $packages->first(fn (MemberPtPackage $candidate): bool => (
                $packageId > 0 && $candidate->id === $packageId
            ) || (
                $saleTransactionId > 0 && $candidate->sale_transaction_id === $saleTransactionId
            ));
            $saleTransaction = $package?->saleTransaction;

            if (! $package
                || $package->status === MemberPtPackage::STATUS_CANCELLED
                || ! $saleTransaction
                || ($saleTransactionId > 0 && $saleTransaction->id !== $saleTransactionId)
                || $saleTransaction->type !== SaleTransaction::TYPE_PT_PACKAGE
                || $saleTransaction->status !== SaleTransaction::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'payroll' => ['This payroll contains a PT commission whose sale is no longer completed. Update or cancel the draft payroll before approval.'],
                ]);
            }
        }
    }

    /**
     * Determine whether a payroll commission snapshot references a PT package.
     *
     * @return bool
     */
    private function payrollContainsPtPackage(Payroll $payroll, MemberPtPackage $package): bool
    {
        foreach ($payroll->commission_details ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $packageId = (int) ($detail['member_pt_package_id'] ?? 0);
            $saleTransactionId = (int) ($detail['sale_transaction_id'] ?? 0);

            if ($packageId > 0 || $saleTransactionId > 0) {
                if (($packageId > 0 && $packageId === $package->id)
                    || ($saleTransactionId > 0 && $saleTransactionId === $package->sale_transaction_id)) {
                    return true;
                }

                continue;
            }

            $commissionDate = $detail['date'] ?? null;
            $candidateDates = array_filter([
                $package->assigned_at?->toDateString(),
                $package->saleTransaction?->sold_at?->toDateString(),
            ]);
            $soldPrice = round((float) ($detail['sold_price'] ?? 0), 2);

            if (in_array($commissionDate, $candidateDates, true)
                && abs($soldPrice - round((float) $package->sold_price, 2)) < 0.01) {
                return true;
            }
        }

        return false;
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

        $totalPaid = (float) $payroll->payouts()->sum('amount');

        $payroll->status = match (true) {
            (float) $payroll->net_amount <= 0 => Payroll::STATUS_PAID,
            $totalPaid <= 0 => Payroll::STATUS_APPROVED,
            $totalPaid >= (float) $payroll->net_amount => Payroll::STATUS_PAID,
            default => Payroll::STATUS_PARTIALLY_PAID,
        };

        $payroll->save();
    }

    /**
     * @param  array{
     *     business_profile?: array{payroll_withholding_tax_enabled?: bool, payroll_government_contributions_enabled?: bool}|BusinessProfile|null
     * }  $context
     * @return array{payroll_withholding_tax_enabled: bool, payroll_government_contributions_enabled: bool}
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
            'payroll_withholding_tax_enabled' => (bool) ($businessProfile['payroll_withholding_tax_enabled'] ?? false),
            'payroll_government_contributions_enabled' => (bool) ($businessProfile['payroll_government_contributions_enabled'] ?? false),
        ];
    }

    /**
     * @return array{payroll_withholding_tax_enabled: bool, payroll_government_contributions_enabled: bool}
     */
    private function businessProfilePayrollSettings(?BusinessProfile $businessProfile): array
    {
        $profile = $businessProfile ?? BusinessProfile::current();

        return [
            'payroll_withholding_tax_enabled' => (bool) $profile->payroll_withholding_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $profile->payroll_government_contributions_enabled,
        ];
    }

    /**
     * @return array{
     *     employee_contributions: array<string, array{label: string, total: float}>,
     *     employer_contributions: array<string, array{label: string, total: float}>,
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
