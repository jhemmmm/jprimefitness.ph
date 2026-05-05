<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Prepare permission cache state before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Verify production defaults are seeded without demo data.
     *
     * @return void
     */
    public function test_production_seeder_creates_operational_defaults(): void
    {
        config()->set('production.super_admin.name', 'Owner Admin');
        config()->set('production.super_admin.email', 'owner@example.com');
        config()->set('production.super_admin.password', 'secure-production-password');

        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $superAdmin = User::query()->where('email', 'owner@example.com')->first();

        $this->assertNotNull($superAdmin);
        $this->assertSame('Owner Admin', $superAdmin->name);
        $this->assertSame(User::STATUS_ACTIVE, $superAdmin->status);
        $this->assertTrue(Hash::check('secure-production-password', $superAdmin->password));
        $this->assertTrue($superAdmin->hasRole('super admin'));

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, User::query()->where('email', 'owner@example.com')->count());
        $this->assertSame(1, BusinessProfile::query()->count());
        $this->assertSame(7, Role::query()->count());
        $this->assertSame(2, Permission::query()->count());

        $this->assertDatabaseHas('permissions', ['name' => 'access panel']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage employees']);
        $this->assertDatabaseHas('rate_plans', [
            'name' => 'Daily Pass',
            'is_walk_in_only' => true,
            'price' => 120.00,
        ]);
        $this->assertDatabaseHas('rate_plans', [
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1600.00,
        ]);
        $this->assertDatabaseHas('pt_products', [
            'name' => '12 Sessions',
            'session_count' => 12,
            'price' => 4800.00,
        ]);

        $this->assertSame(5, RatePlan::query()->count());
        $this->assertSame(4, PTProduct::query()->count());
    }

    /**
     * Verify production seeding fails without a configured password.
     *
     * @return void
     */
    public function test_production_seeder_requires_super_admin_password(): void
    {
        config()->set('production.super_admin.password', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PRODUCTION_SUPER_ADMIN_PASSWORD');

        $this->seed(ProductionSeeder::class);
    }
}
