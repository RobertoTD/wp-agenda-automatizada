# Fuente comun persistida para Listas/Tareas (MC13O-A)

Este documento define el modelo objetivo antes de tocar schema o persistencia.
No implementa runtime, tablas ni policies. Su objetivo es alinear la siguiente
evolucion: que el feed oficial deje de nutrirse de dos pipelines heterogeneos
(Tasks desde BD y Learning desde catalogo) y pase a leer listas/tareas desde un
modelo comun persistido.

## Principios

- No se crea una tabla paralela redundante de listas/tareas.
- La base comun objetivo es la estructura actual de Tasks evolucionada:
  `aa_task_lists`, `aa_tasks`, `aa_task_state`.
- Solo se agregan tablas relacionadas si la informacion es compleja, opcional
  o no universal, por ejemplo acciones ricas o metadata de facts.
- Learning/Recomendaciones puede seguir existiendo como catalogo en codigo, pero
  su rol objetivo es sembrar/sincronizar definiciones en la BD comun.
- La procedencia es dato (`source_category`, `source_label`, `managed_by`), no
  un pipeline separado.
- Los adapters especificos sobreviven solo donde hay ejecucion realmente
  especifica: PWA, navegacion a modulos, evaluacion de facts, sync catalogo -> DB.

## Estado actual

Hoy el feed unified es una unificacion de contrato/render, no una unificacion
estructural persistida.

| Fuente | Definicion | Estado usuario | Feed |
|--------|------------|----------------|------|
| Tasks user | `aa_task_lists`, `aa_tasks` | `aa_task_state` + `aa_tasks.status/completed_at` | `TaskBoardToExecutableMapper` |
| Learning / Agenda app | `AA_Learning_Catalog` en codigo | `aa_learning_recommendation_state` | `LearningRecommendationsToExecutableMapper` |

`GetExecutableListsFeedUseCase` concatena proyecciones al final. MC13O-A no
cambia ese comportamiento; documenta el destino para los siguientes microciclos.

## Cinco capas del modelo objetivo

### A. Definicion base de lista/tarea

Representa que es la lista o tarea. Debe vivir en `aa_task_lists`, `aa_tasks` y,
para items sembrados, en un catalogo de codigo usado solo como fuente de sync.

Ejemplos de datos:

- `title`, `description`
- `source_category`, `source_label`
- `origin_key`
- `managed_by`
- `importance`, `position`
- `default_bucket`
- `completion_type`
- `completion_fact_key`
- permisos o capabilities base si aplica

Ejemplos:

| Lista | Datos objetivo |
|-------|----------------|
| User | `source_category=user`, `source_label=Mis listas`, `managed_by=user` |
| Agenda app | `source_category=agenda_app`, `source_label=Agenda app`, `managed_by=developer`, `origin_key` estable, `can_edit=false`, `can_archive=false`, `can_delete=false` |
| IA futura | `source_category=ai`, `source_label=IA`, `managed_by=ai` o `system` segun decision futura |

### B. Estado/senales del usuario

Representa que hizo el usuario. Debe vivir en `aa_task_state` y/o en campos
actuales de `aa_tasks` cuando ya representen declaracion del usuario.

Ejemplos:

- `status=done` como completion declarada por el usuario
- `completed_at` como fecha de esa declaracion
- defer / "Ahora no"
- dismiss / "Ignorar"
- counters y timestamps (`defer_count`, `dismiss_count`, `last_deferred_at`,
  `last_dismissed_at`)
- efectos activos (`defer_until`, `dismiss_until`)

Las senales son interpretables por policies. No son reglas finales inmutables.

### C. Hechos comprobados por el sistema

Representa que detecto el sistema. Debe separarse de la declaracion manual del
usuario.

Regla clave:

- `status=done` y `completed_at` representan completion declarada por usuario.
- Completion por sistema es un hecho comprobado por facts y requiere campos o
  estado propio.

Modelo objetivo futuro:

- `completed_by_system`
- `system_completed_at`
- `last_system_evaluated_at`
- `fact_result`
- `completion_fact_key` como definicion de que fact debe evaluarse

Learning hoy calcula `completion_fact` en lectura y no lo persiste como estado
comun. El objetivo es que ese resultado pueda vivir en el motor comun sin
mezclarse con `status=done`.

### D. Acciones/capacidades disponibles

Representa que puede hacerse con una lista o tarea.

Ejemplos:

- `complete`
- `defer`
- `dismiss`
- `navigate`
- `handler`
- `edit`
- `archive`
- `delete`
- `reactivate`
- return ignored

Separacion objetivo:

- Acciones declaradas/persistidas: acciones ricas como `navigate` o `handler`,
  con label, categoria, placement y payload.
- Capabilities calculadas: `can_edit`, `can_archive`, `can_delete`,
  `can_complete`, `can_dismiss`, etc. Las calcula una policy comun desde datos
  como `managed_by`, `status`, senales y tipo de completion.

Las acciones/capabilities no deben depender de hardcodes por procedencia. La
procedencia y `managed_by` son datos; las policies interpretan esos datos.

### E. Ejecucion especializada / adapters

Representa codigo que sigue siendo especifico porque ejecuta algo externo o
runtime.

Ejemplos:

- `pwa.install`
- navegacion a modulos de WP
- evaluacion de facts del sistema
- sync catalogo -> DB

El adapter puede sobrevivir, pero debe ser disparado desde metadata
persistida/sincronizada, no desde un pipeline completo paralelo a Tasks.

## Fuente comun persistida

El objetivo es que el feed oficial lea desde una fuente comun:

- listas creadas por usuario
- listas Agenda app / developer
- listas IA futuras
- listas premium/sistema futuras

Todas deben ser comparables por datos. La UI puede seguir recibiendo
`ExecutableList` / `ExecutableItem`, pero esos items deberian originarse en
`aa_task_lists`, `aa_tasks` y `aa_task_state`, mas tablas relacionadas minimas.

## Origin key e identidad

`origin_key` es imprescindible para listas/tareas seeded (developer,
Agenda app, IA/sistema) porque permite sync idempotente sin duplicados.

Reglas:

- Para seeded lists/tasks: `origin_key` estable y unico dentro de
  `source_category`.
- Para user tasks/lists: `origin_key` puede ser `null`; basta la PK de BD.
- Identidad global futura puede derivarse como:
  `source_category + ':' + (origin_key || id)`.
- Para preservar estado de usuario, las filas seeded no deben borrarse y
  recrearse. El sync debe ser no destructivo: UPSERT por `origin_key`.

Si una definicion seeded cambia titulo, copy, accion o metadata, el sync debe
actualizar la fila existente. Si una tarea seeded desaparece del catalogo, la
decision preliminar recomendada es archivar/deprecar la fila, no borrarla.

## Gobernanza y capabilities

Editar, archivar, borrar, completar, ignorar y reactivar son capabilities
generales. No deben estar hardcodeadas por source.

Ejemplos:

| Tipo | Capabilities esperadas |
|------|------------------------|
| Lista user | `can_edit=true`, `can_archive=true`, `can_delete=true` |
| Lista Agenda app seeded | `can_edit=false`, `can_archive=false`, `can_delete=false` |

`can_dismiss` puede ser `true` para seeded si se decide que el usuario puede
ocultar una tarea recomendada. Eso no edita la definicion; registra una senal
del usuario. `can_complete` puede depender de `completion_type` y de la policy.

## Completion manual vs completion por sistema

No mezclar completion declarada por usuario con completion comprobada por
sistema.

| Tipo | Modelo objetivo |
|------|-----------------|
| User-completion | `status=done`, `completed_at`, `completed_by_user` conceptual |
| System-completion | `completed_by_system`, `system_completed_at`, `last_system_evaluated_at`, `completion_fact_key` |

`completion_fact_key` pertenece a la definicion. El resultado del fact y sus
timestamps pertenecen al estado del sistema.

## Actions/facts como datos operables

Capacidades que hoy existen en Learning deben migrar a datos operables:

- navigate actions
- handler actions
- PWA handler (`pwa.install`)
- completion facts
- primary action
- visible actions
- action placement
- action labels
- action category

Tabla relacionada implementada como schema-only en MC13O-B2:

```text
aa_task_actions
  id
  task_id
  action_key
  type
  label
  placement
  category
  target_status
  target_module
  target_setup_focus
  target_fragment
  url
  handler
  payload_json
  enabled
  position
  created_at
  updated_at
```

MC13O-B2 crea solo el schema de `aa_task_actions`: task-only (`task_id` NOT
NULL), sin `list_id`, sin repository y sin consumidores. Las acciones de lista
quedan fuera hasta que exista una necesidad concreta. `navigate` debe preferir
`target_module` / `target_setup_focus` / `target_fragment`; `url` queda como
fallback. `handler` representa adapters como `pwa.install`. `payload_json` es
escape hatch, no sustituto de campos explícitos.

No se persisten acciones estándar derivables por policy (`complete`, `dismiss`,
`reopen`, `reactivate`, `edit`, `archive`, `delete`, return ignored).
`defer` / “Ahora no” queda deprecado funcionalmente desde MC13O-H3B-3: no es
acción visible activa ni fuente de clasificación.

## Default bucket y naturaleza de tarea

`default_bucket` representa la naturaleza/clasificación activa de una tarea:

- `primary`: principal/esencial
- `secondary`: secundaria/complementaria

Desde MC13O-H3B-3, `aa_tasks.default_bucket` es la única fuente activa para
proyectar `primary` / `secondary` en Tasks común. `dismiss_until` puede ocultar
temporalmente sin cambiar `default_bucket`; al vencer, la tarea vuelve a su
bucket por defecto. `defer_*` no participa en projection.

## Aging legacy

El aging de Learning se considera legacy para el modelo objetivo.

- No debe heredarse como regla final de cambio `primary` -> `secondary`.
- `primary` / `secondary` deben expresar naturaleza o criterio semantico, no
  envejecimiento.
- Si en el futuro se necesitan temporizadores, deben modelarse como expiracion
  de efectos activos usando `dismiss_until` / `defer_until`, no como aging de
  bucket.

## Gating por bucket legacy

El gating por bucket de Learning no debe ser verdad final.

- Las acciones no deberian depender de "donde se pinto" el item.
- Deben depender de capabilities calculadas por policies comunes.
- Puede permanecer temporalmente como deuda mientras se migra.
- Objetivo: una policy comun source-agnostic.

## Definition version

Decision actual: no implementar `definition_version` ahora.

`origin_key` + sync idempotente no destructivo basta para esta etapa. Un campo
de version puede agregarse en el futuro si aparece una necesidad real:

- auditoria
- rollout escalonado
- invalidar completion por cambio semantico fuerte
- compatibilidad de instalaciones antiguas

## Propuesta Ejecutiva

MC13O-A no disena la Propuesta Ejecutiva ni su ranking.

Condicion previa objetivo:

- La Propuesta Ejecutiva no debe nacer leyendo fuentes heterogeneas.
- Debe tender a evaluar tareas desde la fuente comun persistida.
- No se definen pesos por `source_category`.
- No se define formula de ranking.
- Este documento solo prepara datos comparables y motor comun.

## Ruta incremental

| Fase | Alcance |
|------|---------|
| Fase 0 | Hecha: labels canonicos de buckets (`primary` -> Principales, `secondary` -> Secundarias). |
| Fase A | Este documento: modelo objetivo de fuente comun persistida. |
| Fase B1 | Implementada en MC13O-B1: schema aditivo base (`DB_VERSION=6`) en `aa_task_lists`, `aa_tasks` y `aa_task_state`; sin consumidores aun. |
| Fase B2 | Implementada en MC13O-B2: schema-only de `aa_task_actions` task-only. |
| Fase C | Implementada parcialmente en MC13O-C1/C2: repository + use case manual/testeable para seed/sync idempotente del catalogo Learning hacia DB comun; sin consumidores. |
| Fase D1A | Implementada: read path DB comun para seeded `agenda_app` dentro del pipeline Tasks; metadata comun y `aa_task_actions` se proyectan al contrato executable, sin apagar Learning legacy. |
| Fase D2 | Hecho: el feed omite Learning legacy cuando existe lista seeded activa `agenda_app` + `learning.recommendations` en DB comun. |
| Fase D3 | Hecho: sync idempotente controlado por `admin_init` + option version; archived-first; activacion al validar seed completo. |
| Fase E1 | Hecho: evaluator + use case de system facts; persiste `completed_by_system` sin projection todavia. |
| Fase E2 | Hecho: evaluator en `GetTaskBoardUseCase`; `completed_by_system=1` fuera de active; `completion_type=system` sin completar manual. |
| Fase E | Feed desde fuente comun: Agenda app leida desde DB comun detras de transicion segura. |
| Fase F | Migracion de estado Learning: mapear `aa_learning_recommendation_state` a estado comun. |
| Fase G | Deprecacion: retirar mapper/pipeline Learning como fuente principal. |


## MC13O-B1: schema base preparado

MC13O-B1 agrega solo columnas base e indices para preparar la fuente comun. No
activa consumidores, no cambia el feed, no siembra Learning y no crea
`aa_task_actions`.

Columnas agregadas:

| Tabla | Columnas |
|-------|----------|
| `aa_task_lists` | `source_category`, `origin_key`, `managed_by` |
| `aa_tasks` | `source_category`, `origin_key`, `managed_by`, `default_bucket`, `completion_type`, `completion_fact_key` |
| `aa_task_state` | `completed_by_system`, `system_completed_at`, `last_system_evaluated_at` |

Indices agregados:

- `aa_task_lists`: `uniq_list_origin (source_category, origin_key)`, `source_category`.
- `aa_tasks`: `uniq_task_origin (source_category, origin_key)`, `source_category`.

Decisiones preservadas:

- `aa_task_actions` queda para MC13O-B2.
- `definition_version` sigue como futuro opcional.
- `source_label` sigue derivado del contrato executable.
- `can_*` sigue siendo capability calculada por policy futura, no columna.
- Completion por sistema queda preparada en `aa_task_state`, separada de
  `aa_tasks.status/completed_at` (declaracion del usuario).

## MC13O-C1/C2: sync manual Learning -> DB comun

MC13O-C1/C2 implementa un mecanismo manual e invocable para sembrar el
catalogo actual de Learning/Recomendaciones hacia la fuente comun persistida:

```text
AA_Learning_Catalog
  -> aa_task_lists
  -> aa_tasks
  -> aa_task_actions
```

El sync vive en `SyncLearningCatalogToTasksUseCase` porque el ownership del
destino es Tasks. Usa repositories SQL-only:

- `SeededTaskRepository`: UPSERT no destructivo de listas/tareas seeded por
  `(source_category, origin_key)`.
- `TaskActionRepository`: UPSERT de acciones declaradas por `(task_id,
  action_key)`.

Lista seeded:

```text
source_category = agenda_app
origin_key = learning.recommendations
owner_type = developer
managed_by = developer
title = Recomendaciones
```

Tareas seeded:

- Una fila por item activo del catalogo Learning.
- `source=system`, `source_category=agenda_app`, `managed_by=developer`.
- `origin_key` es la key estable del catalogo.
- `default_list=1` se persiste como `default_bucket=primary`;
  `default_list=2` como `default_bucket=secondary`.
- `completion_type=auto` se persiste como `system`; `manual` como `manual`.
- `completion_fact` se persiste como `completion_fact_key`.
- No se migran `aging_days`, `dismiss_hours`, `list_override` ni estado
  Learning en esta fase.

Acciones seeded:

- Navegacion: `type=navigate`, `action_key=navigate.{module}` o
  `navigate.{module}.{setup_focus}`, con `target_module`,
  `target_setup_focus` y `target_fragment`.
- PWA install: `action_key=pwa.install`, `type=handler`,
  `handler=pwa.install`.
- No se persisten como rows las acciones derivables por policy: `complete`,
  `defer`, `dismiss`, `reopen`, `reactivate`, `edit`, `archive`, `delete` ni
  return ignored.

Guardrails de esta fase:

- No hay hook automatico: no activation hook, no `admin_init`, no cron.
- No cambia el feed oficial ni sus mappers.
- No toca `aa_learning_recommendation_state`.
- No toca `aa_task_state`.
- No borra ni recrea filas existentes; preserva ids y estado de usuario.
- Si una tarea desaparece del catalogo, no se borra todavia.

Duplicidad visual (cerrada en D2):

Antes de MC13O-D2, si el sync manual ya habia creado la lista seeded Agenda app,
podia coexistir temporalmente con Learning legacy en el feed. D2 elimina esa
duplicidad: cuando la DB comun tiene la lista activa, el feed ya no mapea
`LearningRecommendationsToExecutableMapper`.

## MC13O-D1A: read path DB comun para Agenda app seeded

MC13O-D1A conecta el pipeline comun de Tasks con las filas seeded `agenda_app`
sin apagar Learning legacy. El objetivo es que, si el sync manual ya creo datos
en la DB comun, esas listas/tareas no se proyecten como "Mis listas".

Alcance implementado:

- `TaskListRepository` expone `source_category`, `origin_key` y `managed_by` en
  reads.
- `TaskRepository` expone `source_category`, `origin_key`, `managed_by`,
  `default_bucket`, `completion_type` y `completion_fact_key` en reads.
- `GetTaskBoardUseCase` carga `aa_task_actions` via `TaskActionRepository` y
  las agrega a `organization.task_actions_by_id`.
- `TaskBoardToExecutableMapper` interpreta `source_category=agenda_app` como
  `source=system`, `source_label=Agenda app`, `can_archive=false` y conserva
  `origin_key`.
- Las acciones persistidas `navigate`/`handler` se proyectan como
  `primary_action`; `ExecutableVisibleActionsEnricher` sigue derivando
  `visible_actions`.
- `default_bucket` se usa para seeded Agenda app cuando la policy comun aun no
  sabe clasificar por definicion seeded.
- `ExecutableNavigationUrlResolver` convierte `target_module`,
  `target_setup_focus` y `target_fragment` a URL runtime sin persistir URL
  absoluta.

Guardrails preservados en D1A (D2 cambia solo la omision condicional del feed):

- No hay hook automatico para ejecutar sync.
- No se migra ni toca `aa_learning_recommendation_state`.
- No se implementan facts de sistema ni `completed_by_system`.
- No se renombra aun el adapter JS `LearningActionHandlers`; puede seguir
  ejecutando `pwa.install` como adapter temporal de handlers executable.

## MC13O-D2: omitir Learning legacy cuando DB seeded esta disponible

MC13O-D2 cierra la duplicidad visual entre Learning legacy y la lista seeded
`learning.recommendations` en DB comun.

Regla en `GetExecutableListsFeedUseCase`:

- Lee primero el payload de Tasks (`GetTaskBoardUseCase`).
- Si el payload incluye una lista activa con
  `source_category=agenda_app`, `origin_key=learning.recommendations` y
  `status=active`, no llama a `GetLearningRecommendationsUseCase` ni a
  `LearningRecommendationsToExecutableMapper`.
- Si no existe esa lista seeded activa, conserva el fallback Learning legacy.

Deteccion:

- Se hace sobre el payload de tasks ya leido (`lists[]` del board), sin query
  adicional.
- No usa solo `source=system`; exige la tripleta de metadata comun.

Meta del feed:

- Cuando Learning legacy se omite, `meta.sources.learning.status=skipped` con
  `reason=seeded_agenda_app_available`.

Lo que sigue existiendo (transitorio):

- `AA_Learning_Catalog` y el adapter `LearningRecommendationsToExecutableMapper`
  siguen en el codigo; solo dejan de alimentar el feed cuando la DB seeded esta
  lista.
- No se migro `aa_learning_recommendation_state`. Riesgo aceptado: items
  completados/ignorados solo en legacy pueden reaparecer desde la DB seeded
  hasta un ciclo de migracion de estado (MC13O-F).
- El sync automatico se implementa en MC13O-D3 (ver abajo).

## MC13O-D3: sync controlado Learning catalog → DB comun

MC13O-D3 deja de depender del sync manual para sembrar el catalogo Learning en
la DB comun, sin apagar Learning legacy si el seed queda incompleto.

Mecanismo:

- `AA_Learning_Catalog_Seed_Lifecycle` en `admin_init` prioridad 20.
- Corre solo si `aa_db_version >= 7` y
  `aa_learning_catalog_seed_version < AA_Learning_Catalog::SEED_VERSION`.
- Guards: no `DOING_AJAX`, no `DOING_CRON`, transient lock 60s.
- Ejecuta `SyncLearningCatalogToTasksUseCase` (archived-first).
- Valida `list_id > 0` y `count(task_ids) === count(active_definition_keys())`.
- Solo entonces activa lista `learning.recommendations` y bumpea option version.
- Si falla: `error_log`, `aa_learning_catalog_seed_last_error`, sin bump version.

Archived-first:

- El use case upserta la lista seeded como `status=archived`.
- La activacion a `active` la hace el lifecycle tras validar seed completo.
- Si falla a mitad, la lista queda archived: `GetTaskBoardUseCase` no la carga
  y D2 mantiene fallback Learning legacy.

D2 defensivo (MC13O-D3):

- `GetExecutableListsFeedUseCase` omite Learning legacy solo si la lista seeded
  esta `active` **y** el payload incluye al menos una tarea asociada a esa lista.

Options:

- `aa_learning_catalog_seed_version` (gate principal).
- `aa_learning_catalog_seed_last_error` (debug).
- `aa_learning_catalog_seed_last_synced_at` (observabilidad).

Pendiente:

- No se migro `aa_learning_recommendation_state`.
- No hay system facts ni UI nueva en este ciclo.

## MC1: lista seeded `appointment_actions` (Acciones de citas)

Lista del sistema para tareas derivadas del lifecycle de citas. Catálogo
independiente de Learning; no modifica `AA_Learning_Catalog` ni su lifecycle.

Identidad estable:

- `source_category = agenda_app`
- `origin_key = appointment_actions`
- `managed_by = developer`
- `status = active` desde el seed (sin archived-first)

Mecanismo:

- `AA_Appointment_Actions_Catalog` (`SEED_VERSION`, `list_definition()`).
- `SyncAppointmentActionsListUseCase` → `SeededTaskRepository::upsert_seeded_list()`.
- `AA_Appointment_Actions_List_Seed_Lifecycle` en `admin_init` prioridad 20.
- Option gate: `aa_appointment_actions_list_seed_version`.

Gobernanza manual:

- `AA_Task_List_Governance_Policy::can_accept_user_created_task()` bloquea
  creación manual en listas no user-managed (`CreateTaskUseCase` →
  `list_not_manual_destination`).
- Editar, archivar y eliminar ya bloqueados por policies existentes.

Visibilidad feed/tablero:

- Feed unificado: `filterListsForUnifiedRender()` oculta `appointment_actions`
  sin items en buckets (tareas vigentes proyectadas).
- Tablero manual: `filterListsForBoardRender()` aplica la misma regla usando
  `organization.task_bucket_order_by_list`.
- Listas user vacías conservan comportamiento actual.

## MC13O-E1: evaluator y persistencia de system facts

MC13O-E1 introduce el motor de evaluacion/persistencia de facts para tareas
seeded `agenda_app` sin cambiar todavia la visibilidad del feed.

Componentes:

- `TaskSystemCompletionFactResolver` (`infrastructure/tasks/`): resuelve en
  batch los facts booleanos (`google_connected`, `business_data_complete`,
  `has_active_service`, `has_active_area`, `has_staff_with_service`,
  `has_registered_client`).
- `EvaluateTaskSystemCompletionFactsUseCase` (`application/tasks/`): carga
  candidatos `completion_type=system` + `completion_fact_key` + `status=pending`,
  evalua facts y persiste en `aa_task_state`.
- `TaskRepository::list_system_completion_candidates()`.
- `TaskStateRepository::record_system_completion_evaluation()`.

Reglas de persistencia:

- `completed_by_system` refleja el estado actual del fact (reversible 0/1).
- `system_completed_at` es sticky: se setea en el primer `false→true` y no se
  borra si el fact vuelve a false.
- `last_system_evaluated_at` se actualiza en cada evaluacion.
- No toca `aa_tasks.status`, `completed_at`, defer ni dismiss.

Alcance E1 (sin E2):

- No se invoca automaticamente desde `GetTaskBoardUseCase` ni lifecycle.
- No cambia `AA_Task_Signal_Policy`, `AA_Task_Active_View_Projection_Policy` ni
  `TaskBoardToExecutableMapper`.
- Las tareas con fact cumplido pueden seguir apareciendo en active hasta E2.

## MC13O-E2: system completion en board activo

MC13O-E2 conecta el evaluator de E1 con el flujo real de Tasks y el contrato
executable, sin mezclar completion manual con system completion.

Comportamiento:

- `GetTaskBoardUseCase` invoca `EvaluateTaskSystemCompletionFactsUseCase` al
  inicio de `execute()`. Si falla, loguea y continúa con el estado anterior.
- `AA_Task_Signal_Policy` expone `state.is_system_completed` desde
  `completed_by_system`.
- `AA_Task_Active_View_Projection_Policy` oculta tareas con
  `is_system_completed=true` (`REASON_SYSTEM_COMPLETED`), separado de
  `status=done` (`REASON_NOT_PENDING`).
- `TaskBoardToExecutableMapper`: `completion_type=system` →
  `can_complete=false` y `can_reopen=false`; `state.auto_completed=true` si la
  evaluation trae `is_system_completed`.

Separación preservada:

- `status=done` / `completed_at` = completion manual del usuario.
- `completed_by_system` = hecho verificado por sistema.

## MC13O-F1: migración parcial manual/idempotente de estado Learning

MC13O-F1 introduce mapping y use case de migración **manual** (sin lifecycle ni
gate D2) desde `aa_learning_recommendation_state` hacia el estado común de Tasks.

Componentes:

- `AA_Learning_Legacy_State_To_Task_State_Mapper` (domain/tasks): intención de
  migración por fila legacy + tarea seeded destino.
- `MigrateLearningRecommendationStateToTaskStateUseCase` (application/tasks):
  lee legacy, resuelve `agenda_app` + `origin_key`, persiste intenciones
  permitidas.
- `TaskStateRepository::apply_legacy_defer_migration()`: defer idempotente sin
  incrementar contador en re-runs.

Qué se migra en F1:

- `is_completed=1` + `completion_type=manual` → `aa_tasks.status=done` +
  `completed_at`.
- `is_ignored=1` (“Ahora no” legacy) → `aa_task_state` defer (`last_deferred_at`,
  `defer_until=null`, `defer_count=max(existing,1)`).

Qué NO se migra en F1:

- `is_dismissed` (queda `dismissed_skipped`; policy nueva de regreso pendiente).
- `last_suggested_at` / aging.
- `list_override` (no modifica `default_bucket`).
- `completed_by_system` / system facts (MC13O-E).

Legacy:

- `aa_learning_recommendation_state` no se borra ni modifica.

## MC13O-F2: lifecycle automático de migración legacy state

MC13O-F2 ejecuta de forma controlada `MigrateLearningRecommendationStateToTaskStateUseCase`
sin depender de invocación manual.

Mecanismo:

- `AA_Learning_State_Migration_Lifecycle` en `admin_init` prioridad **21** (después del
  seed en prioridad 20).
- Guards: no `DOING_AJAX`, no `DOING_CRON`, `aa_db_version >= 7`,
  `aa_learning_catalog_seed_version >= AA_Learning_Catalog::SEED_VERSION`,
  `aa_learning_state_migration_version < MIGRATION_VERSION`, transient lock 60s.
- Éxito: bump `aa_learning_state_migration_version`, `aa_learning_state_migration_last_run_at`,
  limpia `aa_learning_state_migration_last_error`.
- Fallo: `error_log`, `aa_learning_state_migration_last_error`, sin bump version.

Alcance F2:

- No migra `is_dismissed` (sigue F1).
- No ajusta D2 / `GetExecutableListsFeedUseCase` (gate D2 quedó en F3).

## MC13O-F3: gate D2 por migración de estado Learning legacy

MC13O-F3 protege instalaciones con historial Learning: el feed solo omite Learning
legacy cuando la DB seeded está lista **y** el estado legacy es seguro de omitir.

Regla:

```text
omitir Learning legacy SI
  seeded DB lista (D2)
  Y (
    aa_learning_state_migration_version >= MIGRATION_VERSION
    O
    no hay legacy actionable state
  )
```

Estado legacy accionable (`LearningRecommendationStateRepository::has_actionable_state`):

- `is_completed = 1`
- OR `is_ignored = 1`
- OR `is_dismissed = 1`

Notas:

- `is_dismissed` cuenta como accionable aunque F1/F2 no lo migren todavía.
- Si migration version no está al día y hay legacy accionable → fallback Learning.
- Si el check falla → fallback seguro (no omitir Learning legacy).

Pendiente post-F3:

- Migración/policy de dismissed legacy cuando se defina regreso de ignoradas.

## MC13O-H1/H2: Ignorar temporal por ciclo de trabajo

MC13O-H1/H2 cambia el write path de **Ignorar** en Tasks común: deja de escribir
`dismiss_until = null` (ocultamiento permanente) y pasa a escribir un
`dismiss_until` futuro calculado por ciclo de trabajo.

### Producto vs naming técnico

- Concepto de producto: **Ignorar** / **Designorar**.
- Persistencia técnica heredada: columnas `dismiss_*` en `aa_task_state`.
- `dismiss_until` futuro se interpreta como **`ignored_until`** (oculto hasta esa fecha).

### Ciclo de trabajo MVP

- Reinicio diario a las **12:00 PM** hora local (la de `$now` / WordPress).
- Default MVP: **1 ciclo** de trabajo por acción Ignorar.
- Policy: `AA_Task_Work_Cycle_Policy::resolve_ignore_until($now, $cycles)`.
- Si `$now` es antes de hoy 12:00 → próximo reset = hoy 12:00.
- Si `$now` es igual o después de hoy 12:00 → próximo reset = mañana 12:00.
- Si `$cycles > 1` → próximo reset + (`$cycles - 1`) días.

### Write path (nuevo Ignorar)

`RecordTaskDismissSignalUseCase`:

```text
last_dismissed_at = now
dismiss_count = dismiss_count + 1
dismiss_until = resolve_ignore_until(now, cycles=1)
```

No altera: `status`, `default_bucket`, defer, archivado ni eliminación.

### Read path (sin cambios en este ciclo)

`AA_Task_Signal_Policy` / `AA_Task_Active_View_Projection_Policy` ya interpretan:

- `dismiss_until` futuro → oculto (`is_dismiss_hiding = true`).
- `now >= dismiss_until` → designorado automático; vuelve a bucket natural según policy.
- `dismiss_until = null` con `last_dismissed_at` → **legacy permanente** hasta
  “Regresar tareas ignoradas” (`ReturnIgnoredUserTasksUseCase`).

No hay backfill de filas legacy en este ciclo.

### Designorar

- Automático cuando `now >= dismiss_until`.
- Manual con “Regresar tareas ignoradas” (sin cambios en F3/H2).
- Conserva `last_dismissed_at` y `dismiss_count`.

### Fuera de alcance H1/H2

- Schema / `DB_VERSION`.
- UI / JS / copy visible.
- Dónde aparece el botón Ignorar (corregido en MC13O-H3A).
- Migración de `is_dismissed` legacy.
- D2 / `GetExecutableListsFeedUseCase`.

## MC13O-H3A: Ignorar desacoplado de defer/bucket

MC13O-H3A corrige el read path de capabilities para **Ignorar** (`can_dismiss`):

- Ya **no depende** de `has_defer`, bucket primary/secondary ni `default_bucket`.
- Pending visible en active → `can_dismiss=true` salvo ocultamiento dismiss activo,
  `completed_by_system`, not pending o done.
- `AA_Executable_Visible_Actions_Policy` para `source=system` ya **no exige**
  `bucket_key=secondary` para emitir la acción Ignorar.

Reglas que se mantenían en H3A antes de H3B-3:

- `can_defer` seguía MC13G-D: true solo en camino primary sin defer; false con defer.
- Bucket projection seguía usando defer como puente temporal hacia secondary.
- Write path defer/dismiss seguía sin cambios (H1/H2).

Deuda explícita:

- **H3B:** reclasificación primary/secondary vía `aa_tasks.default_bucket`; deprecar
  defer como fuente de bucket.

## MC13O-H3C: canal Tasks para Ignorar en seeded/developer

MC13O-H3C alinea el **click** de Ignorar en items `source=system` / `agenda_app`
renderizados desde DB común con el flujo Tasks (H1/H2):

```text
data-tasks-action="dismiss" + data-task-id
→ aa_dismiss_task
→ RecordTaskDismissSignalUseCase
→ aa_task_state.dismiss_until temporal
```

Criterio de canal en renderer (`executableListRenderer.js`):

- `source=user` → siempre Tasks (`item.id`).
- `source_category=agenda_app` + `item.id` numérico (id de `aa_tasks`) → Tasks.
- Legacy Learning (id slug = `origin_key`, sin task id DB) → conserva
  `data-learning-action="dismiss"` → `aa_dismiss_learning_recommendation`.

H3A ya exponía Ignorar en capabilities; H3C conecta ese botón con el write path
común. No cambia duración, copy, projection ni schema.

Ignorar **no es** defer. Defer (“Ahora no”) queda como compatibilidad temporal
hasta H3B; conceptualmente debe migrar a “convertir en secundaria” vía
`default_bucket`, no como ocultamiento temporal.

## MC13O-H3B-1: write path de `default_bucket`

MC13O-H3B-1 habilita persistencia controlada de clasificación natural en
`aa_tasks.default_bucket` sin cambiar projection ni feed:

- `TaskRepository` escribe `default_bucket` (`primary` \| `secondary`); valores
  inválidos en repository se **normalizan a `primary`**.
- `CreateTaskUseCase` acepta `default_bucket` opcional (default lógico `primary`).
- `ChangeTaskDefaultBucketUseCase` cambia clasificación con validación **estricta**
  en application (`invalid_default_bucket` si el valor no es primary/secondary);
  exige lista `active`; no toca `status`, `aa_task_state`, defer ni dismiss.

Fuera de alcance H3B-1:

- Projection sigue usando `defer` como fuente de secondary (H3B-3).
- Sin copy “Convertir a tarea secundaria”, UI, JS ni endpoint AJAX.

## MC13O-H3B-2: backfill defer histórico → `default_bucket`

MC13O-H3B-2 protege tareas existentes antes de H3B-3 copiando la intención
histórica de “Ahora no”/defer hacia la clasificación persistida:

```text
aa_task_state.defer_count > 0
AND last_deferred_at IS NOT NULL (no vacío)
AND aa_tasks.default_bucket = primary
→ aa_tasks.default_bucket = secondary
```

Implementación:

- `TaskRepository::backfill_deferred_primary_to_secondary_bucket()` — UPDATE
  idempotente por site; **no filtra por status** ni lista archivada (backfill
  semántico de dato histórico).
- `MigrateDeferredTasksToDefaultBucketUseCase` — orquesta el backfill.
- `AA_Task_Default_Bucket_Migration_Lifecycle` — `admin_init` prio **22** (tras
  seed 20 y Learning state migration 21); opción
  `aa_task_default_bucket_migration_version` (v1); lock transient.

Conserva intacto: `defer_*`, `dismiss_*`, `completed_by_system`, `status`,
`completed_at`. **No cambia projection** ni defer write path.

## MC13O-H3B-3: `default_bucket` como única clasificación activa

MC13O-H3B-3 depreca funcionalmente `defer` / “Ahora no” como acción activa y
mueve la verdad de buckets a `aa_tasks.default_bucket`.

Regla active projection en Tasks común:

```text
list inactive → invisible, reason=list_not_active
not pending → invisible, reason=not_pending
completed_by_system=1 → invisible, reason=system_completed
dismiss hiding activo → invisible, reason=dismissed
si no:
  visible
  projected_bucket = aa_tasks.default_bucket
  suggested_active_bucket = aa_tasks.default_bucket
  reason = default_primary | default_secondary
```

Cambios de semántica:

- `AA_Task` modela `default_bucket` (`primary` \| `secondary`, default `primary`).
- `REASON_DEFERRED` queda dormido/deprecated; ningún camino active debe emitirlo.
- `can_defer=false` en projection y signal policy.
- `AA_Executable_Visible_Actions_Policy` no emite `defer` / “Ahora no” aunque
  llegue `can_defer=true` desde un payload viejo.
- `state.ignored` en el mapper ya no representa `has_defer`; solo refleja
  ocultamiento real por dismiss activo.
- `TaskBoardToExecutableMapper` confía en `task_bucket_order_by_list`; se retira
  el remapeo especial `agenda_app` por `default_bucket`/`deferred`.
- `defer_*` se conserva en `aa_task_state` como auditoría legacy y para backfill
  H3B-2; backend/JS legacy puede quedar dormido para rollback o limpieza futura.

### Configuración futura (documentada, no implementada)

Tareas seeded/developer podrán definir duración configurable, p. ej.:

```text
ignore_duration_type = work_cycles
ignore_duration_value = N
```

o fecha/hora concreta. Por ahora el use case acepta `ignore_cycles` / `cycles` en
input interno con default `1`; no hay schema ni overrides en catálogo todavía.

## Preguntas abiertas

- Puede el usuario ignorar tareas seeded Agenda app? Recomendacion: si, porque
  ignorar es senal de usuario, no edicion de definicion.
- Que ocurre cuando una tarea seeded se remueve del catalogo? Recomendacion
  preliminar: archivar/deprecar fila, no borrar.
- Cuando se evaluan facts de sistema? Sesion, reload, evento, cron o combinacion.
- Como se deshabilitan o archivan acciones seeded que desaparecen del catalogo?
- Si conviene retirar en un ciclo posterior backend/JS legacy de `defer`
  (`aa_defer_task`, `TasksService.deferTask`, coordinator/renderer dormidos).
- Como se expondria system-completed en UI?

## MC13O-consolidation-audit: tests de modelo oficial Listas/Tareas

MC13O-consolidation-audit cierra con evidencia que Listas/Tareas opera desde el
feed común oficial (`aa_get_executable_lists_feed`) y no desde un segundo
renderer Learning en la pantalla unified.

Confirmado por tests:

- `GetExecutableListsFeedUseCase` puede devolver lista seeded
  `learning.recommendations` con `buckets` vacíos cuando no hay items active;
  la UI unified oculta listas `source=system` sin items (MC13H).
- Listas `source=user` vacías permanecen con mensaje de pendientes.
- `can_archive=true` solo para `source_category=user` + `managed_by=user`.
- Anti-duplicidad: si el mapper Tasks ya proyectó la lista seeded DB
  (`origin_key=learning.recommendations`), `assemble_lists` no antepone la lista
  Learning fallback legacy equivalente.
- `index.php` Listas encola feed unified (`visibleFeed: unified`) y no carga
  `learning-module.js` ni `learningRecommendationRenderer.js`.

## MC13O-H3B-close: cierre documental y tests

MC13O-H3B-close cierra el arco H3B sin tocar runtime PHP/JS de producción.

Estado vigente tras H3B-3 + H3B-close:

```text
aa_tasks.default_bucket = primary | secondary
→ única fuente activa de clasificación en projection active

defer_* → legacy audit / deprecated
→ no visible action
→ no projection
→ no state.ignored
```

- El feed común (`GetExecutableListsFeedUseCase` + enricher) no emite
  `visible_actions.defer` ni botón “Ahora no”.
- Tests JS de parity/renderer documentan `defer` solo como fallback dormido o
  payload legacy manual; no como contrato del feed unified.
- Renderer/coordinator/AJAX defer pueden quedar en repo como código dormido;
  limpieza agresiva es opcional post-MVP.
- Reclasificación primary↔secondary futura: modal/panel de edición sobre
  `default_bucket`; no `defer` ni acción en card.
