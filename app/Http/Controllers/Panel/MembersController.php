<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SystemActivity;
use App\Models\MemberProfile;
use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\SystemActivityService;
use App\Services\MembershipQrService;
use App\Services\MemberPtPackageAlertService;
use App\Services\MemberPtPackageService;
use App\Services\PosSaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MembersController extends Controller
{
    /**
     * Create a new members controller instance.
     *
     * @return void
     */
    public function __construct(
        private MemberPtPackageAlertService $memberPtPackageAlertService,
        private MemberPtPackageService $memberPtPackageService,
        private MembershipQrService $membershipQrService,
        private PosSaleService $posSaleService,
        private SystemActivityService $systemActivityService,
    ) {}

    /**
     * Display the members page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.members');
    }

    /**
     * Return member records.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $statuses = array_values(array_filter((array) $request->input('status', []), fn ($value) => $value !== null && $value !== ''));
        $plans = array_values(array_filter((array) $request->input('plan', []), fn ($value) => $value !== null && $value !== ''));

        $membersQuery = User::role('member')
            ->with([
                'profile',
                'memberSubscriptions' => fn ($query) => $query
                    ->with(['ratePlan:id,name,duration_days'])
                    ->orderByDesc('start_date'),
                'memberPtPackages' => fn ($query) => $query
                    ->with([
                        'ptProduct:id,name,session_count,category',
                        'coach:id,name',
                        'createdBy:id,name',
                        'cancelledBy:id,name',
                        'saleTransaction:id,status,payment_method,sold_at,void_reason,voided_at',
                        'usages.coach:id,name',
                        'usages.recordedBy:id,name',
                    ])
                    ->orderByDesc('assigned_at'),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(! empty($statuses), fn ($query) => $query->whereIn('status', $statuses))
            ->when(! empty($plans), fn ($query) => $query->whereHas('memberSubscriptions', fn ($subscriptionQuery) => $subscriptionQuery->whereIn('rate_plan_id', $plans)))
            ->orderByDesc('created_at');

        $members = (clone $membersQuery)
            ->paginate(15)
            ->through(fn (User $member) => $this->memberPayload($member, detailed: false))
            ->withQueryString();

        $statsQuery = User::role('member');

        return response()->json([
            'members' => $members,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'active' => (clone $statsQuery)->where('status', User::STATUS_ACTIVE)->count(),
                'inactive' => (clone $statsQuery)->where('status', User::STATUS_INACTIVE)->count(),
                'suspended' => (clone $statsQuery)->where('status', User::STATUS_SUSPENDED)->count(),
            ],
        ]);
    }

    /**
     * Create a member record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'status' => [
                'required',
                Rule::in([
                    User::STATUS_ACTIVE,
                    User::STATUS_INACTIVE,
                    User::STATUS_SUSPENDED,
                ]),
            ],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'discount_type' => ['nullable', Rule::in([MemberProfile::DISCOUNT_STUDENT, MemberProfile::DISCOUNT_SENIOR])],
            'rate_plan_id' => ['required', 'exists:rate_plans,id'],
            'start_date' => ['nullable', 'date'],
        ]);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
        ]);

        $member->assignRole('member');

        $member->profile()->create([
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'discount_type' => $data['discount_type'] ?? null,
        ]);

        $subscription = $member->attachPlan(
            (int) $data['rate_plan_id'],
            $data['start_date'] ?? now()->toDateString()
        );
        $member = $member->fresh();

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER,
            $member->id,
            'created',
            $this->memberSystemActivitySnapshot($member),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $subscription->id,
            'created',
            $this->membershipSystemActivitySnapshot($subscription->fresh(['ratePlan', 'member'])),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
        $this->membershipQrService->sendEmail($subscription);

        return response()->json($this->memberPayload($member, detailed: true), 201);
    }

    /**
     * Display a member detail page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function show(User $member): View
    {
        abort_unless($member->hasRole('member'), 404);

        return view('panel.members.show', [
            'member' => $this->memberPayload($member->fresh(), detailed: true),
            'memberName' => $member->name,
        ]);
    }

    /**
     * Return member attendance records.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function attendance(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);

        $records = Attendance::query()
            ->where('user_id', $member->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER)
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->paginate(15)
            ->through(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'attendee_type' => $attendance->attendee_type,
                'name' => $attendance->name,
                'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                'checked_out_at' => $attendance->checked_out_at?->toISOString(),
                'notes' => $attendance->notes,
            ]);

        $statsQuery = Attendance::query()
            ->where('user_id', $member->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER);

        return response()->json([
            'records' => $records,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'this_month' => (clone $statsQuery)->whereMonth('checked_in_at', now()->month)->whereYear('checked_in_at', now()->year)->count(),
                'currently_in' => (clone $statsQuery)->whereNull('checked_out_at')->count(),
            ],
        ]);
    }

    /**
     * Update a member membership.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMembership(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->isManagement(), 403);

        $data = $request->validate([
            'rate_plan_id' => ['required', 'exists:rate_plans,id'],
            'start_date' => ['required', 'date'],
        ]);

        $membership = $member->changeMembershipPlan((int) $data['rate_plan_id'], $data['start_date']);
        $membershipWasCreated = $membership->wasRecentlyCreated;

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $membership->id,
            'plan_changed',
            $this->membershipSystemActivitySnapshot($membership->fresh(['ratePlan', 'member'])),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        if ($membershipWasCreated) {
            $this->membershipQrService->sendEmail($membership);
        }

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    /**
     * Update a member membership status.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMembershipStatus(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->isManagement(), 403);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    MemberSubscription::STATUS_ACTIVE,
                    MemberSubscription::STATUS_PAUSED,
                    MemberSubscription::STATUS_CANCELLED,
                ]),
            ],
        ]);

        $membershipToUpdate = $member->currentMembership();

        if (! $membershipToUpdate) {
            return response()->json(['message' => 'No current membership found.'], 422);
        }

        $member->updateCurrentMembershipStatus($data['status']);
        $membership = $membershipToUpdate->fresh(['ratePlan', 'member']);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            $membership->id,
            'status_updated',
            $this->membershipSystemActivitySnapshot($membership),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    /**
     * Return a membership QR code payload.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function membershipQr(User $member, MemberSubscription $membership): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless((int) $membership->user_id === (int) $member->id, 404);

        return response()->json($this->membershipQrService->modalPayload($membership));
    }

    /**
     * Sell a PT package to a member.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePtPackage(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->isManagement(), 403);

        $data = $request->validate([
            'pt_product_id' => ['required', 'integer', 'exists:pt_products,id'],
            'coach_id' => ['required', 'integer', 'exists:users,id'],
            'assigned_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'payment_method' => ['required', Rule::in(SaleTransaction::supportedPaymentMethods())],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->posSaleService->processSale([
            ...$data,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'member_id' => $member->id,
            'sold_at' => $data['sold_at'] ?? now()->toDateTimeString(),
        ], auth()->user());

        return response()->json($this->memberPayload($member->fresh(), detailed: true), 201);
    }

    /**
     * Cancel an unused member PT package or void its linked sale.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPtPackage(
        Request $request,
        User $member,
        MemberPtPackage $memberPtPackage,
    ): JsonResponse {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->isManagement(), 403);
        abort_unless((int) $memberPtPackage->user_id === (int) $member->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $saleTransaction = $memberPtPackage->saleTransaction()->first();

        if ($saleTransaction) {
            abort_unless(
                $saleTransaction->type === SaleTransaction::TYPE_PT_PACKAGE
                    && (int) $saleTransaction->member_id === (int) $member->id,
                409,
                'This PT package has an invalid sale link.',
            );

            $this->posSaleService->voidSale($saleTransaction, auth()->user(), $data['reason']);
        } else {
            $this->memberPtPackageService->cancelUnused(
                $memberPtPackage,
                auth()->user(),
                $data['reason'],
            );
        }

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    /**
     * Create a member PT session usage record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePtSessionUsage(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager', 'staff']), 403);

        $data = $request->validate([
            'member_pt_package_id' => ['required', 'integer'],
            'coach_id' => ['nullable', 'integer', 'exists:users,id'],
            'sessions_used' => ['required', 'integer', 'min:1'],
            'used_at' => ['required', 'date'],
            'confirmed_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $package = $member->memberPtPackages()
            ->whereKey($data['member_pt_package_id'])
            ->first();

        if (! $package) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'member_pt_package_id' => ['The selected PT package was not found for this member.'],
                ],
            ], 422);
        }

        if (! empty($data['coach_id']) && ! $this->coachIsAssignable((int) $data['coach_id'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'coach_id' => ['The selected coach is not available.'],
                ],
            ], 422);
        }

        $previousRemainingSessions = (int) $package->remaining_sessions;

        $package->consumeSessions(
            (int) $data['sessions_used'],
            $data['used_at'],
            auth()->id(),
            isset($data['coach_id']) ? (int) $data['coach_id'] : null,
            $data['confirmed_by'] ?? null,
            $data['notes'] ?? null
        );

        $this->memberPtPackageAlertService->notifyIfRunningLow(
            $package->fresh(),
            $previousRemainingSessions,
            $data['used_at']
        );

        return response()->json($this->memberPayload($member->fresh(), detailed: true), 201);
    }

    /**
     * Update a member record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($member->id)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => [
                'nullable',
                Rule::in([
                    User::STATUS_ACTIVE,
                    User::STATUS_INACTIVE,
                    User::STATUS_SUSPENDED,
                ]),
            ],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'discount_type' => ['nullable', Rule::in([MemberProfile::DISCOUNT_STUDENT, MemberProfile::DISCOUNT_SENIOR])],
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'start_date' => ['nullable', 'date'],
        ]);

        $previousMembership = $member->currentMembership()?->fresh();

        $member->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? $member->status,
        ]);

        $member->profile()->updateOrCreate(
            ['user_id' => $member->id],
            [
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
            ]
        );

        $member->syncRatePlan(
            $data['rate_plan_id'] ?? null,
            $data['start_date'] ?? now()->toDateString()
        );
        $member = $member->fresh();

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_MEMBER,
            $member->id,
            'updated',
            $this->memberSystemActivitySnapshot($member),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        $currentMembership = $member->currentMembership();

        if ($currentMembership && (
            ! $previousMembership
            || (int) $previousMembership->id !== (int) $currentMembership->id
            || (int) $previousMembership->rate_plan_id !== (int) $currentMembership->rate_plan_id
            || optional($previousMembership->start_date)->toDateString() !== optional($currentMembership->start_date)->toDateString()
        )) {
            $this->systemActivityService->recordSubjectEvent(
                SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
                $currentMembership->id,
                'plan_changed',
                $this->membershipSystemActivitySnapshot($currentMembership->fresh(['ratePlan', 'member'])),
                [],
                auth()->id(),
                auth()->user()?->name,
                now(),
            );
        }

        return response()->json($this->memberPayload($member, detailed: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(User $member, bool $detailed): array
    {
        $member->loadMissing([
            'profile',
            'memberSubscriptions' => fn ($query) => $query
                ->with(['ratePlan:id,name,duration_days'])
                ->orderByDesc('start_date'),
            'memberPtPackages' => fn ($query) => $query
                ->with([
                    'ptProduct:id,name,session_count,category',
                    'coach:id,name',
                    'createdBy:id,name',
                    'cancelledBy:id,name',
                    'saleTransaction:id,status,payment_method,sold_at,void_reason,voided_at',
                    'usages.coach:id,name',
                    'usages.recordedBy:id,name',
                ])
                ->orderByDesc('assigned_at'),
        ]);

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'status' => $member->status,
            'created_at' => $member->created_at?->toISOString(),
            'profile' => $member->profile ? [
                'date_of_birth' => $member->profile->date_of_birth?->toDateString(),
                'gender' => $member->profile->gender,
                'emergency_contact_name' => $member->profile->emergency_contact_name,
                'emergency_contact_phone' => $member->profile->emergency_contact_phone,
                'notes' => $member->profile->notes,
                'discount_type' => $member->profile->discount_type,
            ] : null,
            'pt_products' => $detailed ? $this->availablePtProducts() : [],
            'member_subscriptions' => $member->memberSubscriptions
                ->map(fn (MemberSubscription $subscription) => $this->serializeMemberSubscription($subscription))
                ->values()
                ->all(),
            'member_pt_packages' => $member->memberPtPackages
                ->map(fn (MemberPtPackage $package) => $this->serializeMemberPtPackage($package))
                ->values()
                ->all(),
            'available_coaches' => $detailed ? $this->availableCoaches() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMemberSubscription(MemberSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'rate_plan_id' => $subscription->rate_plan_id,
            'rate_plan' => $subscription->ratePlan ? [
                'id' => $subscription->ratePlan->id,
                'name' => $subscription->ratePlan->name,
                'duration_days' => $subscription->ratePlan->duration_days,
            ] : null,
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'created_at' => $subscription->created_at?->toISOString(),
            'sold_price' => round((float) $subscription->sold_price, 2),
            'qr_url' => route('panel.members.memberships.qr', [$subscription->user_id, $subscription->id]),
            'action_state' => [
                'is_locked' => false,
                'can_change_plan' => true,
                'can_change_status' => in_array($subscription->status, [
                        MemberSubscription::STATUS_ACTIVE,
                        MemberSubscription::STATUS_PAUSED,
                    ], true),
                'reason' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMemberPtPackage(MemberPtPackage $package): array
    {
        return [
            'id' => $package->id,
            'pt_product_id' => $package->pt_product_id,
            'pt_product' => $package->ptProduct ? [
                'id' => $package->ptProduct->id,
                'name' => $package->ptProduct->name,
                'session_count' => $package->ptProduct->session_count,
                'category' => $package->ptProduct->category,
            ] : null,
            'sold_price' => round((float) $package->sold_price, 2),
            'coach_id' => $package->coach_id,
            'coach' => $package->coach ? [
                'id' => $package->coach->id,
                'name' => $package->coach->name,
            ] : null,
            'total_sessions' => (int) $package->total_sessions,
            'remaining_sessions' => (int) $package->remaining_sessions,
            'assigned_at' => $package->assigned_at?->toDateString(),
            'expires_at' => $package->expires_at?->toDateString(),
            'status' => $package->status,
            'notes' => $package->notes,
            'created_by' => $package->createdBy ? [
                'id' => $package->createdBy->id,
                'name' => $package->createdBy->name,
            ] : null,
            'sale_transaction' => $package->saleTransaction ? [
                'id' => $package->saleTransaction->id,
                'receipt_number' => $package->saleTransaction->receiptNumber(),
                'receipt_url' => route('panel.sales.receipt', $package->saleTransaction),
                'status' => $package->saleTransaction->status,
                'payment_method' => $package->saleTransaction->payment_method,
                'sold_at' => $package->saleTransaction->sold_at?->toISOString(),
                'void_reason' => $package->saleTransaction->void_reason,
                'voided_at' => $package->saleTransaction->voided_at?->toISOString(),
            ] : null,
            'cancellation_reason' => $package->cancellation_reason,
            'cancelled_by' => $package->cancelledBy ? [
                'id' => $package->cancelledBy->id,
                'name' => $package->cancelledBy->name,
            ] : null,
            'cancelled_at' => $package->cancelled_at?->toISOString(),
            'action_state' => $this->ptPackageActionState($package),
            'usages' => $package->usages
                ->map(fn (MemberPtSessionUsage $usage) => [
                    'id' => $usage->id,
                    'coach_id' => $usage->coach_id,
                    'coach' => $usage->coach ? [
                        'id' => $usage->coach->id,
                        'name' => $usage->coach->name,
                    ] : null,
                    'recorded_by' => $usage->recorded_by,
                    'recorded_by_name' => $usage->recordedBy?->name,
                    'sessions_used' => (int) $usage->sessions_used,
                    'used_at' => $usage->used_at?->toISOString(),
                    'confirmed_by' => $usage->confirmed_by,
                    'notes' => $usage->notes,
                    'created_at' => $usage->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{can_cancel: bool, label: string, reason: ?string}
     */
    private function ptPackageActionState(MemberPtPackage $package): array
    {
        $reason = match (true) {
            $package->status === MemberPtPackage::STATUS_CANCELLED => 'This PT package has already been cancelled.',
            $package->usages->isNotEmpty() => 'PT packages with recorded session usage cannot be cancelled.',
            $package->status !== MemberPtPackage::STATUS_ACTIVE => 'Only active PT packages can be cancelled.',
            default => null,
        };

        return [
            'can_cancel' => $reason === null,
            'label' => $package->saleTransaction ? 'Void sale' : 'Cancel package',
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberSystemActivitySnapshot(User $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'status' => $member->status,
            'email' => $member->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSystemActivitySnapshot(MemberSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'member_id' => $subscription->user_id,
            'member_name' => $subscription->member?->name ?? 'Unknown Member',
            'rate_plan_id' => $subscription->rate_plan_id,
            'rate_plan_name' => $subscription->ratePlan?->name,
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availablePtProducts(): array
    {
        return PTProduct::query()
            ->where('is_active', true)
            ->whereNotNull('price')
            ->orderBy('session_count')
            ->orderBy('name')
            ->get()
            ->map(fn (PTProduct $ptProduct) => [
                'id' => $ptProduct->id,
                'name' => $ptProduct->name,
                'session_count' => $ptProduct->session_count,
                'category' => $ptProduct->category,
                'description' => $ptProduct->description,
                'pivot' => [
                    'price' => round((float) $ptProduct->price, 2),
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Determine whether a coach can be assigned.
     *
     * @return bool
     */
    private function coachIsAssignable(int $coachId): bool
    {
        return User::query()
            ->activeCoaches()
            ->whereKey($coachId)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableCoaches(): array
    {
        return User::query()
            ->activeCoaches()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.status'])
            ->map(fn (User $coach) => [
                'id' => $coach->id,
                'name' => $coach->name,
                'status' => $coach->status,
            ])
            ->values()
            ->all();
    }

}
