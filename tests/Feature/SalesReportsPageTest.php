<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesReportsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');
    }

    public function test_sales_reports_page_loads_for_panel_users(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/sales')
            ->assertOk()
            ->assertSee('sales-reports-page', false);
    }

    public function test_sales_reports_data_returns_branch_metrics_and_breakdowns(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        SaleTransaction::factory()->create([
            'branch_id' => $branch->id,
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 2000,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Counter Sale',
            'item_name' => '20 inventory items',
            'sold_at' => '2026-03-02 09:00:00',
            'details' => [
                'line_items' => [[
                    'name' => 'Bottled Water',
                    'quantity' => 20,
                    'line_total' => 2000,
                ]],
            ],
        ]);

        SaleTransaction::factory()->create([
            'branch_id' => $branch->id,
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
            'customer_name' => 'Member Joy',
            'item_name' => 'Monthly Membership',
            'sold_at' => '2026-03-03 10:00:00',
        ]);

        SaleTransaction::factory()->create([
            'branch_id' => $branch->id,
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 300,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Old Walk-in',
            'item_name' => 'Walk-in',
            'sold_at' => '2026-02-26 08:00:00',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/sales/data?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.branch.id', $branch->id);
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('filters.type', null);
        $response->assertJsonPath('filters.payment_method', null);
        $response->assertJsonPath('summary.total_sales', 3500);
        $response->assertJsonPath('summary.transaction_count', 2);
        $response->assertJsonPath('summary.average_sale', 1750);
        $response->assertJsonPath('summary.cash_sales', 2000);
        $response->assertJsonPath('type_breakdown.0.type', SaleTransaction::TYPE_INVENTORY);
        $response->assertJsonPath('type_breakdown.0.total_sales', 2000);
        $response->assertJsonPath('type_breakdown.1.type', SaleTransaction::TYPE_MEMBERSHIP);
        $response->assertJsonPath('type_breakdown.1.total_sales', 1500);
        $response->assertJsonPath('payment_breakdown.0.payment_method', SaleTransaction::PAYMENT_METHOD_CASH);
        $response->assertJsonPath('payment_breakdown.0.total_sales', 2000);
        $response->assertJsonPath('payment_breakdown.1.payment_method', SaleTransaction::PAYMENT_METHOD_GCASH);
        $response->assertJsonPath('payment_breakdown.1.total_sales', 1500);
        $response->assertJsonPath('branch_breakdown.0.branch_name', 'Naga');
        $response->assertJsonPath('branch_breakdown.0.total_sales', 3500);
        $response->assertJsonPath('daily_trend.0.sale_date', '2026-03-02');
        $response->assertJsonPath('daily_trend.1.sale_date', '2026-03-03');
        $response->assertJsonPath('top_items.0.name', 'Bottled Water');
        $response->assertJsonPath('top_items.0.quantity', 20);
        $response->assertJsonPath('recent_transactions.0.customer_name', 'Member Joy');
        $response->assertJsonPath('recent_transactions.1.customer_name', 'Counter Sale');
    }

    public function test_sales_reports_can_be_exported_to_csv(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        SaleTransaction::factory()->create([
            'branch_id' => $branch->id,
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 850,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Counter Sale',
            'item_name' => 'Bottled Water',
            'sold_at' => '2026-03-05 09:00:00',
            'details' => [
                'line_items' => [[
                    'name' => 'Bottled Water',
                    'quantity' => 10,
                    'line_total' => 850,
                ]],
            ],
        ]);

        $response = $this->actingAs($staff)
            ->get('/panel/reports/sales/export?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Sales Reports', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Sales by Type', $content);
        $this->assertStringContainsString('Bottled Water', $content);
    }

    public function test_sales_reports_can_aggregate_all_accessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $outside = $this->createBranch('Outside');
        $staff = $this->createUserWithRole('staff', [$naga->id, $legazpi->id], 'Staff Ana');
        $outsideStaff = $this->createUserWithRole('staff', [$outside->id], 'Staff Bea');

        SaleTransaction::factory()->create([
            'branch_id' => $naga->id,
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1000,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Member One',
            'item_name' => 'Monthly',
            'sold_at' => '2026-03-10 10:00:00',
        ]);

        SaleTransaction::factory()->create([
            'branch_id' => $legazpi->id,
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CARD,
            'customer_name' => 'Walk-in Two',
            'item_name' => 'Walk-in',
            'sold_at' => '2026-03-11 11:00:00',
        ]);

        SaleTransaction::factory()->create([
            'branch_id' => $outside->id,
            'processed_by' => $outsideStaff->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 9000,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Hidden Sale',
            'item_name' => 'Protein Shake',
            'sold_at' => '2026-03-12 12:00:00',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/sales/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.is_all_branches', true);
        $response->assertJsonPath('summary.total_sales', 1500);
        $response->assertJsonPath('summary.transaction_count', 2);
        $response->assertJsonCount(2, 'branch_breakdown');
        $response->assertJsonMissing(['branch_name' => 'Outside']);
    }

    public function test_sales_reports_branch_filter_rejects_inaccessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$naga->id], 'Staff Ana');

        $this->actingAs($staff)
            ->getJson('/panel/reports/sales/data?branch='.$legazpi->id)
            ->assertForbidden();
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

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
