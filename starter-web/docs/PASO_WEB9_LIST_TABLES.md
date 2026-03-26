# PASO WEB 9 — Listados y tablas consistentes

## Patron elegido

Se extrajo un componente de tabla/listado liviano:

- `src/components/ui/DataTable.jsx`
  - estados integrados: `loading`, `error`, `empty`, `rows`
  - toolbar opcional (meta + acciones)
  - API simple por props
  - render flexible de filas (`renderRow`)

Tambien se agrega:

- `RowActions` (helper) para estandarizar celda de acciones por fila.

## Que se reutiliza

- feedback base de PASO WEB 8:
  - `ErrorState`
  - `EmptyState`
- clases de tabla ya existentes (`dash-table*`) para no redisenar estilos.

## Aplicacion en UsersPage

- `UsersPage` ahora usa `DataTable` para el directorio de usuarios.
- Se unifican:
  - headers de tabla
  - estados loading/error/empty
  - estructura de acciones por fila (editar/desactivar)

## Tests agregados

- `src/components/ui/DataTable.test.jsx`
  - loading
  - empty
  - error
  - data rows + acciones visibles

- `src/pages/UsersPage.test.jsx`
  - refuerzo: valida acciones visibles (`Editar`, `Desactivar`) en vista con datos.

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

## Limites actuales

- Tabla intencionalmente simple (sin ordenamiento, paginacion cliente ni virtualizacion).
- No se introduce data-grid pesada por alcance del starter.
- Seguridad/autorizacion real sigue en backend.

