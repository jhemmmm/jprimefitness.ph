<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the panel on the `access panel` permission (see RoleSeeder::MATRIX).
 * Members never hold it; individual pages are further gated per route.
 *
 * Applied to the entire panel route group.
 */
class EnsurePanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Please log in to access the panel.'], 401);
            }

            return redirect()->route('login')
                ->with('error', 'Please log in to access the panel.');
        }

        // can() (not hasPermissionTo) so a missing permission row yields 403, not an exception.
        if (!Auth::user()->can('access panel')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. You do not have access to the panel.'], 403);
            }

            return redirect()->route('home')
                ->with('error', 'You do not have access to the panel.');
        }

        return $next($request);
    }
}
