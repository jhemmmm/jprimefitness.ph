<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /**
     * Index inventory page
     * @return View
     */
    public function index(): View
    {
        return view('panel.inventory', [
            'inventoryCategories' => InventoryCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * List inventory items with filters and pagination
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $itemsQuery = InventoryItem::query()
            ->with(['branch:id,name', 'category:id,name'])
            ->when(!auth()->user()->hasRole('super admin'), fn($query) => $query->whereIn('branch_id', auth()->user()->branches()->pluck('branches.id')))
            ->when($request->branch, fn($query) => $query->where('branch_id', $request->branch))
            ->when($request->category, fn($query) => $query->where('inventory_category_id', $request->category))
            ->when($request->search, function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhereHas('category', fn($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn($query) => $query->where('status', $request->status))
            ->when($request->stock_state, function ($query) use ($request) {
                $stockState = $request->stock_state;

                if ($stockState === 'out_of_stock') {
                    $query->where('quantity', '<=', 0);
                }

                if ($stockState === 'low_stock') {
                    $query->where('quantity', '>', 0)
                        ->where('low_stock_threshold', '>', 0)
                        ->whereColumn('quantity', '<=', 'low_stock_threshold');
                }

                if ($stockState === 'in_stock') {
                    $query->where('quantity', '>', 0)
                        ->where(function ($inner) {
                            $inner->where('low_stock_threshold', '<=', 0)
                                ->orWhereColumn('quantity', '>', 'low_stock_threshold');
                        });
                }
            });

        // Clone the query for pagination and stats to avoid modifying the original query builder instance
        $inventory = (clone $itemsQuery)->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total' => (clone $itemsQuery)->count(),
            'active' => (clone $itemsQuery)->where('status', InventoryItem::STATUS_ACTIVE)->count(),
            'low_stock' => (clone $itemsQuery)
                ->where('quantity', '>', 0)
                ->where('low_stock_threshold', '>', 0)
                ->whereColumn('quantity', '<=', 'low_stock_threshold')
                ->count(),
            'out_of_stock' => (clone $itemsQuery)->where('quantity', '<=', 0)->count(),
        ];

        return response()->json(compact('inventory', 'stats'));
    }

    /**
     * Store a new inventory item
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $item = InventoryItem::create($this->validatePayload($request))
            ->load(['branch:id,name', 'category:id,name']);

        return response()->json($item, 201);
    }

    /**
     * Update an existing inventory item
     * @param Request $request
     * @param InventoryItem $inventoryItem
     * @return JsonResponse
     */
    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->update($this->validatePayload($request, $inventoryItem));

        return response()->json($inventoryItem->fresh()->load(['branch:id,name', 'category:id,name']));
    }

    /**
     * Delete an existing inventory item
     * @param InventoryItem $inventoryItem
     * @return JsonResponse
     */
    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->delete();

        return response()->json(null, 204);
    }

    /**
     * Validate the payload for creating or updating an inventory item
     * @param Request $request
     * @param InventoryItem|null $inventoryItem
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?InventoryItem $inventoryItem = null): array
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'inventory_category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'sku' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('inventory_items', 'sku')
                    ->where(fn($query) => $query->where('branch_id', (int) $request->input('branch_id')))
                    ->ignore($inventoryItem?->id),
            ],
            'unit' => ['required', 'string', 'max:40'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in([InventoryItem::STATUS_ACTIVE, InventoryItem::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'last_restocked_at' => ['nullable', 'date'],
        ]);

        $validated['sku'] = $validated['sku'] ?? null;
        $validated['cost_price'] = $validated['cost_price'] ?? null;
        $validated['selling_price'] = $validated['selling_price'] ?? null;
        $validated['notes'] = $validated['notes'] ?? null;
        $validated['last_restocked_at'] = $validated['last_restocked_at'] ?? null;

        return $validated;
    }
}
