<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\User;
use App\Services\BusinessProfileContext;
use App\Services\MemberPtPackageAlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MembersController extends Controller
{
    public function __construct(
        private MemberPtPackageAlertService $memberPtPackageAlertService,
        private BusinessProfileContext $businessProfileContext,
    ) {
    }

    public function index(): View
    {
        return view('panel.members');
    }

    public function list(Request $request): JsonResponse
    {
        $membersQuery = User::role('member')
            ->with([
                'profile',
                'memberSubscriptions' => fn ($query) => $query
                    ->with(['ratePlan:id,name,duration_days', 'manager:id,name', 'commissionPayroll:id,period_start,period_end,status'])
                    ->orderByDesc('start_date'),
                'memberPtPackages' => fn ($query) => $query
                    ->with([
                        'ptProduct:id,name,session_count,category',
                        'coach:id,name',
                        'createdBy:id,name',
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
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('plan'), fn ($query) => $query->whereHas('memberSubscriptions', fn ($subscriptionQuery) => $subscriptionQuery->where('rate_plan_id', $request->plan)))
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
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
        ]);

        $member->attachPlan(
            (int) $data['rate_plan_id'],
            $data['start_date'] ?? now()->toDateString()
        );

        return response()->json($this->memberPayload($member->fresh(), detailed: true), 201);
    }

    public function show(User $member): View
    {
        abort_unless($member->hasRole('member'), 404);

        return view('panel.members.show', [
            'member' => $this->memberPayload($member->fresh(), detailed: true),
            'memberName' => $member->name,
        ]);
    }

    public function attendance(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);

        $location = $this->locationPayload();

        $records = Attendance::query()
            ->where('user_id', $member->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER)
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->paginate(15)
            ->through(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'branch_id' => $location['id'],
                'branch' => $location,
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

    public function updateMembership(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $currentMembership = $member->currentMembership();

        if ($currentMembership && $currentMembership->isManagerCommissionLocked()) {
            return $this->membershipCommissionLockedResponse($currentMembership);
        }

        $data = $request->validate([
            'rate_plan_id' => ['required', 'exists:rate_plans,id'],
            'start_date' => ['required', 'date'],
        ]);

        $member->changeMembershipPlan((int) $data['rate_plan_id'], $data['start_date']);

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    public function updateMembershipStatus(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $currentMembership = $member->currentMembership();

        if ($currentMembership && $currentMembership->isManagerCommissionLocked()) {
            return $this->membershipCommissionLockedResponse($currentMembership);
        }

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

        if (! $member->currentMembership()) {
            return response()->json(['message' => 'No current membership found.'], 422);
        }

        $member->updateCurrentMembershipStatus($data['status']);

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    public function assignMembershipManager(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $currentMembership = $member->currentMembership();

        if (! $currentMembership) {
            return response()->json(['message' => 'No current membership found.'], 422);
        }

        if (! $currentMembership->canAssignManagerCommission()) {
            return $this->membershipCommissionAssignmentUnavailableResponse($currentMembership);
        }

        $data = $request->validate([
            'manager_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (
            auth()->user()->hasRole('manager')
            && ! auth()->user()->hasAnyRole(['super admin', 'admin'])
            && (int) $data['manager_id'] !== (int) auth()->id()
        ) {
            return response()->json([
                'message' => 'Managers may only assign membership commissions to themselves.',
                'errors' => [
                    'manager_id' => ['Managers may only assign membership commissions to themselves.'],
                ],
            ], 422);
        }

        if (! $this->managerIsAssignable((int) $data['manager_id'])) {
            return response()->json([
                'message' => 'The selected manager is not available for membership commissions.',
                'errors' => [
                    'manager_id' => ['The selected manager is not available for membership commissions.'],
                ],
            ], 422);
        }

        $currentMembership->update([
            'manager_id' => (int) $data['manager_id'],
            'manager_commission_amount' => MemberSubscription::calculateCommissionAmount(
                (float) $currentMembership->sold_price,
                (float) $currentMembership->manager_commission_rate
            ),
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => now(),
        ]);

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    public function storePtPackage(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'pt_product_id' => ['required', 'integer', 'exists:pt_products,id'],
            'coach_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $ptProduct = PTProduct::query()
            ->whereKey($data['pt_product_id'])
            ->where('is_active', true)
            ->whereNotNull('price')
            ->first();

        if (! $ptProduct) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'pt_product_id' => ['The selected PT product is not available.'],
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

        $soldPrice = round((float) ($ptProduct->price ?? 0), 2);
        $coachCommissionRate = round((float) ($ptProduct->coach_commission_rate ?? 40), 2);

        $member->memberPtPackages()->create([
            'pt_product_id' => $ptProduct->id,
            'sold_price' => $soldPrice,
            'coach_commission_rate' => $coachCommissionRate,
            'coach_commission_amount' => MemberPtPackage::calculateCommissionAmount($soldPrice, $coachCommissionRate),
            'coach_id' => $data['coach_id'] ?? null,
            'total_sessions' => $ptProduct->session_count,
            'remaining_sessions' => $ptProduct->session_count,
            'assigned_at' => $data['assigned_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'coach_commission_status' => MemberPtPackage::defaultCommissionStatus(isset($data['coach_id']) ? (int) $data['coach_id'] : null),
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return response()->json($this->memberPayload($member->fresh(), detailed: true), 201);
    }

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

    public function update(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($member->id)],
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
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'start_date' => ['nullable', 'date'],
        ]);

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
            ]
        );

        $member->syncRatePlan(
            $data['rate_plan_id'] ?? null,
            $data['start_date'] ?? now()->toDateString()
        );

        return response()->json($this->memberPayload($member->fresh(), detailed: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(User $member, bool $detailed): array
    {
        $member->loadMissing([
            'profile',
            'memberSubscriptions' => fn ($query) => $query
                ->with(['ratePlan:id,name,duration_days', 'manager:id,name', 'commissionPayroll:id,period_start,period_end,status'])
                ->orderByDesc('start_date'),
            'memberPtPackages' => fn ($query) => $query
                ->with([
                    'ptProduct:id,name,session_count,category',
                    'coach:id,name',
                    'createdBy:id,name',
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
            ] : null,
            'branches' => [$this->locationPayload(includePtProducts: true)],
            'member_subscriptions' => $member->memberSubscriptions
                ->map(fn (MemberSubscription $subscription) => $this->serializeMemberSubscription($subscription))
                ->values()
                ->all(),
            'member_pt_packages' => $member->memberPtPackages
                ->map(fn (MemberPtPackage $package) => $this->serializeMemberPtPackage($package))
                ->values()
                ->all(),
            'available_managers' => $detailed ? $this->availableManagers() : [],
            'available_coaches' => $detailed ? $this->availableCoaches() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMemberSubscription(MemberSubscription $subscription): array
    {
        $commissionStatus = $subscription->effectiveManagerCommissionStatus();
        $isCommissionLocked = $subscription->isManagerCommissionLocked();
        $commissionPayroll = $subscription->commissionPayroll;
        $commissionPayrollLabel = null;

        if ($commissionPayroll && $commissionPayroll->period_start && $commissionPayroll->period_end) {
            $commissionPayrollLabel = $commissionPayroll->period_start->format('Y-m-d').' – '.$commissionPayroll->period_end->format('Y-m-d');
        }

        return [
            'id' => $subscription->id,
            'rate_plan_id' => $subscription->rate_plan_id,
            'rate_plan' => $subscription->ratePlan ? [
                'id' => $subscription->ratePlan->id,
                'name' => $subscription->ratePlan->name,
                'duration_days' => $subscription->ratePlan->duration_days,
            ] : null,
            'branch' => $this->locationPayload(),
            'manager' => $subscription->manager ? [
                'id' => $subscription->manager->id,
                'name' => $subscription->manager->name,
            ] : null,
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'created_at' => $subscription->created_at?->toISOString(),
            'sold_price' => round((float) $subscription->sold_price, 2),
            'manager_commission_rate' => round((float) $subscription->manager_commission_rate, 2),
            'manager_commission_amount' => round((float) $subscription->manager_commission_amount, 2),
            'manager_commission_status' => $commissionStatus,
            'manager_commission_earned_at' => $subscription->manager_commission_earned_at?->toISOString(),
            'commission_payroll' => $commissionPayroll ? [
                'id' => $commissionPayroll->id,
                'status' => $commissionPayroll->status,
                'period_start' => $commissionPayroll->period_start?->toDateString(),
                'period_end' => $commissionPayroll->period_end?->toDateString(),
                'period_label' => $commissionPayrollLabel,
            ] : null,
            'commission_summary' => [
                'is_tracked' => $subscription->hasTrackedManagerCommission(),
                'is_locked' => $isCommissionLocked,
                'lock_reason' => $subscription->managerCommissionLockReason(),
                'is_assignable' => $subscription->canAssignManagerCommission(),
                'assignment_reason' => $subscription->managerCommissionAssignmentReason(),
                'status' => $commissionStatus,
                'sold_price' => round((float) $subscription->sold_price, 2),
                'commission_rate' => round((float) $subscription->manager_commission_rate, 2),
                'commission_amount' => round((float) $subscription->manager_commission_amount, 2),
                'earned_at' => $subscription->manager_commission_earned_at?->toISOString(),
                'manager_name' => $subscription->manager?->name,
                'branch_name' => $this->locationPayload()['name'],
                'payroll_label' => $commissionPayrollLabel,
            ],
            'action_state' => [
                'is_locked' => $isCommissionLocked,
                'can_change_plan' => ! $isCommissionLocked,
                'can_change_status' => ! $isCommissionLocked
                    && in_array($subscription->status, [
                        MemberSubscription::STATUS_ACTIVE,
                        MemberSubscription::STATUS_PAUSED,
                    ], true),
                'can_assign_manager' => $subscription->canAssignManagerCommission(),
                'assign_manager_reason' => $subscription->managerCommissionAssignmentReason(),
                'reason' => $subscription->managerCommissionLockReason(),
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
            'branch_id' => $this->locationPayload()['id'],
            'branch' => $this->locationPayload(),
            'pt_product_id' => $package->pt_product_id,
            'pt_product' => $package->ptProduct ? [
                'id' => $package->ptProduct->id,
                'name' => $package->ptProduct->name,
                'session_count' => $package->ptProduct->session_count,
                'category' => $package->ptProduct->category,
            ] : null,
            'sold_price' => round((float) $package->sold_price, 2),
            'coach_commission_rate' => round((float) $package->coach_commission_rate, 2),
            'coach_commission_amount' => round((float) $package->coach_commission_amount, 2),
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
            'coach_commission_status' => $package->coach_commission_status,
            'coach_commission_earned_at' => $package->coach_commission_earned_at?->toISOString(),
            'notes' => $package->notes,
            'created_by_name' => $package->createdBy?->name,
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
     * @return array{id:int, name:string, city:?string, province:?string, status:?string, pt_products?:array<int, array<string, mixed>>}
     */
    private function locationPayload(bool $includePtProducts = false): array
    {
        $payload = $this->businessProfileContext->legacyLocation();

        if (! $includePtProducts) {
            return $payload;
        }

        $payload['pt_products'] = PTProduct::query()
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
                    'coach_commission_rate' => round((float) ($ptProduct->coach_commission_rate ?? 0), 2),
                ],
            ])
            ->values()
            ->all();

        return $payload;
    }

    private function membershipCommissionLockedResponse(MemberSubscription $subscription): JsonResponse
    {
        $message = $subscription->managerCommissionLockReason() ?? 'This membership can no longer be edited because commission processing has already started.';

        return response()->json([
            'message' => $message,
            'errors' => [
                'membership' => [$message],
            ],
        ], 422);
    }

    private function membershipCommissionAssignmentUnavailableResponse(MemberSubscription $subscription): JsonResponse
    {
        $message = $subscription->managerCommissionAssignmentReason() ?? 'This membership sale commission cannot be assigned right now.';

        return response()->json([
            'message' => $message,
            'errors' => [
                'membership' => [$message],
            ],
        ], 422);
    }

    private function managerIsAssignable(int $managerId): bool
    {
        return User::role('manager')
            ->whereKey($managerId)
            ->where('status', User::STATUS_ACTIVE)
            ->exists();
    }

    private function coachIsAssignable(int $coachId): bool
    {
        return User::role('coach')
            ->whereKey($coachId)
            ->where('status', User::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableCoaches(): array
    {
        $location = $this->locationPayload();

        return User::role('coach')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.status'])
            ->map(fn (User $coach) => [
                'id' => $coach->id,
                'name' => $coach->name,
                'status' => $coach->status,
                'branches' => [$location],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableManagers(): array
    {
        $query = User::role('manager')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name');

        if (auth()->user()->hasRole('manager') && ! auth()->user()->hasAnyRole(['super admin', 'admin'])) {
            $query->whereKey(auth()->id());
        }

        return $query
            ->get(['users.id', 'users.name', 'users.status'])
            ->map(fn (User $manager) => [
                'id' => $manager->id,
                'name' => $manager->name,
                'status' => $manager->status,
            ])
            ->values()
            ->all();
    }
}
