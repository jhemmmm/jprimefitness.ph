<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Notifications\InventoryStockAlertNotification;

class InventoryStockAlertService
{
    public const STATE_LOW_STOCK = 'low_stock';

    public const STATE_OUT_OF_STOCK = 'out_of_stock';

    public function __construct(private NotificationRecipientResolver $notificationRecipientResolver) {}

    public function sync(InventoryItem $inventoryItem, ?string $previousAlertState = null): void
    {
        $currentAlertState = $this->currentAlertState($inventoryItem);
        $previousAlertState ??= $inventoryItem->getOriginal('stock_alert_state');

        if ($currentAlertState === null) {
            if ($inventoryItem->stock_alert_state !== null) {
                $inventoryItem->forceFill([
                    'stock_alert_state' => null,
                ])->saveQuietly();
            }

            return;
        }

        if (! $this->shouldNotify($previousAlertState, $currentAlertState)) {
            return;
        }

        $inventoryItem->forceFill([
            'stock_alert_state' => $currentAlertState,
        ])->saveQuietly();

        $this->notificationRecipientResolver->send(
            new InventoryStockAlertNotification($inventoryItem, $currentAlertState),
        );
    }

    public function currentAlertState(InventoryItem $inventoryItem): ?string
    {
        if ((float) $inventoryItem->quantity <= 0) {
            return self::STATE_OUT_OF_STOCK;
        }

        if (
            (float) $inventoryItem->quantity > 0
            && (float) $inventoryItem->low_stock_threshold > 0
            && (float) $inventoryItem->quantity <= (float) $inventoryItem->low_stock_threshold
        ) {
            return self::STATE_LOW_STOCK;
        }

        return null;
    }

    private function shouldNotify(?string $previousAlertState, string $currentAlertState): bool
    {
        if ($currentAlertState === self::STATE_OUT_OF_STOCK) {
            return $previousAlertState !== self::STATE_OUT_OF_STOCK;
        }

        return $previousAlertState === null;
    }
}
