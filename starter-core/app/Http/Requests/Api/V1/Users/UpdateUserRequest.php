<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->route('id');

        return [
            'usuario' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'usuario')->ignore($userId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'codigo_cliente' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fecha_alta' => ['sometimes', 'nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
