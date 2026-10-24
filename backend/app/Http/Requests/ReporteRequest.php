<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/'];

        return [
            'sucursal_id' => $id, 'producto_id' => $id, 'proveedor_id' => $id, 'cliente_id' => $id,
            'desde' => ['nullable', 'required_with:hasta', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'required_with:desde', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'q' => ['nullable', 'string', 'max:100'], 'categoria' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', 'string', 'in:enviado,confirmado,anulado'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
            'formato' => ['nullable', 'string', 'in:json,csv'],
        ];
    }
}
