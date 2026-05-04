<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generates a random sync token for the live <-> local channel.
 *
 * Run on live, copy the printed token, paste it into BOTH:
 *   - live's .env as LIVE_SYNC_TOKEN (so the middleware compares against it)
 *   - local's .env as LIVE_SYNC_TOKEN (so the client sends it)
 *
 * Rotate by re-running and updating both .env files.
 */
class IssueTokenCommand extends Command
{
    protected $signature = 'sync:issue-token {--length=64}';

    protected $description = 'Generate a random sync token for the live <-> local channel.';

    public function handle(): int
    {
        $length = max(32, (int) $this->option('length'));
        $token = Str::random($length);

        $this->newLine();
        $this->info('Generated sync token:');
        $this->line('');
        $this->line('  '.$token);
        $this->line('');
        $this->comment('Set LIVE_SYNC_TOKEN to this value on BOTH live and local nodes.');
        $this->comment('On live it gates incoming /api/sync/* requests; on local the client sends it as a Bearer token.');

        return self::SUCCESS;
    }
}
