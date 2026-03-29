<?php

namespace Tests\Feature;

use App\Models\Branch;
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
        $branch = $this->createBranch('Naga');
        $product = $this->attachPtProduct($branch, '12 Sessions', 12, 1200, 40);
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', [$branch->id], 'Coach Mario');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Ana');

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
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
        $branch = $this->createBranch('Legazpi');
        $product = $this->attachPtProduct($branch, 'Per Session', 1, 500, 40);
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', [$branch->id], 'Coach Ben');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Lea');

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
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
        $branch = $this->createBranch('Daet');
        $product = $this->attachPtProduct($branch, '8 Sessions', 8, 800, 40);
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', [$branch->id], 'Coach Pia');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Josh');

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
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

    public function test_multi_branch_payroll_only_links_commissions_from_the_payroll_branch(): void
    {
        $branchA = $this->createBranch('Naga');
        $branchB = $this->createBranch('Legazpi');
        $productA = $this->attachPtProduct($branchA, '12 Sessions', 12, 1200, 40);
        $productB = $this->attachPtProduct($branchB, '8 Sessions', 8, 800, 40);
        $manager = $this->createUserWithRole('manager', [$branchA->id], 'Payroll Manager');
        $coach = $this->createUserWithRole('coach', [$branchA->id, $branchB->id], 'Coach Mario');
        $memberA = $this->createUserWithRole('member', [$branchA->id], 'Member Ana');
        $memberB = $this->createUserWithRole('member', [$branchB->id], 'Member Bea');

        $branchAPackage = MemberPtPackage::create([
            'user_id' => $memberA->id,
            'branch_id' => $branchA->id,
            'pt_product_id' => $productA->id,
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

        $branchBPackage = MemberPtPackage::create([
            'user_id' => $memberB->id,
            'branch_id' => $branchB->id,
            'pt_product_id' => $productB->id,
            'coach_id' => $coach->id,
            'sold_price' => 800,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 320,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
            'coach_commission_earned_at' => '2026-03-15 10:00:00',
            'total_sessions' => 8,
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
            ->assertJsonCount(1, 'pt_commission_items')
            ->assertJsonPath('pt_commission_items.0.package_id', $branchAPackage->id);

        $payrollId = $response->json('id');

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $branchAPackage->id,
            'commission_payroll_id' => $payrollId,
        ]);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $branchBPackage->id,
            'commission_payroll_id' => null,
        ]);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => 'PH',
            'city' => 'Naga City',
        ]);
    }

    private function attachPtProduct(Branch $branch, string $name, int $sessionCount, float $price, float $commissionRate): PTProduct
    {
        $product = PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'is_active' => true,
        ]);

        $branch->ptProducts()->attach($product->id, [
            'price' => $price,
            'coach_commission_rate' => $commissionRate,
            'is_active' => true,
        ]);

        return $product;
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 450,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
