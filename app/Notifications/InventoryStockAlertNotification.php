<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use App\Services\InventoryStockAlertService;

class InventoryStockAlertNotification extends PanelDatabaseNotification
{
    public function __construct(
        private readonly InventoryItem $inventoryItem,
        private readonly string $alertState,
    ) {
    }

    protected function typeSlug(): string
    {
        return 'inventory-stock-alert';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'action_url' => route('panel.inventory.index', ['search' => $this->inventoryItem->name]),
            'type' => $this->typeSlug(),
            'severity' => $this->alertState === InventoryStockAlertService::STATE_OUT_OF_STOCK ? 'danger' : 'warning',
            'subject_id' => $this->inventoryItem->id,
            'subject_type' => 'inventory_item',
            'occurred_at' => $this->inventoryItem->updated_at?->toISOString(),
        ];
    }

    private function title(): string
    {
        return $this->alertState === InventoryStockAlertService::STATE_OUT_OF_STOCK
            ? 'Inventory out of stock'
            : 'Inventory running low';
    }

    private function message(): string
    {
        $quantity = number_format((float) $this->inventoryItem->quantity, 2);
        $unit = $this->inventoryItem->unit ? ' ' . $this->inventoryItem->unit : '';

        if ($this->alertState === InventoryStockAlertService::STATE_OUT_OF_STOCK) {
            return $this->inventoryItem->name . ' is out of stock. Remaining quantity: ' . $quantity . $unit . '.';
        }

        return $this->inventoryItem->name . ' is low in stock. Remaining quantity: ' . $quantity . $unit . '.';
    }
}
