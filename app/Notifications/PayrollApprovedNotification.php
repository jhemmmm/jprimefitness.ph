<?php

namespace App\Notifications;

use App\Models\Payroll;
use App\Models\User;

class PayrollApprovedNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly Payroll $payroll,
        private readonly User $employee,
    ) {
    }

    protected function typeSlug(): string
    {
        return 'payroll-approved';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->payroll->loadMissing(['branch:id,name', 'approvedBy:id,name']);

        $approvedBy = $this->payroll->approvedBy?->name
            ? ' by '.$this->payroll->approvedBy->name
            : '';

        return [
            'title' => 'Payroll approved',
            'message' => $this->employee->name."'s payroll for ".$this->periodLabel().' was approved'.$approvedBy.'.',
            'action_url' => route('panel.employees.show', $this->employee),
            'type' => $this->typeSlug(),
            'severity' => 'success',
            'branch_id' => $this->payroll->branch_id,
            'branch_name' => $this->payroll->branch?->name,
            'subject_id' => $this->payroll->id,
            'subject_type' => 'payroll',
            'occurred_at' => $this->payroll->approved_at?->toISOString(),
        ];
    }

    private function periodLabel(): string
    {
        $periodStart = $this->payroll->period_start?->format('M j, Y');
        $periodEnd = $this->payroll->period_end?->format('M j, Y');

        if ($periodStart && $periodEnd) {
            return $periodStart.' to '.$periodEnd;
        }

        return 'the selected pay period';
    }
}
