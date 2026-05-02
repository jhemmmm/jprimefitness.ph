<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Services\AuditHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private AuditHistoryService $auditHistoryService,
    ) {}

    public function index(): RedirectResponse
    {
        return to_route('panel.business.settings');
    }

    public function settingsPage(): View
    {
        return view('panel.business.settings', [
            'businessProfile' => BusinessProfile::current(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'pay_overwork_hours' => ['sometimes', 'boolean'],
            'payroll_income_tax_enabled' => ['sometimes', 'boolean'],
            'payroll_government_contributions_enabled' => ['sometimes', 'boolean'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
        ]);

        $data['country_code'] = strtoupper($data['country_code']);

        $profile = BusinessProfile::current();
        $profile->update($data);
        $profile = $profile->fresh();
        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_BUSINESS_PROFILE,
            $profile->id,
            'updated',
            $this->businessProfileAuditSnapshot($profile),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($profile);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessProfileAuditSnapshot(BusinessProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'pay_overwork_hours' => (bool) $profile->pay_overwork_hours,
            'payroll_income_tax_enabled' => (bool) $profile->payroll_income_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $profile->payroll_government_contributions_enabled,
        ];
    }
}
