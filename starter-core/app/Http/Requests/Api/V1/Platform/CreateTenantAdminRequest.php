<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTenantAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_usuario' => ['required', 'string', 'max:255', Rule::unique('users', 'usuario')],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'admin_codigo_cliente' => ['nullable', 'string', 'max:255'],
        ];
    }
}

