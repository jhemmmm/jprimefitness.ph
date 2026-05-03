<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\PTProduct;
use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_page_renders_business_profile_and_global_pricing(): void
    {
        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);

        RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1500,
            'is_active' => true,
        ]);

        PTProduct::create([
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 2400,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('home-component', false)
            ->assertSee('JPrime Fitness Naga', false)
            ->assertSee('Naga City', false)
            ->assertSee('Monthly', false)
            ->assertSee('12 Sessions', false)
            ->assertSee('window.JPrime.timezone =', false);
    }
}
