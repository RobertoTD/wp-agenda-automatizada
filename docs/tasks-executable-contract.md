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
    'status' => 'pending'|'done',
    'state' => [
        'completed' => bool,
        'ignored' => bool,
        'dismissed' => bool,
        'dismiss_active' => bool,
        'auto_completed' => bool,
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
- **status:** `{ type: 'status', label, to: 'pending'|'done' }` — cambio de estado vía Use Case de la fuente

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

- **capabilities:** posibilidades técnicas o latentes (`can_complete`, `can_reopen`, etc.). No equivalen automáticamente a botones.
- **primary_action:** acción principal temporal usada por el contrato actual y renderers experimentales.
- **visible_actions:** resultado de resolver reglas de vista/bucket/fuente/estado para decidir botones visibles.
- **runtime availability:** decisión final de JS para handlers (`pwa.install`, standalone, prompt disponible). JS puede ocultar/deshabilitar un handler no disponible, pero no debe inventar reglas de negocio de visibilidad.

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

En el ciclo 1B el feed executable ya puede incluir `visible_actions` resueltas por `ExecutableVisibleActionsEnricher` + `AA_Executable_Visible_Actions_Policy`. El renderer experimental **todavía no las consume**; sigue usando `primary_action` y `capabilities` como compatibilidad temporal. La disponibilidad runtime de handlers (`pwa.install`, standalone, prompt) sigue resolviéndose en JS.

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
- Bucket `primary` ← `list_1` (label: Principales)
- Bucket `secondary` ← `list_2` (label: Otras sugerencias)
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
- Solo tareas **pending**; `done` no se proyecta al bucket activo
- Tareas ordenadas según `organization.task_order_by_list[list_id]` (solo pending proyectadas)
- `notes` → `description`
- `due_at`, `importance` preservados
- Pending: `can_complete`, `primary_action` status→done
- Done: fuera del feed activo; `can_reopen` / reabrir reservado para futura vista de completadas
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

## Archivos MC7

- `includes/domain/executable/class-aa-executable-contract.php` — normalizador puro
- `includes/domain/executable/class-aa-executable-visible-actions-policy.php` — policy pura de acciones visibles
- `includes/application/executable/ExecutableVisibleActionsEnricher.php` — enricher común del feed (MC11B)
- `includes/application/executable/LearningRecommendationsToExecutableMapper.php`
- `includes/application/executable/TaskBoardToExecutableMapper.php`
- `tests/application/executable/test-executable-contract-mappers-ac.php`
