<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_local_backups(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('2026-07-14/older-backup.zip', 'conteudo-1');
        sleep(1);
        Storage::disk('backups')->put('2026-07-15/newer-backup.zip', 'conteudo-2');

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/backups');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'local' => [['name', 'path', 'size', 'created_at']],
                'google_drive' => ['connected', 'account_email', 'connected_at', 'files', 'error'],
            ],
        ]);

        $names = collect($response->json('data.local'))->pluck('name')->all();
        $this->assertSame(['newer-backup.zip', 'older-backup.zip'], $names);
        $this->assertFalse($response->json('data.google_drive.connected'));
    }

    public function test_seller_cannot_list_backups(): void
    {
        Storage::fake('backups');
        $seller = User::factory()->create();

        $this->actingAs($seller)->getJson('/api/backups')->assertStatus(403);
    }

    public function test_guest_cannot_list_backups(): void
    {
        $this->getJson('/api/backups')->assertStatus(401);
    }
}
