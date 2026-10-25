<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:120'],
            'usuario' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => [$this->isMethod('POST') ? 'required' : 'nullable', 'string', 'min:10', 'max:72', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('La contraseña es demasiado larga.');
                }
            }],
            'codigo' => ['nullable', 'digits:4'],
            'rol' => ['required', 'in:empleado,superusuario'],
            'sucursal_id' => ['required', 'string', 'regex:/^[a-f0-9]{24}$/i'],
            'sucursal_ids' => ['sometimes', 'array', 'min:1', 'max:500'],
            'sucursal_ids.*' => ['required', 'string', 'distinct', 'regex:/^[a-f0-9]{24}$/i'],
            'modulos' => ['sometimes', 'array', 'max:8'],
            'modulos.*' => ['string', 'distinct', Rule::in(config('permisos.modulos'))],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
