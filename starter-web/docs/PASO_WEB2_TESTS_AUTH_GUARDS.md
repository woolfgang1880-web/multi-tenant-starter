# PASO WEB 2 — Tests criticos de auth y guards

## Cobertura agregada

## 1) Login (`src/components/LoginForm.test.jsx`)

- Render del formulario (`tenant_codigo`, `usuario`, `password`).
- Validacion basica de campos requeridos.
- Submit exitoso con credenciales validas (API mockeada).
- Manejo de error en login invalido.

## 2) Sesion (`src/api/client.test.js` + `src/App.auth-guards.test.jsx`)

- Bootstrap con sesion valida restaura usuario en `App`.
- Si falla `auth/me`, se limpia sesion y se redirige a login.
- Si request protegida devuelve `401` y refresh falla, cliente limpia sesion.
- Logout limpia estado local aun si backend falla.

## 3) Guards/routing (`src/App.auth-guards.test.jsx`)

- Ruta protegida redirige a `/login` sin sesion.
- Usuario autenticado en `/login` redirige a `/dashboard`.

## Configuracion de test

- Vitest + Testing Library + JSDOM:
  - `vite.config.js` (seccion `test`)
  - `src/test/setup.js`
  - scripts en `package.json`: `test`, `test:run`

## Como ejecutar

```bash
cd starter-web
npm run test:run
```

## Pendientes explicitos

- No hay e2e (Cypress/Playwright) en esta fase.
- No se cubre toda la UI; solo flujos criticos de auth/sesion/guards.
- Queda pendiente ampliar casos de expiracion avanzada y concurrencia de pestañas.

