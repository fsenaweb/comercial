<?php

namespace App\Actions\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GenerateRestoreConfirmationCodeAction
{
    private const TTL_MINUTES = 5;

    /**
     * Código de confirmação aleatório e de uso único, exigido para restaurar
     * um backup — evita que a fricção de "digite uma palavra" vire só teatro
     * (uma palavra fixa poderia ser cravada num script que chama a API
     * direto, sem passar pela tela). Validado no servidor (`RestoreBackupAction`
     * via `BackupController::restore`), nunca só no front.
     */
    public function execute(int $userId): string
    {
        $code = strtoupper(Str::random(6));

        Cache::put($this->cacheKey($userId), $code, now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    public static function cacheKey(int $userId): string
    {
        return "restore_confirmation_code_{$userId}";
    }
}
