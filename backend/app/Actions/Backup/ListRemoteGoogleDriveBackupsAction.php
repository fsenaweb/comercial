<?php

namespace App\Actions\Backup;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;

class ListRemoteGoogleDriveBackupsAction
{
    /**
     * @return array{connected: bool, account_email: ?string, connected_at: ?string, files: array, error: ?string}
     */
    public function execute(): array
    {
        $settings = StoreSetting::current();

        $base = [
            'connected' => (bool) $settings->google_drive_refresh_token,
            'account_email' => $settings->google_drive_account_email,
            'connected_at' => $settings->google_drive_connected_at?->toIso8601String(),
            'files' => [],
            'error' => null,
        ];

        if (! $base['connected']) {
            return $base;
        }

        try {
            $accessToken = (new RefreshGoogleDriveAccessTokenAction)->execute($settings->google_drive_refresh_token);

            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/drive/v3/files', [
                    'q' => "'{$settings->google_drive_folder_id}' in parents and trashed = false",
                    'fields' => 'files(id,name,size,createdTime)',
                    'orderBy' => 'createdTime desc',
                ])
                ->throw();

            $base['files'] = $response->json('files', []);
        } catch (\Throwable $e) {
            // Não conseguir listar (rede, token revogado) não deve derrubar a
            // tela inteira — mostra o estado "conectado" com um aviso.
            $base['error'] = 'Não foi possível consultar os backups no Google Drive agora.';
        }

        return $base;
    }
}
