<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Role;
use App\Models\SystemActivity;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
    }

    public function test_admin_lists_every_role_but_super_admin_and_member_with_all_permissions(): void
    {
        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->getJson('/panel/roles')
            ->assertOk();

        $this->assertEqualsCanonicalizing(['admin', 'manager', 'cashier', 'staff', 'coach'], $response->json('roles.*.name'));
        $this->assertEqualsCanonicalizing(RoleSeeder::permissions(), $response->json('permissions'));

        $this->assertSame(Role::COLORS, $response->json('colors'));
        // the model's built-in list is what RoleController protects; keep it in step with the seeder
        $this->assertEqualsCanonicalizing(array_keys(RoleSeeder::MATRIX), Role::BUILT_IN);

        $cashier = collect($response->json('roles'))->firstWhere('name', 'cashier');
        $this->assertTrue($cashier['protected']);
        $this->assertSame('amber', $cashier['color']);
        $this->assertEqualsCanonicalizing(RoleSeeder::MATRIX['cashier'], $cashier['permissions']);
    }

    public function test_only_settings_managers_can_reach_role_management(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)->getJson('/panel/roles')->assertForbidden();
        $this->actingAs($manager)->postJson('/panel/roles', ['name' => 'front desk'])->assertForbidden();

        $this->actingAs($this->createUserWithRole('super admin'))->getJson('/panel/roles')->assertOk();
    }

    public function test_admin_can_create_a_role_with_permissions_and_assign_it_to_an_employee(): void
    {
        $admin = $this->createUserWithRole('admin');

        $roleId = $this->actingAs($admin)
            ->postJson('/panel/roles', ['name' => ' Front Desk ', 'color' => 'teal', 'permissions' => ['access panel', 'manage sales']])
            ->assertCreated()
            ->assertJsonPath('name', 'front desk')
            ->assertJsonPath('color', 'teal')
            ->assertJsonPath('protected', false)
            ->assertJsonPath('permissions', ['access panel', 'manage sales'])
            ->json('id');

        $this->assertDatabaseHas('system_activities', ['subject_type' => SystemActivity::SUBJECT_ROLE, 'subject_id' => $roleId, 'event' => 'created']);

        // the new role is offered on the employee form and accepted by it
        $this->actingAs($admin)->get('/panel/employees')->assertOk()->assertSee('"name":"front desk"', false);

        $this->actingAs($admin)
            ->postJson('/panel/employees', [
                'name' => 'Desk Dana',
                'email' => 'dana@example.com',
                'phone' => '09170000000',
                'address' => '12 Rizal St, Naga City',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$roleId],
                'employee_profile' => [
                    'date_of_birth' => '1990-01-01',
                    'emergency_contact_name' => 'Next of Kin',
                    'emergency_contact_phone' => '09170000001',
                    'daily_rate' => 500,
                    'pay_frequency' => 'monthly',
                    'sss_covered' => false,
                    'philhealth_covered' => false,
                    'pagibig_covered' => false,
                ],
                'password' => 'Password123!',
            ])
            ->assertCreated()
            ->assertJsonPath('roles.0.name', 'front desk');

        // ...and a user holding only that role can use what it grants, and nothing else
        $dana = User::where('email', 'dana@example.com')->firstOrFail();
        $this->actingAs($dana)->getJson('/panel/sales/context')->assertOk();
        $this->actingAs($dana)->get('/panel/business/settings')->assertForbidden();
    }

    public function test_role_name_must_be_unique_and_colour_and_permissions_must_exist(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->postJson('/panel/roles', ['name' => 'Cashier', 'color' => 'neon', 'permissions' => ['fly']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color', 'permissions.0']);

        $this->actingAs($admin)
            ->postJson('/panel/roles', ['name' => 'cashier', 'color' => 'slate'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_built_in_roles_can_change_permissions_but_not_be_renamed_or_deleted(): void
    {
        $admin = $this->createUserWithRole('admin');
        $cashier = Role::findByName('cashier');

        $this->actingAs($admin)
            ->putJson("/panel/roles/{$cashier->id}", ['name' => 'cashier', 'color' => 'rose', 'permissions' => ['access panel', 'view reports']])
            ->assertOk()
            ->assertJsonPath('color', 'rose')
            ->assertJsonPath('permissions', ['access panel', 'view reports']);

        $this->assertFalse($cashier->fresh()->hasPermissionTo('manage sales'));
        $this->assertDatabaseHas('system_activities', ['subject_type' => SystemActivity::SUBJECT_ROLE, 'subject_id' => $cashier->id, 'event' => 'updated']);

        $this->actingAs($admin)
            ->putJson("/panel/roles/{$cashier->id}", ['name' => 'front desk', 'color' => 'rose', 'permissions' => ['access panel']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($admin)
            ->deleteJson("/panel/roles/{$cashier->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseHas('roles', ['name' => 'cashier']);
    }

    public function test_custom_role_can_be_renamed_and_deleted_once_nobody_holds_it(): void
    {
        $admin = $this->createUserWithRole('admin');
        $role = Role::create(['name' => 'front desk', 'guard_name' => 'web']);
        $holder = $this->createUserWithRole('front desk');

        $this->actingAs($admin)
            ->putJson("/panel/roles/{$role->id}", ['name' => 'reception', 'color' => 'teal', 'permissions' => ['access panel']])
            ->assertOk()
            ->assertJsonPath('name', 'reception')
            ->assertJsonPath('users_count', 1);

        $this->assertTrue($holder->fresh()->can('access panel'));

        $this->actingAs($admin)
            ->deleteJson("/panel/roles/{$role->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Reassign 1 user(s) holding this role first.');

        $holder->delete(); // soft-deleted, restorable: still counts
        $this->actingAs($admin)->deleteJson("/panel/roles/{$role->id}")->assertUnprocessable();

        $holder->syncRoles(['staff']);

        $this->actingAs($admin)->deleteJson("/panel/roles/{$role->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_super_admin_and_member_roles_are_not_editable(): void
    {
        $admin = $this->createUserWithRole('admin');

        foreach (['super admin', 'member'] as $hidden) {
            $role = Role::findByName($hidden);
            $this->actingAs($admin)->putJson("/panel/roles/{$role->id}", ['name' => $hidden, 'color' => 'slate', 'permissions' => []])->assertNotFound();
            $this->actingAs($admin)->deleteJson("/panel/roles/{$role->id}")->assertNotFound();
        }

        $this->assertEqualsCanonicalizing(RoleSeeder::MATRIX['super admin'], Role::findByName('super admin')->permissions->pluck('name')->all());
    }

    public function test_permission_revocations_reach_the_sync_outbox(): void
    {
        config(['sync.role' => 'live', 'sync.node_id' => 'live-test', 'permission.events_enabled' => true]);
        $admin = $this->createUserWithRole('admin');
        $cashier = Role::findByName('cashier');
        DB::table('sync_outbox')->truncate();

        $this->actingAs($admin)
            ->putJson("/panel/roles/{$cashier->id}", ['name' => 'cashier', 'color' => 'amber', 'permissions' => ['access panel', 'view reports']])
            ->assertOk();

        $events = DB::table('sync_outbox')->where('entity_type', 'role_has_permissions')->get();
        $revoked = $events->where('op', 'delete')->map(fn ($row) => json_decode($row->payload, true)['permission_name'])->all();
        $granted = $events->where('op', 'create')->map(fn ($row) => json_decode($row->payload, true)['permission_name'])->all();

        $this->assertEqualsCanonicalizing(['manage cash drawer', 'manage members', 'manage sales'], $revoked);
        $this->assertSame(['view reports'], array_values($granted));
    }

    public function test_an_admin_cannot_remove_their_own_access_from_a_role_they_hold(): void
    {
        $admin = $this->createUserWithRole('admin');
        $role = Role::findByName('admin');

        $this->actingAs($admin)
            ->putJson("/panel/roles/{$role->id}", ['name' => 'admin', 'color' => 'pink', 'permissions' => ['access panel']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->assertTrue($admin->fresh()->can('manage settings'));
    }

    public function test_employee_managers_can_only_hand_out_roles_that_grant_nothing_they_lack(): void
    {
        $manager = $this->createUserWithRole('manager');
        $escalation = Role::create(['name' => 'ops lead', 'color' => 'rose']);
        $escalation->givePermissionTo(['access panel', 'manage settings']);
        $harmless = Role::create(['name' => 'greeter', 'color' => 'green']);
        $harmless->givePermissionTo(['access panel']);

        $this->assertEqualsCanonicalizing(['cashier', 'coach', 'greeter', 'manager', 'staff'], Role::assignableBy($manager)->pluck('name')->all());
        $this->assertContains('ops lead', Role::assignableBy($this->createUserWithRole('admin'))->pluck('name')->all());

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$manager->id}", [
                'name' => $manager->name,
                'email' => $manager->email,
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$escalation->id],
                'employee_profile' => ['daily_rate' => 500, 'pay_frequency' => 'monthly', 'sss_covered' => false, 'philhealth_covered' => false, 'pagibig_covered' => false],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_ids.0']);

        $this->assertFalse($manager->fresh()->can('manage settings'));
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
