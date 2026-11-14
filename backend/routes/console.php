<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copia de seguridad diaria. Requiere que corra `php artisan schedule:run` cada minuto (ver SECURITY.md).
Illuminate\Support\Facades\Schedule::command('app:backup')->dailyAt('03:00')->onOneServer();

// Limpieza diaria del registro de actividad viejo.
Illuminate\Support\Facades\Schedule::command('app:prune-audit-logs')->dailyAt('03:30')->onOneServer();
