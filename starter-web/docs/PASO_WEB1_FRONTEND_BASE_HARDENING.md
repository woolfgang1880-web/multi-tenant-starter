# PASO WEB 1 — Revision y hardening de base frontend

## Objetivo

Preparar `starter-web` para integrarse de forma solida con `starter-core` en autenticacion/sesion, cliente API y proteccion de vistas, sin sobrearquitectura.

## Cambios aplicados

## 1) Arquitectura base ligera

Se mantuvo estructura existente y se incorporo una capa minima para rutas:

- `src/routes/router.js`
  - rutas hash (`/login`, `/dashboard`, `/users`, `/health`)
  - utilidades de navegacion/suscripcion
  - nocion de rutas protegidas

## 2) Cliente API centralizado

Archivo actualizado: `src/api/client.js`

- Base URL por defecto alineada a backend: `http://localhost:8000/api/v1`
- Soporte de `VITE_API_BASE_URL`
- Headers consistentes (`Accept`, `Authorization`)
- Parseo JSON robusto
- Manejo uniforme de errores con `status`, `code`, `body`
- Normalizacion de envelope API (`return body.data ?? body`)

## 3) Auth flow y sesion

- Login alineado al contrato real:
  - request: `tenant_codigo`, `usuario`, `password`
  - endpoint: `POST /auth/login`
- Persistencia local de:
  - `access_token`
  - `refresh_token`
  - user snippet
- Refresh automatico al recibir `401` en request protegido:
  - `POST /auth/refresh` (una sola carrera compartida)
  - si refresh funciona, reintenta request original
  - si refresh falla, limpia sesion local
- Logout:
  - intenta `POST /auth/logout` y limpia sesion local siempre

## 4) Routing y guardas

Archivo actualizado: `src/App.jsx`

- Bootstrap de sesion con `GET /auth/me` si existe token local.
- Guard en cliente:
  - si no autenticado y ruta protegida -> redirige a `/login`
  - si autenticado y esta en `/login` -> redirige a `/dashboard`
- Navegacion de sidebar sincronizada con hash route.

## 5) UI de login alineada a backend

Archivo actualizado: `src/components/LoginForm.jsx`

- Campos:
  - `tenant_codigo`
  - `usuario`
  - `password`

## 6) Compatibilidad de Users con contrato backend

Archivo actualizado: `src/pages/UsersPage.jsx`

- Ajustes de payloads para `starter-core`:
  - crear usuario: `usuario`, `password`, `password_confirmation`, `codigo_cliente`
  - editar usuario: `usuario`, `codigo_cliente`
  - desactivar usuario: `PATCH /users/{id}/deactivate`
- Normalizacion de respuesta de listado:
  - soporta `items + meta`
- Mensajeria de error adaptada a envelope actual.

## 7) Documentacion de sesion

Archivo actualizado: `docs/SESSION_STORAGE.md`

- claves actuales de storage
- flujo de refresh automatico
- comportamiento al fallar refresh

## Como probar

```bash
cd starter-web
npm run lint
npm run build
npm run dev
```

Pruebas manuales recomendadas:

1. Abrir app sin sesion: debe quedarse en login.
2. Login valido: redirige a dashboard.
3. Navegar a users/health autenticado: acceso permitido.
4. Forzar token invalido: debe intentar refresh; si falla, volver a login.
5. Logout: debe limpiar sesion y volver a login.

## Riesgos y pendientes

- Aun no hay tests automatizados frontend (no habia setup razonable en este paso).
- Sesion sigue en `localStorage` (riesgo XSS conocido y documentado).
- Routing hash es intencionalmente simple; puede migrarse a router dedicado cuando el proyecto crezca.

