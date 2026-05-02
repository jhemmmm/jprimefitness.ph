<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollMembershipEarningsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));
    }

    public function test_payroll_uses_gross_and_bonus_without_membership_sales_earnings(): void
    {
        BusinessProfile::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'J Prime Fitness',
                'city' => 'Naga',
                'country_code' => BusinessProfile::COUNTRY_PHILIPPINES,
                'timezone' => 'Asia/Manila',
                'payroll_withholding_tax_enabled' => false,
                'payroll_government_contributions_enabled' => false,
            ]
        );

        $manager = User::factory()->create(['name' => 'Manager Mia']);
        $manager->assignRole('manager');

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4000,
                'bonus' => 0,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('total_earnings', 4000)
            ->assertJsonPath('net_amount', 4000);

        $this->assertDatabaseHas('payrolls', [
            'id' => $response->json('id'),
            'employee_id' => $manager->id,
            'net_amount' => 4000,
        ]);
    }
}
