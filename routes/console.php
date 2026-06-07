<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:escalate-breached-sla')->hourly();
Schedule::command('commercial:check-offer-mail-nudges')->dailyAt('08:30');
Schedule::command('mail:purge-non-important-bodies')->monthlyOn(1, '03:00');
Schedule::command('mail:sync')->everyTenMinutes();
Schedule::command('contractors:sync-operational-status')->dailyAt('02:30');

$dispositionTimezone = config('disposition.timezone', 'Europe/Samara');

Schedule::command('disposition:remind-unfilled-slots morning')
    ->dailyAt(config('disposition.reminder_schedule.morning', '10:00'))
    ->timezone($dispositionTimezone);

Schedule::command('disposition:remind-unfilled-slots evening')
    ->dailyAt(config('disposition.reminder_schedule.evening', '16:00'))
    ->timezone($dispositionTimezone);
