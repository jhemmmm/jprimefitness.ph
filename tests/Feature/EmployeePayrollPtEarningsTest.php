<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollPtEarningsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('coach');

        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));
    }

    public function test_payroll_uses_gross_and_bonus_without_pt_session_earnings(): void
    {
        BusinessProfile::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'J Prime Fitness',
                'city' => 'Naga',
                'country_code' => BusinessProfile::COUNTRY_PHILIPPINES,
                'timezone' => 'Asia/Manila',
                'payroll_income_tax_enabled' => false,
                'payroll_government_contributions_enabled' => false,
            ]
        );

        $manager = User::factory()->create(['name' => 'Payroll Manager']);
        $manager->assignRole('manager');

        $coach = User::factory()->create(['name' => 'Coach Mario']);
        $coach->assignRole('coach');

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 4500,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('total_earnings', 4500)
            ->assertJsonPath('net_amount', 4500);

        $this->assertDatabaseHas('payrolls', [
            'id' => $response->json('id'),
            'employee_id' => $coach->id,
            'net_amount' => 4500,
        ]);
    }
}
