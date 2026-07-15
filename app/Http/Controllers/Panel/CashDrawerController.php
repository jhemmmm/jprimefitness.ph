<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CashDrawerSession;
use App\Models\CashLedgerEntry;
use App\Services\CashDrawerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CashDrawerController extends Controller
{
    public function __construct(
        private CashDrawerService $cashDrawerService,
    ) {}

    /**
     * Display the cash drawer page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $this->authorizeManagement();

        return view('panel.cash-drawer.index');
    }

    /**
     * Return the current drawer state.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(): JsonResponse
    {
        $this->authorizeManagement();

        $session = $this->cashDrawerService->currentSession();
        $payload = [
            'session' => null,
            'suggested_float' => $this->cashDrawerService->suggestedFloat(),
            'categories' => CashLedgerEntry::CATEGORIES,
            'unassigned_today_total' => (float) CashLedgerEntry::query()
                ->whereNull('session_id')
                ->whereDate('occurred_at', now()->toDateString())
                ->sum('amount'),
        ];

        if ($session) {
            $entries = $session->entries()->get();

            $payload['session'] = [
                'id' => $session->id,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'opened_by_name' => $session->openedBy?->name,
                'opening_float' => (float) $session->opening_float,
                'notes' => $session->notes,
                'cash_in' => (float) $entries->where('amount', '>', 0)->sum('amount'),
                'cash_out' => (float) abs($entries->where('amount', '<', 0)->sum('amount')),
                'expected_cash' => $this->cashDrawerService->expectedCash($session),
                'entries' => $entries
                    ->sortByDesc('occurred_at')
                    ->values()
                    ->map(fn (CashLedgerEntry $entry) => $this->serializeEntry($entry)),
            ];
        }

        return response()->json($payload);
    }

    /**
     * Open a drawer session.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function open(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->cashDrawerService->openSession(
            (float) $data['opening_float'],
            $request->user(),
            $data['notes'] ?? null
        );

        return response()->json(['id' => $session->id], 201);
    }

    /**
     * Record a cash expense.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeExpense(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $request->validate([
            'category' => ['required', Rule::in(CashLedgerEntry::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $data['receipt_path'] = $request->file('receipt')?->store('cash-receipts');

        $entry = $this->cashDrawerService->recordExpense($data, $request->user());

        return response()->json($this->serializeEntry($entry), 201);
    }

    /**
     * Close the open drawer session.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function close(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'deposited_amount' => ['required', 'numeric', 'min:0', 'lte:counted_cash'],
            'deposit_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $session = $this->cashDrawerService->currentSession();
        abort_unless($session, 409, 'No drawer session is open.');

        $session = $this->cashDrawerService->closeSession(
            $session,
            (float) $data['counted_cash'],
            (float) $data['deposited_amount'],
            $data['deposit_reference'] ?? null,
            $request->user()
        );

        return response()->json($this->serializeSession($session));
    }

    /**
     * Return closed session history.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sessions(): JsonResponse
    {
        $this->authorizeManagement();

        $sessions = CashDrawerSession::query()
            ->whereNotNull('closed_at')
            ->with(['openedBy:id,name', 'closedBy:id,name'])
            ->orderByDesc('closed_at')
            ->paginate(15);

        $sessions->getCollection()->transform(fn (CashDrawerSession $session) => $this->serializeSession($session));

        return response()->json($sessions);
    }

    /**
     * Return entries for a session (or unassigned entries when no session id given).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function entries(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:cash_drawer_sessions,id'],
        ]);

        $entries = CashLedgerEntry::query()
            ->when(
                $data['session_id'] ?? null,
                fn ($query, $sessionId) => $query->where('session_id', $sessionId),
                fn ($query) => $query->whereNull('session_id')
            )
            ->with('recordedBy:id,name')
            ->orderByDesc('occurred_at')
            ->paginate(20);

        $entries->getCollection()->transform(fn (CashLedgerEntry $entry) => $this->serializeEntry($entry));

        return response()->json($entries);
    }

    /**
     * Monthly expense totals per category.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function expenseSummary(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $data['month'] ?? now()->format('Y-m');
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        $rows = CashLedgerEntry::query()
            ->where('type', CashLedgerEntry::TYPE_EXPENSE)
            ->whereDate('occurred_at', '>=', $start)
            ->whereDate('occurred_at', '<=', $end)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) abs($row->total),
            ])
            ->values();

        return response()->json([
            'month' => $month,
            'categories' => $rows,
            'total' => round($rows->sum('total'), 2),
        ]);
    }

    /**
     * Stream a stored expense receipt image. Receipts live on the private
     * local disk, so this gated endpoint is the only way to view them.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function receipt(CashLedgerEntry $entry)
    {
        $this->authorizeManagement();

        abort_unless($entry->receipt_path && Storage::exists($entry->receipt_path), 404);

        return Storage::response($entry->receipt_path);
    }

    private function authorizeManagement(): void
    {
        abort_unless(config('jprime.cash_drawer'), 404);
        abort_unless(auth()->user()->isManagement(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(CashDrawerSession $session): array
    {
        return [
            'id' => $session->id,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opened_by_name' => $session->openedBy?->name,
            'closed_by_name' => $session->closedBy?->name,
            'opening_float' => (float) $session->opening_float,
            'expected_cash' => $session->expected_cash !== null ? (float) $session->expected_cash : null,
            'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
            'over_short' => $session->over_short !== null ? (float) $session->over_short : null,
            'deposited_amount' => $session->deposited_amount !== null ? (float) $session->deposited_amount : null,
            'deposit_reference' => $session->deposit_reference,
            'notes' => $session->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(CashLedgerEntry $entry): array
    {
        $entry->loadMissing('recordedBy:id,name');

        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'category' => $entry->category,
            'amount' => (float) $entry->amount,
            'description' => $entry->description,
            'notes' => $entry->notes,
            'receipt_url' => $entry->receipt_path ? route('panel.cash-drawer.entries.receipt', $entry) : null,
            'recorded_by_name' => $entry->recordedBy?->name,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
        ];
    }
}
