<?php

namespace App\Observers;

use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;

class MemberPtSessionUsageObserver
{
    /**
     * Record PT session usage creation activity.
     *
     * @return void
     */
    public function created(MemberPtSessionUsage $memberPtSessionUsage): void
    {
        $memberPtSessionUsage->loadMissing([
            'coach:id,name',
            'recordedBy:id,name',
            'memberPtPackage.member:id,name',
        ]);

        $package = $memberPtSessionUsage->memberPtPackage;

        if (! $package instanceof MemberPtPackage) {
            return;
        }

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_PT_SESSION_USAGE,
            $memberPtSessionUsage->id,
            'recorded',
            $this->snapshot($memberPtSessionUsage, $package),
            [],
            $memberPtSessionUsage->recorded_by,
            $memberPtSessionUsage->recordedBy?->name,
            $memberPtSessionUsage->used_at ?? now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(MemberPtSessionUsage $usage, MemberPtPackage $package): array
    {
        return [
            'id' => $usage->id,
            'member_id' => $package->user_id,
            'member_name' => $package->member?->name ?? 'Unknown Member',
            'package_id' => $package->id,
            'sessions_used' => $usage->sessions_used,
            'remaining_sessions' => max(0, (int) $package->remaining_sessions - (int) $usage->sessions_used),
            'used_at' => $usage->used_at?->toISOString(),
            'coach_id' => $usage->coach_id,
            'coach_name' => $usage->coach?->name,
        ];
    }
}
