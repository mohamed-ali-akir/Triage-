<?php

use Illuminate\Support\Facades\Schedule;

// Scheduled tasks. In production, one cron entry runs "php artisan schedule:run" every minute.

// No ticket stays "new" for ever if the AI or the queue worker is down.
Schedule::command('triage:release-stuck')->everyFiveMinutes();

// Expired tokens of the mobile application are deleted from the database.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// GDPR: erase the content of tickets closed or resolved for longer than the retention period.
Schedule::command('triage:purge-old-tickets')->dailyAt('03:00');
