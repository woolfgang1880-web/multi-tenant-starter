<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida en cada petición que el tenant actual pueda operar según {@see \App\Models\Tenant::allowsApiAccess()}.
 * Debe ir después de `tenant.context` y `active.api.session`.
 *
 * Alineado con login/refresh/switch: mismo criterio comercial (trial vigente, active, etc.).
 */
final class EnsureCommerciallyOperable
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = current_tenant();

        if ($tenant === null) {
            return $next($request);
        }

        if (! $tenant->allowsApiAccess()) {
            return ApiResponse::make(
                AuthErrorCode::SUBSCRIPTION_EXPIRED,
                'El periodo de prueba de esta empresa ha finalizado o el acceso no está disponible.',
                null,
                403
            );
        }

        return $next($request);
    }
}
