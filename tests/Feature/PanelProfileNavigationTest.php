<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PanelProfileNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('manager')->givePermissionTo(Permission::findOrCreate('manage employees'));
        Role::findOrCreate('staff');
    }

    /**
     * Display the profile menu item for panel employees.
     *
     * @return void
     */
    public function test_panel_employee_profile_menu_links_to_their_employee_profile(): void
    {
        $this->setBusinessProfile();
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<i class="bi bi-person me-2"></i>Profile', false)
            ->assertSee(route('panel.employees.show', $staff), false);
    }

    /**
     * Hide the profile menu item for super admins.
     *
     * @return void
     */
    public function test_super_admin_panel_menu_does_not_show_profile_item(): void
    {
        $this->setBusinessProfile();
        $superAdmin = $this->createUserWithRole('super admin', 'Root Admin');

        $this->actingAs($superAdmin)
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertDontSee('<i class="bi bi-person me-2"></i>Profile', false);
    }

    /**
     * Allow panel employees to view only their own employee profile.
     *
     * @return void
     */
    public function test_panel_employee_can_view_own_profile_but_not_other_employee_profiles(): void
    {
        $this->setBusinessProfile();
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $otherStaff = $this->createUserWithRole('staff', 'Staff Ben');

        $this->actingAs($staff)
            ->get(route('panel.employees.show', $staff))
            ->assertOk()
            ->assertSee('employee-detail-page', false)
            ->assertSeeText('Staff Ana');

        $this->actingAs($staff)
            ->get(route('panel.employees.show', $otherStaff))
            ->assertForbidden();
    }

    /**
     * Return the default business profile for panel rendering.
     *
     * @return \App\Models\BusinessProfile
     */
    private function setBusinessProfile(): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
        ]);
    }

    /**
     * Create a user with a panel role.
     *
     * @return \App\Models\User
     */
    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
