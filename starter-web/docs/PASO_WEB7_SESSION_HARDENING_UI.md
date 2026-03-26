# PASO WEB 7 — Hardening de sesion en UI

## Objetivo

Mejorar la UX cuando la sesion expira o falla autenticacion de forma terminal, manteniendo flujo simple y consistente.

## Cambios implementados

- `src/api/client.js`
  - se agrega `API_AUTH_NOTICE_KEY` (sessionStorage)
  - nuevas utilidades:
    - `setAuthNotice(message)`
    - `consumeAuthNotice()`
  - `clearSession({ reason, showMessage })`:
    - limpia access/refresh/user
    - opcionalmente guarda mensaje para mostrar en login
  - `apiFetch`:
    - ante `401` terminal (incluye refresh fallido), limpia sesion con mensaje
  - `logout` manual:
    - limpia sesion **sin** mensaje de error

- `src/App.jsx`
  - si `auth/me` falla en bootstrap con token, limpia sesion con mensaje y redirige a login.

- `src/pages/LoginPage.jsx`
  - consume mensaje de sesion (`consumeAuthNotice`) y lo muestra una sola vez.

- `src/test/setup.js`
  - limpieza adicional de `sessionStorage` por test.

## Motivos de cierre manejados en UI

- `session_expired` / `session_invalid`: mensaje de sesion expirada/no valida.
- `manual_logout`: sin mensaje de error.
- `auth_error`: reservado para fallos inesperados de autenticacion (sin revelar detalles sensibles).

## Tests cubiertos

- `src/App.auth-guards.test.jsx`
  - sesion invalida redirige a login y muestra mensaje.
- `src/api/client.test.js`
  - refresh fallido limpia estado y deja mensaje de feedback.
  - logout manual limpia estado y **no** deja mensaje de sesion.

## Limites actuales

- Mensaje UX es generico por seguridad (no detalla causa interna del backend).
- No hay sistema de notificaciones complejo; solo banner simple en login.

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

