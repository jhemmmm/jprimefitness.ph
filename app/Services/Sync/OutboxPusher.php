<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Drains the local sync_outbox to the live API. Shared by the sync:push
 * schedule and the instant push that follows any request writing outbox rows.
 */
class OutboxPusher
{
    /** One drain at a time across the schedule and instant pushes. A drain stops before this runs out. */
    public const LOCK_SECONDS = 300;

    /** After a failed or stuck instant push, leave retries to the schedule for this long instead of stalling every request. */
    public const BACKOFF_SECONDS = 60;

    private const BACKOFF_KEY = 'sync:push:backoff';

    public function __construct(private SyncClient $client, private SyncStateRepository $state) {}

    /**
     * Push unpushed rows in batches (fewest attempts first, then oldest) until the outbox is empty,
     * $maxBatches is reached, or the lock is about to expire; the next run continues from there.
     * Returns the number of rows acked, or null when another push holds the lock.
     *
     * @throws \Throwable when live rejects a batch or can't be reached
     */
    public function push(?int $batchSize = null, ?int $maxBatches = null): ?int
    {
        $batchSize ??= (int) config('sync.push_batch_size', 200);
        $pushed = Cache::lock('sync:push', self::LOCK_SECONDS)->get(fn () => $this->drain($batchSize, $maxBatches));

        return $pushed === false ? null : $pushed;
    }

    /**
     * The after-response path: one batch, and none at all for a while after a failure. Quiet; the
     * schedule reports the outage.
     */
    public function pushNow(): void
    {
        if (Cache::has(self::BACKOFF_KEY)) {
            return;
        }

        try {
            $this->push(maxBatches: 1);
        } catch (Throwable) {
            $this->backOff();
        }
    }

    private function drain(int $batchSize, ?int $maxBatches): int
    {
        // a batch can take a connect timeout plus the request timeout; never start one that could outlive the lock
        $deadline = microtime(true) + self::LOCK_SECONDS - (int) config('sync.http_timeout', 30) - 10;
        $pushed = 0;

        for ($batch = 0; ($maxBatches === null || $batch < $maxBatches) && microtime(true) < $deadline; $batch++) {
            $rows = DB::table('sync_outbox')->whereNull('pushed_at')->orderBy('attempts')->orderBy('id')->limit($batchSize)->get();

            if ($rows->isEmpty()) {
                break;
            }

            $response = $this->client->push($rows->map(fn ($row) => [
                'event_id' => $row->event_id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'op' => $row->op,
                'payload' => json_decode($row->payload, true) ?? [],
                'origin_node' => $row->origin_node,
                'occurred_at' => $row->occurred_at,
            ])->all());

            if ($response->failed()) {
                throw new RuntimeException('Push HTTP error '.$response->status().': '.Str::limit($response->body(), 200));
            }

            $acks = collect($response->json('acks', []))->keyBy('event_id');
            $terminal = [AckStatus::OK, AckStatus::CONFLICT, AckStatus::SKIPPED];
            [$drained, $errored] = $rows->partition(fn ($row) => in_array($acks->get($row->event_id)['status'] ?? '', $terminal, true));

            // errored rows move to the back of the queue and are retried on later runs
            if ($errored->isNotEmpty()) {
                DB::table('sync_outbox')->whereIn('id', $errored->pluck('id'))->increment('attempts');
            }

            if ($drained->isEmpty()) {
                // nothing but rejected rows left at the head: stop rather than spin, and rest the instant path
                $this->backOff();
                break;
            }

            DB::table('sync_outbox')->whereIn('id', $drained->pluck('id'))->update(['pushed_at' => now(), 'updated_at' => now()]);
            $pushed += $drained->count();

            if ($rows->count() < $batchSize) {
                break;
            }
        }

        $this->state->set('last_push_at', (string) now());

        return $pushed;
    }

    private function backOff(): void
    {
        Cache::put(self::BACKOFF_KEY, true, self::BACKOFF_SECONDS);
    }
}
