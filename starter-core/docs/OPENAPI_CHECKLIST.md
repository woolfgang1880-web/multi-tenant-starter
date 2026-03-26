# Checklist: cambios de backend que obligan a actualizar OpenAPI

Si el proyecto usa `openapi.yaml` o documentación OpenAPI, actualiza cuando:

- [ ] Añades un nuevo endpoint
- [ ] Cambias método, path o códigos de respuesta de un endpoint
- [ ] Modificas el schema de request/response (campos, tipos)
- [ ] Añades o quitas headers requeridos (ej. Authorization)
- [ ] Cambias códigos de error (ej. 401 → 403)
- [ ] Añades rate limiting (documentar 429)

## Endpoints actuales (referencia)

| Método | Path | Auth | Respuestas principales |
|--------|------|------|------------------------|
| GET | /api/health | No | 200 |
| POST | /api/auth/register | No | 201, 400, 409 |
| POST | /api/auth/login | No | 200, 401, 429 |
| POST | /api/auth/refresh | No | 200, 401, 429 |
| GET | /api/auth/me | Bearer | 200, 401 |
| GET | /api/users | Bearer | 200, 401 |
| POST | /api/users | Bearer | 201, 400, 401, 409 |
| GET | /api/users/:id | Bearer | 200, 401, 404 |
| PUT | /api/users/:id | Bearer | 200, 400, 401, 404, 409 |
| DELETE | /api/users/:id | Bearer | 204, 401, 404 |
