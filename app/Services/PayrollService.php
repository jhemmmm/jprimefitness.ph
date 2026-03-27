<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Payroll;
use App\Models\User;

class PayrollService
{
    /**
     * Compute net_amount from payroll components.
     */
    public function computeNet(float $gross, float $bonus, float $manualDed, float $caDed): float
    {
        return round(max(0, $gross + $bonus - $manualDed - $caDed), 2);
    }

    public function maxCashAdvanceDeduction(int $employeeId, float $gross, float $bonus, float $manualDed): float
    {
        $payableBeforeCa = max(0, $gross + $bonus - $manualDed);
        $pendingCa = $this->pendingCaTotal($employeeId);

        return round(min($payableBeforeCa, $pendingCa), 2);
    }

    public function normalizePayrollCashAdvanceDeduction(Payroll $payroll): float
    {
        $maxDeduction = $this->maxCashAdvanceDeduction(
            $payroll->employee_id,
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
            (float) $payroll->manual_deductions
        );

        $actualDeduction = round(min((float) $payroll->cash_advance_deduction, $maxDeduction), 2);

        if ((float) $payroll->cash_advance_deduction !== $actualDeduction) {
            $payroll->cash_advance_deduction = $actualDeduction;
        }

        $payroll->net_amount = $this->computeNet(
            (float) $payroll->gross_amount,
            (float) $payroll->bonus,
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

        $dailyRate  = (float) ($employee->daily_rate ?? 0);
        $gross = round($dailyRate * $daysWorked, 2);
        $suggestedCa = $this->pendingCaTotal($employee->id);

        return [
            'days_worked'   => $daysWorked,
            'daily_rate'    => $dailyRate,
            'gross_amount'  => $gross,
            'suggested_ca'  => $suggestedCa,
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

        $totalPaid = (float) $payroll->payouts()->sum('amount');

        $payroll->status = match (true) {
            (float) $payroll->net_amount <= 0 => Payroll::STATUS_PAID,
            $totalPaid <= 0 => Payroll::STATUS_APPROVED,
            $totalPaid >= (float) $payroll->net_amount => Payroll::STATUS_PAID,
            default => Payroll::STATUS_PARTIALLY_PAID,
        };

        $payroll->save();
    }
}
