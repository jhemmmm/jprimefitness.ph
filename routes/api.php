<?php

use App\Http\Controllers\Api\KioskAttendanceController;
use App\Http\Controllers\Api\KioskPaymentController;
use App\Http\Controllers\Api\HikvisionCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('kiosk')
    ->middleware('kiosk')
    ->group(function (): void {
        Route::post('attendance', [KioskAttendanceController::class, 'store']);

        Route::post('payments', [KioskPaymentController::class, 'store']);
        Route::get('payments/{reference}', [KioskPaymentController::class, 'show']);
    });

Route::prefix('biometric')
    ->middleware('biometric')
    ->group(function (): void {
        Route::post('hikvision/callback', [HikvisionCallbackController::class, 'store'])
            ->name('biometric.hikvision.callback');
    });
