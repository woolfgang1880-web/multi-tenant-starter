# PASO WEB 4 — Capa de datos y errores consistente

## Patron elegido

Se adopto una base liviana y reusable sin librerias pesadas:

- `src/hooks/useAsyncData.js`
  - estados: `idle | loading | success | error`
  - helper `run(request, { silent })`
  - manejo comun de errores y actualizacion de estado

- `src/utils/apiError.js`
  - mapeo consistente de errores API:
    - `401` -> sesion invalida/expirada
    - `403` -> sin permisos
    - `VALIDATION_ERROR` -> primer campo con detalle
    - fallback generico

## Aplicacion en vistas

- `src/pages/UsersPage.jsx`
  - usa `useAsyncData` para listado
  - mantiene estados visibles de loading/error/empty/success
  - conserva refresh silencioso para operaciones CRUD

- `src/components/HealthCheck.jsx`
  - usa `useAsyncData` para health
  - estados claros de loading/error/success

## Tests agregados

- `src/components/HealthCheck.test.jsx`
  - loading -> success
  - error state

- `src/pages/UsersPage.test.jsx`
  - loading state
  - empty state
  - error state (forbidden)
  - success con datos

## Como ejecutar

```bash
cd starter-web
npm run test:run
npm run build
```

## Limites actuales

- No hay cache inteligente/retry/backoff global.
- No se incorporo libreria de fetching (intencional en esta fase).
- El enforcement de seguridad sigue siendo backend; el frontend solo refleja estados UX.

## Nota de estabilidad de tests (PASO WEB 6)

Se detecto un cuelgue en `UsersPage.test.jsx` al ejecutar aislado:

- **Causa:** el mock de `useToast()` devolvia una nueva funcion `showToast` en cada render.
- **Efecto:** `UsersPage` reconstruia `loadUsers` por dependencia de `showToast`, disparando `useEffect` en bucle y dejando el test abierto.
- **Correccion:** usar un mock estable (`showToastMock`) compartido entre renders y limpiar su estado en `beforeEach`.

Validacion posterior:

- `npx vitest run src/pages/UsersPage.test.jsx --reporter=verbose` ✅
- `npm run test:run` ✅

