<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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
     * Index
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.attendance');
    }

    /**
     * List
     */
    public function list(Request $request): JsonResponse
    {
        $records = Attendance::with(['branch', 'user', 'walkIn', 'recordedBy'])
            ->when($request->search, fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                    ->orWhereHas('user', fn ($qqq) => $qqq->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('walkIn', fn ($qqq) => $qqq->where('name', 'like', "%{$request->search}%"));
            }))
            ->when($request->type, fn ($q, $t) => $q->where('attendee_type', $t))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('checked_in_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('checked_in_at', '<=', $d))
            ->when(! auth()->user()->hasRole('super admin'), fn ($q) => $q->whereIn('branch_id', auth()->user()->branches()->pluck('branches.id')))
            ->when($request->branch, fn ($q) => $q->where('branch_id', $request->branch))
            ->orderBy('checked_in_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $records->setCollection(
            $records->getCollection()->map(function (Attendance $attendance) {
                return [
                    'id' => $attendance->id,
                    'attendee_type' => $attendance->attendee_type,
                    'user_id' => $attendance->user_id,
                    'branch_id' => $attendance->branch_id,
                    'name' => $attendance->name ?: $attendance->user?->name ?: $attendance->walkIn?->name,
                    'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                    'checked_out_at' => $attendance->checked_out_at?->toISOString(),
                    'notes' => $attendance->notes,
                    'branch' => $attendance->branch ? [
                        'id' => $attendance->branch->id,
                        'name' => $attendance->branch->name,
                    ] : null,
                ];
            })
        );

        $baseStatsQuery = Attendance::query()
            ->when(! auth()->user()->hasRole('super admin'), fn ($q) => $q->whereIn('branch_id', auth()->user()->branches()->pluck('branches.id')))
            ->when($request->branch, fn ($q) => $q->where('branch_id', $request->branch));

        return response()->json([
            'records' => $records,
            'stats' => [
                'today' => (clone $baseStatsQuery)->whereDate('checked_in_at', Carbon::today())->count(),
                'this_week' => (clone $baseStatsQuery)->where('checked_in_at', '>=', Carbon::now()->startOfWeek())->count(),
                'this_month' => (clone $baseStatsQuery)->where('checked_in_at', '>=', Carbon::now()->startOfMonth())->count(),
                'currently_in' => (clone $baseStatsQuery)->whereDate('checked_in_at', Carbon::today())->whereNull('checked_out_at')->count(),
            ],
        ]);
    }

    /**
     * Store
     */
    public function store(Request $request): JsonResponse
    {
        $base = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'attendee_type' => ['required', Rule::in(['member', 'walk_in', 'employee'])],
            'checked_in_at' => ['nullable', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $type = $base['attendee_type'];

        if ($type === 'walk_in') {
            $extra = $request->validate([
                'name' => ['required', 'string', 'max:150'],
            ]);
        } else {
            $extra = $request->validate([
                'user_id' => ['required', 'exists:users,id'],
            ]);

            $user = User::findOrFail($extra['user_id']);
            $extra['name'] = $user->name;
        }

        $data = array_merge($base, $extra, [
            'checked_in_at' => $base['checked_in_at'] ?? now(),
            'checked_out_at' => $base['checked_out_at'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $attendance = Attendance::create($data);

        $attendance->load(['branch', 'user', 'walkIn', 'recordedBy']);

        return response()->json([
            'id' => $attendance->id,
            'attendee_type' => $attendance->attendee_type,
            'user_id' => $attendance->user_id,
            'branch_id' => $attendance->branch_id,
            'name' => $attendance->name ?: $attendance->user?->name ?: $attendance->walkIn?->name,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'notes' => $attendance->notes,
            'branch' => $attendance->branch ? [
                'id' => $attendance->branch->id,
                'name' => $attendance->branch->name,
            ] : null,
        ], 201);
    }

    /**
     * Update
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
                'name' => ['required', 'string', 'max:150'],
            ]);
            $data = array_merge($data, $nameData);
        } elseif ($attendance->user) {
            $data['name'] = $attendance->user->name;
        }

        $attendance->update($data);

        $attendance = $attendance->fresh()->load(['branch', 'user', 'walkIn', 'recordedBy']);

        return response()->json([
            'id' => $attendance->id,
            'attendee_type' => $attendance->attendee_type,
            'user_id' => $attendance->user_id,
            'branch_id' => $attendance->branch_id,
            'name' => $attendance->name ?: $attendance->user?->name ?: $attendance->walkIn?->name,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'notes' => $attendance->notes,
            'branch' => $attendance->branch ? [
                'id' => $attendance->branch->id,
                'name' => $attendance->branch->name,
            ] : null,
        ]);
    }

    /**
     * Checkout shortcut
     */
    public function checkout(Attendance $attendance): JsonResponse
    {
        if ($attendance->checked_out_at) {
            return response()->json(['message' => 'Already checked out.'], 422);
        }

        $attendance->update(['checked_out_at' => now()]);

        $attendance = $attendance->fresh()->load(['branch', 'user', 'walkIn', 'recordedBy']);

        return response()->json([
            'id' => $attendance->id,
            'attendee_type' => $attendance->attendee_type,
            'user_id' => $attendance->user_id,
            'branch_id' => $attendance->branch_id,
            'name' => $attendance->name ?: $attendance->user?->name ?: $attendance->walkIn?->name,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'notes' => $attendance->notes,
            'branch' => $attendance->branch ? [
                'id' => $attendance->branch->id,
                'name' => $attendance->branch->name,
            ] : null,
        ]);
    }

    /**
     * Destroy
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json(null, 204);
    }
}
