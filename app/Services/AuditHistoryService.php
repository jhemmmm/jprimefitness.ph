<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\CashAdvance;
use App\Models\CashLedgerEntry;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WalkIn;
use App\Support\AuditSubjectRegistry;
use App\Support\CashAdvanceAuditEventFormatter;
use App\Support\PanelAuditEventFormatter;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuditHistoryService
{
    public function __construct(
        private AuditSubjectRegistry $auditSubjectRegistry,
        private CashAdvanceAuditEventFormatter $cashAdvanceAuditEventFormatter,
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
        return match ($subjectType) {
            AuditEvent::SUBJECT_CASH_ADVANCE => $this->cashAdvanceAuditEventFormatter->format($snapshot, $event, $metadata),
            default => $this->panelAuditEventFormatter->format($subjectType, $subjectId, $event, $snapshot, $metadata),
        };
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

    public function canViewAuditHistory(mixed $user): bool
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

        if ($subject instanceof CashLedgerEntry && $subject->is_system) {
            return [
                'available' => false,
                'label' => null,
                'reason' => 'System-generated cash ledger entries are restored by restoring their source record.',
            ];
        }

        if (! method_exists($subject, 'trashed')) {
            return [
                'available' => false,
                'label' => null,
                'reason' => 'This deleted event cannot be restored from Audit History.',
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
                'restore' => $restoreDescriptor['reason'] ?? 'This deleted event cannot be restored from Audit History.',
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
                AuditEvent::SUBJECT_WALK_IN => $this->restoreWalkIn($subject, $actor),
                AuditEvent::SUBJECT_INVENTORY_ITEM => $this->restoreInventoryItem($subject, $actor),
                AuditEvent::SUBJECT_CASH_ADVANCE => $this->restoreCashAdvance($subject, $actor),
                AuditEvent::SUBJECT_CASH_LEDGER_ENTRY => $this->restoreCashLedgerEntry($subject, $actor),
                default => throw ValidationException::withMessages([
                    'restore' => 'This deleted event cannot be restored from Audit History.',
                ]),
            };
        });
    }

    public function backfillCashAdvanceEvents(): void
    {
        if (! Schema::hasTable('audit_events')
            || ! Schema::hasTable('cash_advances')
            || ! Schema::hasColumn('cash_advances', 'audit_data')) {
            return;
        }

        DB::table('cash_advances')
            ->leftJoin('users as employees', 'employees.id', '=', 'cash_advances.employee_id')
            ->leftJoin('users as approved_by_users', 'approved_by_users.id', '=', 'cash_advances.approved_by')
            ->leftJoin('users as released_by_users', 'released_by_users.id', '=', 'cash_advances.released_by')
            ->leftJoin('users as cancelled_by_users', 'cancelled_by_users.id', '=', 'cash_advances.cancelled_by')
            ->select([
                'cash_advances.id',
                'cash_advances.employee_id',
                'cash_advances.amount',
                'cash_advances.remaining_amount',
                'cash_advances.status',
                'cash_advances.notes',
                'cash_advances.requested_at',
                'cash_advances.approved_at',
                'cash_advances.approved_by',
                'cash_advances.released_at',
                'cash_advances.released_by',
                'cash_advances.cancelled_at',
                'cash_advances.cancelled_by',
                'cash_advances.paid_at',
                'cash_advances.audit_data',
                'employees.name as employee_name',
                'approved_by_users.name as approved_by_name',
                'released_by_users.name as released_by_name',
                'cancelled_by_users.name as cancelled_by_name',
            ])
            ->orderBy('cash_advances.id')
            ->chunk(100, function (Collection $cashAdvances): void {
                $inserts = [];

                foreach ($cashAdvances as $cashAdvance) {
                    $snapshot = $this->cashAdvanceSnapshotFromRow($cashAdvance);

                    foreach ($this->normalizedLegacyCashAdvanceEvents($cashAdvance) as $event) {
                        $payload = $this->cashAdvanceAuditEventFormatter->format(
                            $snapshot,
                            $event['event'],
                            $event['metadata'],
                        );

                        $inserts[] = [
                            'subject_type' => $payload['subject_type'],
                            'subject_id' => $payload['subject_id'],
                            'subject_label' => $payload['subject_label'],
                            'event' => $payload['event'],
                            'title' => $payload['title'],
                            'message' => $payload['message'],
                            'actor_user_id' => $event['actor_user_id'],
                            'actor_name' => $event['actor_name'],
                            'metadata' => json_encode($payload['metadata'], JSON_THROW_ON_ERROR),
                            'occurred_at' => $event['at']->toDateTimeString(),
                            'created_at' => $event['at']->toDateTimeString(),
                            'updated_at' => $event['at']->toDateTimeString(),
                        ];
                    }
                }

                if ($inserts !== []) {
                    DB::table('audit_events')->insert($inserts);
                }
            });
    }

    /**
     * @return array{id: int, employee_id: int, employee_name: string, amount: float}
     */
    private function cashAdvanceSnapshotFromRow(object $cashAdvance): array
    {
        return [
            'id' => (int) $cashAdvance->id,
            'employee_id' => (int) $cashAdvance->employee_id,
            'employee_name' => (string) ($cashAdvance->employee_name ?? 'Unknown Employee'),
            'amount' => round((float) $cashAdvance->amount, 2),
        ];
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
            ? $subject->loadMissing(['user', 'walkIn', 'recordedBy'])
            : null;

        if (! $attendance instanceof Attendance) {
            throw ValidationException::withMessages([
                'restore' => 'This attendance delete cannot be restored.',
            ]);
        }

        $attendance->restore();
        $attendance->refresh()->load(['user', 'walkIn', 'recordedBy']);

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_ATTENDANCE,
            $attendance->id,
            'restored',
            [
                'id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'walk_in_id' => $attendance->walk_in_id,
                'name' => $attendance->name ?: $attendance->user?->name ?: $attendance->walkIn?->name,
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

    private function restoreWalkIn(Model $subject, mixed $actor): void
    {
        $walkIn = $subject instanceof WalkIn ? $subject->loadMissing('ratePlan') : null;

        if (! $walkIn instanceof WalkIn) {
            throw ValidationException::withMessages([
                'restore' => 'This walk-in delete cannot be restored.',
            ]);
        }

        $walkIn->restore();
        $walkIn->refresh()->load('ratePlan');
        app(CashLedgerService::class)->syncWalkIn($walkIn, 'restored');

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_WALK_IN,
            $walkIn->id,
            'restored',
            [
                'id' => $walkIn->id,
                'name' => $walkIn->name,
                'rate_plan_name' => $walkIn->ratePlan?->name,
                'amount_paid' => round((float) $walkIn->amount_paid, 2),
                'payment_method' => $walkIn->payment_method,
                'served_by' => $walkIn->served_by,
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

    private function restoreCashAdvance(Model $subject, mixed $actor): void
    {
        $cashAdvance = $subject instanceof CashAdvance
            ? $subject->loadMissing(['employee:id,name', 'approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name'])
            : null;

        if (! $cashAdvance instanceof CashAdvance) {
            throw ValidationException::withMessages([
                'restore' => 'This cash advance delete cannot be restored.',
            ]);
        }

        $cashAdvance->restore();
        $cashAdvance->refresh()->loadMissing(['employee:id,name', 'approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name']);
        app(CashLedgerService::class)->syncCashAdvance($cashAdvance, 'restored');

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_ADVANCE,
            $cashAdvance->id,
            'restored',
            [
                'id' => $cashAdvance->id,
                'employee_id' => (int) $cashAdvance->employee_id,
                'employee_name' => (string) ($cashAdvance->employee?->name ?? 'Unknown Employee'),
                'amount' => round((float) $cashAdvance->amount, 2),
            ],
            [],
            $this->actorId($actor),
            $this->actorName($actor),
            now(),
        );
    }

    private function restoreCashLedgerEntry(Model $subject, mixed $actor): void
    {
        $entry = $subject instanceof CashLedgerEntry ? $subject->loadMissing('createdBy:id,name') : null;

        if (! $entry instanceof CashLedgerEntry || $entry->is_system) {
            throw ValidationException::withMessages([
                'restore' => 'Only manual cash ledger deletes can be restored directly from Audit History.',
            ]);
        }

        $entry->restore();
        $entry->refresh()->loadMissing('createdBy:id,name');

        $this->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            $entry->id,
            'restored',
            [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'direction' => $entry->direction,
                'amount' => round((float) $entry->amount, 2),
                'title' => $entry->title,
                'description' => $entry->description,
                'is_system' => (bool) $entry->is_system,
                'source_id' => $entry->source_id,
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
            AuditEvent::SUBJECT_WALK_IN => WalkIn::withTrashed()->find($auditEvent->subject_id),
            AuditEvent::SUBJECT_INVENTORY_ITEM => InventoryItem::withTrashed()->find($auditEvent->subject_id),
            AuditEvent::SUBJECT_CASH_ADVANCE => CashAdvance::withTrashed()->find($auditEvent->subject_id),
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY => CashLedgerEntry::withTrashed()->find($auditEvent->subject_id),
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

    /**
     * @return array<int, array{
     *     event: string,
     *     at: Carbon,
     *     actor_user_id: ?int,
     *     actor_name: ?string,
     *     metadata: array<string, mixed>
     * }>
     */
    private function normalizedLegacyCashAdvanceEvents(object $cashAdvance): array
    {
        $auditData = json_decode((string) ($cashAdvance->audit_data ?? '[]'), true);

        if (! is_array($auditData)) {
            $auditData = [];
        }

        $normalizedAuditData = [];

        foreach ($auditData as $event) {
            if (! is_array($event) || empty($event['at'])) {
                continue;
            }

            $normalizedAuditData[] = [
                'event' => $this->normalizeEventName($event['event'] ?? null),
                'at' => Carbon::parse((string) $event['at']),
                'actor_user_id' => isset($event['by_user_id']) ? (int) $event['by_user_id'] : null,
                'actor_name' => $this->nullableString($event['by_name'] ?? null),
                'metadata' => $this->filterMetadata([
                    'notes' => $this->nullableString($event['notes'] ?? null),
                    'source' => $this->nullableString($event['source'] ?? null),
                    'source_id' => isset($event['source_id']) ? (int) $event['source_id'] : null,
                    'deducted_amount' => $this->nullableFloat($event['deducted_amount'] ?? null),
                    'remaining_before' => $this->nullableFloat($event['remaining_before'] ?? null),
                    'remaining_after' => $this->nullableFloat($event['remaining_after'] ?? null),
                ]),
            ];
        }

        $recordedEvents = collect($normalizedAuditData)
            ->pluck('event')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $legacyEvents = collect([
            [
                'event' => 'requested',
                'at' => $cashAdvance->requested_at,
                'actor_user_id' => (int) $cashAdvance->employee_id,
                'actor_name' => $this->nullableString($cashAdvance->employee_name ?? null),
                'metadata' => $this->filterMetadata([
                    'notes' => $this->nullableString($cashAdvance->notes ?? null),
                    'source' => 'panel',
                ]),
            ],
            [
                'event' => 'approved',
                'at' => $cashAdvance->approved_at,
                'actor_user_id' => $cashAdvance->approved_by !== null ? (int) $cashAdvance->approved_by : null,
                'actor_name' => $this->nullableString($cashAdvance->approved_by_name ?? null),
                'metadata' => $this->filterMetadata([
                    'notes' => $this->nullableString($cashAdvance->notes ?? null),
                    'source' => 'panel',
                ]),
            ],
            [
                'event' => 'released',
                'at' => $cashAdvance->released_at,
                'actor_user_id' => $cashAdvance->released_by !== null ? (int) $cashAdvance->released_by : null,
                'actor_name' => $this->nullableString($cashAdvance->released_by_name ?? null),
                'metadata' => $this->filterMetadata([
                    'notes' => $this->nullableString($cashAdvance->notes ?? null),
                    'source' => 'panel',
                ]),
            ],
            [
                'event' => 'paid',
                'at' => $cashAdvance->paid_at,
                'actor_user_id' => null,
                'actor_name' => null,
                'metadata' => $this->filterMetadata([
                    'remaining_after' => 0,
                ]),
            ],
            [
                'event' => 'cancelled',
                'at' => $cashAdvance->cancelled_at,
                'actor_user_id' => $cashAdvance->cancelled_by !== null ? (int) $cashAdvance->cancelled_by : null,
                'actor_name' => $this->nullableString($cashAdvance->cancelled_by_name ?? null),
                'metadata' => $this->filterMetadata([
                    'notes' => $this->nullableString($cashAdvance->notes ?? null),
                    'source' => 'panel',
                ]),
            ],
        ])
            ->filter(function (array $event) use ($recordedEvents): bool {
                return $event['at'] !== null && ! in_array($event['event'], $recordedEvents, true);
            })
            ->map(function (array $event): array {
                return [
                    'event' => $event['event'],
                    'at' => Carbon::parse((string) $event['at']),
                    'actor_user_id' => $event['actor_user_id'],
                    'actor_name' => $event['actor_name'],
                    'metadata' => $event['metadata'],
                ];
            })
            ->values()
            ->all();

        $timeline = [...$legacyEvents, ...$normalizedAuditData];

        usort($timeline, function (array $left, array $right): int {
            $atComparison = $left['at']->getTimestamp() <=> $right['at']->getTimestamp();

            if ($atComparison !== 0) {
                return $atComparison;
            }

            return strcmp($left['event'], $right['event']);
        });

        return $timeline;
    }

    private function normalizeEventName(mixed $event): string
    {
        $normalized = trim((string) ($event ?? ''));

        return $normalized !== '' ? $normalized : 'updated';
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function filterMetadata(array $metadata): array
    {
        return array_filter($metadata, static function (mixed $value): bool {
            return $value !== null && $value !== '';
        });
    }
}
