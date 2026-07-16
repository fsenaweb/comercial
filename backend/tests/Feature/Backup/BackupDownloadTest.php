<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_an_existing_backup(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup-2026-07-15.zip', 'conteudo-do-backup');

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/api/backups/laravel-backup/backup-2026-07-15.zip/download');

        $response->assertOk();
    }

    public function test_download_rejects_a_filename_outside_the_real_listing(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup-2026-07-15.zip', 'conteudo-do-backup');

        $admin = User::factory()->admin()->create();

        // Tentativa de path traversal / arquivo inexistente: mesmo que o
        // arquivo exista fisicamente fora do disco de backup, não está na
        // listagem real (allFiles()) e deve ser rejeitado.
        $response = $this->actingAs($admin)->get('/api/backups/../../.env/download');

        $response->assertStatus(404);
    }

    public function test_seller_cannot_download_backups(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup-2026-07-15.zip', 'conteudo-do-backup');
        $seller = User::factory()->create();

        $this->actingAs($seller)
            ->get('/api/backups/laravel-backup/backup-2026-07-15.zip/download')
            ->assertStatus(403);
    }
}
