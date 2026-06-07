# Contrato común de listas/items ejecutables (MC7)

Documento de referencia para la proyección unificada de **Learning/Recomendaciones** y **Tasks de usuario** hacia un mismo modelo operativo consumible por UI.

## Propósito

Evitar dos sistemas incompatibles (Learning rico vs Tasks pobre) sin fusionar almacenamiento ni migrar catálogos. MC7 define **solo la forma de salida** (`ExecutableList` / `ExecutableItem`) y mappers que traducen payloads ya resueltos por cada fuente.

**Single Source of Truth:** las reglas siguen en dominio/application de cada fuente. Este contrato no recalcula visibilidad, buckets ni prioridad.

## Fuente vs persistencia vs proyección

| Capa | Learning | Tasks (usuario) |
|------|----------|-----------------|
| **Definición / catálogo** | `AA_Learning_Catalog` (código) | Filas `aa_task_lists` / `aa_tasks` |
| **Estado persistido** | `aa_learning_recommendation_state` | Columnas `status`, `completed_at`, etc. |
| **Reglas** | `AA_Learning_Visibility_Policy` | `AA_Task_Prioritization_Policy` + VOs |
| **Orquestación** | `GetLearningRecommendationsUseCase` | `GetTaskBoardUseCase` |
| **Proyección MC7** | `LearningRecommendationsToExecutableMapper` | `TaskBoardToExecutableMapper` |
| **Contrato normalizado** | `AA_Executable_Contract` | (mismo) |

La UI futura consumirá **solo** el contrato normalizado, no las tablas ni el catálogo directamente.

**Executable es proyección, no fuente de verdad.** Los mappers y el feed reflejan el estado y las reglas de cada fuente en un momento dado; no persisten señales ni sustituyen el storage de Learning ni Tasks.

## Views, projections y buckets

El feed executable actual opera sobre **`view=active`**. Esa vista activa no es una tabla ni un estado persistido: es una configuración de proyección que decide qué criterios aplicar y qué buckets renderizar ahora.

Modelo conceptual:

| Concepto | Significado |
|----------|-------------|
| **Lista** | Entidad/categoría operable (`ExecutableList`) |
| **Item** | Entidad operable dentro de una lista (`ExecutableItem`) |
| **Estado / señales / facts** | Información registrada o calculada por la fuente |
| **Policy** | Interpreta estado, señales y facts con criterios mutables |
| **View** | Configuración de lectura/proyección (`active` hoy) |
| **Bucket** | Sección renderizable producida por la view (`primary`, `secondary`, `default`) |

`primary`, `secondary` y `default` son **buckets de proyección**, no listas internas fijas ni pertenencia sustancial del item. Un item no “vive” en `primary` o `secondary`; cumple criterios actuales que hacen que una view lo proyecte en uno u otro bucket. Si cambian señales, facts o criterios de policy, el mismo item puede aparecer en otro bucket o salir de la view.

Estado actual:

- **Learning** ya proyecta `list_1` / `list_2` a buckets `primary` / `secondary`.
- **Tasks user** proyectan `view=active` a buckets `primary` / `secondary` desde MC13E. El fallback `default` queda solo para compatibilidad con payloads antiguos sin `task_bucket_order_by_list`.
- **`completed`, `ignored`, `archived`, `today`** y otras lecturas son futuras views o proyecciones. No existen todavía como feed real ni como buckets activos implementados.
- `done` en Tasks sigue fuera de `view=active`; no hay vista `completed` ni `Reabrir` en active feed.

Regla de ownership:

- Las **policies de fuente** deciden criterios de proyección.
- Los **mappers** traducen una proyección ya decidida al contrato executable.
- El **Use Case feed** orquesta fuentes y fija/recibe la view.
- El **enricher** proyecta `visible_actions` para la view recibida; no decide pertenencia ni elegibilidad de acciones desde bucket.
- El **renderer** solo presenta buckets; no decide reglas.

MC13E extiende `AA_Task_Prioritization_Policy` para que user tasks emitan `task_bucket_order_by_list` sin meter criterios en el mapper. Criterio activo provisional: tareas pending vencidas o con alta importancia (`importance < 0`) → bucket `primary` / label `Prioritarias`; resto pending → bucket `secondary` / label `Otras tareas`. Es una interpretación modificable de la view activa, no pertenencia estable del item.

MC13F documenta el modelo futuro de señales user (`defer`/`dismiss`) y el desacople conceptual entre buckets y `visible_actions`. Sin endpoints ni botones todavía.

MC13G-A implementa persistencia write-only en `aa_task_state` + repositorio + use cases de registro. Sin endpoints ni JS.

MC13G-B conecta lectura de señales al board/feed: `GetTaskBoardUseCase` carga `task_state_by_id`, `AA_Task_Signal_Policy` produce `task_evaluations_by_id`, el mapper refleja `state` en ExecutableItem. Sin botones, sin `visible_actions` defer/dismiss, sin cambios de buckets MC13E.

MC13G-C2 activa botones visibles **Ahora no** / **Ignorar** para user tasks: capabilities desde evaluaciones, `visible_actions` user, renderer `data-tasks-action`. Canal Learning intacto. Sin efectos de proyección (MC13G-D).

## Modelo de señales, estado y procedencia

Las acciones del usuario registran **señales o decisiones interpretables**. No deben modelarse ni documentarse como movimientos absolutos ni reglas definitivas. Una policy lee estado persistido, facts y (en el futuro) eventos/counters, y **proyecta** qué aparece en cada vista. Si los criterios cambian, la misma señal puede proyectarse distinto.

### Capas conceptuales (qué es cada cosa)

| Capa | Qué representa | Persistido hoy | Consumidor típico |
|------|----------------|----------------|-------------------|
| **Estado actual** | Snapshot compacto del item/lista en su fuente | Sí (por fuente) | Use Cases, policies de fuente, mappers |
| **Declaración del usuario** | Intención o afirmación subjetiva del operador | Parcialmente (como flags/columnas de estado) | Write Use Cases → estado actual |
| **Fact / verificación automática** | Observación recalculable del sistema | No en tablas de señales; facts en runtime | `AA_Learning_Visibility_Policy`, evaluators |
| **Evento histórico** *(futuro)* | Registro append-only: qué ocurrió, cuándo, con qué outcome | **No implementado** | Policies analíticas, auditoría, IA premium |
| **Contador / resumen** *(futuro)* | Vista derivada sobre eventos o estado (`defer_count`, `last_action_at`) | **No implementado** | Policies que inhiben o re-priorizan |
| **capabilities** | Posibilidades técnicas/latentes del item | Proyección (mapper + enricher) | `AA_Executable_Visible_Actions_Policy` |
| **visible_actions** | Botones que **esta vista/bucket** debe mostrar **ahora** | Proyección (enricher + policy) | Renderer, coordinator |
| **source / procedencia** | Origen lógico del item/lista (`system`, `user`, `ai`) | En contrato executable; parcial en BD Tasks | Policies, renderer (canal DOM) |

### Estado actual por fuente (persistencia real)

**Learning** — tabla `aa_learning_recommendation_state`, una fila por `recommendation_key`:

- `is_completed`, `completed_at` — declaración manual de completion (recomendaciones `completion_type=manual`).
- `is_ignored`, `ignored_at` — señal “Ahora no” / defer (postergación declarada).
- `is_dismissed`, `dismissed_at` — señal “Ignorar” / dismiss (ocultamiento declarado).
- `list_override`, `last_suggested_at` — auxiliares de proyección (aging, override); no son eventos.
- `dismiss_active` — **no se persiste**; la policy la calcula desde `is_dismissed` + `dismissed_at` + `dismiss_hours`.

**Tasks** — tablas `aa_tasks` / `aa_task_lists`:

- Tarea: `status` (`pending` \| `done`), `completed_at` — declaración del usuario al marcar hecha/reabrir.
- Tarea: `due_at`, `importance`, `position`, `source`, `notes`, `list_id` — datos operables para policies de proyección; no son señales de defer/dismiss.
- Lista: `status` (`active` \| `archived`) — declaración al archivar; no hay `archived_at` ni unarchive hoy.
- **MC13G-A (implementado):** persistencia write-only en `aa_task_state` vía `TaskStateRepository` y use cases `RecordTaskDeferSignalUseCase` / `RecordTaskDismissSignalUseCase`.
- **MC13G-B (implementado):** lectura batch en `GetTaskBoardUseCase`; interpretación en `AA_Task_Signal_Policy`; payload enriquecido con `task_state_by_id` y `organization.task_evaluations_by_id`; mapper refleja señales en `ExecutableItem.state`. `can_defer`/`can_dismiss` siguen `false` en feed; sin `visible_actions` defer/dismiss; buckets MC13E sin cambios.
- **MC13G-C1 (implementado):** canal técnico defer/dismiss — `TasksAjax` (`aa_defer_task`, `aa_dismiss_task`), `TasksService.deferTask`/`dismissTask`, coordinator `data-tasks-action`. Sin botones visibles; capabilities siguen false en feed.
- **MC13G-C2 (implementado):** mapper publica `can_defer`/`can_dismiss` desde `task_evaluations_by_id`; enricher + policy emiten `visible_actions` user; renderer source-aware usa canal Tasks (`data-tasks-action` + `data-task-id`). Sin efectos de proyección (defer no mueve bucket; dismiss no oculta).
- **MC13G-D (futuro):** efectos de proyección (visibilidad, buckets) si producto lo confirma.

Ninguna de estas tablas es un **action log**. Solo guardan el **último estado** (y timestamps del último cambio por tipo de señal). Re-dismiss o re-defer **sobrescriben** el timestamp anterior; no queda historial.

### Declaración del usuario vs fact automático

| Concepto | Learning | Tasks |
|----------|----------|-------|
| **Completion declarada** | `is_completed=1` vía `CompleteLearningRecommendationUseCase` (solo manual) | `status=done`, `completed_at` vía `ChangeTaskStatusUseCase` |
| **Completion verificada / automática** | `completion_fact` evaluado en lectura (`is_auto_completed`); **no escribe BD** | No existe hoy |
| **Proyección en ExecutableItem** | `state.completed`, `state.auto_completed` | `state.completed` (= done); `auto_completed` siempre `false` |

`status=done` en Tasks y `is_completed` en Learning manual significan **“el usuario declaró completado”**, no verificación objetiva de que la acción ocurrió en el mundo real.

### Semántica de señales de usuario (no movimientos absolutos)

| Señal UX | Registro persistido | Interpretación policy *(ejemplos actuales, no garantías)* |
|----------|---------------------|-------------------------------------------------------------|
| **Ahora no** (defer) | Learning: `is_ignored`, `ignored_at` | La policy **puede** proyectar el item fuera de `primary` (p. ej. bucket `secondary`) **si** no hay criterios que lo contradigan. No es “mover definitivamente a secondary”. |
| **Ignorar** (dismiss) | Learning: `is_dismissed`, `dismissed_at` | La policy **puede** excluir el item del feed activo mientras `dismiss_active` aplique. No es borrado permanente: al expirar la ventana, el flag puede seguir en BD pero la proyección cambia. |
| **Ahora no** (defer) — Tasks user | `aa_task_state`: `last_deferred_at`, `defer_count`, `defer_until` | Señal de postergación; MC13G-B la lee y expone en evaluaciones/`state.ignored`; MC13G-C2 muestra botón y registra señal vía canal Tasks; **no** mueve buckets todavía. |
| **Ignorar** (dismiss) — Tasks user | `aa_task_state`: `last_dismissed_at`, `dismiss_count`, `dismiss_until` | Señal de ocultamiento; MC13G-B la lee y expone en `state.dismissed`/`dismiss_active`; MC13G-C2 muestra botón y registra señal vía canal Tasks; **no** oculta del feed todavía. |
| **Completar** (manual) | Learning: `is_completed`; Tasks: `status=done` | Declaración del usuario; puede sacar el item del feed activo. Distinta de auto-completion por fact. |
| **Reabrir** (Tasks) | `status=pending`, `completed_at=null` | Declaración inversa; no implica evento histórico de “des-completado verificado”. |
| **Archivar lista** | `aa_task_lists.status=archived` | Declaración sobre la lista; tareas conservadas. Acción de **lista**, no de item. |

El coordinator experimental (MC12) **solo ejecuta** la mutación vía servicio y refresca el feed; **no** implementa estas reglas de proyección.

## User task signals: defer/dismiss (MC13F / MC13G-A / MC13G-B / MC13G-C)

MC13F documentó el modelo conceptual. **MC13G-A** implementa escritura. **MC13G-B** implementa lectura e interpretación en el board/feed. **MC13G-C2** activa botones visibles y canal DOM Tasks.

### Estado actual

Tasks user en el feed executable (`view=active`, `user-swap`) soporta:

- **Completar** — declaración `status=done` vía `ChangeTaskStatusUseCase`.
- **Ahora no** (defer) — señal vía `RecordTaskDeferSignalUseCase`; botón `data-tasks-action="defer"` + `data-task-id`.
- **Ignorar** (dismiss) — señal vía `RecordTaskDismissSignalUseCase`; botón `data-tasks-action="dismiss"` + `data-task-id`.
- **Reabrir** — reservado; no aparece en active feed (`done` excluido).
- **Archivar lista** — acción de lista, no de item.

El mapper publica `can_defer` / `can_dismiss` desde `organization.task_evaluations_by_id[task_id].capabilities` (sin recalcular reglas ni depender de bucket). Fallback sin evaluación: ambos `false`. Learning (`source=system`) conserva canal `data-learning-action` + gate por bucket.

### Semántica objetivo (cuando exista storage)

| Señal UX | Nombre preferido Tasks | Qué registra | Qué **no** significa |
|----------|------------------------|--------------|----------------------|
| **Ahora no** | `defer` / `deferred_*` | Postergación o reducción de prioridad declarada por el usuario | Mover definitivamente a bucket `secondary`; pertenencia estable en “Otras tareas”. |
| **Ignorar** | `dismiss` / `dismissed_*` (o `hidden_*` si se prefiere ocultamiento) | Ocultamiento temporal declarado | Borrar la tarea; ocultar para siempre; equivaler “Ignorar” con `status=done`. |

Learning conserva nombres legacy (`is_ignored` = “Ahora no”, `is_dismissed` = “Ignorar”). **Tasks user debe usar nombres semánticos claros** al implementar; no copiar nombres ciegamente.

### Persistencia (MC13G-A)

Tabla **`aa_task_state`**, una fila por `task_id` (`DB_VERSION = 5`). Defer/dismiss son **señales operativas**, no atributos de `aa_tasks`.

Shape implementado:

```php
[
    'task_id' => int,
    'last_deferred_at' => string|null,
    'defer_until' => string|null,   // MC13G-A: siempre null al registrar
    'defer_count' => int,
    'last_dismissed_at' => string|null,
    'dismiss_until' => string|null, // MC13G-A: siempre null al registrar
    'dismiss_count' => int,
    'created_at' => string|null,
    'updated_at' => string|null,
]
```

**Write-only MC13G-A:** `TaskStateRepository::record_defer` / `record_dismiss` incrementan contadores y timestamps; no calculan `*_until`, no mueven buckets, no ocultan tareas, no tocan `aa_tasks.status`.

### Read path (MC13G-B)

`GetTaskBoardUseCase` devuelve:

```php
[
    'lists' => [...],
    'tasks' => [...],                          // filas aa_tasks sin mezclar
    'task_state_by_id' => [ task_id => row ],  // raw aa_task_state; ausente si no hay fila
    'organization' => [
        'list_order' => [...],
        'task_order_by_list' => [...],
        'task_bucket_order_by_list' => [...],  // MC13E sin cambios
        'executive_candidates' => [...],
        'task_evaluations_by_id' => [
            task_id => [
                'signals' => ['has_defer', 'has_dismiss', 'defer_count', 'dismiss_count'],
                'state' => ['is_defer_active', 'is_dismiss_active'],
                'capabilities' => ['can_defer', 'can_dismiss', 'can_reactivate' => false],
                'visible_in_active' => true,
            ],
        ],
    ],
]
```

`AA_Task_Signal_Policy` (dominio puro) interpreta señales:

- `has_defer` = `defer_count > 0` y `last_deferred_at` presente
- `has_dismiss` = `dismiss_count > 0` y `last_dismissed_at` presente
- `is_defer_active` = `defer_until !== null` y `now < defer_until`
- `is_dismiss_active` = `dismiss_until !== null` y `now < dismiss_until`
- `visible_in_active` = `true` siempre en MC13G-B (sin ocultamiento)
- Sin ventana fallback desde `last_*_at`

`TaskBoardToExecutableMapper` traduce evaluaciones a `ExecutableItem.state` y publica `capabilities.can_defer` / `can_dismiss` desde la evaluación (MC13G-C2). Sin evaluación: `false`.

`AA_Executable_Visible_Actions_Policy`: para `source=user`, defer/dismiss dependen solo de capabilities (sin `bucket_key`); Learning (`source=system`) conserva gate por bucket (`primary` → defer, `secondary` → dismiss).

**MC13G-C1 (canal técnico):** endpoints `aa_defer_task` / `aa_dismiss_task` → use cases de señal; `TasksService.deferTask` / `dismissTask`; coordinator enruta `data-tasks-action="defer|dismiss"` + `data-task-id`.

**MC13G-C2 (botones visibles):** mapper + enricher + policy emiten `visible_actions` defer/dismiss para user pending; renderer source-aware renderiza canal Tasks. Click registra señal y refresca feed; la tarea puede seguir visible (sin efecto de proyección).

**No implementado aún:** ventanas `defer_until`/`dismiss_until` en write UC, efectos visibles/buckets (MC13G-D), vistas completed/ignored.

### Ownership (lectura y escritura)

**MC13G-A (write):** use cases `RecordTaskDeferSignalUseCase`, `RecordTaskDismissSignalUseCase` → `TaskStateRepository` → `aa_task_state`. Sin AJAX ni `TasksService` todavía.

**MC13G-B (read):** `GetTaskBoardUseCase` → batch `task_state_by_id` → `AA_Task_Signal_Policy` → `task_evaluations_by_id` → mapper `state`.

**MC13G-C1 (transport):** `TasksAjax` + `TasksService` + coordinator tasks channel para defer/dismiss.

**MC13G-C2 (visible actions + renderer):** mapper publica capabilities desde evaluaciones; enricher + policy generan intents; `executableListRenderer.js` usa `data-tasks-action` para `source=user` y conserva `data-learning-action` para Learning.

**MC13G-D (futuro):** efectos de proyección (visibilidad/buckets) si se decide.

### Buckets y visible_actions: proyecciones hermanas (MC13F)

**Incorrecto:**

- `bucket_key === primary` → mostrar “Ahora no”.
- `bucket_key === secondary` → mostrar “Ignorar”.

**Correcto:**

```
estado + señales + facts
        ↓
   policy de fuente (Tasks)
        ├→ bucket projection (Prioritarias / Otras tareas)
        └→ action eligibility (can_defer, can_dismiss, can_complete, …)
                ↓
        enricher + visible_actions policy
                ↓
           visible_actions[]
```

Bucket y `visible_actions` son **proyecciones hermanas** desde datos interpretables. Pueden compartir criterios (p. ej. una tarea vencida puede ser prioritaria **y** elegible para defer), pero **ninguna es fuente de verdad de la otra**.

Ejemplo: una tarea puede proyectarse en `primary` por vencimiento, pero “Ahora no” solo debe aparecer si la policy de Tasks decide `can_defer=true` dado su estado/señales actuales — no porque esté en “Prioritarias”. Igual para “Ignorar” y `secondary`.

**Deuda documentada (Learning):** `AA_Executable_Visible_Actions_Policy` en vista `active` filtra defer/dismiss por `bucket_key` (`primary` / `secondary`) **solo para `source=system`**. Tasks user (`source=user`) ya usa capabilities sin gate por bucket (MC13G-C2).

### Qué no existe todavía (diseño futuro)

Explícitamente **fuera de alcance** hasta que una policy concreta lo consuma:

- **Action log** — tabla o stream append-only de eventos (`clicked_defer`, `handler_attempted`, etc.).
- **Counters** — `defer_count`, `last_user_action_at`, agregados derivados.
- **Tabla común de eventos** cross-fuente (Learning + Tasks + handlers + premium/IA).

Cuando exista demanda (p. ej. “no mostrar defer si ya pospuso 3 veces”, auditoría de PWA install, ranking global), el diseño candidato separará:

```php
// Shape conceptual futuro — NO implementado
[
    'event_key' => 'clicked_defer',           // clicked_complete, handler_attempted, ...
    'subject_type' => 'item'|'list',
    'subject_source' => 'system'|'user'|'ai',
    'subject_id' => string,
    'origin_key' => string|null,
    'actor_type' => 'user',
    'channel' => 'executable'|'legacy',
    'occurred_at' => string,
    'payload' => array,
    'outcome' => string|null,
]
```

Los **counters** serían vista derivada (materializada o calculada), no fuente de verdad inicial. `visible_actions` **no** absorberá historial ni contadores.

### source / procedencia

| Valor | Significado hoy | Ejemplo |
|-------|-----------------|---------|
| `system` | Learning / recomendaciones de producto | Lista `system:learning.recommendations` |
| `user` | Tasks creadas por el operador | Listas `aa_task_lists` |
| `ai` | Reservado en contrato; ningún mapper lo emite aún | — |

`origin_key` estabiliza la clave lógica dentro de la fuente (p. ej. recommendation `key`). Tasks usan `origin_key=null` en items; comparación global futura puede requerir convenciones explícitas (`tasks.list.{id}`).

### Deuda: acciones de lista (`archive-list`)

Hoy `archive-list` es acción de **lista**, no de item:

- Capability: `list.capabilities.can_archive`.
- Renderer: botón con `data-tasks-action="archive-list"` + `data-list-id` (namespace tasks provisional).
- Coordinator MC12A: `TasksService.archiveTaskList` tras confirm.

**Modelo futuro** (no implementar hasta ciclo dedicado):

- `list.visible_actions` en contrato normalizado.
- `AA_List_Visible_Actions_Policy` (o equivalente) + enricher de lista.
- Namespace DOM propio (p. ej. `data-executable-list-action="archive"`), deprecando el atajo `data-tasks-action` en feed experimental.

## ExecutableList

```php
[
    'id' => string,              // estable por fuente (ej. system:learning.recommendations, "42")
    'source' => 'system'|'user'|'ai',
    'origin_key' => string|null, // clave lógica de lista (ej. learning.recommendations)
    'title' => string,
    'description' => string|null,
    'importance' => int,
    'position' => int,
    'status' => 'active'|'archived',
    'capabilities' => ['can_archive' => bool],
    'buckets' => ExecutableBucket[],
]
```

## ExecutableBucket

Sublistas internas de una lista (Principales, Otras sugerencias, default).

```php
[
    'key' => 'primary'|'secondary'|'default',
    'label' => string,
    'items' => ExecutableItem[],
]
```

## ExecutableItem

```php
[
    'id' => string,
    'source' => 'system'|'user'|'ai',
    'origin_key' => string|null,   // ej. recommendation key
    'title' => string,
    'description' => string|null,
    'importance' => int,
    'due_at' => string|null,       // Y-m-d H:i:s
    'status' => 'pending'|'done',   // proyección: done = declaración usuario (Tasks) o completion (Learning); no implica verificación objetiva
    'state' => [                    // snapshot interpretado por la fuente; no es action log
        'completed' => bool,        // true si la fuente considera el item completado (manual o auto)
        'ignored' => bool,          // señal defer persistida (Learning)
        'dismissed' => bool,        // señal dismiss persistida (Learning)
        'dismiss_active' => bool,   // ventana dismiss vigente (calculada; Learning)
        'auto_completed' => bool,   // completion por fact; distinta de declaración manual
    ],
    'capabilities' => [
        'can_complete' => bool,
        'can_reopen' => bool,
        'can_defer' => bool,
        'can_dismiss' => bool,
        'can_reactivate' => bool,
    ],
    'primary_action' => null|navigate|handler|status,
    'visible_actions' => VisibleAction[],
    'is_executive_candidate' => bool,
]
```

### primary_action

- **navigate:** `{ type: 'navigate', label, url }`
- **handler:** `{ type: 'handler', label, handler }` — ejecución runtime en JS (handlers registrados)
- **status:** `{ type: 'status', label, to: 'pending'|'done' }` — mutación de **declaración** del usuario vía Use Case de la fuente (no verificación automática)

`primary_action` es un campo temporal/backward-compatible: expresa una acción principal heredada de los mappers actuales, pero no alcanza para modelar la coexistencia de acciones visibles (`Ir` + `Completar` + `Ahora no`, por ejemplo).

## Acciones visibles

`visible_actions` representa las acciones que una vista/bucket debe mostrar **ahora** para un `ExecutableItem`. No es historial, no es un contador de eventos, no enumera todas las acciones técnicamente posibles y no decide disponibilidad runtime de handlers.

Shape conceptual:

```php
[
    'key' => string,                         // clave estable: complete, defer, pwa.install, etc.
    'type' => 'navigate'|'handler'|'status'|'intent',
    'category' => 'mechanical'|'declarative'|'intent'|'recovery',
    'label' => string,
    'placement' => 'primary'|'secondary',
    'target_status' => 'done'|'pending'|null,
    'url' => string|null,
    'handler' => string|null,
]
```

- **capabilities:** posibilidades técnicas o latentes según estado/fuente (`can_complete`, `can_reopen`, etc.). **No** equivalen automáticamente a botones visibles.
- **primary_action:** acción principal temporal usada por el contrato actual y renderers experimentales.
- **visible_actions:** proyección de **qué botones mostrar ahora** en esta vista; resultado de capabilities/state de fuente + policy executable. **No** es historial, contador, lista de acciones posibles ni fuente de verdad de señales. **No** debe inferirse principalmente desde `bucket_key` (ver MC13F).
- **runtime availability:** disponibilidad JS de handlers (`pwa.install`, standalone, prompt). JS puede ocultar/deshabilitar un handler no disponible; no persiste señales ni define proyección de feed.

### Buckets vs visible_actions (MC13F)

| Proyección | Decide | Fuente de verdad |
|------------|--------|------------------|
| **Bucket** (`primary`, `secondary`, …) | En qué sección renderizable aparece el item en esta view | Policy de fuente + estado/señales/facts |
| **visible_actions** | Qué botones mostrar ahora | Capabilities/state ya interpretados por policy de fuente; enricher + policy executable solo formatean |

El enricher pasa `bucket_key` como **contexto de presentación** (p. ej. placement, futuras reglas de layout). **No** debe usarse como sustituto de `can_defer` / `can_dismiss` para Tasks user.

La policy pura `AA_Executable_Visible_Actions_Policy` vive en `includes/domain/executable/` y expone:

```php
AA_Executable_Visible_Actions_Policy::resolve(array $item, array $context = []): array
```

Contexto declarativo mínimo:

```php
[
    'view' => 'active'|'completed'|'ignored',
    'bucket_key' => 'primary'|'secondary'|'default'|'completed'|'ignored',
    'source' => 'system'|'user'|'ai',
]
```

En el ciclo MC11B el feed executable incluye `visible_actions` resueltas por `ExecutableVisibleActionsEnricher` + `AA_Executable_Visible_Actions_Policy`. Desde MC11C el renderer experimental **prefiere `visible_actions`** cuando el array tiene contenido; si está vacío o ausente, mantiene fallback temporal a `primary_action` + `capabilities`. La disponibilidad runtime de handlers (`pwa.install`, standalone, prompt) sigue resolviéndose en JS vía `LearningActionHandlers` (coordinator/options del módulo experimental), no en el renderer.

Para Tasks user, el canal DOM de intents (`defer`/`dismiss`) **no está cableado** todavía; hoy solo `complete`/`pending`/`archive-list`. Ver [User task signals: defer/dismiss futuro (MC13F)](#user-task-signals-deferdismiss-futuro-mc13f).

### Canal DOM por source (renderer experimental, MC11D)

El renderer traduce `visible_actions` a markup legacy-compatible según `item.source`:

| Acción visible | `source=system` (Learning) | `source=user` (Tasks) |
|----------------|----------------------------|------------------------|
| `type=status`, `target_status=done` | `data-learning-action="complete"` + `data-recommendation-key` | `data-tasks-action="complete"` + `data-task-id` |
| `type=status`, `target_status=pending` | *(no renderiza en vista activa)* | `data-tasks-action="pending"` + `data-task-id` |
| `type=handler` | `data-learning-action="primary-handler"` + `data-learning-handler` | — |
| `type=intent` defer/dismiss/reactivate | `data-learning-action` + `data-recommendation-key` | — |
| `type=navigate` | `<a href="...">` | `<a href="...">` |

El feed experimental tiene dos modos debug (MC12A):

**Modo preview** — solo visibilidad del feed:

```js
sessionStorage.setItem('AA_EXECUTABLE_LISTS_DEBUG', '1');
sessionStorage.removeItem('AA_EXECUTABLE_LISTS_ACTIONS_DEBUG');
location.reload();
```

- Sección experimental visible.
- Root `#aa-executable-lists-root` con `inert` y clicks bloqueados.
- No ejecuta mutaciones.

**Modo interactivo debug** — requiere ambos flags:

```js
sessionStorage.setItem('AA_EXECUTABLE_LISTS_DEBUG', '1');
sessionStorage.setItem('AA_EXECUTABLE_LISTS_ACTIONS_DEBUG', '1');
// alternativa: window.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG = true
location.reload();
```

- Quita `inert` del root experimental.
- Inicializa `ExecutableActionsCoordinator` (`executable-actions-coordinator.js`).
- Ejecuta mutaciones **solo** vía servicios existentes; refresca con `ExecutableListsService.getFeed()` sin llamar loaders legacy.

Acciones habilitadas en MC12A (user tasks):

| Markup DOM | Servicio |
|------------|----------|
| `data-tasks-action="complete"` | `TasksService.changeTaskStatus(taskId, 'done')` |
| `data-tasks-action="pending"` | `TasksService.changeTaskStatus(taskId, 'pending')` |
| `data-tasks-action="archive-list"` | `TasksService.archiveTaskList(listId)` (+ confirm) |
| `data-tasks-action="defer"` | `TasksService.deferTask(taskId)` |
| `data-tasks-action="dismiss"` | `TasksService.dismissTask(taskId)` |

Acciones habilitadas en MC12B (Learning simple):

| Markup DOM | Servicio |
|------------|----------|
| `data-learning-action="defer"` | `LearningService.ignoreRecommendation(key)` |
| `data-learning-action="dismiss"` | `LearningService.dismissRecommendation(key)` |
| `data-learning-action="complete"` | `LearningService.completeRecommendation(key)` |

`key` = `data-recommendation-key`.

**Semántica MC12 (dominio/backend, no coordinator):** ver tabla en [Modelo de señales, estado y procedencia](#modelo-de-señales-estado-y-procedencia). Resumen: defer/dismiss/complete **registran señales**; la proyección en buckets/feed es interpretación de policy, no efecto absoluto.

Acciones habilitadas en MC12C (Learning primary-handler):

| Markup DOM | Ejecución |
|------------|-----------|
| `data-learning-action="primary-handler"` + `data-recommendation-key` + `data-learning-handler` | `LearningActionHandlers.run(action, item, ctx)` |

Flujo MC12C:

1. Resolver item desde `lastPayload` vía `findLearningItem(key)` (`origin_key \|\| id`; preferir `source=system`).
2. Resolver `action` desde `item.visible_actions` donde `type=handler` y `handler === data-learning-handler`.
3. Validar `LearningActionHandlers.isAvailable(action, item)` al momento del click.
4. Ejecutar `run`; **no** llamar `LearningService.completeRecommendation`.
5. **Reload** del feed solo si el handler devuelve `{ reload: true }` (p. ej. `pwa.install` devuelve `{ completed: false, outcome }` sin reload automático).
6. Disponibilidad runtime (`beforeinstallprompt`, standalone, `appinstalled`) sigue en `LearningActionHandlers`; el renderer experimental oculta botones vía `buildRenderOptions()`.

No hay action log ni counters en MC12C. `pwa.install` no completa automáticamente la recomendación en BD.

Fuera de alcance MC12C: `reactivate`, navegación `<a href>`, auto-complete post-handler, action log/counters.

**Deuda lista:** ver [Deuda: acciones de lista](#deuda-acciones-de-lista-archive-list).

El feed legacy visible **no se sustituye**; puede quedar desincronizado hasta reload manual en debug.

El coordinator usa delegación en capture sobre cada root inicializado (`#aa-executable-lists-root` experimental, `#aa-executable-user-lists-root` visible MC13A) con `stopPropagation()` para evitar doble ejecución con `tasks-board-module.js` (mismo `#aa-tasks-module-root` ancestro).

## MC13A — feed user visible (infra, no swap)

Flag **`AA_EXECUTABLE_VISIBLE_FEED=user`** (fuentes: `sessionStorage`, `window.AA_EXECUTABLE_VISIBLE_FEED`, opcional `AA_EXECUTABLE_LISTS_DATA.visibleFeed`). **Off por defecto** — producción sin cambios.

Roots nuevos en `index.php` (ocultos por defecto):

| Elemento | Rol |
|----------|-----|
| `#aa-executable-user-lists-visible` | Sección contenedora (violeta, comparación MC13A) |
| `#aa-executable-user-lists-root` | Render target del feed user filtrado |
| `#aa-executable-user-lists-error` | Errores de acciones en el root visible |

Separado del sandbox amber `#aa-executable-lists-experimental`.

Comportamiento con flag **user**:

1. Muestra la sección visible user.
2. `ExecutableListsService.getFeed()` → `lists.filter(l => l.source === 'user')`.
3. Render con `AAExecutableListRenderer.renderFeed(userLists, buildRenderOptions())`.
4. `ExecutableActionsCoordinator.init()` en el root visible con **acciones activas** (no requiere `AA_EXECUTABLE_LISTS_ACTIONS_DEBUG`).
5. `findLearningItem` devuelve `null` en este root (solo user lists).
6. Refresco documentado: `window.AAExecutableUserListsVisibleFeed.reload()` (MC13B cableará post-CRUD legacy).

**No oculta** `#aa-tasks-board-root`, Learning legacy, propuesta ejecutiva ni FABs/modales. Comparación side-by-side antes de MC13B.

**Experimental debug:** si solo `AA_EXECUTABLE_VISIBLE_FEED=user`, el sandbox amber sigue oculto salvo `AA_EXECUTABLE_LISTS_DEBUG=1`. Ambos flags pueden coexistir (coordinator multi-root idempotente por `root.id`).

**`done` en active feed user:** la proyección actual excluye tareas `status=done` del feed activo executable; el board legacy puede seguir mostrándolas — diferencia conocida, no bug MC13A. Sin vista `completed`, sin `Reabrir` en active feed, sin reintroducir `done` al feed activo.

Rollback: `sessionStorage.removeItem('AA_EXECUTABLE_VISIBLE_FEED'); location.reload();`

## MC13B — user-swap (feed principal, legacy oculto)

Nuevo valor de flag: **`AA_EXECUTABLE_VISIBLE_FEED=user-swap`** (mismas fuentes que MC13A). **`user` conserva semántica MC13A** (paralelo).

| Valor | Comportamiento |
|-------|----------------|
| *(off)* | UI legacy |
| `user` | Feed executable user visible + board legacy visible (MC13A) |
| `user-swap` | Feed executable user como render principal; `#aa-tasks-board-root` oculto visualmente |

**Siguen visibles en swap:** `#aa-executive-proposal`, Learning legacy, `#aa-tasks-error`, FABs/modales.

**`tasks-board-module.js` sigue activo:** `loadBoard()` alimenta executive, selector de listas, `lastBoardPayload`, validación FAB “crea una lista primero”. El board legacy se oculta con `hidden`; no se elimina del DOM.

**Refresh post-mutación:**

- CRUD/modales/executive (`tasks-board-module`): `loadBoard({ silent: true })` + `AAExecutableUserListsVisibleFeed.reload()` best-effort.
- Acciones en feed user swap (coordinator): `reloadVisibleUserFeedWithBoardSync()` → feed executable + `AATasksBoard.reload({ silent: true })` para executive/selector.

**API pública:**

- `window.AATasksBoard.reload(options)` — recarga board legacy (executive + selector + render oculto).
- `window.AAExecutableUserListsVisibleFeed.reload()` — recarga feed user executable.
- `AAExecutableUserListsVisibleFeed.isSwapEnabled()` — true solo en `user-swap`.

**Empty swap:** copy con CTA al FAB “+ Nueva lista”.

**`done`:** sin cambios respecto MC13A — fuera del active feed executable; sin vista `completed` ni `Reabrir`.

Activar swap:

```javascript
sessionStorage.setItem('AA_EXECUTABLE_VISIBLE_FEED', 'user-swap');
location.reload();
```

## MC13C — hardening UX user-swap

Sin cambios de backend ni reglas de proyección. Endurece estados visuales del feed user cuando actúa como render principal.

### Loading

- `#aa-executable-user-lists-loading` — texto “Cargando listas…” visible durante `loadVisibleUserFeed()` (inicial, reload post-acción, reload post-CRUD vía best-effort).
- El root `#aa-executable-user-lists-root` se vacía mientras carga.

### Errores unificados

- Carga y acciones del feed user visible → `#aa-executable-user-lists-error`.
- Reload exitoso → limpia error y oculta loading.
- `#aa-tasks-error` sigue reservado a board/modales/executive (no mezclar).

### Empty states

| Caso | Copy / comportamiento |
|------|------------------------|
| Sin listas user (swap) | “Aún no tienes listas propias. Usa el botón flotante + Nueva lista.” |
| Lista user sin tareas pending (all-done u otras) | “No hay tareas pendientes en esta lista.” dentro de la card |
| Modo `user` paralelo | Empty técnico MC13A conservado |

**`done`:** sigue fuera del active feed; no hay Reabrir ni vista `completed`.

### Sync best-effort

Si falla `AAExecutableUserListsVisibleFeed.reload()` tras CRUD legacy, o `AATasksBoard.reload()` tras acción executable, el flujo principal no se rompe; feed o executive pueden quedar stale hasta el siguiente reload manual. Errores silenciosos en `.catch()` best-effort.

### Debug flags

Con `user-swap` + `AA_EXECUTABLE_LISTS_DEBUG=1` puede coexistir feed user principal + sandbox amber completo; útil para comparación, no para producción.

Rollback: `sessionStorage.removeItem('AA_EXECUTABLE_VISIBLE_FEED'); location.reload();`

Proyección común (MC11B):

```php
ExecutableVisibleActionsEnricher::enrich_lists(array $lists, array $context = []): array
```

Se invoca desde `GetExecutableListsFeedUseCase` después de ensamblar listas. Los mappers de fuente siguen produciendo items base sin resolver acciones visibles.

## Mapeo Learning → Executable

Entrada: salida enriquecida de `GetLearningRecommendationsUseCase` (`list_1`, `list_2`, items con `action`, `can_*`).

Salida: **una** `ExecutableList`:

- `source`: `system`
- `id`: `system:learning.recommendations`
- `origin_key`: `learning.recommendations`
- `title`: Recomendaciones
- Bucket `primary` ← items con `effective_list=1` según policy de Learning (label: Principales)
- Bucket `secondary` ← items con `effective_list=2` según policy de Learning (label: Otras sugerencias); proyección interpretada, no posición permanente
- Item `origin_key` = `key` de recomendación
- `can_complete_manually` → `capabilities.can_complete`
- `can_defer` / `can_dismiss` / `can_reactivate` preservados
- `action` navigate/handler → `primary_action` sin re-resolver URLs
- `is_executive_candidate`: `false` (MC7; integración ejecutiva en ciclo posterior)

**No** se reevalúa `AA_Learning_Visibility_Policy`.

## Mapeo Tasks → Executable

Entrada: salida de `GetTaskBoardUseCase` (`lists`, `tasks`, `organization`).

Salida: **una ExecutableList por lista de usuario**:

- `source`: `user`
- `id`: id numérico de lista como string
- Buckets `primary` / `secondary` si `organization.task_bucket_order_by_list[list_id]` existe:
  - `primary` → label `Prioritarias`
  - `secondary` → label `Otras tareas`
- Fallback `default` solo si no existe `task_bucket_order_by_list`, para compatibilidad con fixtures/payloads previos.
- Solo tareas **pending** en buckets activos; tareas con `status=done` (declaración usuario) no se proyectan al feed activo actual
- Tareas ordenadas según ids recibidos por bucket; `organization.task_order_by_list[list_id]` se conserva como orden legacy/compatibilidad.
- `notes` → `description`
- `due_at`, `importance` preservados
- Pending: `can_complete`, `primary_action` status→done (declaración, no verificación)
- Done: reservado para futura vista `completed`; `can_reopen` no implica historial de eventos
- Lista activa: `capabilities.can_archive` en la lista
- `is_executive_candidate` si task id ∈ `organization.executive_candidates`

**No** se reevalúa `AA_Task_Prioritization_Policy` en el mapper. `executive_candidates` sigue siendo independiente de los buckets: alimenta la propuesta ejecutiva global y no define `primary`.

Capabilities defer/dismiss: desde `task_evaluations_by_id[task_id].capabilities` cuando existe evaluación (MC13G-C2); fallback `false`. Ver [User task signals: defer/dismiss](#user-task-signals-deferdismiss-mc13f--mc13g-a--mc13g-b--mc13g-c).

## Fuera de alcance MC7

- UI, JS, AJAX, Schema, repositorios
- Cambios a policies o Use Cases existentes
- Feed unificado en pantalla
- Propuesta ejecutiva multi-fuente
- Backend premium / IA
- Migración de Learning a `aa_tasks`
- Action log, counters y tabla común de eventos (diseño documentado; implementación cuando haya policy consumidora)

## Archivos MC7

- `includes/domain/executable/class-aa-executable-contract.php` — normalizador puro
- `includes/domain/executable/class-aa-executable-visible-actions-policy.php` — policy pura de acciones visibles
- `includes/application/executable/ExecutableVisibleActionsEnricher.php` — enricher común del feed (MC11B)
- `includes/application/executable/LearningRecommendationsToExecutableMapper.php`
- `includes/application/executable/TaskBoardToExecutableMapper.php`
- `tests/application/executable/test-executable-contract-mappers-ac.php`
