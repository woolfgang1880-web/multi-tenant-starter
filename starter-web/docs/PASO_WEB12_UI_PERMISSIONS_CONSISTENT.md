# PASO WEB 12 — Permisos UI consistentes en acciones y vistas

## Regla base

La autorizacion en frontend es **solo UX**: muestra/oculta elementos para una experiencia coherente.
El enforcement real de seguridad sigue en `starter-core`.

## Capa de autorizacion UI

Archivo: `src/utils/authz.js`

Funciones unificadas:

- `canViewUsers(user)`
- `canCreateUser(user)`
- `canEditUser(user)`
- `canDeactivateUser(user)`
- `canManageUsers(user)` (alias para compatibilidad)

Se calcula por:

- roles (`admin`, `super_admin`)
- permisos/abilities (`manage-users`)

## Aplicacion consistente

- `AppSidebar`:
  - link `Users` visible solo con `canViewUsers`.

- `App`:
  - guard de ruta `/users` usa `canViewUsers`.
  - `UsersPage` recibe `user` autenticado para aplicar reglas internas.

- `UsersPage`:
  - sin `canViewUsers` -> muestra estado de acceso restringido UI.
  - create form visible solo con `canCreateUser`.
  - boton `Editar` visible solo con `canEditUser`.
  - boton/modal `Desactivar` visible solo con `canDeactivateUser`.

## Tests agregados

- `src/pages/UsersPage.permissions.test.jsx`
  - usuario sin permiso no ve acciones/botones restringidos
  - usuario con `manage-users` si ve CRUD

Adicionalmente se mantienen tests previos de guardas en `App.auth-guards.test.jsx`.

## Limites actuales

- Permisos UI dependen de datos disponibles en el payload del usuario (`roles`, `abilities`, `permissions`).
- Si backend cambia naming de abilities, se debe ajustar `authz.js`.
- No se implementa framework complejo de policies frontend (intencional).

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

