<?php

use App\Http\Controllers\Home\BranchController;
use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Panel\AttendanceController;
use App\Http\Controllers\Panel\BranchesController;
use App\Http\Controllers\Panel\CashAdvanceController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\EmployeeController;
use App\Http\Controllers\Panel\MembersController;
use App\Http\Controllers\Panel\PayoutController;
use App\Http\Controllers\Panel\PayrollController;
use App\Http\Controllers\Panel\WalkInsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/branches/{branch}', [BranchController::class, 'index'])->name('branch.index');

Route::middleware(['auth', 'panel'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Members
    Route::get('/members', [MembersController::class, 'index'])->name('members.index');
    Route::post('/members/list', [MembersController::class, 'list'])->name('members.list');
    Route::post('/members', [MembersController::class, 'store'])->name('members.store');
    Route::get('/members/{member}', [MembersController::class, 'show'])->name('members.show')->whereNumber('member');
    Route::put('/members/{member}', [MembersController::class, 'update'])->name('members.update')->whereNumber('member');

    // Walk-ins
    Route::get('/walk-ins', [WalkInsController::class, 'index'])->name('walkins.index');
    Route::post('/walk-ins/list', [WalkInsController::class, 'list'])->name('walkins.list');
    Route::post('/walk-ins', [WalkInsController::class, 'store'])->name('walkins.store');
    Route::get('/walk-ins/{walkIn}', [WalkInsController::class, 'show'])->name('walkins.show')->whereNumber('walkIn');
    Route::put('/walk-ins/{walkIn}', [WalkInsController::class, 'update'])->name('walkins.update')->whereNumber('walkIn');
    Route::delete('/walk-ins/{walkIn}', [WalkInsController::class, 'destroy'])->name('walkins.destroy')->whereNumber('walkIn');

    // Branches
    Route::get('/branches', [BranchesController::class, 'index'])->name('branches.index');
    Route::get('/branches/list', [BranchesController::class, 'list'])->name('branches.list');
    Route::post('/branches', [BranchesController::class, 'store'])->middleware('can:super-admin')->name('branches.store');
    Route::put('/branches/{branch}', [BranchesController::class, 'update'])->middleware('can:super-admin')->name('branches.update')->whereNumber('branch');
    Route::delete('/branches/{branch}', [BranchesController::class, 'destroy'])->middleware('can:super-admin')->name('branches.destroy')->whereNumber('branch');
    Route::post('/branches/{branch}/photos', [BranchesController::class, 'storePhoto'])->middleware('can:super-admin')->name('branches.photos.store')->whereNumber('branch');
    Route::delete('/branches/{branch}/photos/{index}', [BranchesController::class, 'destroyPhoto'])->middleware('can:super-admin')->name('branches.photos.destroy')->whereNumber('branch');

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update')->whereNumber('attendance');
    Route::post('/attendance/{attendance}/checkout', [AttendanceController::class, 'checkout'])->name('attendance.checkout')->whereNumber('attendance');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy')->whereNumber('attendance');

    // Employees
    Route::middleware(['can:manage employees'])->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees/list', [EmployeeController::class, 'list'])->name('employees.list');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->whereNumber('employee');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->whereNumber('employee');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->whereNumber('employee');
        Route::get('/employees/{employee}/attendance', [EmployeeController::class, 'attendance'])->name('employees.attendance')->whereNumber('employee');

        // Payrolls
        Route::get('/employees/{employee}/payrolls', [PayrollController::class, 'list'])->name('employees.payrolls.list')->whereNumber('employee');
        Route::post('/employees/{employee}/payrolls', [PayrollController::class, 'store'])->name('employees.payrolls.store')->whereNumber('employee');
        Route::get('/employees/{employee}/payrolls/suggested-ca', [PayrollController::class, 'suggestedCa'])->name('employees.payrolls.suggestedCa')->whereNumber('employee');
        Route::get('/employees/{employee}/payrolls/suggest', [PayrollController::class, 'suggest'])->name('employees.payrolls.suggest')->whereNumber('employee');
        Route::put('/payrolls/{payroll}', [PayrollController::class, 'update'])->name('payrolls.update')->whereNumber('payroll');
        Route::post('/payrolls/{payroll}/approve', [PayrollController::class, 'approve'])->name('payrolls.approve')->whereNumber('payroll');
        Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy'])->name('payrolls.destroy')->whereNumber('payroll');

        // Payouts
        Route::get('/employees/{employee}/payouts', [PayoutController::class, 'listForEmployee'])->name('employees.payouts.list')->whereNumber('employee');
        Route::get('/payrolls/{payroll}/payouts', [PayoutController::class, 'listForPayroll'])->name('payrolls.payouts.list')->whereNumber('payroll');
        Route::post('/payrolls/{payroll}/payouts', [PayoutController::class, 'store'])->name('payrolls.payouts.store')->whereNumber('payroll');
        Route::delete('/payouts/{payout}', [PayoutController::class, 'destroy'])->name('payouts.destroy')->whereNumber('payout');

        // Cash Advances
        Route::get('/employees/{employee}/cash-advances', [CashAdvanceController::class, 'list'])->name('employees.cash-advances.list')->whereNumber('employee');
        Route::post('/employees/{employee}/cash-advances', [CashAdvanceController::class, 'store'])->name('employees.cash-advances.store')->whereNumber('employee');
        Route::put('/cash-advances/{cashAdvance}', [CashAdvanceController::class, 'update'])->name('cash-advances.update')->whereNumber('cashAdvance');
        Route::delete('/cash-advances/{cashAdvance}', [CashAdvanceController::class, 'destroy'])->name('cash-advances.destroy')->whereNumber('cashAdvance');
    });
});

Auth::routes(['register' => false]);
