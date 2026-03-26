# PASO 13 — Correlación y observabilidad avanzada (request_id / trace_id)

## Objetivo

Permitir correlación simple de requests y eventos de seguridad/auditoría sin introducir tracing distribuido complejo.

## Cómo funciona

Se añadió middleware global:

- `app/Http/Middleware/AssignRequestCorrelationId.php`

Comportamiento por request:

1. Toma `X-Request-Id` y `X-Trace-Id` si vienen en la petición.
2. Si faltan, genera `request_id` (UUID) y usa ese mismo valor como `trace_id` por defecto.
3. Guarda ambos en:
   - atributos de request
   - contenedor (`app()->instance(...)`)
   - contexto global de logs (`Log::shareContext`)
4. Devuelve headers:
   - `X-Request-Id`
   - `X-Trace-Id`

## Dónde aparece el request_id

- En la **respuesta HTTP** (headers).
- En eventos de:
  - `SecurityLogger`
  - `AdminAuditLogger`
- Campos añadidos en ambos contextos:
  - `request_id`
  - `trace_id`

## Propagación en logs

`SecurityLogger` y `AdminAuditLogger` leen correlación desde contenedor/atributos de request y la incluyen en cada evento.

Esto permite buscar en logs por `request_id` para reconstruir el flujo (respuesta + evento de seguridad/auditoría).

## Límites actuales

- No hay tracing distribuido entre servicios externos (fuera de alcance).
- `trace_id` local por defecto = `request_id`.
- No se integra OpenTelemetry ni backend de tracing.

## Cómo usar para debugging

1. Tomar `X-Request-Id` de la respuesta del cliente afectado.
2. Buscar ese `request_id` en `storage/logs/security-YYYY-MM-DD.log`.
3. Correlacionar evento de auth/admin con endpoint y actor.

## Cómo probar

```bash
cd starter-core
php artisan test --filter=RequestCorrelationTest
php artisan test
```

