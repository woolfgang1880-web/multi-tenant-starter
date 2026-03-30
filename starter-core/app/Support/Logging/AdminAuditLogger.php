<?php

namespace App\Support\Logging;

use App\Support\Metrics\OperationalMetrics;
use Illuminate\Support\Facades\Log;

/**
 * Auditoría de administración (sin datos sensibles).
 */
final class AdminAuditLogger
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
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function context(
        string $type,
        string $severity,
        int $actorUserId,
        int $tenantId,
        string $targetType,
        int|string $targetId,
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
            'session_id' => null,
            'ip' => null,
            'user_agent' => null,
            'metadata' => $metadata !== [] ? $metadata : null,
        ];
    }

    public static function userCreated(int $actorUserId, int $targetUserId, int $tenantId): void
    {
        self::metrics()->increment('admin.user.created');

        Log::channel(self::CHANNEL)->info('admin.user.created', [
            ...self::context('admin.user.created', 'info', $actorUserId, $tenantId, 'user', $targetUserId),
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function userUpdated(int $actorUserId, int $targetUserId, int $tenantId): void
    {
        Log::channel(self::CHANNEL)->info('admin.user.updated', [
            ...self::context('admin.user.updated', 'info', $actorUserId, $tenantId, 'user', $targetUserId),
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function userDeactivated(int $actorUserId, int $targetUserId, int $tenantId): void
    {
        self::metrics()->increment('admin.user.deactivated');

        Log::channel(self::CHANNEL)->notice('admin.user.deactivated', [
            ...self::context('admin.user.deactivated', 'notice', $actorUserId, $tenantId, 'user', $targetUserId),
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function userRolesSynced(int $actorUserId, int $targetUserId, int $tenantId, int $roleCount): void
    {
        Log::channel(self::CHANNEL)->info('admin.user.roles.synced', [
            ...self::context(
                'admin.user.roles.synced',
                'info',
                $actorUserId,
                $tenantId,
                'user',
                $targetUserId,
                ['role_count' => $roleCount]
            ),
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'tenant_id' => $tenantId,
            'role_count' => $roleCount,
        ]);
    }

    public static function userRolesAttached(int $actorUserId, int $targetUserId, int $tenantId, int $addedCount): void
    {
        Log::channel(self::CHANNEL)->info('admin.user.roles.attached', [
            ...self::context(
                'admin.user.roles.attached',
                'info',
                $actorUserId,
                $tenantId,
                'user',
                $targetUserId,
                ['added_count' => $addedCount]
            ),
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'tenant_id' => $tenantId,
            'added_count' => $addedCount,
        ]);
    }

    public static function roleCreated(int $actorUserId, int $roleId, int $tenantId): void
    {
        Log::channel(self::CHANNEL)->info('admin.role.created', [
            ...self::context('admin.role.created', 'info', $actorUserId, $tenantId, 'role', $roleId),
            'actor_user_id' => $actorUserId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function roleUpdated(int $actorUserId, int $roleId, int $tenantId): void
    {
        Log::channel(self::CHANNEL)->info('admin.role.updated', [
            ...self::context('admin.role.updated', 'info', $actorUserId, $tenantId, 'role', $roleId),
            'actor_user_id' => $actorUserId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function tenantCompanyUpdated(int $actorUserId, int $targetTenantId): void
    {
        Log::channel(self::CHANNEL)->info('admin.tenant_company.updated', [
            ...self::context('admin.tenant_company.updated', 'info', $actorUserId, $targetTenantId, 'tenant', $targetTenantId),
            'actor_user_id' => $actorUserId,
            'target_tenant_id' => $targetTenantId,
        ]);
    }

    public static function tenantCompanyInactivated(int $actorUserId, int $targetTenantId): void
    {
        Log::channel(self::CHANNEL)->notice('admin.tenant_company.inactivated', [
            ...self::context('admin.tenant_company.inactivated', 'notice', $actorUserId, $targetTenantId, 'tenant', $targetTenantId),
            'actor_user_id' => $actorUserId,
            'target_tenant_id' => $targetTenantId,
        ]);
    }

    public static function tenantCompanyReactivated(int $actorUserId, int $targetTenantId): void
    {
        Log::channel(self::CHANNEL)->info('admin.tenant_company.reactivated', [
            ...self::context('admin.tenant_company.reactivated', 'info', $actorUserId, $targetTenantId, 'tenant', $targetTenantId),
            'actor_user_id' => $actorUserId,
            'target_tenant_id' => $targetTenantId,
        ]);
    }
}
