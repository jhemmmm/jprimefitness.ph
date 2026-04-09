<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MembersAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('manager');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');
        Role::findOrCreate('super admin');
    }

    public function test_manager_can_view_attendance_for_a_member_in_the_single_location(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => now()->subHour(),
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/members/{$member->id}/attendance")
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('records.data.0.name', $member->name);
    }

    public function test_manager_can_view_member_details(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();

        $this->actingAs($manager)
            ->get("/panel/members/{$member->id}")
            ->assertOk()
            ->assertSee('member-detail-page', false)
            ->assertSeeText($member->name);
    }

    public function test_manager_can_change_membership_for_a_member(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $currentPlan = $this->createRatePlan('Current Plan', 30);
        $replacementPlan = $this->createRatePlan('Replacement Plan', 90);

        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $currentPlan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership", [
                'rate_plan_id' => $replacementPlan->id,
                'start_date' => '2026-04-01',
            ])
            ->assertOk()
            ->assertJsonPath('member_subscriptions.0.rate_plan.id', $replacementPlan->id)
            ->assertJsonPath('member_subscriptions.0.status', MemberSubscription::STATUS_ACTIVE);

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'status' => MemberSubscription::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('member_subscriptions', [
            'user_id' => $member->id,
            'rate_plan_id' => $replacementPlan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_manager_can_update_membership_status_for_a_member(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/status", [
                'status' => MemberSubscription::STATUS_PAUSED,
            ])
            ->assertOk()
            ->assertJsonPath('member_subscriptions.0.status', MemberSubscription::STATUS_PAUSED);

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'status' => MemberSubscription::STATUS_PAUSED,
        ]);
    }

    public function test_changing_a_paused_membership_plan_keeps_it_paused(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-30',
            'status' => MemberSubscription::STATUS_PAUSED,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership", [
                'rate_plan_id' => $plan->id,
                'start_date' => '2026-04-15',
            ])
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(MemberSubscription::STATUS_PAUSED, $subscription->status);
        $this->assertSame('2026-04-15', $subscription->start_date->toDateString());
        $this->assertSame('2026-05-14', $subscription->end_date?->toDateString());
        $this->assertSame(1, $member->memberSubscriptions()->count());
    }

    public function test_manager_cannot_change_membership_when_current_membership_commission_is_locked(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $currentPlan = $this->createRatePlan('Current Plan', 30);
        $replacementPlan = $this->createRatePlan('Replacement Plan', 90);

        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $currentPlan->id,
            'sold_price' => 2000,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 8,
            'manager_commission_amount' => 160,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-02 10:00:00',
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership", [
                'rate_plan_id' => $replacementPlan->id,
                'start_date' => '2026-04-15',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This membership sale commission is already earned and pending payroll.');

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'rate_plan_id' => $currentPlan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_manager_cannot_update_membership_status_when_current_membership_commission_is_locked(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2500,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 10,
            'manager_commission_amount' => 250,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-03 11:30:00',
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/status", [
                'status' => MemberSubscription::STATUS_PAUSED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This membership sale commission is already earned and pending payroll.');

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_manager_can_assign_themselves_to_an_unassigned_membership_commission(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2000,
            'manager_commission_rate' => 8,
            'manager_commission_amount' => 160,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_UNASSIGNED,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/manager", [
                'manager_id' => $manager->id,
            ])
            ->assertOk()
            ->assertJsonPath('member_subscriptions.0.manager.id', $manager->id)
            ->assertJsonPath('member_subscriptions.0.commission_summary.status', MemberSubscription::COMMISSION_STATUS_EARNED);

        $subscription->refresh();

        $this->assertSame($manager->id, $subscription->manager_id);
        $this->assertSame(MemberSubscription::COMMISSION_STATUS_EARNED, $subscription->manager_commission_status);
        $this->assertNotNull($subscription->manager_commission_earned_at);
    }

    public function test_manager_cannot_assign_membership_commission_to_another_manager(): void
    {
        $manager = $this->createUserWithRole('manager');
        $otherManager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2000,
            'manager_commission_rate' => 8,
            'manager_commission_amount' => 160,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_UNASSIGNED,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/manager", [
                'manager_id' => $otherManager->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Managers may only assign membership commissions to themselves.');
    }

    public function test_manager_cannot_assign_membership_commission_without_an_assignable_snapshot(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2000,
            'manager_commission_rate' => 0,
            'manager_commission_amount' => 0,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_UNASSIGNED,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/manager", [
                'manager_id' => $manager->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This membership sale does not have an assignable commission snapshot yet.');
    }

    public function test_member_detail_page_includes_membership_commission_summary_and_action_state(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2200,
            'manager_id' => $manager->id,
            'manager_commission_rate' => 7.5,
            'manager_commission_amount' => 165,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_EARNED,
            'manager_commission_earned_at' => '2026-04-04 09:00:00',
        ]);

        $this->actingAs($manager)
            ->get("/panel/members/{$member->id}")
            ->assertOk()
            ->assertSee('"commission_summary":{"is_tracked":true,"is_locked":true', false)
            ->assertSee('"action_state":{"is_locked":true,"can_change_plan":false,"can_change_status":false', false)
            ->assertSee('This membership sale commission is already earned and pending payroll.', false);
    }

    public function test_member_detail_page_includes_assign_manager_state_for_unassigned_commission(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2200,
            'manager_commission_rate' => 7.5,
            'manager_commission_amount' => 165,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_UNASSIGNED,
        ]);

        $this->actingAs($manager)
            ->get("/panel/members/{$member->id}")
            ->assertOk()
            ->assertSee('"available_managers":[{"id":'.$manager->id, false)
            ->assertSee('"commission_summary":{"is_tracked":true,"is_locked":false,"lock_reason":null,"is_assignable":true', false)
            ->assertSee('"action_state":{"is_locked":false,"can_change_plan":true,"can_change_status":true,"can_assign_manager":true', false);
    }

    public function test_manager_can_create_member_without_branch_assignment(): void
    {
        $manager = $this->createUserWithRole('manager');
        $plan = $this->createRatePlan('Monthly', 30);

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'New Member',
                'email' => 'new-member@example.com',
                'password' => 'password123',
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $plan->id,
                'start_date' => '2026-04-01',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'New Member')
            ->assertJsonPath('member_subscriptions.0.rate_plan.id', $plan->id);

        $member = User::query()->where('email', 'new-member@example.com')->firstOrFail();

        $this->assertTrue($member->hasRole('member'));
        $this->assertNotNull($member->currentMembership());
    }

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'price' => 1500,
            'manager_commission_rate' => 8,
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createMember(): User
    {
        return $this->createUserWithRole('member');
    }
}
