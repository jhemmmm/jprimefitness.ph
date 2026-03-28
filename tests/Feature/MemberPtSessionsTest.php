<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MemberPtPackage;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MemberPtSessionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('coach');
        Role::findOrCreate('member');
    }

    public function test_manager_can_add_pt_package_for_member(): void
    {
        $branch = $this->createBranch('Calabanga');
        $product = $this->attachPtProduct($branch, '12 Sessions', 12);
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $coach = $this->createUserWithRole('coach', [$branch->id]);
        $member = $this->createMember([$branch->id]);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'branch_id' => $branch->id,
                'pt_product_id' => $product->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-01',
                'notes' => 'Paid in full at the front desk.',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.total_sessions', 12)
            ->assertJsonPath('member_pt_packages.0.remaining_sessions', 12)
            ->assertJsonPath('member_pt_packages.0.coach.name', $coach->name)
            ->assertJsonPath('member_pt_packages.0.sold_price', '500.00')
            ->assertJsonPath('member_pt_packages.0.coach_commission_rate', '40.00')
            ->assertJsonPath('member_pt_packages.0.coach_commission_amount', '200.00')
            ->assertJsonPath('member_pt_packages.0.coach_commission_status', MemberPtPackage::COMMISSION_STATUS_PENDING);

        $this->assertDatabaseHas('member_pt_packages', [
            'user_id' => $member->id,
            'branch_id' => $branch->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 200,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_PENDING,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);
    }

    public function test_staff_can_log_pt_session_usage_and_reduce_balance(): void
    {
        $branch = $this->createBranch('Naga');
        $product = $this->attachPtProduct($branch, '12 Sessions', 12);
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $coach = $this->createUserWithRole('coach', [$branch->id]);
        $staff = $this->createUserWithRole('staff', [$branch->id]);
        $member = $this->createMember([$branch->id]);

        $package = $member->memberPtPackages()->create([
            'branch_id' => $branch->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 2,
                'used_at' => '2026-04-02 09:30:00',
                'confirmed_by' => 'Maria Santos',
                'notes' => 'Upper body session',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.remaining_sessions', 10);

        $this->assertDatabaseHas('member_pt_session_usages', [
            'member_pt_package_id' => $package->id,
            'recorded_by' => $staff->id,
            'coach_id' => $coach->id,
            'sessions_used' => 2,
            'confirmed_by' => 'Maria Santos',
        ]);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'remaining_sessions' => 10,
            'status' => MemberPtPackage::STATUS_ACTIVE,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_PENDING,
        ]);
    }

    public function test_consuming_last_session_marks_pt_commission_as_earned(): void
    {
        $branch = $this->createBranch('Tabaco');
        $product = $this->attachPtProduct($branch, 'Per Session', 1);
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $coach = $this->createUserWithRole('coach', [$branch->id]);
        $staff = $this->createUserWithRole('staff', [$branch->id]);
        $member = $this->createMember([$branch->id]);

        $package = $member->memberPtPackages()->create([
            'branch_id' => $branch->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 200,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_PENDING,
            'total_sessions' => 1,
            'remaining_sessions' => 1,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 1,
                'used_at' => '2026-04-02 09:30:00',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.status', MemberPtPackage::STATUS_CONSUMED)
            ->assertJsonPath('member_pt_packages.0.coach_commission_status', MemberPtPackage::COMMISSION_STATUS_EARNED);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
        ]);
    }

    public function test_logging_usage_with_a_late_coach_assignment_updates_the_package_commission_state(): void
    {
        $branch = $this->createBranch('Goa');
        $product = $this->attachPtProduct($branch, 'Per Session', 1);
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $coach = $this->createUserWithRole('coach', [$branch->id]);
        $staff = $this->createUserWithRole('staff', [$branch->id]);
        $member = $this->createMember([$branch->id]);

        $package = $member->memberPtPackages()->create([
            'branch_id' => $branch->id,
            'pt_product_id' => $product->id,
            'sold_price' => 500,
            'coach_commission_rate' => 40,
            'coach_commission_amount' => 200,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_UNASSIGNED,
            'total_sessions' => 1,
            'remaining_sessions' => 1,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'coach_id' => $coach->id,
                'sessions_used' => 1,
                'used_at' => '2026-04-02 09:30:00',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.coach.name', $coach->name)
            ->assertJsonPath('member_pt_packages.0.coach_commission_status', MemberPtPackage::COMMISSION_STATUS_EARNED);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'coach_id' => $coach->id,
            'coach_commission_status' => MemberPtPackage::COMMISSION_STATUS_EARNED,
        ]);

        $this->assertDatabaseHas('member_pt_session_usages', [
            'member_pt_package_id' => $package->id,
            'coach_id' => $coach->id,
        ]);
    }

    public function test_package_cannot_be_assigned_to_a_coach_from_another_branch(): void
    {
        $branch = $this->createBranch('Sorsogon');
        $otherBranch = $this->createBranch('Iriga');
        $product = $this->attachPtProduct($branch, '8 Sessions', 8);
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $coach = $this->createUserWithRole('coach', [$otherBranch->id]);
        $member = $this->createMember([$branch->id]);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'branch_id' => $branch->id,
                'pt_product_id' => $product->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['coach_id']);
    }

    public function test_sessions_used_cannot_exceed_remaining_balance(): void
    {
        $branch = $this->createBranch('Legazpi');
        $product = $this->attachPtProduct($branch, 'Per Session', 1);
        $staff = $this->createUserWithRole('staff', [$branch->id]);
        $member = $this->createMember([$branch->id]);

        $package = $member->memberPtPackages()->create([
            'branch_id' => $branch->id,
            'pt_product_id' => $product->id,
            'total_sessions' => 1,
            'remaining_sessions' => 1,
            'assigned_at' => '2026-04-01',
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 2,
                'used_at' => '2026-04-02 09:30:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sessions_used']);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'remaining_sessions' => 1,
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);
    }

    public function test_members_list_includes_pt_packages_and_profile_notes(): void
    {
        $branch = $this->createBranch('Daet');
        $product = $this->attachPtProduct($branch, '8 Sessions', 8);
        $manager = $this->createUserWithRole('manager', [$branch->id]);
        $member = $this->createMember([$branch->id]);

        $member->profile()->create([
            'notes' => 'Prefers morning PT sessions.',
        ]);

        $member->memberPtPackages()->create([
            'branch_id' => $branch->id,
            'pt_product_id' => $product->id,
            'total_sessions' => 8,
            'remaining_sessions' => 6,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/members/list')
            ->assertOk()
            ->assertJsonPath('members.data.0.profile.notes', 'Prefers morning PT sessions.')
            ->assertJsonPath('members.data.0.member_pt_packages.0.total_sessions', 8)
            ->assertJsonPath('members.data.0.member_pt_packages.0.remaining_sessions', 6)
            ->assertJsonPath('members.data.0.member_pt_packages.0.pt_product.name', '8 Sessions');
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'city' => 'Naga City',
        ]);
    }

    private function attachPtProduct(Branch $branch, string $name, int $sessionCount): PTProduct
    {
        $product = PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'is_active' => true,
        ]);

        $branch->ptProducts()->attach($product->id, [
            'price' => 500,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        return $product;
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
