<?php

return [
    /**
     * One-shot post-deploy workspace welcomes (email + WhatsApp).
     * Run: php artisan tenants:queue-workspace-welcomes --delay=5
     */
    'recipients' => [
        ['email' => 'nooonyyyy14@gmail.com', 'phone' => '+447723010888'],
        ['email' => 'khalid_rrd@outlook.com', 'phone' => '+447535210020'],
        ['email' => 'omphalosbc@gmail.com', 'phone' => '+447756183454'],
        ['email' => 'bcindy87@yahoo.com', 'phone' => '+447853101133'],
        ['email' => 'tonyinfobrown@yahoo.com', 'phone' => '+447780237539'],
    ],
];
