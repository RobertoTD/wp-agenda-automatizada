# Canonical Record View Composition v0

**Estado:** RVC-0, RVC-1, RVC-2A, RVC-2B y RVC-2C completados.

**Ámbito:** consulta y navegación de registros del Shell Canónico administrativo.

## Propósito

Componer criterios canónicos y de capabilities antes de contar y paginar, sin introducir semántica vertical en el shell ni implementar todavía el buscador facetado.

## Modelo

La consulta efectiva se forma así:

```text
registros canónicos del contenedor
+ criterios naturales de capabilities activas
+ sustituciones seleccionadas en vistas
= resultado contado, paginado y presentado
```

Cada criterio tiene un propietario estable. Una vista sustituye solo el criterio natural de su propietario; conserva los demás. Ejemplo con ambas capabilities activas:

```text
Simple                  completed=false + postponed=false
Completadas             completed=true  + postponed=false
Postpuestas             completed=false + postponed=true
Completadas+Postpuestas completed=true  + postponed=true
```

No se filtran arrays ya leídos ni páginas parciales. `count` y `list` reciben la misma especificación compuesta.

## Transporte y resolución

Forma objetivo:

```text
view=records
records_view=simple
capability_views[completed]=completed
capability_views[postpone]=postponed
```

`lists_scope=all` conserva el origen de navegación; no es un criterio de registros. La URL selecciona definiciones registradas: nunca transporta SQL, callbacks o predicados arbitrarios.

Reglas de resolución:

- transporte malformado → `400`;
- capability conocida, activa y vista válida → se compone;
- capability conocida pero inactiva/not-ready → se elimina su selección y se redirige a URL canónica;
- paquete ausente → se elimina su selección y se redirige conservando el resto válido;
- capability activa con vista inválida → `400`;
- parámetros de lista, origen y selecciones válidas restantes se conservan; parámetros de familia son legado v1 y no se emiten;
- cualquier cambio de criterios reinicia `page` a 1; paginar conserva todas las selecciones.

La ausencia histórica de `records_view` y `records_view=completed` pueden aceptarse como entradas legacy y redirigirse a la forma objetivo. Internamente existe una sola especificación.

## Activación y lifecycle del paquete

Los filtros y vistas provienen de providers registrados por paquetes en código. La activación efectiva por lista decide si contribuyen.

```text
deactivate  retira consulta y presentación; conserva schema/datos
uninstall   retira registros de código; conserva schema/datos
reinstall   reconoce schema existente y recupera la proyección
purge       elimina datos/schema mediante operación destructiva explícita
```

Una URL antigua no reactiva ni instala una capability.

## Capability Package v0 — puerta de aceptación

Toda capability nueva debe declarar o justificar:

- identidad, label y alcance;
- dependencias e incompatibilidades explícitas, si existen;
- persistencia tipada y lifecycle de schema;
- lectura, escritura y permisos;
- criterio natural;
- vistas y sustituciones propias;
- acciones y módulos cliente;
- contribuciones de metadata de record y detalles de lista;
- activación, desactivación, uninstall, reinstall y purge;
- combinaciones o incompatibilidades explícitas;
- pruebas activa → inactiva → reactivada.

El manifiesto será PHP registrado y validado. No reemplaza las capas Domain/Application/Infrastructure/UI ni guarda ejecutables en base de datos. Las capabilities legacy pueden permanecer fuera hasta su migración explícita; `postpone` será el primer paquete nuevo.

## Decisiones de producto para `postpone`

- sin familia propietaria; default desactivada en toda lista;
- acción: **Postponer**; vista: **Postpuestas**; acción inversa: **Reanudar ahora**;
- presets exactos: 24 horas, 3 días, 7 días y 30 días;
- persistencia mínima: `record_id`, `postponed_at`, `postponed_until`, `preset_key`;
- completar no borra la postergación; postponer no modifica `completed`;
- las vistas se ofrecen siempre que su capability esté activa;
- expiración por comparación temporal, sin worker;
- card: metadata de estado antes de la fecha ordinaria;
- detalles de lista: agregado sobre el conjunto lógico completo, no la página visible.

La metadata de `completed` podrá mostrar `Completada el: …`; `postpone`, `Postpuesta hasta: …`. El shell solo itera contribuciones tipadas.

## Secuencia

1. **RVC-0:** este contrato documental. **Completado.**
2. **RVC-1:** especificación y composición de consulta; migrar `completed` sin cambiar producto. **Completado.**
3. **RVC-2A:** definiciones tipadas, toggles aditivos y política de creación. **Completado.**
4. **RVC-2B:** menú accesible `Vista` y `Simple`; aplicar la política de creación. **Completado.**
5. **RVC-2C:** slot de metadata de card; `completed_at` como primer consumidor. **Completado:** provider tipado, registry sellado, localización neutral del composer y slot antes de `updated_at`; repetir completar conserva su fecha.
6. **RVC-2D:** slot tipado de detalles/agregados sin migrar capabilities legacy.
7. **PKG-0:** manifiesto, registry y validador del Package Contract v0. **Completado:** `completed` v1 es el primer package; es fuente de identidad, label y seed/default `action=true`. Sus providers siguen en registries por slot y el package no ejecuta callbacks ni autodiscovery.
8. **POST-1:** schema, persistencia, lectura y escritura de `postpone`.
9. **POST-2:** acción, módulo cliente, vistas y contribuciones visuales.
10. **POST-3:** combinaciones, lifecycle, expiración y validación integrada.

RVC-2D queda aplazado conscientemente: el agregado de detalles de lista no bloquea `postpone` ni el contrato de package.

## Implementación de PKG-0

`AA_Canonical_Capability_Package_Definition` valida el manifiesto y `AA_Canonical_Capability_Package_Registry` lo sella. El package declara resource key y lifecycle conservativo (`deactivate`/`uninstall` preservan; `purge` es explícito), no tablas ni SQL. `completed` v1 declara `record_read`, `record_write`, permiso canónico de registro, criterio natural, vista `completed` y sus cuatro contribuciones v0. La definición canónica, label y seeds se derivan del package; registries de providers permanecen contratos independientes por superficie.

## Implementación de RVC-1

Application resuelve una especificación inmutable de consulta por criterios propietarios. Infrastructure traduce esa especificación a predicados SQL mediante compilers registrados; el repositorio solo consume el predicado resultante y usa la misma composición para `count` y `list`. `completed` aporta su criterio natural y su sustitución explícita desde su provider, sin semántica propia en el shell o repositorio genérico.

El transporte canónico usa `records_view=simple` y `capability_views[owner]=view`. La entrada legacy `records_view=completed` se normaliza por redirección. Las mutaciones conservan el contexto compuesto; crear un registro reinicia únicamente la página de registros.

RVC-1 no cambia schema ni versión de base de datos y no implementa `postpone`, el menú `Vista` ni metadata de card/lista.

## Implementación de RVC-2A

Cada vista es una definición tipada con clave, label, criterio y política `allows_record_creation`. La resolución entrega `Simple`, estado activo, target de toggle por propietario y política combinada; activar conserva las demás selecciones, desactivar retira solo la propia y `Simple` limpia todas. Si varias vistas están seleccionadas, la prohibición de crear prevalece. `completed` declara que Completadas no permite crear.

El compositor ya expone URLs toggle, `simple_record_view` y `records_view_policy`, pero la UI no los aplica todavía: menú, FAB y presentación visible corresponden a RVC-2B. RVC-2A no cambia schema, datos ni `DB_VERSION`.

## Implementación de RVC-2B

El encabezado de lista presenta un disclosure `Vista` con `Simple` y las vistas declaradas, marca todas las selecciones activas y navega mediante los targets aditivos de RVC-2A. El control usa botones y enlaces nativos; Escape cierra primero el disclosure y después el popup exterior.

La política compuesta gobierna únicamente la creación de registros. Las vistas alternativas no retiran edición base, imágenes ni acciones de otras capabilities; la administración de la lista permanece en `Simple`. RVC-2B no cambia consulta, schema, datos ni `DB_VERSION`.

## Aceptación de RVC-1 y posteriores

RVC-1 cubre Simple, Completadas y composición con un segundo provider de prueba; capability inactiva, reactivación, paquete ausente, vista inválida, fallo de lectura, conteo, paginación y conservación de selecciones en URL. `Postpuestas`, expiración real y las combinaciones de producto con `postpone` se validarán cuando exista ese paquete.
