# PASO 7 — Quick wins de arquitectura (aplicados)

Mejoras de bajo riesgo alineadas con `docs/PASO6_ARCHITECTURE_REVIEW.md` §8.

## Cambios realizados

1. **`LoginRequest` y `RefreshRequest`** (`app/Http/Requests/Api/V1/Auth/`)  
   - Extienden `FormRequest` (no `ApiFormRequest`: rutas públicas sin tenant en contexto).  
   - `authorize(): true`.  
   - Mismas reglas que el antiguo `validate()` inline.  
   - `LoginController` y `RefreshController` usan `validated()` sobre estos requests.

2. **`UserRoleAssignmentService::assertRolesBelongToTenant`**  
   - Docblock explicando defensa en profundidad frente a reglas en FormRequests.

3. **`GET /api/v1/health` sin envelope**  
   - Descrito en `docs/openapi/openapi.yaml` (`info.description` + path ` /api/v1/health` + tag `System`).

## Comportamiento de API

Sin cambios en status codes, cuerpos JSON ni mensajes respecto al comportamiento previo.

## Cómo probar

```bash
cd starter-core
php artisan test
```
