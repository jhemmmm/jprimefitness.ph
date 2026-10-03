<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBiometricToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.biometric.token');

        if (empty($expected)) {
            return response()->json([
                'message' => 'Biometric token is not configured on the server.',
            ], 503);
        }

        $provided = $request->header('X-Biometric-Token');

        if (! is_string($provided) || ! hash_equals((string) $expected, $provided)) {
            return response()->json([
                'message' => 'Invalid biometric token.',
            ], 401);
        }

        return $next($request);
    }
}
