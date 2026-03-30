<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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

    /**
     * data
     */
    public function data(Request $request): JsonResponse
    {
        // Get report data
        $report = $this->reportPayload($request);

        // Return as JSON
        return response()->json($report);
    }

    /**
     * export
     */
    public function export(Request $request): StreamedResponse
    {
        // Get report data
        $report = $this->reportPayload($request);
        // Generate filename with timestamp
        $dateSuffix = now()->format('Ymd_His');
        // Sanitize branch name for filename if present
        $fileName = "attendance-report-{$dateSuffix}.csv";

        // Stream CSV download
        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Attendance Reports']);
            fputcsv($handle, ['Branch', $report['scope']['branch']['name'] ?? 'All Accessible Branches']);
            fputcsv($handle, ['Date From', $report['filters']['date_from'] ?: '—']);
            fputcsv($handle, ['Date To', $report['filters']['date_to'] ?: '—']);
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

            fputcsv($handle, ['Branch Breakdown']);
            fputcsv($handle, ['Branch', 'Check-ins', 'Unique Attendees', 'Currently In']);
            foreach ($report['branch_breakdown'] as $row) {
                fputcsv($handle, [$row['branch_name'], $row['check_in_count'], $row['unique_attendees'], $row['currently_in_count']]);
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
            fputcsv($handle, ['Name', 'Type', 'Branch', 'Checked In', 'Checked Out', 'Duration Minutes', 'Status']);
            foreach ($report['recent_records'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['attendee_type_label'],
                    $row['branch_name'],
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
     * reportPayload
     *
     * @return array<string, mixed>
     */
    private function reportPayload(Request $request): array
    {
        $data = $request->validate([
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['nullable', Rule::in([
                Attendance::TYPE_MEMBER,
                Attendance::TYPE_WALK_IN,
                Attendance::TYPE_EMPLOYEE,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $branches = auth()->user()->getBranches();
        $selectedBranch = ($data['branch'] ?? null) ? $branches->find((int) $data['branch']) : null;
        $accessibleBranchIds = $selectedBranch
            ? [$selectedBranch->id]
            : $branches->pluck('id')->all();
        $branchNames = $branches
            ->whereIn('id', $accessibleBranchIds)
            ->pluck('name', 'id');

        $attendanceRecords = Attendance::query()
            ->whereIn('branch_id', $accessibleBranchIds)
            ->when($request->type, fn ($query) => $query->where('attendee_type', $request->type))
            ->when($request->date_from, fn ($query) => $query->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($query) => $query->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->get([
                'id',
                'branch_id',
                'attendee_type',
                'user_id',
                'walk_in_id',
                'name',
                'checked_in_at',
                'checked_out_at',
            ]);

        return [
            'scope' => [
                'branch' => $selectedBranch ? [
                    'id' => $selectedBranch->id,
                    'name' => $selectedBranch->name,
                ] : null,
                'is_all_branches' => ! $selectedBranch,
            ],
            'filters' => [
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'type' => $data['type'] ?? null,
                'type_label' => ($data['type'] ?? null)
                    ? $this->typeLabel($data['type'])
                    : null,
            ],
            'summary' => $this->summary($attendanceRecords),
            'type_breakdown' => $this->typeBreakdown($attendanceRecords),
            'branch_breakdown' => $this->branchBreakdown($attendanceRecords, $branchNames),
            'daily_trend' => $this->dailyTrend($attendanceRecords),
            'busiest_hours' => $this->busiestHours($attendanceRecords),
            'recent_records' => $this->recentRecords($attendanceRecords, $branchNames),
        ];
    }

    /**
     * summary
     *
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
     * typeBreakdown
     *
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
     * branchBreakdown
     *
     * @param  Collection<int, string>  $branchNames
     * @return array<int, array<string, mixed>>
     */
    private function branchBreakdown(Collection $attendanceRecords, Collection $branchNames): array
    {
        return $attendanceRecords
            ->groupBy('branch_id')
            ->map(function (Collection $records, int|string $branchId) use ($branchNames): array {
                return [
                    'branch_id' => (int) $branchId,
                    'branch_name' => $branchNames->get((int) $branchId, 'Unknown Branch'),
                    'check_in_count' => $records->count(),
                    'unique_attendees' => $this->uniqueAttendeeCount($records),
                    'currently_in_count' => $records->whereNull('checked_out_at')->count(),
                ];
            })
            ->sortByDesc('check_in_count')
            ->values()
            ->all();
    }

    /**
     * dailyTrend
     *
     * @return array[]
     */
    private function dailyTrend(Collection $attendanceRecords): array
    {
        return $attendanceRecords
            ->groupBy(fn (Attendance $attendance) => $attendance->checked_in_at?->toDateString() ?? 'unknown')
            ->map(function (Collection $records, string $date): array {
                return [
                    'attendance_date' => $date,
                    'check_in_count' => $records->count(),
                    'unique_attendees' => $this->uniqueAttendeeCount($records),
                ];
            })
            ->sortBy('attendance_date')
            ->values()
            ->all();
    }

    /**
     * busiestHours
     *
     * @return array[]
     */
    private function busiestHours(Collection $attendanceRecords): array
    {
        return $attendanceRecords
            ->groupBy(fn (Attendance $attendance) => $attendance->checked_in_at?->format('H:00') ?? 'Unknown')
            ->map(function (Collection $records, string $hourSlot): array {
                return [
                    'hour_slot' => $hourSlot,
                    'label' => $hourSlot === 'Unknown' ? 'Unknown' : "{$hourSlot} - ".substr($hourSlot, 0, 2).':59',
                    'check_in_count' => $records->count(),
                ];
            })
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
     * recentRecords
     *
     * @return array[]
     */
    private function recentRecords(Collection $attendanceRecords, Collection $branchNames): array
    {
        return $attendanceRecords
            ->take(10)
            ->map(function (Attendance $attendance) use ($branchNames): array {
                return [
                    'id' => $attendance->id,
                    'name' => $attendance->name,
                    'attendee_type' => $attendance->attendee_type,
                    'attendee_type_label' => $this->typeLabel($attendance->attendee_type),
                    'branch_name' => $branchNames->get((int) $attendance->branch_id, 'Unknown Branch'),
                    'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                    'checked_out_at' => $attendance->checked_out_at?->toISOString(),
                    'duration_minutes' => $this->durationMinutes($attendance),
                    'is_currently_in' => $attendance->checked_out_at === null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * uniqueAttendeeCount
     */
    private function uniqueAttendeeCount(Collection $attendanceRecords): int
    {
        return $attendanceRecords
            ->map(fn (Attendance $attendance) => $this->attendeeKey($attendance))
            ->unique()
            ->count();
    }

    /**
     * attendeeKey
     */
    private function attendeeKey(Attendance $attendance): string
    {
        if ($attendance->attendee_type === Attendance::TYPE_WALK_IN) {
            $normalizedName = str($attendance->name)
                ->trim()
                ->lower()
                ->replaceMatches('/\s+/', ' ')
                ->toString();

            if ($normalizedName !== '') {
                return "{$attendance->attendee_type}:{$attendance->branch_id}:{$normalizedName}";
            }

            if ($attendance->walk_in_id) {
                return "{$attendance->attendee_type}:{$attendance->walk_in_id}";
            }
        }

        $referenceId = $attendance->user_id
            ?? $attendance->walk_in_id
            ?? $attendance->id;

        return "{$attendance->attendee_type}:{$referenceId}";
    }

    /**
     * durationMinutes
     *
     * @return float|int|null
     */
    private function durationMinutes(Attendance $attendance): ?int
    {
        if (! $attendance->checked_in_at || ! $attendance->checked_out_at) {
            return null;
        }

        return max(0, $attendance->checked_in_at->diffInMinutes($attendance->checked_out_at));
    }

    /**
     * typeLabel
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
