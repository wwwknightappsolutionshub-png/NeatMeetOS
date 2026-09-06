<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant admin session TTL
    |--------------------------------------------------------------------------
    |
    | Sanctum personal access tokens for salon (tenant) users expire after this
    | many seconds. Activity in the admin app slides the window forward.
    |
    */

    'ttl_seconds' => (int) env('TENANT_SESSION_TTL_SECONDS', 4 * 60 * 60),

    /*
    |--------------------------------------------------------------------------
    | Logout WhatsApp warning
    |--------------------------------------------------------------------------
    |
    | Warn the registered WhatsApp number this many seconds before expiry.
    |
    */

    'warning_before_seconds' => (int) env('TENANT_SESSION_WARNING_BEFORE_SECONDS', 10 * 60),

    /*
    |--------------------------------------------------------------------------
    | Extend throttle
    |--------------------------------------------------------------------------
    |
    | At most one expires_at write / warning reschedule per token this often.
    |
    */

    'extend_throttle_seconds' => (int) env('TENANT_SESSION_EXTEND_THROTTLE_SECONDS', 60),

    'token_name' => 'neatmeet-os-web',

    'warning_message' => "You're About To Be Logged Out From Your NeatMeet, Visit Now To Stay Logged In",
];
