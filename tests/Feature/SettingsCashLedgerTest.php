<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CashLedgerEntry;
use App\Models\PTProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsCashLedgerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');

        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));

        BusinessProfile::factory()->create();
    }

    public function test_pt_package_creation_does_not_inflate_cash_balance(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Ana');
        $member = $this->createUserWithRole('member', 'Member Ben');
        $product = $this->createPtProduct('12 Sessions', 12, 1200);

        $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'name' => 'Walk-in Pax',
                'amount_paid' => 350,
                'visited_at' => '2026-03-20 09:00:00',
            ])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'assigned_at' => '2026-03-20',
            ])
            ->assertCreated();

        $response = $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list')
            ->assertOk()
            ->assertJsonPath('summary.balance', 350)
            ->assertJsonPath('summary.cash_in_total', 350)
            ->assertJsonPath('summary.cash_out_total', 0);

        $entryTypes = collect($response->json('entries.data'))->pluck('entry_type')->all();

        $this->assertContains(CashLedgerEntry::TYPE_WALK_IN_SALE, $entryTypes);
        $this->assertNotContains(CashLedgerEntry::TYPE_PT_PACKAGE_SALE, $entryTypes);

        $this->assertDatabaseHas('cash_ledger_entries', [
            'entry_type' => CashLedgerEntry::TYPE_WALK_IN_SALE,
            'amount' => 350,
            'is_system' => true,
        ]);

        $this->assertDatabaseMissing('cash_ledger_entries', [
            'entry_type' => CashLedgerEntry::TYPE_PT_PACKAGE_SALE,
        ]);
    }

    public function test_staff_cannot_view_cash_ledger(): void
    {
        $staff = $this->createUserWithRole('staff', 'Front Desk Staff');

        $this->actingAs($staff)
            ->get('/panel/business/cash-ledger')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/business/cash-ledger/list')
            ->assertForbidden();

        $this->actingAs($staff)
            ->get('/panel/business/settings')
            ->assertOk()
            ->assertDontSee('/panel/business/cash-ledger');
    }

    public function test_business_pages_render_and_legacy_settings_route_redirects(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Bea');

        $this->actingAs($manager)
            ->get('/panel/business/cash-ledger')
            ->assertOk()
            ->assertSee('business-cash-ledger-page', false);

        $this->actingAs($manager)
            ->get('/panel/business/photos')
            ->assertOk()
            ->assertSee('business-photos-page', false);

        $this->actingAs($manager)
            ->get('/panel/business/settings')
            ->assertOk()
            ->assertSee('business-settings-page', false);

        $this->actingAs($manager)
            ->get('/panel/settings')
            ->assertRedirect('/panel/business/settings');
    }

    public function test_manager_can_enable_overwork_pay_in_business_settings(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Theo');
        $profile = BusinessProfile::current();

        $this->actingAs($manager)
            ->putJson('/panel/business/settings', [
                'name' => $profile->name,
                'status' => $profile->status,
                'country_code' => $profile->country_code,
                'pay_overwork_hours' => true,
                'city' => $profile->city,
                'province' => $profile->province,
                'address' => $profile->address,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'timezone' => $profile->timezone,
                'amenities' => $profile->amenities ?? [],
                'opening_time' => $profile->opening_time,
                'closing_time' => $profile->closing_time,
                'facebook_url' => $profile->facebook_url,
                'messenger_url' => $profile->messenger_url,
                'whatsapp_url' => $profile->whatsapp_url,
                'map_url' => $profile->map_url,
                'hero_badge' => $profile->hero_badge,
                'hero_title' => $profile->hero_title,
                'hero_highlight' => $profile->hero_highlight,
                'hero_description' => $profile->hero_description,
                'about_heading' => $profile->about_heading,
                'about_description' => $profile->about_description,
                'membership_note' => $profile->membership_note,
            ])
            ->assertOk()
            ->assertJsonPath('pay_overwork_hours', true);

        $this->assertDatabaseHas('business_profiles', [
            'id' => $profile->id,
            'pay_overwork_hours' => 1,
        ]);
    }

    public function test_cash_payouts_and_released_cash_advances_reduce_cash_balance(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $coach = $this->createUserWithRole('coach', 'Coach Lou');

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->json('id');

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

        $cashAdvanceId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/cash-advances", [
                'amount' => 300,
                'requested_at' => '2026-03-17 08:00:00',
            ])
            ->assertCreated()
            ->json('id');

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
            ->getJson('/panel/business/cash-ledger/list')
            ->assertOk()
            ->assertJsonPath('summary.balance', -1300)
            ->assertJsonPath('summary.cash_in_total', 0)
            ->assertJsonPath('summary.cash_out_total', 1300);

        $entryTypes = collect($response->json('entries.data'))->pluck('entry_type')->all();

        $this->assertContains(CashLedgerEntry::TYPE_PAYROLL_PAYOUT, $entryTypes);
        $this->assertContains(CashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE, $entryTypes);
    }

    public function test_manager_can_create_update_and_delete_manual_cash_ledger_entries(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Zoe');

        $entryId = $this->actingAs($manager)
            ->postJson('/panel/business/cash-ledger', [
                'direction' => 'in',
                'amount' => 2500,
                'occurred_at' => '2026-03-18 10:15:00',
                'title' => 'Opening Balance',
                'description' => 'Initial cash on hand',
            ])
            ->assertCreated()
            ->assertJsonPath('summary.balance', 2500)
            ->json('entry.id');

        $this->actingAs($manager)
            ->putJson("/panel/business/cash-ledger/{$entryId}", [
                'direction' => 'out',
                'amount' => 400,
                'occurred_at' => '2026-03-18 11:00:00',
                'title' => 'Utility Payment',
                'description' => 'Paid internet bill',
            ])
            ->assertOk()
            ->assertJsonPath('summary.balance', -400)
            ->assertJsonPath('entry.title', 'Utility Payment');

        $this->assertDatabaseHas('cash_ledger_entries', [
            'id' => $entryId,
            'direction' => 'out',
            'amount' => 400,
            'title' => 'Utility Payment',
            'is_system' => false,
        ]);

        $this->actingAs($manager)
            ->deleteJson("/panel/business/cash-ledger/{$entryId}")
            ->assertNoContent();

        $this->assertSoftDeleted('cash_ledger_entries', [
            'id' => $entryId,
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list')
            ->assertOk()
            ->assertJsonPath('summary.balance', 0)
            ->assertJsonPath('entries.data.0.id', $entryId)
            ->assertJsonPath('entries.data.0.is_deleted', true);
    }

    public function test_cash_ledger_filters_only_change_the_entries_list_not_the_summary_cards(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Faye');

        CashLedgerEntry::create([
            'entry_type' => CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashLedgerEntry::DIRECTION_IN,
            'amount' => 100,
            'occurred_at' => '2026-03-01 09:00:00',
            'title' => 'Opening Float',
            'description' => 'Cash drawer start',
            'metadata' => [],
            'is_system' => false,
            'created_by' => $manager->id,
        ]);

        CashLedgerEntry::create([
            'entry_type' => CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
            'direction' => CashLedgerEntry::DIRECTION_OUT,
            'amount' => 40,
            'occurred_at' => '2026-03-05 10:00:00',
            'title' => 'Cleaning Supplies',
            'description' => 'Bought sanitizer',
            'metadata' => [],
            'is_system' => false,
            'created_by' => $manager->id,
        ]);

        CashLedgerEntry::create([
            'entry_type' => CashLedgerEntry::TYPE_WALK_IN_SALE,
            'direction' => CashLedgerEntry::DIRECTION_IN,
            'amount' => 500,
            'occurred_at' => '2026-03-10 11:00:00',
            'title' => 'Walk-in payment',
            'description' => 'Guest One',
            'metadata' => [],
            'is_system' => true,
            'created_by' => $manager->id,
        ]);

        CashLedgerEntry::create([
            'entry_type' => CashLedgerEntry::TYPE_PAYROLL_PAYOUT,
            'direction' => CashLedgerEntry::DIRECTION_OUT,
            'amount' => 200,
            'occurred_at' => '2026-03-12 12:00:00',
            'title' => 'Payroll cash payout',
            'description' => 'Coach Lou',
            'metadata' => [],
            'is_system' => true,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list?date_from=2026-03-05&date_to=2026-03-10')
            ->assertOk()
            ->assertJsonCount(2, 'entries.data')
            ->assertJsonPath('summary.balance', 360)
            ->assertJsonPath('summary.cash_in_total', 600)
            ->assertJsonPath('summary.cash_out_total', 240);

        $directionResponse = $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list?direction=in')
            ->assertOk()
            ->assertJsonCount(2, 'entries.data');

        $this->assertSame(
            [CashLedgerEntry::TYPE_WALK_IN_SALE, CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT],
            collect($directionResponse->json('entries.data'))->pluck('entry_type')->all()
        );

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list?entry_type=walk_in_sale')
            ->assertOk()
            ->assertJsonCount(1, 'entries.data')
            ->assertJsonPath('entries.data.0.title', 'Walk-in payment')
            ->assertJsonPath('summary.balance', 360);

        $modeResponse = $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list?mode=manual')
            ->assertOk()
            ->assertJsonCount(2, 'entries.data')
            ->assertJsonPath('summary.balance', 360);

        $this->assertFalse((bool) $modeResponse->json('entries.data.0.is_system'));
        $this->assertFalse((bool) $modeResponse->json('entries.data.1.is_system'));

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list?search=Cleaning')
            ->assertOk()
            ->assertJsonCount(1, 'entries.data')
            ->assertJsonPath('entries.data.0.title', 'Cleaning Supplies')
            ->assertJsonPath('summary.balance', 360);
    }

    public function test_cash_ledger_list_is_paginated_by_default(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Lio');

        foreach (range(1, 25) as $index) {
            CashLedgerEntry::create([
                'entry_type' => CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
                'direction' => CashLedgerEntry::DIRECTION_IN,
                'amount' => 100 + $index,
                'occurred_at' => now()->subMinutes($index),
                'title' => "Entry {$index}",
                'description' => "Ledger entry {$index}",
                'metadata' => [],
                'is_system' => false,
                'created_by' => $manager->id,
            ]);
        }

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list')
            ->assertOk()
            ->assertJsonPath('entries.current_page', 1)
            ->assertJsonPath('entries.per_page', 20)
            ->assertJsonPath('entries.last_page', 2)
            ->assertJsonCount(20, 'entries.data');

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list?page=2')
            ->assertOk()
            ->assertJsonPath('entries.current_page', 2)
            ->assertJsonCount(5, 'entries.data');
    }

    public function test_resyncing_a_soft_deleted_walk_in_restores_the_original_system_entry(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Pax');

        $walkInId = $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'name' => 'Walk-in Leo',
                'amount_paid' => 350,
                'payment_method' => 'cash',
                'visited_at' => '2026-03-21 09:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $entry = CashLedgerEntry::query()
            ->where('entry_type', CashLedgerEntry::TYPE_WALK_IN_SALE)
            ->where('source_id', $walkInId)
            ->firstOrFail();

        $this->actingAs($manager)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'name' => 'Walk-in Leo',
                'amount_paid' => 350,
                'payment_method' => 'online_payment',
                'visited_at' => '2026-03-21 09:00:00',
            ])
            ->assertOk();

        $this->assertSoftDeleted('cash_ledger_entries', [
            'id' => $entry->id,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'name' => 'Walk-in Leo',
                'amount_paid' => 350,
                'payment_method' => 'cash',
                'visited_at' => '2026-03-21 09:00:00',
            ])
            ->assertOk();

        $restoredEntry = CashLedgerEntry::withTrashed()
            ->where('entry_type', CashLedgerEntry::TYPE_WALK_IN_SALE)
            ->where('source_id', $walkInId)
            ->get();

        $this->assertCount(1, $restoredEntry);
        $this->assertSame($entry->id, $restoredEntry->first()->id);
        $this->assertNull($restoredEntry->first()->deleted_at);

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list')
            ->assertOk()
            ->assertJsonPath('summary.balance', 350)
            ->assertJsonPath('summary.cash_in_total', 350)
            ->assertJsonPath('summary.cash_out_total', 0);
    }

    public function test_walk_in_update_accepts_gcash_and_removes_the_cash_ledger_entry(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Quin');

        $walkInId = $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'name' => 'Walk-in Pax',
                'amount_paid' => 450,
                'payment_method' => 'cash',
                'visited_at' => '2026-03-22 08:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'name' => 'Walk-in Pax',
                'amount_paid' => 450,
                'payment_method' => 'gcash',
                'visited_at' => '2026-03-22 08:00:00',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->getJson('/panel/business/cash-ledger/list')
            ->assertOk()
            ->assertJsonPath('summary.balance', 0)
            ->assertJsonPath('summary.cash_in_total', 0)
            ->assertJsonPath('entries.data.0.entry_type', CashLedgerEntry::TYPE_WALK_IN_SALE)
            ->assertJsonPath('entries.data.0.is_deleted', true);
    }

    private function createPtProduct(string $name, int $sessionCount, float $price): PTProduct
    {
        return PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'price' => $price,
            'coach_commission_rate' => 40,
            'is_active' => true,
            'description' => $name.' PT package',
            'effective_from' => '2026-03-01',
        ]);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 450,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
