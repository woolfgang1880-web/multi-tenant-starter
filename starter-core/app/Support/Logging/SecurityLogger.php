<?php

namespace App\Support\Logging;

use App\Support\Metrics\OperationalMetrics;
use Illuminate\Support\Facades\Log;

/**
 * Logs de seguridad (nunca contraseñas ni tokens completos).
 */
final class SecurityLogger
{
    private static function metrics(): OperationalMetrics
    {
        return app(OperationalMetrics::class);
    }

    private const CHANNEL = 'security';

    private static function requestId(): ?string
    {
        if (app()->bound('request_id')) {
            return (string) app('request_id');
        }

        return request()?->attributes->get('request_id');
    }

    private static function traceId(): ?string
    {
        if (app()->bound('trace_id')) {
            return (string) app('trace_id');
        }

        return request()?->attributes->get('trace_id');
    }

    /**
     * Construye un contexto homogéneo para eventos de seguridad.
     *
     * No incluir secretos (passwords, tokens raw, etc.) en metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function context(
        string $type,
        string $severity,
        ?int $actorUserId = null,
        ?int $tenantId = null,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?string $sessionId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        array $metadata = [],
    ): array {
        return [
            'type' => $type,
            'severity' => $severity,
            'request_id' => self::requestId(),
            'trace_id' => self::traceId(),
            'actor_user_id' => $actorUserId,
            'tenant_id' => $tenantId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'session_id' => $sessionId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'metadata' => $metadata !== [] ? $metadata : null,
        ];
    }

    public static function loginSuccess(int $userId, int $tenantId, string $sessionUuid, ?string $ip): void
    {
        self::metrics()->increment('auth.login.success');

        Log::channel(self::CHANNEL)->info('auth.login.success', [
            ...self::context(
                type: 'auth.login.success',
                severity: 'info',
                actorUserId: $userId,
                tenantId: $tenantId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid,
                ip: $ip
            ),
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'session_uuid' => $sessionUuid,
        ]);
    }

    public static function loginFailed(
        string $reason,
        ?string $usuario,
        ?string $tenantCodigo,
        ?string $ip,
        ?string $userAgent = null
    ): void
    {
        self::metrics()->increment('auth.login.failed', ['reason' => $reason]);

        Log::channel(self::CHANNEL)->warning('auth.login.failed', [
            ...self::context(
                type: 'auth.login.failed',
                severity: 'warning',
                ip: $ip,
                userAgent: $userAgent,
                metadata: [
                    'reason' => $reason,
                    'usuario' => $usuario,
                    'tenant_codigo' => $tenantCodigo,
                ]
            ),
            'reason' => $reason,
            'usuario' => $usuario,
            'tenant_codigo' => $tenantCodigo,
        ]);
    }

    public static function loginThrottled(?string $ip, ?string $key): void
    {
        self::metrics()->increment('auth.rate_limited', ['endpoint' => 'login']);

        Log::channel(self::CHANNEL)->notice('auth.login.throttled', [
            ...self::context(
                type: 'auth.login.throttled',
                severity: 'notice',
                ip: $ip,
                metadata: [
                    'limiter_key_suffix' => $key !== null ? hash('sha256', $key) : null,
                ]
            ),
            'limiter_key_suffix' => $key !== null ? hash('sha256', $key) : null,
        ]);
    }

    public static function refreshSuccess(int $userId, string $sessionUuid): void
    {
        self::metrics()->increment('auth.refresh.success');

        Log::channel(self::CHANNEL)->info('auth.refresh.success', [
            ...self::context(
                type: 'auth.refresh.success',
                severity: 'info',
                actorUserId: $userId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid
            ),
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
        ]);
    }

    public static function refreshFailed(string $reason, ?string $ip, ?string $userAgent = null): void
    {
        self::metrics()->increment('auth.refresh.failed', ['reason' => $reason]);
        if ($reason === 'throttled') {
            self::metrics()->increment('auth.rate_limited', ['endpoint' => 'refresh']);
        }

        Log::channel(self::CHANNEL)->warning('auth.refresh.failed', [
            ...self::context(
                type: 'auth.refresh.failed',
                severity: 'warning',
                ip: $ip,
                userAgent: $userAgent,
                metadata: ['reason' => $reason]
            ),
            'reason' => $reason,
        ]);
    }

    public static function refreshReuseDetected(
        int $userId,
        string $sessionUuid,
        ?string $ip,
        ?string $userAgent = null
    ): void
    {
        self::metrics()->increment('auth.refresh.reuse_detected');

        Log::channel(self::CHANNEL)->warning('auth.refresh.reuse_detected', [
            ...self::context(
                type: 'auth.refresh.reuse_detected',
                severity: 'high',
                actorUserId: $userId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid,
                ip: $ip,
                userAgent: $userAgent
            ),
            'severity' => 'high',
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
        ]);
    }

    public static function logout(int $userId, string $sessionUuid): void
    {
        Log::channel(self::CHANNEL)->info('auth.logout', [
            ...self::context(
                type: 'auth.logout',
                severity: 'info',
                actorUserId: $userId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid
            ),
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
        ]);
    }

    public static function sessionSuperseded(int $userId, string $sessionUuid): void
    {
        Log::channel(self::CHANNEL)->notice('auth.session.superseded', [
            ...self::context(
                type: 'auth.session.superseded',
                severity: 'notice',
                actorUserId: $userId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid
            ),
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
        ]);
    }

    public static function accessDenied(string $reason, ?int $userId, string $code): void
    {
        Log::channel(self::CHANNEL)->notice('auth.access.denied', [
            ...self::context(
                type: 'auth.access.denied',
                severity: 'notice',
                actorUserId: $userId,
                targetType: 'user',
                targetId: $userId,
                metadata: ['reason' => $reason, 'code' => $code]
            ),
            'reason' => $reason,
            'user_id' => $userId,
            'code' => $code,
        ]);
    }

    public static function authorizationDeniedApi(?int $userId, ?int $tenantId, string $path, string $context = ''): void
    {
        Log::channel(self::CHANNEL)->notice('auth.authorization.denied', [
            ...self::context(
                type: 'auth.authorization.denied',
                severity: 'notice',
                actorUserId: $userId,
                tenantId: $tenantId,
                targetType: 'api_path',
                targetId: $path,
                metadata: ['context' => $context !== '' ? mb_substr($context, 0, 200) : null]
            ),
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'path' => $path,
            'context' => $context !== '' ? mb_substr($context, 0, 200) : null,
        ]);
    }

    public static function tenantSwitched(int $userId, ?int $fromTenantId, int $toTenantId, string $sessionUuid, ?string $ip): void
    {
        self::metrics()->increment('auth.tenant_switch.success');

        Log::channel(self::CHANNEL)->info('auth.tenant.switch', [
            ...self::context(
                type: 'auth.tenant.switch',
                severity: 'info',
                actorUserId: $userId,
                tenantId: $toTenantId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid,
                ip: $ip,
                metadata: [
                    'from_tenant_id' => $fromTenantId,
                    'to_tenant_id' => $toTenantId,
                ]
            ),
            'user_id' => $userId,
            'from_tenant_id' => $fromTenantId,
            'to_tenant_id' => $toTenantId,
            'session_uuid' => $sessionUuid,
        ]);
    }

    public static function tenantSwitchDenied(
        int $userId,
        ?int $targetTenantId,
        string $sessionUuid,
        string $reason,
        ?string $ip
    ): void {
        self::metrics()->increment('auth.tenant_switch.denied', ['reason' => $reason]);

        Log::channel(self::CHANNEL)->notice('auth.tenant.switch.denied', [
            ...self::context(
                type: 'auth.tenant.switch.denied',
                severity: 'notice',
                actorUserId: $userId,
                tenantId: $targetTenantId,
                targetType: 'user',
                targetId: $userId,
                sessionId: $sessionUuid,
                ip: $ip,
                metadata: ['reason' => $reason]
            ),
            'user_id' => $userId,
            'target_tenant_id' => $targetTenantId,
            'session_uuid' => $sessionUuid,
            'reason' => $reason,
        ]);
    }
}
