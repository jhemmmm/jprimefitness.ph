<?php

namespace App\Notifications;

use App\Models\CashAdvanceRequest;

class CashAdvanceRequestedNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly CashAdvanceRequest $request,
    ) {
    }

    protected function typeSlug(): string
    {
        return 'cash-advance-requested';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->request->loadMissing('employee:id,name');

        return [
            'title' => 'Cash advance requested',
            'message' => $this->request->employee->name.' requested a ₱'.number_format((float) $this->request->amount, 2).' cash advance: '.$this->request->reason,
            'action_url' => route('panel.employees.show', $this->request->employee).'?tab=cash-advance',
            'type' => $this->typeSlug(),
            'severity' => 'warning',
            'subject_id' => $this->request->id,
            'subject_type' => 'cash_advance_request',
            'occurred_at' => $this->request->created_at?->toISOString(),
        ];
    }
}
