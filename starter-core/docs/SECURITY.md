# Seguridad — starter-core (API Express)

## Postura actual

### Implementado

- **JWT**: access (15m) y refresh (7d) con type check.
- **bcrypt** para hashes de contraseña (10 rounds).
- **Validación de inputs** en auth y users.
- **Rate limiting** en `/auth/login` y `/auth/register` (10 req/15min en prod, 100 en dev).
- **Headers**: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- **Logs de seguridad** para login fallido y rate-limit.
- **Secretos en producción**: falla el arranque si se usan valores por defecto.
- **Límite de body**: 100kb para JSON.

### Pendiente / notas

- **Content Security Policy (CSP)**: no aplicada en la API (es responsabilidad del servidor que sirve el HTML). Documentar para el frontend.
- **CORS**: actualmente abierto (`cors()` sin restricciones). En producción configurar `origin` allowlist.
- **HTTPS**: obligatorio en producción; el backend no fuerza redirect.
- **Cookies httpOnly** para refresh: ver `docs/SESSION_POLICY.md`.

## Riesgos conocidos

| Riesgo | Mitigación actual | Recomendación |
|--------|-------------------|---------------|
| XSS (frontend) | Token en localStorage | Migrar a memoria + cookie httpOnly |
| CORS abierto | Ninguna | Restringir `origin` en prod |
| Secretos débiles | Validación en arranque | Usar secrets manager en prod |
| Repositorio en memoria | Solo para desarrollo | Sustituir por DB antes de prod |

## Headers de seguridad (API)

La API envía:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`

No se usa `helmet` para evitar sobreconfiguración; estos headers cubren los mínimos.
