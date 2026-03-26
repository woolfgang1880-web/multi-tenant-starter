# Política de sesión y tokens

## Modelo ideal (producción)

| Elemento | Almacenamiento | Observaciones |
|----------|----------------|---------------|
| **Access token** | Memoria (variable en JS) | No persistir en localStorage/sessionStorage. Se pierde al recargar; restaurar vía refresh. |
| **Refresh token** | Cookie `httpOnly`, `Secure`, `SameSite=Strict` | El backend setea la cookie; el frontend no accede por JS. Protege frente a XSS. |
| **Restauración** | POST `/auth/refresh` con cookie | El refresh se envía automáticamente. Devuelve nuevo access token. |

### Ventajas

- **XSS**: el access token en memoria no es accesible desde scripts inyectados.
- **CSRF**: `SameSite=Strict` reduce el riesgo si el frontend está en el mismo dominio o subdominio controlado.
- **Robo de cookie**: `httpOnly` impide lectura por JS; `Secure` solo por HTTPS.

## Fallback de desarrollo

Cuando no es viable usar cookies (CORS, dominio distinto, prototipado rápido):

| Elemento | Fallback | Riesgo |
|----------|----------|--------|
| Access token | `sessionStorage` | XSS puede leerlo. Menor exposición que localStorage (no persiste entre pestañas). |
| Refresh token | Body JSON en POST `/auth/refresh` | Mismo riesgo que access si se guarda en storage. |

### Reglas

1. **Nunca localStorage por defecto** para tokens en producción.
2. **sessionStorage** solo si existe política explícita de desarrollo y se documenta el trade-off.
3. El fallback actual en `starter-web` usa **localStorage** para el access token; es funcional para desarrollo pero **debe cambiarse** antes de producción.

## Comportamiento actual (starter-web)

- **Desarrollo**: access token y usuario en `localStorage`.
- **Riesgo XSS**: si hay vulnerabilidad XSS, un atacante puede leer el token.
- **Logout**: solo borra del storage; el backend no mantiene blacklist de tokens (stateless JWT).

## Próximos pasos recomendados

1. Implementar cookie httpOnly para refresh en backend cuando el frontend esté en mismo origen o CORS configurado.
2. Mover access token a memoria en el frontend; usar refresh para restaurar al recargar.
3. Documentar en `.env.example` la variable para forzar modo desarrollo (storage) vs producción (cookies).
