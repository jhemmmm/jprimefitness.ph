<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollReportsPageTest extends TestCase
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
    }

    public function test_payroll_reports_page_loads_for_manager_roles(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Payroll Manager');

        $this->actingAs($manager)
            ->get('/panel/reports/payroll')
            ->assertOk()
            ->assertSee('payroll-reports-page', false);
    }

    public function test_payroll_reports_data_returns_summary_and_breakdowns(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Payroll Manager');
        $employeeA = $this->createUserWithRole('staff', [$branch->id], 'Juan Dela Cruz', Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY);
        $employeeB = $this->createUserWithRole('staff', [$branch->id], 'Maria Santos', Branch::PAYROLL_FREQUENCY_MONTHLY);

        $payrollA = Payroll::create([
            'employee_id' => $employeeA->id,
            'branch_id' => $branch->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1000,
            'bonus' => 100,
            'pt_commission_amount' => 200,
            'pt_commission_items' => [],
            'manual_deductions' => 50,
            'cash_advance_deduction' => 100,
            'net_amount' => 1150,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-03-16 09:00:00',
        ]);

        $payrollB = Payroll::create([
            'employee_id' => $employeeB->id,
            'branch_id' => $branch->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'gross_amount' => 2000,
            'bonus' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 2000,
            'status' => Payroll::STATUS_PAID,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-03-31 17:00:00',
        ]);

        Payroll::create([
            'employee_id' => $employeeB->id,
            'branch_id' => $branch->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'gross_amount' => 999,
            'bonus' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 999,
            'status' => Payroll::STATUS_CANCELED,
            'generated_by' => $manager->id,
        ]);

        Payout::create([
            'payroll_id' => $payrollA->id,
            'employee_id' => $employeeA->id,
            'amount' => 500,
            'method' => Payout::METHOD_CASH,
            'reference_number' => null,
            'released_by' => $manager->id,
            'paid_at' => '2026-03-16 10:00:00',
        ]);

        Payout::create([
            'payroll_id' => $payrollB->id,
            'employee_id' => $employeeB->id,
            'amount' => 2000,
            'method' => Payout::METHOD_BANK_TRANSFER,
            'reference_number' => 'BT-123',
            'released_by' => $manager->id,
            'paid_at' => '2026-03-31 18:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/reports/payroll/data?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.branch.id', $branch->id);
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('summary.payroll_count', 2);
        $response->assertJsonPath('summary.gross_payroll', 3000);
        $response->assertJsonPath('summary.total_bonus', 100);
        $response->assertJsonPath('summary.pt_commission', 200);
        $response->assertJsonPath('summary.total_deductions', 150);
        $response->assertJsonPath('summary.net_payroll', 3150);
        $response->assertJsonPath('summary.total_paid', 2500);
        $response->assertJsonPath('summary.outstanding_balance', 650);
        $response->assertJsonPath('status_breakdown.0.status', Payroll::STATUS_APPROVED);
        $response->assertJsonPath('status_breakdown.0.net_payroll', 1150);
        $response->assertJsonPath('status_breakdown.1.status', Payroll::STATUS_PAID);
        $response->assertJsonPath('pay_frequency_breakdown.0.pay_frequency', Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY);
        $response->assertJsonPath('pay_frequency_breakdown.1.pay_frequency', Branch::PAYROLL_FREQUENCY_MONTHLY);
        $response->assertJsonPath('branch_breakdown.0.branch_name', 'Naga');
        $response->assertJsonPath('branch_breakdown.0.total_paid', 2500);
        $response->assertJsonPath('payout_method_breakdown.0.method', Payout::METHOD_BANK_TRANSFER);
        $response->assertJsonPath('payout_method_breakdown.0.total_paid', 2000);
        $response->assertJsonPath('payroll_trend.0.period_end', '2026-03-15');
        $response->assertJsonPath('payroll_trend.1.period_end', '2026-03-31');
        $response->assertJsonPath('recent_payrolls.0.employee_name', 'Maria Santos');
        $response->assertJsonPath('recent_payrolls.0.total_paid', 2000);
    }

    public function test_payroll_reports_can_be_exported_to_csv(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createUserWithRole('staff', [$branch->id], 'Juan Dela Cruz');

        Payroll::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1400,
            'bonus' => 100,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 50,
            'cash_advance_deduction' => 0,
            'net_amount' => 1450,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-03-16 09:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->get('/panel/reports/payroll/export?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Payroll Reports', $content);
        $this->assertStringContainsString('Period End From', $content);
        $this->assertStringContainsString('Payout Scope', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Paid Out To Date', $content);
        $this->assertStringContainsString('Recent Payrolls', $content);
        $this->assertStringContainsString('Juan Dela Cruz', $content);
    }

    public function test_payroll_reports_can_aggregate_all_accessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $outside = $this->createBranch('Outside');
        $manager = $this->createUserWithRole('manager', [$naga->id, $legazpi->id], 'Payroll Manager');
        $outsideManager = $this->createUserWithRole('manager', [$outside->id], 'Outside Manager');
        $employeeA = $this->createUserWithRole('staff', [$naga->id], 'Juan Dela Cruz');
        $employeeB = $this->createUserWithRole('staff', [$legazpi->id], 'Maria Santos');
        $employeeC = $this->createUserWithRole('staff', [$outside->id], 'Hidden Employee');

        Payroll::create([
            'employee_id' => $employeeA->id,
            'branch_id' => $naga->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1000,
            'bonus' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 1000,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
        ]);

        Payroll::create([
            'employee_id' => $employeeB->id,
            'branch_id' => $legazpi->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'gross_amount' => 2000,
            'bonus' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 2000,
            'status' => Payroll::STATUS_PARTIALLY_PAID,
            'generated_by' => $manager->id,
        ]);

        Payroll::create([
            'employee_id' => $employeeC->id,
            'branch_id' => $outside->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'gross_amount' => 9000,
            'bonus' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 9000,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $outsideManager->id,
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/reports/payroll/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.is_all_branches', true);
        $response->assertJsonPath('summary.payroll_count', 2);
        $response->assertJsonPath('summary.net_payroll', 3000);
        $response->assertJsonCount(2, 'branch_breakdown');
        $response->assertJsonMissing(['branch_name' => 'Outside']);
    }

    public function test_payroll_reports_branch_filter_rejects_inaccessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$naga->id], 'Payroll Manager');

        $this->actingAs($manager)
            ->getJson('/panel/reports/payroll/data?branch='.$legazpi->id)
            ->assertForbidden();
    }

    public function test_payroll_reports_forbid_staff_access(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/payroll')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/reports/payroll/data?branch='.$branch->id)
            ->assertForbidden();
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

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds, string $name, ?string $payFrequency = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
            'pay_frequency' => $payFrequency ?? Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
