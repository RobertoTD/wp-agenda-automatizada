# Solutions canónicas de DEO

**Vigencia:** permanente desde su aprobación documental.

**Autoridad:** norma vinculante para las solutions. Complementa `docs/04-canonical-constitution.md` y `docs/05-canonical-capabilities.md`; no los sustituye.

## 1. Propósito

Una solution declara una experiencia instalable de producto. Coordina las piezas necesarias para una intención reconocible del usuario sin convertir esa coordinación en una nueva persistencia base, una familia especial o una capability artificial.

Esta norma preserva que una configuración útil pueda, en el futuro, consultarse por API, proyectarse en runtime público y empaquetarse sin registros ni credenciales del origen. No autoriza implementar todavía API, exportación/importación, publicación ni ejecución de código arbitrario.

## 2. Términos y fronteras

### Núcleo horizontal

Servicios y contratos comunes: familias, contenedores, registros, identidad, permisos, CRUD base, configuración, coordinación de mutaciones y extensiones. No se denomina «runtime horizontal».

### Shell administrativo

Consumidor privado del núcleo horizontal: navegación, cards, formularios, modales, FAB y CRUD. No conoce la semántica, tablas ni reglas particulares de una solution.

### Runtime público

Consumidor futuro del mismo contrato canónico, accesible por enlace con autorización y operaciones limitadas. No es el shell administrativo sin login.

### Capability

Característica reutilizable, activable y configurable por lista, cuyo significado permanece estable en familias compatibles. Tiene contrato tipado propio de datos, validación, persistencia y proyección. Su norma es `docs/05-canonical-capabilities.md`.

### Solution

Composición instalable que coordina familias, listas, capabilities, relaciones, acciones, vistas o flujos para cumplir una intención de producto. Una solution usa capabilities; no las absorbe, no las redefine y no se registra como capability para aprovechar `container_capabilities`.

Una diferencia exclusivamente visual no es una solution. Una sola propiedad reutilizable tampoco. Cuando una intención exige coordinar varias piezas o una interfaz especializada, se clasifica como solution.

## 3. Invariantes

- Una solution no crea una cuarta tabla base canónica ni duplica el CRUD de contenedores y registros.
- Sus definiciones ejecutables viven en código; la base de datos guarda únicamente estado, configuración y relaciones tipadas.
- Una solution declara sus contextos compatibles, prerrequisitos y lifecycle antes de ofrecerse.
- La configuración de solutions es independiente del repertorio, defaults y selección de capabilities.
- La lista puede ser contexto de aplicación, pero una solution no pertenece semánticamente a una familia ni es propiedad de ella.
- Una capability utilizada por una solution conserva su contrato y puede seguir utilizándose fuera de ella.
- Las relaciones o recursos propios de una solution son tipados, explícitos y con reglas de eliminación y recuperación declaradas.
- El shell integra acciones o vistas particulares mediante contratos de Application y presentación; no conoce claves, tablas o reglas verticales.
- La importación futura solo podrá seleccionar solutions conocidas y sus configuraciones declarativas; nunca ejecutará código arbitrario.

## 4. Solution Contract v0

Una definición de solution debe declarar, como mínimo:

```text
solution_key
version
label
applicable_contexts
application_scope
requirements
capabilities_used
activation_policy
deactivation_policy
lifecycle_policy
```

Cuando aplique, declara además:

```text
relations
resources_created
actions
views
public_projection
```

`applicable_contexts` limita dónde puede ofrecérsela. `application_scope` identifica dónde se guarda su aplicación efectiva. `requirements` expresa prerrequisitos, no side effects implícitos. `lifecycle_policy` declara qué se conserva, retira o bloquea. Ninguna de estas claves admite callbacks, SQL o payload ejecutable persistido.

## 5. Estados de configuración

Una solution atraviesa cuatro capas distintas:

1. **Definición registrada:** contrato de producto sellado en código.
2. **Disponibilidad:** la definición puede ofrecerse en un contexto que cumple compatibilidad y prerrequisitos.
3. **Aplicación efectiva:** estado persistido que indica que está aplicada en un contexto, por ejemplo una lista.
4. **Recursos o instancias:** relaciones, listas creadas u otros recursos tipados que la solution administra.

La aplicación efectiva no se almacena en `aa_canonical_family_capabilities` ni `aa_canonical_container_capabilities`, y no usa el wire `capability_selection_scope` / `capability_selection`.

Una futura interfaz puede mostrar «Opciones» con secciones separadas de «Capacidades» y «Soluciones». Esa separación visual debe reflejar los contratos separados de lectura y mutación.

## 6. Primera solution: `contact_dossier`

`contact_dossier` representa Expediente para una lista de Contactos.

- **Contexto aplicable:** lista de familia `contact`.
- **Ámbito:** aplicación por lista.
- **Prerrequisito:** familia `archive` provisionada y habilitada; la activación se bloquea con explicación cuando falta. No la habilita silenciosamente.
- **Acción:** cada contacto de una lista aplicada puede abrir su expediente.
- **Relación:** como máximo una lista Archivo por registro de contacto en v0.
- **Creación:** diferida al ejecutar la acción; la lectura y SSR no crean recursos.
- **Título inicial:** `Exp — {nombre del contacto}`; es editable y no se resincroniza.
- **Desactivación:** deja de ofrecer la acción, preserva asociaciones y listas Archivo; reactivar las recupera.
- **Límites v0:** no vinculación manual, no múltiples expedientes, no relación universal, no agenda, no exportación/importación operativa y no integración con Expedientes legacy.

### Estado técnico C3

`contact_dossier` dispone de una definición v1 sellada y `ready`. Su application por lista se guarda en la tabla específica `aa_canonical_contact_dossier_applications`; `DB_VERSION=37` retira de forma directa las filas legacy `dossier` de repertorio y asignación de capabilities. La relación `aa_canonical_contact_dossier` permanece como recurso tipado de la solution.

Application distingue explícitamente `applicable`, `ready`, `available`, `active` y `blockers`. El modal «Opciones» tiene wires y secciones distintos para Capacidades y Soluciones. Activar exige una lista de Contactos y Archivo provisionado y habilitado; desactivar continúa permitido si Archivo deja de estar disponible y preserva relaciones y listas Archivo. La acción por contacto, su lectura y apertura/creación ya consultan la application de solution, conservando locks, purge y creación diferida.

La coordinación transaccional de mutaciones de contenedor usa el contrato neutral `CanonicalContainerMutationEffect`. `CanonicalContainerCapabilityEffect` lo extiende por compatibilidad, por lo que una relation de solution no necesita presentarse como capability.

## 7. Decisiones aplazadas

Esta norma no fija todavía un schema **común o polimórfico** de application de solutions, hooks públicos, formatos de paquete, configuración compleja, permisos de terceros, marketplace, API, runtime público ni un motor universal de relaciones o workflows. El registry técnico mínimo y la persistencia específica de `contact_dossier` ya existen; cualquier generalización se extraerá solo ante una segunda necesidad concreta compatible con las mismas invariantes.
