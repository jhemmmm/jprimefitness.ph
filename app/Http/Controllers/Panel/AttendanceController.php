<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     *
     * Index
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.attendance');
    }

    /**
     *
     * List
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $records = Attendance::with(['branch', 'user', 'recordedBy'])
            ->when($request->search, fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                    ->orWhereHas('user', fn($qqq) => $qqq->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('walkIn', fn($qqq) => $qqq->where('name', 'like', "%{$request->search}%"));
            }))
            ->when($request->type, fn($q, $t) => $q->where('attendee_type', $t))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('checked_in_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('checked_in_at', '<=', $d))
            ->when($request->branch, fn($q) => $q->where('branch_id', $request->branch))
            ->orderBy('checked_in_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $baseStatsQuery = Attendance::query()->when($request->branch, fn($q) => $q->where('branch_id', $request->branch));

        return response()->json([
            'records' => $records,
            'stats' => [
                'today' => (clone $baseStatsQuery)->whereDate('checked_in_at',  Carbon::today())->count(),
                'this_week' => (clone $baseStatsQuery)->where('checked_in_at', '>=', Carbon::now()->startOfWeek())->count(),
                'this_month' => (clone $baseStatsQuery)->where('checked_in_at', '>=', Carbon::now()->startOfMonth())->count(),
                'currently_in' => (clone $baseStatsQuery)->whereDate('checked_in_at',  Carbon::today())->whereNull('checked_out_at')->count(),
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
        $base = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'attendee_type' => ['required', Rule::in(['member', 'walk_in', 'employee'])],
            'checked_in_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $type = $base['attendee_type'];

        if ($type === 'walk_in') {
            $extra = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['nullable', 'string', 'max:100'],
            ]);
        } else {
            $extra = $request->validate([
                'user_id' => ['required', 'exists:users,id'],
            ]);

            $user = User::findOrFail($extra['user_id']);
            $extra['first_name'] = $user->first_name;
            $extra['last_name'] = $user->last_name;
        }

        $data = array_merge($base, $extra, [
            'checked_in_at' => $base['checked_in_at'] ?? now(),
            'recorded_by' => Auth::id(),
        ]);

        $attendance = Attendance::create($data);

        return response()->json($attendance->load(['branch', 'user', 'recordedBy']), 201);
    }

    /**
     * Update
     * @param Request $request
     * @param Attendance $attendance
     * @return JsonResponse
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'checked_in_at' => ['required', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'notes' => ['nullable', 'string'],
        ]);

        // For walk-ins allow editing the name
        if ($attendance->attendee_type === 'walk_in') {
            $nameData = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['nullable', 'string', 'max:100'],
            ]);
            $data = array_merge($data, $nameData);
        }

        $attendance->update($data);

        return response()->json($attendance->fresh()->load(['branch', 'user', 'recordedBy']));
    }

    /**
     * Checkout shortcut
     * @param Attendance $attendance
     * @return JsonResponse
     */
    public function checkout(Attendance $attendance): JsonResponse
    {
        if ($attendance->checked_out_at) {
            return response()->json(['message' => 'Already checked out.'], 422);
        }

        $attendance->update(['checked_out_at' => now()]);

        return response()->json($attendance->fresh()->load(['branch', 'user', 'recordedBy']));
    }

    /**
     * Destroy
     * @param Attendance $attendance
     * @return JsonResponse
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json(null, 204);
    }
}
