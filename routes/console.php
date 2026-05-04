<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('panel:send-expiring-membership-notifications')
    ->dailyAt('08:00')
    ->withoutOverlapping();

if (config('sync.role') === \App\Services\Sync\SyncRole::LOCAL) {
    // 5-minute lock TTL: long enough that a backlog drain won't double-fire,
    // short enough that a crashed worker self-recovers within a shift.
    Schedule::command('sync:push')->everyThirtySeconds()->withoutOverlapping(5)->runInBackground();
    Schedule::command('sync:pull')->everyThirtySeconds()->withoutOverlapping(5)->runInBackground();
    Schedule::command('sync:heartbeat')->everyMinute()->withoutOverlapping(5)->runInBackground();
    Schedule::command('sync:prune')->dailyAt('03:30')->withoutOverlapping(30);
}
