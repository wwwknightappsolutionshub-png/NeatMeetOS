<?php

return [

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
        /*
         * When true and secret_key is set, public writes require a valid token.
         * When secret is empty (local) or explicitly false (tests), verification is skipped.
         */
        'enabled' => env('TURNSTILE_ENABLED', null),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    'abuse' => [
        'turnstile_failures' => [
            'max' => 8,
            'minutes' => 15,
            'ban_hours' => 24,
        ],
        'login_failures' => [
            'max' => 5,
            'minutes' => 15,
            'ban_hours' => 1,
        ],
        'throttle_hits' => [
            'max' => 20,
            'minutes' => 10,
            'ban_hours' => 6,
        ],
        'honeypot' => [
            'ban_hours' => 24,
        ],
    ],

];
