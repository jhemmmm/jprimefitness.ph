<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Models\HikvisionEventLog;
use Illuminate\Database\Eloquent\Model;

class HikvisionEventLogReceiver extends DefaultReceiver
{
    use AppendOnlyReceiver;

    protected function locateExisting(array $payload, string $entityId, string $entityType = ''): ?Model
    {
        $deviceSerial = $payload['device_serial'] ?? null;
        $eventSerialNo = $payload['event_serial_no'] ?? null;

        if ($deviceSerial === null || $eventSerialNo === null) {
            return null;
        }

        return HikvisionEventLog::query()
            ->where('device_serial', $deviceSerial)
            ->where('event_serial_no', $eventSerialNo)
            ->first();
    }
}
