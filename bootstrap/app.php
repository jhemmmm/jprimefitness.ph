<?php

use App\Http\Middleware\EnsurePanelAccess;
use App\Http\Middleware\VerifyBiometricToken;
use App\Http\Middleware\VerifyKioskToken;
use App\Http\Middleware\VerifySyncToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'biometric' => VerifyBiometricToken::class,
            'panel' => EnsurePanelAccess::class,
            'kiosk' => VerifyKioskToken::class,
            'sync' => VerifySyncToken::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Trust only the proxies listed in TRUSTED_PROXIES (default: cloudflared loopback).
        // Use '*' only for ephemeral local dev - never accept spoofed X-Forwarded-* from arbitrary clients.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1'))
        )));

        $middleware->trustProxies(
            at: $trustedProxies === ['*'] ? '*' : $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
