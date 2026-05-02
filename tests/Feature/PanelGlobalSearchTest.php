<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PanelGlobalSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('employee');
        Role::findOrCreate('coach');

        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));
    }

    public function test_panel_layout_mounts_global_search_component(): void
    {
        $this->setBusinessProfile('Search Hub');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');

        $this->actingAs($manager)
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<global-search></global-search>', false);
    }

    public function test_global_search_returns_results_for_each_supported_resource(): void
    {
        $this->setBusinessProfile('Search Hub');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $member = $this->createUserWithRole('member', 'Search Member', 'member.search@example.test', '09170000001');
        $employee = $this->createUserWithRole('coach', 'Search Coach', 'coach.search@example.test', '09170000002');
        $category = InventoryCategory::factory()->create(['name' => 'Search Drinks']);
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Search Protein',
            'sku' => 'SEARCH-001',
            'unit' => 'bottle',
            'quantity' => 12,
        ]);
        $walkIn = WalkIn::create([
            'served_by' => $manager->id,
            'name' => 'Search Guest',
            'phone' => '09170000003',
            'amount_paid' => 350,
            'payment_method' => 'cash',
            'visited_at' => now()->subHour(),
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/search?search=Search')
            ->assertOk()
            ->assertJsonPath('query', 'Search')
            ->assertJsonPath('groups.members.total', 1)
            ->assertJsonPath('groups.employees.total', 1)
            ->assertJsonPath('groups.inventory.total', 1)
            ->assertJsonPath('groups.walkins.total', 1)
            ->assertJsonPath('groups.members.items.0.url', route('panel.members.show', $member))
            ->assertJsonPath('groups.employees.items.0.url', route('panel.employees.show', $employee))
            ->assertJsonPath('groups.inventory.items.0.url', route('panel.inventory.index', ['search' => 'SEARCH-001']))
            ->assertJsonPath('groups.walkins.items.0.url', route('panel.walkins.index', ['search' => $walkIn->phone]));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'name' => 'Search Protein',
        ]);
    }

    public function test_global_search_hides_employee_results_without_permission(): void
    {
        $this->setBusinessProfile('Scoped Search');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->createUserWithRole('member', 'Scoped Member');
        $this->createUserWithRole('coach', 'Scoped Coach');

        $category = InventoryCategory::factory()->create(['name' => 'Supplements']);

        InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Scoped Bottle',
            'sku' => 'SCOPED-1',
        ]);

        WalkIn::create([
            'served_by' => $staff->id,
            'name' => 'Scoped Guest',
            'phone' => '09980000001',
            'amount_paid' => 250,
            'payment_method' => 'cash',
            'visited_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/search?search=Scoped')
            ->assertOk();

        $groups = $response->json('groups');

        $this->assertSame(1, data_get($groups, 'members.total'));
        $this->assertSame(1, data_get($groups, 'inventory.total'));
        $this->assertSame(1, data_get($groups, 'walkins.total'));
        $this->assertArrayNotHasKey('employees', $groups);
        $this->assertSame(['Scoped Member'], collect(data_get($groups, 'members.items', []))->pluck('title')->all());
        $this->assertSame(['Scoped Bottle'], collect(data_get($groups, 'inventory.items', []))->pluck('title')->all());
        $this->assertSame(['Scoped Guest'], collect(data_get($groups, 'walkins.items', []))->pluck('title')->all());
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createUserWithRole(string $role, string $name, ?string $email = null, ?string $phone = null): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'email' => $email ?? Str::slug($role.' '.$name, '.').'@example.test',
            'phone' => $phone ?? fake()->unique()->numerify('09#########'),
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
