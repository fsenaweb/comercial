<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidBackupFileException extends Exception
{
    public function __construct()
    {
        parent::__construct('Este arquivo não contém um backup de banco de dados válido.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['file' => [$this->getMessage()]],
        ], 422);
    }
}
