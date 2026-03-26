<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Ubdex
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        return view('panel.employees.index');
    }

    /**
     * Show
     * @param User $employee
     * @return \Illuminate\Contracts\View\View
     */
    public function show(User $employee)
    {
        return view('panel.employees.show', [
            'employee' => $employee->load('branches', 'roles'),
        ]);
    }

    /**
     * List
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $employees = User::role('employee')
            ->with('branches')
            ->when(!empty($request->search), fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when(!empty($request->role), fn($q) => $q->whereHas('roles', fn($qq) => $qq->where('id', $request->role)))
            ->when($request->branch, fn($q) => $q->whereHas('branches', fn($qq) => $qq->where('branches.id', $request->branch)), function ($q) {
                if (!auth()->user()->hasRole('super admin')) {
                    $q->whereHas('branches', fn($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('id')));
                }
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->get();

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = auth()->user();
        $allowedRoles = match (true) {
            $actor->isSuperAdmin() => [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_STAFF, User::ROLE_COACH],
            $actor->isAdminOrAbove() => [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_STAFF, User::ROLE_COACH],
            default => [User::ROLE_MANAGER, User::ROLE_STAFF, User::ROLE_COACH],
        };

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in($allowedRoles)],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
            'daily_rate' => 'required|numeric|min:0',
            'password' => 'required|string|min:8',
        ]);

        $data['password'] = Hash::make($data['password']);

        $branchIds = $data['branch_ids'] ?? ([]);

        // Ensure unique and valid assignment
        $branchIds = array_values(array_unique(array_filter($branchIds, fn($id) => ! is_null($id))));

        if (! $actor->isSuperAdmin()) {
            $authorizedBranches = $actor->getBranchIds();
            $branchIds = array_values(array_intersect($branchIds, $authorizedBranches));
        }

        $createData = $data;
        unset($createData['branch_ids']);

        $employee = User::create($createData);

        $employee->syncBranches($branchIds);

        return response()->json($employee->load('branches'), 201);
    }

    public function update(Request $request, User $employee): JsonResponse
    {
        $actor = auth()->user();
        $allowedRoles = match (true) {
            $actor->isSuperAdmin() => [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_STAFF, User::ROLE_COACH],
            $actor->isAdminOrAbove() => [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_STAFF, User::ROLE_COACH],
            default => [User::ROLE_MANAGER, User::ROLE_STAFF, User::ROLE_COACH],
        };

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in($allowedRoles)],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer|exists:branches,id',
            'daily_rate' => 'required|numeric|min:0',
            'password' => 'nullable|string|min:8',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $branchIds = $data['branch_ids'] ?? ([]);
        $branchIds = array_values(array_unique(array_filter($branchIds, fn($id) => ! is_null($id))));

        if (! $actor->isSuperAdmin()) {
            $authorizedBranches = $actor->getBranchIds();
            $branchIds = array_values(array_intersect($branchIds, $authorizedBranches));
        }

        $updateData = $data;
        unset($updateData['branch_ids']);

        $employee->update($updateData);

        $employee->syncBranches($branchIds);

        return response()->json($employee->fresh()->load('branches'));
    }

    public function destroy(User $employee): JsonResponse
    {
        abort_if($employee->id === auth()->id(), 403);

        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }

    public function attendance(Request $request, User $employee): JsonResponse
    {
        $query = Attendance::where('user_id', $employee->id)
            ->with('branch')
            ->orderByDesc('checked_in_at');

        if ($request->filled('date_from')) {
            $query->whereDate('checked_in_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('checked_in_at', '<=', $request->date_to);
        }

        $records = $query->paginate(15);

        $stats = [
            'total' => Attendance::where('user_id', $employee->id)->count(),
            'this_month' => Attendance::where('user_id', $employee->id)
                ->whereMonth('checked_in_at', now()->month)
                ->whereYear('checked_in_at', now()->year)
                ->count(),
            'currently_in' => Attendance::where('user_id', $employee->id)
                ->whereNull('checked_out_at')
                ->count(),
        ];

        return response()->json(compact('records', 'stats'));
    }
}
