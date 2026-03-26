# Índice de documentación (monorepo)

Punto de entrada para onboarding. El detalle técnico vive en cada paquete.

## Arranque y demo

| Documento | Ubicación |
|-----------|-----------|
| README principal (stack, comandos, CORS, v1.0.0) | [../README.md](../README.md) |
| Comando `app:setup-demo` | [../starter-core/docs/SETUP_DEMO.md](../starter-core/docs/SETUP_DEMO.md) |
| Usuarios y credenciales demo | [../starter-core/docs/DEMO_USERS.md](../starter-core/docs/DEMO_USERS.md) |
| OpenAPI (contrato API) | [../starter-core/docs/openapi/openapi.yaml](../starter-core/docs/openapi/openapi.yaml) |

## Backend (`starter-core`)

| Tema | Documento |
|------|-----------|
| Multi-tenant / evolución | `starter-core/docs/MULTI_TENANT_EVOLUTION.md` |
| Usuario global y membresía N:N | `starter-core/docs/GLOBAL_USER_MEMBERSHIP.md` |
| Login estable tras Fase 1 | `starter-core/docs/LOGIN_STABILITY_PHASE1.md` |
| Tenancy HTTP | `starter-core/docs/TENANCY.md` |
| Usuarios y roles | `starter-core/docs/USERS_ROLES.md` |
| Seguridad / sesiones | `starter-core/docs/SECURITY.md`, `SESSION_POLICY.md` |
| Base de datos | `starter-core/docs/DATABASE.md` |

## Frontend (`starter-web`)

| Tema | Documento |
|------|-----------|
| Auth multi-tenant | `starter-web/docs/PASO_WEB1_AUTH_MULTI_TENANT.md` |
| Capa de datos y errores | `starter-web/docs/PASO_WEB4_DATA_LAYER_ERRORS.md` |
| Sesión en el cliente | `starter-web/docs/SESSION_STORAGE.md` |
| Demo tenant PRUEBAS | `starter-web/docs/DEMO_TENANT_PRUEBAS.md` |

El resto de `PASO_WEB*.md` son notas de iteración UX/arquitectura por fases.
