<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MembersController extends Controller
{
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
     * List
     */
    public function list(Request $request): JsonResponse
    {
        // Get members
        $members = User::role('member')
            ->with([
                'profile',
                'branches',
                'memberSubscriptions.ratePlan',
                'memberPtPackages' => fn ($query) => $query
                    ->with('ptProduct:id,name')
                    ->orderByDesc('assigned_at'),
            ])
            ->when(! empty($request->search), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->branch, fn ($q, $b) => $q->whereHas('branches', fn ($qq) => $qq->where('branches.id', $b)), function ($q) {
                if (! auth()->user()->hasRole('super admin')) {
                    $q->whereHas('branches', fn ($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('branches.id')));
                }
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan, fn ($q, $p) => $q->whereHas('memberSubscriptions', fn ($rq) => $rq->where('rate_plan_id', $p)))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Get stats
        $statsQuery = User::role('member')
            ->when($request->branch, fn ($q, $b) => $q->whereHas('branches', fn ($qq) => $qq->where('branches.id', $b)), function ($q) {
                if (! auth()->user()->hasRole('super admin')) {
                    $q->whereHas('branches', fn ($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('branches.id')));
                }
            });

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
     * Store
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
            'status' => ['required', Rule::in([
                User::STATUS_ACTIVE,
                User::STATUS_INACTIVE,
                User::STATUS_SUSPENDED,
            ])],
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
     * Summary of show
     */
    public function show(User $member): View
    {
        abort_unless($member->hasRole('member'), 404);

        return view('panel.members.show', [
            'member' => $this->loadMemberDetail($member),
        ]);
    }

    public function attendance(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);

        $records = Attendance::where('user_id', $member->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER)
            ->with('branch')
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('checked_in_at', '<=', $request->date_to))
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

    public function updateMembership(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'rate_plan_id' => ['required', 'exists:rate_plans,id'],
            'start_date' => ['required', 'date'],
        ]);

        $member->changeMembershipPlan((int) $data['rate_plan_id'], $data['start_date']);

        return response()->json(
            $this->loadMemberDetail($member->fresh())
        );
    }

    public function updateMembershipStatus(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                MemberSubscription::STATUS_ACTIVE,
                MemberSubscription::STATUS_PAUSED,
                MemberSubscription::STATUS_CANCELLED,
            ])],
        ]);

        if (! $member->currentMembership()) {
            return response()->json(['message' => 'No current membership found.'], 422);
        }

        $member->updateCurrentMembershipStatus($data['status']);

        return response()->json(
            $this->loadMemberDetail($member->fresh())
        );
    }

    public function storePtPackage(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager']), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'pt_product_id' => ['required', 'integer', 'exists:pt_products,id'],
            'assigned_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $member->branches()->whereKey($data['branch_id'])->exists()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'branch_id' => ['The selected branch is not assigned to this member.'],
                ],
            ], 422);
        }

        $ptProduct = PTProduct::findOrFail($data['pt_product_id']);

        if (! $ptProduct->branches()->whereKey($data['branch_id'])->wherePivot('is_active', true)->exists()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'pt_product_id' => ['The selected PT product is not available for the chosen branch.'],
                ],
            ], 422);
        }

        $member->memberPtPackages()->create([
            'branch_id' => $data['branch_id'],
            'pt_product_id' => $ptProduct->id,
            'total_sessions' => $ptProduct->session_count,
            'remaining_sessions' => $ptProduct->session_count,
            'assigned_at' => $data['assigned_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return response()->json($this->loadMemberDetail($member->fresh()), 201);
    }

    public function storePtSessionUsage(Request $request, User $member): JsonResponse
    {
        abort_unless($member->hasRole('member'), 404);
        abort_unless(auth()->user()->hasAnyRole(['super admin', 'admin', 'manager', 'staff']), 403);

        $data = $request->validate([
            'member_pt_package_id' => ['required', 'integer'],
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

        $package->consumeSessions(
            (int) $data['sessions_used'],
            $data['used_at'],
            auth()->id(),
            $data['confirmed_by'] ?? null,
            $data['notes'] ?? null
        );

        return response()->json($this->loadMemberDetail($member->fresh()), 201);
    }

    /**
     * Summary of update
     */
    public function update(Request $request, User $member): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::in([
                User::STATUS_ACTIVE,
                User::STATUS_INACTIVE,
                User::STATUS_SUSPENDED,
            ])],
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

    private function loadMemberDetail(User $member): User
    {
        return $member->load([
            'profile',
            'branches.ptProducts',
            'memberSubscriptions' => fn ($query) => $query->with('ratePlan')->orderByDesc('start_date'),
            'memberPtPackages' => fn ($query) => $query
                ->with([
                    'branch',
                    'ptProduct',
                    'createdBy:id,name',
                    'usages.recordedBy:id,name',
                ])
                ->orderByDesc('assigned_at'),
        ]);
    }
}
