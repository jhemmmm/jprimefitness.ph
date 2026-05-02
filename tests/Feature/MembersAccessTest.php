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

    public function test_member_detail_page_includes_editable_membership_action_state(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $plan = $this->createRatePlan('Monthly', 30);

        $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 2200,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->get("/panel/members/{$member->id}")
            ->assertOk()
            ->assertSee('"action_state":{"is_locked":false,"can_change_plan":true,"can_change_status":true,"reason":null}', false);
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

    public function test_manager_can_reuse_email_from_a_soft_deleted_member(): void
    {
        $manager = $this->createUserWithRole('manager');
        $plan = $this->createRatePlan('Monthly', 30);

        $archivedMember = $this->createMember();
        $archivedMember->update(['email' => 'archived-member@example.com']);
        $archivedMember->delete();

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'New Member',
                'email' => 'archived-member@example.com',
                'password' => 'password123',
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $plan->id,
                'start_date' => '2026-04-01',
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'archived-member@example.com');

        $this->assertSoftDeleted('users', [
            'id' => $archivedMember->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'archived-member@example.com',
            'deleted_at' => null,
        ]);
    }

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'price' => 1500,
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
