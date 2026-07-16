<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestoreBlockedByOpenCashRegisterException extends Exception
{
    public function __construct()
    {
        parent::__construct('Feche o caixa antes de restaurar um backup — a restauração substitui todos os dados atuais.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['cash_register' => [$this->getMessage()]],
        ], 409);
    }
}
