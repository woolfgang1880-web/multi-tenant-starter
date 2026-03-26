# Entregable: Fase de endurecimiento y mantenibilidad

## Resumen

Fase de endurecimiento aplicada al starter (API Express + starter-web). Sin nuevos módulos de negocio.

---

## 1. Pruebas agregadas o reforzadas

| Archivo | Cobertura |
|---------|-----------|
| `tests/api/auth.test.js` | login correcto/incorrecto, register, refresh válido/inválido, auth/me con/sin Bearer, token inválido |
| `tests/api/users.test.js` | CRUD completo, 401 sin token, 404 en id inexistente, 409 email duplicado |

**Total: 20 tests** (10 auth + 10 users). Ejecutar: `npm run test` (sin servidor en marcha, usa supertest).

**No aplica en este starter:**
- CRUD de roles, 403 por autorización, aislamiento entre tenants: el starter Node no incluye roles ni tenants (modelo simplificado).

---

## 2. Endurecimiento realizado

| Área | Cambio |
|------|--------|
| **auth/me** | Nuevo endpoint GET `/api/auth/me` con Bearer para validar sesión y obtener usuario actual |
| **Rate limiting** | `express-rate-limit` en POST `/auth/login` y `/auth/register` (10 req/15min prod, 100 dev) |
| **Headers** | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` |
| **Logs de seguridad** | `logger.security()` para login fallido y rate-limit excedido |
| **Secretos en prod** | Fallo de arranque si `NODE_ENV=production` y se usan secretos por defecto |
| **Body limit** | `express.json({ limit: '100kb' })` |
| **Respuestas de error** | Mantienen el formato `{ message, code, details? }` existente |

---

## 3. Riesgos detectados

| Riesgo | Estado | Recomendación |
|--------|--------|---------------|
| XSS vía localStorage (frontend) | Documentado | Migrar a token en memoria + cookie httpOnly para refresh |
| CORS abierto | Sin cambio | Restringir `origin` en producción |
| Repositorio en memoria | Sin cambio | Sustituir por DB antes de producción |

---

## 4. Deuda pendiente

- Implementar cookie httpOnly para refresh token en backend
- Migrar frontend a token en memoria
- Configurar CORS con allowlist en producción
- openapi.yaml: actualizar si se mantiene documentación OpenAPI

---

## 5. Siguiente prioridad recomendada

1. Persistencia real (DB) para usuarios
2. Implementar modelo de sesión con cookies httpOnly
3. Añadir tests e2e para starter-web (login, CRUD en UI)

---

## 6. Cómo probar

### Tests automatizados

```bash
cd starter-core
npm run test
```

### Prueba manual

1. Iniciar API: `npm run api`
2. Iniciar web: `cd ../starter-web && npm run dev`
3. Registrar usuario, login, CRUD usuarios, auth/me vía cliente o `curl`:

```bash
# Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Demo","email":"demo@test.com","password":"Demo12345"}'

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@test.com","password":"Demo12345"}'

# Me (usar accessToken del login)
curl http://localhost:8000/api/auth/me -H "Authorization: Bearer <TOKEN>"
```

---

## 7. Archivos modificados/creados

| Archivo | Acción |
|---------|--------|
| `src/modules/auth/auth.routes.js` | Añadido GET /me, rate limit en login/register |
| `src/modules/auth/auth.controller.js` | Añadido `me`, logs de login fallido |
| `src/modules/auth/auth.service.js` | Sin cambios |
| `src/config/env.js` | Validación de secretos en producción |
| `src/app.js` | securityHeaders, body limit |
| `src/shared/middleware/security.middleware.js` | Nuevo: rate limit, headers |
| `src/shared/utils/logger.js` | Añadido `logger.security()` |
| `tests/api/auth.test.js` | Nuevo |
| `tests/api/users.test.js` | Nuevo |
| `docs/SESSION_POLICY.md` | Nuevo |
| `docs/SECURITY.md` | Nuevo |
| `docs/README_EXPRESS.md` | Nuevo |
| `docs/OPENAPI_CHECKLIST.md` | Nuevo |
| `docs/DEPENDENCIES.md` | Nuevo |
| `docs/RETRO_FEEDBACK.md` | Nuevo |
| `.env.express.example` | Nuevo |
| `package.json` | express-rate-limit, supertest, script test |
| `starter-web/src/api/client.js` | Añadido `getMe()` |
| `starter-web/docs/SESSION_STORAGE.md` | Nuevo |
