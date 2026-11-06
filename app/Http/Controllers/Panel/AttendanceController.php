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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
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
        $types = array_values(array_filter((array) $request->input('type', []), fn ($value) => $value !== null && $value !== ''));

        $records = Attendance::with(['user', 'recordedBy'])
            ->when($request->search, fn ($query) => $query->where(function ($inner) use ($request) {
                $inner->where('name', 'like', "%{$request->search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$request->search}%"));
            }))
            ->when(! empty($types), fn ($query) => $query->whereIn('attendee_type', $types))
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

    /**
     * Create an attendance record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $base = $request->validate([
            'attendee_type' => ['required', Rule::in(['member', 'walk_in', 'employee'])],
            'checked_in_at' => ['nullable', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->authorizeAttendanceManagement($base['attendee_type']);

        $extra = match ($base['attendee_type']) {
            'walk_in' => $this->validatedWalkIn($request, 'required'),
            default => $this->validatedNamedUser($request, $base['attendee_type']),
        };

        $attendance = Attendance::create(array_merge($base, $extra, [
            'checked_in_at' => $base['checked_in_at'] ?? now(),
            'checked_out_at' => $base['checked_out_at'] ?? null,
            'recorded_by' => Auth::id(),
            'source' => Attendance::SOURCE_MANUAL,
            'source_device_serial' => null,
        ]));
        $attendance = $attendance->fresh(['user', 'recordedBy']);

        return response()->json($this->serializeAttendance($attendance), 201);
    }

    /**
     * Update an attendance record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorizeAttendanceManagement($attendance->attendee_type);

        $data = $request->validate([
            'checked_in_at' => ['required', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($attendance->attendee_type === 'walk_in') {
            // Older walk-ins were saved without contact details, so editing them must not demand any.
            $data = array_merge($data, $this->validatedWalkIn($request, 'nullable'));
        } elseif ($attendance->user) {
            $data['name'] = $attendance->user->name;
        }

        $attendance->update($data);
        $attendance = $attendance->fresh(['user', 'recordedBy']);

        return response()->json($this->serializeAttendance($attendance));
    }

    /**
     * Check out an attendance record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Attendance $attendance): JsonResponse
    {
        $this->authorizeAttendanceManagement($attendance->attendee_type);

        if ($attendance->checked_out_at) {
            return response()->json(['message' => 'Already checked out.'], 422);
        }

        $attendance->update(['checked_out_at' => now()]);
        $attendance = $attendance->fresh(['user', 'recordedBy']);

        return response()->json($this->serializeAttendance($attendance));
    }

    /**
     * Delete an attendance record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->authorizeAttendanceManagement($attendance->attendee_type);

        $attendance->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{name:string, user_id:int}
     */
    private function validatedNamedUser(Request $request, string $attendeeType): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['user_id']);

        $matchesAttendeeType = $attendeeType === Attendance::TYPE_MEMBER
            ? $user->hasRole('member')
            : ! $user->hasAnyRole(User::NON_EMPLOYEE_ROLES);

        if (! $matchesAttendeeType) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user does not match the attendance type.'],
            ]);
        }

        return [
            'user_id' => $user->id,
            'name' => $user->name,
        ];
    }

    /**
     * @return array{name:string, phone:?string, emergency_contact_name:?string, emergency_contact_phone:?string}
     */
    private function validatedWalkIn(Request $request, string $contactRule): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => [$contactRule, 'string', 'max:50'],
            'emergency_contact_name' => [$contactRule, 'string', 'max:150'],
            'emergency_contact_phone' => [$contactRule, 'string', 'max:50'],
        ]);
    }

    /**
     * Ensure the current user can manage attendance for the given attendee type.
     *
     * @return void
     */
    private function authorizeAttendanceManagement(string $attendeeType): void
    {
        $permission = match ($attendeeType) {
            Attendance::TYPE_MEMBER, Attendance::TYPE_WALK_IN => 'manage member attendance',
            Attendance::TYPE_EMPLOYEE => 'manage employee attendance',
            default => null,
        };

        abort_unless($permission !== null && auth()->user()->can($permission), 403);
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
            'phone' => $attendance->phone,
            'emergency_contact_name' => $attendance->emergency_contact_name,
            'emergency_contact_phone' => $attendance->emergency_contact_phone,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'notes' => $attendance->notes,
            'source' => $attendance->source,
            'source_device_serial' => $attendance->source_device_serial,
        ];
    }

}
