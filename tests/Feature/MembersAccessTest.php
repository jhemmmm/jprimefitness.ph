<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
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
        Role::findOrCreate('super admin');
    }

    public function test_manager_cannot_view_attendance_for_member_from_another_branch(): void
    {
        $manager = $this->createUserWithRole('manager', [$this->createBranch('Manager Branch')->id]);
        $member = $this->createMember([$this->createBranch('Other Branch')->id]);

        Attendance::create([
            'branch_id' => $member->branches()->firstOrFail()->id,
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => now()->subHour(),
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/members/{$member->id}/attendance")
            ->assertNotFound();
    }

    public function test_manager_cannot_change_membership_for_member_from_another_branch(): void
    {
        $manager = $this->createUserWithRole('manager', [$this->createBranch('Manager Branch')->id]);
        $member = $this->createMember([$this->createBranch('Other Branch')->id]);
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
            ->assertNotFound();

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'rate_plan_id' => $currentPlan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_manager_cannot_update_membership_status_for_member_from_another_branch(): void
    {
        $manager = $this->createUserWithRole('manager', [$this->createBranch('Manager Branch')->id]);
        $member = $this->createMember([$this->createBranch('Other Branch')->id]);
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
            ->assertNotFound();

        $this->assertDatabaseHas('member_subscriptions', [
            'id' => $subscription->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_changing_a_paused_membership_plan_keeps_it_paused(): void
    {
        $branch = $this->createBranch('Shared Branch');
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $member = $this->createMember([$branch->id]);
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

    public function test_manager_cannot_create_member_in_another_branch(): void
    {
        $manager = $this->createUserWithRole('manager', [$this->createBranch('Manager Branch')->id]);
        $otherBranch = $this->createBranch('Other Branch');
        $plan = $this->createRatePlan('Monthly', 30);

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'Unauthorized Member',
                'email' => 'unauthorized-member@example.com',
                'password' => 'password123',
                'branch_ids' => [$otherBranch->id],
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $plan->id,
                'start_date' => '2026-04-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized-member@example.com',
        ]);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'city' => 'Naga City',
        ]);
    }

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds = []): User
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createMember(array $branchIds): User
    {
        return $this->createUserWithRole('member', $branchIds);
    }
}
