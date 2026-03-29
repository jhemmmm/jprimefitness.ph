<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayslipTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        $staffRole = Role::findOrCreate('staff');
        $permission = Permission::findOrCreate('manage employees');

        $managerRole->givePermissionTo($permission);
        $staffRole->givePermissionTo($permission);
    }

    public function test_manager_can_view_employee_payslip(): void
    {
        Pdf::fake();

        $branch = $this->createBranch('Naga');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Juan Dela Cruz');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1500,
            'bonus' => 200,
            'pt_commission_amount' => 320,
            'pt_commission_items' => [
                [
                    'package_id' => 99,
                    'member_name' => 'Member Ana',
                    'product_name' => '8 Sessions',
                    'commission_amount' => 320,
                ],
            ],
            'manual_deductions' => 100,
            'cash_advance_deduction' => 200,
            'net_amount' => 1720,
            'status' => Payroll::STATUS_APPROVED,
            'notes' => 'Includes holiday bonus.',
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        Payout::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'amount' => 900,
            'method' => Payout::METHOD_CASH,
            'released_by' => $manager->id,
            'paid_at' => '2026-03-16 09:00:00',
        ]);

        $this->actingAs($manager)
            ->get(route('panel.employees.payrolls.payslip', [$employee, $payroll]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(function ($pdf) use ($employee, $payroll) {
            $this->assertSame('panel.employees.payslip', $pdf->viewName);
            $this->assertSame("payslip-employee-{$employee->id}-payroll-{$payroll->id}.pdf", $pdf->downloadName);
            $this->assertSame('Juan Dela Cruz', $pdf->viewData['employee']->name);
            $this->assertSame('Includes holiday bonus.', $pdf->viewData['payroll']->notes);
            $this->assertSame(1720.0, (float) $pdf->viewData['payroll']->net_amount);
            $this->assertSame(320.0, (float) $pdf->viewData['payroll']->pt_commission_amount);
            $this->assertSame('Payroll Manager', $pdf->viewData['payroll']->generatedBy?->name);

            return true;
        });
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => 'PH',
            'city' => 'Naga City',
        ]);
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createEmployeeWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
