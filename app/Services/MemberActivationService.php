<?php

namespace App\Services;

use App\Mail\MemberActivatedMail;
use App\Models\MemberSubscription;
use App\Models\SystemActivity;
use App\Models\User;
use App\Notifications\MemberActivatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MemberActivationService
{
    public function __construct(
        private SystemActivityService $systemActivityService,
        private MembershipQrService $membershipQrService,
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    /**
     * Activate a pending member + subscription. Idempotent: a no-op if already active.
     *
     * @param  array<string, mixed>  $auditMetadata
     */
    public function activate(MemberSubscription $subscription, array $auditMetadata = []): MemberSubscription
    {
        $subscription = $subscription->fresh(['member', 'ratePlan']);
        $member = $subscription->member;

        if (! $member) {
            return $subscription;
        }

        if ($subscription->status === MemberSubscription::STATUS_ACTIVE && $member->status === User::STATUS_ACTIVE) {
            return $subscription;
        }

        DB::transaction(function () use ($member, $subscription) {
            if ($subscription->start_date && $subscription->start_date->isFuture() === false) {
                $subscription->start_date = $subscription->start_date ?? now()->toDateString();
            }

            $member->forceFill(['status' => User::STATUS_ACTIVE])->save();
            $subscription->forceFill(['status' => MemberSubscription::STATUS_ACTIVE])->save();
        });

        $this->membershipQrService->sendEmail($subscription);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $subscription->id,
            'activated',
            [
                'subscription_id' => $subscription->id,
                'user_id' => $member->id,
                'member_name' => $member->name,
                'plan_name' => $subscription->ratePlan?->name,
            ],
            array_merge(['source' => 'activation_service'], $auditMetadata),
        );

        if ($member->email) {
            Mail::to($member->email)->queue(new MemberActivatedMail($member, $subscription->fresh(['ratePlan'])));
        }

        $this->recipientResolver->send(new MemberActivatedNotification($member, $subscription->fresh(['ratePlan'])));

        return $subscription->fresh(['member', 'ratePlan']);
    }
}
