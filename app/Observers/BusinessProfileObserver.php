<?php

namespace App\Observers;

use App\Models\BusinessProfile;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;

class BusinessProfileObserver
{
    public function updated(BusinessProfile $businessProfile): void
    {
        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_BUSINESS_PROFILE,
            $businessProfile->id,
            'updated',
            $this->snapshot($businessProfile),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BusinessProfile $businessProfile): array
    {
        return [
            'id' => $businessProfile->id,
            'name' => $businessProfile->name,
            'pay_overwork_hours' => (bool) $businessProfile->pay_overwork_hours,
            'payroll_withholding_tax_enabled' => (bool) $businessProfile->payroll_withholding_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $businessProfile->payroll_government_contributions_enabled,
        ];
    }
}
