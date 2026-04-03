<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchCashLedgerEntry;
use App\Services\BranchCashLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function __construct(private BranchCashLedgerService $branchCashLedgerService)
    {
    }

    /**
     * Index Page
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.branches');
    }

    /**
     * Show branch details
     * @param Branch $branch
     * @return \Illuminate\Contracts\View\View
     */
    public function show(Branch $branch): View
    {
        // Load and count related data
        $branch->loadCount(['users', 'ratePlans', 'ptProducts']);
        // Load cash ledger summary
        $branch->setAttribute('cash_ledger_summary', $this->branchCashLedgerService->summarize($branch));

        // Return the branch details view
        return view('panel.branches.show', [
            'branch' => $branch,
        ]);
    }

    /**
     * List branches with filters and pagination
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        // Create a query
        $branchesQuery = Branch::query()
            ->when(!auth()->user()->hasRole('super admin'), fn($q) => $q->whereKey(auth()->user()->branches()->pluck('branches.id')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn($q, $s) => $q->where('status', $s));

        // Get paginated results
        $branches = (clone $branchesQuery)->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total' => (clone $branchesQuery)->count(),
            'open' => (clone $branchesQuery)->where('status', 'open')->count(),
            'closed' => (clone $branchesQuery)->where('status', 'closed')->count(),
            'coming_soon' => (clone $branchesQuery)->where('status', 'coming_soon')->count(),
        ];

        return response()->json(compact('branches', 'stats'));
    }

    /**
     * Store a newly created branch
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasRole('super admin'), 403);

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
        ]);

        $data['country_code'] = strtoupper($data['country_code']);

        $branch = Branch::create($data);

        return response()->json($branch, 201);
    }

    /**
     * Update the specified branch
     * @param Request $request
     * @param Branch $branch
     * @return JsonResponse
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $rules = [
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
        ];

        if (auth()->user()->hasRole('super admin')) {
            $rules = array_merge($rules, [
                'name' => ['required', 'string', 'max:255'],
                'status' => ['required', 'in:open,closed,coming_soon'],
                'country_code' => ['required', 'string', 'size:2'],
            ]);
        }

        $data = $request->validate($rules);

        if (array_key_exists('country_code', $data)) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        $branch->update($data);

        return response()->json($branch->fresh());
    }

    /**
     * Destory the specified branch
     * @param Branch $branch
     * @return JsonResponse
     */
    public function destroy(Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasRole('super admin'), 403);

        // Delete all stored photos from disk
        if ($branch->photos) {
            foreach ($branch->photos as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $branch->delete();

        return response()->json(null, 204);
    }

    /**
     * Store a new photo for the specified branch
     * @param Request $request
     * @param Branch $branch
     * @return JsonResponse
     */
    public function storePhoto(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('photo')->store("branches/{$branch->id}", 'public');

        $photos = $branch->photos ?? [];
        $photos[] = $path;
        $branch->update(['photos' => $photos]);

        return response()->json(['path' => $path, 'url' => asset('storage/' . $path)], 201);
    }

    /**
     * Delete a photo for the specified branch
     * @param Branch $branch
     * @param int $index
     * @return JsonResponse
     */
    public function destroyPhoto(Branch $branch, int $index): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $photos = $branch->photos ?? [];

        if (!array_key_exists($index, $photos)) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        Storage::disk('public')->delete($photos[$index]);
        array_splice($photos, $index, 1);
        $branch->update(['photos' => $photos]);

        return response()->json(null, 204);
    }

    /**
     * Get cash ledger entries and summary for the specified branch
     * @param Branch $branch
     * @return JsonResponse
     */
    public function cashLedger(Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        return response()->json([
            'entries' => $this->branchCashLedgerService->listEntries($branch),
            'summary' => $this->branchCashLedgerService->summarize($branch),
        ]);
    }

    /**
     * Store a newly created cash ledger entry for the specified branch
     * @param Request $request
     * @param Branch $branch
     * @return JsonResponse
     */
    public function storeCashLedgerEntry(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = $this->branchCashLedgerService->createManualEntry($branch, $data, (int) auth()->id());

        return response()->json([
            'entry' => $this->branchCashLedgerService->serializeEntry($entry),
            'summary' => $this->branchCashLedgerService->summarize($branch),
        ], 201);
    }

    /**
     * Update a cash ledger entry for the specified branch
     * @param Request $request
     * @param Branch $branch
     * @param BranchCashLedgerEntry $entry
     * @return JsonResponse
     */
    public function updateCashLedgerEntry(Request $request, Branch $branch, BranchCashLedgerEntry $entry): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);
        abort_if($entry->branch_id !== $branch->id, 404);

        if ($entry->is_system) {
            return response()->json(['message' => 'System ledger entries cannot be edited manually.'], 422);
        }

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $updatedEntry = $this->branchCashLedgerService->updateManualEntry($entry, $data);

        return response()->json([
            'entry' => $this->branchCashLedgerService->serializeEntry($updatedEntry),
            'summary' => $this->branchCashLedgerService->summarize($branch),
        ]);
    }

    /**
     * Delete a cash ledger entry for the specified branch
     * @param Branch $branch
     * @param BranchCashLedgerEntry $entry
     * @return JsonResponse
     */
    public function destroyCashLedgerEntry(Branch $branch, BranchCashLedgerEntry $entry): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);
        abort_if($entry->branch_id !== $branch->id, 404);

        if ($entry->is_system) {
            return response()->json(['message' => 'System ledger entries cannot be deleted manually.'], 422);
        }

        $this->branchCashLedgerService->deleteManualEntry($entry);

        return response()->json([
            'summary' => $this->branchCashLedgerService->summarize($branch),
        ]);
    }
}
