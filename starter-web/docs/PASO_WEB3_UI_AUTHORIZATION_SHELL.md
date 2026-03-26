# PASO WEB 3 — Autorizacion UI y app shell base

## Que se implemento

## 1) App shell autenticado

- Se mantiene `DashboardLayout` como shell base:
  - `DashboardHeader` (marca, usuario/tenant, logout)
  - `AppSidebar` (navegacion)
  - zona de contenido protegido

## 2) Sesion / usuario en UI

- Header muestra:
  - nombre del usuario autenticado
  - tenant actual (si viene en `auth/me` y se guarda en estado)
  - boton `Log out` consistente

## 3) Autorizacion UI (UX)

- Se agrego `src/utils/authz.js` para reglas de visibilidad.
- Regla inicial:
  - `Users` visible solo si:
    - rol `admin` o `super_admin`, o
    - permiso `manage-users` en `permissions`/`abilities`.
- Importante: esto es **UX**, no enforcement real de seguridad.

## 4) Rutas protegidas

- Se mantiene guard existente de sesion.
- Refuerzo UX:
  - si usuario autenticado intenta `/users` sin permiso UI, redirige a `/dashboard`.

## Archivos modificados

- `src/components/layout/AppSidebar.jsx`
- `src/components/layout/DashboardLayout.jsx`
- `src/components/layout/DashboardHeader.jsx`
- `src/utils/authz.js` (nuevo)
- `src/App.jsx`
- `src/components/layout/DashboardLayout.authz.test.jsx` (nuevo)
- `src/App.auth-guards.test.jsx`

## Tests agregados

- Shell visible con sesion (`Ohtli`, tenant y logout).
- Logout visible y accionable.
- `Users` oculto para usuario sin rol/permiso esperado.
- Navegacion protegida sigue funcionando (incluyendo bloqueo UX en `/users`).

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

## Limites actuales

- No hay enforcement de seguridad en frontend (correcto por diseno).
- La fuente de permisos depende de payload de usuario (`roles`/`abilities`/`permissions`) cuando este disponible.
- La autorizacion final sigue ocurriendo en `starter-core`.

