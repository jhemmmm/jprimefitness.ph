<?php

use App\Http\Controllers\Api\KioskAttendanceController;
use App\Http\Controllers\Api\KioskDiscoveryController;
use App\Http\Controllers\Api\KioskPaymentController;
use App\Http\Controllers\Api\HikvisionCallbackController;
use App\Http\Controllers\Api\PaymongoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('kiosk/discover', [KioskDiscoveryController::class, 'show']);

Route::prefix('kiosk')
    ->middleware('kiosk')
    ->group(function (): void {
        Route::post('attendance', [KioskAttendanceController::class, 'store']);

        Route::post('payments', [KioskPaymentController::class, 'store']);
        Route::get('payments/{reference}', [KioskPaymentController::class, 'show']);
        Route::post('payments/{reference}/confirm', [KioskPaymentController::class, 'confirm']);
    });

Route::prefix('biometric')
    ->middleware('biometric')
    ->group(function (): void {
        Route::post('hikvision/callback', [HikvisionCallbackController::class, 'store'])
            ->name('biometric.hikvision.callback');
    });

Route::post('paymongo/webhook', [PaymongoWebhookController::class, 'handle'])->name('paymongo.webhook');
