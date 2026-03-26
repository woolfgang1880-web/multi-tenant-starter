# PASO 14 — Health / Readiness / dependency checks

## Objetivo

Mejorar observabilidad operativa con endpoints simples de liveness y readiness, sin introducir complejidad de monitoreo externo.

## Decisiones tomadas

## 1) Health (`/api/v1/health`)

- Se mantiene como check mínimo de proceso/API.
- Respuesta simple:

```json
{ "status": "ok" }
```

No usa envelope `code/message/data` (decisión ya documentada en OpenAPI).

## 2) Readiness (`/api/v1/ready`)

Nuevo endpoint para validación operativa de dependencias:

- `database`: intenta resolver conexión PDO.
- `cache`: write/read/delete de clave efímera en store configurado.
- `queue`: resolución de la conexión de cola configurada.

### Semántica de estado

- `200` + `status=ok`: checks críticos (`database`, `cache`) sanos.
- `503` + `status=degraded`: falla DB o cache.
- `queue` se informa en checks y afecta `status` si no puede resolverse.

### Formato de respuesta

```json
{
  "status": "ok|degraded",
  "checks": {
    "database": { "ok": true, "driver": "sqlite" },
    "cache": { "ok": true, "store": "database" },
    "queue": { "ok": true, "connection": "database" }
  },
  "timestamp": "2026-01-01T00:00:00+00:00"
}
```

## Qué valida y qué no

Valida de forma ligera:

- conectividad básica a DB
- operación mínima de cache
- resolución de conexión de queue

No valida:

- latencia/SLI/SLO
- profundidad de cola o salud de workers
- monitoreo distribuido externo

## Tests agregados

Archivo: `tests/Feature/HealthReadinessTest.php`

- `test_health_is_simple_and_available`
- `test_readiness_reports_expected_structure_in_normal_conditions`

También se actualizó `OpenApiDocsRouteTest` para verificar presencia de `/api/v1/ready` en YAML servido.

## Cómo usar en operación

- **Liveness probe**: `/api/v1/health`
- **Readiness probe**: `/api/v1/ready`

## Cómo probar

```bash
cd starter-core
php artisan test --filter=HealthReadinessTest
php artisan test
```

