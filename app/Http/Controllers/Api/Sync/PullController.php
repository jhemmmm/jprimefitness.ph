<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Returns outbox events with id > since, ordered ascending. The local
 * node persists the cursor only after applying the batch successfully,
 * so retries are safe.
 */
class PullController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $since = (int) ($data['since'] ?? 0);
        $limit = (int) ($data['limit'] ?? config('sync.pull_batch_size', 500));

        $rows = DB::table('sync_outbox')
            ->where('id', '>', $since)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $events = $rows->map(function ($row): array {
            return [
                'sequence' => (int) $row->id,
                'event_id' => $row->event_id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'op' => $row->op,
                'payload' => json_decode($row->payload, true) ?? [],
                'origin_node' => $row->origin_node,
                'occurred_at' => $row->occurred_at,
            ];
        });

        $nextCursor = $events->isNotEmpty()
            ? (int) $events->last()['sequence']
            : $since;

        return response()->json([
            'events' => $events->all(),
            'next_cursor' => $nextCursor,
            'has_more' => $events->count() === $limit,
        ]);
    }
}
