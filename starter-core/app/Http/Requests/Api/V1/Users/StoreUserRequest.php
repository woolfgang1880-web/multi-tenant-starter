<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usuario' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'usuario'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'codigo_cliente' => ['nullable', 'string', 'max:255'],
            'fecha_alta' => ['nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
