<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditSubjectRegistry
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function subjectOptions(): array
    {
        return [
            ['value' => AuditEvent::SUBJECT_BUSINESS_PROFILE, 'label' => 'Business Profile'],
            ['value' => AuditEvent::SUBJECT_EMPLOYEE, 'label' => 'Employees'],
            ['value' => AuditEvent::SUBJECT_PAYROLL, 'label' => 'Payrolls'],
            ['value' => AuditEvent::SUBJECT_PAYOUT, 'label' => 'Payouts'],
            ['value' => AuditEvent::SUBJECT_CASH_ADVANCE, 'label' => 'Cash Advances'],
            ['value' => AuditEvent::SUBJECT_MEMBER, 'label' => 'Members'],
            ['value' => AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION, 'label' => 'Memberships'],
            ['value' => AuditEvent::SUBJECT_MEMBER_PT_PACKAGE, 'label' => 'PT Packages'],
            ['value' => AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE, 'label' => 'PT Session Usage'],
            ['value' => AuditEvent::SUBJECT_ATTENDANCE, 'label' => 'Attendance'],
            ['value' => AuditEvent::SUBJECT_WALK_IN, 'label' => 'Walk-ins'],
            ['value' => AuditEvent::SUBJECT_SALE_TRANSACTION, 'label' => 'Sales'],
            ['value' => AuditEvent::SUBJECT_INVENTORY_ITEM, 'label' => 'Inventory'],
            ['value' => AuditEvent::SUBJECT_RATE_PLAN, 'label' => 'Rate Plans'],
            ['value' => AuditEvent::SUBJECT_PT_PRODUCT, 'label' => 'PT Products'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function eventOptions(): array
    {
        return collect([
            'requested',
            'approved',
            'released',
            'partially_paid',
            'paid',
            'cancelled',
            'created',
            'updated',
            'deleted',
            'restored',
            'configured',
            'removed',
            'plan_changed',
            'status_updated',
            'manager_assigned',
            'assigned',
            'recorded',
            'checked_in',
            'checked_out',
            'stock_deducted',
            'biometric_enrollment_started',
            'biometric_enrolled',
            'biometric_removed',
            'biometric_failed',
        ])
            ->map(fn (string $event): array => [
                'value' => $event,
                'label' => $this->eventLabel($event),
            ])
            ->all();
    }

    public function eventLabel(string $event): string
    {
        return Str::of($event)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function actionUrl(AuditEvent $auditEvent): ?string
    {
        return $this->actionUrlFor(
            $auditEvent->subject_type,
            (int) $auditEvent->subject_id,
            is_array($auditEvent->metadata) ? $auditEvent->metadata : [],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function actionUrlFor(string $subjectType, int $subjectId, array $metadata = []): ?string
    {
        $employeeId = $this->integerValue($metadata, ['employee_id', 'context.employee_id']);
        $memberId = $this->integerValue($metadata, ['member_id', 'context.member_id']);

        return match ($subjectType) {
            AuditEvent::SUBJECT_BUSINESS_PROFILE => route('panel.business.settings'),
            AuditEvent::SUBJECT_EMPLOYEE => route('panel.employees.show', $subjectId),
            AuditEvent::SUBJECT_PAYROLL,
            AuditEvent::SUBJECT_PAYOUT,
            AuditEvent::SUBJECT_CASH_ADVANCE => $employeeId ? route('panel.employees.show', $employeeId) : null,
            AuditEvent::SUBJECT_MEMBER => route('panel.members.show', $subjectId),
            AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION,
            AuditEvent::SUBJECT_MEMBER_PT_PACKAGE,
            AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE => $memberId ? route('panel.members.show', $memberId) : null,
            AuditEvent::SUBJECT_ATTENDANCE => route('panel.attendance.index'),
            AuditEvent::SUBJECT_WALK_IN => route('panel.walkins.index'),
            AuditEvent::SUBJECT_SALE_TRANSACTION => route('panel.sales.index'),
            AuditEvent::SUBJECT_INVENTORY_ITEM => route('panel.inventory.index'),
            AuditEvent::SUBJECT_RATE_PLAN,
            AuditEvent::SUBJECT_PT_PRODUCT => route('panel.pricing.index'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    public function normalizeCausedBy(array $metadata): ?array
    {
        $causedBy = $metadata['caused_by'] ?? null;

        if (! is_array($causedBy)) {
            return null;
        }

        $subjectType = (string) ($causedBy['subject_type'] ?? '');
        $subjectId = (int) ($causedBy['subject_id'] ?? 0);
        $event = (string) ($causedBy['event'] ?? '');

        if ($subjectType === '' || $subjectId < 1 || $event === '') {
            return null;
        }

        $context = is_array($causedBy['context'] ?? null) ? $causedBy['context'] : [];

        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $causedBy['subject_label'] ?? null,
            'event' => $event,
            'event_label' => $this->eventLabel($event),
            'action_url' => $this->actionUrlFor($subjectType, $subjectId, $context),
        ];
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function integerValue(array $metadata, array $paths): ?int
    {
        foreach ($paths as $path) {
            $value = Arr::get($metadata, $path);

            if ($value !== null && $value !== '' && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
