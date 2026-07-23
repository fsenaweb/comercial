<?php

namespace App\Actions\Backup;

class EnsureBackupDirectoryIsAccessibleAction
{
    /**
     * Auto-cura o diretório de backups: cria se não existir (0755) e, se já
     * existir com permissão restritiva (0700/0600 — herdada de antes de
     * `config/filesystems.php` ganhar `visibility => public` no disco
     * `backups`), devolve pra 0755/0644 recursivamente.
     *
     * Achado real na loja do cliente (2026-07-23): Windows + Docker Desktop
     * (bind mount via WSL2) não repassa esse ajuste de config pros
     * diretórios que já existiam antes do deploy — só afeta criação nova.
     * Roda a cada acesso à tela de Backup (idempotente e barata; a pasta
     * tem poucos arquivos, não é o catálogo de produtos), pra corrigir
     * instalações já quebradas sem exigir um passo manual do cliente.
     */
    public function execute(string $root): void
    {
        if (! is_dir($root)) {
            @mkdir($root, 0755, true);

            return;
        }

        $this->fixPermissions($root);
    }

    private function fixPermissions(string $path): void
    {
        if (is_dir($path)) {
            if ((fileperms($path) & 0777) !== 0755) {
                @chmod($path, 0755);
            }

            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $this->fixPermissions("{$path}/{$entry}");
            }

            return;
        }

        if ((fileperms($path) & 0777) !== 0644) {
            @chmod($path, 0644);
        }
    }
}
