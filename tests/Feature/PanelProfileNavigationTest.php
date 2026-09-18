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
     * Show Profile and My Record links to employees; My Record points at their own record.
     *
     * @return void
     */
    public function test_panel_employee_menu_links_to_profile_and_each_own_record_page(): void
    {
        $this->setBusinessProfile();
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<i class="bi bi-person me-2"></i>Profile', false)
            ->assertSee(route('panel.profile.edit'), false)
            ->assertSee('My Record', false)
            ->assertSee(route('panel.my.show', 'attendance'), false)
            ->assertSee(route('panel.my.show', 'schedule'), false)
            ->assertSee(route('panel.my.show', 'payroll'), false)
            ->assertSee(route('panel.my.show', 'payouts'), false)
            ->assertSee(route('panel.my.show', 'cash-advances'), false)
            ->assertSee('My Payroll', false);
    }

    /**
     * Super admins have no employee record, so they get Profile but not My Record.
     *
     * @return void
     */
    public function test_super_admin_menu_shows_profile_but_not_my_record(): void
    {
        $this->setBusinessProfile();
        $superAdmin = User::factory()->create(['name' => 'Root Admin', 'status' => User::STATUS_ACTIVE]);
        $superAdmin->assignRole('super admin');

        $this->actingAs($superAdmin)
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('<i class="bi bi-person me-2"></i>Profile', false)
            ->assertDontSee('My Record', false);
    }

    /**
     * Each My Record section is a full page for employees; super admins (no record) get 404.
     *
     * @return void
     */
    public function test_my_record_sections_render_as_pages(): void
    {
        $this->setBusinessProfile();
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        foreach (['attendance', 'schedule', 'payroll', 'payouts', 'cash-advances'] as $section) {
            $this->actingAs($staff)
                ->get("/panel/my/{$section}")
                ->assertOk()
                ->assertSee('<my-record-page', false)
                ->assertSee('section="'.$section.'"', false);
        }

        $this->actingAs($staff)->get('/panel/my/settings')->assertNotFound();

        $superAdmin = User::factory()->create(['name' => 'Root Admin', 'status' => User::STATUS_ACTIVE]);
        $superAdmin->assignRole('super admin');
        $this->actingAs($superAdmin)->get('/panel/my/payroll')->assertNotFound();
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
