<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
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

        $businessProfile = BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
            'status' => BusinessProfile::STATUS_OPEN,
            'pay_overwork_hours' => true,
        ]);

        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Juan Dela Cruz');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'regular_hours' => 8,
            'regular_pay_amount' => 15000,
            'overwork_hours' => 2,
            'overwork_pay_amount' => 2500,
            'gross_amount' => 20000,
            'bonus' => 500,
            'income_tax' => 1604.10,
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
            'net_amount' => 18915.90,
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

        $payroll->load([
            'employee.roles',
            'generatedBy:id,name',
            'approvedBy:id,name',
            'payouts' => fn ($query) => $query->with('releasedBy:id,name')->orderBy('paid_at'),
        ]);

        $html = view('panel.employees.payslip', [
            'businessProfile' => $businessProfile,
            'employee' => $employee->fresh()->load('roles'),
            'payroll' => $payroll,
        ])->render();

        $this->assertStringContainsString('Regular pay', $html);
        $this->assertStringContainsString('Overwork pay', $html);
        $this->assertStringContainsString('Manual gross adjustment', $html);
        $this->assertStringContainsString('Income tax', $html);
        $this->assertStringContainsString('1,604.10', $html);
        $this->assertStringContainsString('JPrime Fitness Naga', $html);

        Pdf::assertRespondedWithPdf(function ($pdf) use ($employee, $payroll) {
            $this->assertSame('panel.employees.payslip', $pdf->viewName);
            $this->assertSame("payslip-employee-{$employee->id}-payroll-{$payroll->id}.pdf", $pdf->downloadName);
            $this->assertSame('Juan Dela Cruz', $pdf->viewData['employee']->name);
            $this->assertSame('Includes holiday bonus.', $pdf->viewData['payroll']->notes);
            $this->assertSame(1604.1, (float) $pdf->viewData['payroll']->income_tax);
            $this->assertSame(18915.9, (float) $pdf->viewData['payroll']->net_amount);
            $this->assertSame(320.0, (float) $pdf->viewData['payroll']->pt_commission_amount);
            $this->assertSame('Payroll Manager', $pdf->viewData['payroll']->generatedBy?->name);

            return true;
        });
    }

    public function test_payslip_hides_overwork_and_manual_adjustment_when_not_applicable(): void
    {
        $businessProfile = BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Daet',
            'status' => BusinessProfile::STATUS_OPEN,
            'pay_overwork_hours' => false,
        ]);

        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Paula Reyes');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'regular_hours' => 8,
            'regular_pay_amount' => 500,
            'overwork_hours' => 0,
            'overwork_pay_amount' => 0,
            'gross_amount' => 500,
            'bonus' => 0,
            'income_tax' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 500,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $html = view('panel.employees.payslip', [
            'businessProfile' => $businessProfile,
            'employee' => $employee->fresh()->load('roles'),
            'payroll' => $payroll->fresh()->load(['employee.roles', 'generatedBy:id,name', 'approvedBy:id,name', 'payouts']),
        ])->render();

        $this->assertStringContainsString('Regular pay', $html);
        $this->assertStringNotContainsString('Overwork pay', $html);
        $this->assertStringNotContainsString('Manual gross adjustment', $html);
    }

    public function test_payslip_keeps_showing_saved_overwork_even_when_setting_is_now_disabled(): void
    {
        $businessProfile = BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Legazpi',
            'status' => BusinessProfile::STATUS_OPEN,
            'pay_overwork_hours' => false,
        ]);

        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Liza Ramos');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'regular_hours' => 8,
            'regular_pay_amount' => 500,
            'overwork_hours' => 1,
            'overwork_pay_amount' => 62.5,
            'gross_amount' => 562.5,
            'bonus' => 0,
            'income_tax' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 562.5,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $html = view('panel.employees.payslip', [
            'businessProfile' => $businessProfile,
            'employee' => $employee->fresh()->load('roles'),
            'payroll' => $payroll->fresh()->load(['employee.roles', 'generatedBy:id,name', 'approvedBy:id,name', 'payouts']),
        ])->render();

        $this->assertStringContainsString('Regular pay', $html);
        $this->assertStringContainsString('Overwork pay', $html);
    }

    public function test_legacy_payslip_without_attendance_snapshot_does_not_show_new_breakdown_rows(): void
    {
        $businessProfile = BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
            'status' => BusinessProfile::STATUS_OPEN,
            'pay_overwork_hours' => false,
        ]);

        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Cathy Lopez');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 500,
            'bonus' => 0,
            'income_tax' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 500,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $html = view('panel.employees.payslip', [
            'businessProfile' => $businessProfile,
            'employee' => $employee->fresh()->load('roles'),
            'payroll' => $payroll->fresh()->load(['employee.roles', 'generatedBy:id,name', 'approvedBy:id,name', 'payouts']),
        ])->render();

        $this->assertStringNotContainsString('Regular pay', $html);
        $this->assertStringNotContainsString('Overwork pay', $html);
        $this->assertStringNotContainsString('Manual gross adjustment', $html);
    }

    private function createEmployeeWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
