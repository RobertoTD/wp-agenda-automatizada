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
    'is_executive_candidate' => bool,
]
```

### primary_action

- **navigate:** `{ type: 'navigate', label, url }`
- **handler:** `{ type: 'handler', label, handler }` — ejecución runtime en JS (handlers registrados)
- **status:** `{ type: 'status', label, to: 'pending'|'done' }` — cambio de estado vía Use Case de la fuente

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
- `includes/application/executable/LearningRecommendationsToExecutableMapper.php`
- `includes/application/executable/TaskBoardToExecutableMapper.php`
- `tests/application/executable/test-executable-contract-mappers-ac.php`
