<?php

namespace App\Http\Requests\Api\V1\Users;

use App\Http\Requests\Api\V1\ApiFormRequest;

final class DeactivateUserRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
