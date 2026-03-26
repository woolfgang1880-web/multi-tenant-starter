# PASO WEB 11 — CRUD UX mas maduro

## Patron UX aplicado en UsersPage

1. **Confirmacion destructiva**
   - Se mantiene `ConfirmDialog` como componente reutilizable.
   - `desactivar usuario` siempre pasa por confirmacion explicita.

2. **Feedback consistente**
   - Exito:
     - crear -> toast success
     - editar -> toast success
     - desactivar -> toast success
   - Error:
     - create/edit ahora tambien propagan `onError` desde `UserCrudForm` para toast error consistente.
     - desactivar mantiene toast + alert inline cuando aplica.

3. **Estados de accion / doble ejecucion**
   - `UserCrudForm` ya maneja `submitting` con boton deshabilitado.
   - `desactivar` agrega guard de concurrencia (`deactivateInFlight`) para prevenir doble confirmacion accidental.
   - botones de desactivar en filas se deshabilitan cuando hay refresh o operacion de desactivacion en curso.

## Componentes/helpers reutilizados

- `UserCrudForm` (reutilizable create/edit)
- `ConfirmDialog` (confirmacion consistente)
- `InlineAlert` + toasts (`ToastContext`) para feedback uniforme

## Tests agregados

Archivo: `src/pages/UsersPage.crud-ux.test.jsx`

- confirmacion de desactivacion visible
- submit create exitoso con feedback
- error de operacion con feedback
- prevencion de doble accion al confirmar desactivacion

## Como probar

```bash
cd starter-web
npm run test:run
npm run build
```

## Limites actuales

- Notificaciones siguen siendo simples (toast + alert inline), no hay sistema avanzado.
- UX madura pero intencionalmente ligera para starter.

