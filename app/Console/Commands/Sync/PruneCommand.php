<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Trims old sync ledger rows so the tables don't grow unbounded.
 * Pushed-and-acked outbox rows older than the retention window are
 * deleted; ditto applied inbox rows.
 *
 * Default retention: 30 days. Forensic replay typically only needs a
 * couple of weeks; keep more if you need longer-term audit history.
 */
class PruneCommand extends Command
{
    protected $signature = 'sync:prune {--days=30 : Retention window in days}';

    protected $description = 'Delete old sync_outbox and sync_inbox rows past the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $outboxDeleted = DB::table('sync_outbox')
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
