<?php

namespace App\Services\Ai;

use App\Models\AiLog;

/**
 * An answer together with the log line written for the call.
 */
final readonly class AiAnswer
{
    public function __construct(
        public AiResponse $response,
        public AiLog $log,
    ) {}
}
