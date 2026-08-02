<?php

namespace App\Support;

use App\Models\SystemActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SystemActivitySubjectRegistry
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function subjectOptions(): array
    {
        return [
            ['value' => SystemActivity::SUBJECT_BUSINESS_PROFILE, 'label' => 'Business Profile'],
            ['value' => SystemActivity::SUBJECT_EMPLOYEE, 'label' => 'Employees'],
            ['value' => SystemActivity::SUBJECT_PAYROLL, 'label' => 'Payrolls'],
            ['value' => SystemActivity::SUBJECT_PAYOUT, 'label' => 'Payouts'],
            ['value' => SystemActivity::SUBJECT_CASH_ADVANCE, 'label' => 'Cash Advances'],
            ['value' => SystemActivity::SUBJECT_MEMBER, 'label' => 'Members'],
            ['value' => SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION, 'label' => 'Memberships'],
            ['value' => SystemActivity::SUBJECT_MEMBER_PT_PACKAGE, 'label' => 'PT Packages'],
            ['value' => SystemActivity::SUBJECT_MEMBER_PT_SESSION_USAGE, 'label' => 'PT Session Usage'],
            ['value' => SystemActivity::SUBJECT_ATTENDANCE, 'label' => 'Attendance'],
            ['value' => SystemActivity::SUBJECT_SALE_TRANSACTION, 'label' => 'Sales'],
            ['value' => SystemActivity::SUBJECT_INVENTORY_ITEM, 'label' => 'Inventory'],
            ['value' => SystemActivity::SUBJECT_RATE_PLAN, 'label' => 'Rate Plans'],
            ['value' => SystemActivity::SUBJECT_PT_PRODUCT, 'label' => 'PT Products'],
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
            'voided',
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

    public function actionUrl(SystemActivity $systemActivity): ?string
    {
        return $this->actionUrlFor(
            $systemActivity->subject_type,
            (int) $systemActivity->subject_id,
            is_array($systemActivity->metadata) ? $systemActivity->metadata : [],
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
            SystemActivity::SUBJECT_BUSINESS_PROFILE => route('panel.business.settings'),
            SystemActivity::SUBJECT_EMPLOYEE => route('panel.employees.show', $subjectId),
            SystemActivity::SUBJECT_PAYROLL,
            SystemActivity::SUBJECT_PAYOUT,
            SystemActivity::SUBJECT_CASH_ADVANCE => $employeeId ? route('panel.employees.show', $employeeId) : null,
            SystemActivity::SUBJECT_MEMBER => route('panel.members.show', $subjectId),
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            SystemActivity::SUBJECT_MEMBER_PT_PACKAGE,
            SystemActivity::SUBJECT_MEMBER_PT_SESSION_USAGE => $memberId ? route('panel.members.show', $memberId) : null,
            SystemActivity::SUBJECT_ATTENDANCE => route('panel.attendance.index'),
            SystemActivity::SUBJECT_SALE_TRANSACTION => route('panel.sales.index'),
            SystemActivity::SUBJECT_INVENTORY_ITEM => route('panel.inventory.index'),
            SystemActivity::SUBJECT_RATE_PLAN,
            SystemActivity::SUBJECT_PT_PRODUCT => route('panel.pricing.index'),
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
