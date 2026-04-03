<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    private const RESULTS_LIMIT = 5;

    private const EMPLOYEE_ROLE_NAMES = [
        'employee',
        'coach',
        'manager',
        'admin',
        'staff',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search === '') {
            return response()->json([
                'query' => '',
                'groups' => [],
            ]);
        }

        $accessibleBranchIds = $this->accessibleBranchIds(
            isset($validated['branch']) ? (int) $validated['branch'] : null
        );

        return response()->json([
            'query' => $search,
            'groups' => array_filter([
                'members' => $this->memberGroup($search, $accessibleBranchIds),
                'branches' => $this->branchGroup($search, $accessibleBranchIds),
                'employees' => $request->user()->can('manage employees')
                    ? $this->employeeGroup($search, $accessibleBranchIds)
                    : null,
                'inventory' => $this->inventoryGroup($search, $accessibleBranchIds),
                'walkins' => $this->walkInGroup($search, $accessibleBranchIds),
            ]),
        ]);
    }

    /**
     * @param  array<int>|null  $accessibleBranchIds
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function memberGroup(string $search, ?array $accessibleBranchIds): array
    {
        $query = User::role('member')
            ->with('branches:id,name')
            ->when($accessibleBranchIds !== null, fn ($builder) => $builder->whereHas('branches', fn ($branchQuery) => $branchQuery->whereIn('branches.id', $accessibleBranchIds)))
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });

        $total = (clone $query)->count();

        $items = $query
            ->orderBy('name')
            ->limit(self::RESULTS_LIMIT)
            ->get(['users.id', 'users.name', 'users.email', 'users.phone', 'users.status'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'title' => $member->name,
                'subtitle' => $this->implodeMeta([$member->email, $member->phone]),
                'meta' => $this->implodeMeta($member->branches->pluck('name')->all()),
                'status' => $member->status,
                'url' => route('panel.members.show', $member),
            ])
            ->values()
            ->all();

        return $this->groupPayload(
            'Members',
            route('panel.members.index', ['search' => $search]),
            $total,
            $items,
        );
    }

    /**
     * @param  array<int>|null  $accessibleBranchIds
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function branchGroup(string $search, ?array $accessibleBranchIds): array
    {
        $query = Branch::query()
            ->when($accessibleBranchIds !== null, fn ($builder) => $builder->whereKey($accessibleBranchIds))
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%");
            });

        $total = (clone $query)->count();

        $items = $query
            ->orderBy('name')
            ->limit(self::RESULTS_LIMIT)
            ->get(['id', 'name', 'city', 'province', 'address', 'status'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'title' => $branch->name,
                'subtitle' => $this->implodeMeta([$branch->city, $branch->province]),
                'meta' => $branch->address ?: null,
                'status' => $branch->status,
                'url' => route('panel.branches.show', $branch),
            ])
            ->values()
            ->all();

        return $this->groupPayload(
            'Branches',
            route('panel.branches.index', ['search' => $search]),
            $total,
            $items,
        );
    }

    /**
     * @param  array<int>|null  $accessibleBranchIds
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function employeeGroup(string $search, ?array $accessibleBranchIds): array
    {
        $query = User::role(self::EMPLOYEE_ROLE_NAMES)
            ->with(['branches:id,name', 'roles:id,name'])
            ->when($accessibleBranchIds !== null, fn ($builder) => $builder->whereHas('branches', fn ($branchQuery) => $branchQuery->whereIn('branches.id', $accessibleBranchIds)))
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });

        $total = (clone $query)->count();

        $items = $query
            ->orderBy('name')
            ->limit(self::RESULTS_LIMIT)
            ->get(['users.id', 'users.name', 'users.email', 'users.phone', 'users.status'])
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'title' => $employee->name,
                'subtitle' => $this->implodeMeta([$employee->email, $employee->phone]),
                'meta' => $this->implodeMeta([
                    $employee->roles->pluck('name')->map(fn (string $role) => Str::headline($role))->implode(' • '),
                    $employee->branches->pluck('name')->implode(', '),
                ]),
                'status' => $employee->status,
                'url' => route('panel.employees.show', $employee),
            ])
            ->values()
            ->all();

        return $this->groupPayload(
            'Employees',
            route('panel.employees.index', ['search' => $search]),
            $total,
            $items,
        );
    }

    /**
     * @param  array<int>|null  $accessibleBranchIds
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function inventoryGroup(string $search, ?array $accessibleBranchIds): array
    {
        $query = InventoryItem::query()
            ->with(['branch:id,name', 'category:id,name'])
            ->when($accessibleBranchIds !== null, fn ($builder) => $builder->whereIn('branch_id', $accessibleBranchIds))
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('unit', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
            });

        $total = (clone $query)->count();

        $items = $query
            ->orderBy('name')
            ->limit(self::RESULTS_LIMIT)
            ->get([
                'id',
                'branch_id',
                'inventory_category_id',
                'name',
                'sku',
                'unit',
                'quantity',
                'status',
            ])
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'title' => $item->name,
                'subtitle' => $this->implodeMeta([$item->branch?->name, $item->category?->name]),
                'meta' => $this->implodeMeta([
                    $item->sku ? 'SKU '.$item->sku : null,
                    $this->formatQuantity($item->quantity).' '.$item->unit,
                ]),
                'status' => $item->status,
                'url' => route('panel.inventory.index', ['search' => $item->sku ?: $item->name]),
            ])
            ->values()
            ->all();

        return $this->groupPayload(
            'Inventory',
            route('panel.inventory.index', ['search' => $search]),
            $total,
            $items,
        );
    }

    /**
     * @param  array<int>|null  $accessibleBranchIds
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function walkInGroup(string $search, ?array $accessibleBranchIds): array
    {
        $query = WalkIn::query()
            ->with(['branch:id,name', 'ratePlan:id,name'])
            ->when($accessibleBranchIds !== null, fn ($builder) => $builder->whereIn('branch_id', $accessibleBranchIds))
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });

        $total = (clone $query)->count();

        $items = $query
            ->orderByDesc('visited_at')
            ->limit(self::RESULTS_LIMIT)
            ->get(['id', 'branch_id', 'rate_plan_id', 'name', 'phone', 'payment_method', 'visited_at'])
            ->map(fn (WalkIn $walkIn) => [
                'id' => $walkIn->id,
                'title' => $walkIn->name,
                'subtitle' => $this->implodeMeta([
                    $walkIn->branch?->name,
                    $walkIn->ratePlan?->name,
                ]),
                'meta' => $this->implodeMeta([
                    $walkIn->phone,
                    $walkIn->visited_at?->format('M j, Y g:i A'),
                    $walkIn->payment_method ? Str::headline($walkIn->payment_method) : null,
                ]),
                'status' => null,
                'url' => route('panel.walkins.index', ['search' => $walkIn->phone ?: $walkIn->name]),
            ])
            ->values()
            ->all();

        return $this->groupPayload(
            'Walk-ins',
            route('panel.walkins.index', ['search' => $search]),
            $total,
            $items,
        );
    }

    /**
     * @param  array<int>|null  $accessibleBranchIds
     * @param  array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>  $items
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function groupPayload(string $label, string $viewAllUrl, int $total, array $items): array
    {
        return [
            'label' => $label,
            'total' => $total,
            'view_all_url' => $viewAllUrl,
            'items' => $items,
        ];
    }

    /**
     * @return array<int>|null
     */
    private function accessibleBranchIds(?int $selectedBranchId): ?array
    {
        if ($selectedBranchId !== null) {
            return [$selectedBranchId];
        }

        if (auth()->user()->hasRole('super admin')) {
            return null;
        }

        return auth()->user()
            ->branches()
            ->pluck('branches.id')
            ->map(fn ($branchId) => (int) $branchId)
            ->all();
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    private function implodeMeta(array $parts): ?string
    {
        $value = collect($parts)
            ->filter(fn (?string $part) => filled($part))
            ->implode(' • ');

        return $value !== '' ? $value : null;
    }

    private function formatQuantity(string|int|float|null $value): string
    {
        $formatted = number_format((float) $value, 2, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
