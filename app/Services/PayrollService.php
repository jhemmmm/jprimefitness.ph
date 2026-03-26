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
        $gross      = round($dailyRate * $daysWorked, 2);
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
            ->whereIn('status', ['pending', 'partial'])
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
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('requested_at')
            ->get();

        $remaining = $limit;

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min((float) $advance->remaining_amount, $remaining);
            $advance->remaining_amount = round((float) $advance->remaining_amount - $deduct, 2);
            $advance->status = $advance->remaining_amount <= 0 ? 'fully_deducted' : 'partial';
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
        if ($payroll->status === 'draft') {
            return;
        }

        $totalPaid = (float) $payroll->payouts()->sum('amount');

        $payroll->status = match (true) {
            $totalPaid <= 0 => 'approved',
            $totalPaid >= (float) $payroll->net_amount => 'paid',
            default => 'partially_paid',
        };

        $payroll->save();
    }
}
