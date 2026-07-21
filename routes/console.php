<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bolt:retry-webhooks')->everyMinute();
Schedule::command('bolt:prune-operational-logs')->dailyAt('02:15');
Schedule::command('bolt:prune-webhook-deliveries')->dailyAt('02:25');
