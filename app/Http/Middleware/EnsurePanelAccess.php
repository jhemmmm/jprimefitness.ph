<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks members (ROLE_MEMBER) from accessing the panel.
 * Only super_admin, admin, and staff are allowed in.
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

        if (!Auth::user()->hasAnyRole(['super admin', 'admin', 'manager', 'staff'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. You do not have access to the panel.'], 403);
            }

            return redirect()->route('home')
                ->with('error', 'You do not have access to the panel.');
        }

        return $next($request);
    }
}
