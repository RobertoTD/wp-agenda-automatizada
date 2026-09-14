# Exploración: retiro canónico WP, mandatos de limpieza y cuota

**Estado:** incrementos 1–3 implementados en `dev/canonical-images-retire`. Incremento 3: retiro de **un registro** vía mandatos (`aa_delete_canonical_record` → `RetireCanonicalRecordUseCase`). Todavía sin retiro de contenedores, validación integrada, cron/workers ni `is_ready`.
**Fecha:** 2026-09-14.
**Ámbito:** integración WordPress del retiro de registros/adjuntos canónicos y liberación de cuota, reutilizando `accept` / `seal` / `status` del backend.

No modifica el state de la prueba Backend 3 (worker local Storage, `docs/ops/attachment-delete-worker-local-storage-state.json`).

## Referencias actuales

| Repo | Rama | HEAD |
|------|------|------|
| `wp-agenda-automatizada` | `dev/canonical-images-retire` | (SHA del commit de incremento 3; ver git) |
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
5. `aa_canonical_purge_runs` + `aa_canonical_purge_inventory_items` (`DB_VERSION=29` sobre la captura de DB 28). `mandate_id` estable al abrir. Captura congelada por páginas. `has_blocking_purge` solo `in_progress|incomplete` (cancelada/completada no bloquean). Intención HMAC (`accept_intent_batch_seq` / `seal_intent_at`) y `cancelled_at` en inc. 3. `last_accepted_batch_seq` NULL = ninguna tanda acreditada; `0` = primera tanda acreditada.
6. Cuota: `AA_Installation_Storage_Usage::{confirmed_bytes, reserved_bytes, admission_used_bytes}`. Confirmados = `SUM(aa_expediente_adjuntos.byte_size)` + `SUM(aa_canonical_record_images.byte_size)`. Reservados = ops `admitted` vigentes. **No hay RPC de «liberar cuota»:** desaparece la fila confirmada (o la reserva) y el SUM baja.
7. Lectura de imágenes para UI (`find_public_rows_by_record_ids_for_container`) **no** incluye `upload_operation_id`, `content_sha256` ni `storage_path`. Accept usa el inventario persistido.
8. Backend: max 50 ítems/tanda; fingerprint por tanda; replay idéntico → `already_accepted`; misma identidad+metadatos otro mandato → `already_obligated` persistido; `status` recupera respuesta perdida; seal vacío (`expected_batch_count=0`) acredita inventario **entregado vacío** (WP no sella vacío en delete de un registro; skip HMAC). tombstone + advisory lock de identidad serializa accept vs `begin_canonical_upload_issuance`.
9. UI canónica de delete de **registro**: `confirmed` + redirect; `incomplete` + Continuar (mismo POST); `uncertain` 409 bloquea retry («Recargar lista»); conflicto previo a envío + cancelar purga local; `intervention_required` no promete que Continuar lo resuelva. Delete de **contenedor** sigue siendo el delete de shell (inc. 4).
10. `images` permanece `is_ready=false`. Borrado de contenedores y activación de producto siguen fuera.

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
  AJAX aa_delete_canonical_record → RetireCanonicalRecordUseCase
    → lock contenedor → captura (reanuda corrida) → accept/seal HMAC (o skip si vacío)
    → TX: DELETE images/ops del inventario + DELETE registro + touch + completed.
    WriteCanonicalShellRecordUseCase::delete conserva la guarda de purge (no es el camino HTTP).

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
5. Al agotar ambas fuentes sin conflicto: preparar tandas inmutables de **hasta 50 ítems** (o cero tandas si el inventario está vacío). `capture_complete=1`. **Índice de tanda:** persistido 0-based (`intdiv($index, 50)`). `prepared_batch_count` es cantidad.
6. **Incremento 3:** enviar las tandas **ya persistidas** a `POST …/delete-mandates/accept`. No reconstruir la tanda desde las tablas vivas. Replay/`status` con el mismo `mandate_id` + el **mismo** `batch_seq` remoto (0-based).
7. **Incremento 3:** `POST …/delete-mandates/seal` con `expected_batch_count` = **cantidad** de tandas (`prepared_batch_count`, 0 si vacío). No es un índice. Exigir `inventory_status=sealed` / `structural_retire_authorized`.
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

Inventario vacío real (`capture_complete=1`, `prepared_batch_count=0`) → **skip HMAC** en delete de un registro (inc. 3) → DELETE registro (FK permite) → `confirmed`. El `mandate_id` local queda como evidencia sin POST remoto.

**Cerrada para incremento 3 (un registro):** ni accept ni seal 0. En delete de **contenedor** (inc. 4), seal 0 o N sigue siendo obligatorio.

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
- Tandas de **hasta 50 ítems** se asignan **después** de completar la captura (`batch_seq` / `position_in_batch`). Una tanda preparada no gana, pierde ni cambia ítems. Inventario vacío → `prepared_batch_count=0`, ninguna tanda vacía. No hay fingerprint local: el hash lo calcula el backend en accept.
- **Incompatibilidad de índice (inc. 2 vs backend, corregida en inc. 3):** el incremento 2 persistió `batch_seq` 1-based. El 3 persiste 0-based y rechaza corridas legacy 1-based sin renumerarlas. Ver §6.1.
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

Cerrar las dos decisiones menores (seal 0 en registro vacío — **cerrada en §2.3 / §6.4**; chunks DELETE post-seal). Sin runtime.

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

**Incremento 3 consume:** `mandate_id` estable, tandas persistidas 0-based (índice remoto 0..N-1; cantidad N = `prepared_batch_count`), `capture_complete=1`, lock ya adquirido, cliente HMAC del incremento 1, DELETE local post-sello, Continuar UI.

### Incremento 3 — retiro de **un registro** — **completado** (`DB_VERSION=29`)

**Clasificación:** capability `images` + runtime WP. El AJAX/JS de delete del shell coordina el resultado de mutación; no absorbe reglas de mandato.

**Application:** `RetireCanonicalRecordUseCase` orquesta, bajo `GET_LOCK` `canonical_container` (reutiliza `CaptureCanonicalPurgeInventoryUseCase::execute_with_held_lock`; sin adquisición anidada) y **sin TX SQL abierta durante HTTP**:

1. Access Policy (`authorize_identity` por `family_key`) + contenedor existente. El registro vivo **no** es requisito para Continuar si la corrida ya está capture-complete o sellada.
2. Captura `scope=record`, `max_pages=2` por petición.
3. Si captura incompleta o en conflicto: devolver sin HMAC. Conflicto previo a envío: `can_cancel` y cancelación local (conserva corrida+inventario; `status=cancelled`; libera escritores). Nueva eliminación abre **otra** corrida.
4. Acreditar tandas persistidas (accept / status / seal). Intención de envío **antes** del POST. Mismo `mandate_id`, mismo `batch_seq`, mismos ítems. Índice remoto 0-based.
5. Solo con cobertura completa y `structural_retire_authorized`: TX local (imágenes + ops del inventario, registro, touch contenedor, `status=completed`). Inventario y corrida se conservan.
6. Soltar locks. `storage_quota` solo alrededor de la TX local.

**AJAX:** `aa_delete_canonical_record` compone este UseCase. `retire_action=cancel` para abortar purga local. **No** llama `WriteCanonicalShellRecordUseCase::delete`. La guarda en `Write::delete` se conserva.

**JS:** `incomplete` → Continuar (mismo POST, sin `mandate_id`). `conflict` → Reintentar + Cancelar eliminación si `can_cancel`. `uncertain` / `intervention_required` → Recargar; no retry ciego. Delete de contenedor **no** cableado.

**No:** contenedor, cron, `is_ready=true`, worker, Expedientes, ledger físico WP, `POST /expediente/attachments/delete`.

El detalle del flujo, numeración, cancelación segura y procedimiento manual están en **§6**.

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

## 6. Incremento 3 — implementación (un registro)

### 6.1 Comprobación: numeración de tandas (incompatibilidad real)

Hay que distinguir **índice de tanda** (`batch_seq`) y **cantidad de tandas** (`prepared_batch_count` / `expected_batch_count`).

| Concepto | Incremento 2 (WP, persistido) | Backend (`contiguous_prefix_len`, accept/seal) |
|----------|-------------------------------|------------------------------------------------|
| Primera tanda, índice | `1` | `0` |
| Segunda tanda, índice | `2` | `1` |
| 51 ítems → cantidad | `prepared_batch_count=2` | `expected_batch_count=2` |
| Inventario vacío → cantidad | `0` (cero tandas; no hay fila vacía) | `seal(..., 0)` si se sellara |

**Hecho reproducible sin HTTP remoto (incremento 2, ya corregido en el 3):**

- Asignación vigente: `includes/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php` (`$batch_seq = intdiv($index, $max)`; `$batch_count = $batch_seq + 1` es **cantidad**).
- `assign_batch_slot` / `list_prepared_batch` permiten `0` (`< 0` inválido).
- AC: `tests/application/canonical/images/test-capture-canonical-purge-inventory-ac.php` y `test-retire-canonical-record-ac.php` (51 ítems → índices HMAC 0 y 1, seal `expected_batch_count=2`).
- Contrato backend: primera tanda `batch_seq=0`. `last_accepted_batch_seq` nullable: `NULL` = ninguna acreditada; `0` = primera tanda acreditada.

**No traducir en el POST** (`wp_seq - 1`): el cursor local, `status` y el replay comparten el mismo entero que el backend.

**Evidencia de que no había corridas de producto 1-based:** `CaptureCanonicalPurgeInventoryUseCase` no estaba cableado a ningún AJAX productivo (el delete usaba `WriteCanonicalShellRecordUseCase`). La captura solo se ejecutó en tests MySQL con prefijo temporal. No se migró WordPress compartido ni se renumeraron filas ajenas.

**Compatibilidad mínima (no renumerar a ciegas):** si una corrida abierta tiene tandas asignadas con `MIN(batch_seq)=1` y ninguna tanda `0`, se trata como `legacy_batch_index`. No se envía HMAC ni se reescribe el índice. Si no hubo intención de envío, se puede **cancelar** la purga local. Si ya hubo envío/intención remota, `intervention_required` (formato o estado remoto desconocido).

**Schema:** `DB_VERSION=29` (columnas `accept_intent_batch_seq`, `seal_intent_at`, `cancelled_at`). El diseño original del §6.5 decía «sin bump»; el prompt del incremento 3 autorizó versionar si la estructura cambia. `prepared_batch_count` / `expected_batch_count` siguen siendo cantidades.

### 6.2 Comprobación: `new Write…($gateway)` y guardas

«`new Write…($gateway)` sigue sin guarda» significa: el 3.º y 4.º argumento de `WriteCanonicalShellRecordUseCase` / `WriteCanonicalShellContainerUseCase` (`CanonicalPurgeRunsRepository`, `AA_Expediente_Aggregate_Lock`) son **opcionales**. Si `purge_runs === null`, `delete()` **no** consulta `has_blocking_purge` ni toma el lock. Eso conserva los AC de escritura SB1-5A y los tests MySQL SB15b* que construyen solo el gateway.

**Producción (entradas HTTP reales que mutan inventario o borran el recurso):**

| Entrada | Construcción | ¿Bloqueada con purge abierta del registro/lista? |
|---------|--------------|---------------------------------------------------|
| `aa_attach_canonical_record_image` | `UploadCanonicalRecordImageUseCase` | Sí: lock contenedor → `has_blocking_purge` → lock cuota. Confirmación reconsulta antes del INSERT. |
| `aa_delete_canonical_record` | `WriteCanonicalShellRecordUseCase($gateway, null, purge, lock)` | Sí: lock + `has_blocking_purge`. Hoy bloquea el delete **actual** (sin retiro de images). |
| `aa_delete_canonical_container` | análogo + `has_blocking_purge_for_container` | Sí. |
| `aa_create_canonical_record` / `aa_update_canonical_record` | `new Write($gateway, preparer)` | No. No insertan images/ops ni borran el registro. |
| `aa_create_canonical_container` / `aa_update_canonical_container` | `new Write($gateway, …)` sin purge | No. |

No hay otro AJAX productivo que llame `Write::delete` ni `insert_confirmed` / `insert_admitted`.

**Conclusión:** no hay bypass de producción para **inventar o borrar** images/ops ni para el DELETE SQL del registro/lista mientras la corrida está `in_progress|incomplete`. El constructor corto es compatibilidad de **tests**, no un segundo camino HTTP. Create/update de título/amount del mismo registro siguen siendo alcanzables (no alteran el inventario congelado; InnoDB serializa update vs el DELETE local futuro). El incremento 3 no cablea esas guardas (fuera del inventario).

El incremento 3 **sustituye** el delete productivo del registro por `RetireCanonicalRecordUseCase`. La guarda en `Write::delete` se conserva como red de seguridad.

### 6.3 Flujo y transacciones (un registro)

Lock `GET_LOCK` `canonical_container` durante **toda** la petición (incluido HMAC). **Prohibido** `START TRANSACTION` mientras hay HTTP. Lock `storage_quota` solo en la TX local post-HTTP (la cuota es `SUM` de filas; no hay contador nuevo).

```
authorize_identity(family_key) + contenedor existe
GET_LOCK canonical_container
  CaptureCanonicalPurgeInventoryUseCase (reanuda la misma corrida / mandate_id)
  si capture no completa → incomplete (sin HTTP)
  si conflicto de captura → ver tratamientos; sin HMAC
  si prepared_batch_count=0 → skip HMAC → TX local
  si hay tandas:
    persistir accept_intent_batch_seq **antes** del HTTP
    next_seq = last_accepted_batch_seq es NULL ? 0 : last_accepted_batch_seq+1
    si next_seq < prepared_batch_count:
      si ya hay intención de esa tanda: status(mandate_id, next_seq)
        found+can_credit_batch + ítems ≡ tanda local → acreditar next_seq
        found+batch_found=false o found=false → accept idéntico (mismo seq, mismos ítems)
      si no hay duda: accept idéntico
      persistir last_accepted_batch_seq solo tras acreditar contra contenido local
      si aún quedan tandas → incomplete
    si todas acreditadas y sealed_at NULL:
      persistir seal_intent_at **antes** del HTTP
      seal(mandate_id, expected_batch_count=prepared_batch_count)
      o status sin batch_seq si respuesta de seal perdida
      exigir structural_retire_authorized && inventory_status=sealed
      persistir sealed_at
  GET_LOCK storage_quota
  TX InnoDB UNA:
    DELETE images/ops cuyas identidades están en el inventario de esta corrida
    fail-closed si queda image/op viva del registro que no esté en el inventario
    DELETE registro + CASCADE amount + touch contenedor
    status=completed de la corrida (misma TX)
    inventario de purge NO se borra (evidencia; sin FK al registro)
  COMMIT
RELEASE quota, RELEASE container
```

**Presupuesto por petición (incomplete definido):**

- Captura: `max_pages=2`.
- HMAC: como máximo **un** accept (más un status de reconciliación de esa tanda) y, si ya no quedan tandas, **un** seal.
- TX local: solo si el sello ya está acreditado en esta petición o `sealed_at` ya estaba persistido.

Continuar = mismo POST `aa_delete_canonical_record`. El servidor abre/reanuda por `(scope=record, target_id)`. No mandar `mandate_id` al cliente.

**Acreditación de una tanda:** coincidencia de `upload_operation_id` (y sha / `wp_record_id` / `byte_size`) con `list_prepared_batch(run, seq)` **antes** de avanzar `last_accepted_batch_seq`. `already_accepted` / `status.can_credit_batch` cuentan si el contenido coincide. No inventar fingerprint local.

### 6.4 Tratamientos

| Caso | Comportamiento |
|------|----------------|
| Registro sin images ni ops | Captura completa, 0 tandas, skip HMAC, TX local, `confirmed`. |
| Ops no confirmadas (`admitted` vencida o `cleanup_needed`) | Entran en inventario e accept. DELETE local de la fila ops en la TX post-seal → la reserva de cuota desaparece del SUM. |
| Backend no disponible / 5xx / WP_Error | `incomplete`. Mismo mandate. No sellar. No DELETE local. Continuar. |
| Respuesta perdida en accept | `status` con el **mismo** `batch_seq`; si no acredita, replay idéntico. Nunca otro mandate ni otro seq. |
| Respuesta perdida en seal | `status` sin `batch_seq`; si `structural_retire_authorized`, persistir `sealed_at` y pasar a TX local; si no, replay seal con el mismo `expected_batch_count`. |
| Fallo local tras accept/seal remoto | Mandato ya durable. `incomplete`. Reintento: no re-acepta identidades nuevas; TX local idempotente (0 filas = ya retirado) + completar corrida si las filas ya no están. |
| Commit local ambiguo | `uncertain` 409, UI Recargar, **sin** Continuar ciego. Tras recarga: SELECT (no re-DELETE). Si registro/images/ops ausentes → marcar corrida `completed` si no lo estaba → éxito. Si siguen → Continuar (sello ya acreditado). |
| Dos Continuar simultáneos | `GET_LOCK`; el segundo espera el timeout (5 s) y recibe `resource_busy` 409. No segundo mandato. |
| Reintento con registro ya retirado y corrida `completed` | `record_not_found` (igual que el delete actual al repetir). |
| Continuar con registro ausente y corrida aún abierta | Autorizar por familia + contenedor (Access Policy no exige el registro). Reconciliar: si el inventario local ya no tiene filas vivas y el sello está acreditado → TX de cierre (`completed`) → `confirmed`. Si el contenedor también faltara (fuera de este alcance habitual) → persistencia/404 sin mintar mandato. |
| Conflicto de captura (`item_metadata_conflict` / `item_incomplete`) **antes de cualquier envío** | Sin HMAC. No borrar imágenes/ops/registro. `conflict` 409 con `can_cancel`. **Cancelar purga local** (mismo lock): `status=cancelled`, conserva corrida+inventario, libera escritores. Continuar reintenta captura. Una nueva eliminación abre **otra** corrida. |
| Intención de envío, aceptación, sello o resultado remoto desconocido | **No** cancelación simple. `cancel_rejected` + Continuar para recuperar el protocolo. Timeout ≠ cancelación segura. |
| Formato 1-based persistido (`legacy_batch_index`) | No enviar ni renumerar. Cancelable solo si no hubo dispatch remoto; si lo hubo → `intervention_required`. |
| Caso que no puede resolverse con Continuar | `intervention_required` 409 + Recargar. No se promete que Continuar lo arregle. Sin expiración automática ni limpieza física en WP. |

### 6.5 Cambios de código y UI (hechos)

`DB_VERSION=29`. Sin tabla nueva.

- Persistencia 0-based de `batch_seq` + AC de captura.
- Columnas de intención HMAC y `cancelled_at`.
- Repos: acreditar `last_accepted_batch_seq` / `sealed_at` / `completed` / `cancelled`; DELETE images/ops por `upload_operation_id`; fail-closed si queda inventario vivo del registro.
- Store TX local de retiro (images + ops + record + touch + completed). `CanonicalRelationalAmbiguousOutcome` → `uncertain`.
- `RetireCanonicalRecordUseCase` + captura bajo lock ya adquirido + cliente HMAC existente.
- `CanonicalDeleteRecordAjax`: UseCase de retiro; `incomplete`/`conflict`/`cancel_rejected`/`uncertain`/`intervention_required`/`resource_busy` 409; no JSON de `mandate_id`.
- Modal: copy de imágenes; Continuar; Cancelar eliminación (purga local); Recargar; el botón gris es **Cerrar** (no cancela en servidor).

### 6.6 Matriz de pruebas automatizadas

PHP (`php tests/…-ac.php`; MySQL aislado con `AA_WP_ROOT` y prefijo temporal; HTTP **doblado**, sin Storage/Render):

- Alineación `batch_seq=0` primera tanda; `prepared_batch_count=2` ⇔ seal `expected_batch_count=2` (índices 0 y 1).
- Vacío: 0 tandas, cero llamadas HMAC, DELETE registro, corrida `completed`, inventario 0 filas, cuota confirmada igual.
- Una imagen confirmada: accept seq 0, seal count 1, filas images/ops/registro ausentes, fila inventario **conservada**, `SUM` de images baja.
- Solo op `admitted` vencida / `cleanup_needed`: se acepta; ops desaparece; reserva baja.
- Replay accept idéntico → `already_accepted`; mismo `mandate_id`.
- Timeout de accept → `status` acredita o reenvía el mismo seq; no hay segundo mandate.
- Timeout de seal → `status` / replay seal; TX local solo con `structural_retire_authorized`.
- Backend unreachable → `incomplete`; writers siguen bloqueados; Continuar.
- Fallo local post-seal: reintento no duplica obligación; DELETE 0 filas + `completed`.
- Uncertain: no `confirmed`; SELECT de reconciliación.
- Dos conexiones reales: segundo Continuar → `resource_busy` o espera el lock; un solo mandate.
- Attach/confirm durante corrida abierta → `purge_in_progress` (regresión inc. 2).
- Extra image viva no inventariada tras sello → no DELETE del registro (fail-closed).
- Registro ya ausente + corrida completed → `record_not_found`.
- `new Write($gateway)->delete` en tests existentes sigue verde (sin guarda).

JS (`scripts/safe-node-test.sh tests/js/canonical-shell-record-form.test.js`, un archivo): `incomplete` deja Continuar (no `deleteBlocked`); `uncertain` sigue bloqueando; `confirmed` redirige; no aparece `mandate_id` en el body.

### 6.7 Prueba manual posterior (propuesta; **no ejecutada** en este incremento)

**No usar datos reales. No migrar la WP compartida. No arrancar Supabase, Storage, Render, `npm start` ni workers.**

Requisitos cuando se autorice:

1. Plugin en `dev/canonical-images-retire` con schema **29** aplicado **solo** en una instalación de desarrollo aislada (`AA_Schema::install()` / activación del plugin en esa instancia). Comprobar `aa_db_version=29` y columnas `accept_intent_batch_seq`, `seal_intent_at`, `cancelled_at` en `aa_canonical_purge_runs`.
2. Cliente HMAC contra el backend `dev/backend-recovered` **ya desplegado** (RPCs `20260913` de mandatos). `images` sigue `is_ready=false`.
3. Un contenedor **sintético** de familia `finance` (o archive de prueba) con:
   - (a) un registro **sin** images ni ops;
   - (b) un registro con **una** imagen confirmada de prueba (attach de desarrollo, no producción).

Efectos a observar entonces:

- (a) `confirmed` + redirect; **cero** POST accept/seal para ese `mandate_id` local (el id local queda como evidencia); registro ausente; corrida `completed` con `prepared_batch_count=0`.
- (b) accept `batch_seq=0`; seal `expected_batch_count=1` y `structural_retire_authorized`; filas WP images/ops/registro ausentes; fila de inventario de purge **presente**; `SUM` de images menor; cuota informativa baja sin contador nuevo.
- Cortar la red entre accept y seal: modal Continuar (no «eliminado»); al recuperar, mismo `mandate_id` y `batch_seq=0`.
- Conflicto de captura **antes** de envío: Cancelar eliminación deja el registro; un nuevo Eliminar abre otra corrida.
- Tras intención de envío: Cancelar eliminación → rechazo; Continuar recupera.

Fuera de esta exploración: contenedores, worker físico, datos de clientes reales.

### 6.8 Veredicto

**Incremento 3 implementado** para un registro, con `batch_seq` 0-based persistido, intención HMAC antes del POST, cancelación local previa al envío y TX local post-sello.

**Límites (no sustituir por una afirmación de seguridad):**

1. Continuar **no** garantiza resolver `intervention_required` (payload ajeno, filas vivas fuera de inventario, formato 1-based ya enviado).
2. Create/update de título/amount del mismo registro no están guardados por purge; no mutan inventario.
3. No hay expiración automática ni limpieza física en WordPress.
4. Delete de contenedor sigue siendo el camino de shell (RESTRICT si hay images/ops).
5. `physical_status` del worker no autoriza retiro WP. `structural_retire_authorized` no certifica el COMMIT local.

**No cerrado:** incremento 4 (contenedores), validación integrada contra backend real, activación operativa (`is_ready`), cron/worker.

---

## Fuera de alcance restante (tras el incremento 3)

- Eliminación de contenedores (incremento 4).
- Validación integrada contra backend/Storage reales.
- Activar polling del worker, cron o `is_ready`.
- Cambiar TTL, tombstones o identidades de la fixture Storage.
- Expedientes Ciclo B.

**Backend Storage fixture (no reejecutada):** immediate / later / reappear **PASS**; reappear fue sintético.
