# Capacidades canónicas de DEO

**Vigencia:** permanente desde su aprobación documental (D0).

**Autoridad:** desarrollo normativo del sistema de capacidades. Complementa `docs/04-canonical-constitution.md` sin sustituirla. La constitución fija los principios generales del canon; este documento fija asignación, configuración, activación, datos y el marco vinculante de `amount` e `images`.

**Regla documental:** no duplicar aquí el modelo contenedor–registro ni el contrato base del shell. No duplicar este paradigma completo en el cheatsheet, el plan ni las reglas de agente: esos archivos solo referencian.

**Estado de implementación:** el paradigma es normativo aunque el código aún no lo implemente. Distinguir siempre:

- **Decisión aceptada** — regla vinculante para el desarrollo posterior.
- **Estado implementado** — lo que existe hoy en el repositorio.
- **Mecanismo técnico pendiente** — diseño o código aún no aprobado o no construido.

Hoy (tras IMG-5 inc. 3 / DB 29): schema `DB_VERSION=29`; repertorio familiar en `aa_canonical_family_capabilities`; selección explícita de capacidades por lista en create/update del shell; familia canónica `finance` y capability `amount` (`is_ready=true`) sobre `aa_canonical_*`; escritura/lectura/UI amount operativas; único normalizador `AA_Canonical_Amount_Normalizer`; tablas físicas `images`/ops/purge + inventario de captura de retiro; helper de consumo de instalación; attach Application/AJAX canónico (`aa_attach_canonical_record_image`) implementado; cliente HMAC de mandatos + captura durable de purge (IMG-5 inc. 1–2); retiro de un registro vía mandatos (`aa_delete_canonical_record` → `RetireCanonicalRecordUseCase`); capability `images` **registrada `is_ready=false`** (sin seeds ni UI de producto). Validación integrada policyytest 2026-09-14: retiro vacío y cancelación por conflicto **PASS** por AJAX; attach HTTP `canonical_v1` + retiro con imagen **PASS** tras parche de `authorize-upload` (camino de desarrollo acotado); UI Eliminar vacío + Cancelar conflicto acreditados (Continuar no PASS); ver `docs/plans/canonical-images-retire-wp-integration.md` §6.7. El módulo Finance legacy (`module=canonical`, `aa_finance_*`) está **retirado**.

---

## 1. Propósito

Las capacidades expresan características no universales reutilizables sobre la persistencia canónica universal, sin crear registros base propios ni duplicar el CRUD por familia.

El shell consulta capacidades activadas según la configuración efectiva de cada lista. No decide el comportamiento de una capacidad mediante condiciones particulares de familia.

---

## 2. Cuatro capas (decisión aceptada)

Cuatro capas distintas; no confundirlas:

1. **Repertorio y defaults de familia** — filas en `aa_canonical_family_capabilities`: la **presencia** de la fila = capacidad en el repertorio de esa familia; `is_default` = predeterminada para materializar en listas nuevas. El código puede **inicializar** filas ausentes; **no** debe sobrescribir `is_default` ya guardado.
2. **Solicitud de modificación** (wire `scope` + `selection`) — contrato de transporte/Application para **cambiar** la configuración de una lista. No es el setup completo persistido. Ausencia de instancia = omisión; instancia presente (incluso con arrays vacíos) = modificación explícita.
3. **Setup persistido de la lista** — filas en `aa_canonical_container_capabilities` leídas como `CanonicalContainerCapabilityConfigSnapshot` (`ReadContainerCapabilityConfigUseCase`): asignación y activación efectivas de esa lista, **incluida** cualquier capacidad conocida ya asignada aunque ya no esté en el repertorio familiar.
4. **Valores o recursos** de la capacidad — vinculados a registros, a la lista, o a ambos según el alcance declarado (p. ej. `aa_canonical_record_amount`). Separados de la activación en la lista.

La **definición e implementación** de cada capacidad permanece en código (contrato, validación, persistencia tipada, presentación). El núcleo canónico conserva identidad, permisos, CRUD base y coordinación. Las reglas particulares de una capacidad no deben dispersarse en el shell.

Las carpetas y módulos deben corresponder a límites de responsabilidad, no solo a separación visual de archivos.

---

## 3. Lista como unidad de configuración (decisión aceptada)

La **lista** (contenedor canónico) es la unidad de asignación y configuración de capacidades.

- La configuración efectiva pertenece a la lista y gobierna los efectos de la capacidad en esa lista y en **todos** sus registros hijos.
- El **alcance declarado** de una capacidad puede ser lista, registro o ambos. Distinguir el alcance de los efectos del lugar donde se configura.
- No hay herencia dinámica permanente desde la familia.
- No hay dependencia de `variant` ni `variant_key` como fundamento del diseño de capacidades.

---

## 4. Repertorio y defaults de familia (decisión aceptada)

Tabla: `aa_canonical_family_capabilities` (antes `aa_canonical_family_capability_defaults`; columna `is_enabled` → `is_default` en `DB_VERSION=25`).

- Fila presente = capacidad en el **repertorio** de la familia.
- `is_default=1` = predeterminada: al **crear una lista sin** solicitud de modificación explícita, el materializador copia solo repertorio **ready + is_default** a `container_capabilities`.
- Cambiar defaults afecta a **listas nuevas** (u operación explícita sobre existentes).
- Aplicar defaults (u otras capacidades) a listas **existentes** requiere una **operación explícita** (selección en update, Ops, etc.).

---

## 4.1 Solicitud de modificación scope+selection (decisión aceptada)

Contrato aprobado (transporte AJAX / Use Case de contenedor):

- **Ambos ausentes** → omisión (`null`): no hay modificación de capacidades en esa petición.
- **Exactamente uno presente** → inválido (`invalid_payload`).
- **Ambos presentes** → cada uno es un array JSON de claves (listas; vacíos permitidos). Debe cumplirse **selection ⊆ scope**.
- Solo se modifican claves de **scope**. Nunca se reasigna en silencio el scope al repertorio actual.
- **Create:** cada clave de scope debe ser known + ready y estar en el **repertorio actual** de la familia.
- **Update:** cada clave de scope debe ser known + ready y (estar en el repertorio **o** ya asignada a esa lista).
- **Create con selección explícita** (incluso `scope=[]` / `selection=[]`) **sustituye** al materializador: no se copian defaults.
- **Create omitiendo** ambos campos → usa el materializador de defaults.
- **Update omitiendo** → conserva la configuración de capacidades de la lista.
- **Desactivar** (clave en scope y ausente de selection) conserva los **valores** de registro; reactivar los recupera.
- Si la lectura del setup de la lista falla, la UI **no** debe fabricar selección vacía ni defaults: estado `unavailable` y omisión de campos de selección en el envío.

La solicitud no es un snapshot del setup completo; el snapshot es la capa 3.

---

## 5. Activación y datos (decisión aceptada)

- La activación es independiente de los datos.
- **Desactivar** una capacidad: conserva sus valores (y, cuando existan, recursos asociados y su consumo); deja de ofrecer sus controles y representación; **rechaza nuevas escrituras** de esa capacidad. Una operación de escritura ya admitida bajo reglas propias de la capacidad puede completarse según su contrato (p. ej. `images`); eso no autoriza nuevas admisiones mientras esté inactiva.
- **Reactivar** recupera el acceso a los valores y recursos conservados.
- Editar los datos base del registro (`title`, `details`) mientras la capacidad está desactivada **no debe borrar** sus valores.
- La activación y la configuración pasan por un mecanismo común validado en servidor. Inicialmente lo utiliza el desarrollador; posteriormente podrá exponerse a usuarios autorizados **sin cambiar** el modelo de persistencia.
- La configuración enviada por el navegador no sustituye permisos ni comprobaciones sobre la lista real del registro.

Las capacidades declaran su alcance y, cuando corresponda, dependencias e incompatibilidades. Se valida la configuración resultante antes de guardarla. No se construye por anticipado un motor complejo de reglas.

---

## 6. Datos tipados y formas de persistencia (decisión aceptada)

Cada capacidad exige **estructura y validación explícitas** para sus datos.

- No se admite JSON genérico, EAV ni payloads arbitrarios en las tablas base canónicas como sustituto de persistencia tipada (ver constitución).
- **No** se convierte en regla universal “una capacidad equivale a un campo y una tabla”, ni un constructor general de campos personalizados.
- Una capacidad compleja (p. ej. imágenes) **no** queda exenta de datos tipados: su forma de persistencia puede diferir de un escalar, pero sigue siendo contrato tipado y validado.
- Para **`amount`**, se acuerda persistencia **decimal tipada** en una extensión vinculada al **registro** canónico (`aa_canonical_record_amount`; escritura A1a).

El núcleo no obliga a que todas las capacidades se comporten como un campo escalar ni a guardar archivos externos dentro de una transacción local. Almacenamiento, procesamiento, cuotas o galerías de imágenes **no** forman parte de esta norma de `amount`.

---

## 7. Claves estables, exportación futura y límites (decisión aceptada)

- Claves estables de capacidad (p. ej. `amount`).
- Configuración separable del contenido de registros.
- Datos estructurados independientes del HTML.
- Una configuración importada (futuro) solo selecciona capacidades **conocidas**; no ejecuta código arbitrario.
- Futuros paquetes compartidos deben poder aislarse del origen, sin credenciales ni datos ajenos a la selección.

**No autorizado ahora:** API, importación/exportación operativa, publicación, runtime público, ni implementación anticipada de ese futuro más allá de preservar la barrera arquitectónica de la constitución.

---

## 8. Capacidad `amount` (decisión aceptada)

### 8.1 Identidad

La clave estable aprobada es **`amount`**.

La denominación planaria histórica **`monetary_amount`** queda **supersedida** como nombre normativo. No se introduce un alias obligatorio de compatibilidad sin una necesidad real demostrada.

### 8.2 Alcance funcional v1

Incluye:

- una cantidad decimal **opcional** por registro;
- campo en formularios de creación y edición del shell cuando la capacidad esté activa en la lista;
- valor renderizado en la tarjeta;
- persistencia asociada al registro canónico;
- positivos, negativos y cero, con precisión y límites explícitos;
- ausencia de valor **distinguible** de cero;
- en **update**: omitir `amount` **conserva** el valor; vaciarlo expresamente **elimina** el valor; cero es válido;
- guardado coherente con el registro y con las reglas canónicas de recencia y eliminación.

**No incluye** en v1:

- totalización en el contenedor;
- moneda implícita;
- reglas contables o conversiones;
- varios importes nombrados por registro.

Una futura totalización será responsabilidad separada (históricamente anticipada en el plan como agregado monetario / CAP-2; no forma parte de `amount` v1).

### 8.3 Contrato canónico de `amount` (límites operativos)

Reglas vigentes del normalizador y del almacenamiento tipado (sin referencia a código retirado):

- almacenamiento: `decimal(19,2)` nullable firmado en `aa_canonical_record_amount.amount`;
- normalización (`AA_Canonical_Amount_Normalizer`): cadena o null; vacío → null; longitud máxima de entrada 60; como máximo 2 decimales; como máximo 17 dígitos enteros; `-0` → `0.00`; códigos `invalid_amount`, `amount_too_many_decimals`, `amount_out_of_range`;
- update canónico: **omitir = conservar**; vacío conocido = clear; cero válido;
- totales de contenedor quedan fuera de `amount` v1.

La familia `finance` puede materializar `amount` por defecto en listas nuevas; **no es propietaria** de la capacidad.

---

## 9. Secuencia de desarrollo en paralelo (decisión aceptada)

Orden vinculante de etapas de producto:

1. **Documentación del paradigma** (D0) — este documento y referencias; **completada**.
2. **Capacidad canónica `amount`** integrada en el shell y validada; **completada** (C1a–A1b).
3. **Retirada de Finanzas legacy**; **completada** (LEGACY-X / `DB_VERSION=24`). Sin migración de datos (ya vaciados).
4. **Etapa de imágenes** — paradigma normativo en §12 (**IMG-0** documental); implementación por ciclos en el plan. Producto **no activable** hasta completar el recorrido previsto.

Shell visual y CRUD base pueden seguir evolucionando en paralelo mientras no contradigan este paradigma.

### 9.1 Retirada de Finance (cerrada)

LEGACY-X retiró accesos, código y tablas `aa_finance_*` en el mecanismo versionado. La familia canónica `finance` y `amount` permanecen. Detalle histórico en Git y en la decisión 30 del plan.

La reutilización o copia de piezas de Expedientes (galería, resize, Storage) para `images` es decisión de implementación en el plan; no fija el contrato canónico ni obliga a conservar paths o identidades legacy.

### 9.2 Rollback (marco)

Al planificar implementaciones posteriores, separar:

- rollback de **código**;
- rollback o restauración de **configuración**;
- conservación o eliminación deliberada de **datos**.

No presentar bajar `DB_VERSION` como procedimiento ordinario de rollback. La idempotencia de una migración **no** garantiza reversibilidad de datos.

---

## 10. Estado implementado (inventario breve)

Hechos del repositorio tras A1b + LEGACY-X Finance + selección por lista (no sustituyen el paradigma):

- Persistencia universal `aa_canonical_*` (`DB_VERSION=25`) con CRUD de `title` / `details` en el shell.
- **C1a:** tablas de configuración/`record_amount`; config Use Cases + Ops. (Nombre histórico de repertorio: `aa_canonical_family_capability_defaults`.)
- **A1a:** `CanonicalCapabilityWriteBag` + handlers/effects; `CanonicalRecordAmountRepository`; TX registro+efectos+touch; materialización al crear listas; normalizador canónico.
- **A1b:** `amount` **`is_ready=true`**; contributors de página + enrich en `build_records_view_data`; estados `known_value` / `known_absent` / `read_failed`; presenters + formulario genérico por clave + módulo JS amount; lifecycle insert-if-missing sin sobrescribir guardados; sin activación masiva de listas existentes.
- Familia `finance` / `archive` en registry; enablement de familia.
- **LEGACY-X Finance:** módulo clásico retirado (`module=canonical`, `aa_finance_*`, normalizador duplicado). DB 24 dejó de instalar y eliminó esas tablas.
- **DB 25 / selección por lista:** rename repertorio → `aa_canonical_family_capabilities` + `is_default`; wire `capability_selection_scope` / `capability_selection`; `CanonicalContainerCapabilitySelection` + preparer + effect en TX de create/update contenedor; UI de checkboxes en modal de lista (create/edit); edición desde vista records con `return_view=records` y payload `capabilities` en tarjeta; lectura fallida → `unavailable` sin fabricar selección.
- **IMG-0:** paradigma `images` en §12 (docs).
- **IMG-1:** tablas `aa_canonical_record_images` / `aa_canonical_image_upload_operations` / `aa_canonical_purge_runs` (`DB_VERSION=26`); catálogo `images` not-ready sin seeds; **sin** upload/UI/activación de producto.
- **IMG-3a:** `DB_VERSION=27`; columnas `upload_intent` / `upload_objects_json` en ops; helper `AA_Installation_Storage_Usage` (confirmed/reserved/admission); repos suma images + persistencia ops; callers legacy de upload + usage informativo alineados. **Sin** attach canónico / UI / `is_ready`.
- **IMG-3b:** attach canónico Application/AJAX (`UploadCanonicalRecordImageUseCase`, confirmación TX, `aa_attach_canonical_record_image`); sin migración schema; `images` sigue not-ready sin seeds/UI.
- **IMG-4:** lectura por lote + contributor SSR (`known_collection`) + sign-read (`aa_sign_canonical_record_image_read`); `images` sigue not-ready sin seeds/UI/galería.
- **IMG-5 inc. 1:** cliente HMAC `accept`/`seal`/`status` de mandatos; sin schema WP.
- **IMG-5 inc. 2:** `DB_VERSION=28`; corrida durable + inventario congelado + tandas exactas + exclusión de escritores; sin HTTP accept/seal, sin DELETE de producto, sin UI Continuar.
- **IMG-5 inc. 3:** `DB_VERSION=29`; `batch_seq` 0-based persistido; `RetireCanonicalRecordUseCase` + `aa_delete_canonical_record`; accept/seal/status HMAC; cancelación local previa al envío; TX local post-sello. Validación integrada policyytest 2026-09-14 (A/D AJAX PASS; B PASS tras parche authorize HTTP; UI vacío+Cancelar acreditados; Continuar no PASS; C integrado pendiente). Sin retiro de contenedores, sin `is_ready`, sin worker.

---

## 11. Mecanismos técnicos pendientes (no cerrados en esta norma)

Cerrados en C1a/A1a/A1b + DB 25 (inventario §10 / decisiones 27–31 del plan): schema/config, escritura atómica, lectura/UI amount, ready + seed, repertorio rename, selección explícita por lista.

Quedan abiertos para órdenes posteriores. **No** son arquitectura normativa cerrada aquí:

- ~~aplicación explícita de capacidades a listas existentes vía selección en update~~ (**cerrada** en DB 25 / decisión 31; Ops puntual sigue disponible);
- ~~retirada efectiva de Finance legacy~~ (**cerrada** en LEGACY-X / DB 24);
- ~~paradigma de producto de `images`~~ (**cerrado** normativamente en §12 / IMG-0; diseño físico y ciclos en el plan);
- totalización, API pública, Settings de capabilities;
- implementación ejecutable de `images` (schema, backend path, UI, activación) — plan, no esta norma.

Las alternativas exploradas en sesiones de diseño no obligan al diseño final salvo lo fijado en §12 y en las decisiones del plan.

---

## 12. Capacidad `images` (decisión aceptada — paradigma)

### 12.1 Identidad y configuración

- Clave estable: **`images`**.
- Independiente de Expedientes/Clientes y de cualquier familia concreta; reutilizable mediante el shell.
- Configuración efectiva **por lista** (mismas cuatro capas §2–4.1).
- Repertorio: presente en **`finance`** y **`archive`**.
- Defaults al crear lista **sin** selección explícita: `finance` → **desactivada**; `archive` → **activada**.
- Listas existentes: **sin** cambio retroactivo al introducir o alterar defaults.
- `amount` conserva su comportamiento; ambas pueden coexistir activas en la misma lista.

### 12.2 Lectura, subida y conservación

- **Leer** imágenes exige autorización de acceso al registro (y a la instalación); **no** exige beneficio de almacenamiento ni suscripción.
- **Subir** conserva los beneficios y cuotas comerciales vigentes (`expediente_storage`: free sin derecho de subida; freemium/pro según política efectiva; degradación Pro→freemium según lógica ya existente). No se rediseña el catálogo de beneficios en esta norma.
- **Desactivar** `images` en una lista: conserva archivos, metadatos, vínculos y consumo de cuota; deja de ofrecer operaciones y representación; rechaza **nuevas** subidas y **nuevas** firmas de lectura. Una URL de lectura ya emitida puede seguir funcionando hasta su vencimiento (no hay revocación instantánea de permisos Storage). **Reactivar** recupera la funcionalidad y las imágenes conservadas.
- Cambiar el checkbox de selección **no** exige suscripción; subir sí exige beneficio disponible y capacidad según la regla de cuota.
- No hay borrado implícito por desactivación, cambio de plan o abandono de cuenta.

La conservación reversible al desactivar es el criterio general de capabilities (§5), aplicado también a recursos no escalares.

### 12.3 Experiencia de la primera entrega (producto)

- Una imagen por selección de archivo; varias imágenes **acumulables** por registro en guardados sucesivos.
- Selector y preview en los modales canónicos de crear y editar registro.
- Guardar primero el registro (campos base y otras capabilities del mismo POST); **después** adjuntar.
- Si falla la imagen: el registro y sus campos **se conservan**; se informa y se permite **reintentar solo la imagen**.
- Galería: miniatura en cabecera, imagen principal en panel expandido, tira/contador cuando corresponda, visor ampliado; selección/orden de sesión como en el legado útil, **sin** portada ni orden persistidos en v1.
- No copiar contratos base del legacy (p. ej. `details` sigue opcional en el canon).
- **Pospuesto:** selección múltiple en una operación; añadir imágenes desde la galería.

### 12.4 Borrado deliberado

- Eliminar una imagen elimina sus objetos asociados.
- Eliminar un registro elimina todas sus imágenes.
- Eliminar una lista elimina sus registros y todas sus imágenes.
- Lo anterior aplica **aunque** `images` esté desactivada en la lista.
- La confirmación debe mencionar que también se eliminarán las imágenes.
- Si el proceso queda a medias: informar **«Eliminación incompleta»** y permitir **continuar**; no afirmar que no ocurrió nada si ya hubo eliminaciones parciales.

### 12.5 Desarrollo, activación y retirada

- Desarrollo **en paralelo** al legado de adjuntos de Expedientes; se acepta duplicación temporal.
- Retirada del legado de imágenes **después** de consolidar el canon; **sin** requisito de migrar ni conservar registros legacy de adjuntos.
- Retirar imágenes legacy **no** implica por sí retirar Clientes/Expedientes.
- **Producto no activable** (`is_ready` / seeds de repertorio que materialicen en listas reales) hasta completar el recorrido de implementación previsto en el plan.
- No usar `is_ready=true` sin seeds como sustituto de “bloqueada”.

### 12.6 Qué no fija esta norma

El detalle físico (nombres de tablas, estados de operación, fingerprints, paths Storage, tandas de purge, cálculo exacto de reserva) es **diseño de implementación** registrado en `docs/plans/shell-canonical-base-v1.md` (decisión IMG / Ciclo 1). Ese diseño debe respetar §12; no se eleva aquí a obligación constitucional ni a DDL ejecutado.
