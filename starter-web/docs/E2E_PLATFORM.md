# E2E (browser real) — Flujo Plataforma

Este repo incluye un test E2E con **Playwright** que valida el flujo completo **sin mocks**:

1. Login como **super admin** (`admin_demo`)
2. Abrir **Plataforma**
3. Crear **empresa** (código único)
4. Crear **admin inicial**
5. **Cerrar sesión**
6. Login con el **admin recién creado**
7. Validar que ve **Users**
8. Validar que **no** ve **Plataforma**

Archivo: `starter-web/e2e/platform-flow.spec.js`

## Requisitos

- **Backend** `starter-core` corriendo y accesible en `http://127.0.0.1:8000` (por defecto).
- **Playwright Chromium** instalado localmente (una sola vez por máquina):

```bash
cd starter-web
npm run e2e:install
```

- **Datos demo** cargados (incluye `admin_demo` como super admin global):

```bash
cd starter-core
php artisan app:setup-demo
```

- **Red estable** para la primera instalación del browser (descarga ~170MB). Si ves timeouts, reintenta o aumenta el timeout de descarga (variable `PLAYWRIGHT_DOWNLOAD_CONNECTION_TIMEOUT`).

## Cómo ejecutarlo

En una terminal:

```bash
cd starter-core
php artisan serve --host=127.0.0.1 --port=8000
```

En otra:

```bash
cd starter-web
npm run e2e
```

### CORS (muy importante en Windows)

Vite suele abrir el front como `http://127.0.0.1:5173` (no siempre `localhost`).

Si el login muestra **Failed to fetch**, casi siempre es **CORS**: el API debe permitir el **Origin exacto** del navegador.

- En `starter-core` ya se amplió el default y se documentó en `.env.example`.
- Si personalizas orígenes, incluye **ambos**:
  - `http://127.0.0.1:5173`
  - `http://localhost:5173`

### Variables útiles

- **`VITE_API_BASE_URL`**: URL base del API consumido por Vite (debe terminar en `/api/v1`).
  - Default del config: `http://127.0.0.1:8000/api/v1`
- **`E2E_BASE_URL`**: URL del frontend para abrir en el browser.
  - Default: `http://127.0.0.1:5173`

Ejemplo (PowerShell):

```powershell
$env:VITE_API_BASE_URL="http://127.0.0.1:8000/api/v1"
$env:E2E_BASE_URL="http://127.0.0.1:5173"
npm run e2e
```

## Supuestos / límites

- El test usa **códigos únicos** (`E2E{timestamp}{rand}`) para minimizar choques entre corridas.
- Si el API no está arriba, el `webServer` de Vite puede levantar el front, pero el test fallará al intentar login/creación (es esperado).
- En redes restrictivas, evita depender de descargar browsers de Playwright: por eso usamos **Edge del sistema**.
