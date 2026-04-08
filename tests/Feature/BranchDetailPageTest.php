<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\PTProduct;
use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BranchDetailPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_page_renders_single_location_profile_and_global_pricing(): void
    {
        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
            'hero_title' => 'Train with purpose',
            'hero_highlight' => 'One standard.',
            'phone' => '09171234567',
            'email' => 'hello@example.test',
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);

        RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1500,
            'manager_commission_rate' => 10,
            'is_active' => true,
        ]);

        PTProduct::create([
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 2400,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('JPrime Fitness Naga')
            ->assertSee('Memberships and PT packages')
            ->assertSee('Monthly')
            ->assertSee('12 Sessions')
            ->assertSee('All current prices are managed centrally for this location.');
    }

    public function test_legacy_branch_urls_return_not_found(): void
    {
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness']);

        $this->get('/branches/naga')->assertNotFound();
    }
}
