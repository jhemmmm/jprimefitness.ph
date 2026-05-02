<?php

namespace App\Notifications;

use App\Models\CashAdvance;
use App\Models\User;

class CashAdvanceStatusChangedNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly CashAdvance $cashAdvance,
        private readonly User $employee,
    ) {}

    protected function typeSlug(): string
    {
        return 'cash-advance-status-changed';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'action_url' => route('panel.employees.show', $this->employee),
            'type' => $this->typeSlug(),
            'severity' => $this->severity(),
            'subject_id' => $this->cashAdvance->id,
            'subject_type' => 'cash_advance',
            'occurred_at' => $this->occurredAt(),
        ];
    }

    private function title(): string
    {
        return match ($this->cashAdvance->status) {
            CashAdvance::STATUS_REQUESTED => 'Cash advance requested',
            CashAdvance::STATUS_APPROVED => 'Cash advance approved',
            CashAdvance::STATUS_RELEASED => 'Cash advance released',
            CashAdvance::STATUS_CANCELLED => 'Cash advance cancelled',
            default => 'Cash advance updated',
        };
    }

    private function message(): string
    {
        $amount = '₱'.number_format((float) $this->cashAdvance->amount, 2);

        return match ($this->cashAdvance->status) {
            CashAdvance::STATUS_REQUESTED => $this->employee->name.' requested a cash advance of '.$amount.'.',
            CashAdvance::STATUS_APPROVED => 'The cash advance for '.$this->employee->name.' amounting to '.$amount.' was approved.',
            CashAdvance::STATUS_RELEASED => 'The cash advance for '.$this->employee->name.' amounting to '.$amount.' was released.',
            CashAdvance::STATUS_CANCELLED => 'The cash advance for '.$this->employee->name.' amounting to '.$amount.' was cancelled.',
            default => 'The cash advance for '.$this->employee->name.' was updated.',
        };
    }

    private function severity(): string
    {
        return match ($this->cashAdvance->status) {
            CashAdvance::STATUS_REQUESTED => 'warning',
            CashAdvance::STATUS_APPROVED => 'info',
            CashAdvance::STATUS_RELEASED => 'success',
            CashAdvance::STATUS_CANCELLED => 'muted',
            default => 'info',
        };
    }

    private function occurredAt(): ?string
    {
        return match ($this->cashAdvance->status) {
            CashAdvance::STATUS_REQUESTED => $this->cashAdvance->requested_at?->toISOString(),
            CashAdvance::STATUS_APPROVED => $this->cashAdvance->approved_at?->toISOString(),
            CashAdvance::STATUS_RELEASED => $this->cashAdvance->released_at?->toISOString(),
            CashAdvance::STATUS_CANCELLED => $this->cashAdvance->cancelled_at?->toISOString(),
            default => $this->cashAdvance->updated_at?->toISOString(),
        };
    }
}
