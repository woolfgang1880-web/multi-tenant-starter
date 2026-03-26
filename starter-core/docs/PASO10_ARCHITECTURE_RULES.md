# PASO 10 — Reglas arquitectónicas automáticas

## Objetivo

Evitar regresiones estructurales de forma ligera y mantenible usando PHPUnit, sin introducir herramientas pesadas ni refactor masivo.

## Reglas implementadas

Archivo: `tests/Feature/ArchitectureRulesTest.php`

1. **Controllers sin lógica de persistencia/queries de dominio**
   - Protege contra controladores “gordos”.
   - Señales prohibidas: `DB::`, `::query(`, `->save(`, `->update(`, `->delete(`, `->create(`).
   - Además, salvo excepciones puntuales (`LogoutController`, `MeController`), no permite `use App\Models\...` en controllers.

2. **Services no dependen de controllers**
   - En `app/Services`, prohíbe imports `use App\Http\Controllers\...`.
   - Mantiene dirección de dependencias limpia.

3. **Acceso a modelos multi-tenant (User/Role) fuera de servicios/modelos**
   - En `app/*` (excepto `app/Services` y `app/Models`) prohíbe `User::query(` y `Role::query(`.
   - Reduce riesgo de bypass de scoping tenant.

4. **Requests no dependen de responses**
   - En `app/Http/Requests`, prohíbe:
     - `use App\Support\Api\ApiResponse`
     - `HttpResponseException`
   - Mantiene separación validación vs presentación.

5. **Support/Logging no depende de controllers**
   - En `app/Support/Logging`, prohíbe imports de controllers.

## Qué protegen estas reglas

- Mantener controllers como orquestadores.
- Evitar acoplamientos de capa HTTP hacia dominio/servicios en sentido inverso.
- Preservar convenciones multi-tenant en acceso a datos.
- Mantener logging y requests libres de dependencias impropias de presentación.

## Límites de esta validación

- Es una validación por patrones de texto (no análisis AST semántico completo).
- Puede tener falsos negativos/positivos en casos muy sofisticados.
- No reemplaza code review ni una herramienta de arquitectura más avanzada.

## Cómo ejecutar

Solo reglas de arquitectura:

```bash
cd starter-core
php artisan test --filter=ArchitectureRulesTest
```

Suite completa:

```bash
php artisan test
```

## Cómo extender sin fragilidad

1. Añadir reglas nuevas en `ArchitectureRulesTest` por capas claras (una regla = una intención).
2. Preferir checks simples y explícitos sobre convenciones ya acordadas.
3. Si una excepción es intencional, documentarla con comentario y, si aplica, allowlist mínima.
4. Evitar reglas “demasiado inteligentes” que generen ruido y desalienten su uso.

