# Almacenamiento de sesión (starter-web)

## Estado actual

- **Access token**: `localStorage` (clave `starter-web_access_token`)
- **Refresh token**: `localStorage` (clave `starter-web_refresh_token`)
- **Usuario**: `localStorage` (clave `starter-web_user`)
- **Refresh automático**: ante `401` en rutas protegidas, frontend intenta `POST /api/v1/auth/refresh` una vez y reintenta request.
- **Logout**: intenta `POST /api/v1/auth/logout` y luego limpia sesión local.

## Riesgo XSS

`localStorage` es accesible desde JavaScript. Si existe una vulnerabilidad XSS, un atacante puede leer el token. Ver `starter-core/docs/SESSION_POLICY.md` para el modelo ideal (token en memoria, refresh en cookie httpOnly).

## Desarrollo vs producción

| Entorno | Recomendación |
|---------|---------------|
| Desarrollo | Aceptable usar localStorage para prototipado rápido. |
| Producción | Migrar a token en memoria + refresh en cookie httpOnly. |

## Límites del fallback

- Recarga de página: access/refresh persisten (localStorage).
- Múltiples pestañas: comparten el mismo token.
- sessionStorage: no implementado; reduciría persistencia entre pestañas pero no elimina el riesgo XSS.
- Si refresh falla (`401`/`REFRESH_INVALID`), frontend cierra sesión local para evitar estados inconsistentes.
