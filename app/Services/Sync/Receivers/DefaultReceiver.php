<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Models\SystemActivity;
use App\Services\Sync\AckStatus;
use App\Services\Sync\OutboxWriter;
use App\Services\Sync\SyncOp;
use App\Services\SystemActivityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Generic apply pipeline for incoming sync events. The default behavior
 * upserts by uuid with last-write-wins on updated_at. Override the
 * protected hooks for entity-specific lookup, append-only semantics, etc.
 *
 * apply() runs inside OutboxWriter::mute() so persisting an inbound
 * event does not bounce a fresh outbox row back to the sender.
 */
class DefaultReceiver
{
    /** @var array<class-string<Model>, bool> */
    private static array $usesSoftDeletesCache = [];

    public function __construct(
        protected ?SystemActivityService $systemActivityService = null,
    ) {}

    /**
     * Sync entity_type → model class. Default: read the entity_type
     * (passed in the event) out of config('sync.entities'). Receivers
     * with hard-coded behavior can ignore this and pin to a class
     * directly via the per-entity subclass.
     *
     * @return class-string<Model>
     */
    protected function modelClass(string $entityType = ''): string
    {
        $modelClass = config('sync.entities.'.$entityType.'.model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            throw new \InvalidArgumentException("No model configured for sync entity type: {$entityType}");
        }

        return $modelClass;
    }

    /**
     * Apply one decoded sync event.
     *
     * @param  array{event_id:string,entity_type:string,entity_id:string,op:string,payload:array<string,mixed>,origin_node:string,occurred_at:string}  $event
     */
    public function apply(array $event): string
    {
        return OutboxWriter::mute(function () use ($event) {
            try {
                $payload = $this->preparePayload($event['payload']);
                $existing = $this->locateExisting($payload, $event['entity_id'], $event['entity_type']);

                return match ($event['op']) {
                    SyncOp::CREATE, SyncOp::UPDATE => $this->applyUpsert($existing, $payload, $event),
                    SyncOp::DELETE => $this->applyDelete($existing),
                    SyncOp::RESTORE => $this->applyRestore($existing),
                    default => AckStatus::SKIPPED,
                };
            } catch (Throwable $e) {
                report($e);

                return AckStatus::ERROR;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function locateExisting(array $payload, string $entityId, string $entityType = ''): ?Model
    {
        if (empty($payload['uuid'])) {
            return null;
        }

        $modelClass = $this->modelClass($entityType);
        $query = $modelClass::query();

        if ($this->usesSoftDeletes($modelClass)) {
            $query = $query->withTrashed();
        }

        return $query->where('uuid', $payload['uuid'])->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{event_id:string,entity_type:string,entity_id:string,op:string,payload:array<string,mixed>,origin_node:string,occurred_at:string}  $event
     */
    protected function applyUpsert(?Model $existing, array $payload, array $event): string
    {
        if ($existing && $this->incomingIsStale($existing, $payload)) {
            $this->recordConflict($existing, $payload, $event);

            return AckStatus::CONFLICT;
        }

        $payload = $this->beforeWrite($payload, $existing);

        $model = $existing ?? $this->newModelFor($event['entity_type']);
        $this->fillModel($model, $payload);
        $model->saveQuietly();

        $this->afterWrite($model, $payload, $existing === null);

        return AckStatus::OK;
    }

    protected function applyDelete(?Model $existing): string
    {
        if (! $existing) {
            return AckStatus::SKIPPED;
        }

        $existing->delete();

        return AckStatus::OK;
    }

    protected function applyRestore(?Model $existing): string
    {
        if (! $existing) {
            return AckStatus::SKIPPED;
        }

        if (method_exists($existing, 'restore')) {
            $existing->restore();
        }

        return AckStatus::OK;
    }

    /**
     * Hook to mutate payload before locate/persist (e.g. JSON decode
     * fields that arrived as strings).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function beforeWrite(array $payload, ?Model $existing): array
    {
        // Drop the sender's auto-increment id so we don't attempt to
        // overwrite the local primary key.
        unset($payload['id']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function afterWrite(Model $model, array $payload, bool $created): void
    {
        // Override for cascading effects.
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function fillModel(Model $model, array $payload): void
    {
        $payload = $this->decodeJsonCastStrings($model, $payload);

        foreach ($payload as $key => $value) {
            $model->setAttribute($key, $value);
        }
    }

    /**
     * Defensive decode for legacy outbox events that were emitted with
     * raw JSON strings in `array`/`json`/`object`/`collection`-cast
     * columns. Without this, `setAttribute` re-encodes the string and
     * stores a doubly-encoded value that reads back as a string instead
     * of an array. Models emitted by the up-to-date `SyncsToOutbox`
     * trait already send decoded arrays, so this is a no-op for them.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function decodeJsonCastStrings(Model $model, array $payload): array
    {
        $casts = $model->getCasts();

        foreach ($payload as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! isset($casts[$key])) {
                continue;
            }

            $cast = strtolower(trim((string) $casts[$key]));
            $isJsonCast = in_array($cast, ['array', 'json', 'object', 'collection'], true);

            if (! $isJsonCast) {
                foreach (['encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection'] as $prefix) {
                    if ($cast === $prefix || str_starts_with($cast, $prefix.':')) {
                        $isJsonCast = true;
                        break;
                    }
                }
            }

            if (! $isJsonCast) {
                continue;
            }

            $decoded = json_decode($value, true);

            if ($decoded === null && strtolower(trim($value)) !== 'null') {
                continue;
            }

            $payload[$key] = $decoded;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function incomingIsStale(Model $existing, array $payload): bool
    {
        $existingUpdated = $existing->getAttribute('updated_at');
        $incomingUpdated = $payload['updated_at'] ?? null;

        if (! $existingUpdated || ! $incomingUpdated) {
            return false;
        }

        // Strict <: ties go to the incoming event, since equal timestamps
        // typically mean the same originating change being applied a
        // second time and the no-op write is harmless.
        return Carbon::parse($incomingUpdated)->lt(Carbon::parse($existingUpdated));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $event
     */
    protected function recordConflict(Model $existing, array $payload, array $event): void
    {
        try {
            $service = $this->systemActivityService ?? app(SystemActivityService::class);

            // The receiver runs inside OutboxWriter::mute(); SystemActivity
            // uses SyncsToOutbox so this write is muted on the receiving
            // side and won't echo back to the sender.
            $service->record([
                'subject_type' => SystemActivity::SUBJECT_SYNC,
                'subject_id' => (int) $existing->getKey(),
                'subject_label' => $event['entity_type'].':'.$event['entity_id'],
                'event' => SystemActivity::EVENT_SYNC_CONFLICT_DROPPED,
                'title' => 'Sync conflict dropped',
                'message' => sprintf(
                    'Incoming %s from %s lost last-write-wins to a newer local edit.',
                    $event['entity_type'],
                    $event['origin_node'] ?? 'remote'
                ),
                'metadata' => [
                    'origin_node' => $event['origin_node'] ?? null,
                    'event_id' => $event['event_id'] ?? null,
                    'incoming_updated_at' => $payload['updated_at'] ?? null,
                    'local_updated_at' => optional($existing->getAttribute('updated_at'))->toIso8601String(),
                    'incoming_payload' => $payload,
                ],
            ]);
        } catch (Throwable $e) {
            // Conflict logging must never poison apply().
            report($e);
        }
    }

    /**
     * Bootstrap snapshot page for one entity type. Default behavior pages
     * by primary key over the configured Eloquent model's table and returns
     * raw arrays (no Eloquent hydration). Receivers backing non-Eloquent
     * sources (e.g. Spatie pivots) override this entirely.
     *
     * @return array{rows: array<int, array<string, mixed>>, next_after_id: int|string, has_more: bool}
     */
    public function snapshot(string $entityType, int|string $afterId, int $limit): array
    {
        $modelClass = $this->modelClass($entityType);
        /** @var Model $model */
        $model = new $modelClass;
        $keyName = $model->getKeyName();
        $table = $model->getTable();

        $rows = DB::table($table)
            ->where($keyName, '>', $afterId)
            ->orderBy($keyName)
            ->limit($limit)
            ->get();

        $payload = $rows->map(fn ($row) => (array) $row)->all();
        $lastId = $rows->isNotEmpty() ? (int) $rows->last()->{$keyName} : (int) $afterId;

        return [
            'rows' => $payload,
            'next_after_id' => $lastId,
            'has_more' => $rows->count() === $limit,
        ];
    }

    private function newModelFor(string $entityType): Model
    {
        $modelClass = $this->modelClass($entityType);

        return new $modelClass;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function usesSoftDeletes(string $modelClass): bool
    {
        return self::$usesSoftDeletesCache[$modelClass]
            ??= in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }
}
