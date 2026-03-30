# Plataforma — Captura fiscal simplificada PF/PM

## Objetivo

Capturar solo los datos fiscales necesarios para alta de empresa en plataforma, separados por tipo de contribuyente y con régimen fiscal único.

## Campos finales por tipo

### Persona física

- `rfc`
- `curp`
- `pf_nombre`
- `pf_primer_apellido` (nullable)
- `pf_segundo_apellido` (nullable)
- Domicilio:
  - `estado`
  - `municipio` (obligatorio en PF)
  - `colonia` (nullable)
  - `tipo_vialidad` (nullable)
  - `calle` (nullable)
  - `numero_exterior` (nullable)
  - `numero_interior` (nullable)
  - `localidad` (nullable)
  - `codigo_postal`
  - `correo_electronico` (nullable)
- `regimen_fiscal_principal` (único)

### Persona moral

- `rfc`
- `nombre_fiscal` (Denominación/Razón Social)
- Domicilio:
  - `estado`
  - `municipio` (nullable)
  - `colonia` (nullable)
  - `tipo_vialidad` (nullable)
  - `calle` (nullable)
  - `numero_exterior` (nullable)
  - `numero_interior` (nullable)
  - `localidad` (nullable)
  - `codigo_postal`
  - `correo_electronico` (nullable)
- `regimen_fiscal_principal` (único)

## Régimen fiscal

- La UI solo permite seleccionar un régimen (`select`).
- Si SAT/PDF trae varios regímenes, se usan como opciones de selección.
- Se persiste únicamente `regimen_fiscal_principal`.
- No hay captura libre por comas.

## Flujo de captura UI

1. Elegir `tipo_contribuyente` (PF/PM)
2. Elegir `origen_datos`:
   - `sat_url`
   - `pdf`
   - `imagen_qr`
   - `manual`
3. Autollenar lo disponible
4. Corregir manualmente faltantes
5. Guardar

## Prioridad de fuente de datos

1. **QR / URL SAT + HTML público SAT** (fuente principal)
2. **Texto extraído del PDF** (fallback)
3. **Captura manual** (último recurso)

### Imagen con QR

- Se lee QR de imagen.
- Se obtiene URL SAT pública.
- Se intenta consultar HTML SAT y mapear datos.
- Si SAT no devuelve datos útiles, fallback a datos mínimos de URL.
- Si no hay datos útiles, pasa a manual.

### PDF

- Se intenta detectar QR dentro del PDF (render de páginas + lectura QR).
- Si hay URL SAT, se consulta HTML SAT y se mapea (prioridad alta).
- Si no hay QR o SAT no responde útil, fallback a parseo de texto PDF.
- Si tampoco hay datos útiles, pasa a manual.

Nota UX en domicilio: mostrar ayuda **“Si no aplica, captura NA”** (sin autollenado automático de `NA`).

## Reglas backend clave

- `tipo_contribuyente`: requerido
- `rfc`: requerido + unique condicional por `ALLOW_DUPLICATE_RFC`
- `regimen_fiscal_principal`: requerido
- `codigo_postal`: requerido
- `estado`: requerido
- PF:
  - `curp`: requerido
  - `pf_nombre`: requerido
  - `municipio`: requerido
- PM:
  - `nombre_fiscal`: requerido

Mensajes de validación devuelven sección y campo (ej. “Domicilio fiscal: Código postal es obligatorio.”).

## Persistencia y compatibilidad

- Se agrega columna `correo_electronico` en `tenants`.
- Se deja de persistir el conjunto de campos no incluidos en la definición final.
- Se mantiene compatibilidad razonable con columnas históricas existentes, sin refactor masivo.

## Pruebas sugeridas

### Backend

- `php artisan test tests/Feature/PlatformAdminTenancyTest.php`
- `php artisan test tests/Feature/PlatformTenantsListInitialAdminTest.php`
- `php artisan test tests/Feature/PlatformTenantRfcDuplicateBehaviorTest.php`

### Frontend

- `npm run test -- src/pages/PlatformAdminPage.test.jsx`
- `npm run test -- src/utils/satConstanciaPdf.test.js src/utils/satValidador.test.js`

