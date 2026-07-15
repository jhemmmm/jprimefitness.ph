<?php

namespace App\Observers;

use App\Models\CashLedgerEntry;
use App\Models\Payout;
use App\Models\SystemActivity;
use App\Services\CashDrawerService;
use App\Services\SystemActivityService;

class PayoutObserver
{
    /**
     * Record payout creation activity.
     *
     * @return void
     */
    public function created(Payout $payout): void
    {
        $payout->loadMissing(['employee:id,name', 'payroll:id,period_start,period_end', 'releasedBy:id,name']);

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_PAYOUT,
            $payout->id,
            'created',
            $this->snapshot($payout),
            [],
            $payout->released_by,
            $payout->releasedBy?->name,
            $payout->paid_at ?? now(),
        );

        if (config('jprime.cash_drawer') && $payout->method === Payout::METHOD_CASH) {
            app(CashDrawerService::class)->recordSourceEntry(
                CashLedgerEntry::TYPE_PAYOUT,
                -round((float) $payout->amount, 2),
                'payout',
                $payout->id,
                'Payout '.($payout->employee?->name ?? 'Employee'),
                $payout->released_by,
                $payout->paid_at,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payout $payout): array
    {
        $payrollPeriod = $payout->payroll && $payout->payroll->period_start && $payout->payroll->period_end
            ? $payout->payroll->period_start->format('Y-m-d').' - '.$payout->payroll->period_end->format('Y-m-d')
            : null;

        return [
            'id' => $payout->id,
            'employee_id' => $payout->employee_id,
            'employee_name' => $payout->employee?->name ?? 'Unknown Employee',
            'payroll_id' => $payout->payroll_id,
            'payroll_period' => $payrollPeriod,
            'amount' => round((float) $payout->amount, 2),
            'method' => $payout->method,
        ];
    }
}
