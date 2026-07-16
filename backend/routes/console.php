<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário local (regra de ouro: testar o restore, não só o backup —
// ver docs/01-architecture.md). Horário definido pelo PM (10h, não de
// madrugada) — pg_dump não trava a loja em uso, é seguro rodar em horário
// comercial.
Schedule::command('backup:clean')->daily()->at('09:45');
Schedule::command('backup:run')->daily()->at('10:00');

// Camada 2 (bônus): envia o backup local recém-gerado para o Google Drive,
// se a loja tiver conectado uma conta. Síncrono (sem worker de fila no
// projeto) — roda logo após o backup:run terminar.
Schedule::command('backups:sync-google-drive')->daily()->at('10:15');

