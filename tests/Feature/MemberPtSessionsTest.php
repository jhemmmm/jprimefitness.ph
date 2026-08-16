<?php

namespace Tests\Feature;

use App\Models\MemberPtPackage;
use App\Models\Payroll;
use App\Models\PTProduct;
use App\Models\SaleTransaction;
use App\Models\SystemActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MemberPtSessionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('coach');
        Role::findOrCreate('member');
    }

    public function test_manager_can_add_pt_package_for_member(): void
    {
        $product = $this->createPtProduct('12 Sessions', 12);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-04-01 09:30:00',
                'notes' => 'Paid in full at the front desk.',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.total_sessions', 12)
            ->assertJsonPath('member_pt_packages.0.remaining_sessions', 12)
            ->assertJsonPath('member_pt_packages.0.coach.name', $coach->name)
            ->assertJsonPath('member_pt_packages.0.sold_price', 500);

        $this->assertDatabaseHas('member_pt_packages', [
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);

        $package = MemberPtPackage::query()->where('user_id', $member->id)->firstOrFail();

        $this->assertNotNull($package->sale_transaction_id);
        $this->assertDatabaseHas('sale_transactions', [
            'id' => $package->sale_transaction_id,
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'status' => SaleTransaction::STATUS_COMPLETED,
            'total' => 500,
        ]);
    }

    public function test_manager_cannot_sell_pt_package_without_a_coach(): void
    {
        $product = $this->createPtProduct('12 Sessions', 12);
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'assigned_at' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['coach_id']);

        $this->assertDatabaseCount('member_pt_packages', 0);
        $this->assertDatabaseCount('sale_transactions', 0);
    }

    public function test_staff_can_log_pt_session_usage_and_reduce_balance(): void
    {
        $product = $this->createPtProduct('12 Sessions', 12);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $staff = $this->createUserWithRole('staff');
        $member = $this->createMember();

        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 12,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 2,
                'used_at' => '2026-04-02 09:30:00',
                'confirmed_by' => 'Maria Santos',
                'notes' => 'Upper body session',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.remaining_sessions', 10);

        $this->assertDatabaseHas('member_pt_session_usages', [
            'member_pt_package_id' => $package->id,
            'recorded_by' => $staff->id,
            'coach_id' => $coach->id,
            'sessions_used' => 2,
            'confirmed_by' => 'Maria Santos',
        ]);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'remaining_sessions' => 10,
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);
    }

    public function test_consuming_last_session_marks_package_as_consumed(): void
    {
        $product = $this->createPtProduct('Per Session', 1);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $staff = $this->createUserWithRole('staff');
        $member = $this->createMember();

        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'total_sessions' => 1,
            'remaining_sessions' => 1,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 1,
                'used_at' => '2026-04-02 09:30:00',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.status', MemberPtPackage::STATUS_CONSUMED);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'remaining_sessions' => 0,
            'status' => MemberPtPackage::STATUS_CONSUMED,
        ]);
    }

    public function test_logging_usage_with_a_late_coach_assignment_updates_the_package_coach(): void
    {
        $product = $this->createPtProduct('Per Session', 1);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $staff = $this->createUserWithRole('staff');
        $member = $this->createMember();

        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'sold_price' => 500,
            'total_sessions' => 1,
            'remaining_sessions' => 1,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'coach_id' => $coach->id,
                'sessions_used' => 1,
                'used_at' => '2026-04-02 09:30:00',
            ])
            ->assertCreated()
            ->assertJsonPath('member_pt_packages.0.coach.name', $coach->name);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'coach_id' => $coach->id,
        ]);

        $this->assertDatabaseHas('member_pt_session_usages', [
            'member_pt_package_id' => $package->id,
            'coach_id' => $coach->id,
        ]);
    }

    public function test_package_cannot_be_assigned_to_a_non_coach_user(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $staff = $this->createUserWithRole('staff');
        $member = $this->createMember();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'coach_id' => $staff->id,
                'assigned_at' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['coach_id']);
    }

    public function test_manager_can_void_an_unused_pt_package_sale_from_member_details(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-04-01 09:30:00',
            ])
            ->assertCreated();

        $package = MemberPtPackage::query()->where('user_id', $member->id)->firstOrFail();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Member requested a refund.',
            ])
            ->assertOk()
            ->assertJsonPath('member_pt_packages.0.status', MemberPtPackage::STATUS_CANCELLED)
            ->assertJsonPath('member_pt_packages.0.cancellation_reason', 'Member requested a refund.');

        $package->refresh();

        $this->assertSame(MemberPtPackage::STATUS_CANCELLED, $package->status);
        $this->assertSame('Member requested a refund.', $package->cancellation_reason);
        $this->assertSame($manager->id, $package->cancelled_by);
        $this->assertNotNull($package->cancelled_at);
        $this->assertSame(
            SaleTransaction::STATUS_VOIDED,
            SaleTransaction::query()->findOrFail($package->sale_transaction_id)->status,
        );
        $this->assertDatabaseHas('system_activities', [
            'subject_type' => SystemActivity::SUBJECT_MEMBER_PT_PACKAGE,
            'subject_id' => $package->id,
            'event' => 'cancelled',
            'actor_user_id' => $manager->id,
        ]);
    }

    public function test_used_pt_package_sale_cannot_be_cancelled_from_member_details(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
            ])
            ->assertCreated();

        $package = MemberPtPackage::query()->where('user_id', $member->id)->firstOrFail();
        $package->consumeSessions(1, '2026-04-02', $manager->id);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Member requested a refund.',
            ])
            ->assertConflict();

        $this->assertSame(MemberPtPackage::STATUS_ACTIVE, $package->fresh()->status);
        $this->assertSame(
            SaleTransaction::STATUS_COMPLETED,
            SaleTransaction::query()->findOrFail($package->sale_transaction_id)->status,
        );
    }

    public function test_draft_payroll_blocks_pt_sale_void_until_the_payroll_is_cancelled(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();
        $package = $this->sellPtPackage($manager, $coach, $member, $product);
        $payroll = $this->createCommissionPayroll($package, $manager, Payroll::STATUS_DRAFT);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Member requested a refund.',
            ])
            ->assertConflict()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, "draft payroll #{$payroll->id}"));

        $this->assertSame(MemberPtPackage::STATUS_ACTIVE, $package->fresh()->status);
        $this->assertSame(SaleTransaction::STATUS_COMPLETED, $package->saleTransaction()->firstOrFail()->status);

        $payroll->update(['status' => Payroll::STATUS_CANCELED]);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Member requested a refund.',
            ])
            ->assertOk();
    }

    public function test_finalized_payroll_blocks_pt_sale_void(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();
        $package = $this->sellPtPackage($manager, $coach, $member, $product);
        $payroll = $this->createCommissionPayroll($package, $manager, Payroll::STATUS_APPROVED);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Member requested a refund.',
            ])
            ->assertConflict()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, "finalized payroll #{$payroll->id}"));

        $this->assertSame(MemberPtPackage::STATUS_ACTIVE, $package->fresh()->status);
        $this->assertSame(SaleTransaction::STATUS_COMPLETED, $package->saleTransaction()->firstOrFail()->status);
    }

    public function test_manager_can_cancel_a_legacy_unlinked_pt_package_without_deleting_it(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();
        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Legacy entry was recorded by mistake.',
            ])
            ->assertOk();

        $this->assertModelExists($package);
        $this->assertSame(MemberPtPackage::STATUS_CANCELLED, $package->fresh()->status);
        $this->assertNull($package->fresh()->sale_transaction_id);
    }

    public function test_pt_package_cancellation_requires_management_and_a_reason(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $staff = $this->createUserWithRole('staff');
        $member = $this->createMember();
        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-packages/{$package->id}/cancel", [
                'reason' => 'Not authorized.',
            ])
            ->assertForbidden();

        $this->assertSame(MemberPtPackage::STATUS_ACTIVE, $package->fresh()->status);
    }

    public function test_existing_pt_package_sales_are_backfilled_with_an_explicit_link(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();
        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);
        $saleTransaction = SaleTransaction::create([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-04-01 09:30:00',
            'customer_name' => $member->name,
            'item_name' => $product->name,
            'details' => [
                'member_pt_package_id' => $package->id,
            ],
        ]);

        $migration = require database_path('migrations/2026_08_16_082308_backfill_member_pt_package_sale_transaction_links.php');
        $migration->up();

        $this->assertSame($saleTransaction->id, $package->fresh()->sale_transaction_id);
    }

    public function test_existing_voided_pt_sales_backfill_package_cancellation_audit(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();
        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
        ]);
        $saleTransaction = SaleTransaction::create([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'status' => SaleTransaction::STATUS_VOIDED,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-04-01 09:30:00',
            'customer_name' => $member->name,
            'item_name' => $product->name,
            'details' => [
                'member_pt_package_id' => $package->id,
            ],
            'void_reason' => 'Historical refund.',
            'voided_by' => $manager->id,
            'voided_at' => '2026-04-02 10:00:00',
        ]);

        $migration = require database_path('migrations/2026_08_16_082308_backfill_member_pt_package_sale_transaction_links.php');
        $migration->up();
        $package->refresh();

        $this->assertSame($saleTransaction->id, $package->sale_transaction_id);
        $this->assertSame(MemberPtPackage::STATUS_CANCELLED, $package->status);
        $this->assertSame('Historical refund.', $package->cancellation_reason);
        $this->assertSame($manager->id, $package->cancelled_by);
        $this->assertNotNull($package->cancelled_at);
    }

    public function test_backfill_rollback_does_not_clear_sale_links_created_after_deployment(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $coach = $this->createUserWithRole('coach');
        $member = $this->createMember();
        $package = $this->sellPtPackage($manager, $coach, $member, $product);
        $saleTransactionId = $package->sale_transaction_id;
        $migration = require database_path('migrations/2026_08_16_082308_backfill_member_pt_package_sale_transaction_links.php');

        $migration->down();

        $this->assertSame($saleTransactionId, $package->fresh()->sale_transaction_id);
    }

    public function test_sessions_used_cannot_exceed_remaining_balance(): void
    {
        $product = $this->createPtProduct('Per Session', 1);
        $staff = $this->createUserWithRole('staff');
        $member = $this->createMember();

        $package = $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'total_sessions' => 1,
            'remaining_sessions' => 1,
            'assigned_at' => '2026-04-01',
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 2,
                'used_at' => '2026-04-02 09:30:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sessions_used']);

        $this->assertDatabaseHas('member_pt_packages', [
            'id' => $package->id,
            'remaining_sessions' => 1,
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);
    }

    public function test_members_list_includes_pt_packages_and_profile_notes(): void
    {
        $product = $this->createPtProduct('8 Sessions', 8);
        $manager = $this->createUserWithRole('manager');
        $member = $this->createMember();

        $member->profile()->create([
            'notes' => 'Prefers morning PT sessions.',
        ]);

        $member->memberPtPackages()->create([
            'pt_product_id' => $product->id,
            'total_sessions' => 8,
            'remaining_sessions' => 6,
            'assigned_at' => '2026-04-01',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/members/list')
            ->assertOk()
            ->assertJsonPath('members.data.0.profile.notes', 'Prefers morning PT sessions.')
            ->assertJsonPath('members.data.0.member_pt_packages.0.total_sessions', 8)
            ->assertJsonPath('members.data.0.member_pt_packages.0.remaining_sessions', 6)
            ->assertJsonPath('members.data.0.member_pt_packages.0.pt_product.name', '8 Sessions');
    }

    private function createPtProduct(string $name, int $sessionCount): PTProduct
    {
        return PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'price' => 500,
            'is_active' => true,
        ]);
    }

    private function sellPtPackage(User $manager, User $coach, User $member, PTProduct $product): MemberPtPackage
    {
        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $product->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-04-01 09:30:00',
            ])
            ->assertCreated();

        return MemberPtPackage::query()
            ->where('user_id', $member->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function createCommissionPayroll(MemberPtPackage $package, User $manager, string $status): Payroll
    {
        return Payroll::create([
            'employee_id' => $package->coach_id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'commission_amount' => 200,
            'commission_details' => [[
                'sale_transaction_id' => $package->sale_transaction_id,
                'member_pt_package_id' => $package->id,
                'date' => '2026-04-01',
                'sold_price' => 500,
                'rate' => 40,
                'amount' => 200,
            ]],
            'gross_amount' => 200,
            'net_amount' => 200,
            'status' => $status,
            'generated_by' => $manager->id,
            'approved_by' => $status === Payroll::STATUS_DRAFT ? null : $manager->id,
            'approved_at' => $status === Payroll::STATUS_DRAFT ? null : now(),
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createMember(): User
    {
        return $this->createUserWithRole('member');
    }
}
