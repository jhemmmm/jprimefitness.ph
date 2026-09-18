<?php

namespace App\Notifications;

use App\Models\CashAdvanceRequest;

/**
 * Sent to the requesting employee once a manager approves or rejects.
 */
class CashAdvanceRequestReviewedNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly CashAdvanceRequest $request,
    ) {
    }

    protected function typeSlug(): string
    {
        return 'cash-advance-request-reviewed';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->request->loadMissing(['reviewedBy:id,name', 'cashAdvance:id,method']);

        $amount = '₱'.number_format((float) $this->request->amount, 2);
        $reviewer = $this->request->reviewedBy?->name ? ' by '.$this->request->reviewedBy->name : '';
        $approved = $this->request->status === CashAdvanceRequest::STATUS_APPROVED;

        $message = $approved
            ? "Your {$amount} cash advance request was approved{$reviewer} and released via ".str_replace('_', ' ', (string) $this->request->cashAdvance?->method).'.'
            : "Your {$amount} cash advance request was rejected{$reviewer}: ".$this->request->review_note;

        return [
            'title' => $approved ? 'Cash advance approved' : 'Cash advance request rejected',
            'message' => $message,
            'action_url' => route('panel.my.show', 'cash-advances'),
            'type' => $this->typeSlug(),
            'severity' => $approved ? 'success' : 'danger',
            'subject_id' => $this->request->id,
            'subject_type' => 'cash_advance_request',
            'occurred_at' => $this->request->reviewed_at?->toISOString(),
        ];
    }
}
