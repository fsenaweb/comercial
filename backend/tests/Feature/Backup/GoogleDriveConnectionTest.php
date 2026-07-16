<?php

namespace Tests\Feature\Backup;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google_oauth.client_id', 'test-client-id');
        Config::set('services.google_oauth.client_secret', 'test-client-secret');
    }

    public function test_connect_returns_the_device_flow_code_and_verification_url(): void
    {
        Http::fake([
            'oauth2.googleapis.com/device/code' => Http::response([
                'device_code' => 'device-abc',
                'user_code' => 'ABCD-EFGH',
                'verification_url' => 'https://www.google.com/device',
                'expires_in' => 1800,
                'interval' => 5,
            ]),
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/store-settings/google-drive/connect');

        $response->assertOk()
            ->assertJsonPath('data.user_code', 'ABCD-EFGH')
            ->assertJsonPath('data.verification_url', 'https://www.google.com/device');
    }

    public function test_connect_fails_clearly_when_google_oauth_is_not_configured(): void
    {
        Config::set('services.google_oauth.client_id', null);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/store-settings/google-drive/connect');

        $response->assertStatus(422);
    }

    public function test_status_reports_pending_while_the_user_has_not_authorized_yet(): void
    {
        Http::fake([
            'oauth2.googleapis.com/device/code' => Http::response([
                'device_code' => 'device-abc',
                'user_code' => 'ABCD-EFGH',
                'verification_url' => 'https://www.google.com/device',
                'expires_in' => 1800,
                'interval' => 5,
            ]),
            'oauth2.googleapis.com/token' => Http::response(['error' => 'authorization_pending'], 400),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson('/api/store-settings/google-drive/connect')->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/store-settings/google-drive/status');

        $response->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_status_connects_the_account_once_google_authorizes_it(): void
    {
        Http::fake([
            'oauth2.googleapis.com/device/code' => Http::response([
                'device_code' => 'device-abc',
                'user_code' => 'ABCD-EFGH',
                'verification_url' => 'https://www.google.com/device',
                'expires_in' => 1800,
                'interval' => 5,
            ]),
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-456',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'lojista@gmail.com']),
            'www.googleapis.com/drive/v3/files' => Http::response(['id' => 'folder-789']),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson('/api/store-settings/google-drive/connect')->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/store-settings/google-drive/status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.account_email', 'lojista@gmail.com');

        $this->assertDatabaseHas('store_settings', [
            'id' => 1,
            'google_drive_account_email' => 'lojista@gmail.com',
            'google_drive_folder_id' => 'folder-789',
        ]);

        $settings = StoreSetting::current();
        $this->assertSame('refresh-token-456', $settings->google_drive_refresh_token);
    }

    public function test_disconnect_clears_the_stored_connection(): void
    {
        $settings = StoreSetting::current();
        $settings->forceFill([
            'google_drive_refresh_token' => 'refresh-token-456',
            'google_drive_account_email' => 'lojista@gmail.com',
            'google_drive_folder_id' => 'folder-789',
            'google_drive_connected_at' => now(),
        ])->save();

        Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson('/api/store-settings/google-drive');

        $response->assertOk()->assertJsonPath('data.google_drive_connected', false);
        $this->assertDatabaseHas('store_settings', [
            'id' => 1,
            'google_drive_refresh_token' => null,
            'google_drive_account_email' => null,
        ]);
    }

    public function test_seller_cannot_manage_google_drive_connection(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->getJson('/api/store-settings/google-drive/connect')->assertStatus(403);
        $this->actingAs($seller)->getJson('/api/store-settings/google-drive/status')->assertStatus(403);
        $this->actingAs($seller)->deleteJson('/api/store-settings/google-drive')->assertStatus(403);
    }
}
