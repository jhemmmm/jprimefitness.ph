<?php

use App\Http\Controllers\Api\KioskAttendanceController;
use App\Http\Controllers\Api\KioskDiscoveryController;
use App\Http\Controllers\Api\KioskPaymentController;
use App\Http\Controllers\Api\HikvisionCallbackController;
use App\Http\Controllers\Api\PaymongoWebhookController;
use App\Http\Controllers\Api\Sync\HeartbeatController;
use App\Http\Controllers\Api\Sync\PullController;
use App\Http\Controllers\Api\Sync\PushController;
use App\Http\Controllers\Api\Sync\SnapshotController;
use Illuminate\Support\Facades\Route;

// Kiosk + biometric only make sense on the on-prem PC, but we register
// them unconditionally and let the middleware role-check at request time.
// This keeps test-time config overrides effective without re-registering routes.
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

// PayMongo webhooks land on whichever node is internet-reachable; in
// the live/local split that's always live, but we don't gate at route
// level — same rationale as above.
Route::post('paymongo/webhook', [PaymongoWebhookController::class, 'handle'])->name('paymongo.webhook');

// Sync ingress. The 'sync' middleware enforces APP_NODE_ROLE=live and
// the bearer token; non-live nodes 404 here.
Route::prefix('sync')
    ->middleware('sync')
    ->group(function (): void {
        Route::post('push', PushController::class)->name('sync.push');
        Route::get('pull', PullController::class)->name('sync.pull');
        Route::get('snapshot/{entityType}', SnapshotController::class)->name('sync.snapshot');
        Route::post('heartbeat', HeartbeatController::class)->name('sync.heartbeat');
    });
