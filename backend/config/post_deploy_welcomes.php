<?php

return [
    /**
     * One-shot post-deploy workspace welcomes (email + WhatsApp).
     *
     * After production deploy, run once (uses scheduled_at when set):
     *   php artisan tenants:queue-workspace-welcomes
     *
     * Override send time:
     *   php artisan tenants:queue-workspace-welcomes --at="2026-08-29 10:00:00"
     */
    'scheduled_at' => '2026-08-29 10:00:00',
    'scheduled_timezone' => 'Europe/London',

    'recipients' => [
        ['email' => 'nooonyyyy14@gmail.com', 'phone' => '+447723010888'],
        ['email' => 'khalid_rrd@outlook.com', 'phone' => '+447535210020'],
        ['email' => 'omphalosbc@gmail.com', 'phone' => '+447756183454'],
        ['email' => 'bcindy87@yahoo.com', 'phone' => '+447853101133'],
        ['email' => 'tonyinfobrown@yahoo.com', 'phone' => '+447780237539'],
        ['email' => 'golbscissors89@hotmail.com', 'phone' => '+447392993812'],
    ],
];
