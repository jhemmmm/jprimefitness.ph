<?php

use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Panel\AttendanceController;
use App\Http\Controllers\Panel\AttendanceReportsController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\EmployeeController;
use App\Http\Controllers\Panel\FinancialReportsController;
use App\Http\Controllers\Panel\InventoryController;
use App\Http\Controllers\Panel\MembersController;
use App\Http\Controllers\Panel\NotificationsController;
use App\Http\Controllers\Panel\PayrollReportsController;
use App\Http\Controllers\Panel\PricingController;
use App\Http\Controllers\Panel\SalesController;
use App\Http\Controllers\Panel\SalesReportsController;
use App\Http\Controllers\Panel\SearchController;
use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\WalkInsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'panel'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/photos', [SettingsController::class, 'storePhoto'])->name('settings.photos.store');
    Route::delete('/settings/photos/{index}', [SettingsController::class, 'destroyPhoto'])->name('settings.photos.destroy')->whereNumber('index');
    Route::get('/settings/cash-ledger', [SettingsController::class, 'cashLedger'])->name('settings.cash-ledger.list');
    Route::post('/settings/cash-ledger', [SettingsController::class, 'storeCashLedgerEntry'])->name('settings.cash-ledger.store');
    Route::put('/settings/cash-ledger/{entry}', [SettingsController::class, 'updateCashLedgerEntry'])->name('settings.cash-ledger.update')->whereNumber('entry');
    Route::delete('/settings/cash-ledger/{entry}', [SettingsController::class, 'destroyCashLedgerEntry'])->name('settings.cash-ledger.destroy')->whereNumber('entry');
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/list', [NotificationsController::class, 'list'])->name('notifications.list');
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NotificationsController::class, 'markAsRead'])
        ->whereUuid('notificationId')
        ->name('notifications.read');

    // Members
    Route::get('/members', [MembersController::class, 'index'])->name('members.index');
    Route::get('/members/list', [MembersController::class, 'list'])->name('members.list');
    Route::post('/members', [MembersController::class, 'store'])->name('members.store');
    Route::get('/members/{member}/attendance', [MembersController::class, 'attendance'])->name('members.attendance')->whereNumber('member');
    Route::put('/members/{member}/membership', [MembersController::class, 'updateMembership'])->name('members.membership.update')->whereNumber('member');
    Route::put('/members/{member}/membership/status', [MembersController::class, 'updateMembershipStatus'])->name('members.membership.status')->whereNumber('member');
    Route::put('/members/{member}/membership/manager', [MembersController::class, 'assignMembershipManager'])->name('members.membership.manager')->whereNumber('member');
    Route::post('/members/{member}/pt-packages', [MembersController::class, 'storePtPackage'])->name('members.pt-packages.store')->whereNumber('member');
    Route::post('/members/{member}/pt-session-usages', [MembersController::class, 'storePtSessionUsage'])->name('members.pt-session-usages.store')->whereNumber('member');
    Route::get('/members/{member}', [MembersController::class, 'show'])->name('members.show')->whereNumber('member');
    Route::put('/members/{member}', [MembersController::class, 'update'])->name('members.update')->whereNumber('member');

    // Walk-ins
    Route::get('/walk-ins', [WalkInsController::class, 'index'])->name('walkins.index');
    Route::get('/walk-ins/list', [WalkInsController::class, 'list'])->name('walkins.list');
    Route::post('/walk-ins', [WalkInsController::class, 'store'])->name('walkins.store');
    Route::get('/walk-ins/{walkIn}', [WalkInsController::class, 'show'])->name('walkins.show')->whereNumber('walkIn');
    Route::put('/walk-ins/{walkIn}', [WalkInsController::class, 'update'])->name('walkins.update')->whereNumber('walkIn');
    Route::delete('/walk-ins/{walkIn}', [WalkInsController::class, 'destroy'])->name('walkins.destroy')->whereNumber('walkIn');

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/list', [InventoryController::class, 'list'])->name('inventory.list');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update')->whereNumber('inventoryItem');
    Route::delete('/inventory/{inventoryItem}', [InventoryController::class, 'destroy'])->name('inventory.destroy')->whereNumber('inventoryItem');

    // Pricing
    Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');
    Route::get('/pricing/data', [PricingController::class, 'show'])->name('pricing.show');
    Route::post('/pricing/rate-plans/{ratePlan}', [PricingController::class, 'storeRatePlan'])->name('pricing.rate-plans.store')->whereNumber('ratePlan');
    Route::put('/pricing/rate-plans/{ratePlan}', [PricingController::class, 'updateRatePlan'])->name('pricing.rate-plans.update')->whereNumber('ratePlan');
    Route::delete('/pricing/rate-plans/{ratePlan}', [PricingController::class, 'destroyRatePlan'])->name('pricing.rate-plans.destroy')->whereNumber('ratePlan');
    Route::post('/pricing/pt-products/{ptProduct}', [PricingController::class, 'storePtProduct'])->name('pricing.pt-products.store')->whereNumber('ptProduct');
    Route::put('/pricing/pt-products/{ptProduct}', [PricingController::class, 'updatePtProduct'])->name('pricing.pt-products.update')->whereNumber('ptProduct');
    Route::delete('/pricing/pt-products/{ptProduct}', [PricingController::class, 'destroyPtProduct'])->name('pricing.pt-products.destroy')->whereNumber('ptProduct');

    // Sales
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/sales/context', [SalesController::class, 'context'])->name('sales.context');
    Route::get('/sales/history', [SalesController::class, 'history'])->name('sales.history');
    Route::post('/sales', [SalesController::class, 'store'])->name('sales.store');
    Route::get('/sales/{saleTransaction}/receipt', [SalesController::class, 'receipt'])->name('sales.receipt')->whereNumber('saleTransaction');

    // Reports
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('/sales', [SalesReportsController::class, 'index'])->name('sales');
        Route::get('/sales/data', [SalesReportsController::class, 'data'])->name('sales.data');
        Route::get('/sales/export', [SalesReportsController::class, 'export'])->name('sales.export');
        Route::get('/financial', [FinancialReportsController::class, 'index'])->name('financial');
        Route::get('/financial/data', [FinancialReportsController::class, 'data'])->name('financial.data');
        Route::get('/financial/export', [FinancialReportsController::class, 'export'])->name('financial.export');

        Route::get('/attendance', [AttendanceReportsController::class, 'index'])->name('attendance');
        Route::get('/attendance/data', [AttendanceReportsController::class, 'data'])->name('attendance.data');
        Route::get('/attendance/export', [AttendanceReportsController::class, 'export'])->name('attendance.export');

        Route::get('/payroll', [PayrollReportsController::class, 'index'])->name('payroll');
        Route::get('/payroll/data', [PayrollReportsController::class, 'data'])->name('payroll.data');
        Route::get('/payroll/export', [PayrollReportsController::class, 'export'])->name('payroll.export');
    });

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update')->whereNumber('attendance');
    Route::post('/attendance/{attendance}/checkout', [AttendanceController::class, 'checkout'])->name('attendance.checkout')->whereNumber('attendance');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy')->whereNumber('attendance');

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/list', [EmployeeController::class, 'list'])->name('employees.list');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->whereNumber('employee');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->whereNumber('employee');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->whereNumber('employee');
    Route::post('/employees/{employee}/attendance', [EmployeeController::class, 'attendance'])->name('employees.attendance')->whereNumber('employee');
    Route::get('/employees/{employee}/payrolls', [EmployeeController::class, 'payrolls'])->name('employees.payrolls.list')->whereNumber('employee');
    Route::post('/employees/{employee}/payrolls', [EmployeeController::class, 'storePayroll'])->name('employees.payrolls.store')->whereNumber('employee');
    Route::get('/employees/{employee}/payrolls/suggested-ca', [EmployeeController::class, 'payrollSuggestedCa'])->name('employees.payrolls.suggestedCa')->whereNumber('employee');
    Route::get('/employees/{employee}/payrolls/suggest', [EmployeeController::class, 'payrollSuggest'])->name('employees.payrolls.suggest')->whereNumber('employee');
    Route::get('/employees/{employee}/payrolls/{payroll}/payslip', [EmployeeController::class, 'payslip'])->name('employees.payrolls.payslip')->whereNumber('employee')->whereNumber('payroll');
    Route::put('/employees/{employee}/payrolls/{payroll}', [EmployeeController::class, 'updatePayroll'])->name('employees.payrolls.update')->whereNumber('employee')->whereNumber('payroll');
    Route::post('/employees/{employee}/payrolls/{payroll}/approve', [EmployeeController::class, 'approvePayroll'])->name('employees.payrolls.approve')->whereNumber('employee')->whereNumber('payroll');
    Route::post('/employees/{employee}/payrolls/{payroll}/cancel', [EmployeeController::class, 'cancelPayroll'])->name('employees.payrolls.cancel')->whereNumber('employee')->whereNumber('payroll');
    Route::get('/employees/{employee}/payouts', [EmployeeController::class, 'payouts'])->name('employees.payouts.list')->whereNumber('employee');
    Route::get('/employees/{employee}/payrolls/{payroll}/payouts', [EmployeeController::class, 'payrollPayouts'])->name('employees.payrolls.payouts.list')->whereNumber('employee')->whereNumber('payroll');
    Route::post('/employees/{employee}/payrolls/{payroll}/payouts', [EmployeeController::class, 'storePayout'])->name('employees.payrolls.payouts.store')->whereNumber('employee')->whereNumber('payroll');
    Route::get('/employees/{employee}/cash-advances', [EmployeeController::class, 'cashAdvances'])->name('employees.cash-advances.list')->whereNumber('employee');
    Route::post('/employees/{employee}/cash-advances', [EmployeeController::class, 'storeCashAdvance'])->name('employees.cash-advances.store')->whereNumber('employee');
    Route::put('/employees/{employee}/cash-advances/{cashAdvance}', [EmployeeController::class, 'updateCashAdvance'])->name('employees.cash-advances.update')->whereNumber('employee')->whereNumber('cashAdvance');
    Route::delete('/employees/{employee}/cash-advances/{cashAdvance}', [EmployeeController::class, 'destroyCashAdvance'])->name('employees.cash-advances.destroy')->whereNumber('employee')->whereNumber('cashAdvance');
});

Auth::routes(['register' => false]);
