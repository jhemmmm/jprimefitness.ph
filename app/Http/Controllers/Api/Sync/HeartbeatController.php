<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liveness ping. Local hits this every minute so the live admin can
 * surface "local is N seconds behind".
 */
class HeartbeatController extends Controller
{
    public function __construct(private SyncStateRepository $state) {}

    public function __invoke(Request $request): JsonResponse
    {
        $node = (string) $request->input('node_id', 'unknown');

        $this->state->set('last_local_seen_at', (string) now());
        $this->state->set('last_local_node_id', $node);

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
