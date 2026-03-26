# PASO WEB 16 — UX limpia (sin detalles técnicos de API en pantalla)

## Objetivo

Evitar que la interfaz muestre métodos HTTP, rutas de API, URLs de backend u otros detalles del contrato técnico, sustituyéndolos por mensajes orientados al usuario final. La documentación y el código del cliente siguen siendo la fuente técnica; la UI prioriza claridad de producto.

## Qué se ocultó o sustituyó

| Ubicación | Antes (orientación técnica) | Después (orientación usuario) |
|-----------|----------------------------|-------------------------------|
| Login (`LoginForm`) | `Autenticación segura · POST /auth/login` | `Acceso seguro al sistema. Inicia sesión para continuar.` |
| Estado del servicio (`HealthCheck`) | Título `API Status`, lead con `GET /health`, subtítulo con URL base + `/health` | Título de página `Estado del servicio`, lead descriptivo sin rutas; tarjeta `Resumen` sin URL |
| Mensajes de error health | Textos con la palabra “health” / enfoque técnico | `No se pudo consultar el estado del servicio` |
| Dashboard (`DashboardHome`) | `localhost:8000/api` en el hero | Mensaje genérico sobre gestión y estado del servicio |
| Dashboard — stat | `API status`, hints “backend” / “Health check” | `Estado del servicio`, hints orientados a organización y disponibilidad |
| Dashboard — accesos rápidos | “endpoint público de salud” | “comprueba si el sistema está disponible” |
| Sidebar | `API Status` | `Estado del servicio` |
| Users (`UsersPage`) | Lead con `/users` y Bearer; subtítulos `POST/GET/PUT /users…` | Lead y subtítulos en lenguaje de tarea (alta, listado, edición) |

## Por qué

- **UX**: el usuario final no necesita el verbo HTTP ni la ruta para completar una tarea.
- **Percepción de producto**: la app se siente más “cerrada” y menos prototipo de integración.
- **Seguridad (marginal)**: no exponer rutas o hosts en pantalla reduce superficie informativa útil para un atacante que ya tiene acceso al front; el beneficio principal sigue siendo de producto, no de confidencialidad fuerte.

## Reglas para el futuro

1. **En componentes de página** (`pages/`, layouts, formularios visibles): no mostrar `GET`/`POST`/`PUT`/`DELETE`, paths tipo `/api/...` ni URLs de servidor salvo un requisito explícito (p. ej. pantalla de diagnóstico solo para admins).
2. **Errores visibles al usuario**: preferir `mapApiError` y mensajes genéricos; reservar detalles técnicos para consola en desarrollo si hace falta.
3. **Contrato OpenAPI y URLs**: documentación en `docs/`, tests en `*.test.js` y `client.js` siguen alineados al backend; la UI no los duplica.
4. **Navegación interna**: las rutas hash (`#/users`, `#/health`) son implementación del router; no hace falta mostrarlas en copy de marketing.

## Archivos tocados (implementación)

- `src/components/LoginForm.jsx`
- `src/components/HealthCheck.jsx`
- `src/pages/DashboardHome.jsx`
- `src/pages/UsersPage.jsx`
- `src/components/layout/AppSidebar.jsx`
- Tests: `LoginForm.test.jsx`, `HealthCheck.test.jsx`, `DashboardLayout.authz.test.jsx`

## Cómo validar manualmente

1. Abrir login: comprobar que el hint bajo “Acceso al panel” no incluye `POST` ni `/auth/login`.
2. Iniciar sesión → ir a **Estado del servicio** en el menú: no debe verse `GET`, `/health` ni URL completa del API en la cabecera de la página ni en la tarjeta principal.
3. **Dashboard**: el hero no debe mostrar `localhost` ni `/api`.
4. **Users**: cabecera de página y subtítulos de tarjetas sin verbos HTTP ni rutas REST.

## Comando de tests

```bash
cd starter-web
npx vitest run
```
