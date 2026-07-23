<?php

namespace App\Actions\Backup;

use Illuminate\Support\Facades\Artisan;

class RunManualBackupAction
{
    /**
     * Mesma dupla de comandos do agendamento diário (routes/console.php),
     * disparada sob demanda pelo botão "Gerar backup agora" (Configurações >
     * Backup e restauração) — o cliente pediu por precisar de uma cópia fora
     * do horário fixo das 10h.
     */
    public function execute(): void
    {
        Artisan::call('backup:run');
        (new UploadLatestBackupToGoogleDriveAction)->execute();
    }
}
