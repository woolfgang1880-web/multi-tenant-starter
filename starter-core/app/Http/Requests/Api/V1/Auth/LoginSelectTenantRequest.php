<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginSelectTenantRequest extends FormRequest
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
            'selection_token' => ['required', 'string', 'size:64'],
            'tenant_codigo' => ['required', 'string', 'max:64'],
        ];
    }
}
