<?php

namespace App\Console\Commands;

use App\Mail\MembershipExpiryMail;
use App\Models\MemberSubscription;
use App\Notifications\MembershipExpiringNotification;
use App\Services\NotificationRecipientResolver;
use App\Services\Sync\SyncRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

#[Signature('panel:send-expiring-membership-notifications')]
#[Description('Notify staff in-app and email the member for memberships expiring within seven days')]
class SendExpiringMembershipNotifications extends Command
{
    private const WINDOW_DAYS = 7;

    public function __construct(private NotificationRecipientResolver $notificationRecipientResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $notifiedSubscriptions = $this->sendDueNotifications();

        $this->info("Sent {$notifiedSubscriptions} expiring membership notification(s).");

        return self::SUCCESS;
    }

    private function sendDueNotifications(?Carbon $referenceTime = null): int
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
                'member:id,name,email',
                'member.memberSubscriptions:id,user_id,status,start_date',
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

                    // paused rows still get the staff notice, but an unpaid sign-up is not a membership to renew;
                    // live alone mails expiry since both nodes share one SMTP account
                    if (config('sync.role') !== SyncRole::LOCAL && $member->email && $subscription->pending_payment_method === null && ! $subscription->hasRenewal()) {
                        Mail::to($member->email)->queue(new MembershipExpiryMail($member, $subscription, $daysRemaining));
                    }

                    $subscription->forceFill([
                        'expiration_notification_sent_for_date' => $subscription->end_date,
                    ])->saveQuietly();

                    $notifiedSubscriptions++;
                }
            });

        return $notifiedSubscriptions;
    }
}
