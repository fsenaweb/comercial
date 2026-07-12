<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfDeactivationException extends Exception
{
    public function __construct()
    {
        parent::__construct('Você não pode desativar a própria conta.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['active' => [$this->getMessage()]],
        ], 422);
    }
}
