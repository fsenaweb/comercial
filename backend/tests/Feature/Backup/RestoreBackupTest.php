<?php

namespace Tests\Feature\Backup;

use App\Models\CashRegister;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Espelha o rigor do BackupRestoreTest (Sprint 0): restaura de verdade, não
 * só confere que o comando rodou. Usa DatabaseMigrations (não RefreshDatabase)
 * pelo mesmo motivo — a Action derruba e recria o banco de teste de verdade
 * via processos `psql` externos, presos numa transação de teste não commitada
 * eles não veriam nada.
 */
class RestoreBackupTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Storage::disk('backups')->deleteDirectory(config('backup.backup.name'));

        parent::tearDown();
    }

    private function fetchConfirmationCode(User $admin): string
    {
        $response = $this->actingAs($admin)->getJson('/api/backups/restore/confirmation-code');

        return $response->json('data.code');
    }

    public function test_confirmation_code_is_random_and_admin_only(): void
    {
        $admin = User::factory()->admin()->create();

        $codeOne = $this->fetchConfirmationCode($admin);
        $codeTwo = $this->fetchConfirmationCode($admin);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $codeOne);
        $this->assertNotSame($codeOne, $codeTwo, 'O código deveria ser aleatório a cada geração.');

        $seller = User::factory()->create();
        $this->actingAs($seller)->getJson('/api/backups/restore/confirmation-code')->assertStatus(403);
    }

    public function test_admin_can_restore_a_local_backup_replacing_current_data(): void
    {
        StoreSetting::current()->update(['name' => 'Estado Antigo']);
        Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

        $disk = Storage::disk('backups');
        $zipPath = collect($disk->allFiles())->first(fn (string $path) => str_ends_with($path, '.zip'));
        $this->assertNotNull($zipPath, 'Nenhum arquivo de backup foi gerado.');

        StoreSetting::current()->update(['name' => 'Estado Novo']);
        DB::table('sessions')->insert([
            'id' => 'sessao-de-outro-terminal',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $admin = User::factory()->admin()->create();
        $code = $this->fetchConfirmationCode($admin);

        $response = $this->actingAs($admin)->postJson('/api/backups/restore', [
            'filename' => $zipPath,
            'confirmation' => $code,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('store_settings', ['id' => 1, 'name' => 'Estado Antigo']);
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_restore_is_blocked_while_a_cash_register_is_open(): void
    {
        StoreSetting::current();
        Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
        $disk = Storage::disk('backups');
        $zipPath = collect($disk->allFiles())->first(fn (string $path) => str_ends_with($path, '.zip'));

        $admin = User::factory()->admin()->create();
        CashRegister::factory()->open()->create(['opened_by' => $admin->id]);
        $code = $this->fetchConfirmationCode($admin);

        $response = $this->actingAs($admin)->postJson('/api/backups/restore', [
            'filename' => $zipPath,
            'confirmation' => $code,
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseHas('store_settings', ['id' => 1]);
    }

    public function test_restore_rejects_wrong_confirmation_code(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fetchConfirmationCode($admin);

        $response = $this->actingAs($admin)->postJson('/api/backups/restore', [
            'filename' => 'qualquer.zip',
            'confirmation' => 'ERRADO',
        ]);

        $response->assertStatus(422);
    }

    public function test_restore_rejects_a_code_already_used_once(): void
    {
        StoreSetting::current();
        Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
        $disk = Storage::disk('backups');
        $zipPath = collect($disk->allFiles())->first(fn (string $path) => str_ends_with($path, '.zip'));

        $admin = User::factory()->admin()->create();
        $code = $this->fetchConfirmationCode($admin);

        // Primeira tentativa restaura de verdade e consome o código — a
        // segunda tentativa com o mesmo código não pode mais funcionar.
        $this->actingAs($admin)->postJson('/api/backups/restore', [
            'filename' => $zipPath,
            'confirmation' => $code,
        ])->assertNoContent();

        $response = $this->actingAs($admin)->postJson('/api/backups/restore', [
            'filename' => $zipPath,
            'confirmation' => $code,
        ]);

        $response->assertStatus(422);
    }

    public function test_restore_rejects_a_filename_outside_the_real_listing(): void
    {
        Storage::fake('backups');
        $admin = User::factory()->admin()->create();
        $code = $this->fetchConfirmationCode($admin);

        $response = $this->actingAs($admin)->postJson('/api/backups/restore', [
            'filename' => '../../.env',
            'confirmation' => $code,
        ]);

        $response->assertStatus(404);
    }

    public function test_restore_accepts_a_manually_uploaded_zip_file(): void
    {
        StoreSetting::current()->update(['name' => 'Estado Antigo']);
        Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

        $disk = Storage::disk('backups');
        $zipPath = collect($disk->allFiles())->first(fn (string $path) => str_ends_with($path, '.zip'));
        $absoluteZipPath = $disk->path($zipPath);

        StoreSetting::current()->update(['name' => 'Estado Novo']);

        $admin = User::factory()->admin()->create();
        $code = $this->fetchConfirmationCode($admin);

        $response = $this->actingAs($admin)->postJson('/api/backups/restore', [
            'file' => new UploadedFile($absoluteZipPath, 'meu-backup.zip', 'application/zip', null, true),
            'confirmation' => $code,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('store_settings', ['id' => 1, 'name' => 'Estado Antigo']);
    }

    public function test_seller_cannot_restore_backups(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->postJson('/api/backups/restore', [
            'filename' => 'qualquer.zip',
            'confirmation' => 'QUALQUER',
        ])->assertStatus(403);
    }
}
