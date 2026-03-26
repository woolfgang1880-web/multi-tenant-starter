# PASO WEB 10 — Filtros, busqueda y paginacion consistentes

## Patron elegido

Se extiende el listado de `UsersPage` con una capa ligera:

- estado local de listado:
  - `page`
  - `perPage`
  - `searchTerm`
- consumo de backend con parametros soportados:
  - `page` (paginador Laravel)
  - `per_page` (documentado en OpenAPI)
- busqueda local en la pagina actual (cliente), porque backend no expone query de busqueda en el contrato actual.

## Implementacion aplicada

- `src/api/client.js`
  - `getUsers({ page, perPage })` construye query string segura.

- `src/pages/UsersPage.jsx`
  - filtros simples (busqueda + tamano de pagina)
  - paginacion UI con `Anterior` / `Siguiente`
  - sincronizacion con estados loading/error/empty/data via `useAsyncData` + `DataTable`
  - evita comportamientos raros:
    - al cambiar `perPage`, reinicia a pagina 1
    - botones deshabilitados en limites/pending refresh

## Supuestos backend

- Soportado:
  - `per_page` (OpenAPI `GET /api/v1/users`)
  - `page` por paginador Laravel
- No soportado formalmente en contrato actual:
  - busqueda server-side (`q`, `search`, etc.)

Por eso, la busqueda se aplica en frontend sobre la pagina cargada.

## Tests agregados/actualizados

- `src/pages/UsersPage.test.jsx`
  - loading/data con acciones visibles
  - empty coherente
  - error coherente
  - busqueda local funcional
  - paginacion `Siguiente` actualiza listado y query esperada

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

Prueba manual:

1. Ir a `Users`.
2. Escribir en buscar y verificar filtrado local.
3. Cambiar tamano de pagina (10/15/25/50).
4. Usar `Anterior/Siguiente` y validar cambio de resultados.

## Limites actuales

- Busqueda solo local en pagina actual.
- No hay paginador numerico completo (solo anterior/siguiente).
- No hay persistencia de filtros en URL.

