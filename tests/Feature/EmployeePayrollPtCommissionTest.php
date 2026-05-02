<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\MemberPtPackage;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollPtCommissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        $coachRole = Role::findOrCreate('coach');
        $memberRole = Role::findOrCreate('member');
        $permission = Permission::findOrCreate('manage employees');

        $managerRole->givePermissionTo($permission);
        $coachRole->givePermissionTo($permission);
        $memberRole->givePermissionTo($permission);
    }

    public function test_earned_pt_commissions_are_added_to_draft_payroll_and_linked(): void
    {
        $this->setBusinessProfile('Naga');
        $product = $this->createPtProduct('12 Sessions', 12, 1200, 40);
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', 'Coach Mario');
        $member = $this->createUserWithRole('member', 'Member Ana');

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 1200,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 480,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-15 09:30:00',
            'total_sessions' => 12,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'assigned_at' => '2026-03-01',
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 4500,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('pt_commission_amount', 480)
            ->assertJsonPath('pt_commission_items.0.package_id', $package->id)
            ->assertJsonPath('pt_commission_items.0.commission_amount', 480)
            ->assertJsonPath('total_earnings', 4980)
            ->assertJsonPath('net_amount', 4980);

        $payrollId = $response->json('id');

        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'employee_id' => $coach->id,
            'pt_commission_amount' => 480,
            'net_amount' => 4980,
        ]);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'commission_payroll_id' => $payrollId,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
        ]);
    }

    public function test_canceling_draft_payroll_releases_linked_pt_commissions(): void
    {
        $this->setBusinessProfile('Legazpi');
        $product = $this->createPtProduct('Per Session', 1, 500, 40);
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', 'Coach Ben');
        $member = $this->createUserWithRole('member', 'Member Lea');

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 200,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-14 10:00:00',
            'total_sessions' => 1,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'assigned_at' => '2026-03-01',
            'created_by' => $manager->id,
        ]);

        $payrollResponse = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 2250,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated();

        $payrollId = $payrollResponse->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'canceled');

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'commission_payroll_id' => null,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
        ]);
    }

    public function test_full_payroll_payout_marks_linked_pt_commissions_as_paid(): void
    {
        $this->setBusinessProfile('Daet');
        $product = $this->createPtProduct('8 Sessions', 8, 800, 40);
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', 'Coach Pia');
        $member = $this->createUserWithRole('member', 'Member Josh');

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 800,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 320,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-15 11:00:00',
            'total_sessions' => 8,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'assigned_at' => '2026-03-01',
            'created_by' => $manager->id,
        ]);

        $payrollResponse = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 3000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated();

        $payrollId = $payrollResponse->json('id');
        $netAmount = $payrollResponse->json('net_amount');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/approve")
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/payouts", [
                'amount' => $netAmount,
                'method' => 'cash',
                'paid_at' => '2026-03-16 09:00:00',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'commission_payroll_id' => $payrollId,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_PAID,
        ]);
    }

    public function test_only_earned_commissions_within_the_selected_period_are_attached(): void
    {
        $this->setBusinessProfile('Naga');
        $product = $this->createPtProduct('12 Sessions', 12, 1200, 40);
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', 'Coach Mario');
        $member = $this->createUserWithRole('member', 'Member Ana');

        $inPeriodPackage = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 1200,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 480,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-15 09:30:00',
            'total_sessions' => 12,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'assigned_at' => '2026-03-01',
            'created_by' => $manager->id,
        ]);

        MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 800,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 320,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-20 10:00:00',
            'total_sessions' => 8,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'assigned_at' => '2026-03-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 4500,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('pt_commission_amount', 480)
            ->assertJsonCount(1, 'pt_commission_items')
            ->assertJsonPath('pt_commission_items.0.package_id', $inPeriodPackage->id);
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createPtProduct(string $name, int $sessionCount, float $price, float $commissionRate): PTProduct
    {
        return PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'price' => $price,
            'coach_commission_rate' => $commissionRate,
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 450,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
