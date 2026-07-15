<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CashDrawerSession;
use App\Models\CashLedgerEntry;
use App\Models\Payroll;
use App\Models\Payout;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\CashDrawerService;
use App\Services\PosSaleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CashDrawerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('manager');
        Role::findOrCreate('staff');

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness',
        ]);
    }

    public function test_management_can_open_session_and_double_open_is_rejected(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/panel/cash-drawer/open', ['opening_float' => 1000])
            ->assertCreated();

        $this->assertDatabaseHas('cash_drawer_sessions', [
            'is_open' => true,
            'opening_float' => '1000.00',
            'opened_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson('/panel/cash-drawer/open', ['opening_float' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opening_float']);
    }

    public function test_cash_sale_creates_in_entry_attached_to_open_session(): void
    {
        $session = CashDrawerSession::factory()->create();

        $sale = SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'total' => 350,
        ]);

        $this->assertDatabaseHas('cash_ledger_entries', [
            'session_id' => $session->id,
            'type' => CashLedgerEntry::TYPE_SALE,
            'amount' => '350.00',
            'source_type' => 'sale_transaction',
            'source_id' => $sale->id,
        ]);
    }

    public function test_non_cash_sale_creates_no_entry(): void
    {
        CashDrawerSession::factory()->create();

        SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
        ]);

        $this->assertDatabaseCount('cash_ledger_entries', 0);
    }

    public function test_voiding_cash_sale_creates_reversal_and_original_entry_remains(): void
    {
        $manager = $this->createUserWithRole('manager');
        CashDrawerSession::factory()->create();

        $sale = SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'total' => 350,
        ]);

        app(PosSaleService::class)->voidSale($sale, $manager, 'Wrong item');

        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => CashLedgerEntry::TYPE_SALE,
            'source_id' => $sale->id,
            'amount' => '350.00',
        ]);
        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => CashLedgerEntry::TYPE_SALE_VOID,
            'source_id' => $sale->id,
            'amount' => '-350.00',
        ]);
    }

    public function test_cash_payout_creates_out_entry_and_bank_transfer_does_not(): void
    {
        CashDrawerSession::factory()->create();
        $manager = $this->createUserWithRole('manager');
        $employee = $this->createUserWithRole('staff');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'gross_amount' => 4000,
            'withholding_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 4000,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $cashPayout = $payroll->payouts()->create([
            'employee_id' => $employee->id,
            'amount' => 1500,
            'method' => Payout::METHOD_CASH,
            'released_by' => $manager->id,
            'paid_at' => now(),
        ]);
        $payroll->payouts()->create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'method' => Payout::METHOD_BANK_TRANSFER,
            'released_by' => $manager->id,
            'paid_at' => now(),
        ]);

        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => CashLedgerEntry::TYPE_PAYOUT,
            'source_type' => 'payout',
            'source_id' => $cashPayout->id,
            'amount' => '-1500.00',
        ]);
        $this->assertSame(1, CashLedgerEntry::query()->where('type', CashLedgerEntry::TYPE_PAYOUT)->count());
    }

    public function test_sale_without_open_session_is_recorded_but_excluded_from_expected_cash(): void
    {
        SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'total' => 200,
        ]);

        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => CashLedgerEntry::TYPE_SALE,
            'session_id' => null,
        ]);

        $session = CashDrawerSession::factory()->create(['opening_float' => 1000]);

        $this->assertSame(1000.0, app(CashDrawerService::class)->expectedCash($session));
    }

    public function test_close_session_math_and_suggested_next_float(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/panel/cash-drawer/open', ['opening_float' => 1000])
            ->assertCreated();

        SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'total' => 350,
        ]);

        $this->actingAs($manager)
            ->postJson('/panel/cash-drawer/expenses', [
                'category' => 'supplies',
                'description' => 'Cleaning supplies',
                'amount' => 200,
            ])
            ->assertCreated();

        $response = $this->actingAs($manager)
            ->postJson('/panel/cash-drawer/close', [
                'counted_cash' => 1100,
                'deposited_amount' => 1000,
                'deposit_reference' => 'DEP-001',
            ])
            ->assertOk()
            ->assertJsonPath('expected_cash', 1150)
            ->assertJsonPath('counted_cash', 1100)
            ->assertJsonPath('over_short', -50)
            ->assertJsonPath('deposited_amount', 1000);

        $this->assertDatabaseHas('cash_drawer_sessions', [
            'id' => $response->json('id'),
            'is_open' => null,
            'expected_cash' => '1150.00',
            'over_short' => '-50.00',
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/cash-drawer/data')
            ->assertOk()
            ->assertJsonPath('session', null)
            ->assertJsonPath('suggested_float', 100);
    }

    public function test_expense_requires_valid_category_and_positive_amount(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/panel/cash-drawer/expenses', [
                'category' => 'invalid-category',
                'description' => 'Something',
                'amount' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'amount']);
    }

    public function test_kiosk_style_cash_sale_without_processor_creates_entry(): void
    {
        CashDrawerSession::factory()->create();

        $sale = SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => null,
            'total' => 60,
        ]);

        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => CashLedgerEntry::TYPE_SALE,
            'source_id' => $sale->id,
            'recorded_by' => null,
        ]);
    }

    public function test_staff_gets_403_on_all_cash_drawer_routes(): void
    {
        $staff = $this->createUserWithRole('staff');

        $this->actingAs($staff)->get('/panel/cash-drawer')->assertForbidden();
        $this->actingAs($staff)->getJson('/panel/cash-drawer/data')->assertForbidden();
        $this->actingAs($staff)->postJson('/panel/cash-drawer/open', ['opening_float' => 100])->assertForbidden();
        $this->actingAs($staff)->postJson('/panel/cash-drawer/expenses', [])->assertForbidden();
        $this->actingAs($staff)->postJson('/panel/cash-drawer/close', [])->assertForbidden();
        $this->actingAs($staff)->getJson('/panel/cash-drawer/sessions')->assertForbidden();
        $this->actingAs($staff)->getJson('/panel/cash-drawer/entries')->assertForbidden();
        $this->actingAs($staff)->getJson('/panel/cash-drawer/expense-summary')->assertForbidden();
    }

    public function test_source_entry_recording_is_idempotent(): void
    {
        $service = app(CashDrawerService::class);

        $service->recordSourceEntry(CashLedgerEntry::TYPE_SALE, 350, 'sale_transaction', 99, 'Sale', null, now());
        $service->recordSourceEntry(CashLedgerEntry::TYPE_SALE, 350, 'sale_transaction', 99, 'Sale', null, now());

        $this->assertDatabaseCount('cash_ledger_entries', 1);
    }

    public function test_expense_summary_groups_by_category(): void
    {
        $manager = $this->createUserWithRole('manager');

        CashLedgerEntry::factory()->create(['category' => 'supplies', 'amount' => -200, 'recorded_by' => $manager->id]);
        CashLedgerEntry::factory()->create(['category' => 'supplies', 'amount' => -100, 'recorded_by' => $manager->id]);
        CashLedgerEntry::factory()->create(['category' => 'rent', 'amount' => -5000, 'recorded_by' => $manager->id]);

        $this->actingAs($manager)
            ->getJson('/panel/cash-drawer/expense-summary?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('total', 5300)
            ->assertJsonPath('categories.0.category', 'rent')
            ->assertJsonPath('categories.0.total', 5000)
            ->assertJsonPath('categories.1.category', 'supplies')
            ->assertJsonPath('categories.1.total', 300);
    }

    public function test_expense_receipt_image_is_stored_and_served_to_management_only(): void
    {
        Storage::fake();

        $manager = $this->createUserWithRole('manager');
        $staff = $this->createUserWithRole('staff');

        $response = $this->actingAs($manager)
            ->post('/panel/cash-drawer/expenses', [
                'category' => 'utilities',
                'description' => 'Electric bill',
                'amount' => 2500,
                'receipt' => UploadedFile::fake()->image('bill.jpg'),
            ])
            ->assertCreated();

        $entryId = $response->json('id');
        $entry = CashLedgerEntry::findOrFail($entryId);

        $this->assertNotNull($entry->receipt_path);
        Storage::assertExists($entry->receipt_path);
        $this->assertSame(route('panel.cash-drawer.entries.receipt', $entry), $response->json('receipt_url'));

        $this->actingAs($manager)->get("/panel/cash-drawer/entries/{$entryId}/receipt")->assertOk();
        $this->actingAs($staff)->get("/panel/cash-drawer/entries/{$entryId}/receipt")->assertForbidden();
    }

    public function test_expense_rejects_non_image_receipt(): void
    {
        Storage::fake();

        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->withHeader('Accept', 'application/json')
            ->post('/panel/cash-drawer/expenses', [
                'category' => 'utilities',
                'description' => 'Electric bill',
                'amount' => 2500,
                'receipt' => UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receipt']);
    }

    public function test_disabled_feature_hides_routes_and_stops_recording(): void
    {
        config(['jprime.cash_drawer' => false]);

        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)->get('/panel/cash-drawer')->assertNotFound();
        $this->actingAs($manager)->postJson('/panel/cash-drawer/open', ['opening_float' => 1000])->assertNotFound();

        SaleTransaction::factory()->create([
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
        ]);

        $this->assertDatabaseCount('cash_ledger_entries', 0);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
