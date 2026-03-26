# Refinamiento visual y UX de layout (starter-web)

## Que se cambio

Se mejoro la base visual del app shell autenticado sin tocar logica de auth, sesion, rutas o permisos:

- Header mas limpio y consistente.
- Branding visible: **FDS FABRICA DEL SOFTWARE**.
- Sidebar responsive con comportamiento movil (oculto por defecto + toggle desde header).
- Sistema de 3 temas visuales coherentes con persistencia en `localStorage`.

## Temas disponibles

Se agregaron 3 temas aplicados de forma consistente a shell, header, sidebar, botones, inputs y cards:

- `soft` (suave / descanso visual)
- `light` (claro)
- `dark` (oscuro)

Clave de persistencia:

- `starter-web_theme`

Mecanismo:

- El tema se aplica en `document.documentElement` con `data-dash-theme`.
- Al recargar, se restaura el tema guardado.

## Selector de tema

- Ubicacion: header del dashboard.
- Control: selector simple (`Tema`) con opciones Suave / Claro / Oscuro.
- Mantiene la preferencia entre recargas.

## Sidebar responsive

- En desktop: sidebar visible como navegacion principal.
- En pantallas pequenas: sidebar oculto por defecto.
- Apertura/cierre:
  - boton de menu en header
  - backdrop para cerrar
  - cierre automatico al navegar

## Validacion

### Tests agregados/actualizados

- `src/components/layout/DashboardLayout.authz.test.jsx`
  - cambio de tema y persistencia
  - toggle de sidebar movil
  - cobertura previa de shell/permisos se mantiene

### Validacion manual recomendada

1. Iniciar sesion y abrir dashboard.
2. Cambiar entre los 3 temas y recargar pagina para confirmar persistencia.
3. Reducir viewport (<960px):
   - verificar que sidebar este oculto
   - abrir/cerrar menu desde header
   - navegar y confirmar cierre automatico del sidebar
4. Validar que logout, rutas protegidas y permisos UI sigan funcionando.

## Decisiones visuales principales

- Enfoque sobrio/profesional, sin animaciones excesivas.
- Reutilizacion del mini design system existente.
- Sin librerias pesadas nuevas.
- Sin refactor masivo: mejoras focalizadas en layout, tokens y comportamiento responsive.

