<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default provider when platform settings row is missing
    |--------------------------------------------------------------------------
    | Allowed: stub, replicate
    */
    'default_provider' => env('AI_HAIRSTYLE_PROVIDER', 'stub'),

    /*
    | When false, stub cannot be selected or used for generation (production fail-closed).
    | Local / CI keep true; VPS must set AI_HAIRSTYLE_ALLOW_STUB=false.
    */
    'allow_stub' => filter_var(env('AI_HAIRSTYLE_ALLOW_STUB', true), FILTER_VALIDATE_BOOLEAN),

    'replicate' => [
        'api_token' => env('REPLICATE_API_TOKEN'),
        'base_url' => env('REPLICATE_API_BASE', 'https://api.replicate.com/v1'),
        /*
         * Model used for face-preserving hairstyle edits.
         * Override with REPLICATE_AI_HAIRSTYLE_MODEL (owner/name).
         */
        'model' => env('REPLICATE_AI_HAIRSTYLE_MODEL', 'zsxkib/instant-id'),
        'poll_interval_ms' => (int) env('REPLICATE_POLL_INTERVAL_MS', 1500),
        'poll_timeout_seconds' => (int) env('REPLICATE_POLL_TIMEOUT_SECONDS', 120),
    ],

    /*
    | Temporary selfie path on the local disk (never public). Deleted after the job.
    | Stale files are purged by ai-hairstyle:purge-temp (privacy fail-closed).
    */
    'temp_disk' => 'local',
    'temp_prefix' => 'ai_hairstyle_tmp',
    'temp_max_age_minutes' => (int) env('AI_HAIRSTYLE_TEMP_MAX_AGE_MINUTES', 120),

    /*
    | Allow customers to re-upload when a session has been stuck in generating
    | longer than this (minutes). Should exceed GenerateAiHairstyleJob timeout.
    */
    'stale_generating_minutes' => (int) env('AI_HAIRSTYLE_STALE_GENERATING_MINUTES', 7),
];
