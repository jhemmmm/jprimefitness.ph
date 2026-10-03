<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\ReportExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportsController extends Controller
{
    /**
     * Display the attendance reports page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.reports.attendance');
    }

    /**
     * Return attendance report data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json($this->reportPayload($request));
    }

    /**
     * Download the attendance report as an Excel workbook.
     */
    public function export(Request $request): StreamedResponse
    {
        $report = $this->reportPayload($request, false);

        return (new ReportExport(
            'Attendance Report',
            [
                'Period' => ReportExport::period($report['filters']['date_from'], $report['filters']['date_to']),
                'Attendee Type' => $report['filters']['type_label'] ?: 'All Types',
            ],
            [
                'Total Check-ins' => $report['summary']['total_check_ins'],
                'Unique Attendees' => $report['summary']['unique_attendees'],
                'Checked Out' => $report['summary']['checked_out_count'],
                'Currently In' => $report['summary']['currently_in_count'],
                'Average Visit Minutes' => $report['summary']['average_visit_minutes'],
            ],
            [
                ['Attendance by Type', ['Type', 'Check-ins', 'Unique Attendees', 'Currently In'], collect($report['type_breakdown'])->map(fn ($row) => [$row['label'], $row['check_in_count'], $row['unique_attendees'], $row['currently_in_count']])],
                ['Daily Trend', ['Date', 'Check-ins', 'Unique Attendees'], collect($report['daily_trend'])->map(fn ($row) => [$row['attendance_date'], $row['check_in_count'], $row['unique_attendees']])],
                ['Busiest Hours', ['Hour', 'Check-ins'], collect($report['busiest_hours'])->map(fn ($row) => [$row['label'], $row['check_in_count']])],
                ['Attendance Records', ['Name', 'Type', 'Checked In', 'Checked Out', 'Duration Minutes', 'Status'], collect($report['records'])->map(fn ($row) => [
                    $row['name'],
                    $row['attendee_type_label'],
                    $row['checked_in_at'],
                    $row['checked_out_at'],
                    $row['duration_minutes'],
                    $row['is_currently_in'] ? 'Currently In' : 'Checked Out',
                ])],
            ],
        ))->download();
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(Request $request, bool $paginateRecords = true): array
    {
        $data = $request->validate([
            'type' => ['nullable', 'array'],
            'type.*' => [
                Rule::in([
                    Attendance::TYPE_MEMBER,
                    Attendance::TYPE_WALK_IN,
                    Attendance::TYPE_EMPLOYEE,
                ]),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $types = array_values(array_filter($data['type'] ?? [], fn ($value) => $value !== null && $value !== ''));
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 25);

        $attendanceQuery = Attendance::query()
            ->when(! empty($types), fn ($query) => $query->whereIn('attendee_type', $types))
            ->when($data['date_from'] ?? null, fn ($query) => $query->whereDate('checked_in_at', '>=', $data['date_from']))
            ->when($data['date_to'] ?? null, fn ($query) => $query->whereDate('checked_in_at', '<=', $data['date_to']));

        $attendanceRecords = (clone $attendanceQuery)
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'attendee_type',
                'user_id',
                'name',
                'checked_in_at',
                'checked_out_at',
            ]);

        return [
            'filters' => [
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'type' => $types,
                'type_label' => ! empty($types)
                    ? collect($types)->map(fn (string $type) => $this->typeLabel($type))->implode(', ')
                    : null,
            ],
            'summary' => $this->summary($attendanceRecords),
            'type_breakdown' => $this->typeBreakdown($attendanceRecords),
            'daily_trend' => $this->dailyTrend($attendanceRecords),
            'busiest_hours' => $this->busiestHours($attendanceRecords),
            'records' => $paginateRecords
                ? $this->paginatedRecords((clone $attendanceQuery)->orderByDesc('checked_in_at')->orderByDesc('id'), $page, $perPage)
                : $this->records($attendanceRecords),
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRecords
     * @return array<string, float|int>
     */
    private function summary(Collection $attendanceRecords): array
    {
        $checkedOutRecords = $attendanceRecords->filter(fn (Attendance $attendance) => $attendance->checked_out_at !== null);
        $averageVisitMinutes = $checkedOutRecords->count() > 0
            ? round((float) $checkedOutRecords->avg(fn (Attendance $attendance) => $this->durationMinutes($attendance) ?? 0), 2)
            : 0;

        return [
            'total_check_ins' => $attendanceRecords->count(),
            'unique_attendees' => $this->uniqueAttendeeCount($attendanceRecords),
            'checked_out_count' => $checkedOutRecords->count(),
            'currently_in_count' => $attendanceRecords->whereNull('checked_out_at')->count(),
            'average_visit_minutes' => $averageVisitMinutes,
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRecords
     * @return array<int, array<string, mixed>>
     */
    private function typeBreakdown(Collection $attendanceRecords): array
    {
        return collect([
            Attendance::TYPE_MEMBER,
            Attendance::TYPE_WALK_IN,
            Attendance::TYPE_EMPLOYEE,
        ])->map(function (string $type) use ($attendanceRecords): array {
            $records = $attendanceRecords->where('attendee_type', $type)->values();

            return [
                'type' => $type,
                'label' => $this->typeLabel($type),
                'check_in_count' => $records->count(),
                'unique_attendees' => $this->uniqueAttendeeCount($records),
                'currently_in_count' => $records->whereNull('checked_out_at')->count(),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRecords
     * @return array<int, array<string, mixed>>
     */
    private function dailyTrend(Collection $attendanceRecords): array
    {
        return $attendanceRecords
            ->groupBy(fn (Attendance $attendance) => $attendance->checked_in_at?->toDateString() ?? 'unknown')
            ->map(fn (Collection $records, string $date) => [
                'attendance_date' => $date,
                'check_in_count' => $records->count(),
                'unique_attendees' => $this->uniqueAttendeeCount($records),
            ])
            ->sortBy('attendance_date')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRecords
     * @return array<int, array<string, mixed>>
     */
    private function busiestHours(Collection $attendanceRecords): array
    {
        return $attendanceRecords
            ->groupBy(fn (Attendance $attendance) => $attendance->checked_in_at?->format('H:00') ?? 'Unknown')
            ->map(fn (Collection $records, string $hourSlot) => [
                'hour_slot' => $hourSlot,
                'label' => $hourSlot === 'Unknown' ? 'Unknown' : "{$hourSlot} - ".substr($hourSlot, 0, 2).':59',
                'check_in_count' => $records->count(),
            ])
            ->sort(function (array $left, array $right): int {
                if ($left['check_in_count'] === $right['check_in_count']) {
                    return strcmp($left['hour_slot'], $right['hour_slot']);
                }

                return $right['check_in_count'] <=> $left['check_in_count'];
            })
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRecords
     * @return array<int, array<string, mixed>>
     */
    private function records(Collection $attendanceRecords): array
    {
        return $attendanceRecords
            ->map(fn (Attendance $attendance) => $this->recordPayload($attendance))
            ->values()
            ->all();
    }

    private function paginatedRecords(Builder $query, int $page, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $records = $query->paginate(
            $perPage,
            ['id', 'attendee_type', 'user_id', 'name', 'checked_in_at', 'checked_out_at'],
            'page',
            $page
        )->withQueryString();

        $records->setCollection(
            $records->getCollection()->map(fn (Attendance $attendance) => $this->recordPayload($attendance))
        );

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'name' => $attendance->name,
            'attendee_type' => $attendance->attendee_type,
            'attendee_type_label' => $this->typeLabel($attendance->attendee_type),
            'checked_in_at' => $attendance->checked_in_at,
            'checked_out_at' => $attendance->checked_out_at,
            'duration_minutes' => $this->durationMinutes($attendance),
            'is_currently_in' => $attendance->checked_out_at === null,
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRecords
     */
    private function uniqueAttendeeCount(Collection $attendanceRecords): int
    {
        return $attendanceRecords
            ->map(fn (Attendance $attendance) => $this->attendeeKey($attendance))
            ->unique()
            ->count();
    }

    /**
     * Return the unique attendee key for an attendance record.
     *
     * @return string
     */
    private function attendeeKey(Attendance $attendance): string
    {
        if ($attendance->user_id !== null) {
            return $attendance->attendee_type.':user:'.$attendance->user_id;
        }

        $normalizedName = mb_strtolower(trim((string) $attendance->name));

        return $attendance->attendee_type.':name:'.$normalizedName;
    }

    /**
     * Return the attendance duration in minutes.
     *
     * @return ?int
     */
    private function durationMinutes(Attendance $attendance): ?int
    {
        return $attendance->workedMinutes();
    }

    /**
     * Return the display label for an attendance type.
     *
     * @return string
     */
    private function typeLabel(string $type): string
    {
        return match ($type) {
            Attendance::TYPE_MEMBER => 'Member',
            Attendance::TYPE_WALK_IN => 'Walk-in',
            Attendance::TYPE_EMPLOYEE => 'Employee',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }
}
