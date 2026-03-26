# PASO WEB 13 — Componentes base y mini design system pragmatico

## Objetivo aplicado

Se consolidaron componentes base reutilizables para reducir repeticion de JSX/clases y mantener consistencia visual, sin rediseno grande ni librerias pesadas.

## Componentes base consolidados

- `src/components/ui/Button.jsx`
  - variantes: `primary`, `secondary`, `ghost`, `danger`, `danger-solid`, `outline`
  - soporta `size="sm"`, `block`, `loading`, `disabled`

- `src/components/ui/Field.jsx`
  - `Field` para label + error consistente
  - `TextInput` y `SelectInput` como wrappers ligeros con clase base `dash-input`

- `src/components/ui/PageHeader.jsx`
  - cabecera simple de pagina (`title`, `lead`) con estructura uniforme

## Aplicacion pragmatica

- `LoginForm`:
  - botones de demo + submit migrados a `Button`
  - inputs migrados a `Field` + `TextInput`

- `UserCrudForm`:
  - campos create/edit migrados a `Field` + `TextInput`
  - submit migrado a `Button`

- `UsersPage`:
  - encabezados migrados a `PageHeader`
  - filtros (buscar/tamano pagina) con `Field` + `TextInput` + `SelectInput`
  - acciones (editar/desactivar, paginacion, cancelar) con `Button`

## Reglas de uso

- Reutilizar `Button` para acciones de formularios, tabla y toolbar.
- Reutilizar `Field` para evitar mezcla ad-hoc de `label/span/error`.
- Mantener `PageHeader` para intro de vistas en lugar de HTML repetido.
- Seguir usando `Card` existente como contenedor base.

## Limites actuales

- Mini system intencionalmente pequeno; no reemplaza todos los componentes del proyecto.
- No incluye tematizacion avanzada, tokens por componente ni variantes complejas.
- No se migro todo el codigo historico para evitar refactor masivo.

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

