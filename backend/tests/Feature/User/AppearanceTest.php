<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_appearance(): void
    {
        $this->putJson('/api/me/appearance', [
            'theme' => 'dark',
            'font_scale' => 'large',
        ])->assertStatus(401);
    }

    public function test_defaults_are_light_and_medium(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame('light', $user->theme->value);
        $this->assertSame('medium', $user->font_scale->value);
    }

    public function test_any_authenticated_role_can_update_own_appearance(): void
    {
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->putJson('/api/me/appearance', [
            'theme' => 'dark',
            'font_scale' => 'large',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.font_scale', 'large');

        $this->assertSame('dark', $seller->fresh()->theme->value);
        $this->assertSame('large', $seller->fresh()->font_scale->value);
    }

    public function test_appearance_is_reflected_in_me_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/me/appearance', [
            'theme' => 'dark',
            'font_scale' => 'small',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.font_scale', 'small');
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/me/appearance', [
            'theme' => 'blue',
            'font_scale' => 'medium',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['theme']);
    }

    public function test_invalid_font_scale_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/me/appearance', [
            'theme' => 'light',
            'font_scale' => 'huge',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['font_scale']);
    }

    public function test_missing_fields_are_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/me/appearance', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['theme', 'font_scale']);
    }
}
