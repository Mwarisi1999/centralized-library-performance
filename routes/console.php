<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:send-task-reminders')
    ->dailyAt((string) config('notifications.deadline_reminders.schedule', '07:00'))
    ->timezone((string) config('app.timezone'))
    ->withoutOverlapping(10);
