<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private bool $createdBusinessProfilesTable = false;

    public function up(): void
    {
        $primaryBranch = $this->resolvePrimaryBranch();

        $this->ensureBusinessProfilesTable();
        $this->backfillBusinessProfile($primaryBranch);
        $this->ensureRatePlanColumns();
        $this->backfillRatePlanPricing($primaryBranch?->id);
        $this->ensurePtProductColumns();
        $this->backfillPtProductPricing($primaryBranch?->id);
        $this->ensureUserColumns();
        $this->ensureMemberSubscriptionColumns();
        $this->ensureMemberPtPackageColumns();
        $this->ensureMemberPtSessionUsageColumns();
        $this->ensurePayrollColumns();
        $this->ensureCashAdvanceColumns();
        $this->ensureWalkInColumns();
        $this->ensureCashLedgerEntriesTable();
        $this->backfillCashLedgerEntries();
        $this->relaxLegacyBranchRequirements();
    }

    public function down(): void
    {
        // This migration is intentionally additive so existing installs can upgrade safely.
        // Rolling it back would remove compatibility columns and migrated data.
    }

    private function ensureBusinessProfilesTable(): void
    {
        if (Schema::hasTable('business_profiles')) {
            return;
        }

        $this->createdBusinessProfilesTable = true;

        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country_code', 2)->default('PH');
            $table->enum('status', ['open', 'closed', 'coming_soon'])->default('open');
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('messenger_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('whatsapp_url')->nullable();
            $table->string('map_url', 255)->nullable();
            $table->json('photos')->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->json('amenities')->nullable();
            $table->json('operating_hours')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            $table->string('hero_badge')->nullable();
            $table->string('hero_title')->nullable();
            $table->string('hero_highlight')->nullable();
            $table->text('hero_description')->nullable();
            $table->string('about_heading')->nullable();
            $table->text('about_description')->nullable();
            $table->text('membership_note')->nullable();
            $table->timestamps();
        });
    }

    private function backfillBusinessProfile(?object $primaryBranch): void
    {
        if (! $this->createdBusinessProfilesTable || ! Schema::hasTable('business_profiles')) {
            return;
        }

        if (DB::table('business_profiles')->exists()) {
            return;
        }

        $defaults = $this->defaultBusinessProfileAttributes();

        if ($primaryBranch === null) {
            DB::table('business_profiles')->insert($defaults);

            return;
        }

        DB::table('business_profiles')->insert([
            'id' => $primaryBranch->id,
            'name' => $primaryBranch->name ?? $defaults['name'],
            'country_code' => $this->recordValue($primaryBranch, 'country_code', $defaults['country_code']),
            'status' => $primaryBranch->status ?? $defaults['status'],
            'city' => $primaryBranch->city ?? $defaults['city'],
            'province' => $primaryBranch->province ?? $defaults['province'],
            'address' => $primaryBranch->address ?? null,
            'phone' => $primaryBranch->phone ?? null,
            'email' => $primaryBranch->email ?? null,
            'messenger_url' => $primaryBranch->messenger_url ?? null,
            'facebook_url' => $primaryBranch->facebook_url ?? null,
            'whatsapp_url' => $primaryBranch->whatsapp_url ?? null,
            'map_url' => $primaryBranch->map_url ?? null,
            'photos' => $this->jsonValue($this->recordValue($primaryBranch, 'photos', [])),
            'opening_time' => $primaryBranch->opening_time ?? $defaults['opening_time'],
            'closing_time' => $primaryBranch->closing_time ?? $defaults['closing_time'],
            'amenities' => $this->jsonValue($primaryBranch->amenities ?? []),
            'operating_hours' => $this->jsonValue($primaryBranch->operating_hours ?? []),
            'timezone' => $primaryBranch->timezone ?? $defaults['timezone'],
            'hero_badge' => $defaults['hero_badge'],
            'hero_title' => $defaults['hero_title'],
            'hero_highlight' => $defaults['hero_highlight'],
            'hero_description' => $defaults['hero_description'],
            'about_heading' => $defaults['about_heading'],
            'about_description' => $defaults['about_description'],
            'membership_note' => $defaults['membership_note'],
            'created_at' => $primaryBranch->created_at ?? now(),
            'updated_at' => $primaryBranch->updated_at ?? now(),
        ]);
    }

    private function ensureRatePlanColumns(): void
    {
        if (! Schema::hasTable('rate_plans')) {
            return;
        }

        Schema::table('rate_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('rate_plans', 'price')) {
                $table->decimal('price', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('rate_plans', 'manager_commission_rate')) {
                $table->decimal('manager_commission_rate', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('rate_plans', 'effective_from')) {
                $table->date('effective_from')->nullable();
            }

            if (! Schema::hasColumn('rate_plans', 'effective_until')) {
                $table->date('effective_until')->nullable();
            }
        });
    }

    private function backfillRatePlanPricing(?int $primaryBranchId): void
    {
        if (! Schema::hasTable('rate_plans') || ! Schema::hasTable('branch_rate_prices')) {
            return;
        }

        $branchPrices = DB::table('branch_rate_prices')
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get()
            ->groupBy('rate_plan_id');

        if ($branchPrices->isEmpty()) {
            return;
        }

        DB::table('rate_plans')
            ->select('id', 'is_active')
            ->orderBy('id')
            ->get()
            ->each(function (object $ratePlan) use ($branchPrices, $primaryBranchId): void {
                $priceRow = $this->preferredBranchScopedRow($branchPrices, $ratePlan->id, $primaryBranchId);

                if ($priceRow === null) {
                    return;
                }

                DB::table('rate_plans')
                    ->where('id', $ratePlan->id)
                    ->update([
                        'price' => $priceRow->price,
                        'manager_commission_rate' => $this->recordValue($priceRow, 'manager_commission_rate', 0),
                        'is_active' => (bool) $ratePlan->is_active && (bool) ($priceRow->is_active ?? true),
                        'effective_from' => $priceRow->effective_from ?? null,
                        'effective_until' => $priceRow->effective_until ?? null,
                    ]);
            });
    }

    private function ensurePtProductColumns(): void
    {
        if (! Schema::hasTable('pt_products')) {
            return;
        }

        Schema::table('pt_products', function (Blueprint $table) {
            if (! Schema::hasColumn('pt_products', 'price')) {
                $table->decimal('price', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('pt_products', 'coach_commission_rate')) {
                $table->decimal('coach_commission_rate', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('pt_products', 'effective_from')) {
                $table->date('effective_from')->nullable();
            }

            if (! Schema::hasColumn('pt_products', 'effective_until')) {
                $table->date('effective_until')->nullable();
            }
        });
    }

    private function backfillPtProductPricing(?int $primaryBranchId): void
    {
        if (! Schema::hasTable('pt_products') || ! Schema::hasTable('branch_pt_prices')) {
            return;
        }

        $branchPrices = DB::table('branch_pt_prices')
            ->orderBy('branch_id')
            ->orderBy('id')
            ->get()
            ->groupBy('pt_product_id');

        if ($branchPrices->isEmpty()) {
            return;
        }

        DB::table('pt_products')
            ->select('id', 'is_active')
            ->orderBy('id')
            ->get()
            ->each(function (object $ptProduct) use ($branchPrices, $primaryBranchId): void {
                $priceRow = $this->preferredBranchScopedRow($branchPrices, $ptProduct->id, $primaryBranchId);

                if ($priceRow === null) {
                    return;
                }

                DB::table('pt_products')
                    ->where('id', $ptProduct->id)
                    ->update([
                        'price' => $priceRow->price,
                        'coach_commission_rate' => $this->recordValue($priceRow, 'coach_commission_rate', 40),
                        'is_active' => (bool) $ptProduct->is_active && (bool) ($priceRow->is_active ?? true),
                        'effective_from' => $priceRow->effective_from ?? null,
                        'effective_until' => $priceRow->effective_until ?? null,
                    ]);
            });
    }

    private function ensureUserColumns(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'daily_rate')) {
                $table->decimal('daily_rate', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('users', 'pay_frequency')) {
                $table->string('pay_frequency', 20)->nullable();
            }
        });
    }

    private function ensureMemberSubscriptionColumns(): void
    {
        if (! Schema::hasTable('member_subscriptions')) {
            return;
        }

        Schema::table('member_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('member_subscriptions', 'sold_price')) {
                $table->decimal('sold_price', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('member_subscriptions', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable();
            }

            if (! Schema::hasColumn('member_subscriptions', 'manager_commission_rate')) {
                $table->decimal('manager_commission_rate', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('member_subscriptions', 'manager_commission_amount')) {
                $table->decimal('manager_commission_amount', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('member_subscriptions', 'manager_commission_status')) {
                $table->string('manager_commission_status')->default('unassigned');
            }

            if (! Schema::hasColumn('member_subscriptions', 'manager_commission_earned_at')) {
                $table->dateTime('manager_commission_earned_at')->nullable();
            }

            if (! Schema::hasColumn('member_subscriptions', 'commission_payroll_id')) {
                $table->unsignedBigInteger('commission_payroll_id')->nullable();
            }

            if (! Schema::hasColumn('member_subscriptions', 'expiration_notification_sent_for_date')) {
                $table->date('expiration_notification_sent_for_date')->nullable();
            }
        });
    }

    private function ensureMemberPtPackageColumns(): void
    {
        if (! Schema::hasTable('member_pt_packages')) {
            return;
        }

        Schema::table('member_pt_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('member_pt_packages', 'sold_price')) {
                $table->decimal('sold_price', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('member_pt_packages', 'coach_commission_rate')) {
                $table->decimal('coach_commission_rate', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('member_pt_packages', 'coach_commission_amount')) {
                $table->decimal('coach_commission_amount', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('member_pt_packages', 'coach_id')) {
                $table->unsignedBigInteger('coach_id')->nullable();
            }

            if (! Schema::hasColumn('member_pt_packages', 'coach_commission_status')) {
                $table->string('coach_commission_status')->default('unassigned');
            }

            if (! Schema::hasColumn('member_pt_packages', 'coach_commission_earned_at')) {
                $table->dateTime('coach_commission_earned_at')->nullable();
            }

            if (! Schema::hasColumn('member_pt_packages', 'commission_payroll_id')) {
                $table->unsignedBigInteger('commission_payroll_id')->nullable();
            }
        });
    }

    private function ensureMemberPtSessionUsageColumns(): void
    {
        if (! Schema::hasTable('member_pt_session_usages') || Schema::hasColumn('member_pt_session_usages', 'coach_id')) {
            return;
        }

        Schema::table('member_pt_session_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->nullable();
        });
    }

    private function ensurePayrollColumns(): void
    {
        if (! Schema::hasTable('payrolls')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'pay_frequency')) {
                $table->string('pay_frequency', 20)->nullable();
            }

            if (! Schema::hasColumn('payrolls', 'income_tax')) {
                $table->decimal('income_tax', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('payrolls', 'employee_contributions')) {
                $table->json('employee_contributions')->nullable();
            }

            if (! Schema::hasColumn('payrolls', 'employer_contributions')) {
                $table->json('employer_contributions')->nullable();
            }

            if (! Schema::hasColumn('payrolls', 'pt_commission_amount')) {
                $table->decimal('pt_commission_amount', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('payrolls', 'pt_commission_items')) {
                $table->json('pt_commission_items')->nullable();
            }

            if (! Schema::hasColumn('payrolls', 'membership_commission_amount')) {
                $table->decimal('membership_commission_amount', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('payrolls', 'membership_commission_items')) {
                $table->json('membership_commission_items')->nullable();
            }
        });
    }

    private function ensureCashAdvanceColumns(): void
    {
        if (! Schema::hasTable('cash_advances') || Schema::hasColumn('cash_advances', 'audit_data')) {
            return;
        }

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->json('audit_data')->nullable();
        });
    }

    private function ensureWalkInColumns(): void
    {
        if (! Schema::hasTable('walk_ins') || Schema::hasColumn('walk_ins', 'payment_method')) {
            return;
        }

        Schema::table('walk_ins', function (Blueprint $table) {
            $table->string('payment_method', 50)->default('cash');
        });
    }

    private function ensureCashLedgerEntriesTable(): void
    {
        if (Schema::hasTable('cash_ledger_entries')) {
            return;
        }

        Schema::create('cash_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_type', 50);
            $table->string('direction', 10);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['occurred_at', 'is_system'], 'cash_ledger_occurred_system_idx');
            $table->index(['entry_type', 'source_id'], 'cash_ledger_entry_source_idx');
        });
    }

    private function backfillCashLedgerEntries(): void
    {
        if (! Schema::hasTable('cash_ledger_entries') || ! Schema::hasTable('branch_cash_ledger_entries')) {
            return;
        }

        if (DB::table('cash_ledger_entries')->exists()) {
            return;
        }

        $hasDeletedAt = Schema::hasColumn('branch_cash_ledger_entries', 'deleted_at');

        DB::table('branch_cash_ledger_entries')
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows) use ($hasDeletedAt): void {
                $payload = $rows->map(function (object $row) use ($hasDeletedAt): array {
                    return [
                        'id' => $row->id,
                        'entry_type' => $row->entry_type,
                        'direction' => $row->direction,
                        'source_id' => $row->source_id,
                        'amount' => $row->amount,
                        'occurred_at' => $row->occurred_at,
                        'title' => $row->title,
                        'description' => $row->description,
                        'metadata' => $this->jsonValue($row->metadata ?? null),
                        'is_system' => (bool) $row->is_system,
                        'created_by' => $row->created_by,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                        'deleted_at' => $hasDeletedAt ? ($row->deleted_at ?? null) : null,
                    ];
                })->all();

                DB::table('cash_ledger_entries')->insert($payload);
            }, 'id');
    }

    private function relaxLegacyBranchRequirements(): void
    {
        $tables = [
            'walk_ins',
            'attendances',
            'payrolls',
            'cash_advances',
            'inventory_items',
            'sale_transactions',
            'member_pt_packages',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->change();
            });
        }
    }

    private function resolvePrimaryBranch(): ?object
    {
        if (! Schema::hasTable('branches')) {
            return null;
        }

        return DB::table('branches')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    private function preferredBranchScopedRow(Collection $rowsByRecordId, int $recordId, ?int $primaryBranchId): ?object
    {
        /** @var \Illuminate\Support\Collection<int, object> $rows */
        $rows = $rowsByRecordId->get($recordId, collect());

        if ($rows->isEmpty()) {
            return null;
        }

        if ($primaryBranchId !== null) {
            $preferred = $rows->firstWhere('branch_id', $primaryBranchId);

            if ($preferred !== null) {
                return $preferred;
            }
        }

        return $rows->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBusinessProfileAttributes(): array
    {
        return [
            'name' => 'JPrime Fitness',
            'country_code' => 'PH',
            'status' => 'open',
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
            'address' => null,
            'phone' => null,
            'email' => null,
            'messenger_url' => null,
            'facebook_url' => null,
            'whatsapp_url' => null,
            'map_url' => null,
            'photos' => $this->jsonValue([]),
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'amenities' => $this->jsonValue([]),
            'operating_hours' => $this->jsonValue([]),
            'timezone' => 'Asia/Manila',
            'hero_badge' => 'Single-location gym',
            'hero_title' => 'Train with purpose.',
            'hero_highlight' => 'One location. One standard.',
            'hero_description' => 'Clean facilities, straightforward pricing, and coaching that keeps the focus on real progress.',
            'about_heading' => 'Fitness that fits your goals.',
            'about_description' => 'JPrime Fitness is built around a simple promise: a clean, safe, and results-driven training space that stays accessible to everyday members.',
            'membership_note' => 'Membership, walk-in access, and PT pricing are managed from one central profile.',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function jsonValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function recordValue(object $record, string $property, mixed $default = null): mixed
    {
        return property_exists($record, $property) ? $record->{$property} : $default;
    }
};
