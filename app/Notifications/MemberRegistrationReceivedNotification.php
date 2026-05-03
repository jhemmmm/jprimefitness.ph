<?php

namespace App\Notifications;

use App\Models\MemberSubscription;
use App\Models\User;

class MemberRegistrationReceivedNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly User $member,
        private readonly MemberSubscription $subscription,
        private readonly string $paymentMethod,
    ) {}

    protected function typeSlug(): string
    {
        return 'member-registration-received';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->subscription->loadMissing(['ratePlan:id,name']);

        return [
            'title' => 'New member registration',
            'message' => sprintf(
                '%s signed up online for the %s plan (%s).',
                $this->member->name,
                $this->subscription->ratePlan?->name ?? 'membership',
                $this->paymentMethod === 'online' ? 'paying online' : 'paying on-site',
            ),
            'action_url' => route('panel.members.show', $this->member),
            'type' => $this->typeSlug(),
            'severity' => 'info',
            'subject_id' => $this->member->id,
            'subject_type' => 'member',
            'payment_method' => $this->paymentMethod,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
