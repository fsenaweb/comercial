<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InactiveUserException extends Exception
{
    public function __construct()
    {
        parent::__construct('Este usuário está inativo. Procure um administrador.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['email' => [$this->getMessage()]],
        ], 403);
    }
}
