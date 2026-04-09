<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\InventoryStockAlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryStockAlertService $inventoryStockAlertService,
    ) {
    }

    public function index(): View
    {
        return view('panel.inventory', [
            'inventoryCategories' => InventoryCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $itemsQuery = InventoryItem::query()
            ->with('category:id,name')
            ->when($request->category, fn ($query) => $query->where('inventory_category_id', $request->category))
            ->when($request->search, function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($query) => $query->where('status', $request->status))
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

        $inventory = (clone $itemsQuery)
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (InventoryItem $item) => [
                'id' => $item->id,
                'inventory_category_id' => $item->inventory_category_id,
                'category' => $item->category,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'quantity' => round((float) $item->quantity, 2),
                'low_stock_threshold' => round((float) $item->low_stock_threshold, 2),
                'cost_price' => $item->cost_price !== null ? round((float) $item->cost_price, 2) : null,
                'selling_price' => $item->selling_price !== null ? round((float) $item->selling_price, 2) : null,
                'status' => $item->status,
                'notes' => $item->notes,
                'last_restocked_at' => $item->last_restocked_at?->toISOString(),
                'is_low_stock' => $item->is_low_stock,
                'is_out_of_stock' => $item->is_out_of_stock,
            ])
            ->withQueryString();

        $stats = [
            'total' => (clone $itemsQuery)->count(),
            'active' => (clone $itemsQuery)->where('status', InventoryItem::STATUS_ACTIVE)->count(),
            'low_stock' => (clone $itemsQuery)->where('quantity', '>', 0)->where('low_stock_threshold', '>', 0)->whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            'out_of_stock' => (clone $itemsQuery)->where('quantity', '<=', 0)->count(),
        ];

        return response()->json(compact('inventory', 'stats'));
    }

    public function store(Request $request): JsonResponse
    {
        $item = InventoryItem::create($this->validatePayload($request))
            ->load('category:id,name');

        $this->inventoryStockAlertService->sync($item);

        return response()->json($this->serializeItem($item), 201);
    }

    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $previousAlertState = $inventoryItem->stock_alert_state;

        $inventoryItem->update($this->validatePayload($request, $inventoryItem));
        $inventoryItem = $inventoryItem->fresh()->load('category:id,name');

        $this->inventoryStockAlertService->sync($inventoryItem, $previousAlertState);

        return response()->json($this->serializeItem($inventoryItem));
    }

    public function destroy(InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?InventoryItem $inventoryItem = null): array
    {
        $validated = $request->validate([
            'inventory_category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('inventory_items', 'sku')->ignore($inventoryItem?->id)],
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

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(InventoryItem $item): array
    {
        return $item->toArray();
    }
}
