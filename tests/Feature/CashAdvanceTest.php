<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CashAdvance;
use App\Models\CashLedgerEntry;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CashAdvanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness',
            'country_code' => BusinessProfile::COUNTRY_PHILIPPINES,
        ]);

        $this->manager = $this->createEmployeeWithRole('manager', 'Advance Manager');
        $this->employee = $this->createEmployeeWithRole('staff', 'Advance Staff');
    }

    public function test_manager_can_record_and_list_cash_advances(): void
    {
        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/cash-advances", [
                'amount' => 500,
                'method' => 'gcash',
                'reference_number' => 'GC-123',
            ])
            ->assertCreated()
            ->assertJsonPath('amount', 500)
            ->assertJsonPath('balance', 500)
            ->assertJsonPath('method', 'gcash')
            ->assertJsonPath('released_by_name', 'Advance Manager');

        $this->actingAs($this->manager)
            ->getJson("/panel/employees/{$this->employee->id}/cash-advances")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.reference_number', 'GC-123');
    }

    public function test_cash_advance_paid_in_cash_hits_the_cash_ledger(): void
    {
        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/cash-advances", [
                'amount' => 300,
                'method' => 'cash',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => CashLedgerEntry::TYPE_CASH_ADVANCE,
            'source_type' => 'cash_advance',
            'amount' => -300,
        ]);

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/cash-advances", [
                'amount' => 200,
                'method' => 'gcash',
            ])
            ->assertCreated();

        $this->assertSame(1, CashLedgerEntry::where('type', CashLedgerEntry::TYPE_CASH_ADVANCE)->count());
    }

    public function test_employee_can_view_own_advances_but_cannot_record(): void
    {
        CashAdvance::factory()->create(['employee_id' => $this->employee->id]);

        $this->actingAs($this->employee)
            ->getJson("/panel/employees/{$this->employee->id}/cash-advances")
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($this->employee)
            ->getJson("/panel/employees/{$this->manager->id}/cash-advances")
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->postJson("/panel/employees/{$this->employee->id}/cash-advances", [
                'amount' => 100,
                'method' => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_payroll_deduction_cannot_exceed_outstanding_advances(): void
    {
        CashAdvance::factory()->create(['employee_id' => $this->employee->id, 'amount' => 100]);

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls", [
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-15',
                'gross_amount' => 1000,
                'cash_advance_deductions' => 150,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cash_advance_deductions');
    }

    public function test_payroll_suggest_reports_outstanding_and_suggested_deduction(): void
    {
        CashAdvance::factory()->create(['employee_id' => $this->employee->id, 'amount' => 250]);

        $response = $this->actingAs($this->manager)
            ->getJson("/panel/employees/{$this->employee->id}/payrolls/suggest?period_start=2026-07-01&period_end=2026-07-15&gross_amount=1000")
            ->assertOk()
            ->assertJsonPath('cash_advance_outstanding', 250)
            ->assertJsonPath('cash_advance_suggested', 250);

        $this->assertEqualsWithDelta(
            $response->json('net_amount_preview'),
            1000 - $response->json('withholding_tax') - $response->json('employee_contributions_total'),
            0.01
        );

        $withDeduction = $this->actingAs($this->manager)
            ->getJson("/panel/employees/{$this->employee->id}/payrolls/suggest?period_start=2026-07-01&period_end=2026-07-15&gross_amount=1000&cash_advance_deductions=250")
            ->assertOk();

        $this->assertEqualsWithDelta(
            $withDeduction->json('net_amount_preview'),
            1000 - 250 - $withDeduction->json('withholding_tax') - $withDeduction->json('employee_contributions_total'),
            0.01
        );
    }

    public function test_deduction_cannot_exceed_pay_available_for_the_period(): void
    {
        CashAdvance::factory()->create(['employee_id' => $this->employee->id, 'amount' => 5000]);

        $response = $this->actingAs($this->manager)
            ->getJson("/panel/employees/{$this->employee->id}/payrolls/suggest?period_start=2026-07-01&period_end=2026-07-15&gross_amount=1000")
            ->assertOk();

        $available = 1000 - $response->json('withholding_tax') - $response->json('employee_contributions_total');
        $this->assertEqualsWithDelta($available, $response->json('cash_advance_suggested'), 0.01);

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls", [
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-15',
                'gross_amount' => 1000,
                'cash_advance_deductions' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cash_advance_deductions');
    }

    public function test_approving_payroll_repays_advances_fifo(): void
    {
        $older = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 100,
            'paid_at' => now()->subDays(10),
        ]);
        $newer = CashAdvance::factory()->create([
            'employee_id' => $this->employee->id,
            'amount' => 50,
            'paid_at' => now()->subDay(),
        ]);

        $payrollId = $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls", [
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-15',
                'gross_amount' => 1000,
                'cash_advance_deductions' => 120,
            ])
            ->assertCreated()
            ->assertJsonPath('cash_advance_deductions', 120)
            ->json('id');

        $this->assertDatabaseCount('cash_advance_repayments', 0);

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls/{$payrollId}/approve")
            ->assertOk();

        $this->assertSame(100.0, (float) $older->fresh()->repaid_amount);
        $this->assertSame(20.0, (float) $newer->fresh()->repaid_amount);
        $this->assertDatabaseHas('cash_advance_repayments', ['cash_advance_id' => $older->id, 'payroll_id' => $payrollId, 'amount' => 100]);
        $this->assertDatabaseHas('cash_advance_repayments', ['cash_advance_id' => $newer->id, 'payroll_id' => $payrollId, 'amount' => 20]);
        $this->assertSame(30.0, $newer->fresh()->balance());
    }

    public function test_approve_fails_when_outstanding_balance_shrank(): void
    {
        CashAdvance::factory()->create(['employee_id' => $this->employee->id, 'amount' => 100]);

        $makeDraft = fn (string $start, string $end) => $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls", [
                'period_start' => $start,
                'period_end' => $end,
                'gross_amount' => 1000,
                'cash_advance_deductions' => 100,
            ])
            ->assertCreated()
            ->json('id');

        $first = $makeDraft('2026-07-01', '2026-07-15');
        $second = $makeDraft('2026-07-16', '2026-07-31');

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls/{$first}/approve")
            ->assertOk();

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls/{$second}/approve")
            ->assertUnprocessable();

        $this->assertSame(Payroll::STATUS_DRAFT, Payroll::find($second)->status);
        $this->assertSame(1, \App\Models\CashAdvanceRepayment::count());
    }

    public function test_cancelling_draft_payroll_leaves_advances_untouched(): void
    {
        $advance = CashAdvance::factory()->create(['employee_id' => $this->employee->id, 'amount' => 100]);

        $payrollId = $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls", [
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-15',
                'gross_amount' => 1000,
                'cash_advance_deductions' => 100,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->manager)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls/{$payrollId}/cancel")
            ->assertOk();

        $this->assertSame(0.0, (float) $advance->fresh()->repaid_amount);
        $this->assertDatabaseCount('cash_advance_repayments', 0);
    }

    private function createEmployeeWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 800,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
