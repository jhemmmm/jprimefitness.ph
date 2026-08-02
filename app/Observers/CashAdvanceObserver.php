<?php

namespace App\Observers;

use App\Models\CashAdvance;
use App\Models\CashLedgerEntry;
use App\Models\SystemActivity;
use App\Services\CashDrawerService;
use App\Services\SystemActivityService;

class CashAdvanceObserver
{
    /**
     * Record cash advance creation activity.
     *
     * @return void
     */
    public function created(CashAdvance $advance): void
    {
        $advance->loadMissing(['employee:id,name', 'releasedBy:id,name']);

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_CASH_ADVANCE,
            $advance->id,
            'created',
            $this->snapshot($advance),
            [],
            $advance->released_by,
            $advance->releasedBy?->name,
            $advance->paid_at ?? now(),
        );

        if (config('jprime.cash_drawer') && $advance->method === CashAdvance::METHOD_CASH) {
            app(CashDrawerService::class)->recordSourceEntry(
                CashLedgerEntry::TYPE_CASH_ADVANCE,
                -round((float) $advance->amount, 2),
                'cash_advance',
                $advance->id,
                'Cash advance '.($advance->employee?->name ?? 'Employee'),
                $advance->released_by,
                $advance->paid_at,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CashAdvance $advance): array
    {
        return [
            'id' => $advance->id,
            'employee_id' => $advance->employee_id,
            'employee_name' => $advance->employee?->name ?? 'Unknown Employee',
            'amount' => round((float) $advance->amount, 2),
            'method' => $advance->method,
        ];
    }
}
