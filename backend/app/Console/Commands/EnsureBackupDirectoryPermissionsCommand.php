<?php

namespace App\Console\Commands;

use App\Actions\Backup\EnsureBackupDirectoryIsAccessibleAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Roda a mesma auto-cura de `EnsureBackupDirectoryIsAccessibleAction` (usada
 * pelo `BackupController` a cada acesso à tela de Backup) como um passo
 * explícito do deploy (`deploy.sh`/`deploy.bat`) — sem depender de alguém
 * abrir a tela primeiro para corrigir uma permissão restritiva herdada de
 * antes de `visibility => public` em `config/filesystems.php` (achado real
 * na loja do cliente, 2026-07-23, ver docs/07-dev-environment.md).
 */
class EnsureBackupDirectoryPermissionsCommand extends Command
{
    protected $signature = 'backups:ensure-directory-permissions';

    protected $description = 'Garante que storage/app/backup existe e não está com permissão restritiva (0700/0600)';

    public function handle(EnsureBackupDirectoryIsAccessibleAction $action): int
    {
        $action->execute(Storage::disk('backups')->path(''));

        $this->info('Diretório de backups verificado.');

        return self::SUCCESS;
    }
}
