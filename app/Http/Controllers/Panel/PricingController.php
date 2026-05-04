<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SystemActivity;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Services\SystemActivityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * Create a new pricing controller instance.
     *
     * @return void
     */
    public function __construct(
        private SystemActivityService $systemActivityService,
    ) {}

    /**
     * Display the pricing page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.pricing', [
            'canManagePricing' => auth()->user()->hasAnyRole(['super admin', 'admin']),
        ]);
    }

    /**
     * Return pricing configuration data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(): JsonResponse
    {
        $membershipRates = RatePlan::query()
            ->whereNotNull('price')
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->map(fn (RatePlan $ratePlan) => $this->transformRatePlan($ratePlan))
            ->values();

        $ptRates = PTProduct::query()
            ->whereNotNull('price')
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->map(fn (PTProduct $ptProduct) => $this->transformPtProduct($ptProduct))
            ->values();

        return response()->json([
            'membership_rates' => $membershipRates,
            'pt_rates' => $ptRates,
            'stats' => [
                'membership_configured' => $membershipRates->count(),
                'membership_active' => $membershipRates->where('is_active', true)->count(),
                'pt_configured' => $ptRates->count(),
                'pt_active' => $ptRates->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Create a new membership rate plan from scratch.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createRatePlan(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'duration_days'  => ['required', 'integer', 'min:1'],
            'price'          => ['required', 'numeric', 'min:0'],
            'is_active'      => ['required', 'boolean'],
            'is_walk_in_only' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $ratePlan = RatePlan::create([
            'name'           => $data['name'],
            'duration_days'  => (int) $data['duration_days'],
            'price'          => round((float) $data['price'], 2),
            'is_active'      => (bool) $data['is_active'],
            'is_walk_in_only' => (bool) ($data['is_walk_in_only'] ?? false),
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ]);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_RATE_PLAN,
            $ratePlan->id,
            'configured',
            $this->ratePlanSystemActivitySnapshot($ratePlan),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'Rate plan created successfully.', 'id' => $ratePlan->id], 201);
    }

    /**
     * Create a new PT product from scratch.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPtProduct(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'session_count'  => ['required', 'integer', 'min:1'],
            'price'          => ['required', 'numeric', 'min:0'],
            'is_active'      => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $ptProduct = PTProduct::create([
            'name'           => $data['name'],
            'session_count'  => (int) $data['session_count'],
            'price'          => round((float) $data['price'], 2),
            'is_active'      => (bool) $data['is_active'],
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ]);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_PT_PRODUCT,
            $ptProduct->id,
            'configured',
            $this->ptProductSystemActivitySnapshot($ptProduct),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'PT rate created successfully.', 'id' => $ptProduct->id], 201);
    }

    /**
     * Update a membership rate plan.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRatePlan(Request $request, RatePlan $ratePlan): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $ratePlan->update($this->validatedRatePlanPayload($request));
        $ratePlan = $ratePlan->fresh();

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_RATE_PLAN,
            $ratePlan->id,
            'updated',
            $this->ratePlanSystemActivitySnapshot($ratePlan),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'Rate plan pricing updated successfully.']);
    }

    /**
     * Delete a membership rate plan.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyRatePlan(RatePlan $ratePlan): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
        $snapshot = $this->ratePlanSystemActivitySnapshot($ratePlan);

        $ratePlan->update([
            'price' => null,
            'effective_from' => null,
            'effective_until' => null,
        ]);
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_RATE_PLAN,
            $ratePlan->id,
            'removed',
            $snapshot,
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(null, 204);
    }

    /**
     * Update a PT product.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePtProduct(Request $request, PTProduct $ptProduct): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $ptProduct->update($this->validatedPtProductPayload($request));
        $ptProduct = $ptProduct->fresh();

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_PT_PRODUCT,
            $ptProduct->id,
            'updated',
            $this->ptProductSystemActivitySnapshot($ptProduct),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'PT rate updated successfully.']);
    }

    /**
     * Delete a PT product.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyPtProduct(PTProduct $ptProduct): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
        $snapshot = $this->ptProductSystemActivitySnapshot($ptProduct);

        $ptProduct->update([
            'price' => null,
            'effective_from' => null,
            'effective_until' => null,
        ]);
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_PT_PRODUCT,
            $ptProduct->id,
            'removed',
            $snapshot,
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(null, 204);
    }

    /**
     * @return array{effective_from:mixed,effective_until:mixed,is_active:bool,price:float}
     */
    private function validatedRatePlanPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_walk_in_only' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $payload = [
            'price' => round((float) $data['price'], 2),
            'is_active' => (bool) $data['is_active'],
            'is_walk_in_only' => (bool) ($data['is_walk_in_only'] ?? false),
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        return $payload;
    }

    /**
     * @return array{effective_from:mixed,effective_until:mixed,is_active:bool,price:float}
     */
    private function validatedPtProductPayload(Request $request): array
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        return [
            'price' => round((float) $data['price'], 2),
            'is_active' => (bool) $data['is_active'],
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRatePlan(RatePlan $ratePlan): array
    {
        return [
            'id' => $ratePlan->id,
            'name' => $ratePlan->name,
            'duration_days' => $ratePlan->duration_days,
            'description' => $ratePlan->description,
            'is_active' => (bool) $ratePlan->is_active,
            'is_walk_in_only' => (bool) $ratePlan->is_walk_in_only,
            'price' => round((float) $ratePlan->price, 2),
            'effective_from' => $ratePlan->effective_from,
            'effective_until' => $ratePlan->effective_until,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPtProduct(PTProduct $ptProduct): array
    {
        return [
            'id' => $ptProduct->id,
            'name' => $ptProduct->name,
            'session_count' => $ptProduct->session_count,
            'category' => $ptProduct->category,
            'description' => $ptProduct->description,
            'is_active' => (bool) $ptProduct->is_active,
            'price' => round((float) $ptProduct->price, 2),
            'effective_from' => $ptProduct->effective_from,
            'effective_until' => $ptProduct->effective_until,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ratePlanSystemActivitySnapshot(RatePlan $ratePlan): array
    {
        return [
            'id' => $ratePlan->id,
            'name' => $ratePlan->name,
            'duration_days' => $ratePlan->duration_days,
            'price' => $ratePlan->price !== null ? round((float) $ratePlan->price, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ptProductSystemActivitySnapshot(PTProduct $ptProduct): array
    {
        return [
            'id' => $ptProduct->id,
            'name' => $ptProduct->name,
            'session_count' => $ptProduct->session_count,
            'price' => $ptProduct->price !== null ? round((float) $ptProduct->price, 2) : null,
        ];
    }
}
