<?php

namespace App\Services\Observability;

use App\Support\Metrics\OperationalMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ReadinessService
{
    public function __construct(
        private readonly OperationalMetrics $metrics,
    ) {}

    /**
     * @return array{status:string,checks:array<string,mixed>,http_status:int,timestamp:string}
     */
    public function snapshot(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'auth_schema' => $this->checkAuthSchema(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $criticalFail = ($checks['database']['ok'] ?? false) === false
            || ($checks['auth_schema']['ok'] ?? false) === false
            || ($checks['cache']['ok'] ?? false) === false;

        if ($criticalFail) {
            $this->metrics->increment('readiness.degraded');
        }

        return [
            'status' => $criticalFail ? 'degraded' : 'ok',
            'checks' => $checks,
            'http_status' => $criticalFail ? 503 : 200,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'driver' => DB::getDefaultConnection(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'driver' => DB::getDefaultConnection(),
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * Esquema mínimo para login con sesiones multi-tenant (Fase 1).
     *
     * @return array<string, mixed>
     */
    private function checkAuthSchema(): array
    {
        try {
            if (! Schema::hasTable('user_sessions')) {
                return [
                    'ok' => false,
                    'detail' => 'Falta la tabla user_sessions. Ejecuta: php artisan migrate',
                ];
            }

            if (! Schema::hasColumn('user_sessions', 'tenant_id')) {
                return [
                    'ok' => false,
                    'detail' => 'Falta user_sessions.tenant_id (login fallará). Ejecuta: php artisan migrate',
                ];
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCache(): array
    {
        $store = config('cache.default');
        $key = 'ready:'.uniqid('', true);
        $value = 'ok';

        try {
            Cache::store($store)->put($key, $value, 10);
            $read = Cache::store($store)->get($key);
            Cache::store($store)->forget($key);

            return [
                'ok' => $read === $value,
                'store' => $store,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'store' => $store,
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');

        try {
            Queue::connection($connection);

            return [
                'ok' => true,
                'connection' => $connection,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'connection' => $connection,
                'error' => class_basename($e),
            ];
        }
    }
}

