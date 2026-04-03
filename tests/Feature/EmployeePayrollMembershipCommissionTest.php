<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollMembershipCommissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('member');
        $permission = Permission::findOrCreate('manage employees');

        $managerRole->givePermissionTo($permission);
    }

    public function test_earned_membership_commissions_are_added_to_draft_payroll_and_linked(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Mia');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Ana');
        $ratePlan = $this->createRatePlan('Monthly', 30);

        $subscription = MemberSubscription::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 2000,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 8,
            'manager_commission_amount' => 160,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-02 09:30:00',
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('membership_commission_amount', 160)
            ->assertJsonPath('membership_commission_items.0.subscription_id', $subscription->id)
            ->assertJsonPath('membership_commission_items.0.commission_amount', 160)
            ->assertJsonPath('total_earnings', 4160)
            ->assertJsonPath('net_amount', 4160);

        $payrollId = $response->json('id');

        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'employee_id' => $manager->id,
            'membership_commission_amount' => 160,
            'net_amount' => 4160,
        ]);

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'commission_payroll_id' => $payrollId,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
        ]);
    }

    public function test_canceling_draft_payroll_releases_linked_membership_commissions(): void
    {
        $branch = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Ben');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Lea');
        $ratePlan = $this->createRatePlan('Quarterly', 90);

        $subscription = MemberSubscription::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 3000,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 6,
            'manager_commission_amount' => 180,
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-29',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-03 10:00:00',
        ]);

        $payrollResponse = $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 3200,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated();

        $payrollId = $payrollResponse->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls/{$payrollId}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'canceled');

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'commission_payroll_id' => null,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
        ]);
    }

    public function test_full_payroll_payout_marks_linked_membership_commissions_as_paid(): void
    {
        $branch = $this->createBranch('Daet');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Pia');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Josh');
        $ratePlan = $this->createRatePlan('Annual', 365);

        $subscription = MemberSubscription::create([
            'user_id' => $member->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 12000,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 5,
            'manager_commission_amount' => 600,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-04 11:00:00',
        ]);

        $payrollResponse = $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 5000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated();

        $payrollId = $payrollResponse->json('id');
        $netAmount = $payrollResponse->json('net_amount');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls/{$payrollId}/approve")
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls/{$payrollId}/payouts", [
                'amount' => $netAmount,
                'method' => 'cash',
                'paid_at' => '2026-04-16 09:00:00',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'commission_payroll_id' => $payrollId,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_PAID,
        ]);
    }

    public function test_multi_branch_payroll_only_links_membership_commissions_from_the_payroll_branch(): void
    {
        $branchA = $this->createBranch('Naga');
        $branchB = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$branchA->id, $branchB->id], 'Manager Kai');
        $memberA = $this->createUserWithRole('member', [$branchA->id], 'Member A');
        $memberB = $this->createUserWithRole('member', [$branchB->id], 'Member B');
        $ratePlanA = $this->createRatePlan('Plan A', 30);
        $ratePlanB = $this->createRatePlan('Plan B', 60);

        $branchASubscription = MemberSubscription::create([
            'user_id' => $memberA->id,
            'branch_id' => $branchA->id,
            'rate_plan_id' => $ratePlanA->id,
            'sold_price' => 2500,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 8,
            'manager_commission_amount' => 200,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-05 08:30:00',
        ]);

        $branchBSubscription = MemberSubscription::create([
            'user_id' => $memberB->id,
            'branch_id' => $branchB->id,
            'rate_plan_id' => $ratePlanB->id,
            'sold_price' => 4000,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 5,
            'manager_commission_amount' => 200,
            'start_date' => '2026-04-01',
            'end_date' => '2026-05-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-06 09:45:00',
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4500,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('membership_commission_amount', 200)
            ->assertJsonCount(1, 'membership_commission_items')
            ->assertJsonPath('membership_commission_items.0.subscription_id', $branchASubscription->id);

        $payrollId = $response->json('id');

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $branchASubscription->id,
            'commission_payroll_id' => $payrollId,
        ]);

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $branchBSubscription->id,
            'commission_payroll_id' => null,
        ]);
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
            'description' => $name.' membership',
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
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
