<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\RatePlan;
use App\Models\WalkIn;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WalkInsController extends Controller
{
    /**
     * Walk In Index
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.walk-ins');
    }

    /**
     * List
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $walkIns = WalkIn::with(['branch', 'ratePlan'])
            ->when(!empty($request->search), fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->when($request->branch, fn($q) => $q->where('branch_id', $request->branch))
            ->when($request->date_from, fn($q) => $q->whereDate('visited_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('visited_at', '<=', $request->date_to))
            ->orderBy('visited_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $baseStats = WalkIn::when($request->branch, fn($q) => $q->where('branch_id', $request->branch), function ($q) {
            if (!auth()->user()->hasRole('super admin')) {
                $q->whereIn('branch_id', auth()->user()->branches()->pluck('id'));
            }
        });

        $stats = [
            'today' => (clone $baseStats)->whereDate('visited_at', Carbon::today())->count(),
            'this_week' => (clone $baseStats)->where('visited_at', '>=', Carbon::now()->startOfWeek())->count(),
            'this_month' => (clone $baseStats)->where('visited_at', '>=', Carbon::now()->startOfMonth())->count(),
            'revenue_today' => (clone $baseStats)->whereDate('visited_at', Carbon::today())->sum('amount_paid'),
        ];

        return response()->json(compact('walkIns', 'stats'));
    }

    /**
     * Store
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'visited_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['served_by'] = auth()->id();
        $data['visited_at'] = $data['visited_at'] ?? now();

        $walkIn = WalkIn::create($data);

        return response()->json($walkIn->load(['branch', 'ratePlan']), 201);
    }

    /**
     * Summary of update
     * @param Request $request
     * @param WalkIn $walkIn
     * @return JsonResponse
     */
    public function update(Request $request, WalkIn $walkIn): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'visited_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $walkIn->update($data);

        return response()->json($walkIn->fresh()->load(['branch', 'ratePlan']));
    }

    /**
     * Summary of destroy
     * @param WalkIn $walkIn
     * @return JsonResponse
     */
    public function destroy(WalkIn $walkIn): JsonResponse
    {
        $walkIn->delete();

        return response()->json(null, 204);
    }
}
