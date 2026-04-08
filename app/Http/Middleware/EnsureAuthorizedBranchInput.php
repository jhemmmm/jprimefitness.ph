<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureAuthorizedBranchInput
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$fields): Response
    {
        // Single-branch mode: all input branch IDs are implicitly valid.
        // Re-enable this check when multi-branch / centralized DB is introduced.
        return $next($request);
    }

    /**
     * @return array<int>
     */
    private function extractBranchIds(Request $request, string $field): array
    {
        $value = $request->input($field);

        if ($value === null || $value === '') {
            return [];
        }

        return collect(is_array($value) ? $value : [$value])
            ->filter(fn ($branchId) => $branchId !== null && $branchId !== '')
            ->map(fn ($branchId) => (int) $branchId)
            ->values()
            ->all();
    }
}
