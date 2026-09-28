<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 8G: booking expiry & slot release (no Redis/Horizon dependency).
Schedule::command('bookings:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Phase 10B: invoice 24h deadline -> OVERDUE (timer invariant).
Schedule::command('invoices:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Phase 11E: outstanding reminder (idempotent — same-day guard inside command).
Schedule::command('outstanding:remind')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Phase 13D: logical DB backup into private storage (daily).
Schedule::command('db:backup')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
