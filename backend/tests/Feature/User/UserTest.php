<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    public function test_seller_cannot_list_users(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->getJson('/api/users')->assertStatus(403);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_any_authenticated_role_can_list_active_users(): void
    {
        User::factory()->create(['active' => true]);
        User::factory()->create(['active' => false]);
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->getJson('/api/users/active');

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertArrayNotHasKey('email', $response->json('data.0'));
    }

    public function test_guest_cannot_list_active_users(): void
    {
        $this->getJson('/api/users/active')->assertStatus(401);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nova Vendedora',
            'email' => 'vendedora@loja.local',
            'password' => 'senha1234',
            'role' => 'seller',
            'commission_percent' => 5,
            'active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Nova Vendedora')
            ->assertJsonPath('data.role', 'seller')
            ->assertJsonPath('data.role_label', 'Vendedor');
        $this->assertDatabaseHas('users', ['email' => 'vendedora@loja.local']);

        $user = User::where('email', 'vendedora@loja.local')->firstOrFail();
        $this->assertTrue(Hash::check('senha1234', $user->password));
    }

    public function test_seller_cannot_create_user(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->postJson('/api/users', [
            'name' => 'Nova Vendedora',
            'email' => 'vendedora@loja.local',
            'password' => 'senha1234',
            'role' => 'seller',
            'active' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_requires_name_password_and_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'password', 'role', 'active']);
    }

    public function test_create_does_not_require_email(): void
    {
        // Pedido do cliente (2026-07-21): nem todo "vendedor" cadastrado só
        // pra aparecer no seletor do PDV (F3) precisa logar no sistema — o
        // e-mail só é obrigatório pra quem realmente precisa de acesso.
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Vendedor Sem E-mail',
            'password' => 'senha',
            'role' => 'seller',
            'active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.email', null);
        $this->assertDatabaseHas('users', ['name' => 'Vendedor Sem E-mail', 'email' => null]);
    }

    public function test_create_allows_multiple_users_without_email(): void
    {
        $admin = User::factory()->admin()->create();

        $first = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Vendedor A', 'password' => 'senha', 'role' => 'seller', 'active' => true,
        ]);
        $second = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Vendedor B', 'password' => 'senha', 'role' => 'seller', 'active' => true,
        ]);

        $first->assertCreated();
        $second->assertCreated();
    }

    public function test_create_does_not_require_minimum_password_length(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Vendedor Senha Curta',
            'password' => '123',
            'role' => 'seller',
            'active' => true,
        ]);

        $response->assertCreated();
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nova Vendedora',
            'email' => $existing->email,
            'password' => 'senha1234',
            'role' => 'seller',
            'active' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create(['name' => 'Antigo Nome']);

        $response = $this->actingAs($admin)->putJson("/api/users/{$seller->id}", [
            'name' => 'Nome Atualizado',
            'email' => $seller->email,
            'role' => 'cashier',
            'active' => true,
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Nome Atualizado')
            ->assertJsonPath('data.role', 'cashier');
    }

    public function test_update_with_blank_password_keeps_current_password(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $originalHash = $seller->password;

        $this->actingAs($admin)->putJson("/api/users/{$seller->id}", [
            'name' => $seller->name,
            'email' => $seller->email,
            'role' => 'seller',
            'active' => true,
        ])->assertOk();

        $this->assertSame($originalHash, $seller->fresh()->password);
    }

    public function test_update_can_set_new_password(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();

        $this->actingAs($admin)->putJson("/api/users/{$seller->id}", [
            'name' => $seller->name,
            'email' => $seller->email,
            'password' => 'novasenha123',
            'role' => 'seller',
            'active' => true,
        ])->assertOk();

        $this->assertTrue(Hash::check('novasenha123', $seller->fresh()->password));
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson("/api/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'active' => false,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['active']);
        $this->assertTrue($admin->fresh()->active);
    }

    public function test_admin_can_deactivate_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/users/{$seller->id}", [
            'name' => $seller->name,
            'email' => $seller->email,
            'role' => 'seller',
            'active' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.active', false);
        $this->assertFalse($seller->fresh()->active);
    }

    public function test_seller_cannot_update_user(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($seller)->putJson("/api/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'seller',
            'active' => true,
        ]);

        $response->assertStatus(403);
    }
}
