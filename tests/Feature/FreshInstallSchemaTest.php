<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshInstallSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fresh_install_uses_canonical_single_location_schema(): void
    {
        $this->assertTrue(Schema::hasTable('business_profiles'));
        $this->assertTrue(Schema::hasTable('cash_ledger_entries'));
        $this->assertTrue(Schema::hasTable('member_profiles'));
        $this->assertTrue(Schema::hasTable('member_subscriptions'));

        $this->assertFalse(Schema::hasTable('branches'));
        $this->assertFalse(Schema::hasTable('branch_rate_prices'));
        $this->assertFalse(Schema::hasTable('branch_pt_prices'));
        $this->assertFalse(Schema::hasTable('branch_cash_ledger_entries'));

        $this->assertTableHasColumns('rate_plans', [
            'price',
            'manager_commission_rate',
            'effective_from',
            'effective_until',
        ]);

        $this->assertTableHasColumns('pt_products', [
            'price',
            'coach_commission_rate',
            'effective_from',
            'effective_until',
        ]);

        $this->assertTableHasColumns('users', [
            'daily_rate',
            'pay_frequency',
        ]);

        $this->assertTableHasColumns('member_subscriptions', [
            'sold_price',
            'manager_id',
            'manager_commission_rate',
            'manager_commission_amount',
            'manager_commission_status',
            'manager_commission_earned_at',
            'commission_payroll_id',
            'expiration_notification_sent_for_date',
        ]);

        $this->assertTableHasColumns('member_pt_packages', [
            'sold_price',
            'coach_commission_rate',
            'coach_commission_amount',
            'coach_id',
            'coach_commission_status',
            'coach_commission_earned_at',
            'commission_payroll_id',
        ]);

        $this->assertTableHasColumns('member_pt_session_usages', [
            'coach_id',
        ]);

        $this->assertTableHasColumns('payrolls', [
            'pay_frequency',
            'income_tax',
            'employee_contributions',
            'employer_contributions',
            'pt_commission_amount',
            'pt_commission_items',
            'membership_commission_amount',
            'membership_commission_items',
        ]);

        $this->assertTableHasColumns('cash_advances', [
            'audit_data',
        ]);

        $this->assertTableHasColumns('walk_ins', [
            'payment_method',
        ]);

        $this->assertTableHasColumns('inventory_items', [
            'stock_alert_state',
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
