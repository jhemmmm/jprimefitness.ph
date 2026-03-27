<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
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
    * @param Request $request
    * @return JsonResponse
    */
   public function list(Request $request): JsonResponse
   {
      // Get members
      $members = User::role('member')
         ->with(['profile', 'branches', 'ratePlans'])
         ->when(!empty($request->search), fn($q) => $q->where(function ($qq) use ($request) {
            $qq->where('name', 'like', "%{$request->search}%")
               ->orWhere('email', 'like', "%{$request->search}%");
         }))
         ->when($request->branch, fn($q, $b) => $q->whereHas('branches', fn($qq) => $qq->where('branches.id', $b)), function ($q) {
            if (!auth()->user()->hasRole('super admin')) {
               $q->whereHas('branches', fn($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('id')));
            }
         })
         ->when($request->status, fn($q, $s) => $q->where('status', $s))
         ->when($request->plan, fn($q, $p) => $q->whereHas('ratePlans', fn($rq) => $rq->where('rate_plans.id', $p)))
         ->orderBy('created_at', 'desc')
         ->paginate(15)
         ->withQueryString();

      // Get stats
      $statsQuery = User::role('member')
         ->when($request->branch, fn($q, $b) => $q->whereHas('branches', fn($qq) => $qq->where('branches.id', $b)), function ($q) {
            if (!auth()->user()->hasRole('super admin')) {
               $q->whereHas('branches', fn($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('id')));
            }
         });

      return response()->json([
         'members' => $members,
         'stats' =>  [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', User::STATUS_ACTIVE)->count(),
            'inactive' => (clone $statsQuery)->where('status', User::STATUS_INACTIVE)->count(),
            'suspended' => (clone $statsQuery)->where('status', User::STATUS_SUSPENDED)->count(),
         ],
      ]);
   }

   /**
    * Store
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
         $user->fresh()->load(['profile', 'branches', 'ratePlans']),
         201
      );
   }

   /**
    * Summary of show
    * @param User $member
    * @return JsonResponse
    */
   public function show(User $member): JsonResponse
   {
      return response()->json($member->load(['profile', 'branches', 'ratePlans']));
   }

   /**
    * Summary of update
    * @param Request $request
    * @param User $member
    * @return JsonResponse
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
         $member->fresh()->load(['profile', 'branches', 'ratePlans'])
      );
   }
}
