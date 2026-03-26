# PASO WEB 14 — Tests de integracion de vistas clave

## Flujos cubiertos

Suite: `src/App.integration.test.jsx`

Se validan flujos integrados entre `App`, router hash, estado de sesion/auth y `UsersPage`:

- login -> sesion -> redireccion a dashboard
- acceso a `Users` con usuario con permiso UI
- acceso a `Users` con usuario sin permiso UI (redirect a dashboard)
- flujo CRUD integrado en `UsersPage`:
  - crear usuario
  - editar usuario
  - desactivar usuario
  - feedback principal de exito
- sesion invalida -> redireccion a login con notice

## Enfoque de testing

- Nivel integracion de vista/componente grande (no e2e).
- Se mockea `api/client` como frontera externa.
- Se mantienen componentes reales de UI y rutas hash para validar interaccion realista.
- No se introducen herramientas de navegador real (Playwright/Cypress).

## Que queda fuera

- e2e reales contra backend real y navegador real
- validacion de estilos/animaciones al detalle
- condiciones de red reales (latencia, reconexion, errores de infraestructura)
- validacion de seguridad backend (enforcement real)

## Como ejecutar

```bash
cd starter-web
npx vitest run src/App.integration.test.jsx --reporter=verbose
npm run test:run
npm run build
```

