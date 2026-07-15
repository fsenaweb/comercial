<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidNfeXmlException extends Exception
{
    public function __construct()
    {
        parent::__construct('Não foi possível ler o arquivo XML da NF-e. Verifique se é um XML de NF-e válido.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['xml' => [$this->getMessage()]],
        ], 422);
    }
}
