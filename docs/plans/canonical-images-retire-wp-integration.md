# Exploración: retiro canónico WP, mandatos de limpieza y cuota

**Estado:** incrementos 1–2 implementados en `dev/canonical-images-retire`. Incremento 2: schema `DB_VERSION=28`, corrida durable, captura recuperable, tandas exactas y exclusión de escritores. Todavía sin botones de eliminar, HTTP accept/seal, DELETE de producto, UI Continuar ni cron/workers.
**Fecha:** 2026-09-14.
**Ámbito:** integración WordPress del retiro de registros/adjuntos canónicos y liberación de cuota, reutilizando `accept` / `seal` / `status` del backend.

No modifica el state de la prueba Backend 3 (worker local Storage, `docs/ops/attachment-delete-worker-local-storage-state.json`).

## Referencias actuales

| Repo | Rama | HEAD |
|------|------|------|
| `wp-agenda-automatizada` | `dev/canonical-images-retire` | incremento 2 sobre `0ea7723bc6cf15fedf1585999fc8572c5d4d9733` |
| `deoia-oauth-backend` (solo contratos) | `dev/backend-recovered` | `b4d868ced6ddedb4d81ab30ce0e746c91736ab1b` (untracked ajeno: `scripts/runner-from-pack.sh`) |

**Backend Storage fixture (no reejecutada aquí):** immediate / later / reappear **PASS**. El caso reappear fue **sintético** (no un PUT tardío real del proveedor).

La cuota se libera con el retiro SQL local confirmado. La limpieza física durable corresponde al backend; WP no lleva un segundo ledger de objetos Storage.

## Producto acordado (no reabrir)

- El backend acepta durablemente las obligaciones de limpieza (`accept` / `seal` / `status`).
- WordPress retira filas y libera cuota al confirmar el DELETE SQL local.
- El usuario no espera la limpieza física de Storage.
- Retirar un **contenedor** exige acreditar todo el inventario correspondiente y cerrar la recepción (sellado) antes del retiro local.

## Clasificación canónica

Este hito es **capability `images` + runtime WP** (IMG-5 de facto). No es shell, no es familia nueva, no es API pública.

## Tensión normativa (reportada, no reinterpretada)

`docs/05-canonical-capabilities.md` §12.4 exige que borrar imagen/registro/lista elimine los objetos asociados (incluso con `images` inactive) y que un fallo muestre «Eliminación incompleta» + Continuar.

El producto acordado no espera Storage en el request del usuario.

**Lectura operativa (no reescribe la constitución):** «objetos asociados» se acreditan con obligación durable en el backend + worker asíncrono; WordPress no vuelve a llamar `POST /expediente/attachments/delete` en el camino canónico. Si esa lectura se rechaza, detenerse: no «mejorar» §12.4.

## Tensión con el plan Shell Base (hechos vs decisión)

`docs/plans/shell-canonical-base-v1.md` § Continuidad de borrado describe un **purge Storage-first** (cursor de imágenes/ops, presupuesto por petición, DELETE SQL solo con inventario vacío). Eso coincide con Expedientes Ciclo B, no con el producto de mandatos.

**Decisión recomendada:** el orden físico del plan queda **superseded** para el camino canónico. Se conservan inventario, cursor, exclusión mutua de purge, UX de incompleto y FKs RESTRICT. No se reescribe el plan en este documento hasta el primer incremento autorizado.

---

## Hechos comprobados vs decisiones pendientes

### Hechos

1. Existen **dos mundos** de adjuntos. El contrato de mandatos es **`canonical_v1` only**. Expedientes (paths `client_v1` / equivalentes) siguen usando delete síncrono Storage-first. Este hito **no** sustituye Ciclo B.
2. `AA_Expediente_Attachments_Backend_Client` reutiliza `aa_send_authenticated_request` para authorize / finalize / sign-read / `delete_object` **y** (incremento 1) `accept_delete_batch` / `seal_delete_mandate` / `get_delete_mandate_status`. `installation_id` lo deriva el backend del cliente HMAC (`verifyClient` → `agenda_clients` → `resolveExpedienteAttachmentContext`); el cliente WP **no** lo envía en el body. El delete síncrono `POST /expediente/attachments/delete` no cambia.
3. Delete canónico de registro/contenedor **no** toca imágenes ni Storage: `WriteCanonicalShellRecordUseCase::delete` / `WriteCanonicalShellContainerUseCase::delete` → gateway → `CanonicalRelationalRepository`.
4. FK: `aa_canonical_records.container_id` → contenedores **CASCADE**; `aa_canonical_record_images.record_id` y `aa_canonical_image_upload_operations.record_id` → registros **RESTRICT**. Borrar un registro o contenedor **con imágenes u ops** falla en MySQL. Sin imágenes ni ops, el delete actual funciona.
5. `aa_canonical_purge_runs` + `aa_canonical_purge_inventory_items` (`DB_VERSION=28`). `mandate_id` estable al abrir. Captura congelada por páginas con checkpoints de **fuentes** (`images_read_after_id` / `ops_read_after_operation_id`). `has_blocking_purge` / `has_blocking_purge_for_container` bloquean attach, confirmación SQL y el delete de shell actual. `last_accepted_batch_seq` / `sealed_at` permanecen NULL hasta evidencia remota (inc. 3).
6. Cuota: `AA_Installation_Storage_Usage::{confirmed_bytes, reserved_bytes, admission_used_bytes}`. Confirmados = `SUM(aa_expediente_adjuntos.byte_size)` + `SUM(aa_canonical_record_images.byte_size)`. Reservados = ops `admitted` vigentes. **No hay RPC de «liberar cuota»:** desaparece la fila confirmada (o la reserva) y el SUM baja.
7. Lectura de imágenes para UI (`find_public_rows_by_record_ids_for_container`) **no** incluye `upload_operation_id`, `content_sha256` ni `storage_path`. Accept necesita esos campos.
8. Backend: max 50 ítems/tanda; fingerprint por tanda; replay idéntico → `already_accepted`; misma identidad+metadatos otro mandato → `already_obligated` persistido; `status` recupera respuesta perdida; seal vacío (`expected_batch_count=0`) acredita inventario **entregado vacío** (WP no debe sellar vacío si el alcance local tenía imágenes); tombstone + advisory lock de identidad serializa accept vs `begin_canonical_upload_issuance`.
9. UI canónica de delete: éxito `confirmed` + redirect; `uncertain` 409 bloquea retry («Recargar lista»). No existe aún el estado «Eliminación incompleta».
10. `images` permanece `is_ready=false`. IMG-4 es el último incremento de imágenes cerrado. Borrado/purge está explícitamente excluido.

### Decisiones de producto (cerradas; no reabrir)

- Extender `aa_canonical_purge_runs` + tabla de inventario (sin FK a records/containers).
- Un mandato por corrida de alcance (record XOR container). Contenedor vs registro hijo, en ambos órdenes → overlap, no segundo mandato. Dos registros de la misma lista sí pueden tener mandatos distintos (el lock del contenedor serializa la captura).
- Inventario = imágenes confirmadas ∪ ops `admitted|cleanup_needed` (el vencimiento comercial **no** excluye la op).
- Continuar rehidrata la corrida; cron sigue fuera.
- Expedientes Ciclo B **no** migra a mandatos en este hito.

---

## 1. Mapa del flujo ACTUAL

### 1.1 Puntos de entrada

```
Adjunto expediente (legacy)
  AJAX aa_delete_expediente_adjunto[_for_expediente]
    → DeleteExpedienteAdjuntoUseCase / DeleteExpedienteAdjuntoForExpedienteUseCase
    → lock agregado → HMAC POST /expediente/attachments/delete (Storage síncrono)
    → DELETE metadata local si status deleted|already_absent

Registro expediente
  DeleteExpedienteRegistroForExpedienteUseCase
    → lock → preflight → mismo delete Storage+metadata por adjunto → DELETE registro

Contenedor expediente (Ciclo B)
  AJAX aa_delete_expediente → DeleteExpedienteUseCase
    → preflight JOIN adjuntos → Storage+metadata por página (100)
    → TX corta: DELETE registros + padre. Progreso parcial reintentable.

Registro canónico
  AJAX aa_delete_canonical_record → WriteCanonicalShellRecordUseCase::delete
    → DELETE aa_canonical_records (+ amount CASCADE). Sin inventario, sin HMAC delete.

Contenedor canónico
  AJAX aa_delete_canonical_container → WriteCanonicalShellContainerUseCase::delete
    → DELETE contenedor; registros por FK CASCADE. Comentario AJAX: «exclusivamente CASCADE».
    Sin preflight de imágenes. Si hay images/ops, InnoDB RESTRICT aborta.
```

Attach canónico (para contraste de locks y cuota):

```
AJAX aa_attach_canonical_record_image
  → UploadCanonicalRecordImageUseCase
  → lock canonical_container → has_blocking_purge → lock storage_quota
  → authorize HMAC (path_contract=canonical_v1) → PUT → finalize
  → TX: INSERT aa_canonical_record_images + DELETE ops + touch contenedor
```

### 1.2 Tablas de imágenes y operaciones

| Tabla | Rol | Relación con delete actual |
|-------|-----|----------------------------|
| `aa_canonical_record_images` | Original confirmado; `UNIQUE(upload_operation_id)`; `byte_size` cuenta cuota | Nadie las borra en el delete de registro/lista |
| `aa_canonical_image_upload_operations` | `admitted` / `cleanup_needed`; reserva si admitted vigente | Igual; FK RESTRICT impide borrar el registro |
| `aa_canonical_purge_runs` | Corrida durable: `mandate_id`, checkpoints de fuentes, tandas, aceptación remota NULL hasta inc. 3 | Bloquea attach, confirmación SQL y el delete de shell actual |

### 1.3 Transacciones y locks

- Delete canónico: TX corta InnoDB (registro + touch contenedor) **o** DELETE contenedor sin TX explícita larga. **No** hay HTTP dentro de esa TX hoy porque no hay HTTP.
- Expediente: **GET_LOCK nombrado** se mantiene durante HTTP Storage (no es transacción InnoDB). Invariante a conservar: **no abrir TX SQL durante HMAC**.
- Cuota: `AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA` / id `1` (instalación).
- Contenedor canónico: `SCOPE_CANONICAL_CONTAINER` / `container_id`.

### 1.4 Cliente autenticado backend

`includes/auth-helper.php` → `aa_send_authenticated_request`: HMAC con `aa_client_secret`, `X-Client-Id` = dominio tenant. El backend resuelve `installation_id`. Reutilizar el mismo transporte; añadir métodos de mandato en el cliente de adjuntos (o un cliente hermano en `includes/infrastructure/backend/`), no un segundo esquema de auth.

---

## 2. Propuesta mínima de integración

Un solo diseño: **corrida de retiro canónico (purge_runs) + mandato HMAC + DELETE SQL local**. El worker de Storage no forma parte del request WP.

### 2.1 Orden (contenedor; el registro es el mismo recorte)

1. Access Policy + existencia del alcance.
2. **GET_LOCK** `canonical_container` (y, al mutar cuota, `storage_quota`). Sin `BEGIN` abierto durante HTTP.
3. Abrir o reanudar `aa_canonical_purge_runs` de forma atómica bajo el lock: `mandate_id` estable, alcance `record` XOR `container`. El bloqueo durable queda confirmado **antes** de capturar. Alcances solapados (lista vs registro hijo) no abren un segundo mandato.
4. Captura recuperable por páginas (inc. 2, ya implementada): copiar imágenes confirmadas ∪ ops `admitted|cleanup_needed` a `aa_canonical_purge_inventory_items`. Checkpoint de **fuentes**, no de inventario copiado. Ítems + checkpoint en la misma TX. Sin TX larga ni HTTP.
5. Al agotar ambas fuentes sin conflicto: preparar tandas inmutables 1..50 (o cero tandas si el inventario está vacío). `capture_complete=1`.
6. **Incremento 3:** enviar las tandas **ya persistidas** a `POST …/delete-mandates/accept` (`upload_operation_id`, `wp_record_id`, `content_sha256`, `byte_size`, `storage_path` opcional). No reconstruir la tanda desde las tablas vivas. Replay/`status` con el mismo `mandate_id` + `batch_seq`.
7. **Incremento 3:** `POST …/delete-mandates/seal` con `expected_batch_count` = tandas preparadas (0 si vacío y el alcance lo exige). Exigir `inventory_status=sealed` / `structural_retire_authorized`.
8. **TX corta InnoDB** (tras HTTP cerrado): DELETE imágenes y ops del inventario acreditado; luego DELETE registro(s); si alcance contenedor, DELETE contenedor. Commit → cuota confirmada baja sola.
9. Marcar corrida `completed`. Soltar locks.
10. Storage: el worker backend (otro proceso, aún opt-in) limpia objetos. WP no espera.

### 2.2 Piezas a reutilizar

| Pieza | Uso |
|-------|-----|
| `AA_Expediente_Attachments_Backend_Client` + `aa_send_authenticated_request` | **Hecho (inc. 1):** `accept_delete_batch` / `seal_delete_mandate` / `get_delete_mandate_status` |
| Repos de imágenes y ops | **Hecho (inc. 2):** `list_capture_page_after_id` / `list_capture_page_after_operation_id` |
| `CanonicalPurgeRunsRepository` + inventario | **Hecho (inc. 2):** abrir/reanudar, checkpoints de fuentes, overlap; `CanonicalPurgeInventoryItemsRepository` reconstruye tandas |
| Locks `canonical_container` + `storage_quota` | Misma exclusión que attach |
| `CanonicalMutationReceipt` uncertain | Commit local ambiguo |
| Backend RPCs ya desplegados | No nuevas migraciones PG para WP |

### 2.3 Registro sin imágenes

Inventario vacío real → seal 0 → DELETE registro (FK permite) → `confirmed`. Comportamiento de shell actual **conservado** para filas sin images/ops; el único añadido es el seal vacío (o omitirlo — **decisión**: sellar 0 para que el contenedor padre no pueda «olvidar» un registro vacío al retirar la lista; en delete de **un** registro vacío el seal 0 es barato y unifica el cierre).

**Pendiente menor:** en delete de un solo registro vacío, ¿seal 0 o skip HMAC? Recomendación: **skip HMAC** si snapshot vacío **y** alcance = un registro (menos dependencia de backend para el caso mayoritario actual). En delete de **contenedor**, siempre seal (0 o N) porque el contrato de lista lo exige.

### 2.4 Qué no hacer

- No llamar `POST /expediente/attachments/delete` en el camino canónico.
- No CASCADE de imágenes: borrar imágenes **antes** del registro, en la misma TX corta post-seal.
- No abrir `BEGIN` antes del HMAC.
- No activar `images` UI ni el worker.

---

## 3. Concurrencia y recuperación

### 3.1 Subida/confirmación concurrente con eliminación

- **WP:** `GET_LOCK` `canonical_container` cubre abrir corrida + captura de páginas de esta petición. Attach ya espera ese lock y `has_blocking_purge`. Confirmación reconsulta `has_blocking_purge` **antes** del INSERT (también dentro de la TX). El delete de shell actual, si está cableado con repos+lock, no escribe tras una corrida abierta.
- **Backend (inc. 3):** advisory lock de identidad: `begin` tras tombstone → `deletion_accepted`. Un PUT huérfano no crea fila WP si confirm está bloqueado; ops admitted del inventario congelado se mandan al mandato.
- Confirm que completa **antes** de que la purga adquiera el lock: su INSERT entra en la captura. Confirm que intenta escribir **después** de abierta la corrida: exclusión antes de INSERT. No se relee el inventario vivo para armar tandas.

### 3.2 Inventario congelado (incremento 2; sustituye la relectura viva)

La captura copia imágenes y ops del alcance a `aa_canonical_purge_inventory_items` bajo `GET_LOCK` `canonical_container`. El checkpoint de lectura es el de las **fuentes** (images/ops), no un cursor sobre el inventario ya copiado.

- Deduplicación por `upload_operation_id`. Misma identidad y metadatos → un ítem (`source` image|operation|both). Metadatos incompatibles → `item_metadata_conflict`, `capture_complete` permanece 0. Campos obligatorios ausentes → `item_incomplete`, nunca omisión silenciosa.
- Página de hasta 50 filas: ítems + checkpoint en la **misma TX**. Reintentar una página no duplica (UNIQUE) ni salta (el checkpoint solo avanza si esa TX confirma).
- `capture_complete` solo cuando ambas fuentes se recorrieron sin conflicto.
- Tandas 1..50 se asignan **después** de completar la captura (`batch_seq` / `position_in_batch`). Una tanda preparada no gana, pierde ni cambia ítems. Inventario vacío → `prepared_batch_count=0`, ninguna tanda vacía. No hay fingerprint local: el hash lo calcula el backend en accept.
- `last_accepted_batch_seq` / `sealed_at` son aceptación remota; este incremento no los escribe.
- Confirmación que termina **antes** de que la purga adquiera el lock entra en la captura. Un escritor que intenta INSERT después de abierta la corrida ve `has_blocking_purge` **antes** de escribir. El delete de shell actual también queda bloqueado; el nuevo retiro aún no está cableado al botón.

### 3.3 Accept remoto confirmado + fallo SQL local

El mandato/obligación **ya es durable**. Reintento: `status` o replay accept → no nueva obligación. Corrida `incomplete` + mismo `mandate_id`. DELETE local idempotente (0 filas = ya retirado). Cuota no se «revierte» en el backend: nunca se incrementó por el mandato.

### 3.4 Respuesta remota perdida

`GET/POST …/delete-mandates/status` con `mandate_id` + `batch_seq` opcional. Fingerprint persistido. No inventar un segundo `mandate_id` para el mismo `purge_runs.id`.

### 3.5 Commit local ambiguo

Ya modelado: `CanonicalRelationalAmbiguousOutcome` → `uncertain`, UI «Recargar lista», **sin retry ciego**. Tras recarga: si filas de imagen/registro siguen, Continuar (mismo mandate). Si desaparecieron, corrida → `completed` (reconciliar por SELECT, no por re-DELETE).

### 3.6 Reintentos sin doble retiro ni doble cuota

La cuota no es un contador mutable: es `SUM` de filas restantes. Idempotencia = no reinsertar imágenes y DELETE afectados por PK/`upload_operation_id`. Liberar reserva: DELETE o `cleanup_needed` de ops (si se retiran en la TX post-seal, la reserva desaparece; si el worker backend borra Storage de un admitted nunca confirmado, WP igual debe borrar la fila ops para no dejar reserva zombie — eso entra en el inventario).

### 3.7 Cerrar el navegador

Impide olvidar trabajo: **fila `aa_canonical_purge_runs` + `mandate_id` en MySQL**, no estado del SPA. UI «Eliminación incompleta» + Continuar rehidrata la corrida. El worker de Storage es independiente (obligaciones ya aceptadas). Incremento 1 no necesita WP-Cron; si se quiere drenaje sin usuario, eso es un incremento posterior (mismo UseCase, trigger cron).

GET_LOCK se pierde al cerrar el request PHP: otro request de Continuar re-adquiere el lock. Eso es correcto.

---

## 4. Escala y experiencia

### 4.1 Contenedores grandes

Presupuesto por petición AJAX (p. ej. 1–3 tandas de 50 **o** deadline ~10–15 s), luego `incomplete` + cursor. La UI reenvía Continuar. Un contenedor de 10k imágenes = ~200 accepts + 1 seal + 1 TX local (o TX local por página de DELETE de imágenes si el commit único es demasiado grande — **decisión:** DELETE local por chunks **después** de sealed, aún bajo lock; el sello ya acreditó el inventario; chunks locales no reabren recepción).

**Hecho:** el backend no exige que el DELETE WP sea atómico con el sello. **Recomendación:** tras seal, drenar imágenes/ops por keyset en la misma o siguientes peticiones **antes** de borrar registros/contenedor (RESTRICT lo obliga).

### 4.2 Estados de interfaz

| Estado | Origen | UI |
|--------|--------|----|
| `confirmed` | Seal (o skip HMAC vacío) + commit local OK | Redirect lista (hoy) |
| `incomplete` | Presupuesto agotado o HMAC/SQL recuperable | «Eliminación incompleta» + Continuar (§12.4) |
| `uncertain` | Commit ambiguo | Recargar; no retry |
| `deletion_accepted` / attach bloqueado | Tombstone o purge abierta | Attach: error estable ya mapeable |
| `not_found` | Recurso ya ausente | Idempotente éxito o 404 según política actual del AJAX |

Registros **sin imágenes:** camino corto, misma UX `confirmed` que hoy.

### 4.3 Capability inactive

El inventario se acredita igual (§12.4). No hay galería; el delete de registro/lista del shell es el disparador.

---

## 5. Plan de implementación (un diseño, incrementos pequeños)

### Incremento 0 — contrato en docs del plugin (este archivo)

Cerrar las dos decisiones menores (seal 0 en registro vacío; chunks DELETE post-seal). Sin runtime.

### Incremento 1 — cliente HMAC de mandatos — **completado**

**Archivos:** `includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php`; tests `tests/infrastructure/backend/test-aa-expediente-attachments-delete-mandates-client-ac.php`.

**Schema:** ninguno. Delete de shell, cuota, UI, `is_ready` y Expedientes: sin cambios.

**Contrato HTTP verificado en backend actual** (`routes/expedienteAttachments.js` + RPC SQL `20260913`):

| Método | Ruta | Body (sin `installation_id`) | Éxito que el cliente acepta |
|--------|------|------------------------------|-----------------------------|
| POST | `/expediente/attachments/delete-mandates/accept` | `path_contract=canonical_v1`, `mandate_id`, `batch_seq` ≥ 0, `items` 1..50 | `batch.acceptance` ∈ {`accepted`,`already_accepted`} + ítems completos. **`retire_authorized` del cliente = false** |
| POST | `/expediente/attachments/delete-mandates/seal` | `mandate_id`, `expected_batch_count` ≥ 0 | `seal.outcome` ∈ {`sealed`,`already_sealed`} **y** `inventory_status=sealed` **y** `structural_retire_authorized=true` |
| POST | `/expediente/attachments/delete-mandates/status` | `mandate_id`; opcional `batch_seq`, `item_cursor`, `item_limit` 1..100 | `found:false` (consulta válida, no acredita) o cabecera `found:true` completa |

El cliente **no** genera `mandate_id` ni `batch_seq`. Una respuesta perdida se reconcilia con las mismas identidades vía `status` o replay idéntico.

**Evidencia real de `status` (límites para el orquestador):**

- `found: false` — el mandato no existe en esta instalación. No acredita accept ni retiro. Reintentar las **mismas** identidades; no mintar otras.
- `found: true` + `batch_seq` + objeto `batch` con `fingerprint` e `items` (`can_credit_batch=true`) — acredita esa tanda (equivalente a `already_accepted`). El `jsonb_agg` de la tanda **no** está paginado.
- `found: true` + `batch_found: false` — esa `batch_seq` no se persistió. No acreditar la tanda; reenviar el **mismo** `batch_seq`.
- Sin `batch_seq`, `can_credit_batch=false`. El array `items` de cabecera es una **página** (`items_page.has_more` / `next_cursor`). Si `has_more`, no se puede afirmar cobertura total del mandato.
- `structural_retire_authorized` ⇔ `inventory_status=sealed`. Autoriza retiro estructural remoto; **no** certifica el DELETE SQL de WordPress.
- `physical_status` en ítems de status es observabilidad del worker, no autorización WP.

Payload incompleto, error, 5xx, WP_Error o `ok` sin contrato → `ok:false` + `retire_authorized:false`. Clases: `auth` / `conflict` / `invalid_request` / `not_found` / `unreachable` / `malformed` / `unknown`.

**Pruebas:** `php tests/infrastructure/backend/test-aa-expediente-attachments-delete-mandates-client-ac.php` (dobles HTTP).

### Condiciones obligatorias de los incrementos 2–4

El orquestador posterior es dueño de identidades y recuperación. Debe cumplir:

1. **Tandas reproducibles exactamente después de una interrupción.** Persistir `mandate_id`, `batch_seq` y el conjunto ordenado de ítems (o el fingerprint local equivalente) **antes** del POST accept. Tras timeout: `status` con ese `batch_seq`, o replay idéntico. Nunca un mandato o secuencia nuevos como reacción automática a respuesta perdida.
2. **Exclusión de todos los escritores, incluidas confirmaciones en curso.** Abrir la corrida de purge **antes** del primer HMAC. Lock `canonical_container` (+ `storage_quota` al mutar cuota) cubre attach, confirm TX de imágenes y delete. Confirm en vuelo que aún no tiene lock no debe insertar filas a espaldas del snapshot sellable.
3. **Inventario deduplicado por `upload_operation_id` con metadata coherente.** Una identidad, una fila de ítem. SHA / `wp_record_id` / `byte_size` / path derivados deben coincidir entre imagen confirmada, op `admitted|cleanup_needed` y el body de accept. Duplicados locales → no enviar; `item_metadata_conflict` remoto → no sellar.
4. **Purge durable recuperable mediante Continuar; cron todavía fuera.** `aa_canonical_purge_runs` (u homólogo) rehidrata mandato y cursor. UI «Eliminación incompleta» + Continuar. WP-Cron **no** forma parte de 2–4.

### Incremento 2 — inventario + persistencia de corrida — **completado**

**Schema (`DB_VERSION=28`):** columnas de captura en `aa_canonical_purge_runs` (`mandate_id`, `container_id`, checkpoints de fuentes, `capture_complete`, `batches_prepared`, `prepared_batch_count`, `last_accepted_batch_seq` NULL, `sealed_at` NULL, `capture_conflict_code`). Tabla `aa_canonical_purge_inventory_items` (FK CASCADE a purge_runs; **sin** FK a records/containers). UNIQUE `(purge_run_id, upload_operation_id)`.

**Application:** `CaptureCanonicalPurgeInventoryUseCase` (lock → abrir/reanudar atómico → una o más páginas → preparar tandas al completar). Store TX `AA_Canonical_Purge_Capture_Store`. Repos: `list_capture_page_after_id` / `list_capture_page_after_operation_id`, inventario, open/resume/progress. Sin orquestación HTTP.

**Exclusión:** lock `canonical_container` antes de INSERT de corrida; overlap contenedor↔registro; confirmación reconsulta `has_blocking_purge` antes del INSERT; delete AJAX de shell cablea la guarda (no el flujo nuevo de retiro).

**Pruebas:** `tests/application/canonical/images/test-capture-canonical-purge-inventory-ac.php` (MySQL aislado + dos conexiones reales para locks) y schema AC DB 28.

**Incremento 3 consumirá:** `mandate_id` estable, tandas persistidas `batch_seq` 1..N (o 0 tandas si vacío), `capture_complete=1`, el lock y las guardas de escritores ya existentes, y el cliente HMAC del incremento 1. Todavía no: accept/seal HTTP, DELETE de images/ops/registro, Continuar UI.

### Incremento 3 — retiro de **un registro** (camino productivo mínimo)

**Application:** `RetireCanonicalRecordImagesUseCase` (nombre tentativo) orquestando lock → corrida durable → accept/status **reproducible** → seal o skip → TX DELETE images/ops → `WriteCanonicalShellRecordUseCase::delete` (o DELETE images+record en una TX para no dejar registro huérfano sin imágenes a medias).

**AJAX:** el `aa_delete_canonical_record` existente pasa a componer este UseCase **antes** del delete de fila universal, o el Write UseCase delega. Preferible: **UseCase de aplicación de imágenes** llamado desde el AJAX/Write, sin meter HMAC en el adapter relacional.

**JS:** códigos `incomplete` + Continuar (mismo action, mismo record_id, mismas identidades). Conservar `uncertain`.

**Pruebas:** AC PHP (HTTP doblado, InnoDB test DB si el harness lo permite) + JS del form de registro. Casos: sin imágenes; una imagen; replay accept; status tras timeout **sin** nuevo mandate_id; uncertain.

**No:** contenedor, cron, `is_ready=true`, worker, Expedientes.

### Incremento 4 — contenedor

Mismo UseCase con `scope=container`: tandas reproducibles de todo el inventario de la lista, seal obligatorio, DELETE images/ops por chunks, luego delete_container (CASCADE de records ya sin RESTRICT). Continuar reanuda la misma corrida.

**JS:** form de contenedor + incompleto.

### Incremento 5 (opcional, aparte)

WP-Cron Continuar; alinear Expedientes a mandatos (**fuera** de este hito).

### Archivos canónicos afectados (visión completa, no un solo patch)

```
includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php
includes/infrastructure/wp/CanonicalSchema.php          # solo si inc. 2
includes/repositories/CanonicalRecordImagesRepository.php
includes/repositories/CanonicalImageUploadOperationsRepository.php
includes/repositories/CanonicalPurgeRunsRepository.php
includes/application/canonical/images/                  # UseCase nuevo
includes/http/ajax/CanonicalDeleteRecordAjax.php
includes/http/ajax/CanonicalDeleteContainerAjax.php     # inc. 4
assets/js/ (forms shell delete)
tests/application|http|js correspondientes
docs/plans/shell-canonical-base-v1.md                   # anotar IMG-5 cuando se implemente
```

No tocar migraciones PG, worker, Render, ni el state file de la prueba Storage.

---

## Fuera de alcance restante (tras incrementos 1–2)

- HTTP accept/seal, DELETE local de images/ops/registro, UI Continuar (incremento 3).
- Activar polling del worker, cron o `is_ready`.
- Cambiar TTL, tombstones o identidades de la fixture Storage.
- Expedientes Ciclo B.

**Backend Storage fixture (no reejecutada en este incremento):** immediate / later / reappear **PASS**; reappear fue sintético.
