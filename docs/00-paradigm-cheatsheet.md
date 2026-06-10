# Paradigma — Cheatsheet operativo

> Documento corto, pensado para abrirse antes de cada feature.
> Para la referencia larga, ver `docs/02-architecture-principles.md`.

**Paradigma:** Hexagonal ligero + Use Cases + Single Source of Truth en PHP.
**JS proyecta. AI consume. Nadie duplica reglas.**

## Capas y dirección de dependencias (estricta, unidireccional)

```
http (AJAX)  →  application (Use Cases)  →  domain (reglas puras)  →  repositories (SQL)
                                                                 ↘  infrastructure (WP, backend adapters)
ui (JS)      →  http (vía AJAX)
```

- `domain/` no conoce `$wpdb`, `get_option`, `error_log`, `add_action` ni nada de WordPress.
- `repositories/` solo contiene SQL. Cero `if` de negocio.
- `application/` orquesta. No define reglas nuevas.
- `http/` parsea, autentica, delega y serializa. Nada más.
- `infrastructure/` integra con WP, schema, Node backend y notificaciones. El LLM se consume solo vía backend.
- `ui/` (JS) pinta y consume endpoints. No decide nada vinculante.

## Invariantes (no negociables)

1. **Una regla de negocio = un único lugar en PHP.** Si aparece dos veces, es bug.
2. **JS no decide** ocupación, colisión, precio ni estado. Solo pinta y pre-valida.
3. **Cada flujo end-to-end = 1 Use Case PHP** (`VerboCosaUseCase` con un único `execute()`).
   El controlador AJAX y el handler de AI llaman al MISMO Use Case.
4. **Models (futuros Repositories) = SQL puro.** Si un método empieza a "saber" del negocio, se mueve a un domain service.
5. **AI nunca inventa dominio.** Si necesita una regla nueva, primero se crea el service de dominio; luego el evaluator lo llama.

## Decisión rápida — "¿dónde escribo esto?"

| Si lo que voy a escribir es...                | Va en...                                |
| --------------------------------------------- | --------------------------------------- |
| Una query SQL                                 | `includes/repositories/`                |
| Una regla pura del negocio                    | `includes/domain/{contexto}/`           |
| Un flujo que orquesta varias reglas           | `includes/application/{contexto}/{Verbo}UseCase.php` |
| El handler de un request HTTP/AJAX            | `includes/http/ajax/`                   |
| Llamada a WP, Node backend o notifs           | `includes/infrastructure/{adaptador}/`  |
| Pintar DOM, calendarios, modales              | `assets/js/ui/`                         |
| Cliente HTTP que consume endpoint y cachea    | `assets/js/services/`                   |

### Contextos actuales relevantes

- `booking`: creacion/resolucion de citas y disponibilidad asociada.
- `onboarding`: estado de activacion inicial hacia la primera cita.
- `learning`: recomendaciones de producto; el catalogo declara intencion (`action`) y Application normaliza el payload ejecutable.
- `executable`: contrato comun de proyeccion (`AA_Executable_Contract`) para listas/items ejecutables; mappers en `application/executable/` traducen salidas de fuentes (learning, tasks) sin fusionar persistencia. Ver `docs/tasks-executable-contract.md`. **Fuente comun objetivo (MC13O-A/B1):** `docs/tasks-common-source-target.md` documenta el destino y MC13O-B1 prepara schema base y MC13O-B2 sube a `DB_VERSION=7` agregando `aa_task_actions` como tabla task-only de acciones declaradas. **MC13O-C1/C2:** seed/sync manual del catalogo Learning hacia DB comun via `SyncLearningCatalogToTasksUseCase`, `SeededTaskRepository` y `TaskActionRepository`; no hay consumers ni hooks automaticos. **MC13O-D1A:** el read path de Tasks ya interpreta metadata comun (`source_category`, `origin_key`, `managed_by`, `default_bucket`) y `aa_task_actions` para seeded `agenda_app`. **MC13O-D2:** `GetExecutableListsFeedUseCase` prefiere la lista seeded DB comun (`agenda_app` + `learning.recommendations` activa con tareas en payload) y omite el mapeo Learning legacy; sin seeded lista conserva fallback legacy. **MC13O-D3:** `AA_Learning_Catalog_Seed_Lifecycle` corre sync en `admin_init` prioridad 20 controlado por `AA_Learning_Catalog::SEED_VERSION` vs `aa_learning_catalog_seed_version`; sync archived-first y activa lista solo al validar seed completo. No reemplaza migracion de schema (`AA_Schema`). **MC13O-E1:** `TaskSystemCompletionFactResolver` + `EvaluateTaskSystemCompletionFactsUseCase` evaluan `completion_fact_key` y persisten `completed_by_system` en `aa_task_state` sin mezclar con `status=done`. **MC13O-E2:** `GetTaskBoardUseCase` ejecuta el evaluator al inicio; active projection distingue `status=done` (manual) de `completed_by_system` (system); `completion_type=system` no expone completar manual en mapper. **MC13O-F1:** `MigrateLearningRecommendationStateToTaskStateUseCase` migra manual/idempotente completion manual e ignored→defer; no transportar dismissed/aging/list_override al motor comun todavia. **MC13O-F2:** `AA_Learning_State_Migration_Lifecycle` corre en `admin_init` prio 21 tras seed (`aa_learning_state_migration_version`); dismissed sigue sin migrar. **MC13O-F3:** `GetExecutableListsFeedUseCase` omite Learning legacy solo si DB seeded lista y (migración al día o sin legacy actionable); `is_dismissed` cuenta como actionable. **Labels de buckets (MC13O-0):** copy canónico `primary→Principales`, `secondary→Secundarias` vive en el contrato; los mappers no inventan labels por fuente. **Listas MVP (MC13J):** feed unified executable por defecto; única vista oficial. **MC13J-2C:** modos `user`/`user-swap` retirados; DOM user-only eliminado; `AAExecutableUserListsVisibleFeed` conserva nombre (opera sobre unified). **Procedencia de lista es metadata del contrato** (`source`, `source_category`, `source_label`); el renderer solo presenta `source_label`, no inventa copy primario (MC13M). `source` sigue siendo canal técnico; no motivo de secciones UI separadas (MC13H unified). **Señales/estado/eventos:** las acciones del usuario registran señales interpretables por policies; no son reglas absolutas. **Tasks active (MC13G-D):** `AA_Task_Active_View_Projection_Policy` proyecta buckets defer/dismiss; `AA_Task_Prioritization_Policy` solo ordena. **Buckets:** `primary`/`secondary`/`default` son resultado de una `view`; las policies interpretan criterios, los renderers solo presentan.

### Learning: acciones primarias

- El catalogo (`includes/domain/learning/`) puede declarar `action` opcional como intencion: `navigate`, `handler` o `null`.
- `GetLearningRecommendationsUseCase` normaliza esa intencion: resuelve URLs para `navigate`, valida `handler`, y conserva `navigation` como adapter legacy.
- La UI solo renderiza/ejecuta el contrato ya normalizado; la disponibilidad runtime de handlers (ej. PWA install) vive en JS, no en PHP.
- `learning-action-handlers.js` registra handlers por clave estable. Un handler solo se renderiza si existe, tiene `run` y `isAvailable(action, item)` confirma disponibilidad.
- `pwa.install` es el primer handler real: captura `beforeinstallprompt`/`appinstalled` en closure (sin `window.deferredPrompt`) y solo está disponible si hay prompt diferido y no se está en modo standalone.

## Naming (para archivos NUEVOS, no migramos lo viejo todavía)

- **PHP clases:** `AA_{Contexto}_{Rol}` → archivo `class-aa-{contexto}-{rol}.php`
- **Use Cases:** `{Verbo}{Cosa}UseCase` → archivo `{Verbo}{Cosa}UseCase.php` (PascalCase)
- **JS:** `camelCase.js`, `export default`

Lo viejo coexiste con su nombre histórico hasta que se toque por otra razón.

## Antes de añadir código, pregúntate:

- ¿Estoy creando una segunda fuente de verdad? → si sí, **para**.
- ¿El controller/handler tiene lógica que no sea "parsear y delegar"? → extrae a Use Case.
- ¿El JS está calculando algo que el PHP debería calcular? → mueve el cálculo a PHP.
- ¿Esta regla podría correr en CLI sin WordPress? → entonces es **dominio**, no infrastructure.
- ¿Esto representa una intención del usuario formulable como "verbo + cosa"? → es un **Use Case**.

## Glosario mínimo

- **Domain:** reglas que serían ciertas aunque WordPress no existiera. Sin SQL, sin WP. Determinista y testeable.
- **Use Case (Application):** orquestador de un flujo del producto. Una clase, un método público (`execute`). No define reglas, las coordina.
- **Repository:** acceso a BD. Solo SQL. Sin `if` de negocio.
- **Controller AJAX:** traductor HTTP↔Use Case. Autentica, sanitiza, delega, serializa.
- **Infrastructure:** todo lo que toca el mundo exterior (WP, MySQL via repos, LLMs, webhooks, notifs).
- **UI:** todo lo que pinta y captura interacción. No es fuente de verdad. **Herramientas de área** (MC13I desarchivar, MC13N regresar ignoradas) viven en menú discreto `#aa-lists-area-tools` (MC13N-2), no como `data-tasks-action` de item/lista visible. **Listas colapsables (MC13L):** solo presentación en `executableListRenderer` (`<details>`/`<summary>`); sin persistencia ni cambio de feed/policies.

## Veda de los Models (regla operativa)

A partir de ahora, los archivos de `includes/models/` están **en
congelación** para añadidos:

- ❌ **NO se añaden métodos nuevos** a `AssignmentsModel` ni a
  `ReservationsModel`.
- ✅ **Métodos SQL nuevos** van a `includes/repositories/AssignmentsRepository.php`
  o `includes/repositories/ReservationsRepository.php`.
- ✅ **Reglas de negocio nuevas** (incluso si "necesitan datos") van a
  `includes/domain/{contexto}/`. El Domain Service llama al Repository
  para los datos crudos.

Los métodos existentes en los Models siguen funcionando y son llamables
también vía el Repository (por herencia), así que **no hay urgencia de
migrar consumidores**. La migración es por contagio:

> *Cuando toques un consumidor por otra razón (feature nueva, fix de bug,
> refactor), aprovecha y cambia su llamada de `AssignmentsModel::foo()`
> a `AssignmentsRepository::foo()`. Si el método mezcla SQL + reglas,
> ese es el momento de descomponerlo.*

Cuando ningún consumidor importe ya los archivos de `includes/models/`,
podremos vaciarlos y finalmente eliminarlos.

### Métodos del Model con deuda explícita (a descomponer al tocarlos)

Métodos identificados que mezclan SQL + semántica de negocio. Cuando se
toquen por una feature, fix o refactor, se parten en dos: la parte SQL
queda en el Repository correspondiente, la regla "qué cuenta como X" se
mueve a un Domain Service en `includes/domain/{contexto}/`.

#### En `AssignmentsModel`

- `AssignmentsModel::get_busy_ranges_by_assignment_ids()` — define qué
  ventanas se consideran "ocupadas" para una asignación.
- `AssignmentsModel::create_assignment()` — embebe la regla "una zona no
  puede tener dos asignaciones activas que se traslapen" y devuelve
  `['error' => 'La zona seleccionada ya tiene una asignación en ese horario']`
  como mensaje de negocio.
- `AssignmentsModel::is_service_public_calendar()` — regla de
  compatibilidad hacia atrás: `true` si la columna `public_calendar` no
  existe (legacy) o si existe y vale `1`.
- `AssignmentsModel::delete_service()` — semántica "ocultar servicio =
  `is_hidden=1` + `active=0`" embebida en el UPDATE.
- `AssignmentsModel::delete_assignment()` — semántica "ocultar asignación =
  `status='inactive'` + `is_hidden=1`" embebida en el UPDATE.
- `AssignmentsModel::get_service_areas($only_active = true)` — embebe la regla "activa = active=1" en SQL. Al descomponer: SQL parametrizado → Repository, definición de "activa" → Domain.

#### En `ReservationsModel`

- `ReservationsModel::has_confirmed_staff_overlap()` — regla "qué cuenta
  como overlap entre reservas confirmadas del mismo staff".
- `ReservationsModel::get_pending_conflicts_for_staff_overlap()` — regla
  de "conflicto" entre pendientes vs confirmadas del mismo staff.
- `ReservationsModel::get_pending_conflicts_overlapping()` — regla de
  "conflicto" entre pendientes que se traslapan en tiempo.
- `ReservationsModel::get_internal_busy_slots()` — define qué cuenta como
  "fixed busy slot" (`estado='confirmed'` + `assignment_id IS NULL` +
  `not finished`).
- `ReservationsModel::get_pending_conflicts()` — `@deprecated`. Define
  "conflicto" como coincidencia exacta de `fecha`. Cuando se elimine al
  consumidor, retirar también el método.
- `ReservationsModel::get_confirmed_overlap_in_area()` — la regla de
  overlap (`r.fecha < end AND DATE_ADD(...) > start`) y el filtro
  `estado='confirmed'` viven embebidos en el SQL; al descomponer, la
  parte SQL queda en `ReservationsRepository` y la decisión "qué cuenta
  como ocupación" se ancla en `AA_Area_Availability_Service` (que ya la
  consume).

> Esta lista no es exhaustiva. Si al tocar un consumidor descubres otro
> método del Model que mezcle SQL + reglas, añádelo aquí en el momento.
