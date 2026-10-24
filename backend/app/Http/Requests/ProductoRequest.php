<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductoRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:40'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'proveedor_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/i'],
            'precio_costo' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'precio_venta' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock_minimo' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
