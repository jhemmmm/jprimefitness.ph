<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);

        $this->user = User::factory()->withEmployeeProfile([
            'tin' => '123-456-789-000',
            'emergency_contact_name' => 'Next of Kin',
            'hired_at' => '2026-01-15',
        ])->create([
            'name' => 'Staff Ana',
            'email' => 'ana@example.com',
            'address' => '12 Rizal St, Naga City',
            'password' => Hash::make('old-secret'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->user->assignRole('staff');
    }

    public function test_profile_page_renders_for_any_panel_user(): void
    {
        $this->actingAs($this->user)
            ->get('/panel/profile')
            ->assertOk()
            ->assertSee('<profile-page', false)
            ->assertSee('ana@example.com', false)
            // employee details are editable on the profile page
            ->assertSee('12 Rizal St, Naga City', false)
            ->assertSee('123-456-789-000', false)
            ->assertSee('Next of Kin', false)
            ->assertSee('2026-01-15', false);
    }

    public function test_name_and_phone_update_but_email_is_ignored(): void
    {
        // Blank password fields are exactly what ProfilePage.vue submits on a plain profile save.
        $this->actingAs($this->user)
            ->putJson('/panel/profile', [
                ...$this->details(),
                'name' => 'Ana Reyes',
                'phone' => '09171234567',
                'email' => 'hacker@example.com',
                'current_password' => '',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Ana Reyes')
            ->assertJsonPath('email', 'ana@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Ana Reyes',
            'phone' => '09171234567',
            'email' => 'ana@example.com',
            'address' => '12 Rizal St, Naga City',
        ]);
        $this->assertDatabaseHas('employee_profiles', ['user_id' => $this->user->id, 'tin' => '123-456-789-000']);
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $this->actingAs($this->user)
            ->putJson('/panel/profile', [
                ...$this->details(),
                'name' => 'Staff Ana',
                'password' => 'new-secret-1',
                'password_confirmation' => 'new-secret-1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->actingAs($this->user)
            ->putJson('/panel/profile', [
                ...$this->details(),
                'name' => 'Staff Ana',
                'current_password' => 'wrong',
                'password' => 'new-secret-1',
                'password_confirmation' => 'new-secret-1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('old-secret', $this->user->fresh()->password));

        $this->actingAs($this->user)
            ->putJson('/panel/profile', [
                ...$this->details(),
                'name' => 'Staff Ana',
                'phone' => '09171234567',
                'current_password' => 'old-secret',
                'password' => 'New-secret-1',
                'password_confirmation' => 'New-secret-1',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('New-secret-1', $this->user->fresh()->password));
    }

    public function test_employee_can_update_their_own_details_but_not_the_hire_date(): void
    {
        $this->actingAs($this->user)
            ->putJson('/panel/profile', [
                'name' => 'Staff Ana',
                'phone' => '09170000000',
                'address' => '7 Mabini St, Naga City',
                'employee_profile' => [
                    'date_of_birth' => '1992-03-04',
                    'emergency_contact_name' => 'Kin Ana',
                    'emergency_contact_phone' => '09170000001',
                    'tin' => '987-654-321-000',
                    'sss_number' => '34-7654321-8',
                    'hired_at' => '2020-01-01', // ignored: managers set this
                ],
            ])
            ->assertOk()
            ->assertJsonPath('address', '7 Mabini St, Naga City')
            ->assertJsonPath('employee_profile.tin', '987-654-321-000')
            ->assertJsonPath('employee_profile.hired_at', '2026-01-15');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $this->user->id,
            'emergency_contact_name' => 'Kin Ana',
            'sss_number' => '34-7654321-8',
        ]);
        $this->assertSame('1992-03-04', $this->user->employeeProfile->fresh()->date_of_birth->toDateString());

        // once set, the mandatory details can't be cleared
        $this->actingAs($this->user)
            ->putJson('/panel/profile', ['name' => 'Staff Ana', 'phone' => '09170000000', 'employee_profile' => ['date_of_birth' => null]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address', 'employee_profile.date_of_birth', 'employee_profile.emergency_contact_name']);
    }

    /**
     * What the profile page always submits alongside the account fields: the
     * details already on file (required once set).
     *
     * @return array<string, mixed>
     */
    private function details(): array
    {
        return ['address' => '12 Rizal St, Naga City', 'employee_profile' => ['emergency_contact_name' => 'Next of Kin']];
    }

    public function test_new_password_needs_an_uppercase_letter_and_a_symbol(): void
    {
        $this->actingAs($this->user)
            ->putJson('/panel/profile', [
                ...$this->details(),
                'name' => 'Staff Ana',
                'phone' => '09171234567',
                'current_password' => 'old-secret',
                'password' => 'newsecret1',
                'password_confirmation' => 'newsecret1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
