<?php

use App\Http\Controllers\Home\BranchController;
use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Panel\AttendanceController;
use App\Http\Controllers\Panel\BranchesController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\EmployeeController;
use App\Http\Controllers\Panel\InventoryController;
use App\Http\Controllers\Panel\MembersController;
use App\Http\Controllers\Panel\PricingController;
use App\Http\Controllers\Panel\WalkInsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/branches/{branch}', [BranchController::class, 'index'])->name('branch.index');

Route::middleware(['auth', 'panel'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Members
    Route::get('/members', [MembersController::class, 'index'])->name('members.index');
    Route::get('/members/list', [MembersController::class, 'list'])->name('members.list');
    Route::post('/members', [MembersController::class, 'store'])->middleware('branch.input:branch_ids')->name('members.store');
    Route::middleware('branch.resource:member')->group(function () {
        Route::get('/members/{member}/attendance', [MembersController::class, 'attendance'])->name('members.attendance')->whereNumber('member');
        Route::put('/members/{member}/membership', [MembersController::class, 'updateMembership'])->name('members.membership.update')->whereNumber('member');
        Route::put('/members/{member}/membership/status', [MembersController::class, 'updateMembershipStatus'])->name('members.membership.status')->whereNumber('member');
        Route::post('/members/{member}/pt-packages', [MembersController::class, 'storePtPackage'])->middleware('branch.input:branch_id')->name('members.pt-packages.store')->whereNumber('member');
        Route::post('/members/{member}/pt-session-usages', [MembersController::class, 'storePtSessionUsage'])->name('members.pt-session-usages.store')->whereNumber('member');
        Route::get('/members/{member}', [MembersController::class, 'show'])->name('members.show')->whereNumber('member');
        Route::put('/members/{member}', [MembersController::class, 'update'])->middleware('branch.input:branch_ids')->name('members.update')->whereNumber('member');
    });

    // Walk-ins
    Route::get('/walk-ins', [WalkInsController::class, 'index'])->name('walkins.index');
    Route::get('/walk-ins/list', [WalkInsController::class, 'list'])->name('walkins.list');
    Route::post('/walk-ins', [WalkInsController::class, 'store'])->middleware('branch.input:branch_id')->name('walkins.store');
    Route::middleware('branch.resource:walkIn')->group(function () {
        Route::get('/walk-ins/{walkIn}', [WalkInsController::class, 'show'])->name('walkins.show')->whereNumber('walkIn');
        Route::put('/walk-ins/{walkIn}', [WalkInsController::class, 'update'])->middleware('branch.input:branch_id')->name('walkins.update')->whereNumber('walkIn');
        Route::delete('/walk-ins/{walkIn}', [WalkInsController::class, 'destroy'])->name('walkins.destroy')->whereNumber('walkIn');
    });

    // Branches
    Route::get('/branches', [BranchesController::class, 'index'])->name('branches.index');
    Route::get('/branches/list', [BranchesController::class, 'list'])->name('branches.list');
    Route::post('/branches', [BranchesController::class, 'store'])->name('branches.store');
    Route::middleware('branch.resource:branch')->group(function () {
        Route::get('/branches/{branch}', [BranchesController::class, 'show'])->name('branches.show')->whereNumber('branch');
        Route::get('/branches/{branch}/cash-ledger', [BranchesController::class, 'cashLedger'])->name('branches.cash-ledger.list')->whereNumber('branch');
        Route::post('/branches/{branch}/cash-ledger', [BranchesController::class, 'storeCashLedgerEntry'])->name('branches.cash-ledger.store')->whereNumber('branch');
        Route::put('/branches/{branch}/cash-ledger/{entry}', [BranchesController::class, 'updateCashLedgerEntry'])->name('branches.cash-ledger.update')->whereNumber('branch')->whereNumber('entry');
        Route::delete('/branches/{branch}/cash-ledger/{entry}', [BranchesController::class, 'destroyCashLedgerEntry'])->name('branches.cash-ledger.destroy')->whereNumber('branch')->whereNumber('entry');
        Route::put('/branches/{branch}', [BranchesController::class, 'update'])->name('branches.update')->whereNumber('branch');
        Route::delete('/branches/{branch}', [BranchesController::class, 'destroy'])->name('branches.destroy')->whereNumber('branch');
        Route::post('/branches/{branch}/photos', [BranchesController::class, 'storePhoto'])->name('branches.photos.store')->whereNumber('branch');
        Route::delete('/branches/{branch}/photos/{index}', [BranchesController::class, 'destroyPhoto'])->name('branches.photos.destroy')->whereNumber('branch');
    });

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/list', [InventoryController::class, 'list'])->name('inventory.list');
    Route::post('/inventory', [InventoryController::class, 'store'])->middleware('branch.input:branch_id')->name('inventory.store');
    Route::middleware('branch.resource:inventoryItem')->group(function () {
        Route::put('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->middleware('branch.input:branch_id')->name('inventory.update')->whereNumber('inventoryItem');
        Route::delete('/inventory/{inventoryItem}', [InventoryController::class, 'destroy'])->name('inventory.destroy')->whereNumber('inventoryItem');
    });

    // Pricing
    Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');
    Route::middleware('branch.resource:branch')->group(function () {
        Route::get('/pricing/branches/{branch}', [PricingController::class, 'show'])->name('pricing.show')->whereNumber('branch');
        Route::post('/pricing/branches/{branch}/rate-plans/{ratePlan}', [PricingController::class, 'storeRatePlan'])->name('pricing.rate-plans.store')->whereNumber('branch')->whereNumber('ratePlan');
        Route::put('/pricing/branches/{branch}/rate-plans/{ratePlan}', [PricingController::class, 'updateRatePlan'])->name('pricing.rate-plans.update')->whereNumber('branch')->whereNumber('ratePlan');
        Route::delete('/pricing/branches/{branch}/rate-plans/{ratePlan}', [PricingController::class, 'destroyRatePlan'])->name('pricing.rate-plans.destroy')->whereNumber('branch')->whereNumber('ratePlan');
        Route::post('/pricing/branches/{branch}/pt-products/{ptProduct}', [PricingController::class, 'storePtProduct'])->name('pricing.pt-products.store')->whereNumber('branch')->whereNumber('ptProduct');
        Route::put('/pricing/branches/{branch}/pt-products/{ptProduct}', [PricingController::class, 'updatePtProduct'])->name('pricing.pt-products.update')->whereNumber('branch')->whereNumber('ptProduct');
        Route::delete('/pricing/branches/{branch}/pt-products/{ptProduct}', [PricingController::class, 'destroyPtProduct'])->name('pricing.pt-products.destroy')->whereNumber('branch')->whereNumber('ptProduct');
    });

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('branch.input:branch_id')->name('attendance.store');
    Route::middleware('branch.resource:attendance')->group(function () {
        Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->middleware('branch.input:branch_id')->name('attendance.update')->whereNumber('attendance');
        Route::post('/attendance/{attendance}/checkout', [AttendanceController::class, 'checkout'])->name('attendance.checkout')->whereNumber('attendance');
        Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy')->whereNumber('attendance');
    });

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/list', [EmployeeController::class, 'list'])->name('employees.list');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('branch.input:branch_ids')->name('employees.store');
    Route::middleware('branch.resource:employee')->group(function () {
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->whereNumber('employee');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('branch.input:branch_ids')->name('employees.update')->whereNumber('employee');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->whereNumber('employee');
        Route::post('/employees/{employee}/attendance', [EmployeeController::class, 'attendance'])->name('employees.attendance')->whereNumber('employee');

        // Payrolls
        Route::get('/employees/{employee}/payrolls', [EmployeeController::class, 'payrolls'])->name('employees.payrolls.list')->whereNumber('employee');
        Route::post('/employees/{employee}/payrolls', [EmployeeController::class, 'storePayroll'])->name('employees.payrolls.store')->whereNumber('employee');
        Route::get('/employees/{employee}/payrolls/suggested-ca', [EmployeeController::class, 'payrollSuggestedCa'])->name('employees.payrolls.suggestedCa')->whereNumber('employee');
        Route::get('/employees/{employee}/payrolls/suggest', [EmployeeController::class, 'payrollSuggest'])->name('employees.payrolls.suggest')->whereNumber('employee');
        Route::middleware('branch.resource:payroll')->group(function () {
            Route::get('/employees/{employee}/payrolls/{payroll}/payslip', [EmployeeController::class, 'payslip'])->name('employees.payrolls.payslip')->whereNumber('employee')->whereNumber('payroll');
            Route::put('/employees/{employee}/payrolls/{payroll}', [EmployeeController::class, 'updatePayroll'])->name('employees.payrolls.update')->whereNumber('employee')->whereNumber('payroll');
            Route::post('/employees/{employee}/payrolls/{payroll}/approve', [EmployeeController::class, 'approvePayroll'])->name('employees.payrolls.approve')->whereNumber('employee')->whereNumber('payroll');
            Route::post('/employees/{employee}/payrolls/{payroll}/cancel', [EmployeeController::class, 'cancelPayroll'])->name('employees.payrolls.cancel')->whereNumber('employee')->whereNumber('payroll');
        });

        // Payouts
        Route::get('/employees/{employee}/payouts', [EmployeeController::class, 'payouts'])->name('employees.payouts.list')->whereNumber('employee');
        Route::middleware('branch.resource:payroll')->group(function () {
            Route::get('/employees/{employee}/payrolls/{payroll}/payouts', [EmployeeController::class, 'payrollPayouts'])->name('employees.payrolls.payouts.list')->whereNumber('employee')->whereNumber('payroll');
            Route::post('/employees/{employee}/payrolls/{payroll}/payouts', [EmployeeController::class, 'storePayout'])->name('employees.payrolls.payouts.store')->whereNumber('employee')->whereNumber('payroll');
        });

        // Cash Advances
        Route::get('/employees/{employee}/cash-advances', [EmployeeController::class, 'cashAdvances'])->name('employees.cash-advances.list')->whereNumber('employee');
        Route::post('/employees/{employee}/cash-advances', [EmployeeController::class, 'storeCashAdvance'])->name('employees.cash-advances.store')->whereNumber('employee');
        Route::middleware('branch.resource:cashAdvance')->group(function () {
            Route::put('/employees/{employee}/cash-advances/{cashAdvance}', [EmployeeController::class, 'updateCashAdvance'])->name('employees.cash-advances.update')->whereNumber('employee')->whereNumber('cashAdvance');
            Route::delete('/employees/{employee}/cash-advances/{cashAdvance}', [EmployeeController::class, 'destroyCashAdvance'])->name('employees.cash-advances.destroy')->whereNumber('employee')->whereNumber('cashAdvance');
        });
    });
});

Auth::routes(['register' => false]);
