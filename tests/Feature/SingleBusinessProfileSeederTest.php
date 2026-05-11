<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\PTProduct;
use App\Models\RatePlan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SingleBusinessProfileSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_creates_single_business_profile_and_global_pricing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, BusinessProfile::query()->count());
        $this->assertGreaterThan(0, RatePlan::query()->whereNotNull('price')->count());
        $this->assertGreaterThan(0, PTProduct::query()->whereNotNull('price')->count());

        $profile = BusinessProfile::query()->firstOrFail();

        $this->assertSame('06:00', $profile->opening_time);
        $this->assertSame('23:00', $profile->closing_time);
        $this->assertSame(BusinessProfile::defaultOperatingHours(), $profile->operating_hours);
        $this->assertSame('Monday-Friday 6:00 AM - 11:00 PM, Saturday-Sunday 8:00 AM - 11:00 PM', $profile->formattedOperatingHours());
    }
}
