<?php

namespace Tests\Feature\Backup;

use App\Actions\Backup\RunManualBackupAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunManualBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_a_manual_backup(): void
    {
        $this->mock(RunManualBackupAction::class)->shouldReceive('execute')->once();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/backups/run')->assertNoContent();
    }

    public function test_seller_cannot_trigger_a_manual_backup(): void
    {
        $this->mock(RunManualBackupAction::class)->shouldNotReceive('execute');

        $seller = User::factory()->create();

        $this->actingAs($seller)->postJson('/api/backups/run')->assertStatus(403);
    }

    public function test_guest_cannot_trigger_a_manual_backup(): void
    {
        $this->mock(RunManualBackupAction::class)->shouldNotReceive('execute');

        $this->postJson('/api/backups/run')->assertStatus(401);
    }
}
