<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncReceiverRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Bootstrap endpoint. Returns raw rows for one entity_type, paginated by
 * primary key cursor. Bypasses Eloquent hydration so a 500-row page
 * doesn't allocate 500 fully-cast model instances. Soft-deleted rows
 * are included so the local node mirrors deletion state.
 *
 * The current outbox max-id is included so the local node sets its
 * incremental pull cursor at the right point after bootstrap completes.
 */
class SnapshotController extends Controller
{
    public function __construct(private SyncReceiverRegistry $registry) {}

    public function __invoke(Request $request, string $entityType): JsonResponse
    {
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $afterId = (int) ($data['after_id'] ?? 0);
        $limit = (int) ($data['limit'] ?? 500);

        try {
            $modelClass = $this->registry->modelFor($entityType);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
        $model = new $modelClass;
        $keyName = $model->getKeyName();
        $table = $model->getTable();

        $rows = DB::table($table)
            ->where($keyName, '>', $afterId)
            ->orderBy($keyName)
            ->limit($limit)
            ->get();

        $payload = $rows->map(fn ($row) => (array) $row)->all();

        $lastId = $rows->isNotEmpty() ? (int) $rows->last()->{$keyName} : $afterId;
        $maxOutboxId = (int) (DB::table('sync_outbox')->max('id') ?? 0);

        return response()->json([
            'entity_type' => $entityType,
            'rows' => $payload,
            'next_after_id' => $lastId,
            'has_more' => $rows->count() === $limit,
            'outbox_max_id' => $maxOutboxId,
        ]);
    }
}
