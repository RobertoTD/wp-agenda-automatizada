# Shell Canónico Base v1 — Plan de construcción

**Status:** active.

**Naturaleza:** plan temporal de construcción. No tiene rango constitucional.

**Fuente estructural:** `docs/04-canonical-constitution.md`. Capacidades: `docs/05-canonical-capabilities.md`.

**Control del texto:** este brief no debe reescribirse ni completarse por inferencia. Las decisiones nuevas aprobadas deben registrarse separadamente en “Decisiones posteriores y estado”.

## Objetivo único

Construir en paralelo un shell interior reutilizable que pueda vestir y operar cualquier familia registrada con estructura contenedor–registro.

El shell opera por `family_key` sobre la persistencia universal (`aa_canonical_*`), con CRUD de título y detalles. No incluye `amount`, `amount_total` ni otras características particulares.

La UI clásica de Finance (`module=canonical`, tablas `aa_finance_*`) está **retirada** (LEGACY-X / decisión 30). La familia canónica `finance` opera solo vía shell + `aa_canonical_*` + capability `amount`.

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

Una familia integrada conserva una sola fuente de verdad en la persistencia universal. **Finance legacy** quedó retirado en LEGACY-X (decisión 30). Expedientes u otras superficies no canónicas pueden seguir con persistencia propia hasta su propio cierre; eso no autoriza dual-write canónico ni reintroducir Finance legacy.

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
  15. **SET-1** — activación de familias/presets desde Settings (parcialmente anticipado por PCU-5A enablement AJAX; selección de capabilities por lista en shell = decisión 31; Settings/presets de capabilities siguen pendientes).
  16. **CAP-*** — sistema de capabilities y `amount` (clave estable aprobada). **C1a–A1b** completadas; **LEGACY-X Finance** (decisión 30) completada; **selección por lista / repertorio DB 25** (decisión 31) completada. **IMG-0** paradigma `images` documental (decisión 32). **IMG-1** schema + catálogo not-ready (`DB_VERSION=26`, decisión 33). **IMG-3a** consumo compartido + persistencia admisión (`DB_VERSION=27`, decisión 34). **IMG-3b** attach canónico Application/AJAX (decisión 35). **IMG-4** lectura por lote + sign-read (decisión 36). **IMG-5** incrementos 1–5 (**etapa de eliminaciones cerrada**, decisión 41 / plan §9). **Exploración §§10–11** (decisiones 42–43). **Paso 1 activación por lista** (decisión 44 / plan §12.I): **preparación declarativa implementada**. **Paso 2 picker/attach/presentación mínima** (decisión 45 / plan §13): **implementado**. **Paso 5 flip** (decisión 46): `is_ready=true` + `DEFAULTS_VERSION=3`. **Amount total de lista** (decisión 47): **implementado**. Pendiente: galería completa; residuales Continuar/Cerrar/banner; worker ops.
  17. **LEGACY-X** — **Finance completada** (decisión 30). Otros legacies (p. ej. superficies no canónicas) siguen fuera de este cierre.
- 18. **Shell family-only (DB 22)** — variantes eliminadas del shell universal `aa_canonical_*`: identidad/contratos/persistencia por `family_key` sola. *(Histórico: Finance clásico tenía `variant_key` local; retirado en LEGACY-X / decisión 30.)* Rollback estructural en `docs/plans/canonical-shell-db22-rollback.md`.
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
- 30. **LEGACY-X — retirada Finance legacy** — **completada** (`DB_VERSION=24`). Eliminados accesos (`module=canonical` → 404), AJAX/UI/application/repos/`FinanceSchema`/`Shell_Url_Policy`/adapter desconectado y normalizador duplicado. Conservados: familia `finance`, `amount`, `AA_Canonical_Amount_Normalizer`, `aa_canonical_*`, Access Policy. Migración: DROP `aa_finance_records` luego `aa_finance_containers` (FK respetada); no recreación en installs nuevas; fallo no consolida `aa_db_version`. Sin migración de datos. **Excluido:** imágenes, totalización, Settings.
- 31. **Selección de capacidades por lista + rename repertorio (DB 25)** — **completada**. `aa_canonical_family_capability_defaults` → `aa_canonical_family_capabilities` (`is_enabled` → `is_default`; fila = repertorio). Wire `capability_selection_scope`/`capability_selection`; create explícito reemplaza materializador; update omit conserva; desactivar conserva valores; snapshot de lista incluye asignadas fuera de repertorio; UI modal + edit desde records. Normativa en `docs/05-canonical-capabilities.md` §§2–4.1. **Excluido:** imágenes, Settings, API, totalización.
- 32. **IMG-0 — paradigma `images` (documentación) + especificación Ciclo 1 (sin código)** — **completada** (solo docs; sin schema/código ejecutable/backend/UI). Norma: `docs/05-canonical-capabilities.md` §12; punteros en cheatsheet. Producto: clave `images`; **matriz de repertorio/defaults actualizada en §12.1** (cuatro `family_key`: `archive` default on; `finance`/`catalog`/`contact` default off — ver decisión 43); lectura por acceso; subida por beneficio/cuota vigentes; desactivar conserva; v1 una imagen/selección + galería adaptada como destino; fallo conserva registro; borrado registro/lista incluye imágenes aunque inactive; incomplete continuable; paralelo + retirada legacy posterior; multi-select/add-from-gallery pospuestos; no activar producto hasta recorrido previsto. **Diseño físico cerrado abajo (especificación Ciclo 1); implementación schema = decisión 33.**

#### Especificación Ciclo 1 — schema `images` (diseño; ejecutada en decisión 33 / `DB_VERSION=26`)

**Alcance del incremento de código (IMG-1):** migración aditiva + registro catálogo `images` con `is_ready=false` + tests schema; **sin** seeds de repertorio, sin AJAX/UI, sin bump de activación, sin path backend nuevo en plugin.

##### Tablas

1. **`aa_canonical_record_images`** (imagen confirmada; única fuente de **consumo consolidado** canónico)
   - `id` BIGINT UNSIGNED AI PK
   - `record_id` BIGINT UNSIGNED NOT NULL
   - `upload_operation_id` CHAR(36) NOT NULL — UUID v4; **UNIQUE**
   - `storage_path` VARCHAR(191) NOT NULL — **UNIQUE**
   - `content_sha256` CHAR(64) NOT NULL — hex SHA-256 del JPEG preparado admitido
   - `mime_type` VARCHAR(64) NOT NULL
   - `byte_size` INT UNSIGNED NOT NULL
   - `width` INT UNSIGNED NOT NULL
   - `height` INT UNSIGNED NOT NULL
   - `created_at` DATETIME NOT NULL (UTC)
   - FK `record_id` → `aa_canonical_records(id)` **ON DELETE RESTRICT**
   - KEY `(record_id, id)`

2. **`aa_canonical_image_upload_operations`** (admisión / vuelo / limpieza; **nunca** committed)
   - `upload_operation_id` CHAR(36) PK
   - `record_id` BIGINT UNSIGNED NOT NULL
   - `storage_path` VARCHAR(191) NOT NULL
   - `content_sha256` CHAR(64) NOT NULL
   - `mime_type` VARCHAR(64) NOT NULL
   - `byte_size` INT UNSIGNED NOT NULL
   - `width` INT UNSIGNED NOT NULL
   - `height` INT UNSIGNED NOT NULL
   - `status` ENUM/VARCHAR estable: `admitted` | `cleanup_needed`
   - `expires_at` DATETIME NOT NULL (UTC) — fijado en la admisión; **no** se amplía al reemitir URLs
   - `backend_intent_exp_ms` BIGINT UNSIGNED NULL — instante exacto de vencimiento (`admission_expires_at_ms` / intent `exp`); requerido en admisiones nuevas
   - `upload_intent` MEDIUMTEXT NULL — credencial operativa (IMG-3a); requerida en Application si `admitted` completo
   - `upload_objects_json` MEDIUMTEXT NULL — permisos originales de subida por objeto (IMG-3a); requerida en Application si `admitted` completo
   - `created_at` / `updated_at` DATETIME NOT NULL (UTC)
   - FK `record_id` → `aa_canonical_records(id)` **ON DELETE RESTRICT**
   - KEY `(status, expires_at)`, KEY `(record_id, status)`, KEY `(storage_path)`

3. **`aa_canonical_purge_runs`** (continuidad de borrado registro/lista)
   - `id` BIGINT UNSIGNED AI PK
   - `scope` ENUM: `record` | `container`
   - `target_id` BIGINT UNSIGNED NOT NULL — record_id o container_id
   - `family_key` VARCHAR(64) NOT NULL — contexto de auth/redirect (no sustituye Access Policy)
   - `status` ENUM: `in_progress` | `incomplete` | `completed` | `failed`
   - `cursor_kind` ENUM: `image` | `operation` — qué cola se está drenando
   - `cursor_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 — último `id` de imagen **o** no aplica a ops (ver invariante de cursor)
   - `cursor_operation_id` CHAR(36) NULL — para cola de operaciones (keyset por operation_id ordenado)
   - `deleted_ok` INT UNSIGNED NOT NULL DEFAULT 0
   - `failed_count` INT UNSIGNED NOT NULL DEFAULT 0
   - `created_at` / `updated_at` DATETIME NOT NULL (UTC)
   - UNIQUE `(scope, target_id)` mientras `status IN (in_progress, incomplete)` — una corrida abierta por target (implementar con índice parcial o invariante de aplicación si el motor no lo permite)
   - KEY `(scope, target_id, status)`

##### Estados e invariantes de operación

| Status | Reserva cuota | ¿Puede pasar a imagen confirmada? | Siguiente |
|--------|---------------|-----------------------------------|-----------|
| `admitted` (y `now < expires_at`) | **Sí** (`byte_size`) | Sí, si acceso OK y (capability active **o** esta admisión preexistente) y finalize coherente | TX: INSERT image + **DELETE** esta fila |
| `admitted` expirada | **No** (tratar como liberada; transición a `cleanup_needed`) | No — exige nueva admisión | `cleanup_needed` |
| `cleanup_needed` | **No** | No | Purge Storage → DELETE fila ops |

- **No existe** status `committed` en ops: al confirmar, la fila de operación **se elimina en la misma TX** que inserta `aa_canonical_record_images`.
- Una fila **no** puede contar a la vez como reserva y como confirmada.
- Liberar reserva (`cleanup_needed` o DELETE ops) **prohíbe** confirmar esa operación después sin **nueva** admisión (nueva capacidad).
- Idempotencia post-commit: si ya existe imagen con el mismo `upload_operation_id`, el reintento responde éxito con ese DTO (**sin** re-admitir ni re-cobrar).

##### Consumo, reserva y capacidad (diseño)

Separación obligatoria:

| Concepto | Definición |
|----------|------------|
| **Bytes confirmados** | `SUM(aa_expediente_adjuntos.byte_size)` + `SUM(aa_canonical_record_images.byte_size)` |
| **Bytes reservados** | `SUM(ops.byte_size)` donde `status='admitted'` AND `expires_at > now` |
| **Capacidad para nuevas admisiones** | `limit(tier) - confirmados - reservados` (evaluación vía backend `evaluateCapacity` con `used_bytes = confirmados + reservados` y `requestedBytes` del candidato) |
| **Consumo mostrado al usuario** | **Solo bytes confirmados** (no incluir reservas ni variantes). El endpoint informativo canónico futuro y el legacy `aa_get_expediente_storage_usage` deben alinearse a confirmados cuando se adapte coexistencia. |

Transiciones de reserva:

1. Fresh authorize OK → INSERT ops `admitted` → **reserva on**.
2. Commit TX → INSERT image + DELETE ops → reserva off, confirmado on (atómico).
3. Fallo / expiración → UPDATE `cleanup_needed` → **reserva off** de inmediato; objetos posibles siguen en inventario de purge.
4. Purge OK de ops → DELETE fila.

##### Coexistencia (cálculo y lock compartidos; adaptación en ciclo posterior)

**Hecho actual (legacy):** `used_bytes` solo desde `ExpedienteAdjuntosRepository::sum_byte_size_total()` bajo `AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA` / id `1` en:

- `UploadExpedienteRegistroAdjuntoUseCase`
- `UploadExpedienteAdjuntoForExpedienteUseCase`
- (informativo) `GetExpedienteStorageUsageUseCase` → `ExpedienteAdjuntosAjax`

**Canon (IMG-3a / DB 27):** mismo ámbito de lock; helper `AA_Installation_Storage_Usage::{confirmed_bytes, reserved_bytes, admission_used_bytes}` consumido por uploads legacy adaptados + usage AJAX; listo para uploads canónicos (IMG-3b). Vigencia de reserva: `backend_intent_exp_ms > now_ms` cuando existe; si no (filas históricas), `expires_at > now_utc` de la misma referencia. Filas admitted sin credenciales no son resumibles; siguen reservando hasta vencimiento conocido. Credenciales ops solo Application servidor (no DTO/HTML/logs).

**Attach canónico (IMG-3b):** `UploadCanonicalRecordImageUseCase` + `aa_attach_canonical_record_image`; locks `canonical_container` → purge abierta → `storage_quota`; fresh/resume; manifiesto `urls`+`variant_byte_sizes` en `upload_objects_json`; confirmación vía puerto/`AA_Canonical_Record_Image_Confirmation_Store` (TX images+ops+touch); reloj inyectable reconsultado en vigencia/reservas/pre-confirm. `images` permanece `is_ready=false` sin seeds/UI.

Archivos adaptados en coexistencia (IMG-3a): los tres Use Cases/AJAX listados; lock MySQL sin cambio de key.

##### Admisión vinculada y duración

- Inmutable: `record_id`, `operation_id`, `storage_path`, `content_sha256`, `byte_size`, `width`, `height`, `mime`, `expires_at`.
- `content_sha256` = SHA-256 del JPEG preparado; resume exige el mismo hash (otra imagen → rechazo).
- `expires_at` = instante de admisión anclado al `exp` del intent backend (TTL 2 h hoy); reemitir signed URLs **no** mueve `expires_at`.
- Cada request revalida: Access Policy, existencia registro/lista, ausencia de purge abierto en registro **o** su contenedor, vigencia, hash.
- Distinción: transferencia pendiente (`admitted`) / reconciliación idempotente (imagen ya existe) / nueva admisión.

##### Continuidad de borrado

- Inventario = imágenes del alcance ∪ ops (`admitted`|`cleanup_needed`) del alcance, **aunque** capability inactive.
- Cursor **keyset**, no OFFSET: drenar primero cola `image` con `id > cursor_id ORDER BY id ASC`; al vaciar, `cursor_kind=operation` y avanzar por `upload_operation_id` ordenado lexicográficamente (o `created_at, upload_operation_id`). Ítems que fallan **permanecen**; no se avanza el cursor past un fallo sin registrar `failed_count` y dejar el ítem para reintento (mismo id).
- Presupuesto por petición: tope de ítems **y** deadline de wall-clock (p. ej. 10–15 s) / timeout por llamada Storage; al agotar → `incomplete` con cursor conservado.
- Exclusión mutua: purge `in_progress|incomplete` en **container C** bloquea attach y purge de **cualquier registro** de C, y bloquea nuevo purge de C; purge en **record R** bloquea attach a R y delete de R; no inicia segundo purge concurrente del mismo target.
- Registro/lista eliminados solo tras inventario vacío + DELETE SQL del recurso; UI «Eliminación incompleta» + Continuar.

##### Catálogo Ciclo 1

- Registrar `images` en `AA_Canonical_Capability_Registry_Bootstrap`: `scope=record`, **`is_ready=false`**.
- **Sin** `declared_seeds()` para `images`.
- Lifecycle no materializa `images` en listas.

##### Reversibilidad

- Código/migración aditiva ≠ borrar datos de usuario.
- `is_ready` / defaults posteriores ≠ alterar listas existentes.
- Bajar `DB_VERSION` no es rollback ordinario.

- 33. **IMG-1 — Ciclo 1 schema `images` + catálogo not-ready** — **completada** (`DB_VERSION=26`). Tablas aditivas `aa_canonical_record_images`, `aa_canonical_image_upload_operations` (`admitted`/`cleanup_needed`; sin fila committed), `aa_canonical_purge_runs` (cursor keyset; **una purge abierta por target = invariante de Application**, no UNIQUE parcial en schema). FK images/ops → records **RESTRICT**. Catálogo: `images` `scope=record` `is_ready=false`; **sin** seeds finance/archive; lifecycle no materializa. **Excluido:** upload, sign-read, delete funcional, UI, backend path, activación de producto, coexistencia de cuota. **Estado:** infraestructura preparada; `images` **no operativa**.
- 34. **IMG-3a — consumo compartido + persistencia de admisión** — **completada** (`DB_VERSION=27`). Columnas `upload_intent` / `upload_objects_json` (MEDIUMTEXT NULL) en ops; `AA_Installation_Storage_Usage` (`confirmed_bytes` / `reserved_bytes` / `admission_used_bytes`); repos `CanonicalRecordImagesRepository` (suma) + `CanonicalImageUploadOperationsRepository` (insert-only admitted, resume apto, cleanup anula credenciales, suma reservas con ancla `backend_intent_exp_ms` o `expires_at` histórica); callers legacy upload + `GetExpedienteStorageUsageUseCase`/AJAX. UI informativa = confirmados; admisión = confirmed+reservas (exclude solo Application). **Excluido:** orquestación authorize/transfer/finalize canónica, confirmación SQL imagen, AJAX attach, UI, seeds, `is_ready`. **Pendiente IMG-2 ops:** prueba real Node local→Supabase. **Siguiente:** IMG-3b.
- 35. **IMG-3b — attach canónico Application/AJAX** — **completada** (sin bump de `DB_VERSION`; schema ya tenía `content_sha256` en images). `UploadCanonicalRecordImageUseCase` (fresh/resume/árbol de estados); `CanonicalImageUploadTransfer` (PUT+finalize); `authorize_canonical_upload` HMAC (`path_contract=canonical_v1` + `content_sha256`); puerto + `AA_Canonical_Record_Image_Confirmation_Store` (TX insert image + delete ops + touch contenedor; confirmed/failed/uncertain); lock `canonical_container`; purge blocking mínimo; endpoint `aa_attach_canonical_record_image`. Reloj inyectable reconsultado (no `now_ms` congelado de request). Manifiesto local `urls`+`variant_byte_sizes`; `variant_manifest_mismatch` 409 sin PUT. Access Policy canónica vía `authorize_identity` (finance sin `manage_options` extra). **Excluido:** UI/galería, seeds, `is_ready=true`, sign-read/delete productivos, purge runner, Render, retirada legacy. **Siguiente:** IMG-4.
- 36. **IMG-4 — lectura por lote + sign-read canónico** — **completada** (sin bump `DB_VERSION`). `CanonicalRecordImagesRepository::find_public_rows_by_record_ids_for_container`; `CanonicalRecordImagePublicDto`; `CanonicalCapabilityRecordReadState::known_collection`; `CanonicalImagesRecordsPageContributor` + bootstrap; `GetCanonicalRecordImageReadUrlUseCase` + `aa_sign_canonical_record_image_read`; path canónico en `ExpedienteAdjuntoVariants` para reutilizar `AA_Expediente_Attachment_Read_Url_Validator`. `expires_in` en segundos (TTL lectura 600). Desactivar bloquea nuevas firmas; no revoca URLs ya emitidas. **Excluido:** galería/picker/visor, seeds, `is_ready=true`, delete/purge, Render. **Siguiente:** UI/galería cuando se autorice; retiro de imágenes según `docs/plans/canonical-images-retire-wp-integration.md`.
- 37. **IMG-5 — retiro canónico / mandatos** — **incremento 1 completado** (cliente HMAC `accept_delete_batch` / `seal_delete_mandate` / `get_delete_mandate_status` en `AA_Expediente_Attachments_Backend_Client`; sin schema WP, sin delete de shell, sin `is_ready`). Plan y condiciones de incrementos 2–4: `docs/plans/canonical-images-retire-wp-integration.md`. **Excluido aún:** orquestación de purge, UI incompleta, cron, worker, Expedientes.
- 38. **IMG-5 incremento 2 — captura durable** — **completada** (`DB_VERSION=28`). Extiende `aa_canonical_purge_runs` (mandate, checkpoints de fuentes, tandas preparadas; `last_accepted_batch_seq`/`sealed_at` NULL hasta evidencia remota) y añade `aa_canonical_purge_inventory_items` sin FK a records/containers. `CaptureCanonicalPurgeInventoryUseCase` abre/reanuda bajo `GET_LOCK` `canonical_container`, captura páginas recuperables, prepara tandas de hasta 50 ítems. Confirmación y delete de shell respetan corrida abierta. **Excluido entonces:** HTTP accept/seal, DELETE de producto, UI Continuar, cron, worker, `is_ready`.
- 39. **IMG-5 incremento 3 — retiro de un registro** — **completada** (`DB_VERSION=29`). `batch_seq` persistido 0-based; intención de envío HMAC (`accept_intent_batch_seq` / `seal_intent_at`) antes del POST; `RetireCanonicalRecordUseCase` reutiliza captura bajo lock ya adquirido; skip HMAC si inventario vacío; sello estructural antes del DELETE local; cancelación local solo sin envío remoto; `aa_delete_canonical_record` + Continuar/cancelar en el modal. **Validación integrada policyytest 2026-09-14:** A/D AJAX **PASS**; B **PASS** tras parche `authorize-upload`; UI Eliminar vacío (32) + Cancelar (33) acreditados; Continuar **no** PASS; C integrado **pendiente**. **Excluido:** retiro de contenedores, cron/worker, `is_ready`, Expedientes. Plan: `docs/plans/canonical-images-retire-wp-integration.md`.
- 40. **IMG-5 incremento 4 — retiro de lista** — **completada** (`DB_VERSION=30`). `aa_delete_canonical_container` → `RetireCanonicalContainerUseCase`; HMAC compartido (`CanonicalPurgeRemoteMandateAdvancer`); seal obligatorio incl. `expected_batch_count=0`; chunks keyset post-sello (`local_retire_after_inventory_id` / `local_retire_after_record_id`); modal Continuar/cancelar. AC MySQL aislado + Ajax + JS. Validación policyytest 2026-09-14: schema blog 61 29→30; AJAX A/B/C **PASS**; Continuar visual desde listado lista 22 **PASS** (§7.12); Cerrar/recargar/banner/altas **pendientes**; primer clic lista 21 **no PASS** (§7.11). Lista 17 conservada. **Excluido:** retiro de una imagen (§8 del plan), cron/worker, `is_ready`, Expedientes. Plan: `docs/plans/canonical-images-retire-wp-integration.md` §7.
- 41. **IMG-5 incremento 5 — retiro de una imagen (registro vivo)** — **completada** (`DB_VERSION=31`). `scope=image`, `target_id=image.id`, columna durable `record_id` en purge_runs; captura puntual; `RetireCanonicalRecordImageUseCase` + `aa_delete_canonical_record_image`; HMAC via `CanonicalPurgeRemoteMandateAdvancer` (accept + seal count=1); TX local solo image+op; registro y demás capabilities intactos; blocking/overlap image↔record↔container; UI mínima Eliminar imagen + Continuar recovery; sin `is_ready`; sin cambio backend. AC MySQL aislado + Ajax + JS PASS. Policyytest: AJAX §8.12 PASS; clics propietario imagen 5 / registro 190 §8.13 acreditados. **Etapa de eliminaciones IMG-5 (imagen/registro/contenedor) cerrada** — balance en `docs/plans/canonical-images-retire-wp-integration.md` §9. Observaciones residuales Continuar/Cerrar/banner conservadas sin PASS. **Excluido aún:** worker ops, `is_ready=true`, implementación de subida/presentación de producto.
- 42. **IMG-6 dirección — enchufe modular + subida/galería** — **explorada en docs** (`canonical-images-retire-wp-integration.md` §10); **código pendiente**. Conservar delete IMG-5; no activar `is_ready` en la exploración.
- 43. **Primera etapa visible `images` — matriz + plan (solo docs)** — **documentada; implementación NO autorizada**. Norma §12.1 actualizada: repertorio `images` en `archive`/`finance`/`catalog`/`contact` con defaults 1/0/0/0; label «Imágenes»; sin retroactivo. Plan §11: criterios (checkbox, attach post-save, última confirmada por `id DESC`, deactivate sin borrado); reutilizar superficies amount/shell + thumb legacy; fuera galería completa/worker; `is_ready` solo tras A+B y aprobación explícita. HEADs comprobados: plugin `f9aee8299ee6c986426a3427859b87db3a896528`, backend `26452b3ba4ceb24b7bf46271fd30a0c58318ceed`.
- 44. **Paso 1 — activación por lista `images` (preparación declarativa)** — **implementada** (`canonical-images-retire-wp-integration.md` §12.I). Seeds 4 familias en `declared_seeds()`; label «Imágenes»; `is_ready=false`; `DEFAULTS_VERSION=2` y `DB_VERSION=31` sin cambio. Tests AC/JS aislados (registry ready stub). Regla admitida: desactivar bloquea fresh; resume/confirm pueden completar admisión previa; purge independiente. **No** validación visual de producto. Futuro flip ready + bump versión = otra autorización.
- 45. **Paso 2 — picker, subida post-save y presentación mínima** — **implementada** (`canonical-images-retire-wp-integration.md` §13.I). Módulo `AA_CANONICAL_SHELL_CAPABILITY_MODULES.images`; attach post-save antes de redirect SSR; presenter + thumb `summary` (última = `known_collection[0]` / `id DESC`). Sin galería/`display`/visor. HEAD base: `42046340ab0aa073b0282b4c2cd1059cff0c2f7b`.
- 46. **Paso 5 — flip readiness `images`** — **implementada** (`canonical-images-retire-wp-integration.md` §14). `is_ready=true` + `DEFAULTS_VERSION=3` en el mismo commit; matriz seeds sin cambio; `DB_VERSION=31` intacto; ensure materializa repertorio sin sobrescribir listas existentes. Validación: AC Paso 5 + suites post-flip; sin Storage/HTTP real. Galería/`display`/visor pendientes.
- 47. **Amount — proyección de total de lista** — **implementada**. Supersede la exclusión de totalización de contenedor que fijaron D0/`amount` v1 (decisión 26) y el ciclo A1b (decisión 29; «Excluido: totalización»), sin reescribir esos registros históricos. Norma vinculante: `docs/05-canonical-capabilities.md` §8.2–8.3. El total es **proyección derivada** de `amount` sobre **todos** los registros del contenedor (nunca la página visible); valores persistidos siguen en registros; no hay capability nueva ni campo de total en el contenedor; solo con `amount` activa; desactivar conserva valores y retira presentación+total; sin importe no aporta; activa sin importes → `0.00`; fallo de agregado → estado no disponible (sin fabricar `0`, sin omitir en silencio, sin total parcial); sin moneda/conversión/contabilidad/multi-importe. Implementación: agregado exacto de contenedor sin `float`, contributor/presenter de capability y presentación en detalles de lista; precisión de escala inesperada falla de forma visible, sin truncamiento. Sin `if (family)` ni tablas de capability en el shell. **No** pertenece a `finance` ni al shell base. Histórico CAP-2 como «agregado monetario separado» queda **retirado** para este alcance.
- 48. **RVC-0/RVC-1 — composición canónica de consulta y vistas** — **implementada** (sin cambio de schema ni `DB_VERSION`). Application compone criterios por propietario; Infrastructure los traduce mediante compilers registrados y Repository aplica un predicado único a `count` y `list`. `completed` aporta pendiente como criterio natural y completada como sustitución propia; el shell no conoce su tabla ni SQL. Transporte objetivo: `records_view=simple` + `capability_views[owner]=view`; la entrada legacy `records_view=completed` se redirige. Selecciones inactivas o de paquetes ausentes se retiran conservando las válidas; transporte malformado o vista inválida de una capability activa falla. Mutaciones conservan contexto compuesto. Norma y aceptación: `docs/plans/canonical-record-view-composition-v0.md`. **Excluido:** `postpone`, menú `Vista`, metadata/agregados y buscador facetado.
- 49. **RVC-2A — modelo de navegación multivista** — **implementada** (sin cambio de schema ni `DB_VERSION`; sin cambio visual). Definición tipada por vista: clave, label, criterio y `allows_record_creation`. La resolución produce estado activo, targets aditivos y política combinada: activar conserva otros propietarios, desactivar retira solo el propio, `Simple` limpia todos y la prohibición de crear prevalece. `completed` declara `allows_record_creation=false`. El compositor expone `simple_record_view`, enlaces toggle y `records_view_policy`; menú y aplicación visible de la política quedan para RVC-2B. Norma: `docs/plans/canonical-record-view-composition-v0.md`.
- 50. **RVC-2B — presentación y política de vistas** — **implementada** (sin cambio de schema ni `DB_VERSION`). Menú `Vista` con disclosure accesible, `Simple`, toggles aditivos e indicador de selecciones activas; Escape cierra primero el nivel interior. `allows_record_creation` gobierna FAB/formulario de creación sin retirar edición base, imágenes ni acciones de otras capabilities; editar/eliminar la lista permanece en `Simple`. El shell itera metadata neutral y no conoce claves de capability. Norma: `docs/plans/canonical-record-view-composition-v0.md`.
- 51. **RVC-2C — metadata tipada de card** — **implementada** (sin cambio de schema ni `DB_VERSION`). Registry sellado de providers; el composer localiza descriptores UTC genéricos y la card los itera antes de `updated_at`. `completed` aporta `Completada el`; repetir su transición efectiva no reescribe `completed_at`. El shell no conoce su clave ni su semántica.

- PCU-1 no autoriza ni inicia PCU-2.
