<?php

namespace App\Actions\Backup;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DisconnectGoogleDriveAction
{
    public function execute(): void
    {
        $settings = StoreSetting::current();

        if ($settings->google_drive_refresh_token) {
            try {
                Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
                    'token' => $settings->google_drive_refresh_token,
                ]);
            } catch (\Throwable $e) {
                // Best-effort: mesmo se a Google não conseguir revogar agora,
                // o app esquece o token localmente e para de usá-lo.
                Log::warning('Falha ao revogar token do Google Drive.', ['message' => $e->getMessage()]);
            }
        }

        $settings->forceFill([
            'google_drive_refresh_token' => null,
            'google_drive_account_email' => null,
            'google_drive_folder_id' => null,
            'google_drive_connected_at' => null,
        ])->save();
    }
}
