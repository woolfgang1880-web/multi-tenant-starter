# PASO 9 — Hardening de configuración y entorno

## Objetivo

Revisar configuración sensible y dejar un checklist operativo para despliegues seguros de `starter-core`, sin cambiar semántica HTTP de la API.

## Configuración revisada

### Variables / flags críticos

- `APP_ENV`: debe reflejar entorno real (`production` en prod).
- `APP_DEBUG`: **false** fuera de local/testing.
- `APP_URL`: usar **https** en producción.
- `APP_KEY`: obligatorio y robusto (no vacío).
- `SESSION_DRIVER`: en `.env.example` está `database` (aceptable); evitar `array` en producción.
- `CACHE_STORE`: en `.env.example` está `database`; para rate limiting real multi-instancia se recomienda `redis` o store compartido.
- `QUEUE_CONNECTION`: en `.env.example` está `database`; evitar `sync`/`null` en producción.
- `LOG_CHANNEL`: revisar según entorno (`stack` + `daily`/agregación centralizada).
- Canal `security`: definido en `config/logging.php` (`daily`, retención configurable por `LOG_SECURITY_DAYS`).
- Sanctum: `config/sanctum.php` con `guard=[]` (API bearer-only en este starter).

### CORS

- No existe `config/cors.php` en el estado actual del proyecto.
- Implicación: no hay política CORS explícita de app en este starter; debe resolverse en capa de aplicación/middleware o reverse proxy, según despliegue.

### Trusted proxies / trusted hosts

- No hay configuración explícita en `bootstrap/app.php` para trusted proxies/hosts.
- En producción detrás de balanceador/reverse proxy, documentar y configurar cabeceras de proxy confiables en infraestructura y/o middleware dedicado.

## Defaults seguros y límites actuales

- Seguro en local/dev:
  - `.env.example` usa `APP_ENV=local`, `APP_DEBUG=true` (esperable para desarrollo).
- Requerido para producción:
  - `APP_DEBUG=false`.
  - `APP_URL=https://...`.
  - `APP_KEY` definido.
  - `CACHE_STORE` y `SESSION_DRIVER` compartidos si hay varias instancias.
  - `QUEUE_CONNECTION` asíncrona real (`database`/`redis`/servicio externo).
  - logs con retención y centralización acordes a operación.

## Validación ligera incorporada

Se añadió comando de diagnóstico:

```bash
php artisan security:check-config
```

Archivo: `routes/console.php`

Qué valida (reglas actuales):

- `APP_KEY` ausente/débil.
- `APP_DEBUG=true` en `production`.
- `APP_URL` sin https en `production`.
- stores inseguros para producción (`CACHE_STORE=array/file/null`, `QUEUE_CONNECTION=sync/null`).
- `SESSION_SECURE_COOKIE` / `SESSION_SAME_SITE` inconsistentes.
- advertencias de `LOG_CHANNEL`, `sanctum.guard` y `SANCTUM_TOKEN_PREFIX`.

> No reemplaza controles de infraestructura (proxy, WAF, SIEM, IAM, secretos gestionados).

## Checklist operativa de despliegue seguro

1. **Variables obligatorias**
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://...`
   - `APP_KEY` no vacío
2. **Persistencia / stores**
   - `CACHE_STORE` compartido (ideal `redis`) en multi-instancia
   - `SESSION_DRIVER` adecuado para topología (`database`/`redis`)
   - `QUEUE_CONNECTION` no `sync`
3. **Cookies / sesión**
   - `SESSION_SECURE_COOKIE=true` en HTTPS
   - `SESSION_SAME_SITE` acorde a arquitectura (SPA/API)
4. **Logging y observabilidad**
   - `LOG_CHANNEL` y retención definidos
   - canal `security` activo y revisado
5. **Auth/Sanctum**
   - `sanctum.guard=[]` si se mantiene bearer-only
   - revisar `SANCTUM_TOKEN_PREFIX`
6. **Infraestructura**
   - configurar trusted proxies/hosts en la capa de despliegue
   - definir política CORS explícita (app o proxy)
7. **Validación pre-release**
   - ejecutar `php artisan security:check-config`
   - ejecutar `php artisan test`

## Qué no debe usarse en producción

- `APP_DEBUG=true`
- `CACHE_STORE=array` (especialmente con rate limiting)
- `QUEUE_CONNECTION=sync`
- sesiones/caché no compartidas en múltiples instancias sin evaluar impacto

