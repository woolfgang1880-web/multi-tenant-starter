# PASO WEB 8 — Componentes base de feedback y consistencia visual

## Componentes base creados

Archivo: `src/components/ui/feedback.jsx`

- `LoadingState`
- `ErrorState`
- `EmptyState`
- `InlineAlert`

Son wrappers simples sobre estilos existentes (`dash-*`) para unificar estructura y textos sin rediseño.

## Donde se aplican

- `LoginPage`
  - mensaje de sesion/aviso usa `InlineAlert`.

- `UsersPage`
  - error operativo inline usa `InlineAlert`.
  - estado de error de listado usa `ErrorState`.
  - estado vacio de tabla usa `EmptyState`.

- `HealthCheck`
  - loading usa `LoadingState`.
  - error usa `ErrorState`.

## Reglas basicas de consistencia

1. Usar componentes de `feedback.jsx` para nuevos estados de feedback.
2. Mantener mensajes accionables y cortos.
3. Distinguir `error` vs `empty` vs `loading` en vez de reusar alertas para todo.
4. Evitar HTML ad-hoc para estados repetitivos en vistas protegidas.

## Tests agregados

- `src/components/ui/feedback.test.jsx`
  - render de loading/error/empty
  - dismiss opcional de `InlineAlert`

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

## Limites actuales

- No es un sistema de diseño completo.
- No incorpora variantes complejas de accesibilidad/notificaciones globales.
- La semantica de negocio y seguridad sigue controlada por backend.

