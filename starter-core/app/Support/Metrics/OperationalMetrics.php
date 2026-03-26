<?php

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Cache;

/**
 * Métricas operativas mínimas basadas en contadores en cache.
 *
 * Diseño deliberadamente simple para starter:
 * - sin vendors externos
 * - claves diarias por métrica/tags
 * - fácil migración futura a backend dedicado
 */
final class OperationalMetrics
{
    /**
     * Incrementa un contador con bucket diario.
     *
     * @param  array<string, scalar|null>  $tags
     */
    public function increment(string $metric, array $tags = [], int $by = 1): void
    {
        if (! (bool) config('metrics.enabled', true)) {
            return;
        }

        $key = $this->keyFor($metric, $tags);
        $ttlSeconds = (int) config('metrics.counter_ttl_seconds', 7 * 24 * 60 * 60);
        $store = config('metrics.store');
        $cache = $store !== null && $store !== '' ? Cache::store($store) : Cache::store();

        $cache->add($key, 0, $ttlSeconds);
        $cache->increment($key, $by);
    }

    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function keyFor(string $metric, array $tags = []): string
    {
        ksort($tags);
        $normalized = [];
        foreach ($tags as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $normalized[] = $k.'='.$v;
        }

        $prefix = (string) config('metrics.prefix', 'metrics');
        $bucketDate = now()->format('Y-m-d');
        $suffix = $normalized === [] ? '' : '|'.implode(',', $normalized);

        return "{$prefix}:{$bucketDate}:{$metric}{$suffix}";
    }
}

