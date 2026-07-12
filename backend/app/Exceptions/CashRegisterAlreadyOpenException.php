<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterAlreadyOpenException extends Exception
{
    public function __construct()
    {
        parent::__construct('Já existe um caixa aberto. Feche o caixa atual antes de abrir um novo.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['cash_register' => [$this->getMessage()]],
        ], 409);
    }
}
