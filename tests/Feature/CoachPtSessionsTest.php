<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\MemberPtPackage;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CoachPtSessionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
    }

    public function test_only_coaches_can_open_the_pt_sessions_pages(): void
    {
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember('Trainee Tess');
        $this->createPackage($member, $this->createPtProduct(), $coach);

        $this->actingAs($coach)
            ->get('/panel/pt-sessions')
            ->assertOk()
            ->assertSee('<coach-pt-sessions-page', false);

        $this->actingAs($coach)
            ->get("/panel/pt-session/{$member->id}")
            ->assertOk()
            ->assertSee('<coach-pt-session-detail-page', false)
            ->assertSee('Trainee Tess', false)
            ->assertDontSee($member->email, false);

        foreach (['staff', 'manager'] as $role) {
            $user = $this->createUserWithRole($role);
            $this->actingAs($user)->get('/panel/pt-sessions')->assertForbidden();
            $this->actingAs($user)->getJson('/panel/pt-sessions/list')->assertForbidden();
            $this->actingAs($user)->get("/panel/pt-session/{$member->id}")->assertForbidden();
        }
    }

    public function test_client_list_is_scoped_searchable_and_filterable(): void
    {
        $coach = $this->createUserWithRole('coach');
        $otherCoach = $this->createUserWithRole('coach');
        $product = $this->createPtProduct();

        $tess = $this->createMember('Trainee Tess');
        $this->createPackage($tess, $product, $coach);
        $done = $this->createMember('Done Dan');
        $this->createPackage($done, $product, $coach, ['status' => MemberPtPackage::STATUS_CONSUMED, 'remaining_sessions' => 0]);
        $this->createPackage($this->createMember('Not Mine'), $product, $otherCoach);

        $this->actingAs($coach)
            ->getJson('/panel/pt-sessions/list')
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonCount(2, 'clients.data')
            ->assertJsonPath('clients.data.0.name', 'Done Dan')
            ->assertJsonPath('clients.data.0.active_packages', 0)
            ->assertJsonPath('clients.data.1.name', 'Trainee Tess')
            ->assertJsonPath('clients.data.1.current_plan', '12 Sessions')
            ->assertJsonPath('clients.data.1.remaining_sessions', 12)
            ->assertJsonMissingPath('clients.data.0.email');

        $this->actingAs($coach)
            ->getJson('/panel/pt-sessions/list?search=tess')
            ->assertOk()
            ->assertJsonCount(1, 'clients.data')
            ->assertJsonPath('clients.data.0.id', $tess->id);

        $this->actingAs($coach)
            ->getJson('/panel/pt-sessions/list?filter=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'clients.data')
            ->assertJsonPath('clients.data.0.id', $done->id);
    }

    public function test_client_detail_returns_packages_and_logs_and_hides_other_coaches_clients(): void
    {
        $coach = $this->createUserWithRole('coach');
        $otherCoach = $this->createUserWithRole('coach');
        $product = $this->createPtProduct();
        $tess = $this->createMember('Trainee Tess');

        $old = $this->createPackage($tess, $product, $coach, ['status' => MemberPtPackage::STATUS_CONSUMED, 'remaining_sessions' => 0, 'assigned_at' => '2026-06-01']);
        $old->usages()->create(['coach_id' => $otherCoach->id, 'recorded_by' => $otherCoach->id, 'sessions_used' => 1, 'used_at' => '2026-06-05 09:00:00', 'confirmed_by' => 'Tess']);
        $current = $this->createPackage($tess, $product, $coach);
        $current->usages()->create(['coach_id' => $coach->id, 'recorded_by' => $coach->id, 'sessions_used' => 2, 'used_at' => '2026-09-10 18:00:00']);
        $notMine = $this->createMember('Not Mine');
        $this->createPackage($notMine, $product, $otherCoach);

        $this->actingAs($coach)
            ->getJson("/panel/pt-session/{$tess->id}/data")
            ->assertOk()
            ->assertJsonPath('client.name', 'Trainee Tess')
            ->assertJsonPath('client.client_since', '2026-06-01')
            ->assertJsonCount(2, 'packages')
            ->assertJsonPath('packages.0.id', $current->id)
            ->assertJsonPath('packages.0.usages.0.coach_name', $coach->name)
            ->assertJsonPath('packages.1.usages.0.coach_name', $otherCoach->name)
            ->assertJsonPath('packages.1.usages.0.confirmed_by', 'Tess')
            ->assertJsonMissingPath('client.email');

        $this->actingAs($coach)->get("/panel/pt-session/{$notMine->id}")->assertNotFound();
        $this->actingAs($coach)->getJson("/panel/pt-session/{$notMine->id}/data")->assertNotFound();
    }

    public function test_coach_can_log_a_session_only_against_own_package(): void
    {
        $coach = $this->createUserWithRole('coach');
        $otherCoach = $this->createUserWithRole('coach');
        $product = $this->createPtProduct();
        $own = $this->createPackage($this->createMember('Own Trainee'), $product, $coach);
        $foreign = $this->createPackage($this->createMember('Not Mine'), $product, $otherCoach);

        $this->actingAs($coach)
            ->postJson('/panel/pt-sessions', [
                'member_pt_package_id' => $own->id,
                'sessions_used' => 2,
                'used_at' => '2026-09-18 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('client.id', $own->user_id)
            ->assertJsonPath('packages.0.remaining_sessions', 10)
            ->assertJsonPath('packages.0.usages.0.sessions_used', 2);

        $this->assertDatabaseHas('member_pt_session_usages', [
            'member_pt_package_id' => $own->id,
            'coach_id' => $coach->id,
            'recorded_by' => $coach->id,
            'sessions_used' => 2,
        ]);

        $this->actingAs($coach)
            ->postJson('/panel/pt-sessions', [
                'member_pt_package_id' => $foreign->id,
                'sessions_used' => 1,
                'used_at' => '2026-09-18 11:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member_pt_package_id']);

        $this->assertSame(12, $foreign->fresh()->remaining_sessions);

        $this->actingAs($this->createUserWithRole('staff'))
            ->postJson('/panel/pt-sessions', [
                'member_pt_package_id' => $own->id,
                'sessions_used' => 1,
                'used_at' => '2026-09-18 12:00:00',
            ])
            ->assertForbidden();
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->withEmployeeProfile()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    private function createMember(string $name): User
    {
        $member = User::factory()->create(['name' => $name, 'status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');

        return $member;
    }

    private function createPtProduct(): PTProduct
    {
        return PTProduct::create([
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 6000,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPackage(User $member, PTProduct $product, User $coach, array $overrides = []): MemberPtPackage
    {
        return $member->memberPtPackages()->create(array_merge([
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'assigned_at' => '2026-09-01',
            'created_by' => $coach->id,
        ], $overrides));
    }
}
