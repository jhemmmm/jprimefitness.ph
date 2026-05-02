<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\User;
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
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search === '') {
            return response()->json([
                'query' => '',
                'groups' => [],
            ]);
        }

        return response()->json([
            'query' => $search,
            'groups' => array_filter([
                'members' => $this->memberGroup($search),
                'employees' => $request->user()->can('manage employees')
                    ? $this->employeeGroup($search)
                    : null,
                'inventory' => $this->inventoryGroup($search),
            ]),
        ]);
    }

    /**
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function memberGroup(string $search): array
    {
        $query = User::role('member')
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
                'meta' => null,
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
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function employeeGroup(string $search): array
    {
        $query = User::role(self::EMPLOYEE_ROLE_NAMES)
            ->with(['roles:id,name'])
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
                'meta' => $employee->roles->pluck('name')
                    ->map(fn (string $role) => Str::headline($role))
                    ->implode(' • ') ?: null,
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
     * @return array{items: array<int, array{id: int, meta: string|null, status: string|null, subtitle: string|null, title: string, url: string}>, label: string, total: int, view_all_url: string}
     */
    private function inventoryGroup(string $search): array
    {
        $query = InventoryItem::query()
            ->with(['category:id,name'])
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
                'subtitle' => $item->category?->name,
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
