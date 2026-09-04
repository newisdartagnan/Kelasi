<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Le rappel part à six heures, heure de Kinshasa : avant la première séance,
// et assez tôt pour qu'un chef de promotion rattrape la veille.
Schedule::command('kelasi:rappels')
    ->dailyAt('06:00')
    ->timezone('Africa/Kinshasa')
    ->weekdays();
