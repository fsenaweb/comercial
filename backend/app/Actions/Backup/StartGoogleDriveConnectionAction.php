<?php

namespace App\Actions\Backup;

use App\Exceptions\GoogleDriveNotConfiguredException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StartGoogleDriveConnectionAction
{
    // Só drive.file (o app só enxerga o que ele mesmo cria) + email/openid
    // para identificar a conta conectada na tela de configuração.
    private const SCOPE = 'https://www.googleapis.com/auth/drive.file openid email';

    // Loja única, um fluxo de conexão por vez: chave fixa é suficiente.
    public const CACHE_KEY = 'google_drive_pending_device_code';

    public function execute(): array
    {
        $clientId = config('services.google_oauth.client_id');

        if (! $clientId) {
            throw new GoogleDriveNotConfiguredException;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/device/code', [
            'client_id' => $clientId,
            'scope' => self::SCOPE,
        ])->throw();

        $data = $response->json();

        Cache::put(self::CACHE_KEY, [
            'device_code' => $data['device_code'],
            'interval' => $data['interval'] ?? 5,
        ], now()->addSeconds($data['expires_in'] ?? 600));

        return [
            'user_code' => $data['user_code'],
            'verification_url' => $data['verification_url'] ?? $data['verification_uri'],
            'interval' => $data['interval'] ?? 5,
            'expires_in' => $data['expires_in'] ?? 600,
        ];
    }
}
