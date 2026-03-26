# PASO 6 — Revisión de arquitectura limpia y límites de capas

**Alcance:** solo `starter-core`. Sin refactor masivo; este documento describe el estado actual, fortalezas, debilidades y prioridades. Referencias cruzadas: `docs/TENANCY.md`, `docs/PASO3_TENANT_AUTHORIZATION.md`, `docs/PASO4_RATE_LIMITING.md`, `docs/PASO5_OPENAPI_CONTRACT_TESTS.md`.

---

## 1. Controllers

### Patrón dominante (orquestación)

Los controladores de **usuarios**, **roles**, **desactivación** y **asignación de roles** siguen el flujo esperado:

`FormRequest (validación)` → `tenantId() / actorId()` → **Service** → `ApiResponse::make` con datos ya formateados por el servicio (`formatUser`, `formatRole`, `formatPaginator`).

Archivos representativos: `UserController`, `RoleController`, `UserRolesController`, `DeactivateUserController`.

### Auth (caso especial)

| Controller | Rol | Observación |
|------------|-----|-------------|
| `LoginController` | Valida con `$request->validate()` **inline** (no FormRequest) | Orquesta `AuthSessionService::login` y **mapea** `reason` → status + `AuthErrorCode` + mensaje. La lógica de negocio y seguridad (credenciales, tokens, reuse) está en **servicio**; el controller concentra **contrato HTTP** de errores de login. |
| `RefreshController` | Igual: validate inline | Mapeo `reason` → `REFRESH_EXPIRED` / `SESSION_INVALID` / `REFRESH_INVALID`. |
| `LogoutController` | Comprueba `User` + `PersonalAccessToken` | Salvaguarda de tipo de token antes de `AuthSessionService::logout`. No hay negocio pesado. |
| `MeController` | Construye el array `user` / `tenant` | **Presentación** acoplada al controller; podría ser un `UserProfilePresenter` o método en servicio si crece. |

### Desviaciones

- **`HealthController`**: responde `{"status":"ok"}` **sin** envelope `code/message/data`. Es aceptable para un ping operativo; si se unifica con el resto de la API, habría que acordar contrato y OpenAPI.

### ¿Demasiada lógica en controller?

- **No** en CRUD usuarios/roles (delgados).
- **Moderada** en Login/Refresh: mapeo de resultados del servicio a respuestas HTTP (coherente con mantener el servicio agnóstico de `JsonResponse`).
- **Leve** en Me: ensamblado de payload de lectura.

---

## 2. Services / “Actions”

### Centralización actual

| Dominio | Componentes | Tenant |
|---------|-------------|--------|
| Auth sesión | `AuthSessionService`, `UserAccessRevoker` | Login por `tenant_codigo`; refresh por fila token → sesión → usuario |
| Usuarios | `UserService` | Todas las queries con `where('tenant_id', $tenantId)` + `findForTenantOrFail` |
| Roles | `RoleService` | `Role::forTenant($tenantId)` |
| Roles ↔ usuario | `UserRoleAssignmentService` | Usuario vía `UserService::findForTenantOrFail`; `assertRolesBelongToTenant` |

No hay capa **Actions/UseCases** separada: los servicios actúan como casos de uso por agregado. Para el tamaño actual del starter es **razonable**.

### Duplicación / reglas repartidas

- **Roles y tenant**: `AttachUserRolesRequest` / `SyncUserRolesRequest` usan `Rule::exists(..., 'tenant_id', current_tenant_id())`. `UserRoleAssignmentService::assertRolesBelongToTenant` **revalida** el mismo invariante antes de `sync`/`attach`. Es **defensa en profundidad** (útil si en el futuro hay otra entrada al servicio); documentar como intencional para no “simplificar” borrando una de las dos sin análisis.

### ¿Introducir Actions?

- **No prioritario** mientras los servicios sigan siendo clases pequeñas y testables.
- Valorar **un** Action explícito solo si aparece un flujo transaccional multi-servicio que ensucie un único `*Service`.

---

## 3. Requests / validación

### `ApiFormRequest`

- `authorize()`: exige usuario autenticado **y** `current_tenant_id() !== null`.
- **No** sustituye a Gates: la capacidad `manage-users` / `manage-roles` se aplica por **`can:` en rutas** (`routes/api.php`).

### Consistencia

- Rutas autenticadas administrativas: **FormRequest** + reglas con `current_tenant_id()` donde aplica (unicidad `usuario`, `exists` de `roles`).
- **Excepción**: `LoginController` y `RefreshController` usan validación inline porque son rutas **públicas** (sin FormRequest base con tenant).

### Recomendación de bajo riesgo (ver Quick wins)

- Añadir `LoginRequest` y `RefreshRequest` como `FormRequest` con `authorize(): true` y las mismas reglas, solo por **uniformidad** y testabilidad del ruleset.

---

## 4. Respuestas y errores

### Envelope estándar

- Éxito y error de negocio explícito: `ApiResponse::make($code, $message, $data, $status)` → `code`, `message`, `data`.

### Centralizado en `bootstrap/app.php` (API)

- `AuthenticationException` → 401 (`UNAUTHENTICATED` vs `TOKEN_INVALID_OR_REVOKED` según Bearer).
- `ThrottleRequestsException` → 429 `TOO_MANY_ATTEMPTS` (+ logs en login/refresh).
- `AccessDeniedHttpException` (p. ej. tras Gate) → 403 `FORBIDDEN` + `SecurityLogger::authorizationDeniedApi`.
- `ValidationException` → 422 `VALIDATION_ERROR` + `data.errors`.
- `ModelNotFoundException` / `NotFoundHttpException` → 404 `NOT_FOUND`.

### Convenciones implícitas

- Mensajes en **español** fijos en controllers/handlers; no i18n por `Accept-Language`.
- Subcódigos de auth en login/refresh se deciden en **controller**, no en el exception handler.
- **Health** sin envelope (ver arriba).

---

## 5. Dependencias internas (flujo actual)

```
routes/api.php
  → middleware: auth:sanctum, tenant.context, active.api.session, can:Ability, throttle:*
       ↓
Controllers (Api\V1\*)
  → Request::validate() ó FormRequest
  → Controller::tenantId() / actorId()  [usa current_tenant_id()]
       ↓
Services (AuthSessionService, UserService, RoleService, UserRoleAssignmentService)
  → Models (User, Role, Tenant, UserSession, RefreshToken, …)
  → Support: SecurityLogger, AdminAuditLogger, ApiResponse (desde controllers)
       ↓
Middleware: EnsureActiveApiSession, ResolveTenantContext
  → Models UserSession, SecurityLogger
```

### Riesgos de crecimiento

| Riesgo | Detalle |
|--------|---------|
| Acceso directo a modelos multi-tenant | `User` **no** incluye `BelongsToTenant` ni global scope; cualquier `User::find($id)` fuera de `UserService` rompe el aislamiento (ya señalado en PASO 3). |
| Lógica repetida | Mapeo auth en Login/Refresh si se añaden más razones; duplicación Request + `assertRolesBelongToTenant`. |
| Dependencias cruzadas | `UserRoleAssignmentService` depende de `UserService` (aceptable). `AuthSessionService` no debe importar controllers; hoy está limpio. |
| Exception handler “grande” | Más rutas o formatos (XML, etc.) presionarían a fragmentar `bootstrap/app.php`. |

---

## 6. Fortalezas actuales

- Separación clara **CRUD admin** → servicios con **tenant_id explícito**.
- **Auth** sensible concentrada en `AuthSessionService` + `SecurityLogger`.
- **Autorización** por Ability en rutas + Gates en `AppServiceProvider`.
- **Errores API** mayormente unificados en el handler de excepciones.
- **Tests** por capas (feature, contract, rate limit, tenant isolation, seguridad).

---

## 7. Debilidades actuales

- Login/Refresh sin FormRequest (inconsistencia de estilo).
- `MeController` mezcla ensamblado de respuesta sin capa de presentación dedicada.
- `HealthController` fuera del contrato envelope/OpenAPI “estricto”.
- Validación tenant de roles en **dos** niveles (intencional pero fácil de malinterpretar al refactorizar).

---

## 8. Quick wins (bajo riesgo, priorizados)

**Aplicados en PASO 7** (`docs/PASO7_ARCHITECTURE_QUICK_WINS.md`):

1. ~~**FormRequests públicos** `LoginRequest` y `RefreshRequest`~~ — implementado.
2. ~~**Comentario breve** en `UserRoleAssignmentService::assertRolesBelongToTenant`~~ — implementado.
3. ~~**Documentar en OpenAPI** la excepción de health~~ — implementado (`GET /api/v1/health` en `openapi.yaml` + texto en `info.description`).

---

## 9. Refactors que **no** conviene hacer todavía

- Reescribir auth como cadena de Actions por operación (login, refresh, …) sin necesidad de producto.
- Global scope automático en `User` sin diseño de Octane/queues/jobs que también respeten tenant.
- Extraer un “Application Service” paralelo a los `*Service` actuales sin duplicar nombres.
- Mover **toda** la decisión de códigos HTTP de Login/Refresh al servicio (acoplaría dominio a HTTP).

---

## 10. Mejoras priorizadas (siguientes pasos, cuando escale el equipo)

| Prioridad | Mejora |
|-----------|--------|
| P1 | Política escrita para **nuevos endpoints**: siempre `tenant_id` en servicio; prohibido `Model::find` por ID de dominio sin scope. |
| P2 | Si el mapeo auth HTTP crece: clase dedicada `AuthHttpResult` o mapper que reciba `array $result` y devuelva `JsonResponse` (tests unitarios del mapper). |
| P3 | Presentador o `UserProfileResource` para `MeController` alineado a OpenAPI. |
| P4 | Partir renders de excepciones API en clases pequeñas registradas desde `bootstrap/app.php` si el archivo crece. |

---

## 11. Qué no se tocó en PASO 6

- Código de aplicación (controllers, services, requests): **ningún cambio**.
- Tests: **ninguno** añadido (revisión y documentación únicamente).
- `starter-web`: **no** modificado.

---

## Entregable resumido

| Entrega | Contenido |
|---------|-----------|
| Archivo nuevo | `docs/PASO6_ARCHITECTURE_REVIEW.md` |
| Hallazgos | Controllers delgados salvo mapeo auth y Me; servicios centrados; doble validación roles; health sin envelope |
| Quick wins | LoginRequest/RefreshRequest; comentario en service; doc health/OpenAPI |
| Riesgos | `User` sin scope global; exception handler monolítico; drift si se accede a modelos sin servicio |
| No tocado | Implementación y tests |
