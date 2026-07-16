<?php

namespace Tests\Feature\Backup;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadLatestBackupToGoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google_oauth.client_id', 'test-client-id');
        Config::set('services.google_oauth.client_secret', 'test-client-secret');
    }

    private function connectStore(): void
    {
        StoreSetting::current()->forceFill([
            'google_drive_refresh_token' => 'refresh-token-456',
            'google_drive_account_email' => 'lojista@gmail.com',
            'google_drive_folder_id' => 'folder-789',
            'google_drive_connected_at' => now(),
        ])->save();
    }

    public function test_uploads_the_most_recent_local_backup_to_the_connected_folder(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup-older.zip', 'conteudo-antigo');
        sleep(1);
        Storage::disk('backups')->put('laravel-backup/backup-newer.zip', 'conteudo-novo');
        $this->connectStore();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token-123']),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response('', 200, [
                'Location' => 'https://www.googleapis.com/upload-session/fake-session',
            ]),
            'www.googleapis.com/upload-session/*' => Http::response(['id' => 'uploaded-file-id']),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/backups/upload-latest')->assertNoContent();

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'upload-session/fake-session')
                && $request->body() === 'conteudo-novo';
        });
    }

    public function test_scheduled_command_triggers_the_upload(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup.zip', 'conteudo');
        $this->connectStore();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token-123']),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response('', 200, [
                'Location' => 'https://www.googleapis.com/upload-session/fake-session',
            ]),
            'www.googleapis.com/upload-session/*' => Http::response(['id' => 'uploaded-file-id']),
        ]);

        Artisan::call('backups:sync-google-drive');

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'upload-session/fake-session'));
    }

    public function test_upload_failure_is_swallowed_and_never_breaks_the_request(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup.zip', 'conteudo');
        $this->connectStore();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([], 500),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/backups/upload-latest')->assertNoContent();
    }

    public function test_is_a_no_op_when_google_drive_is_not_connected(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('laravel-backup/backup.zip', 'conteudo');

        Http::fake();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/backups/upload-latest')->assertNoContent();

        Http::assertNothingSent();
    }
}
