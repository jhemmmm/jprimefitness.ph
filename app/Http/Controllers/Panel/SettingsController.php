<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CashLedgerEntry;
use App\Services\BusinessProfileContext;
use App\Services\CashLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private BusinessProfileContext $businessProfileContext,
        private CashLedgerService $cashLedgerService,
    ) {
    }

    public function index(): View
    {
        $businessProfile = $this->businessProfileContext->profile();
        $cashLedgerSummary = $this->cashLedgerService->summarize();
        $businessProfile->setAttribute('cash_ledger_summary', $cashLedgerSummary);

        return view('panel.settings', compact('businessProfile'));
    }

    public function update(Request $request)
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

        $profile = $this->businessProfileContext->profile();
        $profile->update($data);
        $profile->setAttribute('cash_ledger_summary', $this->cashLedgerService->summarize());

        return response()->json($profile->fresh());
    }

    public function storePhoto(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $profile = $this->businessProfileContext->profile();
        $path = $request->file('photo')->store("business-profile/{$profile->id}", 'public');

        $photos = $profile->photos ?? [];
        $photos[] = $path;
        $profile->update(['photos' => $photos]);

        return response()->json(['path' => $path, 'url' => asset('storage/' . $path)], 201);
    }

    public function destroyPhoto(int $index)
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $profile = $this->businessProfileContext->profile();
        $photos = $profile->photos ?? [];

        if (! array_key_exists($index, $photos)) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        Storage::disk('public')->delete($photos[$index]);
        array_splice($photos, $index, 1);
        $profile->update(['photos' => $photos]);

        return response()->json(null, 204);
    }

    public function cashLedger()
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return response()->json([
            'entries' => $this->cashLedgerService->listEntries(),
            'summary' => $this->cashLedgerService->summarize(),
        ]);
    }

    public function storeCashLedgerEntry(Request $request)
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

    public function updateCashLedgerEntry(Request $request, CashLedgerEntry $entry)
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

    public function destroyCashLedgerEntry(CashLedgerEntry $entry)
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);
        abort_if($entry->is_system, 422, 'System-generated cash entries cannot be deleted.');

        $this->cashLedgerService->deleteManualEntry($entry);

        return response()->json(null, 204);
    }
}
