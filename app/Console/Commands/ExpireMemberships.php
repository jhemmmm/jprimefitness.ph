<?php

namespace App\Console\Commands;

use App\Mail\MembershipExpiryMail;
use App\Models\MemberSubscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('panel:expire-memberships')]
#[Description('Mark active memberships past their end date as expired and email the member')]
class ExpireMemberships extends Command
{
    public function handle(): int
    {
        $expired = 0;

        // A membership is valid through the whole of its end date (see MembershipQrAntiFraudService).
        // Paused subscriptions double as "pending payment" / staff-set freeze, so they are left alone.
        MemberSubscription::query()
            ->where('status', MemberSubscription::STATUS_ACTIVE)
            ->where('end_date', '<', today()->toDateString())
            ->with(['member:id,name,email', 'member.memberSubscriptions:id,user_id,status,start_date', 'ratePlan:id,name'])
            ->chunkById(100, function ($subscriptions) use (&$expired): void {
                foreach ($subscriptions as $subscription) {
                    // Quiet, like the expiring-notification marker: every node runs this schedule and
                    // flips its own copy, so the first node to run must not sync the flip away from
                    // the other before it has had its turn to mail the member.
                    $subscription->forceFill(['status' => MemberSubscription::STATUS_EXPIRED])->saveQuietly();

                    if ($subscription->member?->email && ! $subscription->hasRenewal()) {
                        Mail::to($subscription->member->email)->queue(new MembershipExpiryMail($subscription->member, $subscription));
                    }

                    $expired++;
                }
            });

        $this->info("Expired {$expired} membership(s).");

        return self::SUCCESS;
    }
}
