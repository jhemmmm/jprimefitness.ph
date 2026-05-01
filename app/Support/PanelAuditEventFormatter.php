<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\CashLedgerEntry;
use App\Models\SaleTransaction;
use Illuminate\Support\Str;

class PanelAuditEventFormatter
{
    private const SUBJECT_LABEL_MAX_LENGTH = 255;

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     subject_type: string,
     *     subject_id: int,
     *     subject_label: string,
     *     event: string,
     *     title: string,
     *     message: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function format(
        string $subjectType,
        int $subjectId,
        string $event,
        array $snapshot = [],
        array $metadata = [],
    ): array {
        $normalizedEvent = trim($event) !== '' ? $event : 'updated';

        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $this->subjectLabel($subjectType, $subjectId, $snapshot),
            'event' => $normalizedEvent,
            'title' => $this->title($subjectType, $normalizedEvent),
            'message' => $this->message($subjectType, $normalizedEvent, $snapshot, $metadata),
            'metadata' => $this->metadata($subjectType, $snapshot, $metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function subjectLabel(string $subjectType, int $subjectId, array $snapshot): string
    {
        $label = match ($subjectType) {
            AuditEvent::SUBJECT_BUSINESS_PROFILE => (string) ($snapshot['name'] ?? 'Business Profile'),
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY => sprintf('Cash Ledger Entry #%d - %s', $subjectId, $snapshot['title'] ?? 'Untitled'),
            AuditEvent::SUBJECT_EMPLOYEE => sprintf('Employee #%d - %s', $subjectId, $snapshot['name'] ?? 'Unknown Employee'),
            AuditEvent::SUBJECT_PAYROLL => sprintf('Payroll #%d - %s', $subjectId, $snapshot['employee_name'] ?? 'Unknown Employee'),
            AuditEvent::SUBJECT_PAYOUT => sprintf('Payout #%d - %s', $subjectId, $snapshot['employee_name'] ?? 'Unknown Employee'),
            AuditEvent::SUBJECT_MEMBER => sprintf('Member #%d - %s', $subjectId, $snapshot['name'] ?? 'Unknown Member'),
            AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION => sprintf('Membership #%d - %s', $subjectId, $snapshot['member_name'] ?? 'Unknown Member'),
            AuditEvent::SUBJECT_MEMBER_PT_PACKAGE => sprintf('PT Package #%d - %s', $subjectId, $snapshot['member_name'] ?? 'Unknown Member'),
            AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE => sprintf('PT Session Usage #%d - %s', $subjectId, $snapshot['member_name'] ?? 'Unknown Member'),
            AuditEvent::SUBJECT_ATTENDANCE => sprintf('Attendance #%d - %s', $subjectId, $snapshot['name'] ?? 'Attendance Record'),
            AuditEvent::SUBJECT_WALK_IN => sprintf('Walk-in #%d - %s', $subjectId, $snapshot['name'] ?? 'Walk-in Record'),
            AuditEvent::SUBJECT_SALE_TRANSACTION => sprintf('Sale #%d - %s', $subjectId, $snapshot['customer_name'] ?? ($snapshot['item_name'] ?? 'Transaction')),
            AuditEvent::SUBJECT_INVENTORY_ITEM => sprintf('Inventory Item #%d - %s', $subjectId, $snapshot['name'] ?? 'Unnamed Item'),
            AuditEvent::SUBJECT_RATE_PLAN => sprintf('Rate Plan #%d - %s', $subjectId, $snapshot['name'] ?? 'Rate Plan'),
            AuditEvent::SUBJECT_PT_PRODUCT => sprintf('PT Product #%d - %s', $subjectId, $snapshot['name'] ?? 'PT Product'),
            default => sprintf('Audit Event #%d', $subjectId),
        };

        if (Str::length($label) <= self::SUBJECT_LABEL_MAX_LENGTH) {
            return $label;
        }

        return Str::limit($label, self::SUBJECT_LABEL_MAX_LENGTH - 3, '...');
    }

    private function title(string $subjectType, string $event): string
    {
        return match ($subjectType) {
            AuditEvent::SUBJECT_BUSINESS_PROFILE => match ($event) {
                'photo_added' => 'Business photo added',
                'photo_removed' => 'Business photo removed',
                default => 'Business profile updated',
            },
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY => match ($event) {
                'created' => 'Cash ledger entry created',
                'deleted' => 'Cash ledger entry deleted',
                'restored' => 'Cash ledger entry restored',
                default => 'Cash ledger entry updated',
            },
            AuditEvent::SUBJECT_EMPLOYEE => match ($event) {
                'created' => 'Employee created',
                'deleted' => 'Employee deleted',
                'restored' => 'Employee restored',
                'biometric_enrollment_started' => 'Employee fingerprint enrollment started',
                'biometric_enrolled' => 'Employee fingerprint enrolled',
                'biometric_removed' => 'Employee fingerprint removed',
                'biometric_failed' => 'Employee fingerprint enrollment failed',
                default => 'Employee updated',
            },
            AuditEvent::SUBJECT_PAYROLL => match ($event) {
                'created' => 'Payroll created',
                'approved' => 'Payroll approved',
                'cancelled' => 'Payroll cancelled',
                default => 'Payroll updated',
            },
            AuditEvent::SUBJECT_PAYOUT => 'Payout created',
            AuditEvent::SUBJECT_MEMBER => match ($event) {
                'created' => 'Member created',
                default => 'Member updated',
            },
            AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION => match ($event) {
                'created' => 'Membership created',
                'plan_changed' => 'Membership plan changed',
                'status_updated' => 'Membership status updated',
                'manager_assigned' => 'Membership manager assigned',
                default => 'Membership updated',
            },
            AuditEvent::SUBJECT_MEMBER_PT_PACKAGE => match ($event) {
                'assigned' => 'PT package assigned',
                default => 'PT package created',
            },
            AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE => 'PT session usage recorded',
            AuditEvent::SUBJECT_ATTENDANCE => match ($event) {
                'checked_in' => 'Attendance checked in',
                'checked_out' => 'Attendance checked out',
                'deleted' => 'Attendance deleted',
                'restored' => 'Attendance restored',
                default => 'Attendance updated',
            },
            AuditEvent::SUBJECT_WALK_IN => match ($event) {
                'created' => 'Walk-in created',
                'deleted' => 'Walk-in deleted',
                'restored' => 'Walk-in restored',
                default => 'Walk-in updated',
            },
            AuditEvent::SUBJECT_SALE_TRANSACTION => 'Sale created',
            AuditEvent::SUBJECT_INVENTORY_ITEM => match ($event) {
                'created' => 'Inventory item created',
                'deleted' => 'Inventory item deleted',
                'restored' => 'Inventory item restored',
                'stock_deducted' => 'Inventory stock deducted',
                default => 'Inventory item updated',
            },
            AuditEvent::SUBJECT_RATE_PLAN => match ($event) {
                'configured' => 'Rate plan configured',
                'removed' => 'Rate plan removed',
                default => 'Rate plan updated',
            },
            AuditEvent::SUBJECT_PT_PRODUCT => match ($event) {
                'configured' => 'PT product configured',
                'removed' => 'PT product removed',
                default => 'PT product updated',
            },
            default => 'Audit event recorded',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     */
    private function message(string $subjectType, string $event, array $snapshot, array $metadata): string
    {
        return match ($subjectType) {
            AuditEvent::SUBJECT_BUSINESS_PROFILE => $this->businessProfileMessage($event, $snapshot),
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY => $this->cashLedgerMessage($event, $snapshot),
            AuditEvent::SUBJECT_EMPLOYEE => $this->employeeMessage($event, $snapshot),
            AuditEvent::SUBJECT_PAYROLL => $this->payrollMessage($event, $snapshot),
            AuditEvent::SUBJECT_PAYOUT => $this->payoutMessage($snapshot),
            AuditEvent::SUBJECT_MEMBER => $this->memberMessage($event, $snapshot),
            AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION => $this->membershipMessage($event, $snapshot),
            AuditEvent::SUBJECT_MEMBER_PT_PACKAGE => $this->ptPackageMessage($event, $snapshot),
            AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE => $this->ptSessionUsageMessage($snapshot),
            AuditEvent::SUBJECT_ATTENDANCE => $this->attendanceMessage($event, $snapshot),
            AuditEvent::SUBJECT_WALK_IN => $this->walkInMessage($event, $snapshot),
            AuditEvent::SUBJECT_SALE_TRANSACTION => $this->saleMessage($snapshot),
            AuditEvent::SUBJECT_INVENTORY_ITEM => $this->inventoryMessage($event, $snapshot, $metadata),
            AuditEvent::SUBJECT_RATE_PLAN => $this->ratePlanMessage($event, $snapshot),
            AuditEvent::SUBJECT_PT_PRODUCT => $this->ptProductMessage($event, $snapshot),
            default => 'An audit event was recorded.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadata(string $subjectType, array $snapshot, array $metadata): array
    {
        $base = match ($subjectType) {
            AuditEvent::SUBJECT_BUSINESS_PROFILE => [
                'business_profile_id' => $snapshot['id'] ?? null,
                'business_name' => $snapshot['name'] ?? null,
                'status' => $snapshot['status'] ?? null,
            ],
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY => [
                'entry_type' => $snapshot['entry_type'] ?? null,
                'direction' => $snapshot['direction'] ?? null,
                'amount' => $this->nullableMoney($snapshot['amount'] ?? null),
                'title' => $snapshot['title'] ?? null,
                'description' => $snapshot['description'] ?? null,
                'is_system' => $snapshot['is_system'] ?? null,
                'source_id' => $snapshot['source_id'] ?? null,
            ],
            AuditEvent::SUBJECT_EMPLOYEE => [
                'employee_id' => $snapshot['id'] ?? null,
                'employee_name' => $snapshot['name'] ?? null,
                'status' => $snapshot['status'] ?? null,
                'role_names' => $snapshot['role_names'] ?? null,
                'daily_rate' => $this->nullableMoney($snapshot['daily_rate'] ?? null),
                'pay_frequency' => $snapshot['pay_frequency'] ?? null,
                'sss_covered' => $snapshot['sss_covered'] ?? null,
                'sss_monthly_compensation' => $this->nullableMoney($snapshot['sss_monthly_compensation'] ?? null),
                'philhealth_covered' => $snapshot['philhealth_covered'] ?? null,
                'philhealth_monthly_basic_salary' => $this->nullableMoney($snapshot['philhealth_monthly_basic_salary'] ?? null),
                'pagibig_covered' => $snapshot['pagibig_covered'] ?? null,
                'pagibig_monthly_compensation' => $this->nullableMoney($snapshot['pagibig_monthly_compensation'] ?? null),
                'biometric_status' => $snapshot['biometric_status'] ?? null,
                'biometric_fingerprint_id' => $snapshot['biometric_fingerprint_id'] ?? null,
                'biometric_enrolled_at' => $snapshot['biometric_enrolled_at'] ?? null,
            ],
            AuditEvent::SUBJECT_PAYROLL => [
                'employee_id' => $snapshot['employee_id'] ?? null,
                'employee_name' => $snapshot['employee_name'] ?? null,
                'period_start' => $snapshot['period_start'] ?? null,
                'period_end' => $snapshot['period_end'] ?? null,
                'income_tax' => $this->nullableMoney($snapshot['income_tax'] ?? null),
                'employee_contributions' => $snapshot['employee_contributions'] ?? null,
                'employee_contributions_total' => $this->nullableMoney($snapshot['employee_contributions_total'] ?? null),
                'employer_contributions' => $snapshot['employer_contributions'] ?? null,
                'employer_contributions_total' => $this->nullableMoney($snapshot['employer_contributions_total'] ?? null),
                'net_amount' => $this->nullableMoney($snapshot['net_amount'] ?? null),
                'status' => $snapshot['status'] ?? null,
            ],
            AuditEvent::SUBJECT_PAYOUT => [
                'employee_id' => $snapshot['employee_id'] ?? null,
                'employee_name' => $snapshot['employee_name'] ?? null,
                'payroll_id' => $snapshot['payroll_id'] ?? null,
                'payroll_period' => $snapshot['payroll_period'] ?? null,
                'amount' => $this->nullableMoney($snapshot['amount'] ?? null),
                'method' => $snapshot['method'] ?? null,
            ],
            AuditEvent::SUBJECT_MEMBER => [
                'member_id' => $snapshot['id'] ?? null,
                'member_name' => $snapshot['name'] ?? null,
                'status' => $snapshot['status'] ?? null,
                'email' => $snapshot['email'] ?? null,
            ],
            AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION => [
                'member_id' => $snapshot['member_id'] ?? null,
                'member_name' => $snapshot['member_name'] ?? null,
                'rate_plan_id' => $snapshot['rate_plan_id'] ?? null,
                'rate_plan_name' => $snapshot['rate_plan_name'] ?? null,
                'status' => $snapshot['status'] ?? null,
                'start_date' => $snapshot['start_date'] ?? null,
                'end_date' => $snapshot['end_date'] ?? null,
                'manager_id' => $snapshot['manager_id'] ?? null,
                'manager_name' => $snapshot['manager_name'] ?? null,
            ],
            AuditEvent::SUBJECT_MEMBER_PT_PACKAGE => [
                'member_id' => $snapshot['member_id'] ?? null,
                'member_name' => $snapshot['member_name'] ?? null,
                'pt_product_id' => $snapshot['pt_product_id'] ?? null,
                'product_name' => $snapshot['product_name'] ?? null,
                'coach_id' => $snapshot['coach_id'] ?? null,
                'coach_name' => $snapshot['coach_name'] ?? null,
                'total_sessions' => $snapshot['total_sessions'] ?? null,
                'remaining_sessions' => $snapshot['remaining_sessions'] ?? null,
                'assigned_at' => $snapshot['assigned_at'] ?? null,
            ],
            AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE => [
                'member_id' => $snapshot['member_id'] ?? null,
                'member_name' => $snapshot['member_name'] ?? null,
                'package_id' => $snapshot['package_id'] ?? null,
                'sessions_used' => $snapshot['sessions_used'] ?? null,
                'remaining_sessions' => $snapshot['remaining_sessions'] ?? null,
                'used_at' => $snapshot['used_at'] ?? null,
                'coach_id' => $snapshot['coach_id'] ?? null,
                'coach_name' => $snapshot['coach_name'] ?? null,
            ],
            AuditEvent::SUBJECT_ATTENDANCE => [
                'user_id' => $snapshot['user_id'] ?? null,
                'walk_in_id' => $snapshot['walk_in_id'] ?? null,
                'name' => $snapshot['name'] ?? null,
                'attendee_type' => $snapshot['attendee_type'] ?? null,
                'checked_in_at' => $snapshot['checked_in_at'] ?? null,
                'checked_out_at' => $snapshot['checked_out_at'] ?? null,
                'source' => $snapshot['source'] ?? null,
                'source_device_serial' => $snapshot['source_device_serial'] ?? null,
            ],
            AuditEvent::SUBJECT_WALK_IN => [
                'walk_in_name' => $snapshot['name'] ?? null,
                'rate_plan_name' => $snapshot['rate_plan_name'] ?? null,
                'amount_paid' => $this->nullableMoney($snapshot['amount_paid'] ?? null),
                'payment_method' => $snapshot['payment_method'] ?? null,
                'served_by' => $snapshot['served_by'] ?? null,
            ],
            AuditEvent::SUBJECT_SALE_TRANSACTION => [
                'sale_type' => $snapshot['type'] ?? null,
                'customer_name' => $snapshot['customer_name'] ?? null,
                'item_name' => $snapshot['item_name'] ?? null,
                'payment_method' => $snapshot['payment_method'] ?? null,
                'member_id' => $snapshot['member_id'] ?? null,
                'total' => $this->nullableMoney($snapshot['total'] ?? null),
            ],
            AuditEvent::SUBJECT_INVENTORY_ITEM => [
                'inventory_name' => $snapshot['name'] ?? null,
                'category_name' => $snapshot['category_name'] ?? null,
                'quantity' => $this->nullableNumber($snapshot['quantity'] ?? null),
                'unit' => $snapshot['unit'] ?? null,
                'deducted_quantity' => $this->nullableNumber($snapshot['deducted_quantity'] ?? null),
                'remaining_quantity' => $this->nullableNumber($snapshot['remaining_quantity'] ?? null),
            ],
            AuditEvent::SUBJECT_RATE_PLAN => [
                'rate_plan_name' => $snapshot['name'] ?? null,
                'duration_days' => $snapshot['duration_days'] ?? null,
                'price' => $this->nullableMoney($snapshot['price'] ?? null),
            ],
            AuditEvent::SUBJECT_PT_PRODUCT => [
                'pt_product_name' => $snapshot['name'] ?? null,
                'session_count' => $snapshot['session_count'] ?? null,
                'price' => $this->nullableMoney($snapshot['price'] ?? null),
            ],
            default => [],
        };

        return $this->cleanMetadata([...$base, ...$metadata]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function businessProfileMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'The business profile');

        return match ($event) {
            'photo_added' => 'A new photo was added for '.$name.'.',
            'photo_removed' => 'A business photo was removed for '.$name.'.',
            default => $name.' was updated.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function cashLedgerMessage(string $event, array $snapshot): string
    {
        $mode = ! empty($snapshot['is_system']) ? 'system' : 'manual';
        $direction = ($snapshot['direction'] ?? CashLedgerEntry::DIRECTION_IN) === CashLedgerEntry::DIRECTION_OUT
            ? 'cash out'
            : 'cash in';
        $title = (string) ($snapshot['title'] ?? 'Untitled entry');
        $amount = $this->currency((float) ($snapshot['amount'] ?? 0));

        return match ($event) {
            'created' => sprintf('A %s %s entry for %s titled "%s" was created.', $mode, $direction, $amount, $title),
            'deleted' => sprintf('The %s %s entry for %s titled "%s" was deleted.', $mode, $direction, $amount, $title),
            'restored' => sprintf('The %s %s entry for %s titled "%s" was restored.', $mode, $direction, $amount, $title),
            default => sprintf('The %s %s entry for %s titled "%s" was updated.', $mode, $direction, $amount, $title),
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function employeeMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'The employee');
        $roles = $snapshot['role_names'] ?? [];
        $roleSuffix = is_array($roles) && $roles !== [] ? ' Roles: '.implode(', ', $roles).'.' : '';

        return match ($event) {
            'created' => $name.' was added as an employee.'.$roleSuffix,
            'deleted' => $name.' was deleted from the employee list.',
            'restored' => $name.' was restored to the employee list.'.$roleSuffix,
            'biometric_enrollment_started' => 'Fingerprint enrollment started for '.$name.'.',
            'biometric_enrolled' => 'A fingerprint was enrolled for '.$name.'.',
            'biometric_removed' => 'The enrolled fingerprint for '.$name.' was removed.',
            'biometric_failed' => 'Fingerprint enrollment failed for '.$name.'.',
            default => $name.' was updated.'.$roleSuffix,
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function payrollMessage(string $event, array $snapshot): string
    {
        $employeeName = (string) ($snapshot['employee_name'] ?? 'Unknown Employee');
        $period = $this->periodLabel($snapshot['period_start'] ?? null, $snapshot['period_end'] ?? null);
        $netAmount = $this->currency((float) ($snapshot['net_amount'] ?? 0));

        return match ($event) {
            'created' => 'A payroll for '.$employeeName.' covering '.$period.' with net pay of '.$netAmount.' was created.',
            'approved' => 'The payroll for '.$employeeName.' covering '.$period.' was approved.',
            'cancelled' => 'The payroll for '.$employeeName.' covering '.$period.' was cancelled.',
            default => 'The payroll for '.$employeeName.' covering '.$period.' was updated.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function payoutMessage(array $snapshot): string
    {
        $employeeName = (string) ($snapshot['employee_name'] ?? 'Unknown Employee');
        $amount = $this->currency((float) ($snapshot['amount'] ?? 0));
        $method = $this->paymentMethodLabel((string) ($snapshot['method'] ?? ''));
        $period = (string) ($snapshot['payroll_period'] ?? 'the linked payroll');

        return 'A '.$method.' payout of '.$amount.' was recorded for '.$employeeName.' on '.$period.'.';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function memberMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'The member');

        return match ($event) {
            'created' => $name.' was added as a member.',
            default => $name.' was updated.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function membershipMessage(string $event, array $snapshot): string
    {
        $memberName = (string) ($snapshot['member_name'] ?? 'Unknown Member');
        $planName = (string) ($snapshot['rate_plan_name'] ?? 'membership plan');
        $managerName = (string) ($snapshot['manager_name'] ?? 'No manager');
        $status = (string) ($snapshot['status'] ?? 'unknown');

        return match ($event) {
            'created' => $memberName.' received a new '.$planName.' membership.',
            'plan_changed' => $memberName."'s membership was changed to ".$planName.'.',
            'status_updated' => $memberName."'s membership status was updated to ".$status.'.',
            'manager_assigned' => $managerName.' was assigned to '.$memberName."'s membership.",
            default => $memberName."'s membership was updated.",
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function ptPackageMessage(string $event, array $snapshot): string
    {
        $memberName = (string) ($snapshot['member_name'] ?? 'Unknown Member');
        $productName = (string) ($snapshot['product_name'] ?? 'PT package');
        $sessions = (int) ($snapshot['total_sessions'] ?? 0);

        return match ($event) {
            'assigned' => sprintf('%s was assigned the %s package with %d sessions.', $memberName, $productName, $sessions),
            default => sprintf('A %s package with %d sessions was created for %s.', $productName, $sessions, $memberName),
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function ptSessionUsageMessage(array $snapshot): string
    {
        $memberName = (string) ($snapshot['member_name'] ?? 'Unknown Member');
        $sessionsUsed = (int) ($snapshot['sessions_used'] ?? 0);
        $remainingSessions = (int) ($snapshot['remaining_sessions'] ?? 0);

        return sprintf(
            '%d PT session(s) were recorded for %s. Remaining sessions: %d.',
            $sessionsUsed,
            $memberName,
            $remainingSessions
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function attendanceMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'This attendee');

        return match ($event) {
            'checked_in' => $name.' was checked in.',
            'checked_out' => $name.' was checked out.',
            'deleted' => $name."'s attendance record was deleted.",
            'restored' => $name."'s attendance record was restored.",
            default => $name."'s attendance record was updated.",
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function walkInMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'This walk-in');
        $amount = $this->currency((float) ($snapshot['amount_paid'] ?? 0));

        return match ($event) {
            'created' => $name.' was recorded as a walk-in payment worth '.$amount.'.',
            'deleted' => $name."'s walk-in record was deleted.",
            'restored' => $name."'s walk-in record was restored.",
            default => $name."'s walk-in record was updated.",
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function saleMessage(array $snapshot): string
    {
        $saleType = $this->saleTypeLabel((string) ($snapshot['type'] ?? ''));
        $itemName = (string) ($snapshot['item_name'] ?? 'item');
        $customerName = (string) ($snapshot['customer_name'] ?? 'Unknown Customer');
        $total = $this->currency((float) ($snapshot['total'] ?? 0));

        return sprintf('A %s sale for %s worth %s was recorded for %s.', $saleType, $itemName, $total, $customerName);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     */
    private function inventoryMessage(string $event, array $snapshot, array $metadata): string
    {
        $name = (string) ($snapshot['name'] ?? 'Inventory item');
        $quantity = $this->number((float) ($snapshot['quantity'] ?? 0));
        $unit = (string) ($snapshot['unit'] ?? 'unit');

        return match ($event) {
            'created' => sprintf('%s was added to inventory with %s %s on hand.', $name, $quantity, $unit),
            'deleted' => $name.' was deleted from inventory.',
            'restored' => sprintf('%s was restored to inventory with %s %s on hand.', $name, $quantity, $unit),
            'stock_deducted' => sprintf(
                '%s %s of %s were deducted from inventory. Remaining stock: %s %s.',
                $this->number((float) ($metadata['deducted_quantity'] ?? $snapshot['deducted_quantity'] ?? 0)),
                $unit,
                $name,
                $this->number((float) ($metadata['remaining_quantity'] ?? $snapshot['remaining_quantity'] ?? 0)),
                $unit,
            ),
            default => $name.' was updated in inventory.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function ratePlanMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'Rate plan');
        $price = $this->currency((float) ($snapshot['price'] ?? 0));

        return match ($event) {
            'configured' => $name.' was configured at '.$price.'.',
            'removed' => $name.' pricing was removed.',
            default => $name.' pricing was updated to '.$price.'.',
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function ptProductMessage(string $event, array $snapshot): string
    {
        $name = (string) ($snapshot['name'] ?? 'PT product');
        $price = $this->currency((float) ($snapshot['price'] ?? 0));

        return match ($event) {
            'configured' => $name.' was configured at '.$price.'.',
            'removed' => $name.' pricing was removed.',
            default => $name.' pricing was updated to '.$price.'.',
        };
    }

    private function periodLabel(mixed $periodStart, mixed $periodEnd): string
    {
        if ($periodStart && $periodEnd) {
            return (string) $periodStart.' - '.(string) $periodEnd;
        }

        return 'the selected payroll period';
    }

    private function saleTypeLabel(string $saleType): string
    {
        return match ($saleType) {
            SaleTransaction::TYPE_INVENTORY => 'inventory',
            SaleTransaction::TYPE_MEMBERSHIP => 'membership',
            SaleTransaction::TYPE_PT_PACKAGE => 'PT package',
            SaleTransaction::TYPE_WALK_IN => 'walk-in',
            default => 'sale',
        };
    }

    private function paymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            SaleTransaction::PAYMENT_METHOD_BANK_TRANSFER => 'bank transfer',
            SaleTransaction::PAYMENT_METHOD_ONLINE_PAYMENT => 'online payment',
            default => str($paymentMethod)->replace('_', ' ')->lower()->toString(),
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function cleanMetadata(array $metadata): array
    {
        return array_filter($metadata, static function (mixed $value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null && $value !== '';
        });
    }

    private function currency(float $amount): string
    {
        return 'PHP '.number_format($amount, 2);
    }

    private function number(float $value): string
    {
        return number_format($value, floor($value) === $value ? 0 : 2, '.', ',');
    }

    private function nullableMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
