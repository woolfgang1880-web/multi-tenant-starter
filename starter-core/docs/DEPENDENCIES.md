# Dependencias — starter-core (Express)

## Esenciales (producción)

| Paquete | Uso | Mantener actualizado |
|---------|-----|----------------------|
| express | Servidor HTTP | Sí — revisiones de seguridad |
| jsonwebtoken | JWT access/refresh | Sí |
| bcryptjs | Hash de contraseñas | Sí |
| cors | CORS | Sí |
| dotenv | Variables de entorno | Sí |
| express-rate-limit | Rate limiting auth | Sí |

## Desarrolladores (dev)

| Paquete | Uso | Nota |
|---------|-----|------|
| axios | HTTP client (Laravel/Vite) | Compatibilidad con build |
| vite, tailwindcss, etc. | Build frontend | No afecta API en sí |

## Producción

- **bcryptjs**: seguro; alternativamente `bcrypt` (nativo) para mejor rendimiento.
- **express-rate-limit**: store en memoria por defecto; para múltiples instancias usar Redis (adaptador `rate-limit-redis`).
- Nada experimental que deba evitarse.

## Actualizaciones

Ejecutar periódicamente:

```bash
npm audit
npm outdated
```

Priorizar parches de seguridad (`npm audit fix`).
