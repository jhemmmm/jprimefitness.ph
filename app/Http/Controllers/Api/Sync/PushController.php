<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncOp;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives a batch of sync events from the remote node.
 *
 * Each event:
 *   { event_id, entity_type, entity_id, op, payload, origin_node, occurred_at }
 *
 * Dedup, conflict detection, and persistence all live in
 * SyncEventApplier so push and pull share the same pipeline.
 */
class PushController extends Controller
{
    public function __construct(
        private SyncEventApplier $applier,
        private SyncStateRepository $state,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array'],
            'events.*.event_id' => ['required', 'string', 'max:64'],
            'events.*.entity_type' => ['required', 'string', 'max:64'],
            'events.*.entity_id' => ['required', 'string', 'max:64'],
            'events.*.op' => ['required', 'string', 'in:'.implode(',', SyncOp::all())],
            'events.*.payload' => ['required', 'array'],
            'events.*.origin_node' => ['required', 'string', 'max:64'],
            'events.*.occurred_at' => ['required', 'string'],
        ]);

        $acks = $this->applier->applyBatch($data['events']);

        $this->state->set('last_push_received_at', (string) now());

        return response()->json(['acks' => $acks]);
    }
}
