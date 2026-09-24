# Rollback estructural — shell canónico DB 22 (sin variant_key)

**Naturaleza:** nota histórica de rollback de Family v1. No ejecutar: Canon libre v2 elimina ese modelo y la instalación local autorizó reset sin backfill.

**Alcance:** revertir el esquema de `aa_canonical_containers` de `DB_VERSION=22` (familia sola) a la forma previa con `variant_key` e índice compuesto con variante.

**No desactivar** `FOREIGN_KEY_CHECKS`.

## Orden inverso de ALTER (con FKs activos)

1. `ADD COLUMN variant_key varchar(64) NOT NULL` (rellenar filas existentes con un valor local acordado, p. ej. `general`, **antes** de añadir NOT NULL si la columna se añade nullable y luego se endurece).
2. `ADD KEY idx_family_variant_updated (family_id, variant_key, updated_at, id)`.
3. Confirmar que `idx_family_variant_updated` existe.
4. `DROP INDEX idx_family_updated`.
5. Verificar índices restantes y FKs (`family_id` → families, `container_id` → containers).
6. Solo entonces bajar `aa_db_version` al valor previo y alinear el código desplegado.

## Código y contratos

El rollback de esquema no restaura por sí solo URLs, AJAX, registry ni adaptadores family-only. Debe acompañarse del despliegue del árbol de código anterior a la eliminación de variantes del shell universal.

Finance clásico (`aa_finance_*`) no forma parte de esta migración: conserva `variant_key` con política local.
