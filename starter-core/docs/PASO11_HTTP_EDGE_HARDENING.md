# PASO 11 — Hardening del borde HTTP

## Objetivo

Endurecer el borde HTTP de `starter-core` para despliegues de backend desacoplado, sin cambiar semántica de la API.

## Qué se endureció

## 1) CORS explícito

Se añadió `config/cors.php` con defaults orientados a desarrollo local y controlables por entorno:

- `CORS_ALLOWED_ORIGINS`
- `CORS_ALLOWED_METHODS`
- `CORS_ALLOWED_HEADERS`
- `CORS_EXPOSED_HEADERS`
- `CORS_MAX_AGE`
- `CORS_SUPPORTS_CREDENTIALS`

**Decisión actual:** bearer-only API (`supports_credentials=false` por defecto).

## 2) Cabeceras de seguridad API

Se añadió middleware `app/Http/Middleware/SecureApiHeaders.php` (solo `api/*`) que inyecta:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: no-referrer`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

## 3) Trusted proxies / trusted hosts (opt-in)

En `bootstrap/app.php`:

- Si `TRUSTED_PROXIES` está definido, habilita `trustProxies(...)`.
- Si `TRUSTED_HOSTS` está definido (CSV de regex), habilita `trustHosts(...)`.

Esto evita suposiciones rígidas de infraestructura y permite endurecer por entorno.

## 4) Sanctum / auth edge

Se mantiene la decisión API-first bearer-only:

- `config/sanctum.php` con `guard=[]`.
- Documentado que mezclar SPA stateful/cookies requiere revisar CORS + `SANCTUM_STATEFUL_DOMAINS` + `SESSION_*`.

## 5) `.env.example` actualizado

Se añadieron variables de borde HTTP:

- bloque CORS
- `TRUSTED_PROXIES`, `TRUSTED_HOSTS`
- `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`
- `SANCTUM_TOKEN_PREFIX`

## Diferencias local vs producción

- **Local/dev:** orígenes localhost, cookies no necesariamente secure.
- **Producción:**
  - permitir solo orígenes explícitos en CORS
  - `SESSION_SECURE_COOKIE=true` bajo HTTPS
  - definir `TRUSTED_PROXIES`/`TRUSTED_HOSTS` según reverse proxy
  - revisar `SANCTUM_TOKEN_PREFIX`

## Validaciones ligeras agregadas

Archivo: `tests/Feature/HttpEdgeHardeningTest.php`

- `test_api_security_headers_are_present`
- `test_cors_preflight_uses_explicit_configuration`

## Límites actuales / pendientes

- No se fuerza CSP porque este starter es backend API (sin HTML app principal).
- Trusted proxies/hosts quedan en modo opt-in por entorno para no romper setups locales.
- Si en el futuro se habilita auth stateful por cookies, hay que revisar de forma conjunta:
  - CORS credentials
  - `SESSION_SAME_SITE`
  - `SESSION_SECURE_COOKIE`
  - `SANCTUM_STATEFUL_DOMAINS`

## Cómo validar

```bash
cd starter-core
php artisan test --filter=HttpEdgeHardeningTest
php artisan test
```

