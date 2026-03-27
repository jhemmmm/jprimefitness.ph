<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MemberSubscription;
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
            ->with(['profile', 'branches', 'memberSubscriptions.ratePlan'])
            ->when(! empty($request->search), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->branch, fn ($q, $b) => $q->whereHas('branches', fn ($qq) => $qq->where('branches.id', $b)), function ($q) {
                if (! auth()->user()->hasRole('super admin')) {
                    $q->whereHas('branches', fn ($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('id')));
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
                    $q->whereHas('branches', fn ($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('id')));
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
            $user->fresh()->load(['profile', 'branches', 'memberSubscriptions.ratePlan']),
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
            'member' => $member->load([
                'profile',
                'branches',
                'memberSubscriptions' => fn ($query) => $query->with('ratePlan')->orderByDesc('start_date'),
            ]),
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
            $member->fresh()->load([
                'profile',
                'branches',
                'memberSubscriptions' => fn ($query) => $query->with('ratePlan')->orderByDesc('start_date'),
            ])
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
            $member->fresh()->load([
                'profile',
                'branches',
                'memberSubscriptions' => fn ($query) => $query->with('ratePlan')->orderByDesc('start_date'),
            ])
        );
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
            $member->fresh()->load(['profile', 'branches', 'memberSubscriptions.ratePlan'])
        );
    }
}
