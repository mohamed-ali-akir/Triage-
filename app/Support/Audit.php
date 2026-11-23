<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Security journal: who logged in, who failed, which account was changed and by whom.
 * Written to its own log file (storage/logs/audit-*.log). Never receives a password.
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $event, array $context = []): void
    {
        Log::channel('audit')->info($event, [...$context, 'ip' => request()->ip()]);
    }
}
