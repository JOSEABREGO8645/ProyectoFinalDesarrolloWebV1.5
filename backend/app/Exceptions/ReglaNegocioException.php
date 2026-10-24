<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $mensaje, private int $estado = 422)
    {
        parent::__construct($mensaje);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->estado);
    }
}