<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\EmployeeProfile;
use App\Models\MemberPtPackage;
use App\Models\Payroll;
use App\Models\PTProduct;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollPtEarningsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('coach');
        Role::findOrCreate('member');

        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));

        BusinessProfile::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'J Prime Fitness',
                'city' => 'Naga',
                'country_code' => BusinessProfile::COUNTRY_PHILIPPINES,
                'timezone' => 'Asia/Manila',
                'payroll_withholding_tax_enabled' => false,
                'payroll_government_contributions_enabled' => false,
            ]
        );
    }

    public function test_suggest_includes_pt_commission_for_plans_sold_in_period(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);

        $this->createPtPackageSale($coach, 3600, '2026-03-05');
        $this->createPtPackageSale($coach, 7200, '2026-03-10');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$coach->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('pt_commission_amount', 4320)
            ->assertJsonCount(2, 'pt_commission_sales')
            ->assertJsonPath('pt_commission_sales.0.sold_price', 3600)
            ->assertJsonPath('pt_commission_sales.0.rate', 40)
            ->assertJsonPath('pt_commission_sales.0.amount', 1440)
            ->assertJsonPath('gross_amount', 4320);
    }

    public function test_payroll_freezes_commission_snapshot_and_keeps_submitted_gross(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);

        $this->createPtPackageSale($coach, 3600, '2026-03-05');

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 4500,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('total_earnings', 4500)
            ->assertJsonPath('net_amount', 4500);

        $this->assertDatabaseHas('payrolls', [
            'id' => $response->json('id'),
            'employee_id' => $coach->id,
            'commission_amount' => 1440,
            'net_amount' => 4500,
        ]);

        $details = \App\Models\Payroll::findOrFail($response->json('id'))->commission_details;

        $this->assertCount(1, $details);
        $this->assertSame('2026-03-05', $details[0]['date']);
        $this->assertSame(1440.0, (float) $details[0]['amount']);
    }

    public function test_cancelled_and_out_of_period_packages_earn_no_commission(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);

        $this->createPtPackageSale($coach, 3600, '2026-03-05', MemberPtPackage::STATUS_CANCELLED);
        $this->createPtPackageSale($coach, 7200, '2026-04-01');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$coach->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('pt_commission_amount', 0)
            ->assertJsonCount(0, 'pt_commission_sales');
    }

    public function test_zero_rate_employee_earns_no_commission(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(0);

        $this->createPtPackageSale($coach, 3600, '2026-03-05');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$coach->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('pt_commission_amount', 0)
            ->assertJsonCount(0, 'pt_commission_sales');
    }

    public function test_unlinked_pt_package_is_not_commissionable(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);
        $member = User::factory()->create();
        $member->assignRole('member');
        $ptProduct = PTProduct::create([
            'name' => 'Legacy Package',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 3600,
            'is_active' => true,
        ]);

        MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $ptProduct->id,
            'sold_price' => 3600,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'assigned_at' => '2026-03-05',
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$coach->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('pt_commission_amount', 0)
            ->assertJsonCount(0, 'pt_commission_sales');
    }

    public function test_saved_commission_snapshot_does_not_shift_when_rate_or_package_changes(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);

        $package = $this->createPtPackageSale($coach, 3600, '2026-03-05');

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1440,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->json('id');

        $coach->employeeProfile->update(['pt_commission_rate' => 10]);
        $package->update(['status' => MemberPtPackage::STATUS_CANCELLED]);

        $payrolls = $this->actingAs($manager)
            ->getJson("/panel/employees/{$coach->id}/payrolls")
            ->assertOk()
            ->json();

        $stored = collect($payrolls)->firstWhere('id', $payrollId);

        $this->assertNotNull($stored);
        $this->assertSame(1440.0, (float) $stored['commission_amount']);

        $frozen = \App\Models\Payroll::findOrFail($payrollId)->commission_details;

        $this->assertCount(1, $frozen);
        $this->assertSame(40.0, (float) $frozen[0]['rate']);
        $this->assertSame(1440.0, (float) $frozen[0]['amount']);
    }

    public function test_stale_pt_commission_snapshot_cannot_be_approved_after_sale_is_voided(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);
        $package = $this->createPtPackageSale($coach, 3600, '2026-03-05');
        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1440,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->json('id');

        $package->saleTransaction()->update([
            'status' => SaleTransaction::STATUS_VOIDED,
            'void_reason' => 'Concurrent refund.',
            'voided_by' => $manager->id,
            'voided_at' => now(),
        ]);
        $package->update(['status' => MemberPtPackage::STATUS_CANCELLED]);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payroll']);

        $this->assertSame(Payroll::STATUS_DRAFT, Payroll::findOrFail($payrollId)->status);
    }

    public function test_completed_pt_sale_commission_snapshot_can_be_approved(): void
    {
        $manager = $this->createManager();
        $coach = $this->createCoach(40);
        $this->createPtPackageSale($coach, 3600, '2026-03-05');
        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1440,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/approve")
            ->assertOk()
            ->assertJsonPath('status', Payroll::STATUS_APPROVED);
    }

    private function createManager(): User
    {
        $manager = User::factory()->create(['name' => 'Payroll Manager']);
        $manager->assignRole('manager');

        return $manager;
    }

    private function createCoach(float $commissionRate): User
    {
        $coach = User::factory()->create(['name' => 'Coach Mario']);
        $coach->assignRole('coach');

        EmployeeProfile::factory()->create([
            'user_id' => $coach->id,
            'daily_rate' => 0,
            'pt_commission_rate' => $commissionRate,
        ]);

        return $coach;
    }

    private function createPtPackageSale(
        User $coach,
        float $soldPrice,
        string $assignedAt,
        string $status = MemberPtPackage::STATUS_ACTIVE
    ): MemberPtPackage {
        $member = User::factory()->create();
        $member->assignRole('member');

        $ptProduct = PTProduct::create([
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => $soldPrice,
            'is_active' => true,
        ]);

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $ptProduct->id,
            'sold_price' => $soldPrice,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'assigned_at' => $assignedAt,
            'status' => $status,
        ]);

        $saleTransaction = SaleTransaction::create([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'status' => SaleTransaction::STATUS_COMPLETED,
            'total' => $soldPrice,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'sold_at' => $assignedAt.' 09:00:00',
            'customer_name' => $member->name,
            'item_name' => $ptProduct->name,
            'details' => [
                'member_pt_package_id' => $package->id,
            ],
        ]);

        $package->update([
            'sale_transaction_id' => $saleTransaction->id,
        ]);

        return $package;
    }
}
