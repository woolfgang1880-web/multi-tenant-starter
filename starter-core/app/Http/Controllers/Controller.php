<?php

namespace App\Http\Controllers;

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function tenantId(): int
    {
        $id = current_tenant_id();

        if ($id === null) {
            throw new HttpResponseException(
                ApiResponse::make(ApiErrorCode::FORBIDDEN, 'Contexto de tenant no disponible.', null, 403)
            );
        }

        return $id;
    }

    protected function actorId(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }
}
