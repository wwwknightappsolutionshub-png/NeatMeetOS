<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook signature enforcement
    |--------------------------------------------------------------------------
    |
    | When true, inbound webhooks for accounts that have a webhook_secret (or
    | driver credential secret) must present a valid HMAC signature or the
    | request is rejected with 401. When the account has no secret configured,
    | signature_valid is stored as null (dev) or the request is rejected when
    | require_secret is also true.
    |
    */
    'webhooks' => [
        'require_valid_signature' => (bool) env('INTEGRATIONS_WEBHOOK_REQUIRE_SIGNATURE', true),
        'require_secret' => (bool) env('INTEGRATIONS_WEBHOOK_REQUIRE_SECRET', false),
        'stripe_tolerance_seconds' => (int) env('INTEGRATIONS_STRIPE_TOLERANCE', 300),
    ],

];
