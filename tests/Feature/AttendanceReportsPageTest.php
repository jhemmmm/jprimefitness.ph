<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceReportsPageTest extends TestCase
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

    public function test_attendance_reports_page_loads_for_panel_users(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/attendance')
            ->assertOk()
            ->assertSee('attendance-reports-page', false);
    }

    public function test_attendance_reports_data_returns_summary_and_breakdowns(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Joy');
        $employee = $this->createUserWithRole('staff', [$branch->id], 'Employee Ben');
        $walkIn = WalkIn::create([
            'branch_id' => $branch->id,
            'served_by' => $staff->id,
            'name' => 'Walk-in Kai',
            'amount_paid' => 350,
            'payment_method' => 'cash',
            'visited_at' => '2026-03-03 11:00:00',
        ]);

        Attendance::create([
            'branch_id' => $branch->id,
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-03-02 08:00:00',
            'checked_out_at' => '2026-03-02 09:00:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'branch_id' => $branch->id,
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $employee->id,
            'name' => $employee->name,
            'checked_in_at' => '2026-03-02 08:15:00',
            'checked_out_at' => null,
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'branch_id' => $branch->id,
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'walk_in_id' => $walkIn->id,
            'name' => $walkIn->name,
            'checked_in_at' => '2026-03-03 11:00:00',
            'checked_out_at' => '2026-03-03 11:30:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'branch_id' => $branch->id,
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => $walkIn->name,
            'checked_in_at' => '2026-03-04 12:00:00',
            'checked_out_at' => '2026-03-04 12:20:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'branch_id' => $branch->id,
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-02-27 07:00:00',
            'checked_out_at' => '2026-02-27 08:00:00',
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/attendance/data?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.branch.id', $branch->id);
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('filters.type', null);
        $response->assertJsonPath('summary.total_check_ins', 4);
        $response->assertJsonPath('summary.unique_attendees', 3);
        $response->assertJsonPath('summary.checked_out_count', 3);
        $response->assertJsonPath('summary.currently_in_count', 1);
        $response->assertJsonPath('summary.average_visit_minutes', 36.67);
        $response->assertJsonPath('type_breakdown.0.type', Attendance::TYPE_MEMBER);
        $response->assertJsonPath('type_breakdown.0.check_in_count', 1);
        $response->assertJsonPath('type_breakdown.1.type', Attendance::TYPE_WALK_IN);
        $response->assertJsonPath('type_breakdown.1.check_in_count', 2);
        $response->assertJsonPath('type_breakdown.1.unique_attendees', 1);
        $response->assertJsonPath('type_breakdown.2.type', Attendance::TYPE_EMPLOYEE);
        $response->assertJsonPath('type_breakdown.2.currently_in_count', 1);
        $response->assertJsonPath('branch_breakdown.0.branch_name', 'Naga');
        $response->assertJsonPath('branch_breakdown.0.check_in_count', 4);
        $response->assertJsonPath('daily_trend.0.attendance_date', '2026-03-02');
        $response->assertJsonPath('daily_trend.0.check_in_count', 2);
        $response->assertJsonPath('daily_trend.1.attendance_date', '2026-03-03');
        $response->assertJsonPath('daily_trend.2.attendance_date', '2026-03-04');
        $response->assertJsonPath('busiest_hours.0.hour_slot', '08:00');
        $response->assertJsonPath('busiest_hours.0.check_in_count', 2);
        $response->assertJsonPath('recent_records.0.name', 'Walk-in Kai');
        $response->assertJsonPath('recent_records.1.name', 'Walk-in Kai');
    }

    public function test_attendance_reports_can_be_exported_to_csv(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        Attendance::create([
            'branch_id' => $branch->id,
            'attendee_type' => Attendance::TYPE_MEMBER,
            'name' => 'Member Joy',
            'checked_in_at' => '2026-03-05 08:00:00',
            'checked_out_at' => '2026-03-05 09:00:00',
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->get('/panel/reports/attendance/export?branch='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Attendance Reports', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Attendance by Type', $content);
        $this->assertStringContainsString('Member Joy', $content);
    }

    public function test_attendance_reports_can_aggregate_all_accessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $outside = $this->createBranch('Outside');
        $staff = $this->createUserWithRole('staff', [$naga->id, $legazpi->id], 'Staff Ana');
        $outsideStaff = $this->createUserWithRole('staff', [$outside->id], 'Staff Bea');

        Attendance::create([
            'branch_id' => $naga->id,
            'attendee_type' => Attendance::TYPE_MEMBER,
            'name' => 'Member One',
            'checked_in_at' => '2026-03-10 08:00:00',
            'checked_out_at' => '2026-03-10 09:00:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'branch_id' => $legazpi->id,
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Walk-in Two',
            'checked_in_at' => '2026-03-11 10:00:00',
            'checked_out_at' => null,
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'branch_id' => $outside->id,
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'name' => 'Hidden Employee',
            'checked_in_at' => '2026-03-12 12:00:00',
            'checked_out_at' => null,
            'recorded_by' => $outsideStaff->id,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/attendance/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonPath('scope.is_all_branches', true);
        $response->assertJsonPath('summary.total_check_ins', 2);
        $response->assertJsonCount(2, 'branch_breakdown');
        $response->assertJsonMissing(['branch_name' => 'Outside']);
    }

    public function test_attendance_reports_branch_filter_rejects_inaccessible_branches(): void
    {
        $naga = $this->createBranch('Naga');
        $legazpi = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$naga->id], 'Staff Ana');

        $this->actingAs($staff)
            ->getJson('/panel/reports/attendance/data?branch='.$legazpi->id)
            ->assertNotFound();
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
