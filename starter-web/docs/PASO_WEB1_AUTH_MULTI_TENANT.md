# PASO WEB 1 — Auth base alineada a multi-tenant

## Objetivo

Dejar `starter-web` listo para autenticarse contra `starter-core` usando `tenant_codigo + usuario + password`, con manejo de sesion consistente.

## Flujo implementado

## 1) Login multi-tenant

- Formulario con campos:
  - `tenant_codigo`
  - `usuario`
  - `password`
- Endpoint: `POST /api/v1/auth/login`
- El cliente incluye dos accesos rapidos para demo:
  - `admin_demo / Admin123!`
  - `user_demo / User123!`

## 2) Session state

- Se guarda en `localStorage`:
  - `starter-web_access_token`
  - `starter-web_refresh_token`
  - `starter-web_user`
- Al iniciar app, si hay token, se intenta `GET /api/v1/auth/me` para restaurar sesion.

## 3) Refresh y 401

- Si una request protegida devuelve `401`:
  - se intenta `POST /api/v1/auth/refresh` una vez (cola compartida para evitar carreras)
  - si refresca, se reintenta la request original
  - si falla, se limpia sesion y se redirige a login

## 4) Logout

- `POST /api/v1/auth/logout`
- Aunque backend falle, frontend limpia sesion local para evitar estado inconsistente.

## 5) Guardas de rutas

- Rutas protegidas: `/dashboard`, `/users`, `/health`
- Sin sesion valida: redirige a `/login`
- Con sesion valida en `/login`: redirige a `/dashboard`

## Archivos clave

- `src/components/LoginForm.jsx`
- `src/api/client.js`
- `src/App.jsx`
- `src/routes/router.js`
- `docs/SESSION_STORAGE.md`

## Como probar con usuarios demo

1. Ejecutar `starter-core` con datos demo cargados.
2. En `starter-web`:

```bash
npm run dev
```

3. En login:
   - tenant: `DEFAULT`
   - usuario: `admin_demo`
   - password: `Admin123!`
4. Confirmar redireccion a `/dashboard`.
5. Abrir `/users` y `/health` para validar guard y sesion.
6. Probar logout y verificar retorno a `/login`.

## Backend: mismo usuario en varias empresas (fase 1 API)

El core ya permite un `usuario` global miembro de varias empresas (`user_tenants`); el login **sigue** pidiendo `tenant_codigo` hasta las fases siguientes del roadmap. El contexto activo (roles, `auth/me`) depende de la empresa indicada en el login. Detalle: `starter-core/docs/GLOBAL_USER_MEMBERSHIP.md`.

## Riesgos / pendientes

- Persistencia de tokens en `localStorage` (riesgo XSS conocido).
- No hay tests automatizados de auth flow frontend todavia.
- Router hash simple; suficiente para base, migrable en fases siguientes.

