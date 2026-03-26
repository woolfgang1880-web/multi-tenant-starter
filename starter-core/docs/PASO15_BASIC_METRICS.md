# PASO 15 — Métricas y contadores operativos básicos

## Objetivo

Añadir una base mínima de métricas operativas internas, simple y mantenible, sin integrar proveedores externos.

## Diseño aplicado

Se añadió una abstracción central:

- `app/Support/Metrics/OperationalMetrics.php`

Características:

- Contadores en cache (incremental).
- Claves diarias por métrica (+ tags opcionales).
- TTL configurable.
- Sin dependencia de Prometheus/OpenTelemetry/etc.

Config:

- `config/metrics.php`
  - `metrics.enabled`
  - `metrics.prefix`
  - `metrics.counter_ttl_seconds`

## Métricas básicas registradas

- `auth.login.success`
- `auth.login.failed` (tag `reason`)
- `auth.refresh.success`
- `auth.refresh.failed` (tag `reason`)
- `auth.refresh.reuse_detected`
- `auth.rate_limited` (tag `endpoint`: `login`/`refresh`)
- `admin.user.created`
- `admin.user.deactivated`
- `readiness.degraded`

## Dónde se registran

- `SecurityLogger`:
  - login/refresh success/failed/reuse/rate-limited
- `AdminAuditLogger`:
  - user created/deactivated
- `ReadinessService`:
  - degraded cuando falla dependencia crítica

Con esto se evita dispersar strings/lógica de métricas por todo el código.

## Semántica (lectura rápida)

- Contadores son **acumulados por día**.
- Tags diferencian causas (`reason`) o endpoint (`endpoint`).
- Pensado para operar internamente y evolucionar luego a backend externo.

## Tests agregados

Archivo: `tests/Feature/OperationalMetricsTest.php`

- `test_auth_metrics_are_incremented_in_key_flows`
- `test_auth_rate_limited_metric_is_incremented`
- `test_admin_metrics_are_incremented`
- `test_readiness_degraded_metric_is_incremented_when_cache_check_fails`

## Límites actuales

- Métricas en cache local/configurada (sin cardinalidad avanzada, histogramas, percentiles).
- No hay scraping/export automático.
- No hay separación por instancia si el cache no es compartido.

## Evolución sugerida (futuro)

1. Añadir endpoint interno de métricas de solo lectura (si hace falta).
2. Exportar a backend externo (Prometheus/OTel) manteniendo `OperationalMetrics` como fachada.
3. Incorporar timers básicos (latencia por endpoint crítico) si hay necesidad operativa.

## Cómo probar

```bash
cd starter-core
php artisan test --filter=OperationalMetricsTest
php artisan test
```

