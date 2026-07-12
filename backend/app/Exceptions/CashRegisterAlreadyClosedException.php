<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterAlreadyClosedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Este caixa já está fechado.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['cash_register' => [$this->getMessage()]],
        ], 422);
    }
}
