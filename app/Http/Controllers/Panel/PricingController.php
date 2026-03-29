<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PTProduct;
use App\Models\RatePlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(): View
    {
        return view('panel.pricing', [
            'canManagePricing' => auth()->user()->hasAnyRole(['super admin', 'admin']),
        ]);
    }

    public function show(Branch $branch): JsonResponse
    {
        $branchRatePlans = $branch->ratePlans()
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $branchPtProducts = $branch->ptProducts()
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $membershipRates = $branchRatePlans
            ->map(function (RatePlan $ratePlan) {
                return [
                    'id' => $ratePlan->id,
                    'name' => $ratePlan->name,
                    'duration_days' => $ratePlan->duration_days,
                    'description' => $ratePlan->description,
                    'is_active' => (bool) $ratePlan->is_active,
                    'branch_price' => round((float) $ratePlan->pivot->price, 2),
                    'branch_is_active' => (bool) $ratePlan->pivot->is_active,
                    'effective_from' => $ratePlan->pivot->effective_from,
                    'effective_until' => $ratePlan->pivot->effective_until,
                ];
            })
            ->values();

        $ptRates = $branchPtProducts
            ->map(function (PTProduct $ptProduct) {
                return [
                    'id' => $ptProduct->id,
                    'name' => $ptProduct->name,
                    'session_count' => $ptProduct->session_count,
                    'category' => $ptProduct->category,
                    'description' => $ptProduct->description,
                    'is_active' => (bool) $ptProduct->is_active,
                    'branch_price' => round((float) $ptProduct->pivot->price, 2),
                    'branch_is_active' => (bool) $ptProduct->pivot->is_active,
                    'coach_commission_rate' => round((float) $ptProduct->pivot->coach_commission_rate, 2),
                    'effective_from' => $ptProduct->pivot->effective_from,
                    'effective_until' => $ptProduct->pivot->effective_until,
                ];
            })
            ->values();

        $availableMembershipRatePlans = RatePlan::query()
            ->where('is_active', true)
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->reject(fn (RatePlan $ratePlan) => $branchRatePlans->has($ratePlan->id))
            ->map(function (RatePlan $ratePlan) {
                return [
                    'id' => $ratePlan->id,
                    'name' => $ratePlan->name,
                    'duration_days' => $ratePlan->duration_days,
                    'description' => $ratePlan->description,
                    'is_active' => (bool) $ratePlan->is_active,
                ];
            })
            ->values();

        $availablePtProducts = PTProduct::query()
            ->where('is_active', true)
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->reject(fn (PTProduct $ptProduct) => $branchPtProducts->has($ptProduct->id))
            ->map(function (PTProduct $ptProduct) {
                return [
                    'id' => $ptProduct->id,
                    'name' => $ptProduct->name,
                    'session_count' => $ptProduct->session_count,
                    'category' => $ptProduct->category,
                    'description' => $ptProduct->description,
                    'is_active' => (bool) $ptProduct->is_active,
                ];
            })
            ->values();

        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'city' => $branch->city,
                'province' => $branch->province,
                'status' => $branch->status,
            ],
            'membership_rates' => $membershipRates,
            'available_membership_rate_plans' => $availableMembershipRatePlans,
            'pt_rates' => $ptRates,
            'available_pt_products' => $availablePtProducts,
            'stats' => [
                'membership_configured' => $membershipRates->count(),
                'membership_active' => $membershipRates->where('branch_is_active', true)->count(),
                'pt_configured' => $ptRates->count(),
                'pt_active' => $ptRates->where('branch_is_active', true)->count(),
            ],
        ]);
    }

    public function storeRatePlan(Request $request, Branch $branch, RatePlan $ratePlan): JsonResponse
    {
        $this->ensureCanManagePricing();
        abort_unless($ratePlan->is_active, 404);

        abort_if($branch->ratePlans()->where('rate_plan_id', $ratePlan->id)->exists(), 422, 'Rate plan pricing already exists for this branch.');

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $branch->ratePlans()->attach($ratePlan->id, $this->transformRatePlanPayload($data));

        return response()->json(['message' => 'Rate plan pricing created successfully.'], 201);
    }

    public function updateRatePlan(Request $request, Branch $branch, RatePlan $ratePlan): JsonResponse
    {
        $this->ensureCanManagePricing();

        abort_unless($branch->ratePlans()->where('rate_plan_id', $ratePlan->id)->exists(), 404);

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $branch->ratePlans()->updateExistingPivot($ratePlan->id, $this->transformRatePlanPayload($data));

        return response()->json(['message' => 'Rate plan pricing updated successfully.']);
    }

    public function destroyRatePlan(Branch $branch, RatePlan $ratePlan): JsonResponse
    {
        $this->ensureCanManagePricing();

        $branch->ratePlans()->detach($ratePlan->id);

        return response()->json(null, 204);
    }

    public function storePtProduct(Request $request, Branch $branch, PTProduct $ptProduct): JsonResponse
    {
        $this->ensureCanManagePricing();
        abort_unless($ptProduct->is_active, 404);

        abort_if($branch->ptProducts()->where('pt_product_id', $ptProduct->id)->exists(), 422, 'PT rate already exists for this branch.');

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'coach_commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $branch->ptProducts()->attach($ptProduct->id, $this->transformPtProductPayload($data));

        return response()->json(['message' => 'PT rate created successfully.'], 201);
    }

    public function updatePtProduct(Request $request, Branch $branch, PTProduct $ptProduct): JsonResponse
    {
        $this->ensureCanManagePricing();

        abort_unless($branch->ptProducts()->where('pt_product_id', $ptProduct->id)->exists(), 404);

        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'coach_commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $branch->ptProducts()->updateExistingPivot($ptProduct->id, $this->transformPtProductPayload($data));

        return response()->json(['message' => 'PT rate updated successfully.']);
    }

    public function destroyPtProduct(Branch $branch, PTProduct $ptProduct): JsonResponse
    {
        $this->ensureCanManagePricing();

        $branch->ptProducts()->detach($ptProduct->id);

        return response()->json(null, 204);
    }

    /**
     * @param  array{
     *     price:numeric-string|int|float,
     *     is_active:bool,
     *     effective_from?:string|null,
     *     effective_until?:string|null
     * }  $data
     * @return array{
     *     price:float,
     *     is_active:bool,
     *     effective_from:string|null,
     *     effective_until:string|null
     * }
     */
    private function transformRatePlanPayload(array $data): array
    {
        return [
            'price' => round((float) $data['price'], 2),
            'is_active' => (bool) $data['is_active'],
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     price:numeric-string|int|float,
     *     coach_commission_rate:numeric-string|int|float,
     *     is_active:bool,
     *     effective_from?:string|null,
     *     effective_until?:string|null
     * }  $data
     * @return array{
     *     price:float,
     *     coach_commission_rate:float,
     *     is_active:bool,
     *     effective_from:string|null,
     *     effective_until:string|null
     * }
     */
    private function transformPtProductPayload(array $data): array
    {
        return [
            'price' => round((float) $data['price'], 2),
            'coach_commission_rate' => round((float) $data['coach_commission_rate'], 2),
            'is_active' => (bool) $data['is_active'],
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ];
    }

    private function ensureCanManagePricing(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
    }
}
