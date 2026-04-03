<?php

namespace App\Notifications;

use App\Models\MemberSubscription;
use App\Models\User;
use Illuminate\Support\Str;

class MembershipExpiringNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly MemberSubscription $subscription,
        private readonly User $member,
        private readonly int $daysRemaining,
        private readonly string $occurredAt,
    ) {
    }

    protected function typeSlug(): string
    {
        return 'membership-expiring';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->subscription->loadMissing(['branch:id,name', 'ratePlan:id,name']);

        return [
            'title' => 'Membership expiring soon',
            'message' => $this->message(),
            'action_url' => route('panel.members.show', $this->member),
            'type' => $this->typeSlug(),
            'severity' => $this->daysRemaining <= 1 ? 'danger' : 'warning',
            'branch_id' => $this->subscription->branch_id,
            'branch_name' => $this->subscription->branch?->name,
            'subject_id' => $this->subscription->id,
            'subject_type' => 'member_subscription',
            'occurred_at' => $this->occurredAt,
        ];
    }

    private function message(): string
    {
        $planName = $this->subscription->ratePlan?->name ?? 'membership';
        $branchName = $this->subscription->branch?->name ?? 'the selected branch';
        $endDate = $this->subscription->end_date?->format('M j, Y') ?? 'the scheduled end date';
        $dayLabel = $this->daysRemaining.' '.Str::plural('day', $this->daysRemaining);

        return $this->member->name."'s {$planName} membership at {$branchName} expires in {$dayLabel} on {$endDate}.";
    }
}
