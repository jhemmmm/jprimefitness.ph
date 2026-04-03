<?php

namespace App\Services;

use App\Models\MemberPtPackage;
use App\Notifications\MemberPtPackageRunningLowNotification;
use Illuminate\Support\Carbon;

class MemberPtPackageAlertService
{
    public const LOW_SESSION_THRESHOLD = 2;

    public function __construct(private NotificationRecipientResolver $notificationRecipientResolver)
    {
    }

    public function notifyIfRunningLow(
        ?MemberPtPackage $memberPtPackage,
        int $previousRemainingSessions,
        ?string $occurredAt = null,
    ): bool {
        if (! $memberPtPackage) {
            return false;
        }

        $currentRemainingSessions = (int) $memberPtPackage->remaining_sessions;

        if (
            $previousRemainingSessions <= self::LOW_SESSION_THRESHOLD
            || $currentRemainingSessions > self::LOW_SESSION_THRESHOLD
            || $currentRemainingSessions <= 0
        ) {
            return false;
        }

        $memberPtPackage->loadMissing([
            'member:id,name',
            'ptProduct:id,name',
            'branch:id,name',
        ]);

        if (! $memberPtPackage->member) {
            return false;
        }

        $this->notificationRecipientResolver->send(
            new MemberPtPackageRunningLowNotification(
                $memberPtPackage,
                $memberPtPackage->member,
                Carbon::parse($occurredAt ?? now())->toISOString(),
            ),
            $memberPtPackage->branch_id,
        );

        return true;
    }
}
