<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKioskToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.kiosk.token');

        if (empty($expected)) {
            return response()->json([
                'message' => 'Kiosk token is not configured on the server.',
            ], 503);
        }

        $provided = $request->header('X-Kiosk-Token');

        if (! is_string($provided) || ! hash_equals((string) $expected, $provided)) {
            return response()->json([
                'message' => 'Invalid kiosk token.',
            ], 401);
        }

        return $next($request);
    }
}
