<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $datos = $this->all();
        foreach (['sucursal_id', 'destino_id', 'cliente_id', 'proveedor_id', 'operacion_id'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = strtolower(trim($datos[$campo]));
            }
        }
        if (isset($datos['items']) && is_array($datos['items'])) {
            foreach ($datos['items'] as &$item) {
                if (is_array($item) && isset($item['producto_id']) && is_string($item['producto_id'])) {
                    $item['producto_id'] = strtolower(trim($item['producto_id']));
                }
            }
            unset($item);
        }
        $this->merge($datos);
    }

    public function rules(): array
    {
        $id = ['required', 'string', 'regex:/^[a-f0-9]{24}$/'];
        $reglas = [
            'operacion_id' => ['required', 'uuid'],
            'sucursal_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:producto_id,cantidad'],
            'items.*.producto_id' => [...$id, 'distinct'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'motivo' => ['required', 'string', 'min:3', 'max:300'],
        ];

        return array_merge($reglas, match ($this->route('tipo')) {
            'ingresos' => ['proveedor_id' => $id],
            'ventas' => [
                'motivo' => ['nullable', 'string', 'max:300'],
                'cliente_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
                'codigo' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
                'total_esperado_centavos' => ['sometimes', 'integer', 'min:0', 'max:9999999999'],
                'efectivo' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            ],
            'traslados' => ['destino_id' => $id],
        });
    }
}
