# Contact Dossier Solution v0 — Plan de transición

**Status:** historical / retired — `contact_dossier` se retira sin sustitución en Canon libre v2.

**Naturaleza:** antecedente técnico. No es autoridad operativa y no preserva la solution, sus tablas ni sus relaciones.

## Objetivo

Retirar la clasificación de `dossier` como capability y establecer `contact_dossier` como la primera solution de DEO, conservando la relación tipada, creación diferida y garantías de retiro/purga.

## Hechos del estado actual

- La relación tipada ya existe en `aa_canonical_contact_dossier`, servida por `CanonicalContactDossierRepository`.
- La application efectiva usa `aa_canonical_contact_dossier_applications`, `CanonicalContactDossierApplicationRepository` y un efecto transaccional neutral de contenedor.
- La apertura o creación consulta la application de solution; lectura, acción y presentación usan la clave `contact_dossier` bajo `solutions`.
- La implementación usa locks y detecta purgas bloqueantes; estos límites no pueden perderse al migrar.

## Invariantes de migración

- Una sola fuente de verdad efectiva por fase; no mantener capability y solution activas como autoridades permanentes.
- Conservar la tabla relacional `aa_canonical_contact_dossier` y sus listas Archivo; en esta instalación local no hay datos a migrar.
- No crear recursos durante lectura/SSR.
- No habilitar Archivo como efecto oculto.
- Conservación reversible al desactivar: ocultar la acción sin borrar relación ni lista.
- Reutilizar el CRUD y la navegación canónicos; no crear un CRUD paralelo de expedientes.
- Mantener la seguridad, locks, tratamiento de purge y resultados inciertos del flujo actual.
- No generalizar relaciones hasta que una segunda solution demuestre las mismas invariantes.

## Decisión técnica de persistencia

La configuración de aplicación por lista usa un contrato propio. Se evaluaron dos alternativas:

1. Tabla común mínima de aplicaciones de solution por contexto, con identidad tipada, clave de solution y estado de aplicación.
2. Tabla específica de `contact_dossier` que una la aplicación por lista y sus relaciones.

Se eligió la tabla específica `aa_canonical_contact_dossier_applications`, cuya PK/FK es `contact_container_id` y cuyo único estado de producto es `is_active`. No contiene `solution_key`, contexto polimórfico, JSON ni payload ejecutable. Esta decisión conserva una sola fuente de verdad futura sin construir un framework antes de que exista una segunda solution real.

## Ruta propuesta

### S0 — Consolidación documental

Completada por esta decisión: constitución enmendada, norma de solutions creada y `dossier` marcado supersedido en la norma de capabilities. Sin cambio de código, schema ni datos.

### S1 — Propuesta de configuración y registry técnico

Completada: definición inmutable, registry sellado y bootstrap de `contact_dossier` v1 `not-ready`; disponibilidad y aplicación se expresan mediante snapshots y blockers tipados.

**Criterio de aceptación:** `contact_dossier` puede validarse y leerse sin depender de `CanonicalCapabilityConfigRepository` ni de `container_capabilities`.

### S2 — Infraestructura de application de solutions

Completada en C2: schema aditivo DB 36, repositorio específico, lectura/mutación headless y tests MySQL aislados con lifecycle idempotente. No se migró `dossier` ni se cambió UI.

**Criterio de aceptación:** una lista de Contactos puede expresar application efectiva de `contact_dossier`; Archivo es prerrequisito validado; no existe escritura en tablas de capabilities.

### S3 — Lectura, mutación y UI de solutions

Completada en C3: `contact_dossier` está ready; el modal separa «Capacidades» y «Soluciones» y manda `solution_selection_scope`/`solution_selection` en un wire independiente. La escritura queda en la misma transacción del contenedor mediante `CanonicalContainerMutationEffect`.

**Criterio de aceptación:** transportes distintos, estado unavailable fail-closed y ningún checkbox de solution enviado como capability.

### S4 — Transición de autoridad

Completada en C3: contributor, presenter, acción y AJAX consumen `contact_dossier`; se preservan locks, purge y navegación.

**Criterio de aceptación:** una lista migrada abre o crea el mismo expediente mediante `contact_dossier`; no consulta la configuración legacy como autoridad.

### S5 — Retirada del legado de capability

Completada en C3: `dossier` fue retirado del registry, defaults, repertorio, selección y autoridad de lectura/escritura. DB 37 elimina exactamente sus filas de configuración; no hay fallback ni backfill.

**Criterio de aceptación:** `dossier` deja de ser clave válida de capability; la solución es la única autoridad; asociaciones y listas existentes siguen disponibles.

## Riesgos y rollback

- El rollback de código no debe borrar applications de solution ni asociaciones existentes.
- No reducir `DB_VERSION` como mecanismo ordinario de rollback.
- Un fallo o estado incierto al abrir/crear no puede crear una segunda lista Archivo para el mismo contacto.
- La eliminación de una lista Archivo o de un contacto mantiene las reglas actuales hasta que exista una decisión explícita de lifecycle.

## Fuera de alcance

- Citas DEO, Tareas DEO y nuevas familias.
- Motor universal de relaciones, workflows o formularios.
- Runtime público, API, importación/exportación y marketplace.
- Renombrado masivo de infraestructura de Images que todavía conserva nombres históricos de Expedientes.
- Integración con los módulos legacy Clientes/Expedientes.
