<?php

use Illuminate\Support\Facades\Schedule;

// Scheduled tasks. In production, one cron entry runs "php artisan schedule:run" every minute.

// No ticket stays "new" for ever if the AI or the queue worker is down.
Schedule::command('triage:release-stuck')->everyFiveMinutes();
