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

Probable tabla relacionada futura:

```text
aa_task_actions
  id
  task_id
  action_key
  type
  label
  url
  handler
  placement
  category
  target_status
  position
```

Esta tabla no se implementa en MC13O-A. Solo documenta que acciones ricas no
deben quedar escondidas en `AA_Learning_Catalog` como una segunda fuente
operativa.

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
| Fase B | Schema aditivo: campos en `aa_task_lists`, `aa_tasks`, `aa_task_state`; tabla relacionada de actions si se aprueba; sin consumidores aun. |
| Fase C | Seed/sync idempotente: catalogo Learning -> filas seeded en DB comun. |
| Fase D | Motor comun: capabilities por `managed_by`, `default_bucket`, actions desde metadata persistida, adapter de facts. |
| Fase E | Feed desde fuente comun: Agenda app leida desde DB comun detras de transicion segura. |
| Fase F | Migracion de estado Learning: mapear `aa_learning_recommendation_state` a estado comun. |
| Fase G | Deprecacion: retirar mapper/pipeline Learning como fuente principal. |

## Preguntas abiertas

- Puede el usuario ignorar tareas seeded Agenda app? Recomendacion: si, porque
  ignorar es senal de usuario, no edicion de definicion.
- Que ocurre cuando una tarea seeded se remueve del catalogo? Recomendacion
  preliminar: archivar/deprecar fila, no borrar.
- Cuando se evaluan facts de sistema? Sesion, reload, evento, cron o combinacion.
- Donde viviran exactamente actions/facts? Columnas minimas, tabla relacionada
  o mezcla.
- Cuando mover "Ahora no" fuera del header?
- Como se expondria system-completed en UI?
