<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
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

        BusinessProfile::factory()->create();
    }

    public function test_pricing_page_loads_for_panel_users(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');

        $this->actingAs($manager)
            ->get('/panel/pricing')
            ->assertOk()
            ->assertSee('pricing-page', false);
    }

    public function test_pricing_component_includes_mobile_rate_cards(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/PricingPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Pricing & Rates</h4>', $contents);
        $this->assertStringContainsString("class=\"d-md-none\"", $contents);
        $this->assertStringContainsString("class=\"member-card\" v-for=\"rate in pricing.membership_rates\"", $contents);
        $this->assertStringContainsString("class=\"member-card\" v-for=\"rate in pricing.pt_rates\"", $contents);
        $this->assertStringContainsString('dropdown-menu dropdown-menu-end', $contents);
    }

    public function test_pricing_data_returns_configured_rates_and_stats(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $configuredRatePlan = $this->createRatePlan('Monthly', 30, [
            'price' => 1499,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
        ]);
        $this->createRatePlan('3 Months', 90); // no price — should not appear
        $configuredPtProduct = $this->createPtProduct('12 Sessions', 12, [
            'price' => 3600,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
        ]);
        $this->createPtProduct('24 Sessions', 24); // no price — should not appear
        $configuredRatePlan = $configuredRatePlan->fresh();
        $configuredPtProduct = $configuredPtProduct->fresh();

        $this->actingAs($staff)
            ->getJson('/panel/pricing/data')
            ->assertOk()
            ->assertJsonCount(1, 'membership_rates')
            ->assertJsonCount(1, 'pt_rates')
            ->assertJsonPath('membership_rates.0.id', $configuredRatePlan->id)
            ->assertJsonPath('membership_rates.0.price', 1499)
            ->assertJsonPath('membership_rates.0.effective_from', $configuredRatePlan->effective_from?->toJSON())
            ->assertJsonPath('membership_rates.0.effective_until', $configuredRatePlan->effective_until?->toJSON())
            ->assertJsonPath('pt_rates.0.id', $configuredPtProduct->id)
            ->assertJsonPath('pt_rates.0.price', 3600)
            ->assertJsonPath('pt_rates.0.effective_from', $configuredPtProduct->effective_from?->toJSON())
            ->assertJsonPath('pt_rates.0.effective_until', $configuredPtProduct->effective_until?->toJSON())
            ->assertJsonPath('stats.membership_configured', 1)
            ->assertJsonPath('stats.pt_configured', 1);
    }

    public function test_unpriced_plans_do_not_appear_in_pricing_data(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin Mia');
        $this->createRatePlan('Legacy Plan', 45, ['is_active' => false]);
        $this->createPtProduct('Legacy PT', 18, ['is_active' => false]);

        $this->actingAs($admin)
            ->getJson('/panel/pricing/data')
            ->assertOk()
            ->assertJsonCount(0, 'membership_rates')
            ->assertJsonCount(0, 'pt_rates');
    }

    public function test_admin_can_create_update_and_delete_rate_plan_pricing(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin Mia');

        $createResponse = $this->actingAs($admin)
            ->postJson('/panel/pricing/rate-plans', [
                'name' => '6 Months',
                'duration_days' => 180,
                'price' => 4999.50,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $ratePlanId = $createResponse->json('id');
        $this->assertNotNull($ratePlanId);

        $this->actingAs($admin)
            ->putJson('/panel/pricing/rate-plans/'.$ratePlanId, [
                'price' => 5499.50,
                'is_active' => false,
                'effective_from' => '2026-04-01',
                'effective_until' => '2026-06-30',
            ])
            ->assertOk();

        $this->assertDatabaseHas('rate_plans', [
            'id' => $ratePlanId,
            'price' => 5499.50,
            'is_active' => false,
            'effective_from' => '2026-04-01 00:00:00',
            'effective_until' => '2026-06-30 00:00:00',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/panel/pricing/rate-plans/'.$ratePlanId)
            ->assertNoContent();

        $this->assertDatabaseHas('rate_plans', [
            'id' => $ratePlanId,
            'price' => null,
            'effective_from' => null,
            'effective_until' => null,
        ]);
    }

    public function test_admin_can_create_update_and_delete_pt_product_pricing(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin Zoe');

        $createResponse = $this->actingAs($admin)
            ->postJson('/panel/pricing/pt-products', [
                'name' => '24 Sessions',
                'session_count' => 24,
                'price' => 6800,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $ptProductId = $createResponse->json('id');
        $this->assertNotNull($ptProductId);

        $this->actingAs($admin)
            ->putJson('/panel/pricing/pt-products/'.$ptProductId, [
                'price' => 7200,
                'is_active' => false,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pt_products', [
            'id' => $ptProductId,
            'price' => 7200.00,
            'is_active' => false,
            'effective_from' => '2026-04-01 00:00:00',
            'effective_until' => null,
        ]);

        $this->actingAs($admin)
            ->deleteJson('/panel/pricing/pt-products/'.$ptProductId)
            ->assertNoContent();

        $this->assertDatabaseHas('pt_products', [
            'id' => $ptProductId,
            'price' => null,
            'effective_from' => null,
            'effective_until' => null,
        ]);
    }

    public function test_manager_cannot_create_update_or_delete_pricing(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Lou');
        $ratePlan = $this->createRatePlan('Annual', 365);
        $ptProduct = $this->createPtProduct('32 Sessions', 32);

        $this->actingAs($manager)
            ->putJson('/panel/pricing/rate-plans/'.$ratePlan->id, [
                'price' => 10999,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson('/panel/pricing/rate-plans', [
                'name' => 'Annual',
                'duration_days' => 365,
                'price' => 10999,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->putJson('/panel/pricing/pt-products/'.$ptProduct->id, [
                'price' => 9200,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson('/panel/pricing/pt-products', [
                'name' => '32 Sessions',
                'session_count' => 32,
                'price' => 9200,
                'is_active' => true,
            ])
            ->assertForbidden();

        $ratePlan->update([
            'price' => 9999,
            'effective_from' => '2026-04-01',
        ]);
        $ptProduct->update([
            'price' => 9200,
            'effective_from' => '2026-04-01',
        ]);

        $this->actingAs($manager)
            ->deleteJson('/panel/pricing/rate-plans/'.$ratePlan->id)
            ->assertForbidden();

        $this->actingAs($manager)
            ->deleteJson('/panel/pricing/pt-products/'.$ptProduct->id)
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRatePlan(string $name, int $durationDays, array $attributes = []): RatePlan
    {
        return RatePlan::create(array_merge([
            'name' => $name,
            'duration_days' => $durationDays,
            'description' => $name.' membership',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPtProduct(string $name, int $sessionCount, array $attributes = []): PTProduct
    {
        return PTProduct::create(array_merge([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'description' => $name.' PT package',
            'is_active' => true,
        ], $attributes));
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
