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
        $employees = User::role(['employee', 'coach', 'manager', 'admin', 'staff'])
            ->with('branches', 'roles')
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

    /**
     * Store
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => ['integer', Rule::in(auth()->user()->allowedEmployeesRoles())],
            'daily_rate' => 'required|numeric|min:0',
            'password' => 'required|string|min:8',
        ]);

        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'daily_rate' => $data['daily_rate'],
            'password' => Hash::make($data['password']),
        ]);

        $employee->branches()->attach($data['branch_ids']);
        $employee->roles()->attach($data['role_ids']);

        return response()->json($employee->load('branches', 'roles'), 201);
    }

    /**
     * Update
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function update(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => ['integer', Rule::in(auth()->user()->allowedEmployeesRoles())],
            'daily_rate' => 'required|numeric|min:0',
            'password' => 'nullable|string|min:8',
        ]);

        $employee->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'daily_rate' => $data['daily_rate'],
            'password' => isset($data['password']) ? Hash::make($data['password']) : $employee->password,
        ]);

        $employee->branches()->sync($data['branch_ids']);

        $employee->roles()->sync($data['role_ids']);

        return response()->json($employee->fresh()->load('branches', 'roles'));
    }

    /**
     * Destroy
     * @param User $employee
     * @return JsonResponse
     */
    public function destroy(User $employee): JsonResponse
    {
        abort_if($employee->id === auth()->id(), 403);

        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * Attendace
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function attendance(Request $request, User $employee): JsonResponse
    {
        $record = Attendance::where('user_id', $employee->id)
            ->with('branch')
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->paginate(15);


        $statsQuery = Attendance::where('user_id', $employee->id);

        return response()->json([
            'records' => $record,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'this_month' => (clone $statsQuery)->whereMonth('checked_in_at', now()->month)->whereYear('checked_in_at', now()->year)->count(),
                'currently_in' => (clone $statsQuery)->whereNull('checked_out_at')->count(),
            ],
        ]);
    }
}
