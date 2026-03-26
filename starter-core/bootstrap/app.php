<?php

use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use App\Support\Logging\SecurityLogger;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\AssignRequestCorrelationId::class);

        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies !== null && $trustedProxies !== '') {
            $middleware->trustProxies(at: $trustedProxies, headers: TrustProxies::HEADER_X_FORWARDED_FOR
                | TrustProxies::HEADER_X_FORWARDED_HOST
                | TrustProxies::HEADER_X_FORWARDED_PORT
                | TrustProxies::HEADER_X_FORWARDED_PROTO
                | TrustProxies::HEADER_X_FORWARDED_PREFIX
                | TrustProxies::HEADER_X_FORWARDED_AWS_ELB);
        }

        $trustedHostsRaw = (string) env('TRUSTED_HOSTS', '');
        if ($trustedHostsRaw !== '') {
            $hosts = array_values(array_filter(array_map('trim', explode(',', $trustedHostsRaw))));
            $middleware->trustHosts(at: $hosts, subdomains: true);
        }

        $middleware->append(\App\Http\Middleware\SecureApiHeaders::class);

        $middleware->alias([
            'tenant.context' => \App\Http\Middleware\ResolveTenantContext::class,
            'active.api.session' => \App\Http\Middleware\EnsureActiveApiSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $hasBearer = (bool) $request->bearerToken();

            return ApiResponse::make(
                $hasBearer ? AuthErrorCode::TOKEN_INVALID_OR_REVOKED : AuthErrorCode::UNAUTHENTICATED,
                $hasBearer ? 'Token inválido o revocado.' : 'No autenticado.',
                null,
                401
            );
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($request->is('api/v1/auth/login')) {
                SecurityLogger::loginThrottled($request->ip(), $request->ip().'|'.$request->input('tenant_codigo').'|'.$request->input('usuario'));
            }

            if ($request->is('api/v1/auth/refresh')) {
                SecurityLogger::refreshFailed('throttled', $request->ip());
            }

            return ApiResponse::make(
                AuthErrorCode::TOO_MANY_ATTEMPTS,
                'Demasiados intentos. Intente más tarde.',
                null,
                429
            );
        });

        // Laravel convierte AuthorizationException → AccessDeniedHttpException antes de render;
        // el tipo efectivo en callbacks es AccessDeniedHttpException.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();
            if ($previous instanceof AuthorizationException) {
                SecurityLogger::authorizationDeniedApi(
                    $request->user()?->getAuthIdentifier(),
                    current_tenant_id(),
                    $request->path(),
                    $previous->getMessage()
                );
            }

            return ApiResponse::make(
                AuthErrorCode::FORBIDDEN,
                'Acceso denegado.',
                null,
                403
            );
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::make(
                ApiErrorCode::VALIDATION_ERROR,
                'Los datos enviados no son válidos.',
                ['errors' => $e->errors()],
                422
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::make(
                ApiErrorCode::NOT_FOUND,
                'Recurso no encontrado.',
                null,
                404
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::make(
                ApiErrorCode::NOT_FOUND,
                'Recurso no encontrado.',
                null,
                404
            );
        });
    })->create();
