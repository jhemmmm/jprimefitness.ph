<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CashAdvanceRequest;
use App\Models\CashDrawerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CashAdvanceRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
        CashDrawerSession::factory()->create(['opened_at' => '2026-09-18 08:00:00']);
    }

    public function test_employee_can_request_and_managers_are_notified_without_money_moving(): void
    {
        $coach = $this->createUserWithRole('coach', 'Coach Theo');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $admin = $this->createUserWithRole('admin', 'Admin Abe');
        $staff = $this->createUserWithRole('staff', 'Staff Sam');

        $response = $this->actingAs($coach)
            ->postJson("/panel/employees/{$coach->id}/cash-advance-requests", [
                'amount' => 2000,
                'reason' => 'Tuition fee',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('amount', 2000);

        $this->assertDatabaseHas('cash_advance_requests', ['id' => $response->json('id'), 'employee_id' => $coach->id, 'status' => 'pending']);
        $this->assertDatabaseCount('cash_advances', 0);
        $this->assertSame(0, DB::table('cash_ledger_entries')->count());

        $this->assertSame(['cash-advance-requested'], $this->notificationTypesFor($manager));
        $this->assertSame(['cash-advance-requested'], $this->notificationTypesFor($admin));
        $this->assertSame([], $this->notificationTypesFor($staff));
        $this->assertSame([], $this->notificationTypesFor($coach));
        $this->assertStringContainsString('Coach Theo requested a ₱2,000.00 cash advance: Tuition fee', $manager->notifications()->first()->data['message']);

        // one pending request at a time
        $this->actingAs($coach)
            ->postJson("/panel/employees/{$coach->id}/cash-advance-requests", ['amount' => 500, 'reason' => 'More'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        // only for yourself
        $this->actingAs($staff)
            ->postJson("/panel/employees/{$coach->id}/cash-advance-requests", ['amount' => 500, 'reason' => 'Not mine'])
            ->assertForbidden();
        $this->actingAs($staff)->getJson("/panel/employees/{$coach->id}/cash-advance-requests")->assertForbidden();
        $this->actingAs($manager)->getJson("/panel/employees/{$coach->id}/cash-advance-requests")->assertOk()->assertJsonCount(1);
    }

    public function test_manager_approval_records_the_cash_advance_and_notifies_the_employee(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $request = CashAdvanceRequest::factory()->create(['employee_id' => $staff->id, 'amount' => 1500, 'reason' => 'Medical']);

        $this->actingAs($staff)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/approve", ['method' => 'cash'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/approve", [
                'method' => 'cash',
                'paid_at' => '2026-09-18 10:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('reviewed_by_name', 'Manager Mia');

        $this->assertDatabaseHas('cash_advances', [
            'employee_id' => $staff->id,
            'amount' => 1500,
            'method' => 'cash',
            'released_by' => $manager->id,
            'notes' => 'Medical',
        ]);
        $this->assertSame(1, DB::table('cash_ledger_entries')->where('type', 'cash_advance')->count());
        $this->assertNotNull($request->fresh()->cash_advance_id);
        $this->assertNull($request->fresh()->review_note, 'the requester\'s reason must not be stored as the reviewer\'s note');
        $this->assertSame(['cash-advance-request-reviewed'], $this->notificationTypesFor($staff));
        $this->assertStringContainsString('approved by Manager Mia and released via cash', $staff->notifications()->first()->data['message']);

        // already reviewed
        $this->actingAs($manager)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/reject", ['reason' => 'late'])
            ->assertStatus(409);

        // the employee's own cash advance list now shows it
        $this->actingAs($staff)->getJson("/panel/employees/{$staff->id}/cash-advances")->assertOk()->assertJsonCount(1)->assertJsonPath('0.amount', 1500);
    }

    public function test_manager_rejection_requires_a_reason_and_notifies_the_employee(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $request = CashAdvanceRequest::factory()->create(['employee_id' => $staff->id]);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/reject", ['reason' => 'Payroll is in three days.'])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('review_note', 'Payroll is in three days.');

        $this->assertDatabaseCount('cash_advances', 0);
        $this->assertStringContainsString('rejected by Manager Mia: Payroll is in three days.', $staff->notifications()->first()->data['message']);

        // rejected → employee may request again
        $this->actingAs($staff)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests", ['amount' => 800, 'reason' => 'Retry'])
            ->assertCreated();
    }

    public function test_employee_can_withdraw_own_pending_request_and_managers_cannot_review_their_own(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $other = $this->createUserWithRole('staff', 'Staff Ben');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $request = CashAdvanceRequest::factory()->create(['employee_id' => $staff->id]);

        $this->actingAs($other)->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/withdraw")->assertForbidden();
        $this->actingAs($staff)->postJson("/panel/employees/{$other->id}/cash-advance-requests/{$request->id}/withdraw")->assertForbidden();

        $this->actingAs($staff)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('status', 'withdrawn');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$staff->id}/cash-advance-requests/{$request->id}/approve", ['method' => 'cash'])
            ->assertStatus(409);

        $own = CashAdvanceRequest::factory()->create(['employee_id' => $manager->id]);
        $this->actingAs($manager)
            ->postJson("/panel/employees/{$manager->id}/cash-advance-requests/{$own->id}/approve", ['method' => 'cash'])
            ->assertForbidden();
    }

    public function test_dashboard_tile_counts_pending_requests(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        CashAdvanceRequest::factory()->create(['employee_id' => $staff->id]);

        $this->actingAs($staff)
            ->getJson('/panel/my-dashboard/data')
            ->assertOk()
            ->assertJsonPath('stats.pending_cash_advance_requests', 1)
            ->assertJsonPath('stats.cash_advance_balance', 0);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile()->create(['name' => $name, 'status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function notificationTypesFor(User $user): array
    {
        return $user->notifications()->pluck('type')->all();
    }
}
