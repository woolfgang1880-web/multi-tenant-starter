# Checklist: cambios de backend que obligan a actualizar OpenAPI

**Starter v1:** `docs/openapi/openapi.yaml` tiene **cobertura parcial intencional**. Aplica este checklist **solo** a endpoints que **ya estén** documentados en el YAML; si añades rutas nuevas solo en código, no es obligatorio añadirlas al spec en la plantilla (los productos derivados pueden ampliar el contrato).

Si el cambio toca un endpoint **documentado** en `openapi.yaml`, actualiza cuando:

- [ ] Añades un nuevo endpoint **que ya forme parte del contrato publicado**
- [ ] Cambias método, path o códigos de respuesta de un endpoint documentado
- [ ] Modificas el schema de request/response (campos, tipos) de lo documentado
- [ ] Añades o quitas headers requeridos (ej. Authorization)
- [ ] Cambias códigos de error (ej. 401 → 403)
- [ ] Añades rate limiting (documentar 429)

## Referencia de rutas reales

La lista completa de endpoints vive en `routes/api.php` (prefijo `/api/v1/...`). El YAML puede incluir solo un subconjunto; no usar tablas antiguas con paths `/api/auth/...` sin `v1`.
