<?php

namespace App\Notifications;

use App\Models\MemberPtPackage;
use App\Models\User;

class MemberPtPackageRunningLowNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly MemberPtPackage $memberPtPackage,
        private readonly User $member,
        private readonly string $occurredAt,
    ) {
    }

    protected function typeSlug(): string
    {
        return 'pt-package-running-low';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $this->memberPtPackage->loadMissing([
            'branch:id,name',
            'ptProduct:id,name',
        ]);

        return [
            'title' => 'PT sessions running low',
            'message' => $this->message(),
            'action_url' => route('panel.members.show', $this->member),
            'type' => $this->typeSlug(),
            'severity' => 'warning',
            'branch_id' => $this->memberPtPackage->branch_id,
            'branch_name' => $this->memberPtPackage->branch?->name,
            'subject_id' => $this->memberPtPackage->id,
            'subject_type' => 'member_pt_package',
            'occurred_at' => $this->occurredAt,
        ];
    }

    private function message(): string
    {
        $packageName = $this->memberPtPackage->ptProduct?->name ?? 'PT package';
        $branchName = $this->memberPtPackage->branch?->name ?? 'the selected branch';
        $remainingSessions = (int) $this->memberPtPackage->remaining_sessions;
        $totalSessions = (int) $this->memberPtPackage->total_sessions;

        return $this->member->name." has {$remainingSessions} of {$totalSessions} sessions left for {$packageName} at {$branchName}.";
    }
}
