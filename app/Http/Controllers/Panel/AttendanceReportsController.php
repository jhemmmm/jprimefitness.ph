<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\BusinessProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportsController extends Controller
{
    public function index(): View
    {
        return view('panel.reports.attendance');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->reportPayload($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->reportPayload($request);
        $dateSuffix = now()->format('Ymd_His');
        $fileName = "attendance-report-{$dateSuffix}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Attendance Reports']);
            fputcsv($handle, ['Location', $report['scope']['location']['name'] ?? '-']);
            fputcsv($handle, ['Date From', $report['filters']['date_from'] ?: '-']);
            fputcsv($handle, ['Date To', $report['filters']['date_to'] ?: '-']);
            fputcsv($handle, ['Attendee Type', $report['filters']['type_label'] ?: 'All Types']);
            fputcsv($handle, []);

            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Check-ins', $report['summary']['total_check_ins']]);
            fputcsv($handle, ['Unique Attendees', $report['summary']['unique_attendees']]);
            fputcsv($handle, ['Checked Out', $report['summary']['checked_out_count']]);
            fputcsv($handle, ['Currently In', $report['summary']['currently_in_count']]);
            fputcsv($handle, ['Average Visit Minutes', $report['summary']['average_visit_minutes']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Attendance by Type']);
            fputcsv($handle, ['Type', 'Check-ins', 'Unique Attendees', 'Currently In']);
            foreach ($report['type_breakdown'] as $row) {
                fputcsv($handle, [$row['label'], $row['check_in_count'], $row['unique_attendees'], $row['currently_in_count']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Location Totals']);
            fputcsv($handle, ['Location', 'Check-ins', 'Unique Attendees', 'Currently In']);
            foreach ($report['location_breakdown'] as $row) {
                fputcsv($handle, [$row['location_name'], $row['check_in_count'], $row['unique_attendees'], $row['currently_in_count']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Daily Trend']);
            fputcsv($handle, ['Date', 'Check-ins', 'Unique Attendees']);
            foreach ($report['daily_trend'] as $row) {
                fputcsv($handle, [$row['attendance_date'], $row['check_in_count'], $row['unique_attendees']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Busiest Hours']);
            fputcsv($handle, ['Hour', 'Check-ins']);
            foreach ($report['busiest_hours'] as $row) {
                fputcsv($handle, [$row['label'], $row['check_in_count']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Recent Attendance Records']);
            fputcsv($handle, ['Name', 'Type', 'Location', 'Checked In', 'Checked Out', 'Duration Minutes', 'Status']);
            foreach ($report['recent_records'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['attendee_type_label'],
                    $row['location_name'],
                    $row['checked_in_at'],
                    $row['checked_out_at'],
                    $row['duration_minutes'],
                    $row['is_currently_in'] ? 'Currently In' : 'Checked Out',
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(Request $request): array
    {
        $data = $request->validate([
            'type' => [
                'nullable',
                Rule::in([
                    Attendance::TYPE_MEMBER,
                    Attendance::TYPE_WALK_IN,
                    Attendance::TYPE_EMPLOYEE,
                ]),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $location = BusinessProfile::current()->locationSummary();

        $attendanceRecords = Attendance::query()
            ->when($data['type'] ?? null, fn ($query) => $query->where('attendee_type', $data['type']))
            ->when($data['date_from'] ?? null, fn ($query) => $query->whereDate('checked_in_at', '>=', $data['date_from']))
            ->when($data['date_to'] ?? null, fn ($query) => $query->whereDate('checked_in_at', '<=', $data['date_to']))
            ->orderByDesc('checked_in_at')
            ->get([
                'id',
                'attendee_type',
                'user_id',
                'name',
                'checked_in_at',
                'checked_out_at',
            ]);

        return [
            'scope' => [
                'location' => [
                    'id' => $location['id'],
                    'name' => $location['name'],
                ],
            ],
            'filters' => [
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'type' => $data['type'] ?? null,
                'type_label' => ($data['type'] ?? null) ? $this->typeLabel($data['type']) : null,
            ],
            'summary' => $this->summary($attendanceRecords),
            'type_breakdown' => $this->typeBreakdown($attendanceRecords),
            'location_breakdown' => [[
                'location_id' => $location['id'],
                'location_name' => $location['name'],
                'check_in_count' => $attendanceRecords->count(),
                'unique_attendees' => $this->uniqueAttendeeCount($attendanceRecords),
                'currently_in_count' => $attendanceRecords->whereNull('checked_out_at')->count(),
            ]],
            'daily_trend' => $this->dailyTrend($attendanceRecords),
            'busiest_hours' => $this->busiestHours($attendanceRecords),
            'recent_records' => $this->recentRecords($attendanceRecords, $location['name']),
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
    private function recentRecords(Collection $attendanceRecords, string $locationName): array
    {
        return $attendanceRecords
            ->take(10)
            ->map(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'name' => $attendance->name,
                'attendee_type' => $attendance->attendee_type,
                'attendee_type_label' => $this->typeLabel($attendance->attendee_type),
                'location_name' => $locationName,
                'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                'checked_out_at' => $attendance->checked_out_at?->toISOString(),
                'duration_minutes' => $this->durationMinutes($attendance),
                'is_currently_in' => $attendance->checked_out_at === null,
            ])
            ->values()
            ->all();
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

    private function attendeeKey(Attendance $attendance): string
    {
        if ($attendance->user_id !== null) {
            return $attendance->attendee_type.':user:'.$attendance->user_id;
        }

        $normalizedName = mb_strtolower(trim((string) $attendance->name));

        return $attendance->attendee_type.':name:'.$normalizedName;
    }

    private function durationMinutes(Attendance $attendance): ?int
    {
        if (! $attendance->checked_in_at || ! $attendance->checked_out_at) {
            return null;
        }

        return max(0, $attendance->checked_in_at->diffInMinutes($attendance->checked_out_at));
    }

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
