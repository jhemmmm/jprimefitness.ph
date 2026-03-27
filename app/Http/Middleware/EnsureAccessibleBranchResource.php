<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureAccessibleBranchResource
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        if (auth()->user()->hasRole('super admin')) {
            return $next($request);
        }

        $accessibleBranchIds = auth()->user()->branches()->pluck('branches.id');

        foreach ($parameters as $parameter) {
            $resource = $request->route($parameter);

            if (! $resource instanceof Model) {
                continue;
            }

            $resourceBranchIds = $this->resolveBranchIds($resource);

            if (
                $resourceBranchIds === []
                || $accessibleBranchIds->intersect($resourceBranchIds)->isEmpty()
            ) {
                throw new NotFoundHttpException;
            }
        }

        return $next($request);
    }

    /**
     * @return array<int>
     */
    private function resolveBranchIds(Model $resource): array
    {
        if ($resource instanceof Branch) {
            return [$resource->getKey()];
        }

        $branchId = $resource->getAttribute('branch_id');

        if ($branchId !== null) {
            return [(int) $branchId];
        }

        if (method_exists($resource, 'branches')) {
            return $resource->branches()
                ->pluck('branches.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if (method_exists($resource, 'branch')) {
            $relatedBranchId = $resource->branch()
                ->getQuery()
                ->pluck('branches.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($relatedBranchId !== []) {
                return $relatedBranchId;
            }
        }

        throw new \RuntimeException('Unable to resolve branch ownership for ['.get_class($resource).'].');
    }
}
