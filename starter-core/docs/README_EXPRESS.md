# starter-core API (Express)

API REST de usuarios con autenticación JWT. Parte del stack starter-core + starter-web.

## Requisitos

- Node.js 18+
- (Opcional) Variables en `.env` — ver `.env.express.example`

## Ejecución

```bash
# Desarrollo
npm run api

# O con puerto explícito
PORT=8000 npm run api
```

La API escucha en `http://localhost:8000/api`.

## Endpoints

| Método | Path | Descripción |
|--------|------|-------------|
| GET | /api/health | Salud del servicio |
| POST | /api/auth/register | Registro (name, email, password, age?) |
| POST | /api/auth/login | Login (email, password) |
| POST | /api/auth/refresh | Refresh token |
| GET | /api/auth/me | Usuario actual (Bearer) |
| GET | /api/users | Lista paginada (Bearer) |
| POST | /api/users | Crear usuario (Bearer) |
| GET | /api/users/:id | Obtener por ID (Bearer) |
| PUT | /api/users/:id | Actualizar (Bearer) |
| DELETE | /api/users/:id | Eliminar (Bearer) |

## Tests

```bash
# Con la API corriendo en otra terminal
npm run api   # terminal 1
npm run test  # terminal 2
```

## Documentación adicional

- `docs/SESSION_POLICY.md` — Política de tokens y sesión
- `docs/SECURITY.md` — Postura de seguridad
- `docs/OPENAPI_CHECKLIST.md` — Cuándo actualizar OpenAPI
