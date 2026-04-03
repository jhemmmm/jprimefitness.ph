<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\User;
use App\Services\MemberPtPackageAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MembersController extends Controller
{
    public function __construct(private MemberPtPackageAlertService $memberPtPackageAlertService)
    {
    }

    /**
     * Member Index
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.members');
    }

    /**
     * List members with filters and pagination
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        // Get members
        $members = User::role('member')
            ->with([
                'profile',
                'branches',
                'memberSubscriptions.ratePlan',
                'memberPtPackages' => fn($query) => $query
                    ->with('ptProduct:id,name')
                    ->orderByDesc('assigned_at'),
            ])
            ->when(!empty($request->search), fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->when(!auth()->user()->hasRole('super admin'), fn($q) => $q->whereHas('branches', fn($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('branches.id'))))
            ->when($request->branch, fn($q, $b) => $q->whereHas('branches', fn($qq) => $qq->where('branches.id', $b)))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->plan, fn($q, $p) => $q->whereHas('memberSubscriptions', fn($rq) => $rq->where('rate_plan_id', $p)))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Get stats
        $statsQuery = User::role('member')
            ->when(!auth()->user()->hasRole('super admin'), fn($q) => $q->whereHas('branches', fn($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('branches.id'))))
            ->when($request->branch, fn($q, $b) => $q->whereHas('branches', fn($qq) => $qq->where('branches.id', $b)));

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
     * Summary of store
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'status' => [
                'required',
                Rule::in([
                    User::STATUS_ACTIVE,
                    User::STATUS_INACTIVE,
                    User::STATUS_SUSPENDED,
                ])
            ],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'rate_plan_id' => ['required', 'exists:rate_plans,id'],
            'start_date' => ['nullable', 'date'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
        ]);

        $user->branches()->sync($data['branch_ids']);
        $user->assignRole('member');

        $user->profile()->create([
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $user->attachPlan(
            (int) $data['rate_plan_id'],
            $data['start_date'] ?? now()->toDateString()
        );

        return response()->json(
            $this->loadMemberDetail($user->fresh()),
            201
        );
    }

    /**
     * Display member details
     * @param User $member
     * @return \Illuminate\Contracts\View\View
     */
    public function show(User $member): View
    {
        abort_unless($member->hasRole('member'), 404);
        return view('panel.members.show', [
            'member' => $this->loadMemberDetail($member),
        ]);
    }

    /**
     * Display member attendance records with filters and pagination
     * @param Request $request
     * @param User $member
     * @return JsonResponse
     */
    public function attendance(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        $records = Attendance::where('user_id', $member->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER)
            ->with('branch')
            ->when($request->filled('date_from'), fn($query) => $query->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($query) => $query->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->paginate(15);

        $statsQuery = Attendance::where('user_id', $member->id)
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
     * Update member's membership plan
     * @param Request $request
     * @param User $member
     * @return JsonResponse
     */
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

        return response()->json(
            $this->loadMemberDetail($member->fresh())
        );
    }

    /**
     * Update member's membership status
     * @param Request $request
     * @param User $member
     * @return JsonResponse
     */
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
                ])
            ],
        ]);

        if (!$member->currentMembership()) {
            return response()->json(['message' => 'No current membership found.'], 422);
        }

        $member->updateCurrentMembershipStatus($data['status']);

        return response()->json(
            $this->loadMemberDetail($member->fresh())
        );
    }

    /**
     * Assign manager commission for the current membership
     * @param Request $request
     * @param User $member
     * @return JsonResponse
     */
    public function assignMembershipManager(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $currentMembership = $member->currentMembership();

        if (!$currentMembership) {
            return response()->json(['message' => 'No current membership found.'], 422);
        }

        if (!$currentMembership->canAssignManagerCommission()) {
            return $this->membershipCommissionAssignmentUnavailableResponse($currentMembership);
        }

        $data = $request->validate([
            'manager_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (auth()->user()->hasRole('manager') && !auth()->user()->hasAnyRole(['super admin', 'admin']) && (int) $data['manager_id'] !== (int) auth()->id()) {
            return response()->json([
                'message' => 'Managers may only assign membership commissions to themselves.',
                'errors' => [
                    'manager_id' => ['Managers may only assign membership commissions to themselves.'],
                ],
            ], 422);
        }

        if (!$this->managerIsAssignableToMembership($member, $currentMembership, (int) $data['manager_id'])) {
            return response()->json([
                'message' => 'The selected manager is not assigned to this membership branch.',
                'errors' => [
                    'manager_id' => ['The selected manager is not assigned to this membership branch.'],
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

        return response()->json(
            $this->loadMemberDetail($member->fresh())
        );
    }

    /**
     * Store a new PT package for a member
     * @param Request $request
     * @param User $member
     * @return JsonResponse
     */
    public function storePtPackage(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'pt_product_id' => ['required', 'integer', 'exists:pt_products,id'],
            'coach_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'notes' => ['nullable', 'string'],
        ]);

        if (!$member->branches()->whereKey($data['branch_id'])->exists()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'branch_id' => ['The selected branch is not assigned to this member.'],
                ],
            ], 422);
        }

        $ptProduct = PTProduct::findOrFail($data['pt_product_id']);
        $branchPricing = $ptProduct->branches()
            ->whereKey($data['branch_id'])
            ->wherePivot('is_active', true)
            ->first();

        if (!$branchPricing) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'pt_product_id' => ['The selected PT product is not available for the chosen branch.'],
                ],
            ], 422);
        }

        if (!empty($data['coach_id']) && !$this->coachIsAssignableToBranch((int) $data['coach_id'], (int) $data['branch_id'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'coach_id' => ['The selected coach is not assigned to the chosen branch.'],
                ],
            ], 422);
        }

        $soldPrice = round((float) ($branchPricing->pivot->price ?? 0), 2);
        $coachCommissionRate = round((float) ($branchPricing->pivot->coach_commission_rate ?? 40), 2);

        $package = $member->memberPtPackages()->create([
            'branch_id' => $data['branch_id'],
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

        return response()->json($this->loadMemberDetail($member->fresh()), 201);
    }

    /**
     * Store PT session usage for a member
     * @param Request $request
     * @param User $member
     * @return JsonResponse
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

        if (!$package) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'member_pt_package_id' => ['The selected PT package was not found for this member.'],
                ],
            ], 422);
        }

        if (!empty($data['coach_id']) && !$this->coachIsAssignableToBranch((int) $data['coach_id'], (int) $package->branch_id)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'coach_id' => ['The selected coach is not assigned to this PT package branch.'],
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

        return response()->json($this->loadMemberDetail($member->fresh()), 201);
    }

    /**
     * Update member's information
     * @param Request $request
     * @param User $member
     * @return JsonResponse
     */
    public function update(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'status' => [
                'nullable',
                Rule::in([
                    User::STATUS_ACTIVE,
                    User::STATUS_INACTIVE,
                    User::STATUS_SUSPENDED,
                ])
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

        $member->branches()->sync($data['branch_ids']);

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

        return response()->json(
            $this->loadMemberDetail($member->fresh())
        );
    }

    /**
     * Load detailed information for a member
     * @param User $member
     * @return User
     */
    private function loadMemberDetail(User $member): User
    {
        $member->load([
            'profile',
            'branches.ptProducts',
            'memberSubscriptions' => fn($query) => $query
                ->with([
                    'ratePlan:id,name,duration_days',
                    'branch:id,name',
                    'manager:id,name',
                    'commissionPayroll:id,period_start,period_end,status',
                ])
                ->orderByDesc('start_date'),
            'memberPtPackages' => fn($query) => $query
                ->with([
                    'branch',
                    'ptProduct',
                    'coach:id,name',
                    'createdBy:id,name',
                    'usages.coach:id,name',
                    'usages.recordedBy:id,name',
                ])
                ->orderByDesc('assigned_at'),
        ]);

        $member->setRelation(
            'memberSubscriptions',
            $member->memberSubscriptions
                ->map(fn(MemberSubscription $subscription) => $this->serializeMemberSubscription($subscription))
                ->values()
        );

        $member->setRelation('availableManagers', $this->availableManagersForMember($member));

        return $member->setRelation('availableCoaches', $this->availableCoachesForMember($member));
    }

    /**
     * Serialize a member subscription with commission display state
     * @param MemberSubscription $subscription
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
            'branch' => $subscription->branch ? [
                'id' => $subscription->branch->id,
                'name' => $subscription->branch->name,
            ] : null,
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
                'branch_name' => $subscription->branch?->name,
                'payroll_label' => $commissionPayrollLabel,
            ],
            'action_state' => [
                'is_locked' => $isCommissionLocked,
                'can_change_plan' => !$isCommissionLocked,
                'can_change_status' => !$isCommissionLocked
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
     * Build a consistent validation response for locked commission-backed memberships
     * @param MemberSubscription $subscription
     * @return JsonResponse
     */
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

    /**
     * Build a consistent validation response for unassignable membership commissions
     * @param MemberSubscription $subscription
     * @return JsonResponse
     */
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

    /**
     * Check if a manager is assignable to a membership commission
     * @param User $member
     * @param MemberSubscription $subscription
     * @param int $managerId
     * @return bool
     */
    private function managerIsAssignableToMembership(User $member, MemberSubscription $subscription, int $managerId): bool
    {
        $branchIds = $subscription->branch_id
            ? collect([$subscription->branch_id])
            : $member->branches()->pluck('branches.id');

        if ($branchIds->isEmpty()) {
            return false;
        }

        return User::role('manager')
            ->whereKey($managerId)
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->exists();
    }

    /**
     * Check if a coach is assignable to a specific branch
     * @param int $coachId
     * @param int $branchId
     * @return bool
     */
    private function coachIsAssignableToBranch(int $coachId, int $branchId): bool
    {
        return User::role('coach')
            ->whereKey($coachId)
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->where('branches.id', $branchId))
            ->exists();
    }

    /**
     * Get available coaches for a member
     * @param User $member
     * @return \Illuminate\Support\Collection
     */
    private function availableCoachesForMember(User $member)
    {
        $branchIds = $member->branches()->pluck('branches.id');

        if ($branchIds->isEmpty()) {
            return collect();
        }

        return User::role('coach')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->with(['branches:id,name'])
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.status']);
    }

    /**
     * Get available managers for a member
     * @param User $member
     * @return \Illuminate\Support\Collection
     */
    private function availableManagersForMember(User $member)
    {
        $currentMembership = $member->currentMembership();
        $branchIds = $currentMembership && $currentMembership->branch_id
            ? collect([$currentMembership->branch_id])
            : $member->branches()->pluck('branches.id');

        if ($branchIds->isEmpty()) {
            return collect();
        }

        $query = User::role('manager')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('branches', fn($query) => $query->whereIn('branches.id', $branchIds))
            ->with(['branches:id,name'])
            ->orderBy('name');

        if (auth()->user()->hasRole('manager') && !auth()->user()->hasAnyRole(['super admin', 'admin'])) {
            $query->whereKey(auth()->id());
        }

        return $query->get(['users.id', 'users.name', 'users.status']);
    }
}
