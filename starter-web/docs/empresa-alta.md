# Alta de empresa (Plataforma)

Documento de cierre operativo del flujo de **creación de empresa** desde la UI de plataforma en `starter-web`. La API y reglas de validación del servidor viven en `starter-core` (ver referencias al final).

## Propósito

Describir el flujo en pantalla, el orden de los campos y las reglas de datos que el front aplica para alinear **nombre de empresa** y **código** con los datos fiscales, sin duplicar la especificación completa del backend.

## Archivos relevantes

| Área | Ruta |
|------|------|
| Formulario y estado | `starter-web/src/pages/PlatformAdminPage.jsx` |
| Tests del flujo | `starter-web/src/pages/PlatformAdminPage.test.jsx` |
| E2E (opcional) | `starter-web/e2e/platform-flow.spec.js` |
| API cliente | `starter-web/src/api/client.js` (creación vía `createPlatformTenant`) |

## Reglas de datos (resumen)

| Campo en UI / payload | Comportamiento |
|----------------------|----------------|
| **Código de empresa** | Igual al **RFC** (misma cadena normalizada: recorte y mayúsculas). Visible, **no editable**. |
| **Nombre (empresa)** | **Derivado** de datos fiscales: persona física → nombre(s) + apellidos (solo partes no vacías, sin dobles espacios); persona moral → razón social (`nombre_fiscal`). Visible, **no editable**. |

La construcción del payload que se envía al API se centraliza en el mismo módulo de la página (`buildTenantCreatePayload`); no duplicar reglas en otros sitios sin revisar ese código.

## Orden visual del formulario (después de elegir régimen)

1. **Persona física:** tarjeta CURP + nombre(s) + apellidos → **Nombre (empresa)** + texto de ayuda + **Código de empresa** → domicilio registrado → Activo → Crear empresa.
2. **Persona moral:** Denominación / Razón social → **Nombre (empresa)** + ayuda + **Código de empresa** → domicilio registrado → Activo → Crear empresa.

**Motivo del orden:** el usuario completa primero los datos fiscales de identidad; debajo ve el nombre de empresa actualizarse en automático antes de pasar al domicilio.

Antes de ese bloque (siempre): origen de datos → RFC → tipo detectado → régimen fiscal principal.

## Pruebas

En el directorio `starter-web`:

```bash
npx vitest run
```

Última verificación documentada: **22** archivos de test, **110** tests en verde.

## Referencias en backend

Para detalle fiscal, validaciones y contratos HTTP del alta en core:

- `starter-core/docs/PLATFORM_ALTA_EMPRESA_FISCAL.md` (u otra doc de plataforma vigente en ese paquete).

## Historial (cierre)

- **UX — orden de campos:** `Nombre (empresa)` y `Código de empresa` se movieron **debajo** del bloque fiscal (PF o PM) y **encima** del domicilio, manteniendo `data-testid` (`platform-tenant-nombre`, `platform-tenant-codigo`) y sin cambiar la lógica de derivación ni el payload más allá de lo ya definido en código.

## Pendientes (opcional)

Usar esta sección como checklist interno (vacío hasta que definas ítems).
