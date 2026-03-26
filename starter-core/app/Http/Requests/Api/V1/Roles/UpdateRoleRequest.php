<?php

namespace App\Http\Requests\Api\V1\Roles;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = current_tenant_id();
        $roleId = (int) $this->route('id');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($roleId),
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
