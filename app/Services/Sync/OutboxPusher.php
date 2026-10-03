<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public const MAX_ATTEMPTS = 10;

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
        $fullBatch = $batchSize;
        $pushed = 0;
        $failed = []; // ids rejected during this drain: retried next run, not in every batch of this one

        for ($batch = 0; ($maxBatches === null || $batch < $maxBatches) && microtime(true) < $deadline; $batch++) {
            // id order keeps per-entity causality (a delete never overtakes the create it follows);
            // rows past MAX_ATTEMPTS are parked and skipped so one poison row can't stall the queue
            $rows = DB::table('sync_outbox')->whereNull('pushed_at')->where('attempts', '<', self::MAX_ATTEMPTS)->whereNotIn('id', $failed)->orderBy('id')->limit($batchSize)->get();

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

            if ($response->clientError()) {
                // live rejected the batch as a whole (413 too large, 422 malformed): shrink until the
                // offending row stands alone, then count that as a failed attempt for it
                if ($rows->count() > 1) {
                    $batchSize = intdiv($rows->count(), 2);

                    continue;
                }

                $this->markFailed($rows->pluck('id'));
                $failed[] = $rows->first()->id;
                $batchSize = $fullBatch;

                continue;
            }

            if ($response->failed()) {
                throw new RuntimeException('Push HTTP error '.$response->status().': '.Str::limit($response->body(), 200));
            }

            $acks = collect($response->json('acks', []))->keyBy('event_id');
            $terminal = [AckStatus::OK, AckStatus::CONFLICT, AckStatus::SKIPPED];
            [$drained, $errored] = $rows->partition(fn ($row) => in_array($acks->get($row->event_id)['status'] ?? '', $terminal, true));

            if ($errored->isNotEmpty()) {
                $this->markFailed($errored->pluck('id'));
                $failed = [...$failed, ...$errored->pluck('id')->all()];
            }

            if ($drained->isEmpty()) {
                // nothing but rejected rows at the head: stop rather than spin, and rest the instant path
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

    /** Unpushed rows live has rejected MAX_ATTEMPTS times; skipped until retryParked(). */
    public function parked(): int
    {
        return DB::table('sync_outbox')->whereNull('pushed_at')->where('attempts', '>=', self::MAX_ATTEMPTS)->count();
    }

    public function retryParked(): int
    {
        return DB::table('sync_outbox')->whereNull('pushed_at')->where('attempts', '>=', self::MAX_ATTEMPTS)->update(['attempts' => 0]);
    }

    private function markFailed(Collection $ids): void
    {
        DB::table('sync_outbox')->whereIn('id', $ids)->increment('attempts');

        $parked = DB::table('sync_outbox')->whereIn('id', $ids)->where('attempts', '>=', self::MAX_ATTEMPTS)->pluck('event_id');

        if ($parked->isNotEmpty()) {
            Log::warning('sync: outbox rows parked after '.self::MAX_ATTEMPTS.' rejected pushes; run sync:push --retry-parked once fixed', ['event_ids' => $parked->all()]);
        }
    }

    private function backOff(): void
    {
        Cache::put(self::BACKOFF_KEY, true, self::BACKOFF_SECONDS);
    }
}
