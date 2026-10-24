<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SucursalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:120',
            'direccion' => 'nullable|string|max:250',
            'telefono' => 'nullable|string|max:25',
            'activo' => 'nullable|boolean',
        ];
    }
}