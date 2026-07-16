<?php

namespace App\Actions\Backup;

use App\Exceptions\GoogleDriveNotConfiguredException;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PollGoogleDriveConnectionAction
{
    public function execute(): array
    {
        $clientId = config('services.google_oauth.client_id');
        $clientSecret = config('services.google_oauth.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new GoogleDriveNotConfiguredException;
        }

        $pending = Cache::get(StartGoogleDriveConnectionAction::CACHE_KEY);

        if (! $pending) {
            return ['status' => 'no_pending_connection'];
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'device_code' => $pending['device_code'],
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
        ]);

        $data = $response->json();

        if ($response->successful() && isset($data['access_token'])) {
            Cache::forget(StartGoogleDriveConnectionAction::CACHE_KEY);

            $accessToken = $data['access_token'];
            $settings = StoreSetting::current();

            $email = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo')
                ->json('email');

            $folderId = $settings->google_drive_folder_id
                ?? $this->createBackupsFolder($accessToken, $settings->name);

            $settings->forceFill([
                'google_drive_refresh_token' => $data['refresh_token'] ?? null,
                'google_drive_account_email' => $email,
                'google_drive_folder_id' => $folderId,
                'google_drive_connected_at' => now(),
            ])->save();

            return ['status' => 'connected', 'account_email' => $email];
        }

        return match ($data['error'] ?? null) {
            'authorization_pending', 'slow_down' => ['status' => 'pending'],
            'access_denied' => $this->clearPending('denied'),
            default => $this->clearPending('expired'),
        };
    }

    private function clearPending(string $status): array
    {
        Cache::forget(StartGoogleDriveConnectionAction::CACHE_KEY);

        return ['status' => $status];
    }

    private function createBackupsFolder(string $accessToken, string $storeName): ?string
    {
        return Http::withToken($accessToken)
            ->post('https://www.googleapis.com/drive/v3/files', [
                'name' => "Backups - {$storeName}",
                'mimeType' => 'application/vnd.google-apps.folder',
            ])
            ->json('id');
    }
}
