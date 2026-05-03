<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class KioskDiscoveryController extends Controller
{
    /**
     * Unauthenticated discovery probe so kiosk clients can verify they have
     * latched onto a real JPrime server (and not some random 200 on the LAN).
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'service' => 'jprimefitness-kiosk-api',
            'version' => '1',
            'serverId' => $this->serverId(),
        ]);
    }

    /**
     * Derive a stable server identifier from APP_KEY. Deterministic,
     * no I/O, unique per install, and rotates only if APP_KEY rotates.
     *
     * @return string
     */
    private function serverId(): string
    {
        $hash = hash('sha256', (string) config('app.key').'|jprimefitness-kiosk');

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
