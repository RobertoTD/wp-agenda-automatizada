# Capacidades canónicas de DEO

**Vigencia:** permanente desde su aprobación documental (D0).

**Autoridad:** desarrollo normativo del sistema de capacidades. Complementa `docs/04-canonical-constitution.md` sin sustituirla. La constitución fija los principios generales del canon; este documento fija asignación, configuración, activación, datos y el marco vinculante de `amount`.

**Regla documental:** no duplicar aquí el modelo contenedor–registro ni el contrato base del shell. No duplicar este paradigma completo en el cheatsheet, el plan ni las reglas de agente: esos archivos solo referencian.

**Estado de implementación:** el paradigma es normativo aunque el código aún no lo implemente. Distinguir siempre:

- **Decisión aceptada** — regla vinculante para el desarrollo posterior.
- **Estado implementado** — lo que existe hoy en el repositorio.
- **Mecanismo técnico pendiente** — diseño o código aún no aprobado o no construido.

Hoy (tras A1a): schema `DB_VERSION=23`, catálogo con `amount` (`is_ready=false`), configuración C1a, **escritura atómica de valores** (`WriteBag`/handlers/effects), normalizador canónico paralelo y materialización de defaults al crear listas. **No** están lectura/UI ni `is_ready=true` (A1b).

---

## 1. Propósito

Las capacidades expresan características no universales reutilizables sobre la persistencia canónica universal, sin crear registros base propios ni duplicar el CRUD por familia.

El shell consulta capacidades activadas según la configuración efectiva de cada lista. No decide el comportamiento de una capacidad mediante condiciones particulares de familia.

---

## 2. Separación de responsabilidades (decisión aceptada)

Tres responsabilidades distintas:

1. **Definición e implementación** de la capacidad — en código (contrato, validación, persistencia propia cuando corresponda, presentación).
2. **Asignación y configuración** de la capacidad para una lista — persistidas; fuente efectiva de lo que esa lista ofrece.
3. **Valores o recursos** de la capacidad — vinculados a registros, a la lista, o a ambos, según el alcance declarado.

El núcleo canónico conserva identidad, permisos, CRUD base y coordinación. Los módulos de capacidad aportan sus datos, validación, persistencia y presentación mediante puntos de integración definidos. Las reglas particulares de una capacidad no deben dispersarse en el shell.

Las carpetas y módulos deben corresponder a límites de responsabilidad, no solo a separación visual de archivos. La organización concreta de paquetes queda para la propuesta técnica de implementación.

---

## 3. Lista como unidad de configuración (decisión aceptada)

La **lista** (contenedor canónico) es la unidad de asignación y configuración de capacidades.

- La configuración efectiva pertenece a la lista y gobierna los efectos de la capacidad en esa lista y en **todos** sus registros hijos.
- El **alcance declarado** de una capacidad puede ser lista, registro o ambos. Distinguir el alcance de los efectos del lugar donde se configura.
- No hay herencia dinámica permanente desde la familia.
- No hay dependencia de `variant` ni `variant_key` como fundamento del diseño de capacidades.

---

## 4. Defaults de familia (decisión aceptada)

- Los defaults de familia son **configuración persistida en la base de datos**.
- El código puede **inicializarlos**, pero **no debe sobrescribir** ajustes ya guardados.
- Al **crear una lista**, esos defaults se **materializan** en la configuración propia de la lista.
- Cambiar los defaults afecta a **listas nuevas**.
- Aplicar defaults (u otras capacidades) a listas **existentes** requiere una **operación explícita**.

---

## 5. Activación y datos (decisión aceptada)

- La activación es independiente de los datos.
- **Desactivar** una capacidad: conserva sus valores; deja de ofrecer sus controles; **rechaza escrituras** de esa capacidad.
- **Reactivar** recupera el acceso a los valores conservados.
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

### 8.3 Referencia legacy (no contrato canónico)

El módulo Finance legacy (`aa_finance_*`, `FinanceUseCaseSupport::normalize_amount`, UI `module=canonical`) es **referencia de comportamiento y de límites** para la implementación futura:

- almacenamiento legacy: `decimal(19,2)` nullable firmado en `aa_finance_records.amount`;
- normalización: cadena o null; vacío → null; longitud máxima de entrada 60; como máximo 2 decimales; como máximo 17 dígitos enteros; `-0` → `0.00`; códigos `invalid_amount`, `amount_too_many_decimals`, `amount_out_of_range`;
- totales de contenedor (`amount_total`) son **agregados calculados**, no columna; fuera de `amount` v1;
- **contrato de update legacy distinto del canónico:** en Finance la clave `amount` es obligatoria en update (`missing_amount` si falta); vacío limpia. El canónico usa **omitir = conservar**.

La capacidad canónica `amount` **no** debe depender de controladores, tablas base ni inicialización exclusivos del módulo legacy. Finanzas puede incluir `amount` por defecto en sus listas nuevas, pero **no es propietaria** de la capacidad.

---

## 9. Secuencia de desarrollo en paralelo (decisión aceptada)

Orden vinculante de etapas de producto:

1. **Documentación del paradigma** (D0) — este documento y referencias; **completada** al aprobarse este texto.
2. **Capacidad canónica `amount`** integrada en el shell y validada (núcleo de capacidades + `amount` v1).
3. **Transición de datos** (si se aprueba) y **retirada de Finanzas legacy**, con higiene de datos, referencias y consumidores.
4. **Etapa de imágenes** (posterior; CAP histórico de imágenes).

Shell visual y CRUD base pueden seguir evolucionando en paralelo mientras no contradigan este paradigma.

### 9.1 Retirada de legacy (marco)

La retirada debe contemplar datos, referencias y consumidores (navegación, configuración, otros módulos, helpers compartidos).

Diferenciar operaciones:

- **deshabilitar accesos** (p. ej. dejar de ofrecer la UI clásica);
- **retirar código**;
- **eliminar tablas o datos**.

La eliminación de tablas o datos **no** es consecuencia automática de disponer de la UI canónica nueva. No eliminar datos solo porque la nueva UI los sustituya visualmente.

Queda **abierta** la futura evaluación de reutilización de galería, resize y helpers de Storage de Expedientes para la etapa de imágenes; no se diseña aquí esa capacidad.

### 9.2 Rollback (marco)

Al planificar implementaciones posteriores, separar:

- rollback de **código**;
- rollback o restauración de **configuración**;
- conservación o eliminación deliberada de **datos**.

No presentar bajar `DB_VERSION` como procedimiento ordinario de rollback. La idempotencia de una migración **no** garantiza reversibilidad de datos.

---

## 10. Estado implementado (inventario breve)

Hechos del repositorio tras A1a (no sustituyen el paradigma):

- Persistencia universal `aa_canonical_*` con CRUD de `title` / `details` en el shell.
- **C1a:** tablas de defaults/configuración/`record_amount`; catálogo con `amount` no ready; config Use Cases + Ops.
- **A1a:** `CanonicalCapabilityWriteBag` + handlers/effects neutrales; `CanonicalRecordAmountRepository`; TX registro+efectos+touch; materialización atómica al crear listas; `AA_Canonical_Amount_Normalizer` en paralelo a Finance (sin delegación legacy). `amount` sigue `is_ready=false` en producto.
- Familia `finance` / `archive` en registry; enablement de familia.
- Finance legacy operativo en paralelo (`module=canonical`, `aa_finance_*`); duplicación temporal del normalizador hasta retirada del legacy tras amount operativo en A1b (antes de imágenes).
- Pendiente **A1b:** lectura/UI de `amount` y marcar `is_ready=true` (detalles de presentación siguen abiertos).

---

## 11. Mecanismos técnicos pendientes (no cerrados en esta norma)

Cerrados en C1a/A1a (inventario §10 / decisiones 27–28 del plan): schema/config, escritura atómica de `amount`, materialización al crear listas, normalizador canónico.

Quedan abiertos para A1b u órdenes posteriores. **No** son arquitectura normativa cerrada aquí:

- puntos mínimos de extensión de lectura y UI (A1b; sin fijar aquí semántica SSR ni compositor);
- operación explícita de aplicación de capacidades a listas existentes;
- retirada efectiva de Finance legacy (tras A1b; sin migración de datos vaciados).

Las alternativas exploradas en sesiones de diseño no obligan al diseño final.
