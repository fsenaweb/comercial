<?php

namespace App\Http\Controllers\Api;

use App\Actions\Backup\EnsureBackupDirectoryIsAccessibleAction;
use App\Actions\Backup\GenerateRestoreConfirmationCodeAction;
use App\Actions\Backup\ListRemoteGoogleDriveBackupsAction;
use App\Actions\Backup\RestoreBackupAction;
use App\Actions\Backup\RunManualBackupAction;
use App\Actions\Backup\UploadLatestBackupToGoogleDriveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backup\RestoreBackupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(ListRemoteGoogleDriveBackupsAction $remoteAction, EnsureBackupDirectoryIsAccessibleAction $ensureAccessible): JsonResponse
    {
        return response()->json([
            'data' => [
                'local' => $this->localBackups($ensureAccessible),
                'google_drive' => $remoteAction->execute(),
            ],
        ]);
    }

    public function download(string $filename, EnsureBackupDirectoryIsAccessibleAction $ensureAccessible): StreamedResponse
    {
        $disk = Storage::disk('backups');
        $ensureAccessible->execute($disk->path(''));

        // Whitelist contra a listagem real do disco (allFiles — os dumps do
        // spatie/laravel-backup ficam num subdiretório) — nunca confiar no
        // parâmetro de rota para montar um path (proteção contra traversal).
        abort_unless(in_array($filename, $disk->allFiles(), true), 404, 'Backup não encontrado.');

        return $disk->download($filename);
    }

    public function uploadLatest(UploadLatestBackupToGoogleDriveAction $action): Response
    {
        $action->execute();

        return response()->noContent();
    }

    public function run(RunManualBackupAction $action): Response
    {
        $action->execute();

        return response()->noContent();
    }

    public function confirmationCode(Request $request, GenerateRestoreConfirmationCodeAction $action): JsonResponse
    {
        return response()->json(['data' => ['code' => $action->execute($request->user()->id)]]);
    }

    public function restore(RestoreBackupRequest $request, RestoreBackupAction $action, EnsureBackupDirectoryIsAccessibleAction $ensureAccessible): Response
    {
        // Cache::pull é atômico (lê e apaga) — o código só serve para uma
        // tentativa, mesmo que a restauração falhe depois por outro motivo.
        $expectedCode = Cache::pull(GenerateRestoreConfirmationCodeAction::cacheKey($request->user()->id));
        abort_unless(
            $expectedCode && hash_equals($expectedCode, $request->string('confirmation')->toString()),
            422,
            'Código de confirmação inválido ou expirado. Gere um novo e tente de novo.',
        );

        $tempUploadPath = null;

        if ($request->hasFile('file')) {
            $tempUploadPath = storage_path('app/restore-uploads/'.Str::uuid().'.zip');
            File::ensureDirectoryExists(dirname($tempUploadPath));
            $request->file('file')->move(dirname($tempUploadPath), basename($tempUploadPath));
            $zipPath = $tempUploadPath;
        } else {
            $disk = Storage::disk('backups');
            $ensureAccessible->execute($disk->path(''));
            $filename = $request->string('filename')->toString();
            abort_unless(in_array($filename, $disk->allFiles(), true), 404, 'Backup não encontrado.');
            $zipPath = $disk->path($filename);
        }

        try {
            $action->execute($zipPath);
        } finally {
            if ($tempUploadPath) {
                File::delete($tempUploadPath);
            }
        }

        return response()->noContent();
    }

    private function localBackups(EnsureBackupDirectoryIsAccessibleAction $ensureAccessible): array
    {
        $disk = Storage::disk('backups');
        $ensureAccessible->execute($disk->path(''));

        return collect($disk->allFiles())
            ->filter(fn (string $file) => str_ends_with($file, '.zip'))
            ->map(fn (string $file) => [
                'name' => basename($file),
                'path' => $file,
                'size' => $disk->size($file),
                'created_at' => date(DATE_ATOM, $disk->lastModified($file)),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }
}
