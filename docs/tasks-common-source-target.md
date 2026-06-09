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

No se persisten acciones estándar derivables por policy (`complete`, `defer`,
`dismiss`, `reopen`, `reactivate`, `edit`, `archive`, `delete`, return ignored).
Esas siguen siendo capabilities/visible actions calculadas por policies futuras.

## Default bucket y naturaleza de tarea

`default_bucket` (o campo equivalente) representa la naturaleza inicial o
propuesta de una tarea:

- `primary`: principal/esencial
- `secondary`: secundaria/complementaria

No significa "donde se pinta siempre". Las policies pueden proyectar distinto
segun senales, estado o facts.

Hoy "Ahora no" / defer funciona como cambio de proyeccion hacia `secondary`.
Conceptualmente no debe quedar necesariamente como regla final ni como accion
rapida permanente del header. En un modelo posterior puede moverse a
edicion/configuracion de tarea.

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

Pendiente MC13O-F:

- Migrar `aa_learning_recommendation_state` hacia estado comun (defer/dismiss/
  completed manual legacy).

## Preguntas abiertas

- Puede el usuario ignorar tareas seeded Agenda app? Recomendacion: si, porque
  ignorar es senal de usuario, no edicion de definicion.
- Que ocurre cuando una tarea seeded se remueve del catalogo? Recomendacion
  preliminar: archivar/deprecar fila, no borrar.
- Cuando se evaluan facts de sistema? Sesion, reload, evento, cron o combinacion.
- Como se deshabilitan o archivan acciones seeded que desaparecen del catalogo?
- Cuando mover "Ahora no" fuera del header?
- Como se expondria system-completed en UI?
