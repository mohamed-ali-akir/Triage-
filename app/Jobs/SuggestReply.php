<?php

namespace App\Jobs;

use App\Enums\ReplyStatus;
use App\Models\AiSuggestion;
use App\Services\Ai\ReplySuggester;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Drafts a reply from the knowledge base, after the classification.
 * It is a separate job on purpose: a failure here never affects the triage.
 */
class SuggestReply implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public AiSuggestion $suggestion) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('ai-calls')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('ai.stuck_after_minutes'));
    }

    public function handle(ReplySuggester $suggester): void
    {
        // Already attempted (the job may be delivered twice).
        if ($this->suggestion->reply_status !== null) {
            return;
        }

        $suggester->suggest($this->suggestion);
    }

    public function failed(?Throwable $exception): void
    {
        $this->suggestion->fresh()?->update(['reply_status' => ReplyStatus::Failed]);
    }
}
