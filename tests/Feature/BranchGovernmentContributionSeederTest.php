<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\PTProduct;
use App\Models\RatePlan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BranchGovernmentContributionSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_creates_single_business_profile_and_global_pricing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Schema::hasTable('business_profiles'));
        $this->assertFalse(Schema::hasTable('branches'));
        $this->assertSame(1, BusinessProfile::query()->count());
        $this->assertGreaterThan(0, RatePlan::query()->whereNotNull('price')->count());
        $this->assertGreaterThan(0, PTProduct::query()->whereNotNull('price')->count());
        $this->assertFalse(Permission::query()->where('name', 'manage branches')->exists());
    }
}
