<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Services\AuditHistoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        private AuditHistoryService $auditHistoryService,
    ) {}

    public function index(): View
    {
        return view('panel.pricing', [
            'canManagePricing' => auth()->user()->hasAnyRole(['super admin', 'admin']),
        ]);
    }

    public function show(): JsonResponse
    {
        $membershipRates = RatePlan::query()
            ->where('is_active', true)
            ->whereNotNull('price')
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->map(fn (RatePlan $ratePlan) => $this->transformRatePlan($ratePlan))
            ->values();

        $availableMembershipRatePlans = RatePlan::query()
            ->where('is_active', true)
            ->whereNull('price')
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get()
            ->map(fn (RatePlan $ratePlan) => [
                'id' => $ratePlan->id,
                'name' => $ratePlan->name,
                'duration_days' => $ratePlan->duration_days,
                'description' => $ratePlan->description,
                'is_active' => (bool) $ratePlan->is_active,
            ])
            ->values();

        $ptRates = PTProduct::query()
            ->where('is_active', true)
            ->whereNotNull('price')
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->map(fn (PTProduct $ptProduct) => $this->transformPtProduct($ptProduct))
            ->values();

        $availablePtProducts = PTProduct::query()
            ->where('is_active', true)
            ->whereNull('price')
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->map(fn (PTProduct $ptProduct) => [
                'id' => $ptProduct->id,
                'name' => $ptProduct->name,
                'session_count' => $ptProduct->session_count,
                'category' => $ptProduct->category,
                'description' => $ptProduct->description,
                'is_active' => (bool) $ptProduct->is_active,
            ])
            ->values();

        return response()->json([
            'membership_rates' => $membershipRates,
            'available_membership_rate_plans' => $availableMembershipRatePlans,
            'pt_rates' => $ptRates,
            'available_pt_products' => $availablePtProducts,
            'stats' => [
                'membership_configured' => $membershipRates->count(),
                'membership_active' => $membershipRates->where('is_active', true)->count(),
                'pt_configured' => $ptRates->count(),
                'pt_active' => $ptRates->where('is_active', true)->count(),
            ],
        ]);
    }

    public function storeRatePlan(Request $request, RatePlan $ratePlan): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
        abort_unless($ratePlan->is_active, 404);

        $ratePlan->update($this->validatedRatePlanPayload($request));
        $ratePlan = $ratePlan->fresh();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_RATE_PLAN,
            $ratePlan->id,
            'configured',
            $this->ratePlanAuditSnapshot($ratePlan),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'Rate plan pricing created successfully.'], 201);
    }

    public function updateRatePlan(Request $request, RatePlan $ratePlan): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $ratePlan->update($this->validatedRatePlanPayload($request));
        $ratePlan = $ratePlan->fresh();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_RATE_PLAN,
            $ratePlan->id,
            'updated',
            $this->ratePlanAuditSnapshot($ratePlan),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'Rate plan pricing updated successfully.']);
    }

    public function destroyRatePlan(RatePlan $ratePlan): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
        $snapshot = $this->ratePlanAuditSnapshot($ratePlan);

        $ratePlan->update([
            'price' => null,
            'effective_from' => null,
            'effective_until' => null,
        ]);
        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_RATE_PLAN,
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

    public function storePtProduct(Request $request, PTProduct $ptProduct): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
        abort_unless($ptProduct->is_active, 404);

        $ptProduct->update($this->validatedPtProductPayload($request));
        $ptProduct = $ptProduct->fresh();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PT_PRODUCT,
            $ptProduct->id,
            'configured',
            $this->ptProductAuditSnapshot($ptProduct),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'PT rate created successfully.'], 201);
    }

    public function updatePtProduct(Request $request, PTProduct $ptProduct): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);

        $ptProduct->update($this->validatedPtProductPayload($request));
        $ptProduct = $ptProduct->fresh();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PT_PRODUCT,
            $ptProduct->id,
            'updated',
            $this->ptProductAuditSnapshot($ptProduct),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'PT rate updated successfully.']);
    }

    public function destroyPtProduct(PTProduct $ptProduct): JsonResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin']), 403);
        $snapshot = $this->ptProductAuditSnapshot($ptProduct);

        $ptProduct->update([
            'price' => null,
            'effective_from' => null,
            'effective_until' => null,
        ]);
        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PT_PRODUCT,
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
    private function ratePlanAuditSnapshot(RatePlan $ratePlan): array
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
    private function ptProductAuditSnapshot(PTProduct $ptProduct): array
    {
        return [
            'id' => $ptProduct->id,
            'name' => $ptProduct->name,
            'session_count' => $ptProduct->session_count,
            'price' => $ptProduct->price !== null ? round((float) $ptProduct->price, 2) : null,
        ];
    }
}
