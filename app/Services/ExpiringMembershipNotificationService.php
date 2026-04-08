<?php

namespace App\Services;

use App\Models\MemberSubscription;
use App\Notifications\MembershipExpiringNotification;
use Illuminate\Support\Carbon;

class ExpiringMembershipNotificationService
{
    public const WINDOW_DAYS = 7;

    public function __construct(private NotificationRecipientResolver $notificationRecipientResolver)
    {
    }

    public function sendDueNotifications(?Carbon $referenceTime = null): int
    {
        $referenceTime ??= now();
        $windowStart = $referenceTime->copy()->startOfDay();
        $windowEnd = $referenceTime->copy()->addDays(self::WINDOW_DAYS)->endOfDay();
        $notifiedSubscriptions = 0;

        MemberSubscription::query()
            ->whereIn('status', [
                MemberSubscription::STATUS_ACTIVE,
                MemberSubscription::STATUS_PAUSED,
            ])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [
                $windowStart->toDateString(),
                $windowEnd->toDateString(),
            ])
            ->where(function ($query) {
                $query->whereNull('expiration_notification_sent_for_date')
                    ->orWhereColumn('expiration_notification_sent_for_date', '!=', 'end_date');
            })
            ->with([
                'member:id,name',
                'ratePlan:id,name',
            ])
            ->orderBy('end_date')
            ->chunkById(100, function ($subscriptions) use ($referenceTime, &$notifiedSubscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $member = $subscription->member;

                    if (! $member || ! $subscription->end_date) {
                        continue;
                    }

                    $daysRemaining = max(
                        0,
                        $referenceTime->copy()->startOfDay()->diffInDays($subscription->end_date->copy()->startOfDay(), false)
                    );

                    $this->notificationRecipientResolver->send(
                        new MembershipExpiringNotification(
                            $subscription,
                            $member,
                            $daysRemaining,
                            $referenceTime->toISOString(),
                        ),
                    );

                    $subscription->forceFill([
                        'expiration_notification_sent_for_date' => $subscription->end_date,
                    ])->saveQuietly();

                    $notifiedSubscriptions++;
                }
            });

        return $notifiedSubscriptions;
    }
}
