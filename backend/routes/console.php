<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário local (regra de ouro: testar o restore, não só o backup —
// ver docs/01-architecture.md). Upload ao Google Drive entra depois como disk extra.
Schedule::command('backup:clean')->daily()->at('01:30');
Schedule::command('backup:run')->daily()->at('02:00');

