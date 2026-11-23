<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI triage
    |--------------------------------------------------------------------------
    |
    | When disabled, every new ticket goes straight to the manual triage queue.
    | The application never depends on the AI to work.
    |
    */

    'enabled' => (bool) env('AI_ENABLED', false),

    // "anthropic" (Claude API) or "fake" (scripted answers, used by the tests).
    'provider' => env('AI_PROVIDER', 'anthropic'),

    // Maximum time, in seconds, to wait for an answer before giving up.
    'timeout' => (int) env('AI_TIMEOUT', 30),

    // Maximum number of AI calls per minute, all tickets combined.
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 20),

    // Below this confidence score the suggestion is flagged "low confidence".
    'low_confidence_threshold' => (float) env('AI_LOW_CONFIDENCE_THRESHOLD', 0.7),

    // A ticket still "new" after this many minutes is released to manual triage.
    'stuck_after_minutes' => (int) env('AI_STUCK_AFTER_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    |
    | A new ticket is compared with the open tickets of the last days. Tickets
    | sharing enough keywords are candidates; the AI, when enabled, confirms.
    |
    */

    'duplicates' => [
        'window_days' => (int) env('AI_DUPLICATES_WINDOW_DAYS', 14),
        'min_shared_keywords' => (int) env('AI_DUPLICATES_MIN_SHARED_KEYWORDS', 2),
        'max_candidates' => 5,
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => '2026-10-21',
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
        // Reasoning depth: low is enough to classify a ticket. Leave empty for
        // older models that do not accept this parameter.
        'effort' => env('ANTHROPIC_EFFORT', 'low'),
        // If the model declines a request, let the API retry it on the fallback
        // model it recommends. Set to false for models without this feature (Haiku).
        'refusal_fallback' => (bool) env('ANTHROPIC_REFUSAL_FALLBACK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | US dollars per million tokens: [input, output]. Used only to estimate
    | the cost shown in the AI log. Prices as published on 2026-11-03.
    |
    */

    'pricing' => [
        'claude-fable-5-2' => [8.00, 40.00],
        'claude-opus-5-5' => [4.00, 20.00],
        'claude-opus-5' => [5.00, 25.00],
        'claude-opus-4-8' => [5.00, 25.00],
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-sonnet-5' => [2.00, 10.00],
        'claude-haiku-5-5' => [0.10, 0.50],
        'fake' => [0.0, 0.0],
    ],

];
