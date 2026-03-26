# Contrato API (fuente de verdad)

Este documento enlaza la **especificación OpenAPI** del starter y fija expectativas para consumidores (p. ej. el futuro **starter-web**).

## Archivo oficial

| Recurso | Ubicación |
|--------|-----------|
| Especificación OpenAPI 3.0 | [`openapi/openapi.yaml`](openapi/openapi.yaml) |

Cualquier cambio de rutas, cuerpos de petición, códigos de error o forma del envelope debe **actualizarse primero** en ese archivo (y luego en el código), o al revés en el mismo PR, pero **sin divergencia** entre implementación y contrato.

## Visualizar (Swagger UI)

Con la aplicación en marcha (`php artisan serve` u otro host):

1. Abrir en el navegador: **`/docs/api`** (vista Swagger UI embebida, sin paquetes extra).
2. La especificación se sirve en **`/docs/openapi.yaml`** (`Content-Type: application/yaml`).

Alternativa sin levantar Laravel: copiar `docs/openapi/openapi.yaml` en [editor.swagger.io](https://editor.swagger.io/) o cargar el archivo en Swagger UI estático.

## Envelope JSON

Todas las respuestas documentadas usan:

```json
{
  "code": "string",
  "message": "string",
  "data": null
}
```

- En éxito habitual, `code` es `OK` (salvo convenciones futuras documentadas).
- En error, `code` es un identificador estable (`AuthErrorCode`, `ApiErrorCode`, etc.).
- Validación: `code` = `VALIDATION_ERROR`, `data.errors` = objeto campo → array de mensajes.

## Autenticación y middleware (resumen)

| Área | Middleware / notas |
|------|-------------------|
| `POST .../auth/login`, `POST .../auth/refresh` | Públicos (rate limit propio). |
| `POST .../auth/logout` | `auth:sanctum`, `tenant.context` (sin `active.api.session`). |
| `GET .../auth/me` | `auth:sanctum`, `tenant.context`, `active.api.session`. |
| Usuarios y asignación de roles | + `can:manage-users`. |
| Roles CRUD | + `can:manage-roles`. |

Bearer: cabecera `Authorization: Bearer <access_token>` (token devuelto en login/refresh).

## Multi-tenant y autorización

- El tenant activo se obtiene del **usuario autenticado**; no hace falta enviar `tenant_id` en el cuerpo para delimitar datos.
- En **login** sí se envía `tenant_codigo` para ubicar la cuenta.
- La autorización actual usa **roles dinámicos** y **Gates** (`manage-users`, `manage-roles`); la evolución a permisos finos está descrita en [`AUTHORIZATION.md`](AUTHORIZATION.md).

## Uso por el futuro starter-web

- El cliente debe generar tipos, clientes HTTP o mocks a partir de **`docs/openapi/openapi.yaml`** (o de su publicación estable en CI).
- **No** inventar endpoints, query params ni formas de `data` no documentadas.
- Cualquier necesidad nueva de API implica **cambio de contrato + backend** en el mismo flujo de trabajo.

## Referencias

- Autorización: [`AUTHORIZATION.md`](AUTHORIZATION.md)
- Tenancy: [`TENANCY.md`](TENANCY.md) (si existe en el repo)
