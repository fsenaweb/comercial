<?php

namespace App\Actions\Backup;

use App\Exceptions\InvalidBackupFileException;
use App\Exceptions\RestoreBlockedByOpenCashRegisterException;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ZipArchive;

class RestoreBackupAction
{
    /**
     * Operação destrutiva e irreversível: substitui o banco de dados inteiro
     * pelo conteúdo do backup. Derruba o banco atual (e todas as conexões
     * ativas de outros terminais/telas com ele) e recarrega o dump — por
     * isso a trava de caixa aberto e a confirmação exigida no Form Request
     * (`RestoreBackupRequest`) antes de chegar aqui.
     */
    public function execute(string $zipPath): void
    {
        if (CashRegister::current()) {
            throw new RestoreBlockedByOpenCashRegisterException;
        }

        $extractDir = storage_path('app/restore-'.Str::uuid());
        File::makeDirectory($extractDir, recursive: true);

        try {
            $zip = new ZipArchive;
            $zip->open($zipPath);
            $zip->extractTo($extractDir);
            $zip->close();

            $sqlFiles = glob($extractDir.'/db-dumps/*.sql');

            if (empty($sqlFiles)) {
                throw new InvalidBackupFileException;
            }

            $this->restoreDatabase($this->sanitizeDump($sqlFiles[0]));

            Log::warning('Backup restaurado sobre o banco de dados ativo.', ['arquivo' => basename($zipPath)]);
        } finally {
            File::deleteDirectory($extractDir);
        }
    }

    /**
     * A imagem PHP traz cliente `psql`/`pg_dump` (17.x) mais novo que o
     * servidor Postgres do projeto (16.x, ver `docker-compose.yml`) — o dump
     * gerado inclui `SET transaction_timeout = 0;`, um parâmetro que só
     * existe a partir do Postgres 17 e que o servidor 16 rejeita
     * ("unrecognized configuration parameter"). Removida antes de restaurar;
     * é só um ajuste de sessão do dump, não afeta os dados restaurados.
     */
    private function sanitizeDump(string $sqlFile): string
    {
        $contents = preg_replace('/^SET transaction_timeout.*$/m', '', File::get($sqlFile));
        File::put($sqlFile, $contents);

        return $sqlFile;
    }

    private function restoreDatabase(string $sqlFile): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $env = ['PGPASSWORD' => $connection['password']];
        $host = $connection['host'];
        $database = $connection['database'];
        $username = $connection['username'];

        // Fecha a conexão do próprio processo Laravel antes de derrubar o
        // banco — senão ela mesma impede o DROP DATABASE a seguir.
        DB::disconnect();

        $run = fn (string $db, string $sql) => Process::timeout(300)->env($env)->run([
            'psql', '-h', $host, '-U', $username, '-d', $db, '-v', 'ON_ERROR_STOP=1', '-c', $sql,
        ])->throw();

        // Encerra outras conexões ativas (outros terminais/telas) — sem isso
        // o DROP DATABASE falha com "database is being accessed by other users".
        $run('postgres', "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$database}' AND pid <> pg_backend_pid();");
        $run('postgres', "DROP DATABASE IF EXISTS \"{$database}\";");
        $run('postgres', "CREATE DATABASE \"{$database}\" OWNER \"{$username}\";");

        Process::timeout(300)->env($env)
            ->run(['psql', '-h', $host, '-U', $username, '-d', $database, '-v', 'ON_ERROR_STOP=1', '-f', $sqlFile])
            ->throw();

        // Derruba as sessões de todo mundo (os dados de sessão agora são os
        // do momento do backup, não os atuais) — força um novo login.
        Process::timeout(60)->env($env)
            ->run(['psql', '-h', $host, '-U', $username, '-d', $database, '-c', 'TRUNCATE sessions;']);
    }
}
