<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditHistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        private AuditHistoryService $auditHistoryService,
    ) {}

    /**
     * Display the attendance index page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.attendance');
    }

    /**
     * Display a list of attendance records.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $records = Attendance::with(['user', 'recordedBy'])
            ->when($request->search, fn ($query) => $query->where(function ($inner) use ($request) {
                $inner->where('name', 'like', "%{$request->search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$request->search}%"));
            }))
            ->when($request->type, fn ($query, $type) => $query->where('attendee_type', $type))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('checked_in_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('checked_in_at', '<=', $date))
            ->orderByDesc('checked_in_at')
            ->paginate(20)
            ->withQueryString();

        $records->setCollection(
            $records->getCollection()->map(fn (Attendance $attendance) => $this->serializeAttendance($attendance))
        );

        $baseStatsQuery = Attendance::query();

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

    public function store(Request $request): JsonResponse
    {
        $base = $request->validate([
            'attendee_type' => ['required', Rule::in(['member', 'walk_in', 'employee'])],
            'checked_in_at' => ['nullable', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $extra = match ($base['attendee_type']) {
            'walk_in' => $request->validate(['name' => ['required', 'string', 'max:150']]),
            default => $this->validatedNamedUser($request),
        };

        $attendance = Attendance::create(array_merge($base, $extra, [
            'checked_in_at' => $base['checked_in_at'] ?? now(),
            'checked_out_at' => $base['checked_out_at'] ?? null,
            'recorded_by' => Auth::id(),
            'source' => Attendance::SOURCE_MANUAL,
            'source_device_serial' => null,
        ]));
        $attendance = $attendance->fresh(['user', 'recordedBy']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_ATTENDANCE,
            $attendance->id,
            'checked_in',
            $this->attendanceAuditSnapshot($attendance),
            [],
            auth()->id(),
            auth()->user()?->name,
            $attendance->checked_in_at ?? now(),
        );

        return response()->json($this->serializeAttendance($attendance), 201);
    }

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validate([
            'checked_in_at' => ['required', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($attendance->attendee_type === 'walk_in') {
            $data = array_merge($data, $request->validate(['name' => ['required', 'string', 'max:150']]));
        } elseif ($attendance->user) {
            $data['name'] = $attendance->user->name;
        }

        $attendance->update($data);
        $attendance = $attendance->fresh(['user', 'recordedBy']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_ATTENDANCE,
            $attendance->id,
            'updated',
            $this->attendanceAuditSnapshot($attendance),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializeAttendance($attendance));
    }

    public function checkout(Attendance $attendance): JsonResponse
    {
        if ($attendance->checked_out_at) {
            return response()->json(['message' => 'Already checked out.'], 422);
        }

        $attendance->update(['checked_out_at' => now()]);
        $attendance = $attendance->fresh(['user', 'recordedBy']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_ATTENDANCE,
            $attendance->id,
            'checked_out',
            $this->attendanceAuditSnapshot($attendance),
            [],
            auth()->id(),
            auth()->user()?->name,
            $attendance->checked_out_at ?? now(),
        );

        return response()->json($this->serializeAttendance($attendance));
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $snapshot = $this->attendanceAuditSnapshot($attendance->loadMissing(['user', 'recordedBy']));

        $attendance->delete();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_ATTENDANCE,
            $attendance->id,
            'deleted',
            $snapshot,
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(null, 204);
    }

    /**
     * @return array{name:string, user_id:int}
     */
    private function validatedNamedUser(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['user_id']);

        return [
            'user_id' => $user->id,
            'name' => $user->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttendance(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'attendee_type' => $attendance->attendee_type,
            'user_id' => $attendance->user_id,
            'name' => $attendance->name ?: $attendance->user?->name,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'notes' => $attendance->notes,
            'source' => $attendance->source,
            'source_device_serial' => $attendance->source_device_serial,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceAuditSnapshot(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'name' => $attendance->name ?: $attendance->user?->name,
            'attendee_type' => $attendance->attendee_type,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'source' => $attendance->source,
            'source_device_serial' => $attendance->source_device_serial,
        ];
    }
}
