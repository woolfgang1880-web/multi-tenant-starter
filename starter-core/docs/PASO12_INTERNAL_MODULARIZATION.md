# PASO 12 — Modularización interna por contexto (propuesta pragmática)

## Objetivo

Definir una modularización interna **ligera** para `starter-core` que mejore mantenibilidad y crecimiento, sin refactor masivo ni cambios de comportamiento.

---

## 1) Mapa actual (estado real)

Estructura principal hoy:

- `app/Http/*`
  - Controllers API (`Auth`, `Users`, `Roles`)
  - Requests API (`Auth`, `Users`, `Roles`)
  - Middlewares (`ResolveTenantContext`, `EnsureActiveApiSession`, `SecureApiHeaders`)
- `app/Services/*`
  - `Auth` (`AuthSessionService`, `UserAccessRevoker`)
  - `Users` (`UserService`, `UserRoleAssignmentService`)
  - `Roles` (`RoleService`)
- `app/Models/*`
  - `User`, `Role`, `Tenant`, `UserSession`, `RefreshToken`, trait `BelongsToTenant`
- `app/Support/*`
  - `Api`, `Auth`, `Authorization`, `Logging`, `Tenancy`, helpers
- `app/Contracts/*`
  - `Tenancy/TenantResolver`
- `app/Providers/AppServiceProvider.php`

Conclusión: ya existe **semi-modularización por tema**, pero repartida entre `Http`, `Services`, `Support` y `Models`.

---

## 2) Contextos conceptuales recomendados

Para evolución, los contextos más claros son:

1. **Auth**
   - Login/refresh/logout/me, sesiones, refresh tokens, middleware de sesión activa.
2. **Users**
   - CRUD de usuario, lifecycle de usuario (activar/desactivar).
3. **Roles / Authorization**
   - CRUD de roles, asignación/sync de roles, abilities/gates.
4. **Tenancy**
   - resolución de tenant, contexto de request y helpers.
5. **Audit / Logging**
   - seguridad + auditoría administrativa.
6. **Shared / Support**
   - envelope API, códigos comunes, utilidades transversales.

---

## 3) Target ligero (sin “framework interno”)

### Opción recomendada (incremental)

Mantener Laravel idiomático y agrupar por contextos **solo donde aporta**, sin mover todo:

- `app/Domain/Auth/*` (servicios y value objects de auth)
- `app/Domain/Users/*`
- `app/Domain/Roles/*`
- `app/Domain/Tenancy/*`
- `app/Domain/Audit/*`
- `app/Http/*` se mantiene (controllers/requests/middleware)
- `app/Support/*` se reduce a utilidades realmente compartidas

> No implica DDD completo, solo separación semántica gradual.

### Qué ya está bien agrupado

- `Services/Auth|Users|Roles` (buena base).
- Requests por dominio (`Auth`, `Users`, `Roles`).
- Logging separado (`SecurityLogger`, `AdminAuditLogger`).
- Tenancy encapsulado (`TenantManager`, `TenantContext`, resolver).

### Dependencias cruzadas actuales (aceptables)

- `UserRoleAssignmentService` depende de `UserService` (cohesión razonable).
- `AuthSessionService` usa modelos de sesión/token y logger de seguridad (esperable).
- `AppServiceProvider` concentra rate limits + gates (normal en starter, pero punto de crecimiento).

---

## 4) Movimientos de bajo riesgo (si se decide migrar después)

1. **Mover clases de servicio** a namespace/contexto `Domain/*` manteniendo class names.
2. **Extraer mappers/presenters** pequeños para respuestas complejas (`Me`), sin tocar contrato HTTP.
3. **Agrupar soporte por contexto**:
   - `Support/Logging` → `Domain/Audit/Logging` (o similar) con aliases temporales.
4. **Reducir `Support` global** a lo transversal real (`ApiResponse`, códigos comunes).

---

## 5) Qué NO conviene hacer todavía

- Renombrar/mover toda la base de golpe (alto riesgo de churn en imports).
- Introducir CQRS/DDD completo sin necesidad de producto.
- Multiplicar capas vacías (“Application/Domain/Infrastructure”) solo por estilo.
- Reescribir rutas/controllers para encajar en una arquitectura teórica.

---

## 6) Secuencia recomendada (migración futura)

### Fase A — Preparación (sin mover archivos)

- Mantener reglas de arquitectura (PASO 10).
- Añadir convención de ownership por contexto en docs.

### Fase B — Primer corte pequeño

- Mover **solo Auth services** a `Domain/Auth` + actualizar imports.
- Ejecutar suite completa; validar cero cambios de comportamiento.

### Fase C — Users/Roles

- Mover `UserService`, `RoleService`, `UserRoleAssignmentService`.
- Mantener controllers y routes intactos.

### Fase D — Audit/Tenancy

- Reubicar loggers y componentes de tenancy si sigue aportando claridad.

### Fase E — Limpieza

- Eliminar namespaces antiguos y actualizar documentación final.

---

## 7) Riesgos de sobrearquitectura

- **Riesgo 1: complejidad accidental.** Más capas ≠ mejor diseño en un starter.
- **Riesgo 2: costo de onboarding.** Estructuras “enterprise” tempranas frenan al equipo.
- **Riesgo 3: refactor por refactor.** Cambios de carpeta sin valor funcional generan deuda de mantenimiento.

Mitigación: mover solo cuando haya dolor real (acoplamiento, duplicación, ownership confuso).

---

## 8) Quick wins propuestos (sin tocar código hoy)

1. Definir en docs una tabla `contexto -> owners -> carpetas actuales`.
2. Estandarizar nombres de servicios por caso de uso (`*Service` vs `*AssignmentService`) cuando aparezcan nuevos.
3. Añadir en PR template una checklist de dependencia por capas (controller -> service -> model/support).

---

## Entregable de PASO 12

- Se creó este documento de propuesta: `docs/PASO12_INTERNAL_MODULARIZATION.md`.
- **No** se movieron carpetas ni namespaces.
- **No** se tocaron rutas, tests, API ni starter-web.

