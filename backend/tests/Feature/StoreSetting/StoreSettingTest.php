<?php

namespace Tests\Feature\StoreSetting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_store_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/store-settings');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'name', 'trade_name', 'cnpj', 'email', 'phone', 'mobile_phone',
                'zip_code', 'address', 'address_number', 'address_complement', 'neighborhood', 'city', 'state',
                'logo_path', 'logo_url', 'require_seller_on_sale', 'auto_open_cash_register',
            ],
        ]);

        // Provisionamento implícito do registro único não pode deixar os
        // booleanos como null (save() não recarrega defaults de coluna).
        $response->assertJsonPath('data.require_seller_on_sale', false)
            ->assertJsonPath('data.auto_open_cash_register', false);
    }

    public function test_guest_cannot_view_store_settings(): void
    {
        $this->getJson('/api/store-settings')->assertStatus(401);
    }

    public function test_admin_can_update_store_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/store-settings', [
            'name' => 'Loja do Zé',
            'require_seller_on_sale' => true,
            'auto_open_cash_register' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Loja do Zé');
        $this->assertDatabaseHas('store_settings', ['id' => 1, 'name' => 'Loja do Zé']);
    }

    public function test_seller_cannot_update_store_settings(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->putJson('/api/store-settings', [
            'name' => 'Loja do Zé',
            'require_seller_on_sale' => true,
            'auto_open_cash_register' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/store-settings', [
            'require_seller_on_sale' => true,
            'auto_open_cash_register' => false,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_extended_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/store-settings', [
            'name' => 'Loja do Zé',
            'trade_name' => 'JP Parafusos',
            'email' => 'contato@jpparafusos.com.br',
            'mobile_phone' => '(41) 9 9999-9999',
            'zip_code' => '80000-000',
            'address' => 'Rua 15 de Março',
            'address_number' => '836',
            'neighborhood' => 'Centro',
            'city' => 'Curitiba',
            'state' => 'PR',
            'require_seller_on_sale' => false,
            'auto_open_cash_register' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.trade_name', 'JP Parafusos')
            ->assertJsonPath('data.city', 'Curitiba')
            ->assertJsonPath('data.state', 'PR');
    }

    public function test_update_rejects_invalid_email(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/store-settings', [
            'name' => 'Loja do Zé',
            'email' => 'não-é-email',
            'require_seller_on_sale' => false,
            'auto_open_cash_register' => false,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_upload_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/store-settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.png', 50, 'image/png'),
        ]);

        $response->assertOk();
        $logoPath = $response->json('data.logo_path');
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
        $this->assertNotNull($response->json('data.logo_url'));
    }

    public function test_uploading_new_logo_replaces_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $first = $this->actingAs($admin)->postJson('/api/store-settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.png', 50, 'image/png'),
        ])->json('data.logo_path');

        $second = $this->actingAs($admin)->postJson('/api/store-settings/logo', [
            'logo' => UploadedFile::fake()->create('logo2.png', 50, 'image/png'),
        ])->json('data.logo_path');

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_seller_cannot_upload_logo(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/store-settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.png', 50, 'image/png'),
        ]);

        $response->assertStatus(403);
    }

    public function test_logo_upload_rejects_non_image_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/store-settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.pdf', 100),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['logo']);
    }
}
