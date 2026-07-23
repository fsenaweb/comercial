<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Achado real na loja do cliente (2026-07-23): o diretório `storage/app/backup`
 * criado antes de `visibility => public` entrar em `config/filesystems.php`
 * ficava com permissão restritiva (0700) — Windows + Docker Desktop não
 * repassa esse ajuste de config pros diretórios que já existiam. A auto-cura
 * (`EnsureBackupDirectoryIsAccessibleAction`) corrige isso a cada acesso à
 * tela de Backup, sem exigir intervenção manual do cliente.
 */
class EnsureBackupDirectoryIsAccessibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_backups_fixes_a_pre_existing_restrictive_directory_permission(): void
    {
        Storage::fake('backups');
        $disk = Storage::disk('backups');
        $disk->put('laravel-backup/backup.zip', 'conteudo');
        $root = $disk->path('');

        chmod($root, 0700);
        $this->assertSame('0700', substr(sprintf('%o', fileperms($root)), -4));

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/backups');

        $response->assertOk();
        $this->assertSame('0755', substr(sprintf('%o', fileperms($root)), -4));
    }

    public function test_download_fixes_a_pre_existing_restrictive_directory_permission(): void
    {
        Storage::fake('backups');
        $disk = Storage::disk('backups');
        $disk->put('laravel-backup/backup.zip', 'conteudo');
        $root = $disk->path('');

        chmod($root, 0700);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get('/api/backups/laravel-backup/backup.zip/download');

        $response->assertOk();
        $this->assertSame('0755', substr(sprintf('%o', fileperms($root)), -4));
    }

    public function test_deploy_command_fixes_a_pre_existing_restrictive_directory_permission(): void
    {
        Storage::fake('backups');
        $disk = Storage::disk('backups');
        $disk->put('laravel-backup/backup.zip', 'conteudo');
        $root = $disk->path('');

        chmod($root, 0700);

        Artisan::call('backups:ensure-directory-permissions');

        $this->assertSame('0755', substr(sprintf('%o', fileperms($root)), -4));
    }

    public function test_creates_the_directory_when_missing_entirely(): void
    {
        Storage::fake('backups');
        $disk = Storage::disk('backups');
        $root = $disk->path('');
        rmdir($root);
        $this->assertDirectoryDoesNotExist($root);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/backups');

        $response->assertOk();
        $this->assertDirectoryExists($root);
    }
}
