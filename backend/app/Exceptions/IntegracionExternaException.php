<?php

namespace App\Exceptions;

class IntegracionExternaException extends ReglaNegocioException
{
    public function __construct(public readonly string $estadoIntegracion, string $mensaje, int $estadoHttp = 503)
    {
        parent::__construct($mensaje, $estadoHttp);
    }
}
