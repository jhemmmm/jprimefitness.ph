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
        if (auth()->user()->hasRole('super admin')) {
            return $next($request);
        }

        $accessibleBranchIds = auth()->user()->branches()->pluck('branches.id');

        foreach ($fields as $field) {
            $submittedBranchIds = $this->extractBranchIds($request, $field);

            if ($submittedBranchIds === []) {
                continue;
            }

            if ($accessibleBranchIds->intersect($submittedBranchIds)->count() !== count($submittedBranchIds)) {
                throw new HttpException(403, 'You are not authorized to use the selected branch.');
            }
        }

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
