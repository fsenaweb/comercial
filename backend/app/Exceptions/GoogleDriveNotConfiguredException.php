<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleDriveNotConfiguredException extends Exception
{
    public function __construct()
    {
        parent::__construct('Backup no Google Drive não está configurado neste ambiente.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['google_drive' => [$this->getMessage()]],
        ], 422);
    }
}
