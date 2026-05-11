<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
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
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/sales')
            ->assertOk()
            ->assertSee('sales-reports-page', false)
            ->assertSee('business-profile=', false);
    }

    public function test_sales_reports_data_returns_metrics_and_breakdowns(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        SaleTransaction::factory()->create([
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
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
            'customer_name' => 'Member Joy',
            'item_name' => 'Monthly Membership',
            'sold_at' => '2026-03-03 10:00:00',
        ]);

        SaleTransaction::factory()->create([
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'status' => SaleTransaction::STATUS_VOIDED,
            'total' => 999,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Voided Customer',
            'item_name' => 'Voided Shake',
            'sold_at' => '2026-03-04 10:00:00',
            'void_reason' => 'Duplicate sale.',
            'voided_by' => $staff->id,
            'voided_at' => '2026-03-04 10:05:00',
        ]);

        SaleTransaction::factory()->create([
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 300,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'customer_name' => 'Old Walk-in',
            'item_name' => 'Walk-in',
            'sold_at' => '2026-02-26 08:00:00',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/sales/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonMissingPath('scope.location');
        $response->assertJsonMissingPath('location_breakdown');
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('filters.type', []);
        $response->assertJsonPath('filters.payment_method', []);
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
        $response->assertJsonPath('daily_trend.0.sale_date', '2026-03-02');
        $response->assertJsonPath('daily_trend.1.sale_date', '2026-03-03');
        $response->assertJsonPath('top_items.0.name', 'Bottled Water');
        $response->assertJsonPath('top_items.0.quantity', 20);
        $response->assertJsonPath('transactions.data.0.customer_name', 'Member Joy');
        $response->assertJsonPath('transactions.data.1.customer_name', 'Counter Sale');
        $this->assertFalse(collect($response->json('transactions.data'))->contains('customer_name', 'Voided Customer'));
    }

    public function test_sales_reports_details_default_to_all_time_and_are_paginated(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        foreach ([
            ['Recent Customer', '2026-05-10 09:00:00'],
            ['Middle Customer', '2026-04-10 09:00:00'],
            ['Old Customer', '2026-01-10 09:00:00'],
        ] as [$customerName, $soldAt]) {
            SaleTransaction::factory()->create([
                'processed_by' => $staff->id,
                'type' => SaleTransaction::TYPE_INVENTORY,
                'total' => 500,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'customer_name' => $customerName,
                'item_name' => 'Day Pass',
                'sold_at' => $soldAt,
            ]);
        }

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/sales/data?per_page=2')
            ->assertOk();

        $response->assertJsonPath('filters.date_from', null);
        $response->assertJsonPath('filters.date_to', null);
        $response->assertJsonPath('summary.transaction_count', 3);
        $response->assertJsonPath('transactions.current_page', 1);
        $response->assertJsonPath('transactions.per_page', 2);
        $response->assertJsonPath('transactions.total', 3);
        $response->assertJsonPath('transactions.last_page', 2);
        $response->assertJsonPath('transactions.data.0.customer_name', 'Recent Customer');
        $response->assertJsonPath('transactions.data.1.customer_name', 'Middle Customer');

        $this->actingAs($staff)
            ->getJson('/panel/reports/sales/data?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('transactions.current_page', 2)
            ->assertJsonPath('transactions.data.0.customer_name', 'Old Customer');
    }

    public function test_report_date_presets_include_yesterday_and_reports_default_to_all_time(): void
    {
        $presets = file_get_contents(resource_path('js/mixins/dateRangePresets.js'));

        $this->assertStringContainsString('{ key: "yesterday", label: "Yesterday" }', $presets);
        $this->assertStringContainsString('yesterday: { from: daysAgoDate(1), to: daysAgoDate(1) }', $presets);

        foreach ([
            resource_path('js/components/panel/SalesReportsPage.vue'),
            resource_path('js/components/panel/AttendanceReportsPage.vue'),
            resource_path('js/components/panel/PayrollReportsPage.vue'),
        ] as $componentPath) {
            $contents = file_get_contents($componentPath);

            $this->assertStringContainsString("defaultDateFrom: function () {\n         return \"\";\n      }", $contents);
            $this->assertStringContainsString("defaultDateTo: function () {\n         return \"\";\n      }", $contents);
        }
    }

    public function test_sales_reports_can_be_exported_to_csv(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        SaleTransaction::factory()->create([
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

        SaleTransaction::factory()->create([
            'processed_by' => $staff->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1200,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
            'customer_name' => 'Old Member',
            'item_name' => 'Monthly Membership',
            'sold_at' => '2026-01-05 09:00:00',
        ]);

        $response = $this->actingAs($staff)
            ->get('/panel/reports/sales/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Sales Reports', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Sales by Type', $content);
        $this->assertStringContainsString('Transactions', $content);
        $this->assertStringContainsString('Bottled Water', $content);
        $this->assertStringContainsString('Old Member', $content);
        $this->assertStringNotContainsString('Location Totals', $content);
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
