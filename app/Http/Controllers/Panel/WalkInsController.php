<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\WalkIn;
use App\Services\CashLedgerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WalkInsController extends Controller
{
    public function __construct(
        private CashLedgerService $cashLedgerService,
    ) {
    }

    public function index(): View
    {
        return view('panel.walk-ins');
    }

    public function list(Request $request): JsonResponse
    {
        $walkIns = WalkIn::with('ratePlan')
            ->when(! empty($request->search), fn ($query) => $query->where(function ($inner) use ($request) {
                $inner->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->when($request->date_from, fn ($query) => $query->whereDate('visited_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($query) => $query->whereDate('visited_at', '<=', $request->date_to))
            ->orderByDesc('visited_at')
            ->paginate(20)
            ->through(fn (WalkIn $walkIn) => [
                'id' => $walkIn->id,
                'rate_plan_id' => $walkIn->rate_plan_id,
                'rate_plan' => $walkIn->ratePlan,
                'served_by' => $walkIn->served_by,
                'name' => $walkIn->name,
                'phone' => $walkIn->phone,
                'amount_paid' => round((float) $walkIn->amount_paid, 2),
                'payment_method' => $walkIn->payment_method,
                'visited_at' => $walkIn->visited_at?->toISOString(),
                'notes' => $walkIn->notes,
            ])
            ->withQueryString();

        $baseStats = WalkIn::query();

        $stats = [
            'today' => (clone $baseStats)->whereDate('visited_at', Carbon::today())->count(),
            'this_week' => (clone $baseStats)->where('visited_at', '>=', Carbon::now()->startOfWeek())->count(),
            'this_month' => (clone $baseStats)->where('visited_at', '>=', Carbon::now()->startOfMonth())->count(),
            'revenue_today' => (clone $baseStats)->whereDate('visited_at', Carbon::today())->sum('amount_paid'),
        ];

        return response()->json(compact('walkIns', 'stats'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(SaleTransaction::supportedPaymentMethods())],
            'visited_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['rate_plan_id'])) {
            RatePlan::query()->whereKey($data['rate_plan_id'])->firstOrFail();
        }

        $data['served_by'] = auth()->id();
        $data['payment_method'] = $data['payment_method'] ?? SaleTransaction::PAYMENT_METHOD_CASH;
        $data['visited_at'] = $data['visited_at'] ?? now();

        $walkIn = WalkIn::create($data);
        $this->cashLedgerService->syncWalkIn($walkIn);

        return response()->json($this->serializeWalkIn($walkIn->fresh(['ratePlan'])), 201);
    }

    public function show(WalkIn $walkIn): JsonResponse
    {
        return response()->json($this->serializeWalkIn($walkIn->load('ratePlan')));
    }

    public function update(Request $request, WalkIn $walkIn): JsonResponse
    {
        $data = $request->validate([
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(SaleTransaction::supportedPaymentMethods())],
            'visited_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['payment_method'] = $data['payment_method'] ?? $walkIn->payment_method ?? SaleTransaction::PAYMENT_METHOD_CASH;
        $walkIn->update($data);
        $this->cashLedgerService->syncWalkIn($walkIn->fresh(['ratePlan']));

        return response()->json($this->serializeWalkIn($walkIn->fresh(['ratePlan'])));
    }

    public function destroy(WalkIn $walkIn): JsonResponse
    {
        $this->cashLedgerService->deleteWalkIn($walkIn);
        $walkIn->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWalkIn(WalkIn $walkIn): array
    {
        return [
            'id' => $walkIn->id,
            'rate_plan_id' => $walkIn->rate_plan_id,
            'rate_plan' => $walkIn->ratePlan,
            'served_by' => $walkIn->served_by,
            'name' => $walkIn->name,
            'phone' => $walkIn->phone,
            'amount_paid' => round((float) $walkIn->amount_paid, 2),
            'payment_method' => $walkIn->payment_method,
            'visited_at' => $walkIn->visited_at?->toISOString(),
            'notes' => $walkIn->notes,
        ];
    }
}
