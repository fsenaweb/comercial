<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterClosedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Não há caixa aberto no momento.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['cash_register' => [$this->getMessage()]],
        ], 422);
    }
}
