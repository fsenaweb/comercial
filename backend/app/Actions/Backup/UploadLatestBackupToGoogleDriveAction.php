<?php

namespace App\Actions\Backup;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadLatestBackupToGoogleDriveAction
{
    /**
     * Camada 2 (bônus) nunca pode quebrar a camada 1: qualquer falha aqui é
     * logada e engolida — o backup local já foi concluído com sucesso antes
     * desta ação rodar.
     */
    public function execute(): void
    {
        $settings = StoreSetting::current();

        if (! $settings->google_drive_refresh_token) {
            return;
        }

        try {
            $filename = $this->latestLocalBackup();

            if (! $filename) {
                return;
            }

            $accessToken = (new RefreshGoogleDriveAccessTokenAction)->execute($settings->google_drive_refresh_token);
            $this->upload($accessToken, $settings->google_drive_folder_id, $filename);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar backup para o Google Drive.', ['message' => $e->getMessage()]);
        }
    }

    private function latestLocalBackup(): ?string
    {
        $disk = Storage::disk('backups');
        $files = collect($disk->allFiles())
            ->filter(fn (string $file) => str_ends_with($file, '.zip'))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file));

        return $files->first();
    }

    private function upload(string $accessToken, ?string $folderId, string $filename): void
    {
        $disk = Storage::disk('backups');

        $init = Http::withToken($accessToken)
            ->withHeaders(['X-Upload-Content-Type' => 'application/zip'])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                'name' => basename($filename),
                'parents' => $folderId ? [$folderId] : [],
            ])
            ->throw();

        $uploadUrl = $init->header('Location');

        Http::withHeaders(['Content-Type' => 'application/zip'])
            ->withBody($disk->get($filename), 'application/zip')
            ->put($uploadUrl)
            ->throw();
    }
}
