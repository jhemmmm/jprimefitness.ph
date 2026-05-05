<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollReportsPageTest extends TestCase
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
    }

    public function test_payroll_reports_page_loads_for_manager_roles(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');

        $this->actingAs($manager)
            ->get('/panel/reports/payroll')
            ->assertOk()
            ->assertSee('payroll-reports-page', false)
            ->assertSee('business-profile=', false);
    }

    public function test_payroll_reports_stat_cards_render_even_eight_card_grid(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/PayrollReportsPage.vue'));

        $this->assertSame(1, preg_match('/statCards: function \(\) \{\s+return \[(.*?)\];\s+\},\s+chartStatusBreakdown/s', $contents, $matches));

        $statCards = $matches[1];

        $this->assertSame(7, preg_match_all('/label: "/', $statCards));
        $this->assertStringContainsString('Payroll Runs', $statCards);
        $this->assertStringContainsString('Gross Payroll', $statCards);
        $this->assertStringContainsString('Withholding Tax', $statCards);
        $this->assertStringContainsString("Employee Gov't Contributions", $statCards);
        $this->assertStringContainsString('Net Payroll', $statCards);
        $this->assertStringContainsString('Paid Out To Date', $statCards);
        $this->assertStringContainsString('Outstanding To Date', $statCards);
        $this->assertStringNotContainsString("Employer Gov't Contributions", $statCards);
    }

    public function test_payroll_reports_data_returns_summary_and_breakdowns(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');
        $employeeA = $this->createUserWithRole('staff', 'Juan Dela Cruz', 'semi_monthly');
        $employeeB = $this->createUserWithRole('staff', 'Maria Santos', 'monthly');

        $payrollA = Payroll::create([
            'employee_id' => $employeeA->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1000,
            'withholding_tax' => 100,
            'employee_contributions' => [
                'sss' => [
                    'label' => 'SSS',
                    'total' => 400,
                    'lines' => [
                        'regular_ss' => [
                            'label' => 'Regular SS',
                            'amount' => 400,
                        ],
                    ],
                ],
                'philhealth' => [
                    'label' => 'PhilHealth',
                    'total' => 200,
                    'lines' => [
                        'premium' => [
                            'label' => 'Premium',
                            'amount' => 200,
                        ],
                    ],
                ],
            ],
            'employer_contributions' => [
                'sss' => [
                    'label' => 'SSS',
                    'total' => 650,
                    'lines' => [
                        'regular_ss' => [
                            'label' => 'Regular SS',
                            'amount' => 620,
                        ],
                        'ec' => [
                            'label' => "Employees' Compensation",
                            'amount' => 30,
                        ],
                    ],
                ],
                'philhealth' => [
                    'label' => 'PhilHealth',
                    'total' => 300,
                    'lines' => [
                        'premium' => [
                            'label' => 'Premium',
                            'amount' => 300,
                        ],
                    ],
                ],
            ],
            'manual_deductions' => 50,
            'net_amount' => 1050,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-03-16 09:00:00',
        ]);

        $payrollB = Payroll::create([
            'employee_id' => $employeeB->id,
            'pay_frequency' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'gross_amount' => 2000,
            'withholding_tax' => 0,
            'employee_contributions' => [
                'pagibig' => [
                    'label' => 'Pag-IBIG',
                    'total' => 100,
                    'lines' => [
                        'premium' => [
                            'label' => 'Premium',
                            'amount' => 100,
                        ],
                    ],
                ],
                'philhealth' => [
                    'label' => 'PhilHealth',
                    'total' => 200,
                    'lines' => [
                        'premium' => [
                            'label' => 'Premium',
                            'amount' => 200,
                        ],
                    ],
                ],
            ],
            'employer_contributions' => [
                'pagibig' => [
                    'label' => 'Pag-IBIG',
                    'total' => 100,
                    'lines' => [
                        'premium' => [
                            'label' => 'Premium',
                            'amount' => 100,
                        ],
                    ],
                ],
                'philhealth' => [
                    'label' => 'PhilHealth',
                    'total' => 350,
                    'lines' => [
                        'premium' => [
                            'label' => 'Premium',
                            'amount' => 350,
                        ],
                    ],
                ],
            ],
            'manual_deductions' => 0,
            'net_amount' => 2000,
            'status' => Payroll::STATUS_PAID,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-03-31 17:00:00',
        ]);

        Payroll::create([
            'employee_id' => $employeeB->id,
            'pay_frequency' => 'monthly',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'gross_amount' => 999,
            'withholding_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 999,
            'status' => Payroll::STATUS_CANCELED,
            'generated_by' => $manager->id,
        ]);

        Payout::create([
            'payroll_id' => $payrollA->id,
            'employee_id' => $employeeA->id,
            'amount' => 500,
            'method' => Payout::METHOD_CASH,
            'reference_number' => null,
            'released_by' => $manager->id,
            'paid_at' => '2026-03-16 10:00:00',
        ]);

        Payout::create([
            'payroll_id' => $payrollB->id,
            'employee_id' => $employeeB->id,
            'amount' => 2000,
            'method' => Payout::METHOD_BANK_TRANSFER,
            'reference_number' => 'BT-123',
            'released_by' => $manager->id,
            'paid_at' => '2026-03-31 18:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/panel/reports/payroll/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $response->assertJsonMissingPath('scope.location');
        $response->assertJsonMissingPath('location_breakdown');
        $response->assertJsonPath('filters.date_from', '2026-03-01');
        $response->assertJsonPath('filters.date_to', '2026-03-31');
        $response->assertJsonPath('summary.payroll_count', 2);
        $response->assertJsonPath('summary.gross_payroll', 3000);
        $response->assertJsonPath('summary.withholding_tax', 100);
        $response->assertJsonPath('summary.employee_government_contributions', 900);
        $response->assertJsonPath('summary.employer_government_contributions', 1400);
        $response->assertJsonPath('summary.total_deductions', 1050);
        $response->assertJsonPath('summary.net_payroll', 3050);
        $response->assertJsonPath('summary.total_paid', 2500);
        $response->assertJsonPath('summary.outstanding_balance', 550);
        $response->assertJsonPath('status_breakdown.0.status', Payroll::STATUS_APPROVED);
        $response->assertJsonPath('status_breakdown.0.net_payroll', 1050);
        $response->assertJsonPath('status_breakdown.1.status', Payroll::STATUS_PAID);
        $response->assertJsonPath('pay_frequency_breakdown.0.pay_frequency', 'semi_monthly');
        $response->assertJsonPath('pay_frequency_breakdown.1.pay_frequency', 'monthly');
        $response->assertJsonPath('payout_method_breakdown.0.method', Payout::METHOD_BANK_TRANSFER);
        $response->assertJsonPath('payout_method_breakdown.0.total_paid', 2000);
        $response->assertJsonPath('payroll_trend.0.period_end', '2026-03-15');
        $response->assertJsonPath('payroll_trend.1.period_end', '2026-03-31');
        $response->assertJsonPath('recent_payrolls.0.employee_name', 'Maria Santos');
        $response->assertJsonPath('recent_payrolls.0.total_paid', 2000);
    }

    public function test_payroll_reports_can_be_exported_to_csv(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Payroll Manager');
        $employee = $this->createUserWithRole('staff', 'Juan Dela Cruz');

        Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 1400,
            'withholding_tax' => 75,
            'employee_contributions' => [
                'sss' => [
                    'label' => 'SSS',
                    'total' => 500,
                    'lines' => [
                        'regular_ss' => [
                            'label' => 'Regular SS',
                            'amount' => 500,
                        ],
                    ],
                ],
            ],
            'employer_contributions' => [
                'sss' => [
                    'label' => 'SSS',
                    'total' => 800,
                    'lines' => [
                        'regular_ss' => [
                            'label' => 'Regular SS',
                            'amount' => 770,
                        ],
                        'ec' => [
                            'label' => "Employees' Compensation",
                            'amount' => 30,
                        ],
                    ],
                ],
            ],
            'manual_deductions' => 50,
            'net_amount' => 1375,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-03-16 09:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->get('/panel/reports/payroll/export?date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Payroll Reports', $content);
        $this->assertStringContainsString('Period End From', $content);
        $this->assertStringContainsString('Payout Scope', $content);
        $this->assertStringContainsString('Summary', $content);
        $this->assertStringContainsString('Withholding Tax', $content);
        $this->assertStringContainsString("Employee Government Contributions", $content);
        $this->assertStringContainsString("Employer Government Contributions", $content);
        $this->assertStringContainsString('Paid Out To Date', $content);
        $this->assertStringContainsString('Recent Payrolls', $content);
        $this->assertStringContainsString('Juan Dela Cruz', $content);
        $this->assertStringNotContainsString('Location Totals', $content);
    }

    public function test_payroll_reports_forbid_staff_access(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/reports/payroll')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/reports/payroll/data?date_from=2026-03-01&date_to=2026-03-31')
            ->assertForbidden();
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createUserWithRole(string $role, string $name, ?string $payFrequency = null): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => $payFrequency ?? 'semi_monthly',
        ])->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
