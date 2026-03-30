# Alcance: starter v1

Este repositorio es una **plantilla base** (monorepo) para **clonar o derivar** productos SaaS multi-tenant. **No** está pensado como un “core compartido” que evolucione en vivo para muchos productos a la vez: cada producto futuro debería partir de un **snapshot** (fork/clon) con su propio ciclo de vida.

## Qué incluye starter v1

| Área | Contenido |
|------|-----------|
| **Autenticación** | Login (incl. flujo global y selección de tenant), refresh, logout, `me`, cambio de tenant en sesión. |
| **Multi-tenant** | Contexto de tenant por sesión, membresía usuario–tenant, aislamiento en API. |
| **Usuarios** | CRUD acotado al tenant, desactivación, métricas/auditoría básicas de admin. |
| **Roles** | Roles dinámicos por tenant, CRUD y asignación a usuarios (sync / attach). |
| **Autorización** | Gates por ability (`manage-users`, `manage-roles`, `manage-tenant-company`) + roles en config; sin permisos finos por acción. |
| **Empresa (tenant)** | Datos editables de empresa en contexto tenant; rutas plataforma separadas para operadores globales. |
| **Comercial** | Middleware de acceso según estado de suscripción/trial (según implementación actual). |
| **Plataforma** | Usuarios `is_platform_admin`: creación de tenants, alta de admin inicial, operaciones de plataforma documentadas en el código y en docs. |
| **Contrato API** | OpenAPI **parcial** (política explícita en README y `API_CONTRACT.md`); tests de contrato sobre lo documentado. |
| **Tests** | Suite Feature (y tests de front donde existan) como red de seguridad para regresiones. |
| **Demo** | Comando `app:setup-demo`, seeders y usuarios de prueba documentados. |

## Qué no incluye (fuera de alcance de la plantilla)

- Productos verticales concretos (CRM, facturación, ventas, etc.): **no** hay módulos de negocio de esos dominios como producto terminado.
- **Permisos granulares** (por acción/recurso): el modelo actual es roles + abilities agregadas; el refinamiento es responsabilidad del producto derivado.
- **OpenAPI al 100%** del API real en v1: ver política en README (`starter v1` declara cobertura parcial intencional).
- Reglas de negocio que dependan de un cliente o vertical: deben vivir en el repositorio del producto, no en esta base genérica.

## Módulos considerados “listos” para arrancar un producto

- Autenticación y sesión API.
- Usuarios y roles por tenant con autorización base.
- Empresa/tenant operativa con endurecimiento reciente (edición tenant solo rol `admin`, rutas plataforma separadas).
- Flujo “Crear admin inicial” (plataforma → admin del tenant).

## Qué debe agregar cada producto derivado

- Dominio de negocio (entidades, reglas, integraciones).
- Permisos más finos si los necesita (policies, equipos, etc.).
- Completar o sustituir documentación OpenAPI según su API pública.
- Branding, despliegue, observabilidad y compliance propios.

## Decisiones de arquitectura ya tomadas (no reabrir sin motivo)

- **Tenant** = organización/cliente dentro del producto.
- **Empresa** en el starter = unidad operativa base ligada al modelo de tenant.
- **API** versionada bajo `/api/v1`.
- **Separación tenant vs plataforma**: rutas bajo prefijo `platform` para operadores globales; rutas tenant con contexto de sesión.
- **Starter reusable por clonación**, no como librería compartida obligatoria.

## Cómo reutilizar esta base

1. Clonar el repositorio (o crear un fork) en un repo nuevo del producto.
2. Renombrar paquetes/proyecto según necesidad.
3. Leer [`CHANGELOG.md`](CHANGELOG.md) y este documento.
4. Ejecutar migraciones + demo (`starter-core`) y comprobar tests.
5. Eliminar o aislar documentación y código que no apliquen al vertical (p. ej. ejemplos de dominio fiscal si el producto no es fiscal).

## Qué no debes seguir desarrollando en esta plantilla

Reglas de negocio específicas de un solo producto: incorpóralas en el **repo del producto derivado** para no mezclar la plantilla con un dominio concreto.
