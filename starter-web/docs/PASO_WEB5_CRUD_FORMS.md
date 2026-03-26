# PASO WEB 5 — Formularios CRUD consistentes

## Patron aplicado

Se extrajo un componente reutilizable para formulario de usuario:

- `src/components/forms/UserCrudForm.jsx`

Objetivo:

- evitar duplicacion entre crear y editar
- estandarizar estado de submit (`idle | submitting | success | error`)
- centralizar validacion basica de cliente
- integrar errores de API de forma consistente

## Comportamiento del formulario

- Campos:
  - `usuario` (requerido, minimo 2)
  - `codigo_cliente` (opcional)
  - `password` (solo modo create, requerido minimo 8)
- Estados UX:
  - boton deshabilitado en submitting
  - mensaje de exito
  - errores por campo (cliente/API)
  - error general consistente

## Integracion en Users

Archivo actualizado: `src/pages/UsersPage.jsx`

- Alta: usa `UserCrudForm` en `mode="create"`
- Edicion: usa `UserCrudForm` en `mode="edit"`
- La pagina conserva:
  - toasts
  - recarga de listado
  - dialog de desactivacion

## Tests agregados

- `src/components/forms/UserCrudForm.test.jsx`
  - render del formulario
  - validacion basica
  - submit exitoso
  - error de validacion API
  - estado submitting y prevencion de doble submit

## Como ejecutar

```bash
cd starter-web
npm run test:run
npm run build
```

## Limites actuales

- No se uso libreria de formularios (decision intencional en esta fase).
- No hay validacion avanzada por schema compartido.
- El enforcement final de reglas sigue en `starter-core`.

