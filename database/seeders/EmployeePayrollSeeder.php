<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CashAdvance;
use App\Models\Payroll;
use App\Models\Payout;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EmployeePayrollSeeder extends Seeder
{
    public function run(): void
    {
        $service = new PayrollService();
        $branch  = Branch::first();

        if (! $branch) {
            $this->command->warn('No branches found. Run BranchSeeder first.');
            return;
        }

        $role = Role::create(['name' => 'super admin']);
        $permission = Permission::first();
        $role->givePermissionTo($permission);

        // Create a dedicated seeded employee (idempotent)
        $emp = User::firstOrCreate(
            ['email' => 'juan.delacruz@jprimefitness.ph'],
            [
                'name'  => 'Juan Dela Cruz',
                'password'   => bcrypt('password'),
                'status'     => User::STATUS_ACTIVE,
                'branch_id'  => $branch->id,
                'phone'      => '09171234567',
            ]
        );
        $emp->assignRole($role); {

            // ── Cash Advances ─────────────────────────────────────────────
            // Fully deducted advance (older)
            $ca1 = CashAdvance::create([
                'employee_id'      => $emp->id,
                'branch_id'        => $branch->id,
                'amount'           => 2000.00,
                'remaining_amount' => 0.00,
                'status'           => 'fully_deducted',
                'notes'            => 'Emergency loan - fully settled',
                'requested_at'     => Carbon::now()->subMonths(2),
            ]);

            // Partial advance (still outstanding)
            $ca2 = CashAdvance::create([
                'employee_id'      => $emp->id,
                'branch_id'        => $branch->id,
                'amount'           => 3000.00,
                'remaining_amount' => 1500.00,
                'status'           => 'partial',
                'notes'            => 'Personal advance - partially deducted',
                'requested_at'     => Carbon::now()->subMonth(),
            ]);

            // Pending advance (untouched)
            $ca3 = CashAdvance::create([
                'employee_id'      => $emp->id,
                'branch_id'        => $branch->id,
                'amount'           => 1000.00,
                'remaining_amount' => 1000.00,
                'status'           => 'pending',
                'notes'            => 'Cash advance for transportation',
                'requested_at'     => Carbon::now()->subDays(5),
            ]);

            // ── Payroll 1 — Paid (2 months ago) ──────────────────────────
            $gross1  = 18000.00;
            $bonus1  = 1500.00;
            $manDed1 = 500.00;
            $caDed1  = 2000.00; // cleared ca1
            $payroll1 = Payroll::create([
                'employee_id'            => $emp->id,
                'branch_id'              => $branch->id,
                'period_start'           => Carbon::now()->subMonths(2)->startOfMonth(),
                'period_end'             => Carbon::now()->subMonths(2)->endOfMonth(),
                'gross_amount'           => $gross1,
                'bonus'                  => $bonus1,
                'manual_deductions'      => $manDed1,
                'cash_advance_deduction' => $caDed1,
                'net_amount'             => $service->computeNet($gross1, $bonus1, $manDed1, $caDed1),
                'status'                 => 'paid',
                'notes'                  => 'Regular bi-monthly payroll',
                'generated_by'           => null,
                'approved_by'            => null,
                'approved_at'            => Carbon::now()->subMonths(2)->endOfMonth()->addDay(),
            ]);

            // Single full payout for payroll1
            Payout::create([
                'payroll_id'       => $payroll1->id,
                'employee_id'      => $emp->id,
                'amount'           => $payroll1->net_amount,
                'method'           => 'bank',
                'reference_number' => 'TRF-' . strtoupper(substr(md5($emp->id . '1'), 0, 8)),
                'released_by'      => null,
                'notes'            => 'Full salary transfer',
                'paid_at'          => Carbon::now()->subMonths(2)->endOfMonth()->addDays(2),
            ]);

            // ── Payroll 2 — Partially Paid (last month) ───────────────────
            $gross2  = 18000.00;
            $bonus2  = 0.00;
            $manDed2 = 200.00;
            $caDed2  = 1500.00; // partial on ca2
            $payroll2 = Payroll::create([
                'employee_id'            => $emp->id,
                'branch_id'              => $branch->id,
                'period_start'           => Carbon::now()->subMonth()->startOfMonth(),
                'period_end'             => Carbon::now()->subMonth()->endOfMonth(),
                'gross_amount'           => $gross2,
                'bonus'                  => $bonus2,
                'manual_deductions'      => $manDed2,
                'cash_advance_deduction' => $caDed2,
                'net_amount'             => $service->computeNet($gross2, $bonus2, $manDed2, $caDed2),
                'status'                 => 'partially_paid',
                'notes'                  => 'Regular payroll — partial payout pending',
                'generated_by'           => null,
                'approved_by'            => null,
                'approved_at'            => Carbon::now()->subMonth()->endOfMonth()->addDay(),
            ]);

            // Partial payout for payroll2
            Payout::create([
                'payroll_id'       => $payroll2->id,
                'employee_id'      => $emp->id,
                'amount'           => round($payroll2->net_amount / 2, 2),
                'method'           => 'gcash',
                'reference_number' => 'GC-' . strtoupper(substr(md5($emp->id . '2'), 0, 8)),
                'released_by'      => null,
                'notes'            => 'First half release',
                'paid_at'          => Carbon::now()->subMonth()->endOfMonth()->addDays(2),
            ]);

            // ── Payroll 3 — Approved (current month, awaiting payout) ─────
            $gross3  = 18000.00;
            $bonus3  = 500.00;
            $manDed3 = 0.00;
            $caDed3  = 1000.00; // pending ca3
            Payroll::create([
                'employee_id'            => $emp->id,
                'branch_id'              => $branch->id,
                'period_start'           => Carbon::now()->startOfMonth(),
                'period_end'             => Carbon::now()->endOfMonth(),
                'gross_amount'           => $gross3,
                'bonus'                  => $bonus3,
                'manual_deductions'      => $manDed3,
                'cash_advance_deduction' => $caDed3,
                'net_amount'             => $service->computeNet($gross3, $bonus3, $manDed3, $caDed3),
                'status'                 => 'approved',
                'notes'                  => 'Current month payroll — approved, payout pending',
                'generated_by'           => null,
                'approved_by'            => null,
                'approved_at'            => Carbon::now(),
            ]);

            // ── Payroll 4 — Draft (next period, not yet approved) ─────────
            $gross4  = 18000.00;
            Payroll::create([
                'employee_id'            => $emp->id,
                'branch_id'              => $branch->id,
                'period_start'           => Carbon::now()->addMonth()->startOfMonth(),
                'period_end'             => Carbon::now()->addMonth()->endOfMonth(),
                'gross_amount'           => $gross4,
                'bonus'                  => 0,
                'manual_deductions'      => 0,
                'cash_advance_deduction' => 0,
                'net_amount'             => $gross4,
                'status'                 => 'draft',
                'notes'                  => 'Next period draft — awaiting review',
                'generated_by'           => null,
            ]);
        }

        $this->command->info('EmployeePayrollSeeder: seeded employee juan.delacruz@jprimefitness.ph with payrolls, payouts, and cash advances.');
    }
}
