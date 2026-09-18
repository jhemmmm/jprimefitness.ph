<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\SaleTransaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * One representative GET per permission, hit by every panel role.
 * Proves RoleSeeder::MATRIX and the route middleware agree.
 */
class RolePermissionMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PANEL_ROLES = ['super admin', 'admin', 'manager', 'cashier', 'staff', 'coach'];

    /** permission => [method, representative url]. Non-GET routes target real records (bound before middleware): allowed → 422, denied → 403. */
    private const ROUTES = [
        'access panel' => ['GET', '/panel/my-dashboard/data'],
        'log pt sessions' => ['GET', '/panel/pt-sessions/list'],
        'view dashboard' => ['GET', '/panel/dashboard/data'],
        'manage members' => ['GET', '/panel/members/list'],
        'edit members' => ['PUT', '/panel/members/{member}'],
        'manage employees' => ['GET', '/panel/employees/list'],
        'manage attendance' => ['GET', '/panel/attendance/list'],
        'manage sales' => ['GET', '/panel/sales/context'],
        'void sales' => ['POST', '/panel/sales/{sale}/void'],
        'manage pricing' => ['GET', '/panel/pricing/data'],
        'manage inventory' => ['GET', '/panel/inventory/list'],
        'manage cash drawer' => ['GET', '/panel/cash-drawer/data'],
        'view reports' => ['GET', '/panel/reports/payroll/data'],
        'manage settings' => ['GET', '/panel/business/settings'],
        'view system activity' => ['GET', '/panel/system-activity/list'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
    }

    public static function matrix(): array
    {
        $cases = [];

        foreach (self::ROUTES as $permission => [$method, $url]) {
            foreach (self::PANEL_ROLES as $role) {
                $allowed = in_array($permission, RoleSeeder::MATRIX[$role], true);
                $cases["{$role} / {$permission}"] = [$role, $method, $url, $allowed];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_role_access_matches_seeded_matrix(string $role, string $method, string $url, bool $allowed): void
    {
        $user = User::factory()->withEmployeeProfile()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        $member = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');
        $sale = SaleTransaction::factory()->create();
        $url = strtr($url, ['{member}' => $member->id, '{sale}' => $sale->id]);

        $response = $this->actingAs($user)->json($method, $url);

        if ($allowed) {
            $this->assertNotSame(403, $response->status(), "{$role} should reach {$url}");
        } else {
            $response->assertForbidden();
        }
    }

    public function test_every_matrix_permission_has_a_representative_route(): void
    {
        $this->assertSame(RoleSeeder::PERMISSIONS, array_keys(self::ROUTES));
    }

    public function test_member_is_kept_out_of_the_panel(): void
    {
        $member = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');

        $this->actingAs($member)->get('/panel/dashboard')->assertRedirect(route('home'));
        $this->actingAs($member)->getJson('/panel/my-dashboard/data')->assertForbidden();
    }
}
