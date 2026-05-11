<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin');

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
        ]);
    }

    public function test_admin_can_update_weekly_operating_hours(): void
    {
        $admin = $this->createUserWithRole('admin');
        $operatingHours = BusinessProfile::defaultOperatingHours();

        $this->actingAs($admin)
            ->putJson('/panel/business/settings', $this->businessSettingsPayload([
                'operating_hours' => $operatingHours,
            ]))
            ->assertOk()
            ->assertJsonPath('opening_time', '06:00')
            ->assertJsonPath('closing_time', '23:00')
            ->assertJsonPath('operating_hours.0.day', 'Monday')
            ->assertJsonPath('operating_hours.0.opening_time', '06:00')
            ->assertJsonPath('operating_hours.4.day', 'Friday')
            ->assertJsonPath('operating_hours.4.closing_time', '23:00')
            ->assertJsonPath('operating_hours.5.day', 'Saturday')
            ->assertJsonPath('operating_hours.5.opening_time', '08:00')
            ->assertJsonPath('operating_hours.6.day', 'Sunday')
            ->assertJsonPath('operating_hours.6.opening_time', '08:00');

        $profile = BusinessProfile::current()->fresh();

        $this->assertSame($operatingHours, $profile->operating_hours);
        $this->assertSame('06:00', $profile->opening_time);
        $this->assertSame('23:00', $profile->closing_time);
    }

    public function test_weekly_operating_hours_must_cover_each_day_and_close_after_opening(): void
    {
        $admin = $this->createUserWithRole('admin');
        $operatingHours = BusinessProfile::defaultOperatingHours();
        $operatingHours[5]['closing_time'] = '07:00';
        array_pop($operatingHours);

        $this->actingAs($admin)
            ->putJson('/panel/business/settings', $this->businessSettingsPayload([
                'operating_hours' => $operatingHours,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'operating_hours',
                'operating_hours.5.closing_time',
            ]);
    }

    /**
     * Build a complete business settings payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function businessSettingsPayload(array $overrides = []): array
    {
        $profile = BusinessProfile::current();

        return array_merge([
            'name' => $profile->name,
            'country_code' => $profile->country_code,
            'pay_overwork_hours' => (bool) $profile->pay_overwork_hours,
            'payroll_withholding_tax_enabled' => (bool) $profile->payroll_withholding_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $profile->payroll_government_contributions_enabled,
            'city' => $profile->city,
            'province' => $profile->province,
            'address' => $profile->address,
            'timezone' => $profile->timezone,
            'amenities' => $profile->amenities ?? [],
            'operating_hours' => $profile->operating_hours ?? BusinessProfile::defaultOperatingHours(),
        ], $overrides);
    }

    /**
     * Create a user with the given role.
     *
     * @return \App\Models\User
     */
    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
