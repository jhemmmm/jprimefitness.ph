<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\User;
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
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/attendance')
            ->assertOk()
            ->assertSee('attendance-reports-page', false)
            ->assertSee('business-profile=', false);
    }

    public function test_attendance_reports_data_returns_summary_and_breakdowns(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $member = $this->createUserWithRole('member', 'Member Joy');
        $employee = $this->createUserWithRole('staff', 'Employee Ben');
        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-03-02 08:00:00',
            'checked_out_at' => '2026-03-02 09:00:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $employee->id,
            'name' => $employee->name,
            'checked_in_at' => '2026-03-02 08:15:00',
            'checked_out_at' => null,
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Walk-in Kai',
            'checked_in_at' => '2026-03-03 11:00:00',
            'checked_out_at' => '2026-03-03 11:30:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Walk-in Lee',
            'checked_in_at' => '2026-03-04 12:00:00',
            'checked_out_at' => '2026-03-04 12:20:00',
            'recorded_by' => $staff->id,
        ]);

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-02-27 07:00:00',
            'checked_out_at' => '2026-02-27 08:00:00',
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/reports/attendance/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonMissingPath('scope.location');
        $response->assertJsonMissingPath('location_breakdown');
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('filters.type', []);
        $response->assertJsonPath('summary.total_check_ins', 4);
        $response->assertJsonPath('summary.unique_attendees', 4);
        $response->assertJsonPath('summary.checked_out_count', 3);
        $response->assertJsonPath('summary.currently_in_count', 1);
        $response->assertJsonPath('summary.average_visit_minutes', 36.67);
        $response->assertJsonPath('type_breakdown.0.type', Attendance::TYPE_MEMBER);
        $response->assertJsonPath('type_breakdown.0.check_in_count', 1);
        $response->assertJsonPath('type_breakdown.1.type', Attendance::TYPE_WALK_IN);
        $response->assertJsonPath('type_breakdown.1.check_in_count', 2);
        $response->assertJsonPath('type_breakdown.1.unique_attendees', 2);
        $response->assertJsonPath('type_breakdown.2.type', Attendance::TYPE_EMPLOYEE);
        $response->assertJsonPath('type_breakdown.2.currently_in_count', 1);
        $response->assertJsonPath('daily_trend.0.attendance_date', '2026-03-02');
        $response->assertJsonPath('daily_trend.0.check_in_count', 2);
        $response->assertJsonPath('daily_trend.1.attendance_date', '2026-03-03');
        $response->assertJsonPath('daily_trend.2.attendance_date', '2026-03-04');
        $response->assertJsonPath('busiest_hours.0.hour_slot', '08:00');
        $response->assertJsonPath('busiest_hours.0.check_in_count', 2);
        $response->assertJsonPath('recent_records.0.name', 'Walk-in Lee');
        $response->assertJsonPath('recent_records.1.name', 'Walk-in Kai');
    }

    public function test_attendance_reports_can_be_exported_to_csv(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'name' => 'Member Joy',
            'checked_in_at' => '2026-03-05 08:00:00',
            'checked_out_at' => '2026-03-05 09:00:00',
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->get('/panel/reports/attendance/export?date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Attendance Reports', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Attendance by Type', $content);
        $this->assertStringContainsString('Member Joy', $content);
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
