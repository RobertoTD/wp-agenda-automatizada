# Capacidades canónicas de DEO

**Vigencia:** permanente desde su aprobación documental (D0).

**Autoridad:** desarrollo normativo del sistema de capacidades. Complementa `docs/04-canonical-constitution.md` sin sustituirla. La constitución fija los principios generales del canon; este documento fija asignación, configuración, activación, datos y el marco vinculante de `amount` e `images`.

**Regla documental:** no duplicar aquí el modelo contenedor–registro ni el contrato base del shell. No duplicar este paradigma completo en el cheatsheet, el plan ni las reglas de agente: esos archivos solo referencian.

**Estado de implementación:** el paradigma es normativo aunque el código aún no lo implemente. Distinguir siempre:

- **Decisión aceptada** — regla vinculante para el desarrollo posterior.
- **Estado implementado** — lo que existe hoy en el repositorio.
- **Mecanismo técnico pendiente** — diseño o código aún no aprobado o no construido.

Hoy: schema `DB_VERSION=38`; repertorio familiar y selección explícita de capabilities por lista; `amount`, `images`, `phone`, `whatsapp`, `email` y `completed` ready. `completed` está disponible sólo para Acciones y nace activa en listas nuevas; su estado es un recurso tipado, no un criterio de orden. `dossier` ya no es capability ni clave admisible en su repertorio o selección: `contact_dossier` es una solution independiente, documentada en `docs/06-canonical-solutions.md`. Observaciones residuales Continuar/Cerrar/banner conservadas sin PASS. Plan §§10–12. Worker ops antes de producción. El módulo Finance legacy (`module=canonical`, `aa_finance_*`) está **retirado**.

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

### 5.1 Cierre de proyección al desactivar (decisión aceptada)

La desactivación es el retiro total y reversible de la **proyección** de una capability en esa lista; no es sólo ocultar uno de sus controles. Mientras permanezca inactiva, deben cumplirse conjuntamente estas condiciones:

- no admite escrituras nuevas de la capability;
- no aporta campos, valores, acciones de tarjeta, controles, scripts ni representación de sus recursos;
- no aporta filtros, vistas, etiquetas, enlaces ni navegación propios; la lectura base conserva exclusivamente el contrato canónico ordinario;
- una URL que nombre una vista de capability reconocida pero inactiva debe volver de forma segura a la vista base de la lista; una clave de vista desconocida continúa siendo una solicitud inválida;
- los valores y recursos tipados permanecen conservados, y al reactivar recuperan su misma proyección conforme al contrato de la capability.

El shell puede transportar una clave de vista canónica y presentar contribuciones declaradas, pero no puede inferir que una familia posee una capability ni codificar una de sus claves, tablas o semánticas para cumplir estas condiciones. La compatibilidad de una family con una capability es una condición de la capability o de su repertorio; nunca una sustitución de su activación efectiva por lista.

Todo ciclo que introduzca o amplíe una capability debe probar, como mínimo, el recorrido **activa → inactiva → reactivada** sobre una misma lista y verificar estas fronteras antes de considerarse cerrado.

### 5.2 Presentation Contract v0 y puente legacy (decisión aceptada)

El **Capability Presentation Contract v0** es el contrato de proyección para el **Shell administrativo**. El shell ofrece slots comunes; una capability registrada aporta contribuciones declaradas y el shell no bifurca por su clave, tabla, familia ni semántica. El runtime público tendrá un contrato distinto cuando exista.

v0 inicia con acciones de tarjeta, metadata temporal tipada de tarjeta, assets/configuración cliente y las vistas de registros ya registradas. El shell formatea la metadata UTC y solo itera descriptores genéricos: ninguna capability aporta HTML ejecutable. No define todavía campos ni un generador universal de formularios; ese contrato se explora antes de `event_date`. Las contribuciones viven en código registrado: nunca callbacks, paths o HTML ejecutable persistidos.

Existe un **Legacy Presentation Bridge** administrativo y de lista cerrada: `amount`, `phone`, `whatsapp`, `email` e `images`. `completed` usa el slot v0 de acciones, su módulo cliente registrado y el contrato de composición de vistas RVC-1. El bridge solo puede recibir correcciones de bug, seguridad o compatibilidad; está prohibido añadirle una capability, campo, acción, script, label, configuración o comportamiento de producto nuevos. La prueba de frontera protege esta regla.

`contact_dossier` no pertenece a este bridge: es una deuda de presentación de la solution `contact_dossier`, regulada por `docs/06-canonical-solutions.md` y fuera de v0 inicial. El detalle operativo y la secuencia de migración viven en `docs/plans/capability-presentation-contract-v0.md`.

### 5.3 Composición de consulta y vistas (decisión aceptada)

La lectura de registros parte siempre del conjunto canónico del contenedor. Cada capability activa puede aportar un **criterio natural** y vistas que sustituyen exclusivamente ese criterio, sin retirar los criterios de otras capabilities. El shell transporta selecciones registradas y presenta navegación; no conoce claves, tablas, SQL ni semántica particulares.

La vista canónica **Simple** es la base más los criterios naturales activos. Una combinación explícita de vistas es válida cuando todos sus propietarios están registrados y activos y no declaran incompatibilidad. Conteo, listado y paginación deben consumir una misma especificación de consulta; no se permite componer resultados ya paginados.

Las vistas son definiciones tipadas. Cada una declara su clave, label, criterio sustituto y si permite crear registros mientras está seleccionada. Su navegación es aditiva: activar una vista conserva selecciones de otros propietarios; pulsar una vista activa retira solo la propia; **Simple** limpia todas. La política combinada de creación es restrictiva: si alguna vista seleccionada no permite crear, la composición tampoco lo permite. El shell consume el resultado y no bifurca por capability.

La política de creación gobierna solo esa operación: una vista alternativa no retira edición base, imágenes ni acciones pertenecientes a otras capabilities. Cada contribución controla exclusivamente su propia proyección.

Una vista solicitada de capability conocida pero inactiva o no disponible se retira de la URL mediante redirección canónica, conservando el resto del contexto y selecciones válidas. Una selección perteneciente a un paquete ausente también se descarta de forma segura. Transporte malformado o una vista inválida de una capability activa sigue siendo solicitud inválida.

Desactivar o desinstalar una capability conserva por defecto sus datos y schema tipados. La eliminación física es una operación **purge** explícita y separada. El contrato y la secuencia operativa están en `docs/plans/canonical-record-view-composition-v0.md`.

### 5.4 Capability Package v0 (decisión aceptada)

Toda capability nueva entra por un manifiesto PHP sellado y validado: identidad, versión, compatibilidad/default por familia, persistencia tipada, lifecycle, lectura/escritura/permisos, criterios/vistas, contribuciones v0 y combinaciones. No contiene SQL, callbacks, HTML, paths ejecutables ni configuración persistida. Los packages legacy quedan fuera hasta una migración explícita; el detalle operativo está en `docs/plans/canonical-record-view-composition-v0.md`.

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

### 8.2 Alcance funcional

Incluye:

- una cantidad decimal **opcional** por registro;
- campo en formularios de creación y edición del shell cuando la capacidad esté activa en la lista;
- valor renderizado en la tarjeta;
- persistencia asociada al registro canónico;
- positivos, negativos y cero, con precisión y límites explícitos;
- ausencia de valor **distinguible** de cero;
- en **update**: omitir `amount` **conserva** el valor; vaciarlo expresamente **elimina** el valor; cero es válido;
- guardado coherente con el registro y con las reglas canónicas de recencia y eliminación;
- una **proyección derivada de total de lista**: suma de los valores de `amount` de **todos** los registros del contenedor (nunca de la página visible, filtro parcial u otro subconjunto cargado), presentada en la superficie común de detalles de la lista mediante contribución de capability.

Los valores **persistidos** siguen perteneciendo a los registros. El total **no** es una capability nueva, **no** es un campo persistido en el contenedor y **no** convierte el contrato en moneda. Se configura por la activación de `amount` en la lista y es reutilizable en cualquier familia compatible; **no** pertenece a `finance` ni al shell base. El shell no decide el total con condiciones de familia ni conoce las tablas de la capability.

**Activación y presentación del total:**

- solo se ofrece cuando `amount` está **activa** en la lista;
- desactivar `amount` **conserva** los valores de registro existentes y **elimina** su presentación (tarjeta, formularios) y el total de la lista;
- registros **sin** importe no aportan al total;
- lista con `amount` activa y sin importes (o sin registros con valor) muestra total canónico **`0.00`**;
- si el agregado **no** se puede leer: estado de total **no disponible** — no se fabrica `0`, no se omite en silencio y no se presenta un total parcial.

**No incluye:**

- moneda implícita;
- reglas contables o conversiones;
- varios importes nombrados por registro;
- persistencia de un total en el contenedor.

**Historia:** A1b y la redacción D0 de `amount` v1 **excluyeron** la totalización de contenedor (anticipada entonces como responsabilidad separada / CAP-2). Esa exclusión queda **supersedida** por esta decisión: el total de lista es proyección de la capability `amount`, no una capability ni un ciclo CAP separado.

### 8.3 Contrato canónico de `amount` (límites operativos)

Reglas vigentes del normalizador y del almacenamiento tipado (sin referencia a código retirado):

- almacenamiento: `decimal(19,2)` nullable firmado en `aa_canonical_record_amount.amount`;
- normalización (`AA_Canonical_Amount_Normalizer`): cadena o null; vacío → null; longitud máxima de entrada 60; como máximo 2 decimales; como máximo 17 dígitos enteros; `-0` → `0.00`; códigos `invalid_amount`, `amount_too_many_decimals`, `amount_out_of_range`;
- update canónico: **omitir = conservar**; vacío conocido = clear; cero válido;
- total de lista: proyección derivada sobre el conjunto completo de registros del contenedor; precisión decimal **sin** `float`; el mecanismo técnico exacto de agregación y presentación queda para el incremento de implementación (no se fija aquí).

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
- **A1b:** `amount` **`is_ready=true`**; contributors de página + enrich en `build_records_view_data`; estados `known_value` / `known_absent` / `read_failed`; presenters + formulario genérico por clave + módulo JS amount; lifecycle insert-if-missing sin sobrescribir guardados; sin activación masiva de listas existentes. **A1b no implementó** total de lista (excluido en ese ciclo; la norma vigente de proyección de total es posterior — ver §8.2 y decisión del plan).
- Familia `finance` / `archive` en registry; enablement de familia.
- **Total de lista `amount` (decisión 47):** proyección derivada operativa sobre el contenedor completo mediante agregado exacto sin `float`, contributor/presenter de capability y presentación en detalles de lista; incluye `0.00` sin importes y estado visible ante fallo de agregado. Ver §8.2–8.3.
- **LEGACY-X Finance:** módulo clásico retirado (`module=canonical`, `aa_finance_*`, normalizador duplicado). DB 24 dejó de instalar y eliminó esas tablas.
- **DB 25 / selección por lista:** rename repertorio → `aa_canonical_family_capabilities` + `is_default`; wire `capability_selection_scope` / `capability_selection`; `CanonicalContainerCapabilitySelection` + preparer + effect en TX de create/update contenedor; UI de checkboxes en modal de lista (create/edit); edición desde vista records con `return_view=records` y payload `capabilities` en tarjeta; lectura fallida → `unavailable` sin fabricar selección.
- **IMG-0:** paradigma `images` en §12 (docs).
- **IMG-1:** tablas `aa_canonical_record_images` / `aa_canonical_image_upload_operations` / `aa_canonical_purge_runs` (`DB_VERSION=26`); catálogo `images` not-ready sin seeds; **sin** upload/UI/activación de producto.
- **IMG-3a:** `DB_VERSION=27`; columnas `upload_intent` / `upload_objects_json` en ops; helper `AA_Installation_Storage_Usage` (confirmed/reserved/admission); repos suma images + persistencia ops; callers legacy de upload + usage informativo alineados. **Sin** attach canónico / UI / `is_ready`.
- **IMG-3b:** attach canónico Application/AJAX (`UploadCanonicalRecordImageUseCase`, confirmación TX, `aa_attach_canonical_record_image`); sin migración schema; `images` sigue not-ready sin seeds/UI.
- **IMG-4:** lectura por lote + contributor SSR (`known_collection`) + sign-read (`aa_sign_canonical_record_image_read`); `images` sigue not-ready sin seeds/UI/galería.
- **IMG-5 inc. 1:** cliente HMAC `accept`/`seal`/`status` de mandatos; sin schema WP.
- **IMG-5 inc. 2:** `DB_VERSION=28`; corrida durable + inventario congelado + tandas exactas + exclusión de escritores; sin HTTP accept/seal, sin DELETE de producto, sin UI Continuar.
- **IMG-5 inc. 3:** `DB_VERSION=29`; `batch_seq` 0-based persistido; `RetireCanonicalRecordUseCase` + `aa_delete_canonical_record`; accept/seal/status HMAC; cancelación local previa al envío; TX local post-sello. Validación integrada policyytest 2026-09-14 (A/D AJAX PASS; B PASS tras parche authorize HTTP; UI vacío+Cancelar acreditados; Continuar no PASS; C integrado pendiente). Sin `is_ready`, sin worker.
- **IMG-5 inc. 4:** `DB_VERSION=30`; `RetireCanonicalContainerUseCase` + `aa_delete_canonical_container`; seal obligatorio (incl. count=0); chunks keyset post-sello; modal de lista. AC aislados PASS. Policyytest 2026-09-14: blog 61 schema 29→30; AJAX A/B/C PASS; Continuar visual desde listado lista 22 PASS (§7.12); Cerrar/recargar/banner pendientes; lista 21 primer intento no PASS (§7.11).
- **IMG-5 inc. 5:** `DB_VERSION=31`; `RetireCanonicalRecordImageUseCase` + `aa_delete_canonical_record_image`; `scope=image`; captura puntual; seal count=1; TX local solo de la asociación; AC aislados PASS; policyytest AJAX + clics propietario (imagen 5) acreditados; etapa eliminaciones cerrada (plan §9); `is_ready` sigue false (capability no cerrada).

---

## 11. Mecanismos técnicos pendientes (no cerrados en esta norma)

Cerrados en C1a/A1a/A1b + DB 25 (inventario §10 / decisiones 27–31 del plan): schema/config, escritura atómica, lectura/UI amount, ready + seed, repertorio rename, selección explícita por lista.

Quedan abiertos para órdenes posteriores. **No** son arquitectura normativa cerrada aquí:

- ~~aplicación explícita de capacidades a listas existentes vía selección en update~~ (**cerrada** en DB 25 / decisión 31; Ops puntual sigue disponible);
- ~~retirada efectiva de Finance legacy~~ (**cerrada** en LEGACY-X / DB 24);
- ~~paradigma de producto de `images`~~ (**cerrado** normativamente en §12 / IMG-0; diseño físico y ciclos en el plan);
- ~~totalización como responsabilidad separada de `amount`~~ (**supersedida e implementada**: el total de lista es proyección de `amount` — §8.2–8.3 / decisión 47);
- ~~implementación del total de lista de `amount`~~ (**implementada**: agregado completo del contenedor, contribución/presentación en detalles de lista, precisión sin `float`; decisión 47);
- API pública, Settings de capabilities;
- implementación ejecutable restante de `images` (galería/`display`/visor y afines) — plan, no esta norma.

Las alternativas exploradas en sesiones de diseño no obligan al diseño final salvo lo fijado en §12 y en las decisiones del plan.

---

## 12. Capacidad `images` (decisión aceptada — paradigma)

### 12.1 Identidad y configuración

- Clave estable: **`images`**.
- Independiente de Expedientes/Clientes y de cualquier familia concreta; reutilizable mediante el shell.
- Configuración efectiva **por lista** (mismas cuatro capas §2–4.1).
- Label de producto del checkbox en «Campos y funciones»: **«Imágenes»**.
- Repertorio y defaults al crear lista **sin** selección explícita (`family_key` reales del registry):

| `family_key` | Label de producto | En repertorio | `is_default` (crear lista sin selección explícita) |
|---|---|---|---|
| `archive` | Archivo | sí | **1** (marcada) |
| `finance` | Finanzas | sí | **0** (desmarcada) |
| `catalog` | Catálogos | sí | **0** (desmarcada) |
| `contact` | Contactos | sí | **0** (desmarcada) |

- La asignación a familia, el default y la activación efectiva usan el **modelo común** (repertorio + `is_default` + `container_capabilities` + wire `scope`/`selection`). No hay condiciones especiales por familia en el frontend.
- En **editar** lista se muestra la elección **persistida** de esa lista. No reaplicar defaults sobre decisiones guardadas.
- Listas existentes: **sin** cambio retroactivo al introducir o alterar defaults (lifecycle insert-if-missing; nunca UPDATE de `is_default` ya guardado).
- `amount` conserva su comportamiento; ambas pueden coexistir activas en la misma lista.

**Estado implementado (no confundir con la matriz normativa):** catálogo `images` con `is_ready=true` (Paso 5). Seeds en `declared_seeds()` para las cuatro familias; lifecycle insert-if-missing con `DEFAULTS_VERSION=3`. Label «Imágenes»; Paso 2 picker post-save + thumb SSR `summary`. Galería/`display`/visor pendientes. `DB_VERSION=31` sin cambio.

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

La **primera etapa visible** planificada en `docs/plans/canonical-images-retire-wp-integration.md` §11 es un **subconjunto provisional de presentación** hacia esta experiencia (p. ej. representación sencilla de la última imagen confirmada), **sin** cambiar el modelo de almacenamiento ni convertir “una sola imagen por registro” en regla. No sustituye ni reduce esta §12.3.

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

---

## 13. Registro histórico supersedido — `dossier` / «Expediente»

La decisión anterior clasificaba `dossier` como capability exclusiva de Contactos y lo almacenaba en el repertorio y la selección de capabilities. Esa clasificación queda **supersedida**: Expediente es la solution `contact_dossier`, definida normativamente en `docs/06-canonical-solutions.md`.

La transición directa quedó completada en C3 (`DB_VERSION=37`): `dossier` fue retirado del registry, repertorio, defaults, selección, contributors y autoridad de lectura/escritura de capabilities. La relación `aa_canonical_contact_dossier` se conserva como recurso tipado de la solution `contact_dossier`, junto con su application específica por lista. No hay fallback ni doble fuente de verdad. El detalle histórico de la transición vive en `docs/plans/contact-dossier-solution-v0.md`.

La solution conserva una relación 1:1 contacto→lista Archivo, creación diferida al abrir, título inicial `Exp — {nombre del contacto}`, Archivo como prerrequisito no autoactivable, desactivación que conserva datos y ausencia de creación durante lectura/SSR. No se autoriza aquí ampliar a múltiples expedientes, relaciones genéricas, agenda, exportación/importación ni integración con legacy.

---

## 14. `completed` — Acciones (C5)

`completed` es una capability de registro exclusiva de la familia `action`. Su default se materializa activo para listas nuevas de Acciones; la lista conserva después su decisión explícita de activación.

Su recurso es `aa_canonical_record_completion`: una fila con `completed_at` significa completado; la ausencia de fila significa pendiente. `completed_at` conserva la última transición efectiva a completado: repetir “Completar” no la reescribe y volver a completar después de marcar pendiente sí crea una nueva fecha. No es historial, prioridad ni criterio de orden. Completar o devolver a pendiente toca el registro y su lista en la misma transacción para conservar el orden canónico ordinario.

La vista Simple contiene pendientes porque `completed` activa aporta su criterio natural. `capability_views[completed]=completed` sustituye únicamente ese criterio y selecciona completadas antes de contar y paginar; la entrada legacy `records_view=completed` solo se acepta para normalizarla por redirección. Sólo la capability aporta esa semántica: el shell transporta selecciones registradas, navega y presenta el contexto. La UI ofrece `Completar` y la acción reversible `Marcar como pendiente`; en Completadas no se ofrece crear registros.
