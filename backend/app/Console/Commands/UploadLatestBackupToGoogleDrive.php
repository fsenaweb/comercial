<?php

namespace App\Console\Commands;

use App\Actions\Backup\UploadLatestBackupToGoogleDriveAction;
use Illuminate\Console\Command;

class UploadLatestBackupToGoogleDrive extends Command
{
    protected $signature = 'backups:sync-google-drive';

    protected $description = 'Envia o backup local mais recente para o Google Drive, se a loja tiver conectado uma conta (camada 2, bônus — não afeta o backup local)';

    public function handle(UploadLatestBackupToGoogleDriveAction $action): int
    {
        $action->execute();

        return self::SUCCESS;
    }
}
