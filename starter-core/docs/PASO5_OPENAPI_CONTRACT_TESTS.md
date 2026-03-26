# PASO 5 — Contract tests frente a OpenAPI

## Contrato oficial

- Archivo de referencia: **`docs/openapi/openapi.yaml`** (también servido en `/docs/openapi.yaml`).
- Las respuestas JSON siguen el envelope `code`, `message`, `data` descrito en los esquemas `ApiEnvelope*`.

## Tipo de validación

| Enfoque | Descripción |
|---------|-------------|
| **Pragmática (implementada)** | Tests PHPUnit que llaman a la API real (`tests/Feature/OpenApiContractTest.php`) y aserciones sobre status HTTP, códigos de error esperados y forma del JSON alineada a los esquemas nombrados en OpenAPI. Helpers en `tests/Support/OpenApiContractAsserts.php`. |
| **Automática completa (no implementada)** | No se usa librería tipo `league/openapi-psr7-validator` ni validación JSON Schema generada desde el YAML contra cada respuesta. Añadirlo sería mejora futura para reducir *drift*. |

## Endpoints y status cubiertos por los tests

| Área | Casos |
|------|--------|
| **POST /auth/login** | 200 (TokenPayload), 401 `INVALID_CREDENTIALS`, 403 `ACCOUNT_INACTIVE`, 422 `VALIDATION_ERROR`, 429 `TOO_MANY_ATTEMPTS` |
| **POST /auth/refresh** | 200, 401 `REFRESH_INVALID`, 422, 429 |
| **POST /auth/logout** | 200 envelope OK + `data` null, 401 `UNAUTHENTICATED` (sin Bearer; usar `withoutToken()` en tests para no arrastrar cabeceras) |
| **GET /auth/me** | 200 `ApiEnvelopeMe`, 401 `UNAUTHENTICATED` |
| **POST /users** | 201 `UserWithRoles`, 422, 429 |
| **PUT /users/{id}** | 200 `UserWithRoles` |
| **GET /users** | 200 listado `items` + `meta` |
| **GET /users/{id}** | 404 `NOT_FOUND` (cross-tenant) |
| **POST /users** (sin permiso) | 403 `FORBIDDEN` |
| **POST /roles** | 201 `RoleResource` |
| **POST /roles** (sin permiso) | 403 `FORBIDDEN` |
| **PUT /users/{id}/roles** | 200 sync |
| **POST /users/{id}/roles** | 200 attach |
| **PUT …/roles** cross-tenant | 404 `NOT_FOUND` |
| **POST …/roles** vacío | 422 |

## Cómo ejecutar

```bash
cd starter-core
php artisan test --filter=OpenApiContractTest
php artisan test
```

La spec sigue validándose como YAML servido en `OpenApiDocsRouteTest`.

## Límites actuales

- No se validan cabeceras opcionales (`Retry-After` en 429, etc.).
- No se comprueba cada variante de 401 en `/auth/me` (`TOKEN_INVALID_OR_REVOKED`, `SESSION_*`) — la spec las agrupa en `SessionOrAuth401`.
- **Multi-tenant**: 404 en usuario ajeno se cubre en contract test de usuarios; no hay un caso dedicado “solo OpenAPI” por recurso adicional.

## Riesgos de *drift* (implementación vs OpenAPI)

| Tema | Nota |
|------|------|
| **429 en rutas protegidas** | OpenAPI declara 429 en login y refresh. Se añadió **429** a `POST /api/v1/users` en el YAML para alinearlo con PASO 4; otras rutas con throttle (p. ej. roles, assign) pueden seguir sin entrada 429 en la spec. |
| **429 en logout / auth/me** | El middleware de rate limit aplica también a logout y me; el YAML actual **no** documenta 429 en esas rutas. |
| **Códigos 401** | Si el cliente envía Bearer inválido, la API puede responder `TOKEN_INVALID_OR_REVOKED` en lugar de `UNAUTHENTICATED` (ver `bootstrap/app.php`); la spec ya lo admite para rutas protegidas. |
| **Campos extra en JSON** | Los tests exigen presencia de campos críticos, no prohiben propiedades adicionales (OpenAPI `additionalProperties` no se fuerza). |

Mantener **actualizado `openapi.yaml`** cuando cambien códigos de error o esquemas; los contract tests pragmáticos son la red de seguridad inmediata.
