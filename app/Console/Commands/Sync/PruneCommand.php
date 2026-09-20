<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\SyncRole;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Trims old sync ledger rows so the tables don't grow unbounded.
 * Outbox rows the other node has consumed (pushed_at on local; id at or
 * below the cursor local reported via heartbeat on live) and older than
 * the retention window are deleted; ditto applied inbox rows.
 *
 * Default retention: 30 days. Forensic replay typically only needs a
 * couple of weeks; keep more if you need longer-term audit history.
 */
class PruneCommand extends Command
{
    protected $signature = 'sync:prune {--days=30 : Retention window in days}';

    protected $description = 'Delete old sync_outbox and sync_inbox rows past the retention window.';

    public function handle(SyncStateRepository $state): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $outboxDeleted = config('sync.role') === SyncRole::LIVE
            ? DB::table('sync_outbox')
                ->where('id', '<=', $state->getInt('last_local_pull_cursor', 0))
                ->where('created_at', '<', $cutoff)
                ->delete()
            : DB::table('sync_outbox')
                ->whereNotNull('pushed_at')
                ->where('pushed_at', '<', $cutoff)
                ->delete();

        $inboxDeleted = DB::table('sync_inbox')
            ->where('applied_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$outboxDeleted} outbox + {$inboxDeleted} inbox rows older than {$days} days.");

        return self::SUCCESS;
    }
}
