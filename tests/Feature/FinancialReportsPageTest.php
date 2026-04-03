<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchCashLedgerEntry;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\Payroll;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinancialReportsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');
    }

    public function test_financial_reports_page_loads_for_panel_users(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Ana');

        $this->actingAs($manager)
            ->get('/panel/reports/financial')
            ->assertOk()
            ->assertSee('financial-reports-page', false);
    }

    public function test_financial_reports_data_returns_profitability_breakdown(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Ana');
        $coach = $this->createUserWithRole('coach', [$branch->id], 'Coach Abe');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Joy');
        $employee = $this->createUserWithRole('staff', [$branch->id], 'Payroll Ana');
        $ratePlan = $this->createRatePlan('Monthly', 30);
        $ptProduct = PTProduct::create([
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'is_active' => true,
        ]);

        SaleTransaction::create([
            'branch_id' => $branch->id,
            'member_id' => null,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 2000,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-03-02 09:00:00',
            'customer_name' => 'Counter Sale',
            'item_name' => 'Drinks',
            'details' => [],
        ]);

        SaleTransaction::create([
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-03-03 10:00:00',
            'customer_name' => $member->name,
            'item_name' => 'Monthly Membership',
            'details' => [],
        ]);

        MemberSubscription::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 1500,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 10,
            'manager_commission_amount' => 150,
            'start_date' => '2026-03-03',
            'end_date' => '2026-04-01',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-03-04 09:00:00',
        ]);

        MemberPtPackage::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
            'pt_product_id' => $ptProduct->id,
            'sold_price' => 1000,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 400,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 0,
            'assigned_at' => '2026-03-01',
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-06 12:00:00',
            'created_by' => $manager->id,
        ]);

        Payroll::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1000,
            'bonus' => 100,
            'pt_commission_amount' => 400,
            'pt_commission_items' => [],
            'manual_deductions' => 50,
            'cash_advance_deduction' => 0,
            'net_amount' => 1450,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
        ]);

        BranchCashLedgerEntry::create([
            'branch_id' => $branch->id,
            'entry_type' => BranchCashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
            'direction' => BranchCashLedgerEntry::DIRECTION_OUT,
            'amount' => 300,
            'occurred_at' => '2026-03-07 08:00:00',
            'title' => 'Utilities',
            'description' => 'Electricity bill',
            'metadata' => [],
            'is_system' => false,
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/reports/financial/data?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.branch.id', $branch->id);
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('summary.gross_revenue', 3500);
        $response->assertJsonPath('summary.pt_commission', 400);
        $response->assertJsonPath('summary.membership_commission', 150);
        $response->assertJsonPath('summary.adjusted_revenue', 2950);
        $response->assertJsonPath('summary.payroll_wages', 1050);
        $response->assertJsonPath('summary.other_operating_expenses', 300);
        $response->assertJsonPath('summary.net_profit', 1600);
        $response->assertJsonPath('expense_breakdown.1.key', 'membership_commission');
        $response->assertJsonPath('expense_breakdown.1.amount', 150);
        $response->assertJsonPath('revenue_breakdown.0.type', SaleTransaction::TYPE_INVENTORY);
        $response->assertJsonPath('revenue_breakdown.0.total_sales', 2000);
        $response->assertJsonPath('revenue_breakdown.1.type', SaleTransaction::TYPE_MEMBERSHIP);
        $response->assertJsonPath('revenue_breakdown.1.total_sales', 1500);
        $response->assertJsonPath('operating_expense_categories.0.title', 'Utilities');
        $response->assertJsonPath('operating_expense_categories.0.total_amount', 300);
        $response->assertJsonPath('recent_operating_expenses.0.title', 'Utilities');
    }

    public function test_financial_reports_can_be_exported_to_csv(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Ana');

        SaleTransaction::create([
            'branch_id' => $branch->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 850,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-03-05 09:00:00',
            'customer_name' => 'Counter Sale',
            'item_name' => 'Bottled Water',
            'details' => [],
        ]);

        $response = $this->actingAs($manager)
            ->get('/panel/reports/financial/export?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Financial Reports', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Revenue Breakdown', $content);
        $this->assertStringContainsString('Gross Revenue', $content);
        $this->assertStringContainsString('Membership Commission', $content);
        $this->assertStringContainsString('850', $content);
    }

    public function test_financial_reports_can_aggregate_all_accessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $outside = $this->createBranch('Outside');
        $manager = $this->createUserWithRole('manager', [$naga->id, $legazpi->id], 'Manager Ana');
        $outsideStaff = $this->createUserWithRole('staff', [$outside->id], 'Staff Bea');

        SaleTransaction::create([
            'branch_id' => $naga->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1000,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-03-10 10:00:00',
        ]);

        SaleTransaction::create([
            'branch_id' => $legazpi->id,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CARD,
            'processed_by' => $manager->id,
            'sold_at' => '2026-03-11 11:00:00',
        ]);

        SaleTransaction::create([
            'branch_id' => $outside->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 9000,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $outsideStaff->id,
            'sold_at' => '2026-03-12 12:00:00',
        ]);

        BranchCashLedgerEntry::create([
            'branch_id' => $legazpi->id,
            'entry_type' => BranchCashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
            'direction' => BranchCashLedgerEntry::DIRECTION_OUT,
            'amount' => 250,
            'occurred_at' => '2026-03-13 09:00:00',
            'title' => 'Supplies',
            'is_system' => false,
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/reports/financial/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.is_all_branches', true);
        $response->assertJsonPath('summary.gross_revenue', 1500);
        $response->assertJsonPath('summary.other_operating_expenses', 250);
        $response->assertJsonPath('summary.net_profit', 1250);
    }

    public function test_financial_reports_branch_filter_rejects_inaccessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$naga->id], 'Manager Ana');

        $this->actingAs($manager)
            ->getJson('/panel/reports/financial/data?branch='.$legazpi->id)
            ->assertForbidden();
    }

    public function test_financial_reports_forbid_staff_access(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/financial')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/reports/financial/data?branch='.$branch->id)
            ->assertForbidden();
    }

    public function test_financial_reports_include_walk_ins_logged_outside_pos(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Ana');
        $outsideBranch = $this->createBranch('Outside');
        $outsideManager = $this->createUserWithRole('manager', [$outsideBranch->id], 'Outside Manager');

        WalkIn::create([
            'branch_id' => $branch->id,
            'served_by' => $manager->id,
            'name' => 'Direct Walk-in',
            'amount_paid' => 350,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'visited_at' => '2026-03-05 08:00:00',
        ]);

        $posWalkIn = WalkIn::create([
            'branch_id' => $branch->id,
            'served_by' => $manager->id,
            'name' => 'POS Walk-in',
            'amount_paid' => 200,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'visited_at' => '2026-03-06 08:00:00',
        ]);

        SaleTransaction::create([
            'branch_id' => $branch->id,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 200,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-03-06 08:00:00',
            'details' => [
                'walk_in_id' => $posWalkIn->id,
            ],
        ]);

        WalkIn::create([
            'branch_id' => $outsideBranch->id,
            'served_by' => $outsideManager->id,
            'name' => 'Hidden Walk-in',
            'amount_paid' => 999,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'visited_at' => '2026-03-07 08:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/reports/financial/data?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('summary.gross_revenue', 550);
        $response->assertJsonPath('revenue_breakdown.3.type', SaleTransaction::TYPE_WALK_IN);
        $response->assertJsonPath('revenue_breakdown.3.transaction_count', 2);
        $response->assertJsonPath('revenue_breakdown.3.total_sales', 550);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => Branch::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
        ]);
    }

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'description' => $name.' description',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
