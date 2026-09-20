# Contact Dossier Solution v0 — Plan de transición

**Status:** proposed.

**Naturaleza:** plan temporal de exploración e implementación. No sustituye `docs/04-canonical-constitution.md`, `docs/05-canonical-capabilities.md` ni `docs/06-canonical-solutions.md`.

## Objetivo

Retirar gradualmente la clasificación de `dossier` como capability y establecer `contact_dossier` como la primera solution de DEO, sin perder asociaciones existentes, listas Archivo, comportamiento de creación diferida ni garantías de retiro/purga.

## Hechos del estado actual

- `dossier` está registrado como capability ready de alcance record en `includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php`.
- El repertorio/default de Contactos se inicializa en `includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php`.
- La selección efectiva usa `aa_canonical_container_capabilities` y `CanonicalCapabilityConfigRepository`.
- La relación tipada ya existe en `aa_canonical_contact_dossier`, servida por `CanonicalContactDossierRepository`.
- La apertura o creación se orquesta en `OpenOrCreateCanonicalContactDossierUseCase`; la relación se inserta como efecto transaccional.
- Lectura, acción y presentación dependen de `CanonicalDossierRecordsPageContributor`, su bootstrap, presenter, partial de card, JavaScript y `CanonicalOpenContactDossierAjax`.
- La implementación usa locks y detecta purgas bloqueantes; estos límites no pueden perderse al migrar.

## Invariantes de migración

- Una sola fuente de verdad efectiva por fase; no mantener capability y solution activas como autoridades permanentes.
- Conservar todas las filas actuales de `aa_canonical_contact_dossier` y sus listas Archivo.
- No crear recursos durante lectura/SSR.
- No habilitar Archivo como efecto oculto.
- Conservación reversible al desactivar: ocultar la acción sin borrar relación ni lista.
- Reutilizar el CRUD y la navegación canónicos; no crear un CRUD paralelo de expedientes.
- Mantener la seguridad, locks, tratamiento de purge y resultados inciertos del flujo actual.
- No generalizar relaciones hasta que una segunda solution demuestre las mismas invariantes.

## Pregunta técnica que debe resolverse antes de schema

La configuración de aplicación por lista necesita un contrato propio. Se evaluarán dos alternativas:

1. Tabla común mínima de aplicaciones de solution por contexto, con identidad tipada, clave de solution y estado de aplicación.
2. Tabla específica de `contact_dossier` que una la aplicación por lista y sus relaciones.

La decisión deberá privilegiar una sola fuente de verdad, configuración declarativa, facilidad de lectura y una segunda solution real; no se construirá un framework o JSON genérico solo por anticipación.

## Ruta propuesta

### S0 — Consolidación documental

Completada por esta decisión: constitución enmendada, norma de solutions creada y `dossier` marcado supersedido en la norma de capabilities. Sin cambio de código, schema ni datos.

### S1 — Propuesta de configuración y registry técnico

Explorar el modelo mínimo de definición registrada, disponibilidad y aplicación por lista. Determinar puertos, snapshots, errores y composición sin modificar código todavía.

**Criterio de aceptación:** `contact_dossier` puede validarse y leerse sin depender de `CanonicalCapabilityConfigRepository` ni de `container_capabilities`.

### S2 — Infraestructura de application de solutions

Implementar schema aditivo y repositorio/configuración para applications de solutions, con tests MySQL aislados y lifecycle idempotente. No migrar todavía `dossier` ni cambiar UI.

**Criterio de aceptación:** una lista de Contactos puede expresar application efectiva de `contact_dossier`; Archivo es prerrequisito validado; no existe escritura en tablas de capabilities.

### S3 — Lectura, mutación y UI de solutions

Implementar contratos de Application para leer/modificar la application de solution y adaptar el modal de lista para separar «Capacidades» de «Soluciones». Mantener el flujo histórico de `dossier` como compatibilidad mientras no exista backfill.

**Criterio de aceptación:** transportes distintos, estado unavailable fail-closed y ningún checkbox de solution enviado como capability.

### S4 — Migración de `dossier`

Backfill idempotente de listas con configuración histórica de `dossier`; elegir una fuente de verdad de transición; adaptar contributor, presenter, acción y AJAX para consumir la solution; preservar asociación, locks, purge y navegación.

**Criterio de aceptación:** una lista migrada abre o crea el mismo expediente mediante `contact_dossier`; no consulta la configuración legacy como autoridad.

### S5 — Retirada del legado de capability

Retirar `dossier` del registry, defaults, repertorio, selección, handlers/contributors y UI de capabilities. Eliminar configuración legacy únicamente después de comprobar la migración y de retirar el fallback.

**Criterio de aceptación:** `dossier` deja de ser clave válida de capability; la solución es la única autoridad; asociaciones y listas existentes siguen disponibles.

## Riesgos y rollback

- El rollback de código no debe borrar aplicaciones de solution ni asociaciones existentes.
- No reducir `DB_VERSION` como mecanismo ordinario de rollback.
- Antes de retirar fallback, toda lista con configuración legacy debe estar migrada o marcada explícitamente para recuperación.
- Un fallo o estado incierto al abrir/crear no puede crear una segunda lista Archivo para el mismo contacto.
- La eliminación de una lista Archivo o de un contacto mantiene las reglas actuales hasta que exista una decisión explícita de lifecycle.

## Fuera de alcance

- Citas DEO, Tareas DEO y nuevas familias.
- Motor universal de relaciones, workflows o formularios.
- Runtime público, API, importación/exportación y marketplace.
- Renombrado masivo de infraestructura de Images que todavía conserva nombres históricos de Expedientes.
- Integración con los módulos legacy Clientes/Expedientes.
