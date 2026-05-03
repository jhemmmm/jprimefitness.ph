<?php

namespace App\Notifications;

use App\Models\MemberSubscription;
use App\Models\User;

class MemberActivatedNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly User $member,
        private readonly MemberSubscription $subscription,
    ) {}

    protected function typeSlug(): string
    {
        return 'member-activated';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->subscription->loadMissing(['ratePlan:id,name']);

        return [
            'title' => 'Member activated',
            'message' => sprintf(
                '%s is now active on the %s plan.',
                $this->member->name,
                $this->subscription->ratePlan?->name ?? 'membership',
            ),
            'action_url' => route('panel.members.show', $this->member),
            'type' => $this->typeSlug(),
            'severity' => 'success',
            'subject_id' => $this->member->id,
            'subject_type' => 'member',
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
