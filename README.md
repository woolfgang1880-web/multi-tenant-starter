# Starter monorepo — v1.0.0

Repositorio **inicial estable** con dos paquetes que trabajan juntos: API Laravel (**starter-core**) y SPA React + Vite (**starter-web**). Un solo repositorio en la raíz facilita versionar contratos, docs y releases alineados (p. ej. **v1.0.0**).

## Contenido

| Carpeta | Rol |
|---------|-----|
| `starter-core/` | API REST Laravel 11: autenticación (access/refresh), multi-tenant, usuarios, roles, OpenAPI. |
| `starter-web/` | Front React 19 + Vite: login multi-tenant, dashboard, CRUD usuarios, tests (Vitest). |

## Stack

- **Backend:** PHP 8.2+, Laravel 11, Sanctum (Bearer), sesiones en BD, rate limiting y métricas opcionales.
- **Front:** React, Vite, React Router, capa API centralizada.
- **Contrato:** OpenAPI en `starter-core/docs/openapi/openapi.yaml` (fuente de verdad para formas de respuesta y códigos de error en tests de contrato).
- **Base de datos:** el ejemplo por defecto usa **SQLite** en desarrollo; el mismo código está preparado para **MySQL/MariaDB** en equipos o despliegues que lo requieran (ver `.env.example` en core).

## Requisitos

- PHP, Composer, extensiones habituales de Laravel
- Node.js 20+ y npm
- MySQL/MariaDB solo si no usas SQLite

## Cómo levantar `starter-core`

```bash
cd starter-core
composer install
copy .env.example .env   # Windows; en macOS/Linux: cp .env.example .env
php artisan key:generate
```

Si usas **SQLite** (por defecto en `.env.example`):

```bash
# Crear fichero SQLite si no existe (Windows PowerShell ejemplo)
New-Item -ItemType File -Force database/database.sqlite
php artisan migrate --force
php artisan app:setup-demo
```

Si usas **MySQL**, edita `.env` (`DB_CONNECTION=mysql` y variables `DB_*`), crea la base de datos y ejecuta `php artisan migrate --force` y `php artisan app:setup-demo`.

Servidor de desarrollo:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## Cómo levantar `starter-web`

```bash
cd starter-web
npm install
copy .env.example .env   # o cp en Unix
npm run dev
```

Por defecto Vite sirve en `http://127.0.0.1:5173` (o el puerto que indique la consola).

### `VITE_API_BASE_URL` y `127.0.0.1`

En `.env` del front, **`VITE_API_BASE_URL`** debe apuntar a la API **sin barra final**, p. ej. `http://127.0.0.1:8000/api/v1`. Usar **`127.0.0.1`** en lugar de `localhost` reduce problemas en Windows donde `localhost` puede resolver a IPv6 mientras el servidor PHP escucha solo en IPv4.

En **starter-core**, mantén `CORS_ALLOWED_ORIGINS` alineado con el origen del front (p. ej. `http://127.0.0.1:5173`).

## Datos y credenciales demo

El comando **`php artisan app:setup-demo`** (desde `starter-core`) aplica migraciones pendientes **sin** `migrate:fresh` y ejecuta seeders idempotentes (tenants `DEFAULT`, `PRUEBA1`, `PRUEBAS`, roles, usuarios demo).

Documentación detallada de usuarios y contraseñas: [`starter-core/docs/DEMO_USERS.md`](starter-core/docs/DEMO_USERS.md).  
Flujo del comando: [`starter-core/docs/SETUP_DEMO.md`](starter-core/docs/SETUP_DEMO.md).

## OpenAPI

El contrato publicado y usado en tests de contrato vive en:

`starter-core/docs/openapi/openapi.yaml`

Rutas de documentación en la API (si están habilitadas en tu entorno) se describen en la documentación del core.

## Tests rápidos

```bash
cd starter-core && php artisan test
cd starter-web && npm test
```

## Documentación adicional

Índice maestro: [`docs/README.md`](docs/README.md).

## Versionado

Estado publicado como **v1.0.0** (snapshot inicial estable). Etiqueta sugerida en Git:

`v1.0.0`

---

**Seguridad:** no subas `.env`, `vendor/`, `node_modules/`, artefactos `dist/`/`build/` ni logs; el `.gitignore` en la raíz y en cada paquete está pensado para un repo limpio en GitHub.
