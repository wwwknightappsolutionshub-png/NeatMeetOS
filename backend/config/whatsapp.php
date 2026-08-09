<?php

return [
    'default_provider' => env('WHATSAPP_PROVIDER', 'genius'),

    'genius' => [
        'api_key' => env('WHATSAPP_GENIUS_API_KEY'),
        'session_id' => env('WHATSAPP_GENIUS_SESSION_ID'),
        'base_url' => env('WHATSAPP_GENIUS_BASE_URL', 'https://restapi.geniusdevel.com'),
    ],
];
