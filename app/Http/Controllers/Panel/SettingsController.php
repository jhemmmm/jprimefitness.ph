<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\CashLedgerEntry;
use App\Services\AuditHistoryService;
use App\Services\CashLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private CashLedgerService $cashLedgerService,
        private AuditHistoryService $auditHistoryService,
    ) {}

    public function index(): RedirectResponse
    {
        return to_route('panel.business.settings');
    }

    public function cashLedgerPage(): View
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return view('panel.business.cash-ledger', [
            'businessProfile' => $this->businessProfile(),
        ]);
    }

    public function photosPage(): View
    {
        return view('panel.business.photos', [
            'businessProfile' => $this->businessProfile(),
        ]);
    }

    public function settingsPage(): View
    {
        return view('panel.business.settings', [
            'businessProfile' => $this->businessProfile(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:open,closed,coming_soon'],
            'country_code' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'messenger_url' => ['nullable', 'url', 'max:500'],
            'whatsapp_url' => ['nullable', 'url', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'hero_badge' => ['nullable', 'string', 'max:120'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_highlight' => ['nullable', 'string', 'max:255'],
            'hero_description' => ['nullable', 'string', 'max:2000'],
            'about_heading' => ['nullable', 'string', 'max:255'],
            'about_description' => ['nullable', 'string', 'max:4000'],
            'membership_note' => ['nullable', 'string', 'max:1000'],
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
        $profile->setAttribute('cash_ledger_summary', $this->cashLedgerService->summarize());
        $profile->setAttribute('cash_balance', $profile->cash_ledger_summary['balance'] ?? 0);

        return response()->json($profile);
    }

    public function storePhoto(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $profile = BusinessProfile::current();
        $path = $request->file('photo')->store("business-profile/{$profile->id}", 'public');

        $photos = $profile->photos ?? [];
        $photos[] = $path;
        $profile->update(['photos' => $photos]);
        $profile = $profile->fresh();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_BUSINESS_PROFILE,
            $profile->id,
            'photo_added',
            $this->businessProfileAuditSnapshot($profile),
            [
                'photo_path' => $path,
            ],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['path' => $path, 'url' => asset('storage/'.$path)], 201);
    }

    public function destroyPhoto(int $index): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $profile = BusinessProfile::current();
        $photos = $profile->photos ?? [];

        if (! array_key_exists($index, $photos)) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        $removedPhoto = $photos[$index];

        Storage::disk('public')->delete($removedPhoto);
        array_splice($photos, $index, 1);
        $profile->update(['photos' => $photos]);
        $profile = $profile->fresh();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_BUSINESS_PROFILE,
            $profile->id,
            'photo_removed',
            $this->businessProfileAuditSnapshot($profile),
            [
                'photo_index' => $index,
                'photo_path' => $removedPhoto,
            ],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(null, 204);
    }

    public function cashLedger(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'direction' => ['nullable', Rule::in([CashLedgerEntry::DIRECTION_IN, CashLedgerEntry::DIRECTION_OUT])],
            'entry_type' => ['nullable', Rule::in([
                CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
                CashLedgerEntry::TYPE_INVENTORY_SALE,
                CashLedgerEntry::TYPE_MEMBERSHIP_SALE,
                CashLedgerEntry::TYPE_PT_PACKAGE_SALE,
                CashLedgerEntry::TYPE_WALK_IN_SALE,
                CashLedgerEntry::TYPE_PAYROLL_PAYOUT,
                CashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE,
            ])],
            'mode' => ['nullable', Rule::in(['system', 'manual'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'entries' => $this->cashLedgerService->listEntries($filters),
            'summary' => $this->cashLedgerService->summarize(),
            'filters' => $filters,
        ]);
    }

    public function storeCashLedgerEntry(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = $this->cashLedgerService->createManualEntry($data, (int) auth()->id());

        return response()->json([
            'entry' => $this->cashLedgerService->serializeEntry($entry),
            'summary' => $this->cashLedgerService->summarize(),
        ], 201);
    }

    public function updateCashLedgerEntry(Request $request, CashLedgerEntry $entry): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);
        abort_if($entry->is_system, 422, 'System-generated cash entries cannot be edited.');

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $updatedEntry = $this->cashLedgerService->updateManualEntry($entry, $data);

        return response()->json([
            'entry' => $this->cashLedgerService->serializeEntry($updatedEntry),
            'summary' => $this->cashLedgerService->summarize(),
        ]);
    }

    public function destroyCashLedgerEntry(CashLedgerEntry $entry): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);
        abort_if($entry->is_system, 422, 'System-generated cash entries cannot be deleted.');

        $this->cashLedgerService->deleteManualEntry($entry);

        return response()->json(null, 204);
    }

    private function businessProfile(): BusinessProfile
    {
        $businessProfile = BusinessProfile::current();
        $cashLedgerSummary = $this->cashLedgerService->summarize();
        $businessProfile->setAttribute('cash_ledger_summary', $cashLedgerSummary);
        $businessProfile->setAttribute('cash_balance', $cashLedgerSummary['balance']);

        return $businessProfile;
    }

    /**
     * @return array<string, mixed>
     */
    private function businessProfileAuditSnapshot(BusinessProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'status' => $profile->status,
        ];
    }
}
