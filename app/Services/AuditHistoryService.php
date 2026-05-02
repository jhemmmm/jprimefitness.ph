<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\InventoryItem;
use App\Models\User;
use App\Support\AuditSubjectRegistry;
use App\Support\PanelAuditEventFormatter;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditHistoryService
{
    public function __construct(
        private AuditSubjectRegistry $auditSubjectRegistry,
        private PanelAuditEventFormatter $panelAuditEventFormatter,
    ) {}

    /**
     * @param  array{
     *     subject_type: string,
     *     subject_id: int,
     *     subject_label?: ?string,
     *     event: string,
     *     title: string,
     *     message: string,
     *     actor_user_id?: ?int,
     *     actor_name?: ?string,
     *     metadata?: array<string, mixed>,
     *     occurred_at?: DateTimeInterface|string|null
     * }  $payload
     */
    public function record(array $payload): AuditEvent
    {
        return AuditEvent::create([
            'subject_type' => $payload['subject_type'],
            'subject_id' => $payload['subject_id'],
            'subject_label' => $payload['subject_label'] ?? null,
            'event' => $payload['event'],
            'title' => $payload['title'],
            'message' => $payload['message'],
            'actor_user_id' => $payload['actor_user_id'] ?? null,
            'actor_name' => $payload['actor_name'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     */
    public function formatSubjectEvent(
        string $subjectType,
        int $subjectId,
        string $event,
        array $snapshot = [],
        array $metadata = [],
    ): array {
        return $this->panelAuditEventFormatter->format($subjectType, $subjectId, $event, $snapshot, $metadata);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     */
    public function recordSubjectEvent(
        string $subjectType,
        int $subjectId,
        string $event,
        array $snapshot = [],
        array $metadata = [],
        ?int $actorUserId = null,
        ?string $actorName = null,
        DateTimeInterface|string|null $occurredAt = null,
    ): AuditEvent {
        $payload = $this->formatSubjectEvent($subjectType, $subjectId, $event, $snapshot, $metadata);

        return $this->record([
            ...$payload,
            'actor_user_id' => $actorUserId,
            'actor_name' => $actorName,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function causedBy(
        string $subjectType,
        int $subjectId,
        string $event,
        array $snapshot = [],
        array $context = [],
    ): array {
        $payload = $this->formatSubjectEvent($subjectType, $subjectId, $event, $snapshot);

        return array_filter([
            'subject_type' => $payload['subject_type'],
            'subject_id' => $payload['subject_id'],
            'subject_label' => $payload['subject_label'] ?? null,
            'event' => $payload['event'],
            'context' => $context !== [] ? $context : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function subjectOptions(): array
    {
        return $this->auditSubjectRegistry->subjectOptions();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function eventOptions(): array
    {
        return $this->auditSubjectRegistry->eventOptions();
    }

    public function eventLabel(string $event): string
    {
        return $this->auditSubjectRegistry->eventLabel($event);
    }

    public function actionUrl(AuditEvent $auditEvent): ?string
    {
        return $this->auditSubjectRegistry->actionUrl($auditEvent);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    public function normalizeCausedBy(array $metadata): ?array
    {
        return $this->auditSubjectRegistry->normalizeCausedBy($metadata);
    }

    public function canViewSystemActivity(mixed $user): bool
    {
        return $user !== null && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['super admin', 'admin', 'manager']);
    }

    /**
     * @return array{available: bool, label: ?string, reason: ?string}
     */
    public function restoreDescriptor(AuditEvent $auditEvent): array
    {
        if ($auditEvent->event !== 'deleted') {
            return [
                'available' => false,
                'label' => null,
                'reason' => null,
            ];
        }

        $subject = $this->restorableSubject($auditEvent);

        if ($subject === null) {
            return [
                'available' => false,
                'label' => null,
                'reason' => 'This historical delete cannot be recovered because the original record no longer exists.',
            ];
        }

        if (! method_exists($subject, 'trashed')) {
            return [
                'available' => false,
                'label' => null,
                'reason' => 'This deleted activity cannot be restored from System Activity.',
            ];
        }

        if (! $subject->trashed()) {
            return [
                'available' => false,
                'label' => null,
                'reason' => 'This record is already active.',
            ];
        }

        return [
            'available' => true,
            'label' => 'Restore',
            'reason' => null,
        ];
    }

    public function restoreSubject(AuditEvent $auditEvent, mixed $actor): void
    {
        $restoreDescriptor = $this->restoreDescriptor($auditEvent);

        if (! $restoreDescriptor['available']) {
            throw ValidationException::withMessages([
                'restore' => $restoreDescriptor['reason'] ?? 'This deleted activity cannot be restored from System Activity.',
            ]);
        }

        $subject = $this->restorableSubject($auditEvent);

        if (! $subject instanceof Model) {
            throw ValidationException::withMessages([
                'restore' => 'This historical delete cannot be recovered because the original record no longer exists.',
            ]);
        }

        DB::transaction(function () use ($auditEvent, $subject, $actor): void {
            match ($auditEvent->subject_type) {
                AuditEvent::SUBJECT_EMPLOYEE => $this->restoreEmployee($subject, $actor),
                AuditEvent::SUBJECT_ATTENDANCE => $this->restoreAttendance($subject, $actor),
                AuditEvent::SUBJECT_INVENTORY_ITEM => $this->restoreInventoryItem($subject, $actor),
                default => throw ValidationException::withMessages([
                    'restore' => 'This deleted activity cannot be restored from System Activity.',
                ]),
            };
        });
    }

    private function restoreEmployee(Model $subject, mixed $actor): void
    {
        $employee = $subject instanceof User ? $subject->loadMissing('roles') : null;

        if (! $employee instanceof User) {
            throw ValidationException::withMessages([
                'restore' => 'This employee delete cannot be restored.',
            ]);
        }

        $employee->restore();
        $employee->refresh()->load(['roles', 'employeeProfile']);
        $employeeProfile = $employee->employeeProfile;

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'restored',
            [
                'id' => $employee->id,
                'name' => $employee->name,
                'status' => $employee->status,
                'role_names' => $employee->roles->pluck('name')->values()->all(),
                'daily_rate' => round((float) ($employeeProfile?->daily_rate ?? 0), 2),
                'pay_frequency' => $employeeProfile?->pay_frequency,
            ],
            [],
            $this->actorId($actor),
            $this->actorName($actor),
            now(),
        );
    }

    private function restoreAttendance(Model $subject, mixed $actor): void
    {
        $attendance = $subject instanceof Attendance
            ? $subject->loadMissing(['user', 'recordedBy'])
            : null;

        if (! $attendance instanceof Attendance) {
            throw ValidationException::withMessages([
                'restore' => 'This attendance delete cannot be restored.',
            ]);
        }

        $attendance->restore();
        $attendance->refresh()->load(['user', 'recordedBy']);

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_ATTENDANCE,
            $attendance->id,
            'restored',
            [
                'id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'name' => $attendance->name ?: $attendance->user?->name,
                'attendee_type' => $attendance->attendee_type,
                'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            ],
            [],
            $this->actorId($actor),
            $this->actorName($actor),
            now(),
        );
    }

    private function restoreInventoryItem(Model $subject, mixed $actor): void
    {
        $inventoryItem = $subject instanceof InventoryItem ? $subject->loadMissing('category:id,name') : null;

        if (! $inventoryItem instanceof InventoryItem) {
            throw ValidationException::withMessages([
                'restore' => 'This inventory delete cannot be restored.',
            ]);
        }

        $inventoryItem->restore();
        $inventoryItem->refresh()->load('category:id,name');

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_INVENTORY_ITEM,
            $inventoryItem->id,
            'restored',
            [
                'id' => $inventoryItem->id,
                'name' => $inventoryItem->name,
                'category_name' => $inventoryItem->category?->name,
                'quantity' => round((float) $inventoryItem->quantity, 2),
                'unit' => $inventoryItem->unit,
            ],
            [],
            $this->actorId($actor),
            $this->actorName($actor),
            now(),
        );
    }

    private function restorableSubject(AuditEvent $auditEvent): ?Model
    {
        return match ($auditEvent->subject_type) {
            AuditEvent::SUBJECT_EMPLOYEE => User::withTrashed()->find($auditEvent->subject_id),
            AuditEvent::SUBJECT_ATTENDANCE => Attendance::withTrashed()->find($auditEvent->subject_id),
            AuditEvent::SUBJECT_INVENTORY_ITEM => InventoryItem::withTrashed()->find($auditEvent->subject_id),
            default => null,
        };
    }

    private function actorId(mixed $actor): ?int
    {
        if (is_object($actor) && method_exists($actor, 'getAuthIdentifier')) {
            $identifier = $actor->getAuthIdentifier();

            return $identifier !== null ? (int) $identifier : null;
        }

        return null;
    }

    private function actorName(mixed $actor): ?string
    {
        if (is_object($actor) && isset($actor->name) && is_string($actor->name) && trim($actor->name) !== '') {
            return $actor->name;
        }

        return null;
    }

}
