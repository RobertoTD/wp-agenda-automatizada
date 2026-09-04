# Shell Canónico Base v1 — Plan de construcción

**Status:** active.

**Naturaleza:** plan temporal de construcción. No tiene rango constitucional.

**Fuente estructural:** `docs/04-canonical-constitution.md`.

**Control del texto:** este brief no debe reescribirse ni completarse por inferencia. Las decisiones nuevas aprobadas deben registrarse separadamente en “Decisiones posteriores y estado”.

## Objetivo único

Construir en paralelo un shell interior reutilizable que pueda vestir y operar cualquier familia registrada con estructura contenedor–registro.

El shell debe mostrar inicialmente `finance.general`, pero únicamente mediante su estructura canónica base. No debe incluir `amount`, `amount_total` ni ninguna otra característica financiera.

La UI actual de Finance debe permanecer intacta y operativa durante toda la construcción.

Al terminar la primera etapa acumulada, podremos entrar al nuevo shell desde una entrada independiente del sidebar, observar su construcción y compararlo con Finance. El shell podrá vestir `finance.general`, pero mostrará únicamente lo que podría mostrar de cualquier familia mediante el contrato base.

## Contrato base del shell

Contenedores:

- `id`
- `variant_key`
- `title`
- `details`
- `updated_at`

Registros:

- `id`
- `container_id`
- `title`
- `details`
- `updated_at`

`title` es obligatorio. `details` es nullable. `updated_at` es autoritativo, generado por servidor y no editable.

Contenedores y registros se ordenan por:

```text
updated_at DESC, id DESC
```

Antes de implementar la persistencia debe definirse si la actividad de un registro actualiza también `updated_at` de su contenedor y presentarse una recomendación explícita.

## Funciones que debe reunir Shell Base v1

- resolver familia y variante;
- recibir manifest, labels y adaptador;
- header contextual;
- vista paginada de contenedores;
- cards base con título, detalles y fecha de actualización;
- navegación a registros;
- vista paginada de registros;
- FAB con copy dinámico;
- crear, editar y eliminar contenedores;
- crear, editar y eliminar registros;
- modales base;
- loading, empty y errores;
- abort y descarte de respuestas obsoletas;
- locks;
- refresh autoritativo;
- revisión de resultados inciertos;
- accesibilidad y manejo de foco.

No diseñar todavía capabilities particulares. Solo identificar los puntos mínimos donde posteriormente podrán montarse sobre cards, formularios, resúmenes y detalle.

No construir un generador universal de formularios.

## Composición prevista

```text
family + variant
→ registry
→ manifest y adaptador
→ gateway
→ shell
```

El shell recibe una proyección canónica. No debe conocer la persistencia, endpoints, reglas ni características internas de ninguna familia.

## Gateway y adaptadores

El gateway canónico debe entregar un contrato común apto para ser consumido por el shell administrativo y, posteriormente, por el runtime público y la API/CLI.

Debe contemplar:

1. Adaptadores de familias que implementen directamente el contrato canónico.
2. Adaptadores para familias o persistencias existentes que necesiten proyectarse al contrato.
3. Puntos futuros de extensión para datos identificados por capabilities.

No se implementarán capabilities durante Shell Base v1.

El gateway, el shell y sus transportes no deben construir nombres de tablas a partir de parámetros recibidos.

La familia y variante deben resolverse mediante el registry antes de seleccionar manifest, adaptador o servicios.

**Supersedido:** la prohibición de una pareja universal de tablas compartida por todas las familias. El destino canónico es la persistencia universal (`canonical_families`, `canonical_containers`, `canonical_records`; nombres físicos con prefijo técnico, actualmente planteados como `aa_canonical_*`). Ver `docs/04-canonical-constitution.md`.

Una familia integrada conserva una sola fuente de verdad en la persistencia universal. Durante la adopción inicial, Finance y Expedientes mantienen además su persistencia legacy con sus datos y su UI. Esa coexistencia es temporal, explícita y acotada: no autoriza dual-write, backfill ni borrar tablas legacy. Se cierra en el ciclo LEGACY-X.

El shell, el runtime y la API no deben consultar directamente tablas ni repositorios internos de familia.

## Semántica de mutaciones

El shell solo envía y opera los campos base que le corresponden.

En creación:

- `title` es obligatorio;
- `details` puede ser `null`;
- los campos particulares no enviados usan la semántica definida por su familia o capability.

En edición:

- solo se modifican los campos base que la operación declare;
- los campos particulares ausentes no deben sobrescribirse;
- editar `title` o `details` nunca debe borrar información propia de la familia.

La preservación de campos particulares corresponde a Application, al adaptador y a la persistencia de la capability o del legado aplicable. El shell no debe leerlos ni reenviarlos para conservarlos.

Después de una mutación debe obtenerse estado autoritativo del servidor.

Las mutaciones deben contemplar locks, descarte de respuestas obsoletas y revisión posterior cuando el resultado sea incierto.

## Construcción paralela

El Shell Canónico Base v1 debe construirse mediante una entrada independiente.

Debe existir una entrada provisional en el sidebar para observar su avance sin sustituir la UI actual de Finance.

La URL y el mecanismo exacto de routing no se decidirán hasta inspeccionar el router existente.

Una ruta con `family + variant` debe resolverse mediante el registry.

Una ruta sin identidad canónica completa solo podrá mostrar un estado controlado de desarrollo o un fixture neutral; no deberá inferir silenciosamente una familia.

La UI actual de Finance debe permanecer disponible e intacta durante todos los ciclos.

## Límites del shell

El shell base no debe contener:

- nombres de familias;
- nombres de tablas;
- nombres de endpoints particulares;
- campos o reglas financieras;
- copy fijo propio de una familia;
- acceso directo a persistencia;
- comportamiento especializado presentado como universal.

No diseñar ni implementar todavía:

- capabilities particulares;
- Archive;
- sharing;
- runtime público;
- API;
- API keys;
- interfaces especializadas;
- migraciones generales de familias existentes.

Solo deben identificarse puntos mínimos y neutrales donde futuras extensiones puedan montarse sobre:

- cards;
- formularios;
- resúmenes;
- detalle.

## Garantías requeridas

La construcción debe permitir verificar que:

- una familia registrada puede resolverse sin que el shell conozca su implementación;
- manifest, labels, adaptador y gateway mantienen contratos explícitos;
- el shell puede renderizar datos neutrales que no pertenezcan a Finance;
- un segundo manifest ficticio puede vestir el mismo shell sin copiarlo ni modificarlo;
- el código del shell no contiene dependencias ni vocabulario particulares de Finance;
- `finance.general` se proyecta únicamente mediante campos base;
- paginación y orden respetan el contrato;
- la UI actual de Finance no sufre regresiones;
- cada ciclo tiene rollback sencillo.

## Primera etapa acumulada

El objetivo último de la primera etapa es dejar probado, sin tocar Finance actual, el esqueleto contractual y la entrada paralela del nuevo shell:

- una ruta validada mediante `family + variant`;
- resolución de manifest y adaptador;
- paso a través del gateway;
- render de datos canónicos base;
- visualización de `finance.general` sin características particulares.

Todavía no comprende el CRUD completo ni capabilities.

## Ruta progresiva aceptada para la primera etapa

1. **Explorar el terreno:** identificar routing, sidebar, registry, Finance y contratos actuales.
2. **Abrir acceso paralelo:** añadir al sidebar una entrada provisional al Shell Canónico sin alterar Finanzas.
3. **Definir contratos mínimos:** manifest, labels, adaptador, gateway y DTO canónico de lectura.
4. **Resolver la ruta:** validar `family + variant` mediante el registry.
5. **Renderizar con datos neutros:** probar header, contenedores y registros usando fixtures no financieros.
6. **Conectar `finance.general`:** proyectar únicamente sus campos base mediante su adaptador.
7. **Cerrar la prueba arquitectónica:** verificar aislamiento, paginación y orden, un segundo manifest ficticio y regresión cero en Finance.

La ruta detallada para completar el resto de Shell Base v1 se diseñará después de la exploración técnica y no podrá modificar silenciosamente este brief.

## Método de trabajo

Cada ciclo técnico comienza con una exploración y propuesta de Cursor.

La exploración no autoriza implementación.

Si la propuesta contiene errores o huecos, se solicita una corrección antes de implementar.

Cuando la propuesta sea aprobada, el prompt de implementación será una autorización dirigida con los límites y criterios de aceptación correspondientes.

## Decisiones posteriores y estado

- Gobierno documental: Ciclo 0 completado (`docs: establish canonical architecture governance`).
- Módulo paralelo aprobado: `canonical_shell`.
- Label provisional del sidebar: `Shell canónico`.
- Acceso provisional: únicamente `manage_options` (enlace y acceso directo).
- Sidebar provisional enlazado inicialmente a `module=canonical_shell&family=finance&variant=general`.
- `updated_at` representará la actividad contenida (crear/editar/eliminar un registro también actualiza el contenedor). Decisión aprobada; la implementación en schema Finance legacy quedó en SB1-4A. **Nota PCU:** el destino de timestamps canónicos nuevos es UTC en tablas universales, no “persistencia de familia”.
- SB1-1 (entrada paralela + root controlado + resolución de ruta): **commiteado** (`29ac40d`).
- División aprobada: **SB1-2A** (cadena de lectura tipada) → **SB1-2B** (composición visual del shell).
- SB1-2A: identidad `CanonicalReadIdentity` → puerto `CanonicalReadAdapterResolver` (impl `AA_Canonical_Read_Binding_Registry`) → `CanonicalReadGateway` → `CanonicalPage`; `AA_Canonical_Container` en Domain; `updated_at` interno `DateTimeImmutable` UTC serializado solo como `Y-m-d\TH:i:s\Z`; `PAGE_SIZE=15`; fixture solo en `tests/`. **Commiteado** (`469e5df`).
- SB1-2B (manifest + composición SSR + preview fail-closed): `CanonicalShellManifest` (defs + identity; labels derivados); `CanonicalShellReadResult` (`resolved_page|empty|read_adapter_pending|contract_error`); `ReadCanonicalShellContainersUseCase` (gateway por constructor); compositor WP + preview temporal `shell_preview.demo` bajo `AA_CANONICAL_SHELL_PREVIEW`; `finance.general` → pending + CTA solo si preview habilitado; sin segundo enlace sidebar; sin adaptador Finance real. **Commiteado** (`4aa32da`).
- Manifest: fuente única de labels = definitions; preview usa defs efímeras fuera del registry productivo; banner/CTA solo en view data.
- División aprobada: **SB1-3A** (contratos Record + Pagination + get_container/list_records + Use Case) → **SB1-3B** (URL `view=records`, navegación título, SSR).
- SB1-3A: `AA_Canonical_Instant` compartido; `AA_Canonical_Record`; `CanonicalPagination` + `CanonicalPage`/`CanonicalRecordsPage`; puerto ampliado; `CanonicalContainerNotFound`; `ReadCanonicalShellRecordsUseCase` + `CanonicalShellRecordsReadResult`; preview/fixture con registros; UI/router/URL sin cambios. **Commiteado** (`72708f6`).
- SB1-3B (navegación SSR contenedores → registros): policy ampliada (`view`, `container_id`, `containers_page`) + builders `build_records_url` / `build_preview_records_url`; router valida transporte y delega; compositor con `compose_*_records` vía Use Case 3A; título de card como único enlace; partial `record-card.php`; back exacto con `containers_page`; `finance.general` sigue pending; sin AJAX/JS. **Commiteado** (`c238709`).
- SB1-4A (timestamps autoritativos en persistencia Finance): columna `updated_at datetime NOT NULL` en `aa_finance_containers` y `aa_finance_records`; `DB_VERSION=20`; índices canónicos `(variant_key, updated_at, id)` y `(container_id, updated_at, id)`; autoridad `current_time('mysql')` **exclusiva de los repositorios Finance legacy** (no aplica a las tablas canónicas universales futuras, que almacenarán UTC); actividad de registros actualiza `updated_at` del contenedor padre en la misma transacción; sin exposición en DTOs/AJAX/UI. **Commiteado** (`c579f94`).
- SB1-4B (adaptador Finance legacy + binding `finance.general`): SQL de lectura específico en `AA_Finance_Canonical_Read_Adapter` (sin reutilizar repositorios Finance); registro productivo vía `AA_Canonical_Read_Binding_Bootstrap` (lazy, solo `finance.general`); compositor delega bootstrap genérico; timestamps MySQL locales interpretados con `wp_timezone()` → UTC Z; persistencia y UI Finance permanecen como única fuente de escritura del legado. **Commiteado** (`c775b9a`).
- SB1-5A1 (cadena neutral de escritura): puerto `CanonicalWriteAdapter` (seis métodos); `CanonicalWriteGateway` con validación de recibos; comandos tipados de contenedor y registro; `CanonicalMutationReceipt` (`confirmed`/`uncertain`); `CanonicalShellMutationResult`; `WriteCanonicalShellContainerUseCase` y `WriteCanonicalShellRecordUseCase`; `AA_Canonical_Write_Binding_Registry`. Sin adaptador productivo ni bootstrap de escritura: scaffolding pendiente de consumidor hasta PCU-5. **Commiteado** (`63f4610`).
- SB1-5A2 (adaptador de escritura Finance / `update_canonical_fields`): **cancelado**. Supersedido por la ruta PCU.
- Giro arquitectónico aprobado: Persistencia Canónica Universal (PCU). Destino canónico = tablas universales compartidas; Finance y Expedientes legacy intactos durante la transición.
- **Ruta vigente (PCU y continuación del shell):**
  1. **PCU-0** — auditoría y propuesta arquitectónica: **completada**.
  2. **PCU-1** — reconciliación documental e inicialización de la ruta: **completada** (`c5f97ee`).
  3. **PCU-2** — schema universal aditivo y tests MySQL: **completada** (`6fcb124`). Tablas vacías `aa_canonical_families`, `aa_canonical_containers`, `aa_canonical_records`; `DB_VERSION=21`; `AA_Canonical_Schema` con FK RESTRICT/CASCADE y `verify()` fail-closed.
  4. **PCU-3** — repositorio y adaptadores relacionales universales: **completada** (`d01eb81`). `CanonicalRelationalRepository` + adaptadores read/write; sin binding productivo ni conexión del shell.
  5. **PCU-4** — catálogo y provisioning de familias: **implementado en working tree (sin commit).** Catálogo productivo `finance.general` + `archive.general` en `AA_Canonical_Core_Bootstrap`; `AA_Canonical_Family_Provisioner` + `AA_Canonical_Family_Catalog_Lifecycle` (`CATALOG_VERSION=1`, option `aa_canonical_family_catalog_version`, `admin_init` prio 25); filas iniciales `is_enabled=0`, `seed_version=0`; **cero presets, bindings, contenedores y registros**; `DB_VERSION` permanece `21`. **PCU-5 no iniciado.**
  6. **PCU-5** — bindings productivos, reconexión de lectura/escritura del shell y fail-on-duplicate en registries.
  7. **SB1-5B+** — shell visual base: header, toolbar, FAB, modales y CRUD `title`/`details`.
  8. **SET-1** — activación de familias/presets desde Settings.
  9. **CAP-1 / CAP-2 / CAP-3** — sistema de capabilities; `monetary_amount`; agregado monetario; imágenes.
  10. **LEGACY-X** — proyección, integración o deprecación selectiva de módulos legacy.
- PCU-1 no autoriza ni inicia PCU-2.
