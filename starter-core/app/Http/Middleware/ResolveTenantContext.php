<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rellena y limpia el contexto de tenant por petición (compatible con Octane).
 * Colocar después del middleware de autenticación cuando el tenant deba existir.
 */
final class ResolveTenantContext
{
    public function __construct(
        private readonly TenantManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->manager->resolveFromRequest($request);

        try {
            return $next($request);
        } finally {
            $this->manager->clear();
        }
    }
}
