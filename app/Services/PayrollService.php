<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\MemberPtPackage;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Compute net_amount from payroll components.
     */
    public function computeNet(
        float $gross,
        float $bonus,
        float $ptCommission,
        float $manualDed,
        float $caDed
    ): float {
        return round(max(0, $gross + $bonus + $ptCommission - $this->totalEmployeeDeductionsBeforeCashAdvance($manualDed) - $caDed), 2);
    }

    public function totalEmployeeDeductionsBeforeCashAdvance(float $manualDed): float
    {
        return round(max(0, $manualDed), 2);
    }

    public function maxCashAdvanceDeduction(
        int $employeeId,
        float $gross,
        float $bonus,
        float $ptCommission,
        float $manualDed
    ): float {
        $payableBeforeCa = max(0, $gross + $bonus + $ptCommission - $this->totalEmployeeDeductionsBeforeCashAdvance($manualDed));
        $pendingCa = $this->pendingCaTotal($employeeId);

        return round(min($payableBeforeCa, $pendingCa), 2);
    }

    public function normalizePayrollCashAdvanceDeduction(Payroll $payroll): float
    {
        $maxDeduction = $this->maxCashAdvanceDeduction(
            $payroll->employee_id,
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
            (float) $payroll->pt_commission_amount,
            (float) $payroll->manual_deductions
        );

        $actualDeduction = round(min((float) $payroll->cash_advance_deduction, $maxDeduction), 2);

        if ((float) $payroll->cash_advance_deduction !== $actualDeduction) {
            $payroll->cash_advance_deduction = $actualDeduction;
        }

        $payroll->net_amount = $this->computeNet(
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
            (float) $payroll->pt_commission_amount,
            (float) $payroll->manual_deductions,
            $actualDeduction
        );
        $payroll->save();

        return $actualDeduction;
    }

    /**
     * Suggest gross amount and CA deduction based on attendance in a period.
     * Counts distinct days worked × employee daily_rate.
     */
    public function suggestFromAttendance(User $employee, string $periodStart, string $periodEnd): array
    {
        $daysWorked = Attendance::where('user_id', $employee->id)
            ->whereDate('checked_in_at', '>=', $periodStart)
            ->whereDate('checked_in_at', '<=', $periodEnd)
            ->selectRaw('DATE(checked_in_at) as work_date')
            ->distinct()
            ->get()
            ->count();

        $dailyRate = (float) ($employee->daily_rate ?? 0);
        $gross = round($dailyRate * $daysWorked, 2);
        $suggestedCa = $this->pendingCaTotal($employee->id);

        return [
            'days_worked' => $daysWorked,
            'daily_rate' => $dailyRate,
            'gross_amount' => $gross,
            'suggested_ca' => $suggestedCa,
        ];
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
            $advance->appendAuditEvent([
                'event' => $advance->status,
                'at' => $processedAt->toISOString(),
                'by_user_id' => auth()->id() ?? $payroll->approved_by,
                'by_name' => auth()->user()?->name,
                'source' => 'payroll',
                'source_id' => $payroll->id,
                'deducted_amount' => round($deduct, 2),
                'remaining_before' => round($remainingBefore, 2),
                'remaining_after' => round((float) $advance->remaining_amount, 2),
            ]);
            $advance->save();

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
        }
    }

    /**
     * @return array{amount: float, items: array<int, array<string, mixed>>}
     */
    public function previewPtCommissions(
        User $employee,
        string $periodStart,
        string $periodEnd,
        ?Payroll $payroll = null,
        ?int $branchId = null
    ): array {
        $packages = $this->ptCommissionPackageQuery($employee, $periodStart, $periodEnd, $payroll, $branchId)
            ->with(['member:id,name', 'ptProduct:id,name', 'branch:id,name'])
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
            $payroll,
            $payroll->branch_id
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
        $payroll->net_amount = $this->computeNet(
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
            (float) $payroll->pt_commission_amount,
            (float) $payroll->manual_deductions,
            (float) $payroll->cash_advance_deduction
        );
        $payroll->save();
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

    private function ptCommissionPackageQuery(
        User $employee,
        string $periodStart,
        string $periodEnd,
        ?Payroll $payroll = null,
        ?int $branchId = null
    ) {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        return MemberPtPackage::query()
            ->where('coach_id', $employee->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('coach_commission_status', MemberPtPackage::COMMISSION_STATUS_EARNED)
            ->whereBetween('coach_commission_earned_at', [$start, $end])
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
            'branch_name' => $package->branch?->name,
            'product_name' => $package->ptProduct?->name,
            'earned_at' => $package->coach_commission_earned_at?->toISOString(),
            'sold_price' => round((float) $package->sold_price, 2),
            'commission_rate' => round((float) $package->coach_commission_rate, 2),
            'commission_amount' => round((float) $package->coach_commission_amount, 2),
        ];
    }
}
