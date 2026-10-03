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

    public function test_manager_can_view_attendance_for_a_member(): void
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

    public function test_membership_plans_can_no_longer_be_assigned_from_the_member_pages(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $held = $this->createRatePlan('Monthly', 30);
        $smuggled = $this->createRatePlan('Quarterly', 90);

        // Give the member a real plan, so a swap would be visible if update() still honoured it.
        $existing = $member->memberSubscriptions()->create([
            'rate_plan_id' => $held->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        // A plan is paid for, so it is issued through the POS only. The old endpoint is gone,
        // and the member update endpoint ignores a smuggled rate_plan_id rather than acting on it.
        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership", ['rate_plan_id' => $smuggled->id, 'start_date' => '2026-04-01'])
            ->assertNotFound();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}", [
                'name' => $member->name,
                'email' => $member->email,
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $smuggled->id,
                'start_date' => '2026-04-01',
            ])
            ->assertOk();

        $existing->refresh();

        $this->assertSame($held->id, $existing->rate_plan_id);
        $this->assertSame('2026-03-01', $existing->start_date->toDateString());
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $existing->status);
        $this->assertSame(1, $member->memberSubscriptions()->count());
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

    public function test_cancelling_a_membership_requires_and_records_a_reason(): void
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
            ->putJson("/panel/members/{$member->id}/membership/status", ['status' => MemberSubscription::STATUS_CANCELLED])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $subscription->fresh()->status);

        // Pausing is reversible, so it needs no note.
        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/status", ['status' => MemberSubscription::STATUS_PAUSED])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/status", [
                'status' => MemberSubscription::STATUS_CANCELLED,
                'reason' => 'Member moved away.',
            ])
            ->assertOk();

        $subscription->refresh();

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertSame('Member moved away.', $subscription->cancellation_reason);
        $this->assertNotNull($subscription->cancelled_by);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_member_creation_requires_contact_details(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'New Member',
                'email' => 'new-member@example.com',
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'address', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone']);
    }

    public function test_legacy_member_can_be_edited_without_contact_details_but_cannot_clear_them_once_set(): void
    {
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $payload = ['name' => 'Legacy Member', 'email' => $member->email, 'status' => User::STATUS_ACTIVE];

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}", $payload)
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}", $payload + ['phone' => '09170000000', 'date_of_birth' => '1990-01-01'])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}", $payload + ['phone' => '', 'date_of_birth' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'date_of_birth']);
    }

    public function test_manager_can_create_member_without_a_password_or_a_plan(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'New Member',
                'email' => 'new-member@example.com',
                'phone' => '09170000000',
                'address' => '12 Rizal St, Naga City',
                'date_of_birth' => '1990-01-01',
                'emergency_contact_name' => 'Next of Kin',
                'emergency_contact_phone' => '09170000001',
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'New Member')
            ->assertJsonPath('address', '12 Rizal St, Naga City')
            ->assertJsonPath('member_subscriptions', []);

        $member = User::query()->where('email', 'new-member@example.com')->firstOrFail();

        $this->assertTrue($member->hasRole('member'));
        // Identity only: the plan is sold at the POS, so nothing is issued here.
        $this->assertDatabaseCount('member_subscriptions', 0);
        // No member-facing login, so no password at all.
        $this->assertNull($member->password);
    }

    public function test_manager_can_reuse_email_from_a_soft_deleted_member(): void
    {
        $manager = $this->createUserWithRole('manager');

        $archivedMember = $this->createMember();
        $archivedMember->update(['email' => 'archived-member@example.com']);
        $archivedMember->delete();

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'New Member',
                'email' => 'archived-member@example.com',
                'phone' => '09170000000',
                'address' => '12 Rizal St, Naga City',
                'date_of_birth' => '1990-01-01',
                'emergency_contact_name' => 'Next of Kin',
                'emergency_contact_phone' => '09170000001',
                'status' => User::STATUS_ACTIVE,
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
