<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class SyncUserRolesRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_ids' => ['present', 'array'],
            'role_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where('tenant_id', current_tenant_id()),
            ],
        ];
    }
}
