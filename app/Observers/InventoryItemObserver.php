<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;

class InventoryItemObserver
{
    public function created(InventoryItem $inventoryItem): void
    {
        $this->record($inventoryItem, 'created');
    }

    public function updated(InventoryItem $inventoryItem): void
    {
        $this->record($inventoryItem, 'updated');
    }

    public function deleted(InventoryItem $inventoryItem): void
    {
        $this->record($inventoryItem, 'deleted');
    }

    private function record(InventoryItem $inventoryItem, string $event): void
    {
        $inventoryItem->loadMissing('category:id,name');

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_INVENTORY_ITEM,
            $inventoryItem->id,
            $event,
            $this->snapshot($inventoryItem),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(InventoryItem $inventoryItem): array
    {
        return [
            'id' => $inventoryItem->id,
            'name' => $inventoryItem->name,
            'category_name' => $inventoryItem->category?->name,
            'quantity' => round((float) $inventoryItem->quantity, 2),
            'unit' => $inventoryItem->unit,
        ];
    }
}
