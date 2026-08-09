<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('streaks:maintain')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('quests:generate')->dailyAt('00:01')->withoutOverlapping();
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();
