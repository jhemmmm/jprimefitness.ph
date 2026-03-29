<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchCashLedgerEntry;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchCashLedgerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');

        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));
    }

    public function test_pt_package_creation_does_not_inflate_branch_cash_balance(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Branch Manager');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Ana');
        $product = $this->attachPtProduct($branch, '12 Sessions', 12, 1200);

        $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'branch_id' => $branch->id,
                'name' => 'Walk-in Ben',
                'amount_paid' => 350,
                'visited_at' => '2026-03-20 09:00:00',
            ])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'branch_id' => $branch->id,
                'pt_product_id' => $product->id,
                'assigned_at' => '2026-03-20',
            ])
            ->assertCreated();

        $response = $this->actingAs($manager)
            ->getJson("/panel/branches/{$branch->id}/cash-ledger")
            ->assertOk()
            ->assertJsonPath('summary.balance', 350)
            ->assertJsonPath('summary.cash_in_total', 350)
            ->assertJsonPath('summary.cash_out_total', 0);

        $entryTypes = collect($response->json('entries'))->pluck('entry_type')->all();

        $this->assertContains('walk_in_sale', $entryTypes);
        $this->assertNotContains('pt_package_sale', $entryTypes);

        $this->assertDatabaseHas('branch_cash_ledger_entries', [
            'branch_id' => $branch->id,
            'entry_type' => 'walk_in_sale',
            'amount' => 350,
            'is_system' => true,
        ]);

        $this->assertDatabaseMissing('branch_cash_ledger_entries', [
            'branch_id' => $branch->id,
            'entry_type' => 'pt_package_sale',
        ]);
    }

    public function test_staff_cannot_view_branch_cash_ledger(): void
    {
        $branch = $this->createBranch('Iriga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Front Desk Staff');

        $this->actingAs($staff)
            ->getJson("/panel/branches/{$branch->id}/cash-ledger")
            ->assertForbidden();
    }

    public function test_cash_payouts_and_released_cash_advances_reduce_branch_balance(): void
    {
        $branch = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Branch Manager');
        $coach = $this->createUserWithRole('coach', [$branch->id], 'Coach Mia');

        $payrollResponse = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated();

        $payrollId = $payrollResponse->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/approve")
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$payrollId}/payouts", [
                'amount' => 1000,
                'method' => 'cash',
                'paid_at' => '2026-03-16 09:30:00',
            ])
            ->assertCreated();

        $cashAdvanceResponse = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/cash-advances", [
                'amount' => 300,
                'requested_at' => '2026-03-17 08:00:00',
            ])
            ->assertCreated();

        $cashAdvanceId = $cashAdvanceResponse->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$coach->id}/cash-advances/{$cashAdvanceId}", [
                'status' => 'approved',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$coach->id}/cash-advances/{$cashAdvanceId}", [
                'status' => 'released',
            ])
            ->assertOk();

        $response = $this->actingAs($manager)
            ->getJson("/panel/branches/{$branch->id}/cash-ledger")
            ->assertOk()
            ->assertJsonPath('summary.balance', -1300)
            ->assertJsonPath('summary.cash_in_total', 0)
            ->assertJsonPath('summary.cash_out_total', 1300);

        $entryTypes = collect($response->json('entries'))->pluck('entry_type')->all();

        $this->assertContains('payroll_payout', $entryTypes);
        $this->assertContains('cash_advance_release', $entryTypes);
    }

    public function test_manager_can_create_update_and_delete_manual_cash_ledger_entries(): void
    {
        $branch = $this->createBranch('Daet');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Branch Manager');

        $createResponse = $this->actingAs($manager)
            ->postJson("/panel/branches/{$branch->id}/cash-ledger", [
                'direction' => 'in',
                'amount' => 2500,
                'occurred_at' => '2026-03-18 10:15:00',
                'title' => 'Opening Balance',
                'description' => 'Initial branch cash on hand',
            ])
            ->assertCreated()
            ->assertJsonPath('summary.balance', 2500);

        $entryId = $createResponse->json('entry.id');

        $this->actingAs($manager)
            ->putJson("/panel/branches/{$branch->id}/cash-ledger/{$entryId}", [
                'direction' => 'out',
                'amount' => 400,
                'occurred_at' => '2026-03-18 11:00:00',
                'title' => 'Utility Payment',
                'description' => 'Paid internet bill',
            ])
            ->assertOk()
            ->assertJsonPath('summary.balance', -400)
            ->assertJsonPath('entry.title', 'Utility Payment');

        $this->assertDatabaseHas('branch_cash_ledger_entries', [
            'id' => $entryId,
            'branch_id' => $branch->id,
            'direction' => 'out',
            'amount' => 400,
            'title' => 'Utility Payment',
            'is_system' => false,
        ]);

        $this->actingAs($manager)
            ->deleteJson("/panel/branches/{$branch->id}/cash-ledger/{$entryId}")
            ->assertOk()
            ->assertJsonPath('summary.balance', 0);

        $this->assertSoftDeleted('branch_cash_ledger_entries', [
            'id' => $entryId,
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/branches/{$branch->id}/cash-ledger")
            ->assertOk()
            ->assertJsonPath('entries.0.id', $entryId)
            ->assertJsonPath('entries.0.is_deleted', true);
    }

    public function test_resyncing_a_soft_deleted_walk_in_restores_the_original_system_entry(): void
    {
        $branch = $this->createBranch('Sorsogon');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Branch Manager');

        $walkInResponse = $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'branch_id' => $branch->id,
                'name' => 'Walk-in Leo',
                'amount_paid' => 350,
                'payment_method' => 'cash',
                'visited_at' => '2026-03-21 09:00:00',
            ])
            ->assertCreated();

        $walkInId = $walkInResponse->json('id');
        $entry = BranchCashLedgerEntry::query()
            ->where('entry_type', BranchCashLedgerEntry::TYPE_WALK_IN_SALE)
            ->where('source_id', $walkInId)
            ->firstOrFail();

        $this->actingAs($manager)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'branch_id' => $branch->id,
                'name' => 'Walk-in Leo',
                'amount_paid' => 350,
                'payment_method' => 'online_payment',
                'visited_at' => '2026-03-21 09:00:00',
            ])
            ->assertOk();

        $this->assertSoftDeleted('branch_cash_ledger_entries', [
            'id' => $entry->id,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'branch_id' => $branch->id,
                'name' => 'Walk-in Leo',
                'amount_paid' => 350,
                'payment_method' => 'cash',
                'visited_at' => '2026-03-21 09:00:00',
            ])
            ->assertOk();

        $restoredEntry = BranchCashLedgerEntry::withTrashed()
            ->where('entry_type', BranchCashLedgerEntry::TYPE_WALK_IN_SALE)
            ->where('source_id', $walkInId)
            ->get();

        $this->assertCount(1, $restoredEntry);
        $this->assertSame($entry->id, $restoredEntry->first()->id);
        $this->assertNull($restoredEntry->first()->deleted_at);

        $this->actingAs($manager)
            ->getJson("/panel/branches/{$branch->id}/cash-ledger")
            ->assertOk()
            ->assertJsonPath('summary.balance', 350)
            ->assertJsonPath('summary.cash_in_total', 350)
            ->assertJsonPath('summary.cash_out_total', 0);
    }

    public function test_walk_in_update_accepts_gcash_and_removes_the_cash_ledger_entry(): void
    {
        $branch = $this->createBranch('Masbate');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Branch Manager');

        $walkInId = $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'branch_id' => $branch->id,
                'name' => 'Walk-in Pax',
                'amount_paid' => 450,
                'payment_method' => 'cash',
                'visited_at' => '2026-03-22 08:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'branch_id' => $branch->id,
                'name' => 'Walk-in Pax',
                'amount_paid' => 450,
                'payment_method' => 'gcash',
                'visited_at' => '2026-03-22 08:00:00',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->getJson("/panel/branches/{$branch->id}/cash-ledger")
            ->assertOk()
            ->assertJsonPath('summary.balance', 0)
            ->assertJsonPath('summary.cash_in_total', 0);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => Branch::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
        ]);
    }

    private function attachPtProduct(Branch $branch, string $name, int $sessionCount, float $price): PTProduct
    {
        $product = PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'is_active' => true,
        ]);

        $branch->ptProducts()->attach($product->id, [
            'price' => $price,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        return $product;
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 450,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
