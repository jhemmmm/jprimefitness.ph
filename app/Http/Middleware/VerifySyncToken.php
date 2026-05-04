<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Sync\SyncRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the live-side /api/sync/* routes.
 *
 *   - Bearer token equality check against config('sync.live_token').
 *     The local instance sends the same value it has in its .env, no
 *     per-user identity required for this single-tenant setup.
 *   - Optional IP allowlist via config('sync.allowed_ips') for an
 *     extra layer when the live host is behind a known reverse proxy.
 */
class VerifySyncToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('sync.role') !== SyncRole::LIVE) {
            // Role-gate at the middleware so the routes can stay
            // statically registered. Hidden behind 404 to keep the
            // existence of /api/sync/* invisible on non-live nodes.
            abort(404);
        }

        $expected = config('sync.live_token');

        if (empty($expected)) {
            return response()->json([
                'message' => 'Sync token is not configured on this server.',
            ], 503);
        }

        $provided = $this->extractToken($request);

        if (! is_string($provided) || $provided === '' || ! hash_equals((string) $expected, $provided)) {
            return response()->json([
                'message' => 'Invalid sync token.',
            ], 401);
        }

        $allowed = (array) config('sync.allowed_ips', []);

        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            return response()->json([
                'message' => 'Sync caller is not in the IP allowlist.',
            ], 403);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $alt = $request->header('X-Sync-Token');

        return is_string($alt) ? $alt : null;
    }
}
