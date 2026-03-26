<?php

namespace App\Http\Requests\Api\V1\Roles;

use App\Http\Requests\Api\V1\ApiFormRequest;

final class ListRolesRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
