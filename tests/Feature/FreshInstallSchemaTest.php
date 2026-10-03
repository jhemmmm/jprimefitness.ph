<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshInstallSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fresh_install_uses_canonical_business_schema(): void
    {
        $this->assertTrue(Schema::hasTable('system_activities'));
        $this->assertTrue(Schema::hasTable('business_profiles'));
        $this->assertTrue(Schema::hasTable('member_profiles'));
        $this->assertTrue(Schema::hasTable('member_subscriptions'));

        $this->assertTrue(Schema::hasTable('cash_drawer_sessions'));
        $this->assertTrue(Schema::hasTable('cash_ledger_entries'));
        $this->assertTableHasColumns('cash_ledger_entries', [
            'payment_method',
        ]);
        $this->assertFalse(Schema::hasTable('walk_ins'));

        $this->assertTableHasColumns('rate_plans', [
            'price',
            'effective_from',
            'effective_until',
        ]);

        $this->assertTableHasColumns('pt_products', [
            'price',
            'effective_from',
            'effective_until',
        ]);

        $this->assertTableHasColumns('users', [
            'deleted_at',
        ]);
        $this->assertFalse(Schema::hasColumn('users', 'daily_rate'));
        $this->assertFalse(Schema::hasColumn('users', 'pay_frequency'));

        $this->assertTableHasColumns('employee_profiles', [
            'user_id',
            'hikvision_employee_no',
            'biometric_status',
            'biometric_fingerprint_id',
            'biometric_enrolled_at',
            'biometric_last_error',
            'daily_rate',
            'pay_frequency',
            'pt_commission_rate',
            'sss_covered',
            'sss_employee_share',
            'sss_employer_share',
            'philhealth_covered',
            'philhealth_employee_share',
            'philhealth_employer_share',
            'pagibig_covered',
            'pagibig_employee_share',
            'pagibig_employer_share',
        ]);
        $this->assertFalse(Schema::hasColumn('employee_profiles', 'sss_monthly_compensation'));
        $this->assertFalse(Schema::hasColumn('employee_profiles', 'philhealth_monthly_basic_salary'));
        $this->assertFalse(Schema::hasColumn('employee_profiles', 'pagibig_monthly_compensation'));

        // Rows written without share amounts (pre-migration data) read as the legal minimum;
        // the migration itself also enrols every existing employee.
        $userId = DB::table('users')->insertGetId([
            'name' => 'Legacy Employee',
            'email' => 'legacy@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_profiles')->insert([
            'user_id' => $userId,
            'hikvision_employee_no' => '00000001',
            'biometric_status' => 'not_enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $userId,
            'sss_employee_share' => 250,
            'sss_employer_share' => 500,
            'philhealth_employee_share' => 250,
            'philhealth_employer_share' => 250,
            'pagibig_employee_share' => 100,
            'pagibig_employer_share' => 100,
        ]);

        $this->assertTableHasColumns('employee_biometric_sessions', [
            'uuid',
            'employee_profile_id',
            'status',
            'fingerprint_id',
            'started_by',
            'completed_at',
            'error_message',
        ]);

        $this->assertTableHasColumns('hikvision_event_logs', [
            'device_serial',
            'event_serial_no',
            'event_type',
            'employee_no',
            'attendance_id',
            'payload',
            'processed_at',
        ]);

        $this->assertTableHasColumns('member_subscriptions', [
            'sold_price',
            'expiration_notification_sent_for_date',
        ]);

        $this->assertTableHasColumns('member_pt_packages', [
            'sold_price',
            'coach_id',
            'sale_transaction_id',
            'cancellation_reason',
            'cancelled_by',
            'cancelled_at',
        ]);

        $this->assertTableHasColumns('member_pt_session_usages', [
            'coach_id',
        ]);

        $this->assertTableHasColumns('payrolls', [
            'pay_frequency',
            'withholding_tax',
            'employee_contributions',
            'employer_contributions',
            'regular_hours',
            'regular_pay_amount',
            'overwork_hours',
            'overwork_pay_amount',
            'commission_amount',
            'commission_details',
        ]);

        $this->assertTableHasColumns('business_profiles', [
            'pay_overwork_hours',
            'payroll_withholding_tax_enabled',
            'payroll_government_contributions_enabled',
        ]);

        $this->assertTableHasColumns('system_activities', [
            'subject_type',
            'subject_id',
            'subject_label',
            'event',
            'title',
            'message',
            'actor_user_id',
            'actor_name',
            'metadata',
            'occurred_at',
        ]);
        $this->assertTableHasColumns('inventory_items', [
            'stock_alert_state',
            'deleted_at',
        ]);

        $this->assertTableHasColumns('attendances', [
            'deleted_at',
            'source',
            'source_device_serial',
        ]);

        $this->assertSame(0, DB::table('inventory_categories')->count());
        $this->assertSame(0, DB::table('business_profiles')->count());
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertTableHasColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn($table, $column),
                sprintf('Failed asserting that table [%s] has column [%s].', $table, $column)
            );
        }
    }
}
