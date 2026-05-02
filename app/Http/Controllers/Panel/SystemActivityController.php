<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SystemActivityController extends Controller
{
    public function __construct(
        private SystemActivityService $systemActivityService,
    ) {
        $this->middleware(function (Request $request, \Closure $next) {
            abort_unless($this->systemActivityService->canViewSystemActivity($request->user()), 403);

            return $next($request);
        });
    }

    public function index(): View
    {
        return view('panel.system-activity');
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['nullable', 'string', 'max:60'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in(['occurred_at', 'event', 'subject_label', 'title', 'actor_name'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $perPage = (int) ($data['per_page'] ?? 20);
        $sortBy = (string) ($data['sort_by'] ?? 'occurred_at');
        $sortDirection = (string) ($data['sort_direction'] ?? 'desc');

        $systemActivitiesQuery = SystemActivity::query()
            ->when(! empty($data['subject_type']), fn ($query) => $query->where('subject_type', $data['subject_type']))
            ->when(! empty($data['subject_id']), fn ($query) => $query->where('subject_id', $data['subject_id']))
            ->when(! empty($data['event']), fn ($query) => $query->where('event', $data['event']))
            ->when(! empty($data['date_from']), fn ($query) => $query->where('occurred_at', '>=', Carbon::parse((string) $data['date_from'])->startOfDay()))
            ->when(! empty($data['date_to']), fn ($query) => $query->where('occurred_at', '<=', Carbon::parse((string) $data['date_to'])->endOfDay()))
            ->when(! empty($data['search']), function ($query) use ($data) {
                $search = trim((string) $data['search']);

                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('subject_label', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%");
                });
            });

        $this->applySorting($systemActivitiesQuery, $sortBy, $sortDirection);

        $systemActivities = $systemActivitiesQuery
            ->paginate($perPage)
            ->withQueryString();

        $systemActivities->setCollection(
            $systemActivities->getCollection()->map(
                fn (SystemActivity $systemActivity) => $this->serializeSystemActivity($systemActivity)
            )
        );

        return response()->json([
            'events' => $systemActivities,
            'meta' => [
                'subject_types' => $this->systemActivityService->subjectOptions(),
                'event_options' => $this->systemActivityService->eventOptions(),
            ],
        ]);
    }

    public function restore(Request $request, SystemActivity $systemActivity): JsonResponse
    {
        $this->systemActivityService->restoreSubject($systemActivity, $request->user());

        return response()->json([
            'message' => 'Record restored successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSystemActivity(SystemActivity $systemActivity): array
    {
        return [
            'id' => $systemActivity->id,
            'title' => $systemActivity->title,
            'message' => $systemActivity->message,
            'event' => $systemActivity->event,
            'event_label' => $this->systemActivityService->eventLabel($systemActivity->event),
            'subject_type' => $systemActivity->subject_type,
            'subject_type_label' => collect($this->systemActivityService->subjectOptions())
                ->firstWhere('value', $systemActivity->subject_type)['label'] ?? $systemActivity->subject_type,
            'subject_id' => $systemActivity->subject_id,
            'subject_label' => $systemActivity->subject_label,
            'actor_name' => $systemActivity->actor_name,
            'occurred_at' => $systemActivity->occurred_at?->toISOString(),
            'action_url' => $this->systemActivityService->actionUrl($systemActivity),
            'restore' => $this->systemActivityService->restoreDescriptor($systemActivity),
            'metadata' => $systemActivity->metadata ?? [],
            'caused_by' => $this->systemActivityService->normalizeCausedBy($systemActivity->metadata ?? []),
        ];
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        match ($sortBy) {
            'event' => $query
                ->orderBy('event', $sortDirection)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
            'subject_label' => $query
                ->orderByRaw(sprintf('COALESCE(subject_label, subject_type) %s', $sortDirection))
                ->orderBy('subject_id', $sortDirection)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
            'title' => $query
                ->orderBy('title', $sortDirection)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
            'actor_name' => $query
                ->orderByRaw(sprintf("COALESCE(actor_name, '') %s", $sortDirection))
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
            default => $query
                ->orderBy('occurred_at', $sortDirection)
                ->orderBy('id', $sortDirection),
        };
    }
}
