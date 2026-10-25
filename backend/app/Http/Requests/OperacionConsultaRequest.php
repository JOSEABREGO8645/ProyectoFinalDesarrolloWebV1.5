<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperacionConsultaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sucursal_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'producto_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'estado' => ['nullable', 'string', 'in:registrado,emitida,enviado,confirmado,anulado'],
            'q' => ['nullable', 'string', 'max:100'],
            'nit' => ['nullable', 'string', 'max:20'],
            'operacion_id' => ['nullable', 'uuid'],
        ];
    }
}
