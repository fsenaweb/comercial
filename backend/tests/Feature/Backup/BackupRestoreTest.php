<?php

namespace Tests\Feature\Backup;

use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * "Regra inegociável: testar o restore, não só o backup" — docs/01-architecture.md.
 * Gera um backup real do banco de testes, restaura o dump em um banco descartável
 * e confirma que os dados batem — não apenas que o comando `backup:run` saiu com sucesso.
 *
 * Usa DatabaseMigrations (não RefreshDatabase): o `pg_dump` roda como processo
 * externo, numa conexão separada da conexão de teste — se os dados ficassem presos
 * numa transação de teste não commitada (como o RefreshDatabase faz), o dump nunca
 * os veria.
 */
class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    private string $restoreDatabase = 'comercial_backup_restore_check';

    protected function tearDown(): void
    {
        Storage::disk('backups')->deleteDirectory(config('backup.backup.name'));
        File::deleteDirectory(storage_path('app/backup-restore-test'));
        $this->runPsql('postgres', "DROP DATABASE IF EXISTS {$this->restoreDatabase}");

        parent::tearDown();
    }

    public function test_database_backup_can_be_restored_and_matches_original_data(): void
    {
        StoreSetting::current()->update(['name' => 'Loja Testada no Restore']);

        Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

        $disk = Storage::disk('backups');
        $zipPath = collect($disk->allFiles())->first(fn (string $path) => str_ends_with($path, '.zip'));

        $this->assertNotNull($zipPath, 'Nenhum arquivo de backup foi gerado.');

        $localZipPath = $disk->path($zipPath);
        $extractDir = storage_path('app/backup-restore-test');
        File::deleteDirectory($extractDir);
        File::makeDirectory($extractDir, recursive: true);

        $zip = new ZipArchive();
        $zip->open($localZipPath);
        $zip->extractTo($extractDir);
        $zip->close();

        $sqlFiles = glob($extractDir.'/db-dumps/*.sql');
        $this->assertNotEmpty($sqlFiles, 'O backup não contém dump SQL do banco.');

        $this->runPsql('postgres', "CREATE DATABASE {$this->restoreDatabase}")->throw();

        $restoreResult = Process::env($this->pgEnv())
            ->run(['psql', '-h', 'postgres', '-U', 'comercial', '-d', $this->restoreDatabase, '-f', $sqlFiles[0]]);
        $restoreResult->throw();

        $check = Process::env($this->pgEnv())->run([
            'psql', '-h', 'postgres', '-U', 'comercial', '-d', $this->restoreDatabase,
            '-t', '-A', '-c', "SELECT name FROM store_settings WHERE id = 1",
        ]);
        $check->throw();

        $this->assertSame('Loja Testada no Restore', trim($check->output()));
    }

    private function runPsql(string $database, string $sql): \Illuminate\Contracts\Process\ProcessResult
    {
        return Process::env($this->pgEnv())
            ->run(['psql', '-h', 'postgres', '-U', 'comercial', '-d', $database, '-c', $sql]);
    }

    private function pgEnv(): array
    {
        return ['PGPASSWORD' => 'comercial'];
    }
}
