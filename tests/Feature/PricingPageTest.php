<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
    }

    public function test_pricing_page_loads_for_panel_users(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Mia');

        $this->actingAs($manager)
            ->get('/panel/pricing')
            ->assertOk()
            ->assertSee('pricing-page', false);
    }

    public function test_branch_pricing_data_is_scoped_to_accessible_branches(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');
        $ratePlan = $this->createRatePlan('Monthly', 30);
        $unusedRatePlan = $this->createRatePlan('3 Months', 90);
        $ptProduct = $this->createPtProduct('12 Sessions', 12);
        $unusedPtProduct = $this->createPtProduct('24 Sessions', 24);

        $accessibleBranch->ratePlans()->attach($ratePlan->id, [
            'price' => 1499,
            'manager_commission_rate' => 12.5,
            'is_active' => true,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
        ]);
        $accessibleBranch->ptProducts()->attach($ptProduct->id, [
            'price' => 3600,
            'coach_commission_rate' => 40,
            'is_active' => true,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
        ]);
        $otherBranch->ratePlans()->attach($ratePlan->id, [
            'price' => 1999,
            'manager_commission_rate' => 8,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/pricing/branches/'.$accessibleBranch->id)
            ->assertOk()
            ->assertJsonPath('branch.id', $accessibleBranch->id)
            ->assertJsonCount(1, 'membership_rates')
            ->assertJsonCount(1, 'available_membership_rate_plans')
            ->assertJsonCount(1, 'pt_rates')
            ->assertJsonCount(1, 'available_pt_products')
            ->assertJsonPath('membership_rates.0.id', $ratePlan->id)
            ->assertJsonPath('membership_rates.0.branch_price', 1499)
            ->assertJsonPath('membership_rates.0.manager_commission_rate', 12.5)
            ->assertJsonPath('membership_rates.0.effective_from', '2026-04-01')
            ->assertJsonPath('membership_rates.0.effective_until', '2026-04-30')
            ->assertJsonPath('available_membership_rate_plans.0.id', $unusedRatePlan->id)
            ->assertJsonPath('pt_rates.0.id', $ptProduct->id)
            ->assertJsonPath('pt_rates.0.branch_price', 3600)
            ->assertJsonPath('pt_rates.0.coach_commission_rate', 40)
            ->assertJsonPath('pt_rates.0.effective_from', '2026-04-01')
            ->assertJsonPath('pt_rates.0.effective_until', '2026-04-30')
            ->assertJsonPath('available_pt_products.0.id', $unusedPtProduct->id);

        $this->actingAs($staff)
            ->getJson('/panel/pricing/branches/'.$otherBranch->id)
            ->assertNotFound();
    }

    public function test_inactive_master_pricing_options_are_not_returned_or_attachable(): void
    {
        $branch = $this->createBranch('Iriga');
        $admin = $this->createUserWithRole('admin', [$branch->id], 'Admin Mia');
        $inactiveRatePlan = $this->createRatePlan('Legacy Plan', 45, false);
        $inactivePtProduct = $this->createPtProduct('Legacy PT', 18, false);

        $this->actingAs($admin)
            ->getJson('/panel/pricing/branches/'.$branch->id)
            ->assertOk()
            ->assertJsonCount(0, 'available_membership_rate_plans')
            ->assertJsonCount(0, 'available_pt_products');

        $this->actingAs($admin)
            ->postJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$inactiveRatePlan->id, [
                'price' => 1999,
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->postJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$inactivePtProduct->id, [
                'price' => 3999,
                'coach_commission_rate' => 40,
                'is_active' => true,
            ])
            ->assertNotFound();
    }

    public function test_admin_can_create_update_and_delete_branch_rate_plan_pricing(): void
    {
        $branch = $this->createBranch('Iriga');
        $admin = $this->createUserWithRole('admin', [$branch->id], 'Admin Mia');
        $ratePlan = $this->createRatePlan('6 Months', 180);

        $this->actingAs($admin)
            ->postJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$ratePlan->id, [
                'price' => 4999.50,
                'manager_commission_rate' => 15,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$ratePlan->id, [
                'price' => 5499.50,
                'manager_commission_rate' => 17.5,
                'is_active' => false,
                'effective_from' => '2026-04-01',
                'effective_until' => '2026-06-30',
            ])
            ->assertOk();

        $this->assertDatabaseHas('branch_rate_prices', [
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'price' => 5499.50,
            'manager_commission_rate' => 17.5,
            'is_active' => false,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-06-30',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$ratePlan->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('branch_rate_prices', [
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
        ]);
    }

    public function test_admin_can_create_update_and_delete_branch_pt_product_pricing(): void
    {
        $branch = $this->createBranch('Daet');
        $admin = $this->createUserWithRole('admin', [$branch->id], 'Admin Zoe');
        $ptProduct = $this->createPtProduct('24 Sessions', 24);

        $this->actingAs($admin)
            ->postJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$ptProduct->id, [
                'price' => 6800,
                'coach_commission_rate' => 40,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$ptProduct->id, [
                'price' => 7200,
                'coach_commission_rate' => 45,
                'is_active' => false,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('branch_pt_prices', [
            'branch_id' => $branch->id,
            'pt_product_id' => $ptProduct->id,
            'price' => 7200,
            'coach_commission_rate' => 45,
            'is_active' => false,
            'effective_from' => '2026-04-01',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$ptProduct->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('branch_pt_prices', [
            'branch_id' => $branch->id,
            'pt_product_id' => $ptProduct->id,
        ]);
    }

    public function test_manager_cannot_create_update_or_delete_branch_pricing(): void
    {
        $branch = $this->createBranch('Sorsogon');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Lou');
        $ratePlan = $this->createRatePlan('Annual', 365);
        $ptProduct = $this->createPtProduct('32 Sessions', 32);

        $this->actingAs($manager)
            ->putJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$ratePlan->id, [
                'price' => 10999,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$ratePlan->id, [
                'price' => 10999,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->putJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$ptProduct->id, [
                'price' => 9200,
                'coach_commission_rate' => 40,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$ptProduct->id, [
                'price' => 9200,
                'coach_commission_rate' => 40,
                'is_active' => true,
            ])
            ->assertForbidden();

        $branch->ratePlans()->attach($ratePlan->id, [
            'price' => 9999,
            'is_active' => true,
        ]);
        $branch->ptProducts()->attach($ptProduct->id, [
            'price' => 9200,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->deleteJson('/panel/pricing/branches/'.$branch->id.'/rate-plans/'.$ratePlan->id)
            ->assertForbidden();

        $this->actingAs($manager)
            ->deleteJson('/panel/pricing/branches/'.$branch->id.'/pt-products/'.$ptProduct->id)
            ->assertForbidden();
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

    private function createRatePlan(string $name, int $durationDays, bool $isActive = true): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'description' => $name.' membership',
            'is_active' => $isActive,
        ]);
    }

    private function createPtProduct(string $name, int $sessionCount, bool $isActive = true): PTProduct
    {
        return PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'description' => $name.' PT package',
            'is_active' => $isActive,
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
