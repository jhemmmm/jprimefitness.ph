<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySchemaCompatibilityMigrationTest extends TestCase
{
    public function test_compatibility_migration_backfills_branch_scoped_schema(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'jprime-compat-');

        config()->set('database.connections.compat_legacy', [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('compat_legacy');

        $schema = Schema::connection('compat_legacy');
        $database = DB::connection('compat_legacy');

        $this->createLegacySchema($schema);
        $this->seedLegacyData($database);

        $originalDefaultConnection = config('database.default');

        config()->set('database.default', 'compat_legacy');

        try {
            $migration = require base_path('database/migrations/2026_04_08_182212_add_single_location_compatibility_layer.php');
            $migration->up();
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }

        $this->assertTrue($schema->hasTable('business_profiles'));
        $this->assertTrue($schema->hasTable('cash_ledger_entries'));
        $this->assertTrue($schema->hasColumn('member_pt_packages', 'coach_commission_rate'));
        $this->assertTrue($schema->hasColumn('member_pt_session_usages', 'coach_id'));
        $this->assertTrue($schema->hasColumn('cash_advances', 'audit_data'));

        $businessProfile = $database->table('business_profiles')->first();

        $this->assertNotNull($businessProfile);
        $this->assertSame(1, $businessProfile->id);
        $this->assertSame('Naga Branch', $businessProfile->name);
        $this->assertSame('PH', $businessProfile->country_code);

        $ratePlan = $database->table('rate_plans')->first();

        $this->assertNotNull($ratePlan);
        $this->assertSame(1499.0, (float) $ratePlan->price);
        $this->assertSame(12.5, (float) $ratePlan->manager_commission_rate);

        $ptProduct = $database->table('pt_products')->first();

        $this->assertNotNull($ptProduct);
        $this->assertSame(3600.0, (float) $ptProduct->price);
        $this->assertSame(40.0, (float) $ptProduct->coach_commission_rate);

        $cashLedgerEntry = $database->table('cash_ledger_entries')->first();

        $this->assertNotNull($cashLedgerEntry);
        $this->assertSame('manual_adjustment', $cashLedgerEntry->entry_type);
        $this->assertSame(1500.0, (float) $cashLedgerEntry->amount);

        $walkInId = $database->table('walk_ins')->insertGetId([
            'rate_plan_id' => 1,
            'served_by' => 1,
            'name' => 'Drop-in Dana',
            'phone' => null,
            'amount_paid' => 350,
            'payment_method' => 'cash',
            'visited_at' => '2026-04-08 09:00:00',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payrollId = $database->table('payrolls')->insertGetId([
            'employee_id' => 1,
            'pay_frequency' => 'monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'gross_amount' => 10000,
            'bonus' => 0,
            'income_tax' => 0,
            'employee_contributions' => null,
            'employer_contributions' => null,
            'pt_commission_amount' => 0,
            'pt_commission_items' => null,
            'membership_commission_amount' => 0,
            'membership_commission_items' => null,
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 10000,
            'status' => 'draft',
            'notes' => null,
            'generated_by' => 1,
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $walkInId);
        $this->assertGreaterThan(0, $payrollId);
        $this->assertNull($database->table('walk_ins')->where('id', $walkInId)->value('branch_id'));
        $this->assertNull($database->table('payrolls')->where('id', $payrollId)->value('branch_id'));

        DB::disconnect('compat_legacy');
        @unlink($databasePath);
    }

    private function createLegacySchema($schema): void
    {
        $schema->create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('status');
            $table->string('country_code', 2)->default('PH');
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('messenger_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('whatsapp_url')->nullable();
            $table->string('map_url')->nullable();
            $table->text('photos')->nullable();
            $table->string('opening_time')->nullable();
            $table->string('closing_time')->nullable();
            $table->text('amenities')->nullable();
            $table->text('operating_hours')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            $table->timestamps();
        });

        $schema->create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('duration_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $schema->create('branch_rate_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('rate_plan_id');
            $table->decimal('price', 10, 2);
            $table->decimal('manager_commission_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });

        $schema->create('pt_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('session_count');
            $table->string('category');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $schema->create('branch_pt_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('pt_product_id');
            $table->decimal('price', 10, 2);
            $table->decimal('coach_commission_rate', 5, 2)->default(40);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->string('password');
            $table->timestamps();
        });

        $schema->create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('rate_plan_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $schema->create('member_pt_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('pt_product_id');
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('remaining_sessions');
            $table->date('assigned_at');
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $schema->create('member_pt_session_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_pt_package_id');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->unsignedInteger('sessions_used')->default(1);
            $table->dateTime('used_at');
            $table->string('confirmed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $schema->create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('branch_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('manual_deductions', 10, 2)->default(0);
            $table->decimal('cash_advance_deduction', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('branch_id');
            $table->decimal('amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->string('status')->default('requested');
            $table->text('notes')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        $schema->create('walk_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('rate_plan_id')->nullable();
            $table->unsignedBigInteger('served_by')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->dateTime('visited_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $schema->create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('attendee_type');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('walk_in_id')->nullable();
            $table->string('name')->nullable();
            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $schema->create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('inventory_category_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('low_stock_threshold', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->dateTime('last_restocked_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sale_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('type');
            $table->decimal('total', 10, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->dateTime('sold_at');
            $table->string('customer_name')->nullable();
            $table->string('item_name');
            $table->text('details')->nullable();
            $table->timestamps();
        });

        $schema->create('branch_cash_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('entry_type', 50);
            $table->string('direction', 10);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->dateTime('occurred_at');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('metadata')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function seedLegacyData($database): void
    {
        $database->table('branches')->insert([
            'id' => 1,
            'name' => 'Naga Branch',
            'slug' => 'naga-branch',
            'status' => 'open',
            'country_code' => 'PH',
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
            'address' => 'Magsaysay Ave',
            'phone' => '09170000000',
            'email' => 'naga@example.test',
            'messenger_url' => null,
            'facebook_url' => null,
            'whatsapp_url' => null,
            'map_url' => null,
            'photos' => json_encode(['hero.jpg']),
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'amenities' => json_encode(['weights']),
            'operating_hours' => json_encode(['mon' => ['06:00-22:00']]),
            'timezone' => 'Asia/Manila',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $database->table('rate_plans')->insert([
            'id' => 1,
            'name' => 'Monthly',
            'duration_days' => 30,
            'is_active' => true,
            'description' => 'Monthly access',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $database->table('branch_rate_prices')->insert([
            'id' => 1,
            'branch_id' => 1,
            'rate_plan_id' => 1,
            'price' => 1499,
            'manager_commission_rate' => 12.5,
            'is_active' => true,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $database->table('pt_products')->insert([
            'id' => 1,
            'name' => '12 Sessions',
            'session_count' => 12,
            'category' => 'package',
            'is_active' => true,
            'description' => 'PT package',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $database->table('branch_pt_prices')->insert([
            'id' => 1,
            'branch_id' => 1,
            'pt_product_id' => 1,
            'price' => 3600,
            'coach_commission_rate' => 40,
            'is_active' => true,
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $database->table('users')->insert([
            'id' => 1,
            'name' => 'Manager Mia',
            'email' => 'manager@example.test',
            'phone' => '09171234567',
            'status' => 'active',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $database->table('branch_cash_ledger_entries')->insert([
            'id' => 1,
            'branch_id' => 1,
            'entry_type' => 'manual_adjustment',
            'direction' => 'in',
            'source_id' => null,
            'amount' => 1500,
            'occurred_at' => '2026-04-08 08:00:00',
            'title' => 'Opening cash',
            'description' => 'Seeded legacy entry',
            'metadata' => json_encode(['source' => 'legacy']),
            'is_system' => false,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
