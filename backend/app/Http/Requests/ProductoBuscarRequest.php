<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductoBuscarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:40'],
            'activo' => ['nullable', 'boolean'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'proveedor_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/i'],
            'sucursal_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/i'],
            'orden' => ['nullable', 'string', 'in:codigo,nombre,categoria,precio_venta,created_at'],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
