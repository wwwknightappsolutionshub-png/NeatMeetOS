<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:dispatch-booking-reminders')->everyFiveMinutes();
Schedule::command('booking:dispatch-approaching-sos')->everyMinute();
Schedule::command('marketing:run-scheduled')->everyFiveMinutes();
Schedule::command('analytics:run-scheduled')->everyFiveMinutes();
Schedule::command('platform:dispatch-upgrade-campaigns')->hourly();
Schedule::command('platform:process-billing')->dailyAt('06:15');
Schedule::command('next-visit:dispatch-reminders')->everyFifteenMinutes();
Schedule::command('next-visit:missed-digest')->hourly();
Schedule::command('ai-hairstyle:purge-temp')->hourly();
