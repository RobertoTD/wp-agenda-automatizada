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
- Lista: `status` (`active` \| `archived`) — declaración al archivar; no hay `archived_at` ni unarchive hoy.

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
| **Completar** (manual) | Learning: `is_completed`; Tasks: `status=done` | Declaración del usuario; puede sacar el item del feed activo. Distinta de auto-completion por fact. |
| **Reabrir** (Tasks) | `status=pending`, `completed_at=null` | Declaración inversa; no implica evento histórico de “des-completado verificado”. |
| **Archivar lista** | `aa_task_lists.status=archived` | Declaración sobre la lista; tareas conservadas. Acción de **lista**, no de item. |

El coordinator experimental (MC12) **solo ejecuta** la mutación vía servicio y refresca el feed; **no** implementa estas reglas de proyección.

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
- **visible_actions:** proyección de **qué botones mostrar ahora** en esta vista/bucket; resultado de policy + contexto. **No** es historial, contador, lista de acciones posibles ni fuente de verdad de señales.
- **runtime availability:** disponibilidad JS de handlers (`pwa.install`, standalone, prompt). JS puede ocultar/deshabilitar un handler no disponible; no persiste señales ni define proyección de feed.

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

El coordinator usa delegación en capture sobre `#aa-executable-lists-root` con `stopPropagation()` para evitar doble ejecución con `tasks-board-module.js` (mismo `#aa-tasks-module-root` ancestro).

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
- Bucket único `default` (feed activo/normal)
- Solo tareas **pending** en bucket activo; tareas con `status=done` (declaración usuario) no se proyectan al feed activo actual
- Tareas ordenadas según `organization.task_order_by_list[list_id]` (solo pending proyectadas)
- `notes` → `description`
- `due_at`, `importance` preservados
- Pending: `can_complete`, `primary_action` status→done (declaración, no verificación)
- Done: reservado para futura vista `completed`; `can_reopen` no implica historial de eventos
- Lista activa: `capabilities.can_archive` en la lista
- `is_executive_candidate` si task id ∈ `organization.executive_candidates`

**No** se reevalúa `AA_Task_Prioritization_Policy`.

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
