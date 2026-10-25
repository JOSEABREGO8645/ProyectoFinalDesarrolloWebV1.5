<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TipoCambioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['monto_usd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2']];
    }
}
