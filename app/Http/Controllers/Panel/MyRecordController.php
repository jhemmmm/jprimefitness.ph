<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The signed-in employee's own record, one full page per section. The pages
 * reuse the employee sub-components, which read `/panel/employees/{self}/…`
 * (allowed by EmployeeController::authorizeSelfOrManager()).
 */
class MyRecordController extends Controller
{
    /**
     * Section slug => [sidebar label, icon]. The Vue side (MyRecordPage.vue)
     * maps the same slugs to components.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SECTIONS = [
        'attendance' => ['Attendance', 'bi-calendar-check'],
        'schedule' => ['Schedule', 'bi-calendar-week'],
        'payroll' => ['Payroll', 'bi-receipt'],
        'payouts' => ['Payouts', 'bi-cash-stack'],
        'cash-advances' => ['Cash Advances', 'bi-cash-coin'],
    ];

    /**
     * Display one section of the current user's record.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function show(string $section): View
    {
        $user = auth()->user();

        abort_unless($user->employeeProfile, 404);

        return view('panel.my-record', [
            'section' => $section,
            'employee' => $user->only(['id', 'name']),
        ]);
    }
}
