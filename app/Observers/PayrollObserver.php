<?php

namespace App\Observers;

use App\Models\Payroll;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;

class PayrollObserver
{
    /**
     * Record payroll creation activity.
     *
     * @return void
     */
    public function created(Payroll $payroll): void
    {
        $payroll->loadMissing(['employee:id,name', 'generatedBy:id,name']);

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_PAYROLL,
            $payroll->id,
            'created',
            $this->snapshot($payroll),
            [],
            $payroll->generated_by,
            $payroll->generatedBy?->name,
            now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payroll $payroll): array
    {
        return [
            'id' => $payroll->id,
            'employee_id' => $payroll->employee_id,
            'employee_name' => $payroll->employee?->name ?? 'Unknown Employee',
            'period_start' => $payroll->period_start?->format('Y-m-d'),
            'period_end' => $payroll->period_end?->format('Y-m-d'),
            'regular_hours' => round((float) $payroll->regular_hours, 2),
            'regular_pay_amount' => round((float) $payroll->regular_pay_amount, 2),
            'overwork_hours' => round((float) $payroll->overwork_hours, 2),
            'overwork_pay_amount' => round((float) $payroll->overwork_pay_amount, 2),
            'withholding_tax' => round((float) $payroll->withholding_tax, 2),
            'employee_contributions' => $payroll->employee_contributions ?? [],
            'employee_contributions_total' => $payroll->employeeContributionsTotal(),
            'employer_contributions' => $payroll->employer_contributions ?? [],
            'employer_contributions_total' => $payroll->employerContributionsTotal(),
            'net_amount' => round((float) $payroll->net_amount, 2),
            'status' => $payroll->status,
        ];
    }
}
