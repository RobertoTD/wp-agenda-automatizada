# Shell Canónico Base v1 — Plan de construcción

**Status:** active.

**Naturaleza:** plan temporal de construcción. No tiene rango constitucional.

**Fuente estructural:** `docs/04-canonical-constitution.md`. Capacidades: `docs/05-canonical-capabilities.md`.

**Control del texto:** este brief no debe reescribirse ni completarse por inferencia. Las decisiones nuevas aprobadas deben registrarse separadamente en “Decisiones posteriores y estado”.

## Objetivo único

Construir en paralelo un shell interior reutilizable que pueda vestir y operar cualquier familia registrada con estructura contenedor–registro.

El shell opera por `family_key` sobre la persistencia universal (`aa_canonical_*`), con CRUD de título y detalles. No incluye `amount`, `amount_total` ni otras características particulares.

La UI clásica de Finance (`module=canonical`, tablas `aa_finance_*`) permanece intacta y operativa; su `variant_key` es legado local, no del shell universal.

El shell se observa desde `module=canonical_shell` (listado general «Todas las listas» sin `family`) y desde rutas familiares (`module=canonical_shell&family=…`). Muestra únicamente el contrato base común a cualquier familia.

## Contrato base del shell

Contenedores:

- `id`
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

- resolver familia;
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

No construir un generador universal de formularios.

**Supersedido (decisión 26 / D0):** la prohibición de diseñar o implementar capabilities durante Shell Base v1. El paradigma de capacidades es normativo en `docs/05-canonical-capabilities.md`. La secuencia vigente es documentación → `amount` canónico integrado y validado → transición/retirada de Finanzas legacy → imágenes. El shell base sigue sin embeber reglas particulares de familia ni de `amount`.

~~No diseñar todavía capabilities particulares. Solo identificar los puntos mínimos donde posteriormente podrán montarse sobre cards, formularios, resúmenes y detalle.~~

## Composición prevista

```text
family
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

**Supersedido (decisión 26 / D0):** «No se implementarán capabilities durante Shell Base v1.» Ver `docs/05-canonical-capabilities.md` y la secuencia CAP en «Decisiones posteriores y estado».

El gateway, el shell y sus transportes no deben construir nombres de tablas a partir de parámetros recibidos.

La familia debe resolverse mediante el registry antes de seleccionar manifest, adaptador o servicios.

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

Una ruta con `family` debe resolverse mediante el registry. El parámetro `variant` en el shell es obsoleto: no participa en la identidad; las URLs nuevas y `$aa_canonical_url` no lo emiten.

Distinguir **alcance de consulta** e **identidad de recurso**:
- `module=canonical_shell` sin `family` abre el listado general de contenedores («Todas las listas») sobre las familias activadas y accesibles; no infiere una familia concreta ni sustituye la identidad de un recurso.
- **Excepción Ciclo 2B.1:** si exactamente una familia está disponible (enabled + autorizada), la petición bare se normaliza con `302` a `module=canonical_shell&family={única}` (conserva `page`). El alcance general sigue existiendo para N=0 y N≥2; la identidad de recursos no cambia.
- Crear/editar/eliminar y abrir registros siguen exigiendo `family_key` (identidad del recurso). Una `family` explícita inválida o desconocida no cae al alcance general ni se reescribe hacia otra familia.
- `lists_scope=all` en `view=records` solo conserva el origen de navegación para el retorno; no altera autorización ni pertenencia.

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

- ~~capabilities particulares~~ (**supersedido** por decisión 26 / D0 — ver `docs/05-canonical-capabilities.md`; el shell sigue sin embeber reglas de `amount` ni de familia);
- Archive como producto completo más allá de la familia canónica ya registrada;
- sharing;
- runtime público;
- API;
- API keys;
- interfaces especializadas;
- migraciones generales de familias existentes.

Los puntos mínimos y neutrales de extensión (cards, formularios, resúmenes, detalle) siguen siendo el lugar donde se montan capabilities sin contaminar el shell; el detalle de integración queda para la propuesta técnica, no cerrado en esta norma.

## Garantías requeridas

La construcción debe permitir verificar que:

- una familia registrada puede resolverse sin que el shell conozca su implementación;
- manifest, labels, adaptador y gateway mantienen contratos explícitos;
- el shell puede renderizar datos neutrales que no pertenezcan a Finance;
- un segundo manifest ficticio puede vestir el mismo shell sin copiarlo ni modificarlo;
- el código del shell no contiene dependencias ni vocabulario particulares de Finance;
- la familia `finance` en el shell universal se proyectaba únicamente mediante campos base (sin amount) durante Shell Base / PCU; **`amount` canónico** queda autorizado por decisión 26 como capability reutilizable (implementación pendiente), no como columna base ni como lógica `if (family===finance)` en el shell;
- paginación y orden respetan el contrato;
- la UI clásica de Finance (`module=canonical`) no sufre regresiones;
- cada ciclo tiene rollback sencillo.

## Primera etapa acumulada

**Estado:** completada y supersedida por el modelo family-only (DB 22). El esqueleto contractual y la entrada paralela del shell quedaron probados; el CRUD universal opera por `family` sin variante.

Referencia histórica (ya no operativa): la primera etapa se planteó originalmente con ruta `family + variant` y proyección provisional etiquetada `finance.general`. Ese contrato fue retirado; ver decisión 18 en «Decisiones posteriores y estado».

## Ruta progresiva aceptada para la primera etapa

**Histórico / completado.** Los pasos 1–7 de acceso paralelo, contratos, fixtures y proyección base se ejecutaron en SB1/PCU. El paso de resolución vigente es validar `family` mediante el registry (sin variante). No reabrir la ruta `family + variant` ni bindings `*.general` del shell.

La ruta detallada para el resto de Shell Base v1 (selector de familias, capabilities) se define en ciclos posteriores y no modifica silenciosamente este brief.

## Método de trabajo

Cada ciclo técnico comienza con una exploración y propuesta de Cursor.

La exploración no autoriza implementación.

Si la propuesta contiene errores o huecos, se solicita una corrección antes de implementar.

Cuando la propuesta sea aprobada, el prompt de implementación será una autorización dirigida con los límites y criterios de aceptación correspondientes.

## Decisiones posteriores y estado

- Gobierno documental: Ciclo 0 completado (`docs: establish canonical architecture governance`).
- Módulo paralelo aprobado: `canonical_shell`.
- Label provisional del sidebar: `Shell canónico` → PCU-5A grupo «Tipos de registros» → **Ciclo 2A:** entrada fija «Listas» (`build_module_url`) debajo de Agenda; grupo «Tipos de registros» retirado del sidebar (Settings conserva toggles).
- Acceso provisional: únicamente `manage_options` (enlace y acceso directo).
- Sidebar provisional enlazado inicialmente a `module=canonical_shell&family=finance&variant=general` → **PCU-5A:** URLs por familia vía `AA_Canonical_Family_Enablement_Nav` + policy base.
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
  5. **PCU-4** — catálogo y provisioning de familias: **completada** (`5703f84`). Catálogo productivo `finance.general` + `archive.general` en `AA_Canonical_Core_Bootstrap`; `AA_Canonical_Family_Provisioner` + `AA_Canonical_Family_Catalog_Lifecycle` (`CATALOG_VERSION=1`, option `aa_canonical_family_catalog_version`, `admin_init` prio 25); filas iniciales `is_enabled=0`, `seed_version=0`; **cero presets, bindings, contenedores y registros**; `DB_VERSION` permanece `21`.
  6. **PCU-5** — dividido en **PCU-5A** / **PCU-5B**:
     - **PCU-5A** — activación AJAX individual, Settings sin formulario/botón, gate `family_disabled` (HTTP 200), navegación dinámica «Tipos de registros», `postMessage` al padre: **completada** (`473cf7e`). Finance habilitada seguía con read adapter legacy temporal hasta 5B; Archive habilitada → `read_adapter_pending` hasta 5B; `is_enabled=0` = deshabilitada.
     - **PCU-5B** — bindings universales, fail-on-duplicate, desconexión Finance legacy del shell: **completada** (`7b9ee5a`). Shell `finance.general` / `archive.general` (enabled) → `AA_Canonical_Relational_Read_Adapter` + `aa_canonical_*`; bootstrap write universal (`AA_Canonical_Write_Binding_Bootstrap`); módulo clásico Finanzas sigue en `aa_finance_*`; cero dual-read/write, fallback o migración.
  7. **SB1-5B1** — primera escritura productiva del shell (create container): **completada** (`de3488c`). Endpoint `aa_create_canonical_container` + composition root write; UI «Nueva lista»; command 200 UTF-8 y details vacío → `null`; post-create → página 1 del listado.
  8. **SB1-5B2** — create record productivo: **completada** (`6477886`). Endpoint `aa_create_canonical_record` + `WriteCanonicalShellRecordUseCase`; UI «Nuevo registro»; command alineado (200/details null); tx INSERT + touch parent; redirect a página 1 de records sin `containers_page`.
  9. **SB1-5B3** — update record productivo: **completada** (`2596cc6`). Endpoint `aa_update_canonical_record` + `WriteCanonicalShellRecordUseCase::update`; UI «Editar» + modal create/update unificado (`canonical-shell-record-form.js`); command endurecido (200/details null); last-write-wins; preserva `public_id`/`created_at`/`container_id`; tx UPDATE + touch parent; redirect página 1 de records sin `page`/`containers_page`; sin delete ni edit/delete de contenedores; sin optimistic lock; cero legacy.
  10. **SB1-5B4** — delete record productivo: **completada** (`dcb6f49`). Endpoint `aa_delete_canonical_record` + `WriteCanonicalShellRecordUseCase::delete`; UI «Eliminar» + modal de confirmación separado; hard delete + touch atómico; uncertain bloquea retry y ofrece «Recargar lista»; CRUD de records completo; harness nav actualizado a PCU-5B; sin soft delete ni edit/delete de contenedores; cero legacy.
  11. **SB1-5B5** — update container productivo (title/details): **completada** (`d339826`). Endpoint `aa_update_canonical_container` + `WriteCanonicalShellContainerUseCase::update`; command alineado (200 UTF-8 / details vacío → `null`); UI «Editar» + modal create/update unificado (`canonical-shell-container-form.js`); precarga SSR segura; last-write-wins; preserva `public_id`/`family_id`/`created_at` (nota histórica del ciclo mencionaba `variant_key`; **supersedida** tras DB 22 / family-only); registros contenidos intactos; confirmed → página 1 del listado (`container_id: null` en JSON); sin delete container; sin support PHP compartido; cero legacy. Una API futura puede añadir control optimista sin cambiar la identidad canónica.
  12. **SB1-5B6** — delete container productivo (hard delete + FK CASCADE): **completada** (`ccca5e6`). Endpoint `aa_delete_canonical_container` + `WriteCanonicalShellContainerUseCase::delete`; command tag `[invalid_container_id]`; UI «Eliminar» + modal de confirmación separado; advertencia genérica sin conteo/preflight; una sola DELETE del contenedor; records vía `ON DELETE CASCADE`; uncertain bloquea retry y ofrece «Recargar listas»; CRUD universal de containers completo; sin soft delete; sin support PHP compartido en ese ciclo; cero legacy. **Hotfix posterior** (`cea9e00`): el modal `#aa-shell-delete-container-modal` se renderiza antes del script síncrono `canonical-shell-container-form.js` (el IIFE capturaba `null`), con prueba de orden en `test-canonical-shell-module-ac.php`; solo markup, sin cambios de JS, backend ni schema.
  13. **SB1-5C1** — higiene del transporte AJAX de mutaciones canónicas: **implementado** (`CanonicalShellWriteAjaxSupport` + `CanonicalShellWriteAjaxRejection` en `includes/http/ajax/`, cargados por el loader antes de los seis endpoints). El soporte concentra `authorize_identity()` (núcleo → ruta → Access Policy → provisioning → enablement; **devuelve la definición de familia** — family-only; una redacción histórica que hablaba de «familia y variante» quedó **supersedida**), `build_write_gateway()` (registry nuevo por construcción + bootstrap existente → `CanonicalWriteGateway`) y `parse_positive_int()`. No lee la superglobal de la petición, no ejecuta SQL, no instancia repositories, no conoce comandos ni operaciones, no abre transacciones, no genera redirects y no emite JSON: lanza el rechazo y cada endpoint responde con su propio `error()`. Cada endpoint conserva auth, action, nonce, payload, IDs, mensajes, command, Use Case, identidad y manifest en su punto actual, estados, JSON, redirects y `error()` de tres o cuatro parámetros. Se conservan las **dos lecturas de enablement** (gate + bootstrap) sin cache ni cambios de firma, y las inconsistencias históricas de mapeo se preservan documentadas, no corregidas. Los ciclos SB1-5B5 y SB1-5B6 se cerraron sin support compartido; **SB1-5C1 introduce esa extracción**. La futura API reutilizará los contratos y Use Cases de Application, no este helper de WordPress.
  14. **SB1-5B+** — shell visual base restante (cerrado el CRUD contenedores; siguientes mejoras visuales/presets fuera de este cierre).
  15. **SET-1** — activación de familias/presets desde Settings (parcialmente anticipado por PCU-5A enablement AJAX; presets/capabilities de lista siguen el paradigma de `docs/05-canonical-capabilities.md`, implementación pendiente).
  16. **CAP-*** — sistema de capabilities y `amount` (clave estable aprobada). **Histórico / supersedido como nombre normativo:** el plan nombraba `monetary_amount` para el importe; la clave vigente es `amount` (ver decisión 26 y `docs/05-canonical-capabilities.md`). **C1a** (decisión 27) + **A1a** (28) + **A1b** (29) completadas: config, escritura, lectura/UI, `is_ready=true`, seed `DEFAULTS_VERSION=2`. Pendiente: retirada Finance e imágenes.
  17. **LEGACY-X** — proyección, integración o deprecación selectiva de módulos legacy. La retirada de Finanzas debe contemplar datos, referencias y consumidores; distinguir deshabilitar accesos, retirar código y eliminar tablas/datos (esta última no es automática al tener UI canónica).
- 18. **Shell family-only (DB 22)** — variantes eliminadas del shell universal `aa_canonical_*`: identidad/contratos/persistencia por `family_key` sola; Finance clásico conserva `variant_key` vía política local en `FinanceUseCaseSupport`; rollback estructural en `docs/plans/canonical-shell-db22-rollback.md`.
- 19. **Alcance general «Todas las listas» (Ciclo 1 UI)** — `module=canonical_shell` sin `family` lista contenedores agregados de familias enabled+accesibles (`ReadCanonicalShellAllContainersUseCase` + `CanonicalAggregatedContainersPort` / adapter / repo multi-familia); paginación/orden/conteo globales; vacío de familias → página vacía sin SQL abierto; rutas familiares intactas; `lists_scope=all` solo en retorno de records; header `data-aa-page-title="Todas las listas"`. `DB_VERSION=22` / `CATALOG_VERSION=1` sin índices nuevos.
- 20. **Navegación Listas + filtro header + default create (Ciclo 2A UI)** — sidebar «Listas» activo en todo `canonical_shell` (visible con acceso al módulo aunque 0 familias); filtro header «Todas las listas» + familias (`aria-label="Filtrar listas"`; current = contexto mostrado, no `lists_scope`); Settings toggles sin regeneración live de nav/sidebar; política de familia inicial al crear solo en JS (`resolveInitialCreateFamilyKey`).
- 21. **Colapso N=1 (Ciclo 2B.1 UI)** — `AA_Canonical_Family_Enablement_Nav::available_families` (registrada + provisionada + enabled + autorizada) unifica router, «Listas» y header; bare `module=canonical_shell` con exactamente una disponible → `302` a `family={única}` (conserva `page`); N=0 → Todas vacío + título estático; N=1 → Listas/header a esa familia sin «Todas»/disclosure; N≥2 → Todas + switcher; sin optimizar returns `lists_scope=all` (hop extra vía redirect).
- 22. **FAB Nueva lista + retirada encabezado (Ciclo 2B.2 UI)** — `#aa-shell-open-create-btn` como FAB violeta (`#aa-shell-fab-stack`, patrón Clientes) bajo `$show_create_ui`; modales shell a `z-[300]`; listados de contenedores resueltos sin tarjeta-header/badge «Resuelto»; `h1.sr-only` + `data-aa-page-title`; records conservan Volver + CTA inline «Nuevo registro» (no FAB de registros); preview conserva encabezado de demostración.
- 23. **Superficie de lista abierta en registros (Ciclo 3 UI / records fill)** — en `resolved` + `empty|resolved_page` (no preview/gates): marca `aa-shell-records-fill` en `html`+`body` desde `canonical-layout.php`; panel blanco `.aa-shell-list-panel` con encabezado fijo (Volver, título, «Detalles») y cuerpo scroll; detalles plegados (texto + `updated_at`) al inicio del cuerpo; FAB `#aa-shell-open-create-record-btn` (mismo id/controlador); padding FAB dentro del cuerpo (`.aa-shell-list-panel-body--fab`), no `pb-24` exterior; `main.js` reporta solo cromo (sin `moduleH`) en fill para que el padre `max(cromo, available)` pueda reducir; standalone con `100dvh`; cards actuales intactas (rediseño compacto = ciclo siguiente).
- 24. **Registros compactos con apertura superpuesta (Ciclo 4 UI)** — solo fill/`resolved_page`: filas 1 columna gris cerrado / azul abierto; panel abspos sin empujar filas; `#aa-shell-records-scroll-extender` tras el `ul` y antes de paginación; medición `yContent` (getBoundingClientRect + scrollTop del body) sin `scrollHeight`; Escape con acuerdo `preventDefault`/`defaultPrevented` entre form y `canonical-shell-records-compact.js` (modal → menú → registro); `inert` en filas cubiertas; preview conserva cards; sin cambio de iframe cromo-only.
- 25. **Opciones en encabezado + geometría de menú (Ciclo 5 UI)** — opciones ⋮ solo con registro abierto, en `.aa-shell-record-header` (hermanas del toggle, no anidadas); reserva `pr-12` en el toggle vía `:has(.aa-shell-record-options)` (clickeable al cerrado; sin cambio de altura/`break-words`); menú hermano del wrap con `w-[12rem] max-w-full min-w-0 box-border` (sin `min-w-[12rem]`); búsqueda del menú y clic fuera = `.aa-shell-record-options` **o** `.aa-shell-record-options-menu`; `overlayBottom = max(panel, menú visible)`; al abierto sin ring de foco en toggle/panel (evita junta header–cuerpo; cerrado conserva ring); Escape/CRUD/preview/fill intactos.
- 26. **D0 — paradigma de capacidades canónicas (documentación)** — **completada** (solo docs; sin código/schema). Fuente normativa: `docs/05-canonical-capabilities.md`; constitución ajustada en `docs/04-canonical-constitution.md`; punteros en `AGENTS.md`, `.cursor/rules/canonical-deo.mdc` y `docs/00-paradigm-cheatsheet.md`. Decisiones fijadas: clave estable `amount` (**`monetary_amount` supersedido** como nombre normativo, sin alias obligatorio); defaults de familia persistidos en BD (código puede inicializar, no pisar guardados; materialización al crear lista; cambio de defaults → listas nuevas; aplicación a existentes = operación explícita); config efectiva en la lista; alcance declarado lista/registro/ambos; sin herencia dinámica ni `variant`; datos tipados explícitos (`amount` = decimal tipado ligado al registro; no “cada campo = una tabla”; imágenes no exentas de tipado); activación conserva valores y rechaza escrituras; update omitir=`amount` conserva / vaciar elimina; cero válido; `amount` v1 sin totalización/moneda/contabilidad/multi-importe; legacy Finance = referencia de límites/normalización con contrato de update distinto; secuencia documentación → `amount` canónico → transición/retirada Finance → imágenes. Pendientes técnicos **no** cerrados en norma: frontera de transacción atómica registro+`amount`, puntos de extensión R/W/UI, forma de invocar config desde desarrollador (AJAX **no** requisito aprobado), extracción de helpers neutrales sin dependencia del módulo Finance.
- 27. **C1a — infraestructura de capacidades (schema + config; sin activación de producto)** — **completada** (`DB_VERSION=23`). Tres tablas aditivas: `aa_canonical_family_capability_defaults`, `aa_canonical_container_capabilities`, `aa_canonical_record_amount` (valores sin repositorio/uso todavía). Catálogo sellado con `amount` (`scope=record`, **`is_ready=false`**). Repo `CanonicalCapabilityConfigRepository`; Use Cases set default / set activación lista / read config; fachada `AA_Canonical_Capability_Ops` (usuario autenticado + Access Policy / manage_options; sin bypass wp-config, sin AJAX, sin Settings). Lifecycle defaults: insert-if-missing filtrado por `is_ready` (cero seeds activos de amount en C1a); UC explícito sí puede modificar defaults existentes; habilitar exige ready; consultar/desactivar configuración existente de capacidad conocida permitido. **Excluido de C1a:** firmas/TX de CRUD registros, writer/normalizer amount, materialización al crear listas, lectura/UI amount, migración legacy. **Siguiente:** A1a (escritura atómica de valores); A1b (lectura/UI + marcar `amount` ready). Detalles de A1b (p. ej. semántica SSR `amount_read`) siguen abiertos hasta su incremento.
- 28. **A1a — escritura amount + materialización de defaults** — **completada**. Integración neutral `CanonicalCapabilityWriteBag` + handlers/effects (sin campos amount en CRUD base); `CanonicalRecordAmountRepository`; TX de registro (base+efectos+touch) propiedad del repositorio canónico; TX de create lista + materialización ready+enabled; `AA_Canonical_Amount_Normalizer` canónico en paralelo (Finance no delega; duplicación temporal hasta retirada legacy). Códigos HTTP: invalid_* 400, capability_unknown 400, capability_inactive/not_ready 409, schema_not_ready 503, persistence_failed 500, uncertain 409. En su momento el producto conservó `is_ready=false` hasta A1b.
- 29. **A1b — lectura/UI amount + ready** — **completada**. Contributors de página (`CanonicalCapabilityRecordPageContributor` + registry + enricher) coordinados por `capability_key`; lectura por lote string-preserving; estados inequívocos `known_value` / `known_absent` / `read_failed` (config indeterminable → offered+read_failed, no “desactivada”); única vía `build_records_view_data` (preview sin capabilities); presenters + tarjeta (importe en panel abierto; sin nodo si ausencia conocida; error distinguible); formulario genérico clear→apply→collect + módulo JS amount; `is_ready=true`; lifecycle `DEFAULTS_VERSION=2` insert-if-missing de `finance`/`amount` sin sobrescribir; sin activación masiva de listas existentes. **Excluido:** totalización, API, Settings, imágenes, retirada Finance.
- PCU-1 no autoriza ni inicia PCU-2.
