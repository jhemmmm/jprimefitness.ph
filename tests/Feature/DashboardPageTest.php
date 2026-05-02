<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\MemberSubscription;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-15 14:30:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('employee');
        Role::findOrCreate('member');
        Role::findOrCreate('coach');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_page_loads_for_panel_users(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertSee('dashboard-page', false)
            ->assertSee('business-profile=', false)
            ->assertSee('window.JPrime.timezone =', false);
    }

    public function test_dashboard_component_uses_single_business_sections_instead_of_location_cards(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/DashboardPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringNotContainsString('Location Load', $contents);
        $this->assertStringNotContainsString('Location Overview', $contents);
        $this->assertStringContainsString("Today's Operations", $contents);
        $this->assertStringContainsString('Business Snapshot', $contents);
        $this->assertStringNotContainsString('<th>Location</th>', $contents);
        $this->assertStringNotContainsString('bi-arrow-repeat', $contents);
        $this->assertStringNotContainsString('>Refresh<', $contents);
    }

    public function test_stat_cards_allow_wrapping_on_small_screen_layouts(): void
    {
        $contents = file_get_contents(resource_path('sass/panel.scss'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('white-space: normal;', $contents);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $contents);
        $this->assertStringContainsString('@media (max-width: 575.98px)', $contents);
    }

    public function test_dashboard_data_returns_single_location_metrics_for_managers(): void
    {
        $profile = $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffEmployee = $this->createUserWithRole('staff', 'Staff Ben');
        $coach = $this->createUserWithRole('coach', 'Coach Joy');
        $member = $this->createUserWithRole('member', 'Member Lea');
        $monthly = $this->createRatePlan('Monthly', 30);

        MemberSubscription::create([
            'user_id' => $member->id,
            'rate_plan_id' => $monthly->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-20',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        $walkIn = WalkIn::create([
            'served_by' => $manager->id,
            'name' => 'Walk-in Pia',
            'amount_paid' => 350,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'visited_at' => '2026-04-15 11:00:00',
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-04-15 08:00:00',
            'checked_out_at' => '2026-04-15 09:00:00',
            'recorded_by' => $manager->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $coach->id,
            'name' => $coach->name,
            'checked_in_at' => '2026-04-15 08:30:00',
            'checked_out_at' => null,
            'recorded_by' => $manager->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'walk_in_id' => $walkIn->id,
            'name' => $walkIn->name,
            'checked_in_at' => '2026-04-15 11:00:00',
            'checked_out_at' => '2026-04-15 11:30:00',
            'recorded_by' => $manager->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-04-05 11:00:00',
            'checked_out_at' => '2026-04-05 12:00:00',
            'recorded_by' => $manager->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'name' => 'March Visitor',
            'checked_in_at' => '2026-03-25 08:00:00',
            'checked_out_at' => '2026-03-25 09:00:00',
            'recorded_by' => $manager->id,
        ]);

        SaleTransaction::create([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 600,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-04-15 10:00:00',
            'customer_name' => $member->name,
            'item_name' => 'Monthly Membership',
        ]);

        SaleTransaction::create([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'total' => 800,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $manager->id,
            'sold_at' => '2026-04-03 09:00:00',
            'customer_name' => $member->name,
            'item_name' => '8 Sessions',
        ]);

        SaleTransaction::create([
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 999,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $staffEmployee->id,
            'sold_at' => '2026-03-20 09:00:00',
            'customer_name' => 'Old Sale',
            'item_name' => 'Protein Shake',
        ]);

        $payroll = Payroll::create([
            'employee_id' => $coach->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'gross_amount' => 1200,
            'bonus' => 0,
            'income_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 1200,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-04-15 12:00:00',
        ]);

        Payout::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $coach->id,
            'amount' => 200,
            'method' => Payout::METHOD_CASH,
            'reference_number' => null,
            'released_by' => $manager->id,
            'paid_at' => '2026-04-15 13:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/dashboard/data')
            ->assertOk();

        $response->assertJsonPath('scope.location.id', $profile->id);
        $response->assertJsonPath('scope.location.name', 'Naga');
        $response->assertJsonPath('permissions.can_view_financial_data', true);
        $response->assertJsonPath('stats_row_1.total_members', 1);
        $response->assertJsonPath('stats_row_1.check_ins_today', 3);
        $response->assertJsonPath('stats_row_1.revenue_today', 950);
        $response->assertJsonPath('stats_row_1.revenue_this_month', 1750);
        $response->assertJsonPath('stats_row_2.active_trainers', 1);
        $response->assertJsonPath('stats_row_2.active_employees', 2);
        $response->assertJsonPath('stats_row_2.walk_ins_today', 1);
        $response->assertJsonPath('stats_row_2.pending_payroll_balance', 1000);
        $response->assertJsonCount(24, 'peak_hours');
        $response->assertJsonPath('peak_hours.8.hour_slot', '08:00');
        $response->assertJsonPath('peak_hours.8.check_in_count', 2);
        $response->assertJsonPath('peak_hours.11.hour_slot', '11:00');
        $response->assertJsonPath('peak_hours.11.check_in_count', 2);
        $response->assertJsonPath('location_load.0.location_name', 'Naga');
        $response->assertJsonPath('location_load.0.current_occupancy', 1);
        $response->assertJsonPath('location_load.0.today_check_ins', 3);
        $response->assertJsonPath('check_ins_today.0.name', 'Walk-in Pia');
        $response->assertJsonPath('check_ins_today.0.plan_or_rate', 'Walk-in Rate');
        $response->assertJsonPath('recent_members.0.name', 'Member Lea');
        $response->assertJsonPath('recent_sales.0.item_name', 'Monthly Membership');
        $response->assertJsonPath('expiring_memberships.0.member_name', 'Member Lea');
        $response->assertJsonPath('pending_payrolls.0.employee_name', 'Coach Joy');
        $response->assertJsonPath('pending_payrolls.0.outstanding_balance', 1000);
    }

    public function test_dashboard_hides_financial_widgets_for_staff_roles(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'name' => 'Today Naga',
            'checked_in_at' => '2026-04-15 08:00:00',
            'checked_out_at' => null,
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Today Guest',
            'checked_in_at' => '2026-04-15 10:00:00',
            'checked_out_at' => '2026-04-15 11:00:00',
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/dashboard/data')
            ->assertOk();

        $payload = $response->json();

        $response->assertJsonPath('permissions.can_view_financial_data', false);
        $response->assertJsonPath('stats_row_1.check_ins_today', 2);
        $response->assertJsonPath('location_load.0.current_occupancy', 1);

        $this->assertArrayNotHasKey('pending_payrolls', $payload);
        $this->assertArrayNotHasKey('revenue_today', $payload['stats_row_1']);
        $this->assertArrayNotHasKey('revenue_this_month', $payload['stats_row_1']);
        $this->assertArrayNotHasKey('pending_payroll_balance', $payload['stats_row_2']);
        $this->assertSame(['Today Guest', 'Today Naga'], collect($payload['check_ins_today'])->pluck('name')->all());
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'price' => 1500,
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
