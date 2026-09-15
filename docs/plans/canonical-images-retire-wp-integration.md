# Exploración: retiro canónico WP, mandatos de limpieza y cuota

**Estado:** **etapa de desarrollo de eliminaciones IMG-5 cerrada**. Worker periódico **apagado**. **§12 Paso 1** + **§13 Paso 2** implementados. **Paso 5 flip:** `images.is_ready=true` + `DEFAULTS_VERSION=3` **implementado**. Galería/`display`/visor/delete UI final **pendientes**.
**Fecha:** 2026-09-14 … Paso 2: 2026-09-15; Paso 5 flip: 2026-09-15.
**Ámbito:** retiro canónico; amount↔images; Pasos 1–2 UI; **Paso 5 readiness flip**.

No modifica el state de la prueba Backend 3 (worker local Storage, `docs/ops/attachment-delete-worker-local-storage-state.json`).

## Referencias actuales

| Repo | Rama | HEAD |
|------|------|------|
| `wp-agenda-automatizada` | `dev/canonical-images-retire` | (Paso 2 implementado; ver SHA del commit) |
| `deoia-oauth-backend` (solo consulta; sin cambios) | `dev/backend-recovered` | `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` (untracked ajeno: `scripts/runner-from-pack.sh`) |

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

### Incremento 4 — contenedor / lista completa — **completado** (`DB_VERSION=30`)

`aa_delete_canonical_container` orquesta mandatos `scope=container` (`RetireCanonicalContainerUseCase`). Seal obligatorio (incl. `expected_batch_count=0`). Chunks locales post-sello. AJAX integrado policyytest A/B/C **PASS** (§7.10). Continuar visual desde listado **PASS** (lista 22, §7.12). Cerrar/recargar/banner **pendientes**. Primer intento lista 21 **no PASS** (§7.11). Detalle: **§7**.

### Incremento 5 — retiro de **una imagen** (registro vivo) — **implementado (§8)**

Norma §12.4. Diseño único, componentes, schema mínimo, matriz de pruebas: **§8**. Código en Application/Ajax/UI; `DB_VERSION=31`. Capability `images` sigue `is_ready=false` (no cierra la capability).

### Incremento 6 (opcional, aparte)

WP-Cron Continuar; alinear Expedientes a mandatos (**fuera** de este hito).

### Archivos canónicos afectados (visión completa, no un solo patch)

```
includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php  # inc. 1
includes/infrastructure/wp/CanonicalSchema.php          # DB 30 checkpoints lista
includes/repositories/CanonicalRecordImagesRepository.php
includes/repositories/CanonicalImageUploadOperationsRepository.php
includes/repositories/CanonicalPurgeRunsRepository.php
includes/application/canonical/images/                  # RetireCanonicalContainer* + advancer HMAC
includes/http/ajax/CanonicalDeleteRecordAjax.php        # inc. 3
includes/http/ajax/CanonicalDeleteContainerAjax.php     # inc. 4: Retire
includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js
tests/application|http|js correspondientes
docs/plans/shell-canonical-base-v1.md                   # decisión 40
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

### 6.7 Validación integrada policyytest (2026-09-14)

Ejecutada contra WordPress local + Node local + Supabase remoto compartido. **No** se activó `is_ready`, **no** se desplegó Render, **no** se migró Supabase, **no** se arrancó el worker periódico, **no** se reutilizó la fixture Backend 3 (`wp_record_id=919901`, `upload_operation_id=1f5e50dc-9768-4adf-a7e9-a7dddaf59448`, `mandate_id=171419cc-1085-419a-8875-f1feca9dc2b4`). La retoma del mismo día parcheó `authorize-upload` y completó el caso B sobre el registro **34** (el 32 se dejó vacío para clics UI).

#### Entorno comprobado

| Dato | Valor |
|------|--------|
| Sitio | `http://localhost/deoia-platform/policyytest/agenda-app` |
| Blog ID | **61** (path `/deoia-platform/policyytest/`) |
| Prefijo | **`wp_61_`** (leído de `$wpdb->prefix` tras cargar el blog; coincidía con la hipótesis, no se asumió) |
| Identidad HMAC (`X-Client-Id`) | `localhost/deoia-platform/policyytest` |
| `AA_API_BASE_URL` efectiva | `http://localhost:3000` (regla `localhost` del plugin; no `https://api.deoia.com`) |
| `aa_client_secret` | configurado (no se imprime) |
| `installation_id` (backend) | `e49a26c8-c7db-4a8a-a84a-2643ddfb8b29` (HMAC → `agenda_clients`; WP no la envía) |
| ¿Instalación compartida? | Un solo `agenda_clients.domain` apunta a ese UUID. **Sí se comparte** con las identidades Storage de la fixture Backend 3. Toda la prueba se acotó por IDs sintéticos nuevos. |
| Schema | **antes = 29, después = 29**. Columnas `accept_intent_batch_seq`, `seal_intent_at`, `cancelled_at` ya presentes. **No se ejecutó** `AA_Schema::maybe_migrate()`. |
| Alcance de una hipotética actualización | `maybe_migrate()` lee `get_option('aa_db_version')` del blog actual y `install()` usa `$wpdb->prefix`. Habría tocado **solo** tablas `wp_61_*` de este plugin (dbDelta completo de esas tablas, `flush_rewrite_rules` de ese blog, DROP `aa_finance_*` residuales de ese prefijo si existieran). Otros blogs muestreados siguen en versiones distintas (blog 1 → 22, 59 → 22, 60 → 13, 62 → 18) y **no** se visitaron en admin. |
| `images` | catálogo `is_ready=false`; 0 filas `aa_canonical_container_capabilities` con `images` activas. `AA_CANONICAL_SHELL_PREVIEW=true` es demo in-memory de `shell_preview`, no habilita images. |
| Node | **Arrancado para esta prueba** y **recargado** para cargar el parche `26452b3` (`npm start` → `node index.js` pid **33419**, cwd backend, health `{"ok":true}`). Workers en `.env` = `0` (comprobados otra vez antes del rearranque). Faltaban dependencias npm locales (`mailgun.js`); `npm install` previo añadió paquetes **sin** cambiar git. |
| Plugin cargado | symlink `wp-content/plugins/wp-agenda-automatizada-feature-admin-ai-assistant` → este repo, HEAD `752274ddc755be437b66b332246e6d8be92663e4`. |

#### Fixtures sintéticos (conservar; no borrar el contenedor)

Lista finance **id=17** `INC3-RETIRE-20260914 policyytest` (`public_id=f5ee0b24-3be4-47f0-86db-0e23ecaba61c`). Capacidad de lista: solo `amount` activa.

| Caso | Record id | Título | Notas |
|------|-----------|--------|--------|
| A | 31 | `INC3-A-empty` | retirado |
| B | 32 | `INC3-B-image` | **retirado por UI** del propietario (corrida 5 `completed` vacía); la subida integrada B fue el registro **34** |
| B2 | 34 | `INC3-B2-image` | creado, imagen confirmada y **retirado** (`aa_delete_canonical_record` confirmed) |
| D | 33 | `INC3-D-conflict` | imagen+op locales en conflicto; corrida 3 **cancelled** por UI (`cancelled_at` set); registro conservado |

URL de registros (iframe): `http://localhost/deoia-platform/policyytest/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance&view=records&container_id=17`

Create lista/registros y deletes se enviaron al `admin-ajax.php` de policyytest con cookie de `deoia_admin` (id 1) y nonce de la misma sesión: **AJAX HTTP real**, no el Use Case en proceso. **No** hubo navegador: los clics del modal quedan pendientes (instrucciones abajo). El JSON de create lista devuelve `container_id: null` y el id en `resource_id` (recibo de contenedor; no es un fallo del retiro).

#### Resultados por caso

| Caso | Resultado | Qué se probó |
|------|-----------|----------------|
| A vacío | **PASS** | AJAX `aa_delete_canonical_record` → `success.status=confirmed`, redirect al iframe de la lista. Registro 31 ausente. Contenedor 17 `updated_at` 17:43:26 → 17:43:40 UTC. Corrida `id=1` `status=completed`, `prepared_batch_count=0`, inventario 0, `accept_intent_batch_seq`/`seal_intent_at` NULL. `mandate_id` local `2aa5a8a0-98df-4d6e-b61d-af2710250d46` **ausente** en `attachment_delete_mandates`. |
| B con imagen | **PASS** (retoma) | Producto AJAX `aa_attach_canonical_record_image` sigue **409 `capability_not_ready`** (esperado). Desarrollo acotado: `UploadCanonicalRecordImageUseCase` con registro `images` ready **in-memory** + stub `find_container_capability` `is_active=1` + validador `is_readable` (no `is_uploaded_file`). Catálogo persistido intacto (`is_ready=false`, 0 filas activas). Autorizó HTTP `canonical_v1` al Node local, PUT+finalize, confirmó imagen id=2 (384×384, 2994 B) en registro **34**. Retiro AJAX `aa_delete_canonical_record` → `confirmed`. Ver retoma abajo. |
| C interrupción accept→seal | **pendiente** | No hay gancho de fallo acotado a esta prueba. No se cortó Supabase ni se alteraron credenciales. Cobertura automatizada del incremento 3 (timeout de seal + Continuar) se conserva. |
| D conflicto previo al envío | **PASS** | Fixture controlada en el registro 33: misma `upload_operation_id=240d8d55-c9cd-42a2-a567-69e30511a724`, SHA de imagen `aa…` vs op `admitted` `bb…`. AJAX delete → 409 `conflict` `can_cancel=true`, corrida 2 `item_metadata_conflict`, inventario 1, sin intención HMAC. Cancelar → 200 `cancelled`; registro/imagen/op intactos; corrida 2 `cancelled_at` set; inventario conservado. Nueva eliminación → corrida **3** `mandate_id=ab4e4fc3-ddb3-4298-ad29-017a7801c512` (distinta), otra vez `conflict`. Mandatos 2 y 3 **ausentes** en Supabase. |

#### Diagnóstico original del FAIL de B (conservado)

`POST http://localhost:3000/expediente/attachments/authorize-upload` con `path_contract=canonical_v1` (sin `wp_client_id` / `wp_expediente_id`) respondió **400 `path_contract_invalid`**.

En `deoia-oauth-backend` `routes/expedienteAttachments.js` el adaptador HTTP **siempre** asignaba `wpClientId: body.wp_client_id` (la clave existía aunque el body no trajera el campo). `resolveAuthorizeContract` trata `hasOwnProperty(wpClientId)` como campo legado y lanza `path_contract_invalid` para `canonical_v1`. Los tests de servicio **borran** esa clave en el input canónico (`tests/expedienteAttachments.test.js`); el cable HTTP no. `wpExpedienteId` sí se copiaba solo si venía en el body. No hay el mismo patrón en finalize / sign-read / delete / delete-mandates.

#### Parche backend y prueba de ruta

SHA `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` en `dev/backend-recovered` (remoto verificado). `scripts/runner-from-pack.sh` sigue untracked.

Copia `wp_client_id` → `wpClientId` **solo** si `Object.prototype.hasOwnProperty.call(body, "wp_client_id")`. Campo ausente: la clave no se materializa. Campo presente (incluido `null` o un entero incompatible): se reenvía para que el contrato siga rechazando el híbrido. `resolveAuthorizeContract` no se debilitó.

Regresión: `tests/expedienteAttachmentsRoute.test.js` («authorize-upload HTTP adapter identity keys»). Atraviesa `postAuthorizeUpload` y el `authorizeExpedienteAttachmentUpload` real; Storage/issuance/contexto son doubles (sin proveedor). Cubre: `canonical_v1` sin `wp_client_id` (200, path canónico, 4 mints); `canonical_v1` con `wp_client_id` explícito 9 o `null` (400 `path_contract_invalid`, 0 probes/mints/issuance); `client_v1` con `wp_client_id=3` (path `/clients/3/`). Runner: `scripts/safe-node-test.sh tests/expedienteAttachmentsRoute.test.js` (19/19).

#### Retoma caso B (mismo día, Node recargado)

No se reutilizó el registro 32: se inspeccionó vivo (sigue existiendo, vacío) y se creó **registro 34** `INC3-B2-image` en la lista 17 (`resource_id=34`). Op nueva: `10a54f85-af96-4941-a63a-8da4fe9027c5`. Path: `installations/e49a26c8-c7db-4a8a-a84a-2643ddfb8b29/canonical/records/34/10a54f85-af96-4941-a63a-8da4fe9027c5.jpg`. JPEG sintético 384×384 (no 1×1). No se tocó Backend 3 (`wp_record_id=919901`, op `1f5e50dc-…`, mandato `171419cc-…`).

**Sustitución de desarrollo (acotada, no producto):** instancia local de `AA_Canonical_Capability_Registry` con `images` ready; stub de `find_container_capability` que devuelve `is_active=1`; `ExpedienteAdjuntoJpegValidator` con `is_readable` en lugar de `is_uploaded_file`. Eso sustituye **solo** el gate de catálogo/lista y el chequeo de upload PHP. El resto del recorrido (HMAC authorize/finalize, PUT Storage, TX de confirmación, `aa_delete_canonical_record`) es el código de instalación. **No** es validación de disponibilidad de `images` en la UI de producto: el catálogo persistido sigue `is_ready=false` y hay 0 filas `aa_canonical_container_capabilities` con `images` activas.

Comprobaciones:

| Chequeo | Resultado |
|---------|-----------|
| authorize HTTP sin `wp_client_id` | ya no `path_contract_invalid`; attach de desarrollo `ok=true` |
| Subida + confirmación | imagen id=2, 384×384, `byte_size=2994`, SHA `ca77600613737d066f96edcac6fbf194870e4f7718d318a717cf3d603249bf94`; ops `admitted` vacías tras el TX |
| `aa_delete_canonical_record` | HTTP 200 `status=confirmed`, registro 34 ausente |
| Primera tanda | inventario `batch_seq=0` `position_in_batch=1` |
| Sello | corrida 4 `prepared_batch_count=1`, `last_accepted_batch_seq=0`, `accept_intent_batch_seq=0`, `sealed_at` / `seal_intent_at` set; remoto `sealed_expected_batch_count=1` `reception_item_count=1` |
| DELETE local + touch | images/ops del 34 vacías; contenedor 17 `updated_at` 18:09:42 → **18:10:04** UTC; corrida **4** `completed` |
| Inventario | 1 fila conservada (op `10a54f85-…`, 2994 B, path de esta prueba) |
| Cuota | confirmados 2327788 → 2330782 (+2994) → **2327788** (−2994); reservas **634** intactas (op admitted del 33); SUM images 634+2994 → 634 |
| Remoto de esta identidad | `attachment_delete_mandates.mandate_id=6764dd13-eb48-4b8f-85f7-5aa3cda9909d` `inventory_status=sealed` `owned_obligation_count=1` `tech_bytes_owned=2994`; obligación `523c3b81-9d2d-442b-89aa-73b2d40555fe` `wp_record_id=34` `physical_status=pending` |
| Backend 3 | mandato `171419cc-…` sigue `open`, 1 obligación; no se reutilizó |

Worker periódico apagado: **no** se borraron objetos Storage. Residuos físicos (todos `found=true` en `expediente-adjuntos`): original, `_summary`, `_gallery`, `_display` bajo el path del registro 34 / op `10a54f85-…`. La obligación durable cubre esa identidad (`physical_status=pending`).

A y D no se reejecutaron (el parche no los toca). C sigue cubierto por tests automatizados; no hay inyección integrada acotada.

#### Residuos a conservar

- Corridas WP `wp_61_aa_canonical_purge_runs`: id 1 completed (vacío, 31); id 2 cancelled (conflicto 33, AJAX); id 3 **cancelled** (conflicto 33, UI propietario, `cancelled_at=2026-09-14 18:40:50`); id 4 completed (B2, 34); id 5 completed (UI vacío, 32).
- Inventarios de las corridas 2, 3 y 4 (inventario vacío en 1 y 5).
- Registro 33 + imagen id=1 + op `admitted` `240d8d55-…` (sin objeto Storage ni mandato remoto de esas corridas).
- Registro 32 ausente; registro 34 ausente; objetos Storage de la op B2 **sí** existen (worker off).
- Lista 17: **conservar**; no usarla para probar borrado de contenedores. Bloqueo de escritores en contenedor 17 al cierre del 3: **0** corridas `in_progress|incomplete`.
- Mandato/obligación remotos B2 (`6764dd13-…` / `523c3b81-…`) y fixture Backend 3.

#### Node y workers

Proceso de prueba: `node index.js` pid **33419**, cwd `/home/roberto/dev/deoia-oauth-backend` (rearranque del día para cargar `26452b3`). Workers en `.env` = `0`. Tras cerrar los clics de UI acreditados, **se detiene solo ese árbol** (no otros Node). Nota de arranque: un segundo `npm start` con el puerto ocupado imprime `Servidor corriendo…` y sale con código 0 por `EADDRINUSE` (callback de `listen` engañoso); no se cambió el manejo del puerto en este hito.

#### Pruebas de interfaz (navegador del propietario)

Separado de AJAX/HTTP. No hay agente con navegador en estas sesiones.

| Acción UI | Registro | Resultado | Evidencia |
|-----------|----------|-----------|-----------|
| Eliminar (vacío) | **32** `INC3-B-image` | Propietario confirmó desaparición visual. SQL: ausente; corrida **5** `completed`, inventario 0, sin `accept_intent`/`seal_intent`/`sealed_at`. | Visual + SQL |
| Cerrar (conflicto) | **33** | Modal cerrado; purga **no** cancelada en ese momento (corrida 3 seguía `incomplete`). | Reportado + SQL intermedio |
| Cancelar eliminación | **33** | Modal cerrado; registro visible. SQL: corrida **3** `status=cancelled`, `cancelled_at=2026-09-14 18:40:50`; imagen/op intactos; `accept_intent_batch_seq`/`seal_intent_at` NULL; mandatos locales `ab4e4fc3-…` / `87b4824e-…` **ausentes** en Supabase; 0 corridas bloqueantes en contenedor 17. | Visual + SQL |
| Continuar | **33** | **Sin** confirmación visual explícita del propietario. **No PASS.** | — |

Caso B integrado (AJAX/HTTP, registro 34): **PASS** previo (ver retoma arriba). Caso C interrupción accept→seal: cobertura automatizada; prueba integrada **pendiente**.

### 6.8 Veredicto (incremento 3)

**Incremento 3 implementado** y validado en policyytest salvo Continuar UI y C integrado. Authorize HTTP `canonical_v1` corregido. Caso B integrado **PASS** (desarrollo acotado). UI: vacío + Cancelar acreditados; Continuar **no** PASS.

**Re-verificación solo lectura (2026-09-14, post-Cancelar del propietario):** blog 61 / `wp_61_`; registro 33 + imagen id=1 + op `admitted` conservados; corrida 3 `cancelled` / `cancelled_at=2026-09-14 18:40:50`; `accept_intent_batch_seq`/`seal_intent_at`/`last_accepted_batch_seq`/`sealed_at` NULL; mandato `ab4e4fc3-…` ausente en Supabase; `blocking_runs` en contenedor 17 = **0**. Sin discrepancia con lo documentado.

**No cerrado tras el 3:** retiro de una imagen (§8); Continuar UI del registro; C integrado (interrupción de red); `is_ready`; cron/worker. Esas clasificaciones **no** cambian por el incremento 4.

**Incremento 4:** AJAX integrado A/B/C **PASS** (§7.10). Continuar visual listado lista 22 **PASS** (§7.12); Cerrar/recargar/banner pendientes. Lista 21 primer intento **no PASS** (§7.11). Schema policyytest blog 61: 29→30.

---

## 7. Incremento 4 — retiro de una lista completa — **completado** (`DB_VERSION=30`)

**Estado:** implementado en código + AC MySQL aislado / Ajax / JS. Policyytest 2026-09-14: schema blog 61 29→30; AJAX A/B/C **PASS**; Continuar visual desde listado lista 22 **PASS** (§7.12); Cerrar/recargar/banner/altas **no** PASS visual. Lista 21 primer intento **no PASS** (§7.11). HMAC de registro: protocolo PASS; estructural `'vacío skip HMAC'` 45/45. Backend no se desplegó.

**Clasificación:** capability `images` + runtime WP (IMG-5).

**Paradigma:** capa nueva Application (`RetireCanonicalContainerUseCase`); Ajax/JS solo cablean; HMAC compartido en `CanonicalPurgeRemoteMandateAdvancer` (el retiro de registro delega ahí; no se copió el protocolo). Schema `DB_VERSION=30`.

### 7.1 Flujo conectado

`aa_delete_canonical_container` → `RetireCanonicalContainerUseCase` (no `WriteCanonicalShellContainerUseCase::delete`; Write sigue como red de seguridad RESTRICT + purge guard).

Bajo `GET_LOCK` `canonical_container` **toda** la petición, **sin** TX SQL durante HMAC:

1. Access Policy (`authorize_identity` por `family_key`). El contenedor vivo **no** es requisito para Continuar si la corrida ya está capture-complete o sellada; la corrida conserva `family_key` / `scope` / `target_id` / `container_id`.
2. Captura `scope=container`, `max_pages=2`. Overlap lista↔registro hijo → `scope_overlap`.
3. Conflicto / captura incompleta: sin HMAC. Cancelación local solo si `!has_attempted_remote_dispatch`.
4. HMAC vía `CanonicalPurgeRemoteMandateAdvancer`: intención persistida **antes** del POST. **Siempre** seal, también con `prepared_batch_count=0` → `expected_batch_count=0`. Fail-closed si hay images/ops vivas con inventario vacío.
5. Post-sello: `retire_container_chunk` (presupuesto local acotado). `storage_quota` solo alrededor de esa TX.
6. `confirmed` solo si el contenedor quedó retirado y la corrida `completed` en la misma TX final. Nunca `confirmed` con trabajo restante.

### 7.2 Chunks, FKs y cuota

Orden real (RESTRICT images/ops → records; CASCADE records/amount/capabilities → container):

1. DELETE images/ops del inventario por `upload_operation_id`, página keyset `id > local_retire_after_inventory_id` (no OFFSET), tamaño por defecto 50.
2. Checkpoint `local_retire_after_inventory_id` **en la misma TX** que esos DELETE. Si la página llenó el presupuesto → COMMIT + `incomplete`.
3. Fail-closed: images/ops vivas del contenedor fuera del inventario → rollback + `intervention_required`.
4. DELETE registros keyset `id > local_retire_after_record_id` (evita CASCADE ilimitado al borrar el contenedor). Checkpoint `local_retire_after_record_id` misma TX. Si quedan registros → `incomplete`.
5. DELETE contenedor + `mark_completed` misma TX. Inventario y corrida **no** se borran.

Una petición nueva reanuda desde los checkpoints. Respuesta de COMMIT ambigua → `uncertain`; no avanzar a ciegas. Cuota = SUM de filas images/ops que **aún existen**; baja al COMMIT de cada chunk de identidades. No hay contadores nuevos.

No hay operación de producto que cambie la pertenencia de un registro entre listas. Un `UPDATE container_id` sintético tras la captura no saca las identidades del inventario: esas images/ops se retiran; el registro ya no anclado a la lista no se borra con ella.

### 7.3 Recuperación, acceso y cancelación

| Caso | Comportamiento |
|------|----------------|
| Accept/seal perdidos | `status` / replay idéntico; nunca otro mandato ni cancel. |
| Backend inaccesible | `incomplete`; Continuar. |
| Fallo local tras sello | `incomplete`; reintento idempotente. |
| Interrupción tras chunk | checkpoints; Continuar misma corrida. |
| Commit ambiguo | `uncertain` 409; Recargar; no Continuar ciego. |
| Hijos/contenedor ya ausentes | sello acreditado + 0 filas vivas → `completed` / `confirmed`. |
| Auth con contenedor ausente | Access Policy + corrida; no mandato nuevo. |
| Familia distinta a la corrida | `forbidden`. |
| Cancelación | solo sin envío remoto. Tras intención/sello/retiro local: `cancel_rejected` (no promete restaurar). |
| Doble Continuar | segunda conexión → `resource_busy`. |

Tras sello o retiro local **no** se ofrece una cancelación que restaure datos.

### 7.4 UI de lista

Modal reutiliza el protocolo del registro: `confirmed` → listado de listas; `incomplete` → Continuar (mismo POST, sin `mandate_id`); `uncertain` → Recargar; conflicto previo al envío → Cancelar eliminación si `can_cancel`; `intervention_required` → mensaje explícito sin resolución automática. **Cerrar** solo cierra el modal.

Lista parcialmente retirada: banner «No está borrada del todo»; se ocultan FAB y mutaciones de registros; **Eliminar lista** permanece para Continuar. Si la fila del contenedor ya no existe y la corrida `scope=container` sigue abierta, el listado muestra aviso + Continuar. No se afirma terminada ni se permiten acciones que rompan la exclusión.

Nonce `aa_delete_canonical_container` en eliminar / continuar / cancelar.

### 7.5 Schema

`DB_VERSION=30`: `aa_canonical_purge_runs.local_retire_after_inventory_id` y `local_retire_after_record_id` (`bigint unsigned NOT NULL DEFAULT 0`). `ensure_purge_container_local_retire_v30()` es idempotente.

Policyytest 2026-09-14: `AA_Schema::maybe_migrate()` **solo** en blog **61** (`wp_61_*`, `aa_db_version` 29→30). Columnas de checkpoint presentes. Lista 17 intacta. Otros blogs muestreados **no** se migraron (1→22, 59→22, 60→13, 62→18). No se activó `is_ready`.

### 7.6 Pruebas automatizadas (turno de implementación)

MySQL prefijo temporal + HMAC simulado; lock con **segunda conexión** real. Cubierto: lista vacía seal 0; registros sin imágenes; images+ops no confirmadas; 51 identidades (tandas 0/1); accept/seal/status perdidos; overlap ambos órdenes; writers bloqueados; chunks + cuota SUM; commit ambiguo; reintento con contenedor ya ausente; corrida/inventario conservados; `resource_busy`; acceso indebido; fail-closed fila extra; Ajax estados; JS Continuar/Cancelar/Cerrar.

### 7.7 Límites (no declarar de más)

- Clics de **navegador** del incremento 4: Continuar desde listado lista 22 **PASS** (§7.12). Cerrar/recargar/banner/bloqueo de altas **pendientes**. Primer intento lista 21 **no PASS** (§7.11). AJAX A/B/C no sustituye esos clics.
- Continuar UI del incremento 3 y caso C integrado (interrupción de red): **siguen sin PASS** (no se reabrieron ni se reclasifican).
- El attach de desarrollo del caso C **no** es PASS de disponibilidad de `images` en UI de producto.
- No hay garantía de que un `UPDATE` de pertenencia hecho a mano conserve imágenes del registro movido (las identidades inventariadas se retiran).
- Worker periódico apagado: residuos Storage esperables y documentados.
- `Write::delete` no es el camino productivo; sigue fallando por RESTRICT si alguien lo llama con images/ops.
- Aserción estructural `'vacío skip HMAC'` alineada al skip local (`commit_local` en el UseCase de registro; accept/seal solo en el advancer).

### 7.8 Tipos de prueba (contradicción «sin Storage» corregida)

Tres tipos **distintos**. No mezclar resultados:

| Tipo | Storage | HMAC delete-mandates | Cómo se ejecuta |
|------|---------|----------------------|-----------------|
| **A / B** | **No.** Listas y registros vacíos. Sin identities de imagen/op. | Sí. Seal obligatorio `expected_batch_count=0`. Mandato remoto **sin** obligaciones de objeto. | AJAX HTTP `aa_create_*` / `aa_delete_canonical_container` |
| **C** | **Sí.** JPEG sintético, authorize/PUT/finalize reales, familia canónica en bucket. Worker apagado: archivos **quedan**. | Sí. Accept tanda 0 + seal `expected_batch_count=1`. Obligación `physical_status=pending`. | Create AJAX; attach por el **mismo** camino de desarrollo acotado del inc. 3 (no producto); delete AJAX |
| **Clics de navegador** | No en la fixture viva (51 registros vacíos). | El primer clic del propietario sí sellará count=0. | Solo el propietario; **no** se pre-eliminó por AJAX |

No usar la lista **17**, registros 33/inc. 3, ni Backend 3. Worker **apagado**. Sin `is_ready=true`. Sin Render. Sin migraciones Supabase. Chunk de producción = **50** (no se bajó el límite).

### 7.9 Bloqueos

Ninguno de contrato backend para el 4. Fixture lista 17 / registro 33 se conservan. Incremento 5 = una imagen con registro vivo (§8). Lista 21 consumida (§7.11); lista 22 consumida en Continuar visual (§7.12). Node de prueba detenerse al cerrar §7.12.

### 7.10 Validación integrada policyytest (2026-09-14, inc. 4)

Ejecutada contra WordPress local + Node local `:3000` + Supabase remoto compartido. **No** se activó `is_ready`, **no** se desplegó Render, **no** se migró Supabase, **no** se arrancó el worker, **no** se tocó lista 17 ni Backend 3.

#### Regresión HMAC (componente extraído)

`CanonicalPurgeRemoteMandateAdvancer` lo usan retiro de registro y de contenedor.

Suite `AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-retire-canonical-record-ac.php`: **45/45**.

Protocolo **PASS** (comportamiento): vacío sin HMAC (0 POST, registro borrado, corrida `completed`); intención persistida antes del HTTP; recuperación `already_accepted` / status sin segundo accept; seal perdido + recuperación; cancelación previa al envío; `cancel_rejected` tras remoto desconocido; tandas 0-based (51 ítems seq 0 luego 1, seal count=2); `resource_busy` en segunda conexión.

**Estructural `'vacío skip HMAC'`:** comprueba el comportamiento en el UseCase de registro (`prepared_batch_count` + `commit_local`) y la **ausencia** de `accept_delete_batch` / `seal_delete_mandate` en ese archivo tras extraer el advancer. No busca el nombre del método HMAC dentro del UseCase.

#### Entorno

| Dato | Valor |
|------|--------|
| Sitio | `http://localhost/deoia-platform/policyytest/agenda-app` |
| Blog | **61**, prefijo **`wp_61_`** (leídos, no asumidos) |
| HMAC `X-Client-Id` | `localhost/deoia-platform/policyytest` |
| `AA_API_BASE_URL` | `http://localhost:3000` (no Render) |
| `installation_id` | `e49a26c8-c7db-4a8a-a84a-2643ddfb8b29` |
| Schema | antes **29**, después **30** (solo blog 61; ver §7.5) |
| `images` | `is_ready=false`; 0 filas activas de lista |
| Node | **No había** listener en `:3000`. Arrancado para esta validación: `node index.js` pid **51399**, cwd `/home/roberto/dev/deoia-oauth-backend`, health `{"ok":true}`. Workers del `.env` en 0; el proceso no arrancó `attachmentDeleteWorker`. **Se deja en marcha** para los clics. No se detuvo ningún proceso ajeno. |

#### Resultados AJAX (no clics)

Creates y deletes por `admin-ajax.php` con cookie `deoia_admin` (id 1). Chunk consultado en código: `AA_Canonical_Purge_Local_Retire_Store` default **50**. Caso B: **51** registros vacíos.

| Caso | Resultado | Evidencia |
|------|-----------|-----------|
| A lista vacía | **PASS** | Lista **18** `INC4-A-empty-20260914`. Delete → 200 `confirmed`. Contenedor ausente. Corrida **6** `completed`, `prepared_batch_count=0`, inventario 0, `seal_intent_at`/`sealed_at` set. Mandato `7459b45b-cd3d-4a7a-908f-335a099ccf40` remoto `sealed_expected_batch_count=0`, `owned_obligation_count=0`, obligaciones **[]**. |
| B chunk + reanudación | **PASS** | Lista **19** `INC4-B-chunk-20260914`, registros **35–85**. 1ª petición: 409 `incomplete` `can_continue=true` `can_cancel=false`; contenedor vivo; queda registro **85**; corrida **7** `incomplete`; checkpoint `local_retire_after_record_id=84`; seal count=0; mandato `b2825cc4-1a60-4ee1-bb95-f45504d0c7ea`. 2ª petición **independiente** (mismo POST, sin `mandate_id`): 200 `confirmed`; hijos y contenedor ausentes; **misma** corrida 7 / mismo mandato `completed`; inventario 0 conservado. Remoto: seal 0, 0 obligaciones. |
| C lista con imagen | **PASS** (desarrollo acotado; **no** UI de producto) | Lista **20** `INC4-C-image-20260914`, registro **86**. Attach in-memory `images` ready + stub `is_active=1` + `is_readable`: imagen id **3**, 384×384, 2994 B, op `49491195-d3a9-4284-8af7-3122f7970941`. Catálogo persistido intacto. Delete AJAX → `confirmed`. Inventario 1, `batch_seq=0`, `prepared_batch_count=1`, `last_accepted_batch_seq=0`. Cuota `canonical_images_sum` 3628→634 (−2994); `confirmed_bytes` 2330782→2327788. Corrida **8** `completed` conservada. Mandato `d7a2ba7c-9067-4bfe-b388-eb76ae35692d` `sealed_expected_batch_count=1`. Obligación `1964d4de-e05f-464b-9a34-3883a1ada1c7` `physical_status=pending`. |

Backend 3 intacto: mandato `171419cc-…` `open`, 1 obligación.

**Navegador:** no operativo en esta sesión. AJAX ≠ clics.

#### Fixture para clics (no eliminar por AJAX)

| Campo | Valor |
|-------|--------|
| Título | `INC4-UI-continue-20260914` |
| ID | **21** |
| `public_id` | `7ac91da1-c295-435c-bdfb-1fd31a15189d` |
| Registros | **51** vacíos, ids **87–137**, títulos `INC4-UI-r001` … `INC4-UI-r051` |
| Corrida previa | **0** (el propietario dispara el sello) |
| Sitio | `http://localhost/deoia-platform/policyytest/agenda-app` |
| Iframe registros | `http://localhost/deoia-platform/policyytest/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance&view=records&container_id=21` |
| Iframe listas | `http://localhost/deoia-platform/policyytest/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance` |

Clics exactos:

1. Abrir el sitio o el iframe de registros de la lista **21**. Comprobar título e ids 87–137.
2. **Eliminar lista** → en el modal confirmar **Eliminar lista** (no **Cerrar** todavía).
3. Esperado: modal sigue; «La eliminación no terminó. Pulsa Continuar para seguir.»; el primario pasa a **Continuar**; **Cancelar eliminación** no aparece (ya hubo sello).
4. Pulsar **Cerrar**. Recargar la URL de registros.
5. Esperado: banner «No está borrada del todo»; sin FAB de alta ni edición de registros; **Eliminar lista** permanece; ~1 registro residual (keyset 50+1, análogo al caso B: quedaría **137**).
6. **Eliminar lista** → **Continuar** hasta `confirmed` y vuelta al listado de listas. La 21 desaparece.

Node **51399** debe seguir vivo. **No** Cancelar eliminación tras el primer intento.

#### Residuos físicos (worker apagado; no borrar)

Paths C aún en bucket `expediente-adjuntos` (los cuatro `found=true`):

- `installations/e49a26c8-c7db-4a8a-a84a-2643ddfb8b29/canonical/records/86/49491195-d3a9-4284-8af7-3122f7970941.jpg`
- `…/49491195-d3a9-4284-8af7-3122f7970941_summary.jpg`
- `…/49491195-d3a9-4284-8af7-3122f7970941_gallery.jpg`
- `…/49491195-d3a9-4284-8af7-3122f7970941_display.jpg`

No se borraron tombstones ni objetos. Mandatos A/B sellados vacíos permanecen en Supabase.

Lista 17 / registro 33 / Backend 3 no se usaron.

### 7.11 Primer clic de navegador (2026-09-14) — no PASS de Continuar

El propietario reportó dos discrepancias en la lista 21. **No** se marca pausa/reanudación visual como PASS.

#### Estado persistido (solo lectura; no se recreó la 21)

| Dato | Valor |
|------|--------|
| Contenedor 21 / `public_id` `7ac91da1-…` | **ausente** |
| Registros 87–137 | **ausentes** (0 en ese rango) |
| Corrida | **9**, `scope=container`, `status=completed` |
| Mandato | `7920fa4a-03ae-4392-8815-ab3c180e98b2` |
| Inventario | 0 filas; `prepared_batch_count=0`; `last_accepted_batch_seq=null` |
| Sello | `seal_intent_at` / `sealed_at` = `2026-09-14 22:31:08` (UTC) — count=0 acreditado |
| Checkpoint | `local_retire_after_record_id=137` (último id del alcance) |
| Tiempos corrida | `created_at` `:08`; `updated_at` `:16` |

Chunk de producción intacto (`AA_Canonical_Purge_Local_Retire_Store` default **50**). 51 registros vacíos **no** caben en una sola petición; el checkpoint 137 acredita un **segundo** chunk local. No hay defecto de chunk en servidor. No se añadió regresión MySQL de chunk.

Apache `access.log` (hora local 16:31 = 22:31 UTC), Referer = iframe `container_id=21`, Chrome:

1. `16:31:08` POST `admin-ajax.php` → **409** (765 B) = `incomplete`
2. `16:31:16` POST `admin-ajax.php` → **200** (854 B) = `confirmed`
3. GET inmediato al listado `family=finance` (`location.assign` tras confirmed)

Dos POSTs **secuenciales** (~8 s). No son dos confirms en paralelo (eso habría sido dos POST a las `:08`). El intervalo coincide con la duración de la primera petición (captura + sello count=0 + 50 DELETE). `completed` **no** basta para contar peticiones; aquí el access log sí las distingue. Node pid **51399** no registra líneas HMAC de este intento (solo arranque); no se reinició Node. JS servido: `canonical-shell-container-form.js?ver=3.4.1` (fuente del plugin, no bundle webpack).

**No reconstruible:** si el segundo POST fue un clic explícito en Continuar, un Enter/clic retenido al reactivarse el botón, u otra acción humana en el mismo segundo. El propietario no acredita Continuar. El código **no** reenviaba solo al recibir `incomplete`. Los dos botones duplicados **no** prueban dos handlers de confirmación en paralelo.

#### Botones duplicados

Causa: markup PHP duplicado en el header fill de `includes/admin/ui/modules/canonical_shell/index.php` (commit `17cf69e`: dos `.aa-shell-delete-container-btn` idénticos junto a Editar y Detalles). No fue clonación JS ni doble init del iframe. Corrección: un solo Eliminar lista en `.aa-shell-list-header-actions`. No se ocultó con CSS.

#### Por qué no se vio incomplete

La primera petición sí devolvió 409 `incomplete`. El JS no hace retry automático. Tras ~8 s el confirm se reetiquetaba Continuar y se reactivaba; una segunda petición inmediatamente después completa el chunk restante y `location.assign` al listado. El modal no permanece visible. Cooldown 400 ms + bind-once del IIFE + ignore `e.repeat` en confirm impiden ese reenvío inmediato. Cerrar sigue sin fetch.

#### Pruebas dirigidas

- JS `canonical-shell-container-form.test.js`: una sola solicitud por confirmación (incl. doble boot); incomplete conserva Continuar sin reenvío automático; Cerrar no POST; confirmed solo `location.assign` tras éxito final.
- Ajax AC: fill header una sola acción Eliminar; bind + cooldown en JS.
- Records-nav AC: fill header 1 Eliminar + 1 Editar (harness de contributors vacío para `compose_family_records`).
- Record AC estructural `'vacío skip HMAC'`: 45/45.

#### Fixture de reemplazo (no pulsar Eliminar)

Sustituye la lista 21. Chunk de producción intacto (50). 0 corridas. 0 imágenes. Lista 17 / registro 33 / Backend 3 intactos.

| Campo | Valor |
|-------|--------|
| Título | `INC4-UI-continue-20260914-r2` |
| ID | **22** |
| `public_id` | `f146d231-d841-4561-b4ae-96a76e22742d` |
| Registros | **51** vacíos, ids **138–188**, títulos `INC4-UI-r2-001` … `INC4-UI-r2-051` |
| Header fill | 1× Eliminar lista (HTML SSR; no navegador) |
| Sitio | `http://localhost/deoia-platform/policyytest/agenda-app` |
| Iframe registros | `http://localhost/deoia-platform/policyytest/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance&view=records&container_id=22` |
| Iframe listas | `http://localhost/deoia-platform/policyytest/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance` |

Clics (el agente **no** pulsó Eliminar):

1. Recarga forzada (Ctrl+Shift+R): el JS sigue `?ver=3.4.1`.
2. Abrir iframe de registros de la **22**. Un solo «Eliminar lista» junto a Editar y Detalles.
3. Eliminar lista → confirmar. Esperado: modal sigue; «La eliminación no terminó…»; primario **Continuar**; sin Cancelar eliminación.
4. **Cerrar**. Recargar. Banner «No está borrada del todo»; ~1 registro residual (keyset 50+1; análogo al caso B).
5. Eliminar lista → **Continuar** (un clic, esperar). Confirmed → listado de listas.

Network opcional: filtro `admin-ajax.php`. 1ª POST 409 `incomplete`; 2ª solo tras Continuar, 200 `confirmed`. No pedir cookies.

### 7.12 Continuar visual desde listado (lista 22, 2026-09-14)

#### Observación del propietario (UI)

- Inició **Eliminar lista** desde el **listado de listas** (fuera del contenedor).
- Tras la primera confirmación, el modal mostró: «La eliminación no terminó. Pulsa Continuar para seguir.»
- Pulsó **Continuar**.
- La lista desapareció.

Acredita visualmente **incomplete → Continuar → desaparición final**. **No** se probó Cerrar/recargar en incomplete, ni el banner interior, ni la ausencia de altas: esas partes **no** PASS.

#### Resultado final persistido (solo lectura; no se repitió el borrado)

Blog **61** / `wp_61_`. Contenedor **22** / `public_id` `f146d231-d841-4561-b4ae-96a76e22742d` **ausente**. Registros `container_id=22` = **0**; ids **138–188** = **0**.

| Dato | Valor |
|------|--------|
| Corrida | **10**, `scope=container`, `target_id=22`, `status=completed` |
| Mandato (única corrida) | `518397ab-67ee-42b5-89d1-f2581fa3790d` — mismo id en toda la continuación |
| Inventario | **0** filas conservadas bajo `purge_run_id=10`; `prepared_batch_count=0`; `last_accepted_batch_seq=null` |
| Sello local | `seal_intent_at` / `sealed_at` = `2026-09-14 23:18:04` (UTC) |
| Sello remoto (status HMAC) | `inventory_status=sealed`, `sealed_expected_batch_count=0`, `owned_obligation_count=0`, `sealed_at=2026-09-14T23:18:05Z` |
| Checkpoint | `local_retire_after_record_id=188` (último id del alcance) |
| Tiempos corrida | `created_at`/`sealed_at` `:04`; `updated_at` `:51` |

Apache `access.log` (hora local −0600; Referer = iframe **listas** `family=finance`, sin `view=records`):

1. `17:18:04` POST `admin-ajax.php` → **409** (765 B) — compatible con `incomplete`
2. `17:18:51` POST `admin-ajax.php` → **200** (854 B) — compatible con `confirmed`
3. `17:18:51` GET listado `family=finance` — redirect tras confirmed

Dos POSTs secuenciales (~47 s). Una sola corrida / un solo mandato. Chunk 50 intacto (51 registros requieren segunda petición). Lista **17** / registro **33** intactos. Backend 3 no tocado.

#### Pendiente visual (no PASS)

- Cerrar el modal en `incomplete` y recargar.
- Banner «No está borrada del todo» dentro de registros.
- Bloqueo de altas / edición mientras la purga está incompleta.

Las demás limitaciones ya registradas (Continuar UI inc. 3; C integrado inc. 3; `is_ready`; worker; retiro de una imagen §8) **permanecen**.

## 8. Incremento 5 — retiro de una imagen (registro vivo) — **implementado**

**Estado:** implementado en `dev/canonical-images-retire`. AC MySQL aislado + Ajax estructural + JS modal PASS. Sin HTTP remoto real ni Storage en este turno. Cerrar/banner/altas del **incremento 4** siguen **no** PASS.

**Norma:** `docs/05-canonical-capabilities.md` §12.4 — eliminar una imagen elimina sus objetos asociados; aplica aunque `images` esté inactiva en la lista; fallo a medias → incompleto + Continuar.

**Clasificación:** capability `images` + runtime WP (IMG-5). No es shell genérico, no es familia nueva, no es API pública.

**Producto (cerrado; no reabrir):**

- Quitar **una** imagen confirmada.
- Conservar el **registro** (title, details, amount u otras capabilities).
- Conservar **otras** imágenes y operaciones no inventariadas en esta corrida.
- La cuota comercial (`SUM` de `aa_canonical_record_images.byte_size`) baja al retirar la asociación SQL.
- La limpieza física sigue siendo obligación durable del backend + worker; WP no llama `POST /expediente/attachments/delete` ni borra Storage.

### 8.1 Hechos del código actual (comprobados)

| Hecho | Detalle |
|-------|---------|
| Scopes de purge | Solo `record` \| `container` (`CanonicalPurgeRunsRepository`). `scope` es `varchar(32)` sin ENUM SQL. |
| Captura | `CaptureCanonicalPurgeInventoryCommand` rechaza cualquier otro scope; páginas por registro o contenedor completos. |
| Inventario | Identidad durable = `upload_operation_id`; también `wp_record_id`, sha, byte_size, `storage_path`; `source` image\|operation\|both. Sin columna de `image.id`. |
| Retiro local registro | `AA_Canonical_Purge_Local_Retire_Store::retire_record` borra identidades del inventario, **exige** que no queden filas vivas fuera del inventario, **DELETE del registro**, touch contenedor. **Incompatible** con conservar el registro. |
| Advancer HMAC | `CanonicalPurgeRemoteMandateAdvancer` ya sirve tandas 0-based y seal con `prepared_batch_count` (incl. 0 y 1). |
| Backend | Accept exige `upload_operation_id` + metadatos; max 50; tombstone por `(installation_id, upload_operation_id)`. Seal `expected_batch_count≥0` (1 ítem = seal 1) ya soportado. |
| UI pública | `CanonicalRecordImagePublicDto` expone `{id,width,height,byte_size,created_at}` — **sin** `upload_operation_id`. Sign-read resuelve `image_id` → fila completa en servidor. |
| Contributor | `CanonicalImagesRecordsPageContributor` → `not_offered` mientras `is_ready=false`. Partial de card no pinta galería. |
| Attach / sign | Exigen `images` ready + activa en lista. Delete de registro/lista **no** exige ready (§12.4). |
| Blocking | `has_blocking_purge(record,container)` solo mira scopes `record`\|`container`. `has_blocking_purge_for_container` mira cualquier abierta con ese `container_id`. |
| Confirm attach | INSERT image → DELETE op → touch **contenedor** `updated_at`. Tombstone remoto **no** se crea en confirm; se crea en accept. |

### 8.2 Diseño único elegido

**Un mandato / una corrida / alcance imagen**, reutilizando captura durable + accept + seal + retiro local acotado.

| Campo de corrida | Valor |
|------------------|--------|
| `scope` | **`image`** (nueva constante Application; no reutilizar `record`) |
| `target_id` | **`aa_canonical_record_images.id`** (bigint estable hasta el DELETE local) |
| `container_id` | Siempre el contenedor del registro dueño |
| `record_id` | **Columna nueva nullable** en `aa_canonical_purge_runs` (`DB_VERSION=31`): el registro dueño, sellado al abrir. Necesaria para blocking/recuperación sin JOIN a la fila de imagen (que desaparece al completar) |

**Por qué no `scope=record` filtrado:** la captura y `retire_record` actuales significan “vaciar y borrar el registro”; Continuar/conflicto colisionarían con Eliminar registro; un filtro ad hoc sobrecarga `target_id` sin dejar de bloquear el slot de purge de registro entero.

**Por qué `target_id = image.id` y no el UUID de operación:** `target_id` es bigint; el UUID vive en inventario (`upload_operation_id`), que es la identidad del mandato. El cliente habla en `image_id` (como sign-read); el servidor resuelve a operación.

**Flujo:**

1. Autorizar familia (Access Policy / `authorize_identity`). **No** exigir `images.is_ready` (norma §12.4). Comprobar pertenencia: imagen → registro → contenedor → familia.
2. `GET_LOCK` del contenedor (mismo patrón). No mantener TX SQL durante HTTP.
3. Abrir o reanudar corrida `scope=image`, `target_id=image_id`. Overlap → conflicto / `resource_busy` según reglas §8.4.
4. Captura **puntual** (no página de todo el registro):
   - Fila imagen por `id` + `record_id` esperado.
   - Op `admitted|cleanup_needed` con el **mismo** `upload_operation_id`, si existe (tras confirm suele no haber op).
   - Metadatos (sha, byte_size, path) deben coincidir entre fuentes; discrepancia → **conflicto explícito** (`capture_conflict` / equivalente), sin accept.
   - Inventario: 0 o 1 identidad (`upload_operation_id`). `prepared_batch_count` 0 o 1; `batch_seq` 0-based.
5. Si inventario 0 (asociación ya ausente al capturar): cerrar como el vacío de registro — **sin** inventar DELETE del registro; marcar completed / confirmed según corrida (ver §8.5). No hace falta seal vacío salvo que haya corrida abierta con mandato ya comunicado; entonces recuperar vía status/seal como hoy.
6. Si inventario 1: `CanonicalPurgeRemoteMandateAdvancer` → accept tanda 0 → seal `expected_batch_count=1` (misma política común que contenedor: seal obligatorio cuando hubo preparación). Evidencia: `last_accepted_batch_seq` / `sealed_at` / status remoto.
7. TX local (`retire_image`): DELETE solo image+op del `upload_operation_id` inventariado; **no** tocar otras imágenes/ops; **no** DELETE del registro; touch `updated_at` del **contenedor** (misma recencia que confirm attach); `mark_completed`; conservar corrida e inventario.
8. Respuesta: `confirmed` (registro vivo; opcional `redirect_url` o payload con `record_id`/`image_id` ausente). `incomplete` no debería aparecer con N≤1 salvo fallo de protocolo a medias (accept sin seal, etc.) → Continuar. `uncertain` → recargar, sin retry ciego.

**Tombstones:** no se borran. Una subida nueva al mismo registro usa **nuevo** `upload_operation_id`; el tombstone de la operación retirada no bloquea identidades distintas. No reutilizar la operación retirada.

### 8.3 Componentes reutilizados vs nuevos

| Reutilizar | Nuevo / extender |
|------------|------------------|
| Cliente HMAC accept/seal/status | `SCOPE_IMAGE` + validación en repos |
| `CanonicalPurgeRemoteMandateAdvancer` | Captura puntual (método/store o UseCase hermano; **no** reutilizar páginas record/container sin filtro) |
| Locks `canonical_container` | `RetireCanonicalRecordImageUseCase` (nombre orientativo) |
| Inventario / corridas / intención HMAC | `AA_Canonical_Purge_Local_Retire_Store::retire_image` (o equivalente) |
| Patrones AJAX delete registro/lista (nonce, incomplete, Continuar, Cancelar local, uncertain) | `aa_delete_canonical_record_image` + support cableado |
| Resolución `image_id` → fila (como sign-read) | Extender `has_blocking_purge` / `find_overlapping_open_run` |
| Schema purge_runs/items (base) | `DB_VERSION=31`: columna `record_id` nullable + verify; **sin** ENUM SQL nuevo (varchar admite `image`) |

**No reutilizar:** `RetireCanonicalRecordUseCase` / `retire_record` / captura scope=record completa / `WriteCanonicalShellRecordUseCase::delete` / delete síncrono Expedientes / `POST /expediente/attachments/delete`.

### 8.4 Concurrencia y recuperación

| Escenario | Comportamiento acordado |
|-----------|-------------------------|
| Purge `image` abierta vs attach/confirm en **ese** registro | Bloqueo vía `has_blocking_purge` extendido (`scope=image` + `record_id`) → `purge_in_progress`. |
| Purge `image` vs Eliminar **ese** registro / **esa** lista | Overlap: open `record` del mismo `record_id` o open `container` del mismo `container_id` ↔ open `image` → `scope_overlap` / conflicto; un solo mandato de alcance. |
| Dos Eliminar imagen del **mismo** `image_id` | Segunda ve open run o imagen ausente; no segundo mandato. |
| Dos imágenes distintas del mismo registro | Serializadas por lock de contenedor; pueden existir corridas distintas en el tiempo; no dos abiertas que se pisen sin overlap rule — **mínimo:** segunda espera lock; si la primera sigue abierta sobre otro `image_id` del mismo `record_id`, overlap de registro (bloquear) **o** permitir si solo se toca otra identidad — **elegido: bloquear a nivel registro** mientras haya cualquier `scope=image` abierta con ese `record_id` (simple, fail-closed; evita attach a medias). |
| Confirm/reanudación misma op | Confirm ya borró la op; captura solo ve imagen. Replay accept → `already_accepted` / status. |
| Respuesta remota perdida | Igual que inc. 3: intención local + status / already_accepted antes de re-POST. |
| Cancelación | Solo si **no** hubo intento remoto (`has_attempted_remote_dispatch`); conserva corrida+inventario cancelados; libera escritores. |
| Commit local ambiguo | `uncertain`; no afirmar confirmed; recargar. |
| Reintento: imagen ausente, registro vivo | Si corrida completed → confirmed idempotente. Si no hay filas ni open run → confirmed/`image_not_found` estable (análogo a record_not_found tras completed). Nunca borrar el registro. |
| Subida nueva tras retiro | Nuevo `upload_operation_id`; attach vuelve a exigir ready+activa; tombstone viejo no aplica. |

**Escritores a trazar al implementar (no declarar “seguro” sin cablear):** attach/confirm, Write delete record/container, Retire record/container, y el nuevo Retire image. Hoy attach **no** ve `scope=image` — extensión obligatoria.

### 8.5 Retiro local (TX)

Orden bajo lock ya adquirido, **después** de evidencia de seal (o skip vacío acreditado):

1. DELETE `aa_canonical_image_upload_operations` por `upload_operation_id` inventariado (si existe).
2. DELETE `aa_canonical_record_images` por el mismo `upload_operation_id` (o `id`+op coherente).
3. Assert: esas identidades ya no existen; **otras** imágenes del registro pueden seguir.
4. Touch `aa_canonical_containers.updated_at` del `container_id` de la corrida.
5. `mark_completed` de la corrida.
6. COMMIT. Inventario y corrida **permanecen**.

No contadores de cuota. No Storage. No DELETE de `aa_canonical_records`.

### 8.6 Punto de entrada UX (mínimo)

- **AJAX:** `aa_delete_canonical_record_image` (nombre orientativo). Body: `family_key`, `image_id`, nonce, opcional `retire_action=cancel`. Servidor resuelve registro/contenedor. **Sin** gate `is_ready`.
- **UI:** sin galería nueva ni subida/reemplazo. Patrón del modal de Eliminar registro (confirmación que mencione objetos asociados / limpieza diferida según copy ya usado en lista/registro). Control “Eliminar imagen” por miniatura cuando el SSR exponga el DTO público.
- Con `is_ready=false` el contributor de página sigue `not_offered` para producto general; para cumplir §12.4 (borrar con capability inactiva) el SSR de delete puede listar filas existentes vía repositorio **sin** exigir ready, solo Access Policy + pertenencia. No activar seeds ni `is_ready`.
- Continuar / Cancelar local / uncertain: mismos contratos de estado que delete de registro.

### 8.7 Cambios mínimos por capa

| Capa | Cambio |
|------|--------|
| Schema | `DB_VERSION=31`; `aa_canonical_purge_runs.record_id` NULL; verify/migrate. Sin tablas nuevas. |
| Domain/repos | `SCOPE_IMAGE`; open/find/overlap/blocking conscientes de `image` + `record_id`. |
| Application | Captura puntual; `RetireCanonicalRecordImage*`; local `retire_image`; cablear advancer. |
| HTTP | Un endpoint AJAX + registro en bootstrap. |
| UI | Botón + modal/JS mínimos; sin galería. |
| Backend repo | **Ninguno.** |

### 8.8 Matriz de pruebas

**Automatizadas (MySQL aislado + stub HMAC; sin Storage real):**

| Caso | Expectativa |
|------|-------------|
| Una imagen; registro con title/details/amount | Imagen/op inventariadas ausentes; registro y amount intactos |
| Segunda imagen en el mismo registro | Intacta |
| Op no relacionada / otra identidad | Intacta |
| Cuota SUM | Baja exactamente `byte_size` de la imagen retirada |
| Recencia | `container.updated_at` avanza; registro no se borra |
| Familia/contenedor ajenos | Rechazo |
| Accept perdido → status / already_accepted | Sin segundo accept inventado |
| Seal perdido → recuperación | Sin DELETE local prematuro |
| Cancel antes de remoto | Cancelled; escritores libres; sin mandato remoto |
| Overlap image↔record / image↔container | Conflicto; un alcance |
| Doble delete misma imagen | Idempotente / not_found estable; registro vivo |
| Imagen ausente, registro vivo | No DELETE registro |
| Nueva subida (identidad nueva) tras completed | No bloqueada por tombstone de la op vieja (stub de issuance) |

**Manual policyytest:** §8.12 (AJAX+SSR) + §8.13 (clics propietario imagen 5). Incomplete/Continuar forzado **no** se ejecutó. Observaciones residuales del **incremento 4** conservadas (§9.2). Lista 17 / registro 33 / Backend 3 conservados.

### 8.9 Bloqueos y veredicto

| Ítem | Estado |
|------|--------|
| Contrato backend single-item + seal 1 | Listo (sin cambio backend) |
| Advancer HMAC | Listo para reutilizar |
| Captura/retiro local actuales scope=record | **No** sirven sin ramificación — bloqueo de diseño ya resuelto en §8.2 |
| Blocking APIs vs futuro `scope=image` | Debe implementarse en el mismo incremento |
| `is_ready=false` | No bloquea el diseño del delete (§12.4); sí limita galería de producto |
| Worker / Render / tombstones | Fuera; no bloquean el diseño WP |

**Veredicto:** **implementado** según §8.2–8.7. No reabrir subida ni limpieza física del backend. Capability `images` no cerrada (`is_ready=false`).

### 8.10 Relación con pendientes visuales del 4

Cerrar/recargar en incomplete, banner interior y bloqueo de altas del retiro de **lista** siguen **no PASS** (§7.12). Este incremento **no** los acredita ni los reabre.

### 8.11 Procedimiento manual policyytest

1. Blog policyytest; Node local; worker Storage **apagado**.
2. Crear **nueva** imagen sintética en un registro vivo (camino de desarrollo acotado; **no** reutilizar fixtures de lista 17 / registro 33 / Backend 3).
3. SSR: comprobar botón «Eliminar imagen» y, tras incomplete forzado, banner Continuar.
4. AJAX/UI: Eliminar imagen → `confirmed`; registro visible; cuota SUM baja; residuo Storage esperado.
5. Incomplete/Continuar solo si se fuerza fallo de protocolo.
6. No marcar PASS de Cerrar/banner/altas del incremento 4.

Ejecución: §8.12. Incomplete/Continuar forzado sigue opcional/no ejecutado.

### 8.12 Validación integrada policyytest (2026-09-15 UTC)

Plugin HEAD `8093e71d869f816cbc816268381f40379323e086` (`dev/canonical-images-retire`). Backend HEAD `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` (`dev/backend-recovered`, sin cambios de código).

#### Entorno

| Dato | Valor |
|------|--------|
| Sitio | `http://localhost/deoia-platform/policyytest/agenda-app` |
| Blog / prefijo | **61** / **`wp_61_`** |
| Identidad HMAC | `localhost/deoia-platform/policyytest` |
| `AA_API_BASE_URL` | `http://localhost:3000` |
| `installation_id` | `e49a26c8-c7db-4a8a-a84a-2643ddfb8b29` (derivado por backend; WP no lo envía) |
| Schema | **antes = 30 → después = 31** vía `AA_Schema::maybe_migrate()` **solo** en blog 61 (`get_option` + `$wpdb->prefix`). Columna `aa_canonical_purge_runs.record_id` presente. Alcance: tablas/option de este blog; no se visitó admin de otros blogs. |
| `images` | `is_ready=false`; 0 filas activas de lista |
| Node | Arrancado para esta validación: `node index.js` **pid 63173**, health `{"ok":true}`. `ATTACHMENT_DELETE_WORKER=0`. Se **deja corriendo** para clics del propietario. |

#### Fixtures nuevas (no lista 17 / registro 33 / Backend 3)

Lista finance **id=23** `INC5-IMG-RETIRE-20260914` (`public_id=f96d62dc-aa23-46bb-a2e0-4a601bed3e87`). Capacidad de lista: `amount` activa (materializada por default familiar; **no** se tocó `images`).

| Rol | Record | Título | Amount | Imagen | `upload_operation_id` | bytes | path |
|-----|--------|--------|--------|--------|------------------------|-------|------|
| AJAX integrado | **189** | `INC5-AJAX-image-retire` | `61.50` | **id=4** (retirada) | `d582110f-af27-41b3-835e-94c08dcafe2c` | 2994 | `…/canonical/records/189/d582110f-….jpg` |
| Clics propietario | **190** | `INC5-OWNER-image-clicks` | `42.00` | **id=5** (viva) | `4b87205a-491b-492a-8a9e-b4084de2337a` | 2994 | `…/canonical/records/190/4b87205a-….jpg` |
| Control aislamiento | **191** | `INC5-CONTROL-isolation` | `7.00` | **id=6** (viva) | `d6b73ea7-3633-4ec4-94f4-fc2594da1f34` | 2994 | `…/canonical/records/191/d6b73ea7-….jpg` |

**Sustitución de desarrollo (acotada, no producto):** misma que inc. 3/4 caso C — registry in-memory `images` ready + stub `find_container_capability` `is_active=1` + validador `is_readable`. Sustituye **solo** el gate de catálogo/lista y el chequeo `is_uploaded_file`. Authorize/PUT/finalize/HMAC delete y TX de producto son los de instalación. Catálogo persistido intacto (`is_ready=false`, 0 filas `images` activas). **No** es subida habilitada en UI de producto. Un registro = una imagen (contrato vigente).

#### AJAX `aa_delete_canonical_record_image` (registro 189 / imagen 4) — **PASS**

- `success.status=confirmed`; redirect al iframe de la lista 23.
- Corrida **11** `scope=image` `target_id=4` `record_id=189` `status=completed`; `prepared_batch_count=1`; `last_accepted_batch_seq=0`; `accept_intent_batch_seq=0`; `sealed_at` / `seal_intent_at` set.
- Inventario 1 fila: op `d582110f-…`, `batch_seq=0`, `byte_size=2994`; corrida+inventario **conservados**.
- Imagen 4 y su op **ausentes**; registro **189** vivo con título/detalles/`amount=61.50` intactos; `record.updated_at` sin cambio (contrato: touch de **contenedor**).
- Contenedor 23 `updated_at` avanzó a `2026-09-15 00:09:25` UTC.
- Cuota `canonical_images_sum` 9616→6622 (−2994). `confirmed_bytes` no aplicable en este blog (tabla ausente/null).
- Imágenes 5 y 6 + registros 190/191 intactos.
- Reintento autorizado misma imagen 4 → `confirmed` otra vez; **misma** corrida/mandato; cuota y controles sin cambio.

#### Remoto (worker apagado)

- Mandato `4c116538-85c6-4294-bea6-809a00d9d347`: `inventory_status=sealed`, `sealed_expected_batch_count=1`, `reception_item_count=1`, `structural_retire_authorized=true`.
- Obligación `90ca4855-4fbd-4c17-821c-c2504be0545a` `physical_status=pending` (residuo Storage **esperado**; no borrado manual; worker no activado).

#### SSR / preparación de clics del propietario

- `is_ready=false` **no** oculta el delete mínimo: el shell lista filas vía `CanonicalRecordImagesRepository` sin exigir ready (§8 / §12.4).
- HTML real del iframe lista 23 incluye `.aa-shell-delete-image-btn`, modal `#aa-shell-delete-image-modal` y boot `aa_delete_canonical_record_image`.
- **URL:** `http://localhost/deoia-platform/policyytest/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance&view=records&container_id=23`
- **Registro a usar:** título `INC5-OWNER-image-clicks`, id **190**, imagen **#5** (botón «Eliminar imagen» en la card).
- **No** se pre-eliminó por AJAX. Control 191/imagen 6 debe permanecer si solo se borra la 5.
- **Clics de navegador:** propietario **acreditó** Eliminar imagen sobre registro **190** / imagen **5** (§8.13). Comprobado además por SQL/HTML/AJAX HTTP en §8.12–8.13. La ausencia de miniatura **no** se investiga ni se trata como fallo de eliminación.

#### Limitaciones (sin reclasificar)

- Cerrar/recargar/banner/altas del **4** y Continuar UI / C de red del **3** siguen documentados como observaciones residuales (**no** PASS; **no** bloqueo funcional del retiro confirmado) — ver §9.
- Residuos físicos (worker apagado) pendientes de ops — ver §9.2.
- Attach de desarrollo **no** acredita galería/`is_ready`.

### 8.13 Clics del propietario — imagen 5 / registro 190 (2026-09-15 UTC)

**Observación del propietario:** en la card del registro 190 pulsó «Eliminar imagen»; el modal mostró confirmación y la acción pareció completarse; el registro permaneció. No veía miniatura antes — **no** es fallo de eliminación ni autorización de galería.

**Verificación solo lectura (blog 61 / `wp_61_`, sin repetir borrado ni fixtures):**

| Comprobación | Resultado |
|--------------|-----------|
| Imagen **5** | Ausente (`COUNT=0`) |
| Registro **190** | Vivo: título `INC5-OWNER-image-clicks`, details intactos, `amount=42.00`; ops del registro = 0 |
| Control **191** / imagen **6** | Intactos (op `d6b73ea7-…`, 2994 B) |
| Corrida | **12** `scope=image` `target_id=5` `record_id=190` `status=completed`; `prepared_batch_count=1`; `last_accepted_batch_seq=0`; `sealed_at` / `seal_intent_at` set |
| Inventario | 1 fila conservada: op `4b87205a-…`, `batch_seq=0`, 2994 B |
| Cuota | `canonical_images_sum=3628` (= 6622 post-AJAX imagen 4 − 2994) |
| Contenedor 23 | `updated_at=2026-09-15 00:17:01` (touch de recencia) |
| Remoto | Mandato `5381bc3c-5727-4f79-a7e4-f6b0cfb690d7` sealed (`sealed_expected_batch_count=1`); obligación `becc0a5b-8dec-4cf7-9487-e131842fdf80` `physical_status=pending` |

**Veredicto imagen (alcance `scope=image`):** desarrollo + HTTP + observación propietario **cerrados** para este recorrido. Residuo Storage esperado con worker apagado.

---

## 9. Balance de cierre — etapa de eliminaciones IMG-5

**Desarrollo de eliminación (WP + mandatos HMAC):** **cerrado** para los tres alcances. No queda bloqueo funcional conocido que impida dar por terminado el desarrollo de retiro canónico de imagen / registro / contenedor. Las observaciones visuales residuales y el worker **no** reabren el diseño de delete.

### 9.1 Tres alcances

| Alcance | Implementación | Automatizadas | Integración HTTP policyytest | Observación propietario |
|---------|----------------|---------------|------------------------------|-------------------------|
| **Imagen** (`scope=image`, DB 31) | `RetireCanonicalRecordImageUseCase` + `aa_delete_canonical_record_image`; captura puntual; seal count=1; TX solo image+op | AC MySQL/Ajax/JS **PASS** | AJAX imagen 4 / reg. 189 **PASS** (§8.12); reintento idempotente **PASS** | Eliminar imagen reg. **190** / img **5** acreditada + SQL/remoto (§8.13) |
| **Registro** (`scope=record`, DB 29) | `RetireCanonicalRecordUseCase` + `aa_delete_canonical_record`; skip HMAC vacío; Continuar/cancelar | AC **PASS** | A/D/B AJAX **PASS** (§6.7); UI vacío+Cancelar acreditados | — |
| **Contenedor** (`scope=container`, DB 30) | `RetireCanonicalContainerUseCase` + `aa_delete_canonical_container`; seal incl. 0; chunks | AC **PASS** | AJAX A/B/C **PASS** (§7.10); Continuar desde listado lista 22 **PASS** (§7.12) | — |

### 9.2 Observaciones residuales (conservadas; ni PASS ni fallo de producto)

| Ítem | Estado documentado |
|------|-------------------|
| Continuar UI de registro (inc. 3) | Observación residual — no PASS |
| C integrado interrupción de red (inc. 3) | Observación residual — no PASS |
| Cerrar / recargar incomplete / banner interior / bloqueo de altas (inc. 4) | Observaciones residuales — no PASS; lista 21 primer intento documentado §7.11 |
| Miniatura ausente en fixture INC5 | **Fuera de esta etapa**; no investigada; no es fallo de delete |

### 9.3 Pendientes antes de producción (ops / producto; no reabren delete)

| Pendiente | Notas |
|-----------|--------|
| **Worker periódico Storage** | Sigue **apagado** (`ATTACHMENT_DELETE_WORKER=0`). Activación y validación operativa **obligatorias** antes de producción. No activado en estas pruebas; residuos `physical_status=pending` esperados (p. ej. obligaciones de imgs 4 y 5; control 6 aún vivo en WP). No borrar Storage a mano. |
| **`is_ready` / seeds / galería de producto** | Fuera del cierre de eliminaciones; ver §10 |
| **Render / despliegue backend compartido** | Ops aparte; fixture Backend 3 no reejecutada |

### 9.4 Node local de validación

Instancia arrancada para estas pruebas: `node index.js` **pid 63173**, `:3000`, health ok, workers off. **No detenida** en el cierre (puede estar en uso del propietario para Expedientes u otras pruebas).

---

## 10. Integración modular `images` ↔ capabilities (exploración 2026-09-15)

**Naturaleza:** solo lectura y documentación. No se activó `is_ready`, no se tocaron schema/runtime/datos, no se reabrieron eliminaciones ni fixtures de miniaturas. Norma vinculante: `docs/05-canonical-capabilities.md` §§2–5 y §12. Canon amount comprobado en código; `images` contrastada contra el mismo recorrido.

**HEAD comprobados:**

| Momento | Plugin | Backend |
|---------|--------|---------|
| §10 inicial | `b3c6a727e5b1a6b614a5c38a7438f22bc5ebca0d` | `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` |
| §11 (esta sesión) | `f9aee8299ee6c986426a3427859b87db3a896528` (`dev/canonical-images-retire`; sin avance respecto al SHA conocido) | `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` (`dev/backend-recovered`; sin avance; untracked ajeno `scripts/runner-from-pack.sh`) |

Locales ajenos en plugin: `.cursor/hooks/__pycache__/` (no tocar).

### 10.1 Canon comprobado mediante `amount`

Cuatro capas (no confundir):

| Capa | Significado | Persistencia / código |
|------|-------------|------------------------|
| Disponibilidad técnica | Catálogo sellado `is_ready` | `AA_Canonical_Capability_Registry_Bootstrap` (`amount` ready) |
| Asignación a familia | Repertorio + `is_default` | `aa_canonical_family_capabilities`; seed lifecycle solo `finance`/`amount`/`is_default=1` |
| Activación por lista | Asignada + `is_active` | `aa_canonical_container_capabilities`; materializer o `scope`+`selection` |
| Datos en registros | Valor tipado | `aa_canonical_record_amount` |

**Familias reales** (`AA_Canonical_Core_Bootstrap`): `finance`, `archive`, `catalog`, `contact` (labels Finanzas / Archivo / Catálogos / Contactos).

Recorrido real:

1. **Registro:** `AA_Canonical_Capability_Definition` + registry freeze (`includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php`).
2. **Repertorio:** `AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds()` insert-if-missing; Ops/`SetFamilyCapabilityDefaultUseCase` pueden asignar `amount` a **otra** familia sin duplicar el handler. Lifecycle **omite** seeds de capacidades `!is_ready`.
3. **Defaults → lista nueva:** `AA_Canonical_Capability_Defaults_Materializer` + `AA_Canonical_Materialize_Family_Defaults_Effect` (solo ready + `is_default`) cuando create **omite** selección.
4. **UI create/edit lista:** el modal «Campos y funciones» con mount operativo **envía siempre** `capability_selection_scope` + `capability_selection` (`canonical-shell-container-form.js` `collectCapabilitySelectionFields`). Create con checkboxes pinta `checked = is_default`; edit pinta la elección persistida ∩ repertorio ready. Por tanto el camino omit→materializer **no** es el habitual del modal operativo; el habitual es selección **explícita**.
5. **Checkbox «Importe»:** opciones = repertorio ∩ ready (`index.php` ~1055–1067); label especial `amount`→«Importe»; montaje `#aa-shell-container-capabilities`.
6. **Validación servidor:** `CanonicalContainerCapabilitySelectionPreparer` (selection ⊆ scope; known+ready; create ⊆ repertorio; update ⊆ repertorio ∪ ya asignada). Escritura de valor: `AA_Canonical_Amount_Write_Handler` exige fila activa → `CanonicalCapabilityInactive`.
7. **Escritura/lectura:** WriteBag `amount` → normalizer → set/clear effects en TX del registro; enricher + `CanonicalAmountRecordsPageContributor` → presenters/card/form JS (`AA_CANONICAL_SHELL_CAPABILITY_MODULES.amount`).
8. **Desactivar/reactivar:** solo `is_active` en `container_capabilities`; **no** toca `aa_canonical_record_amount`. Reactivar recupera UI y lecturas. Clear de valor es escritura deliberada, no efecto de desactivación.

#### Omitir vs vacío (evidencia; no confundir con reaplicar defaults)

| Caso | Transporte | Create | Update |
|------|------------|--------|--------|
| Ambos campos **ausentes** | `null` | Materializa defaults ready+`is_default` | No toca capacidades |
| `scope=[]` / `selection=[]` **presentes** | instancia | **Sustituye** materializer → 0 caps | No-op de activaciones (mapa vacío) |
| `scope=["amount"]` / `selection=[]` | instancia | amount asignada inactiva | Desactiva amount |
| Lectura setup fallida (edit) | UI `unavailable` | — | **Omite** campos → conserva |

Create con repertorio vacío + mount operativo también envía `[]`/`[]` (bloquea materializer). Eso es fricción UI distinta de “reaplicar defaults”; no es norma deseada para images.

`variant_key` retirado; ausente en este camino. Hardcode a `finance` limitado al **seed** de repertorio de amount, no a la implementación de la capability.

### 10.2 Contraste `images` (tabla)

| Punto | `amount` | `images` hoy | Estado |
|-------|----------|--------------|--------|
| Catálogo | `is_ready=true` | `is_ready=false` | Conectado; producto bloqueado a propósito |
| Repertorio familia | Seed `finance` default on | **Sin seeds**; norma §12.1 = cuatro familias (ver matriz abajo) | Mecanismo común listo; seeds **pendientes** |
| Defaults materializer | Copia ready+default | Nunca emite `images` (not ready + sin filas) | Pendiente tras ready+seeds |
| Activación por lista | UI + filas | Mismo upsert `is_active`; 0 filas producto | Mecanismo OK; UI oculta no-ready |
| Checkbox «Campos y funciones» | «Importe» | Filtrado por `!is_ready` | Pendiente label «Imágenes» + aparición tras ready |
| Validación selección | Preparer común | Mismo preparer (rechaza not-ready en scope) | Conectado |
| Gate escritura | Handler WriteBag | `UploadCanonicalRecordImageUseCase::assert_fresh_capability` (`capability_not_ready` / `capability_inactive`) **solo en `run_fresh`** | Conectado; **post-save attach**, no WriteBag (§12.3) |
| Gate lectura firmada | N/A (valor local) | `GetCanonicalRecordImageReadUrlUseCase` exige ready+active | Conectado |
| Persistencia tipada | `aa_canonical_record_amount` | `aa_canonical_record_images` + ops | Implementado; identidad canónica |
| Contributor página | Offered si lista activa | `CanonicalImagesRecordsPageContributor` → `not_offered` mientras `!is_ready` | Conectado; producto no ofrece |
| Presentación card | Presenter importe | Lista mínima «Imagen #id» + Eliminar (repo directo, bypass contributor) | Delete §12.4; galería **pendiente** |
| Desactivar | Conserva valores | Conserva filas/cuota; rechaza nuevas subidas/firmas | Alineado §5/§12.2; **no** auto-borrado |
| Delete deliberado | N/A | Mandatos IMG-5 **sin** gate `is_ready` | Cumple §12.4; cerrado §9 |

**Matriz normativa `images` (docs/05 §12.1) vs implementado:**

| `family_key` | Norma repertorio | Norma `is_default` | Implementado |
|---|---|---|---|
| `archive` | sí | 1 | **sin fila** |
| `finance` | sí | 0 | **sin fila** |
| `catalog` | sí | 0 | **sin fila** |
| `contact` | sí | 0 | **sin fila** |

**Comprobaciones explícitas del checkbox (qué implica):**

- **Activa:** lista con fila `images` `is_active=1` + catálogo ready → contributor `offered`; registros hijos pueden iniciar **nuevas** admisiones (`run_fresh`) y **nuevas** firmas de lectura; representación ofrecida.
- **Desactiva:** controles/representación dejan de ofrecerse; **nuevas** subidas y **nuevas** firmas → rechazo; filas `aa_canonical_record_images`, objetos Storage y cuota **permanecen**; delete de imagen/registro/lista **sigue** (§12.4).
- **Operación ya admitida:** `run_resume` / `confirm_after_transfer` **no** re-chequean ready/active (alineado §5: admisión ya admitida puede completar). Ocultar el picker **no** basta: el servidor es la guarda.

**Capas de madurez:**

| Pieza | Implementado | Conectado a UI normal | Probado con stubs | Disponible con `is_ready=false` |
|-------|--------------|----------------------|-------------------|----------------------------------|
| Schema images/ops/purge | sí | N/A | sí | sí (infra) |
| Attach AJAX/UC | sí | **no** (sin módulo JS shell) | sí (registry ready in-memory) | producto → 409 `capability_not_ready` |
| Sign-read AJAX/UC | sí | **no** (sin cliente shell) | sí | 409 not_ready |
| Contributor | sí | no ofrece | — | `not_offered` |
| Delete 1 imagen | sí | sí (mínimo) | sí | **sí** (sin gate ready) |
| Galería/picker | no | no | no | no |

**Acoplamientos / divergencias:** ver §10.2 previa: escritura fuera de WriteBag; card delete bypass; Access Policy `manage_options` si `family_key !== 'finance'`; helpers `expediente*` = transporte/utilidades.

**Veredicto:** `images` es **parcialmente enchufable**. Falta cierre de producto (seeds + label + UI attach + presentación + decisión de `is_ready`). No reintroducir `variant_key`.

### 10.3 Reutilización de la galería legacy (mapa; sin implementar)

**Fuente:** `includes/admin/ui/modules/clients/expediente-registros.js` + CSS `admin.source.css` / `admin.css` (`.aa-expediente-adjunto-*`, `.aa-expediente-galeria-*`).

| Concern | Funciones / clases | Reutilizable (A) | Acoplado legacy (B) |
|---------|-------------------|------------------|---------------------|
| Picker + preview local | `openRegistroForm`, `prepareExpedienteImage`, `renderPendingPreview` | Markup/CSS, resize JPEG cliente, object URLs, flowState | Caps `attach`, IDs expediente |
| Post-save attach | `postAttach`, `flowState` saving→uploading | Orden texto→imagen, retry, mensaje parcial | Actions `aa_attach_expediente_*`, tabla `aa_expediente_adjuntos` |
| Progreso | Texto botón «Subiendo imagen...» | Patrón mínimo (sin %) | — |
| Thumb / main / strip / counter / viewer | `buildRecordGallery`, `openAdjuntoViewer`, IntersectionObserver | Presentación visual preferida del propietario | Sign-read variantes legacy, DTO `adjuntos[]` |
| Delete sync legacy | `confirmAndDeleteAdjunto` | Confirm UX | **No copiar:** delete síncrono Expedientes; usar mandatos canónicos |

Init/destroy: `ExpedienteRegistros.init`/`destroy`; observer al abrir `<details>`; cleanup object URLs al cerrar modal.

### 10.4 Ruta histórica mínima (§10; supersedida en detalle por §11)

Orden explorado el 2026-09-15 (aún válido como dirección amplia; el **alcance autorizado pendiente** es §11):

1. Seeds + label «Imágenes».
2. Cablear picker post-save → `aa_attach_canonical_record_image`.
3. Adaptar presentación legacy (galería completa).
4. `is_ready=true`.
5. Validar modularidad.
6. Ops worker antes de producción.

§11 acota la **primera etapa visible** a un subconjunto provisional de (2)+(presentación sencilla), sin galería completa.

### 10.5 Prueba futura de modularidad (propuesta; no ejecutar ahora)

Demostrar sin código por familia:

1. `images` en repertorio de las **cuatro** familias solo con filas + catálogo ready (§12.1).
2. Dos listas de la **misma** familia: una con `images` activa, otra inactiva.
3. Mismo attach / sign-read / presentación / delete sin ramas `if (family_key === …)` en la capability.
4. `amount` e `images` coexistentes en finance.
5. Desactivar → offered/off; datos/cuota intactos; attach/sign nuevos → `capability_inactive`; delete sigue; reactivar → mismas imágenes.

### 10.6 Decisiones de producto

**Ya decididas (no reabrir):** conservación al desactivar; delete deliberado aunque inactive; post-save attach; matriz §12.1 de cuatro familias; no migrar adjuntos legacy; no `variant_key`; worker apagado hasta ops; galería estilo Expedientes como destino (§12.3).

**Pendientes que cambian materialmente la etapa (ver §11.6):** momento del flip `is_ready`; detalle táctico de extracción vs adaptación in-situ del JS/CSS de thumb principal.

---

## 11. Primera etapa visible — hallazgos y propuesta (2026-09-15)

**Naturaleza:** documentación. **Propuesta de implementación NO autorizada** hasta revisión del propietario. No se modificó runtime, schema instalado, catálogo, activaciones, datos, `.env` ni fixtures.

**Clasificación:** capability (`images`) + superficies shell existentes (formulario lista, formulario registro, card).

### 11.1 Objetivo aceptado para planear (criterios de terminado)

1. Crear/editar lista y elegir **Imágenes** según matriz §12.1 y modelo común (sin `if` por familia en frontend).
2. En un **registro** de lista con `images` activa: seleccionar y subir **una** imagen vía flujo canónico (`aa_attach_canonical_record_image`).
3. Ver representación **sencilla** de la **última** imagen confirmada de ese registro.
4. Recargar y seguir viéndola.
5. Otra lista con `images` desactivada respeta la política común.
6. Desactivar/reactivar **sin** borrar datos; eliminación deliberada permanece (§12.4).

**«Última»:** orden estable ya implementado en lectura por lote — `ORDER BY i.record_id ASC, i.id DESC` → primer ítem del array por registro = `id` más alto (`CanonicalRecordImagesRepository::find_public_rows_by_record_ids_for_container`). No usar estado temporal del navegador. No borrar ni reemplazar imágenes anteriores al mostrar solo la última. No imponer “una imagen por registro”.

**Fuera de esta etapa:** galería completa (tira/contador/visor), nuevas funciones de `amount`, agregados de lista, framework general de insertables JS, cambios de permisos ajenos a images, despliegue/activación de workers, Render.

### 11.2 Qué se reutiliza directamente

| Pieza | Evidencia |
|-------|-----------|
| Modelo común de selección lista | `CanonicalContainerCapabilitySelection*` + `canonical-shell-container-form.js` |
| Attach Application/AJAX | `UploadCanonicalRecordImageUseCase`, `CanonicalAttachRecordImageAjax` |
| Lectura por lote + DTO | `CanonicalImagesRecordsPageContributor`, `CanonicalRecordImagePublicDto` |
| Sign-read | `aa_sign_canonical_record_image_read` / `GetCanonicalRecordImageReadUrlUseCase` |
| Delete imagen | `aa_delete_canonical_record_image` + UI mínima card |
| Superficie módulos capability registro | `AA_CANONICAL_SHELL_CAPABILITY_MODULES` + `canonical-shell-record-form.js` (patrón amount) |
| Presentación principal legacy (CSS/patrón thumb) | `.aa-expediente-adjunto-thumb` / main gallery styles — adaptar, no rediseñar |
| Prepare JPEG cliente (patrón) | `prepareExpedienteImage` (canon validator = JPEG) |

### 11.3 Conexiones que faltan

1. Seeds `images` en las cuatro familias + bump `DEFAULTS_VERSION` (insert-if-missing; no UPDATE).
2. Label «Imágenes» junto a «Importe» en boot de opciones (`index.php`).
3. Módulo JS shell `images` (picker post-save → attach; preview/retry) registrado en `AA_CANONICAL_SHELL_CAPABILITY_MODULES`.
4. Presentación sencilla en card/panel cuando contributor `offered` + `known_collection` (sign-read de la primera fila = última por `id`).
5. Flip `is_ready=true` **solo** tras cumplir criterios y con **aprobación explícita** (§11.5).
6. Gates: sin ready, checkbox no aparece, preparer rechaza scope, attach/sign/contributor bloquean producto.

**Persistir matriz sin sobrescribir:** lifecycle `insert_family_capability_if_missing`; seeds nuevos no tocan `is_default` existentes ni `container_capabilities` de listas ya creadas. Create/edit con selección explícita no reaplican defaults.

**Restricción lifecycle:** `run_ensure` **salta** seeds si `!is_ready`. Ops `SetFamilyCapabilityDefault` con `enabled=true` (`is_default=1`) también exige ready. Por tanto `archive`/`images` default on **no** puede aterrizar en BD hasta ready (o un cambio de mecanismo no propuesto aquí). Seeds pueden **declararse** en código antes del flip; la inserción efectiva acompaña al ready + bump de versión.

### 11.4 Cambios mínimos previstos por componente (propuesta)

| Componente | Cambio mínimo |
|------------|---------------|
| `AA_Canonical_Capability_Defaults_Lifecycle` | Declarar 4 seeds `images`; bump `DEFAULTS_VERSION` |
| Registry bootstrap | `images` → `is_ready=true` **solo** en el paso de disponibilidad aprobado |
| `index.php` boot labels | `images` → «Imágenes» |
| Nuevo `canonical-shell-images-field.js` (o nombre acorde) | Picker + prepare + post-save attach + retry; `offered` gate |
| `canonical-shell-record-form.js` | Orquestar módulo images como amount (sin framework nuevo) |
| Card / presenter images | Mostrar última confirmada (thumb/main legacy si viable) + conservar delete |
| Tests | AC seeds/label/selection; JS módulo; attach UI; deactivate; «última» por `id` |

**No tocar:** schema purge/mandatos; Access Policy (salvo encargo); worker; amount; galería completa.

### 11.5 Orden de trabajo, dependencias e `is_ready`

```text
A. Declarar seeds + label + módulo UI + presentación sencilla (código)
   └─ Validar con stubs/dev (registry ready in-memory) como IMG-5 — NO producto
B. Criterios 1–6 en entorno de desarrollo con stub
C. Aprobación del propietario: flip is_ready + bump DEFAULTS_VERSION
D. Validación producto real (checkbox visible, attach, última imagen, deactivate)
E. Galería completa (§12.3 / §10.4 paso 3) = etapa posterior
F. Worker ops = antes de producción (no de esta etapa UI)
```

**No** saltarse `is_ready`. **No** habilitar globalmente una capability incompleta. Mientras `is_ready=false`, la etapa **no** es ofrecible en UI de producto aunque el código esté cableado.

**Cómo validar sin flip:** mismos sustitutos de desarrollo ya usados (registry in-memory ready + stub `is_active=1` + validador `is_readable`); no acredita disponibilidad de producto.

**Decisión de disponibilidad (aprobación posterior):** marcar `images` `is_ready=true` cuando A+B cumplan los seis criterios y el propietario autorice C. Esa decisión **no** se da por hecha en este plan.

### 11.6 Restricciones actuales (no preguntas)

- **Selección de archivo / momento de subida:** norma §12.3 y código: guardar registro primero; **después** adjuntar. Fallo de imagen conserva el registro; reintento solo de imagen.
- **Formato:** attach canónico valida JPEG (`ExpedienteAdjuntoJpegValidator`); el patrón legacy convierte a JPEG en cliente.
- **Omitir vs vacío en lista:** ver §10.1; no reaplicar defaults sobre listas existentes ni sobre selección explícita vacía.
- **Access Policy:** no-finance exige `manage_options` — afecta pruebas en `archive`/`catalog`/`contact`, no es propiedad de `images`.

### 11.7 Preguntas indispensables / contradicciones

1. **Flip `is_ready`:** ¿autorizar C inmediatamente al cerrar A+B de esta etapa, o exigir también un mínimo de presentación legacy (thumb) antes del flip? (La propuesta asume flip tras A+B de §11.1.)
2. **Discrepancia histórica cerrada en docs:** §12.1 previa solo `finance`/`archive`; esta sesión fija las **cuatro** familias. Código aún sin seeds — no hay conflicto de datos, solo de norma anterior.
3. **No es pregunta:** conservación al desactivar; post-save; delete con inactive; no galería completa en esta etapa; no worker.

### 11.8 Cómo se probará cada resultado visible (cuando se autorice código)

| Criterio | Prueba |
|----------|--------|
| 1 Checkbox/matriz | Crear lista archive → Imágenes marcada; finance/catalog/contact → desmarcada; edit muestra persistido; sin retroactivo en listas viejas |
| 2 Subida | Registro en lista activa → picker → save → attach → confirmada en BD |
| 3–4 Presentación | Card muestra última por `id`; reload SSR/sign-read la conserva |
| 5 Lista inactiva | Sin picker/offered; attach/sign → `capability_inactive` |
| 6 Deactivate/reactivate | Filas/cuota intactas; UI off/on; delete imagen sigue |

**Desglose de implementación:** el criterio 1 se planifica solo en **§12 (Paso 1)**; criterios 2–6 quedan para pasos posteriores de la etapa visible. §12 no autoriza código.

---

## 12. Paso 1 — activación por lista: propuesta pendiente de autorización

**Naturaleza:** solo lectura y documentación (2026-09-15). **No autoriza implementación.** No se tocó runtime, schema, catálogo, activaciones, datos, `.env` ni fixtures.

**HEADs comprobados:** plugin `33e4bd886e2e29d2538e1be4193e22a69a628214` (`dev/canonical-images-retire`); backend `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` (`dev/backend-recovered`). Worktrees: un worktree por repo. Locales ajenos intactos (plugin `__pycache__`; backend `scripts/runner-from-pack.sh`).

**Alcance de este paso:** únicamente elegir **Imágenes** al crear/editar lista según matriz §12.1 y el mecanismo común de amount. **Fuera:** picker, subida, presentación, galería, cambios de eliminación, flip `is_ready`.

Norma vinculante: `docs/05-canonical-capabilities.md` §§2–5 y §12.1. Semántica scope/selection **no se rediseña**.

### 12.A Mecanismo común heredado (evidencia)

Cadena amount (idéntica para cualquier clave ready en repertorio):

| Eslabón | Archivo / símbolo |
|---------|-------------------|
| Catálogo | `AA_Canonical_Capability_Registry_Bootstrap` (`amount` ready; `images` not-ready) |
| Seeds / versión | `AA_Canonical_Capability_Defaults_Lifecycle` (`DEFAULTS_VERSION=2`; `declared_seeds()`; insert-if-missing; **omite** `!is_ready`) |
| Opciones modal | `canonical_shell/index.php` ~1047–1067: repertorio ∩ `is_ready`; label `amount`→«Importe», else clave cruda |
| Checkbox UI | `canonical-shell-container-form.js` `renderCapabilityCheckboxes` / `applyCreateCapabilities` / `applyUpdateCapabilities` / `collectCapabilitySelectionFields` |
| Parse wire | `CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source` |
| Create/Update UC | `WriteCanonicalShellContainerUseCase::resolve_create_effects` / `resolve_update_effects` |
| Validación | `CanonicalContainerCapabilitySelectionPreparer` (`assert_known_ready`; create ⊆ repertorio; update ⊆ repertorio ∪ asignada; selection ⊆ scope) |
| Persistencia | `AA_Canonical_Apply_Container_Capability_Selection_Effect` → `upsert_container_capability(..., is_active)` |
| Edit boot | `ReadContainerCapabilityConfigUseCase` → `edit_container_capabilities_boot` (`active`/`assigned` o `unavailable`) |

**No hace falta módulo JS de `images` para el checkbox de lista:** el montaje común ya basta cuando la clave aparece en `familyCapabilityOptions`.

#### Payloads reales (ejemplos)

Campos POST: `capability_selection_scope`, `capability_selection` (JSON listas).

**a) Crear “usando defaults” vía modal operativo (camino habitual):** mount operativo → siempre envía ambos campos. Create con `checked = opt.is_default`.

- Archive con repertorio ready `{images: is_default true}` y checkbox intacto:
  - `capability_selection_scope=["images"]`
  - `capability_selection=["images"]`
  → fila `container_capabilities` images `is_active=1`.

- Finance con `{amount: true, images: false}` y checkboxes intactos:
  - `scope=["amount","images"]`, `selection=["amount"]`
  → amount activa; images **asignada inactiva** (`is_active=0`).

**b) Crear con selección explícita distinta de defaults:** mismo wire; p. ej. finance marca Imágenes:
  - `scope=["amount","images"]`, `selection=["amount","images"]`.

**c) Editar y desmarcar:** edit pinta `checked` desde `active` persistido ∩ opciones. Desmarcar images y guardar:
  - `scope=["amount","images"]`, `selection=["amount"]` (si amount sigue marcada)
  → upsert images `is_active=0`; **no** borra `aa_canonical_record_amount` ni imágenes (capa datos intacta).

**d) Editar sin cambiar capabilities:** UI `unavailable` → `capabilitiesOperative=false` → **omite** ambos campos → `resolve_update_effects` [] → setup intacto. Si el mount está ok y el usuario no cambia checks, igual envía el snapshot actual de checkboxes (explícito no-op efectivo si coincide con persistido).

**e) Omitir vs arrays vacíos:**

| Wire | Create | Update |
|------|--------|--------|
| Ambos ausentes | Materializer (ready+`is_default`) | No toca caps |
| `scope=[]` `selection=[]` | 0 filas de caps (sustituye materializer) | No-op de activaciones |
| Exactamente uno presente | `invalid_payload` | igual |

Tests de evidencia: `test-canonical-list-capability-selection-ac.php`; JS `canonical-shell-container-form.test.js`.

### 12.B Delta mínimo de `images`

| Cambio | Archivo | Responsabilidad | Por qué falta hoy | Reutiliza |
|--------|---------|-----------------|-------------------|-----------|
| 4 seeds `images` | `class-aa-canonical-capability-defaults-lifecycle.php` `declared_seeds()` | Repertorio + defaults matriz | Solo seed amount | Lifecycle insert-if-missing |
| Bump `DEFAULTS_VERSION` | mismo (p. ej. 2→3) | Re-ejecutar ensure | Versión ya en 2 | `should_skip` / `run_ensure` |
| Label «Imágenes» | `canonical_shell/index.php` boot options | Copy del checkbox | Hoy fallback = clave cruda; solo `amount` tiene label | Mismo mapa de opciones; **sin** JS nuevo |

**No proponer:** endpoints, tablas, handlers, políticas, ni `canonical-shell-images-field.js` en este paso.

**Orden con `is_ready` (obligatorio):** ver §12.D — **no** bump de versión mientras `images` siga not-ready si eso deja `OPTION_VERSION` adelantado sin insertar seeds.

### 12.C Seeds, versiones y listas existentes

Matriz a declarar (norma §12.1):

| family_key | is_default |
|---|---|
| `archive` | true |
| `finance` | false |
| `catalog` | false |
| `contact` | false |

- Insert-if-missing: **no** sobrescribe `is_default` ya guardado ni toca `container_capabilities`.
- Listas existentes: **sin** cambio retroactivo.
- Filas de repertorio ya existentes (hoy: ninguna de images): si en el futuro hubiera una, el ensure no la pisa.
- Modal archive marcada: con options ready + `is_default=true`, create envía `images` en selection (§12.A.a). No depende del camino omit→materializer.

### 12.D Dependencia de `is_ready` y límite de validación

Hoy: `images` `is_ready=false` → UI filtra la clave; preparer rechaza scope; lifecycle salta seeds; attach/sign/contributor bloquean producto.

| Parte del Paso 1 | Con `is_ready=false` | Con ready (fin de etapa completa; **otra** autorización) |
|------------------|----------------------|----------------------------------------------------------|
| Declarar seeds en código | Posible; ensure **no** inserta | Inserta tras bump |
| Label en `index.php` | Código inerte (clave filtrada) | Visible |
| Checkbox en UI normal | **No aparece** | Visible |
| AC Application con registry stub ready | Sí (aislado; como amount) | — |
| Validación visual de producto | **No** — no llamarla así | Tras flip aprobado |

**Bump `DEFAULTS_VERSION`:** acoplarlo al **mismo** cambio (o posterior inmediato) que ponga `images` `is_ready=true`. Si se bumpea ahora con not-ready, ensure corre, salta images, marca versión alta → al flip posterior **sin** nuevo bump los seeds **nunca** se insertan.

**Conclusión expresa:** el Paso 1, implementado solo, queda **preparado y comprobable de forma aislada** (stubs/AC). **No** es validación visual de producto ni cierra el criterio 1 de §11 en la interfaz normal hasta el flip de disponibilidad al final de la etapa completa.

**No** eludir el filtro ready; **no** flags alternativos; **no** habilitar `images` en este paso.

### 12.E Operaciones admitidas vs desactivar (inspección; no cambiar en Paso 1)

Canon §5 / §12.2: desactivar rechaza **nuevas** escrituras; una operación **ya admitida** puede completarse según contrato de `images`.

| Concepto | Evidencia |
|----------|-----------|
| Admisión | Fila ops `status=admitted` con `upload_intent` + `upload_objects_json` + `backend_intent_exp_ms` vigentes |
| `run_fresh` | Único camino que llama `assert_fresh_capability` (ready + `is_active`) → authorize → insert admitted → PUT → confirm |
| `run_resume` | **Sin** re-chequeo ready/active; exige misma identidad (sha/mime/size); `authorize_canonical_upload` con `prior_upload_intent` (revalida intent/exp remotos); PUT usa **URLs firmadas ya persistidas** en el manifiesto local (no sustituye por URLs nuevas del authorize para `pending_upload`); luego `confirm_after_transfer` |
| `confirm_after_transfer` | TX local insert image + delete ops + touch; sin gate de activación |
| Desactivar | Solo `is_active` en config; no abre purge |
| Purga/delete | Mandatos IMG-5; independiente de activación |

**Pendiente a resolver antes del paso de subida (no del checkbox):** documentar/aceptar explícitamente que resume puede re-llamar authorize remoto y completar PUT tras desactivar, siempre que la admisión siga vigente — alineado a §5, pero conviene fijarlo en el plan de subida (¿timeout, cleanup al desactivar, etc.?). **No** mezclar con persistencia del checkbox. **No** cambiar comportamiento en Paso 1.

### 12.F Pruebas previstas y cobertura

| Caso | Cobertura hoy | Faltaría al implementar Paso 1 |
|------|---------------|--------------------------------|
| Matriz 4 familias + defaults | amount solo finance | AC seeds images; create stub por familia |
| Create selección explícita modal | JS container-form + AC selection amount | Extender fixtures options con `images` + label |
| Edit refleja/conserva | AC update omit/deactivate amount | Mismos asserts con clave `images` |
| Desmarcar solo esa lista | UNIQUE container+key (amount) | Idem images; segunda lista intacta |
| Reactivar | AC amount | Idem images (config only) |
| No altera amount | implícito en effect por scope | Assert amount intacto al tocar solo images |
| Seeds idempotentes / no overwrite | lifecycle amount | Assert insert-if-missing images; no UPDATE |
| not-ready no aparece / no bypass | config AC «sin seed images»; UI filtra ready | Mantener assert producto not-ready; stub aparte |
| Desactivar no borra datos | amount value intact; images filas (norma) | AC config-only; **no** ejecutar eliminaciones |

Criterio de cierre del Paso 1 (cuando se autorice código): AC aislados PASS + declaración seeds/label según §12.B–D; **sin** exigir checkbox visible en UI normal mientras `is_ready=false`.

### 12.G Archivos que cambiarían / no tocar

**Cambiarían (propuesta):**

- `includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php`
- `includes/admin/ui/modules/canonical_shell/index.php` (label)
- Tests AC/JS de selección/seeds que hoy niegan images
- Docs de estado al cerrar el incremento

**No tocar en Paso 1:** registry `is_ready`; Upload/Sign-read/Delete UCs; `canonical-shell-record-form.js`; amount handler; schema/purge; backend; Access Policy; galería legacy; workers.

### 12.H Preguntas pendientes

Ninguna para el checkbox/persistencia: el canon y amount ya fijan la semántica.

### 12.I Estado de implementación (Paso 1 — 2026-09-15)

**Cerrado como preparación declarativa** (no producto visible):

| Ítem | Estado |
|------|--------|
| Seeds `declared_seeds()` 4 familias | **Implementado** (`archive` default on; `finance`/`catalog`/`contact` off) |
| Label «Imágenes» en boot shell | **Implementado** (`index.php`) |
| `images.is_ready` | **false** (sin cambio) |
| `DEFAULTS_VERSION` | **2** (sin bump; futuro flip ready + bump juntos) |
| `DB_VERSION` | **31** (sin cambio) |
| Checkbox en UI policyytest | **No visible** (filtro ready) |
| Validación | AC/JS **aislados** con registry ready stub; **no** visual de producto |

**Regla aprobada (subidas ya iniciadas):** desactivar bloquea **nuevas** admisiones (`run_fresh` / `assert_fresh_capability`). Una operación **previamente admitida** puede completar `run_resume` y `confirm` hasta resultado terminal. Purge/delete siguen independientes y prevalecen. Cubierto con aserción estática en `test-upload-canonical-record-image-permissions-states-ac.php` (sin cambio de runtime).

**Futuro (otra autorización):** `is_ready=true` + bump `DEFAULTS_VERSION` + ensure inserta matriz + validación UI integrada (junto con subida/presentación mínima — ver §13).

---

## 13. Paso 2 — picker, subida post-save y presentación mínima: **implementado**

**Naturaleza:** implementación UI shell (2026-09-15). Sin flip `is_ready`; sin bump `DEFAULTS_VERSION`/`DB_VERSION`; sin schema/backend/worker/Storage.

**HEADs base previos:** plugin `42046340ab0aa073b0282b4c2cd1059cff0c2f7b`; backend `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` (consulta).

**Cerrado:** lista con Imágenes activa (+ ready en stubs) → picker → guardar registro → attach canónico post-save → redirect/recarga SSR → miniatura `summary` de la **última** confirmada (`known_collection[0]` = `id DESC`); cancelar/fallo conservan registro; retry reutiliza `upload_operation_id`; inactive no ofrece picker/thumb de producto. **Paso 5:** flip ready + `DEFAULTS_VERSION=3` (ver §14). **Pendiente:** galería/`display`/visor/delete UI final.

Norma: `docs/05-canonical-capabilities.md` §§5, §12.3 (post-save; destino galería = etapa posterior; este paso = subconjunto provisional de presentación).

### 13.I Cierre de implementación

| Pieza | Estado |
|-------|--------|
| `canonical-shell-images-field.js` | **Implementado** — picker, prepare JPEG, pending, retry |
| Hook post-save en `canonical-shell-record-form.js` | **Implementado** — attach tras `confirmed`+`resource_id` antes de redirect |
| Markup + boot `attachImage*` en `index.php` | **Implementado** |
| `AA_Canonical_Images_Shell_Presenter` + thumb card | **Implementado** — solo `summary`; firma fallida = omitir (discreto) |
| Redirect/recarga SSR | **Estrategia de esta etapa** |
| Galería / display / visor / delete UI final | **Pendiente** |
| `images.is_ready` | **true** (Paso 5) |
| `DEFAULTS_VERSION` / `DB_VERSION` | **3** / **31** |
| Validación | JS + AC aislados; **no** UI de producto mientras not-ready |

**Flip (Paso 5 / §14):** `is_ready=true` + `DEFAULTS_VERSION=3` implementados juntos.

### 13.1 Mapa: qué ya existe (servidor)

| Eslabón | Evidencia | Estado |
|---------|-----------|--------|
| Validación JPEG | `ExpedienteAdjuntoJpegValidator` — máx 1 048 576 B, 2048 px, solo `image/jpeg` | Listo |
| `upload_operation_id` | UUID v4 cliente; UC lo exige | Listo (falta generarlo en UI shell) |
| Fresh admit | `run_fresh` → `assert_fresh_capability` → variantes → authorize `canonical_v1` → `insert_admitted` → PUT create-only (`x-upsert:false`) → finalize → confirm TX | Listo |
| Resume | Misma identidad; **sin** re-check ready/active; URLs del manifiesto local; authorize con `prior_upload_intent` | Listo (§12.E) |
| Confirm | `AA_Canonical_Record_Image_Confirmation_Store` → INSERT `aa_canonical_record_images` + DELETE ops + touch | Listo |
| Cuota | `AA_Installation_Storage_Usage` confirmed+reserved; códigos `storage_quota_exceeded` / `storage_not_included` | Listo |
| AJAX attach | `aa_attach_canonical_record_image` — POST `family_key`, `container_id`, `record_id`, `upload_operation_id`, `file` → DTO público | Listo; **cliente shell Paso 2** |
| Contributor | `CanonicalImagesRecordsPageContributor` → `known_collection` / `known_absent` / `read_failed`; offered solo ready+active | Listo; producto `not_offered` mientras `!is_ready` |
| «Última» | `ORDER BY i.id DESC` en batch repo — primer ítem = más reciente | Listo |
| Sign-read | `aa_sign_canonical_record_image_read` — variants `summary`\|`gallery`\|`display` | Listo; **SSR card usa `summary`** |
| Delete 1 imagen | Mandatos + UI mínima card «Imagen #id» | Listo (fuera del delta visual de Paso 2) |
| Purge blocking | `has_blocking_purge` en fresh y TX confirm | Listo |

**Errores (resumen):** retry mismo op → resume si admitted vigente; `uncertain` no retry ciego; `capability_inactive|not_ready`, `purge_in_progress`, conflictos de identidad, cuota = terminales o mensaje comercial según código.

**UI/orquestación de registro (Paso 2):** implementada (§13.I). Galería/`display`/visor siguen fuera.

### 13.2 Delta mínimo de plugin/UI

| Pieza | Cambio | Por qué no cubierto |
|-------|--------|---------------------|
| `capabilities/canonical-shell-images-field.js` (nuevo) | Picker + prepare JPEG (patrón legacy) + preview + pending `{blob, upload_operation_id}` + retry; `AA_CANONICAL_SHELL_CAPABILITY_MODULES.images` | Solo existe módulo `amount` |
| Extensión contrato módulo + `canonical-shell-record-form.js` | Tras create/update `confirmed` + `resource_id`, si hay pending → attach **antes** de `location.assign`; si falla → registro conservado + UI partial/retry | Hoy redirect inmediato (líneas ~473–476 / ~691–694); `collect` amount-style **no** sirve para imagen |
| `index.php` | Markup sibling en `#aa-shell-record-capability-fields` gated por `images.offered`; enqueue JS; cfg `attachAction`/`attachNonce`/`signReadAction`/`signReadNonce` | Solo bloque amount hoy |
| Card / presenter mínimo | Si contributor `offered` + `known_collection`: mostrar **primera** fila (`id` DESC) vía sign-read `summary` o `display`; sin colección = vacío; conservar Eliminar existente | Hoy solo texto «Imagen #id» vía repo bypass |
| CSS | Reutilizar `.aa-expediente-adjunto-*` / thumb (y opcional main) **sin** strip/contador/visor | Evitar rediseño |

**No proponer:** endpoints/tablas nuevas; WriteBag para imagen; galería completa; cambiar UC attach/sign/delete.

### 13.3 Reutilización legacy y límites

| Reutilizar (patrón/CSS) | No reutilizar |
|-------------------------|---------------|
| `prepareExpedienteImage` (límites alineados al validator JPEG) | Actions `aa_attach_expediente_*`, DTO `adjuntos[]`, tabla `aa_expediente_adjuntos` |
| Preview object URL + remove pending | `buildRecordGallery` completa, strip, counter, viewer |
| `flowState` saving→uploading→partial + retry mismo `upload_operation_id` | Delete sync Expedientes |
| Thumb `.aa-expediente-adjunto-thumb` (+ main CSS si aporta) | Ports legacy como dependencia runtime del shell |
| `generateUploadOperationId` (crypto.randomUUID) | Optimistic `applyAdjuntoToRecord` sin reload (shell usa redirect SSR) |

Corte mínimo: **copiar/adaptar patrones** al módulo shell; **no** montar `ExpedienteRegistros` ni extraer framework genérico en este paso.

### 13.4 Flujo único propuesto

```text
1. Lista con images activa (+ ready en producto futuro) → form registro ofrece picker (offered)
2. Usuario elige archivo → prepare cliente → pending local (sin POST imagen aún)
3. Submit create/update: title/details [+ amount collect]; images NO en WriteBag
4. Servidor confirmed + resource_id (= record_id)
5. Si hay pending:
   a. POST aa_attach_canonical_record_image(container, record_id, file, upload_operation_id)
   b. OK → redirect records (SSR: última en card)
   c. Fallo recuperable → UI «registro guardado; imagen falló» + Reintentar (mismo op / resume)
   d. Cancelar picker o sin archivo → solo redirect; registro OK sin imagen
6. Card: última = known_collection[0]; sign-read; reload relee batch
7. Lista inactiva / not-ready: sin picker; fresh → capability_inactive|not_ready
```

**Cancelar / fallo / red:** el registro ya persistido **se conserva** (§12.3). No re-crear el registro en retry. Abandonar pending al cerrar modal: revocar object URL; no llamar attach.

**Update** con registro ya existente: mismo post-save si hay pending; `record_id` = id actual.

### 13.5 Contrato mínimo de presentación

| Concern | Propuesta |
|---------|-----------|
| Dato contributor | `offered` + por registro `known_collection` (lista DTO `{id,width,height,byte_size,created_at}` orden id DESC) / `known_absent` / `read_failed` |
| URL | Solo `aa_sign_canonical_record_image_read` con `image_id` + variant (`summary` para thumb card); **no** filtrar URLs legacy |
| Sin imagen | Sin nodo visual (como amount absent) |
| «Última» | `collection[0]` del contributor (= `id` máximo); **no** orden del navegador |
| Firma falla/caduca | Estado error local en thumb; un reintento sign-read; no borrar fila |
| Inactive / not-ready | Contributor `not_offered` → sin picker ni thumb de producto; **datos intactos**; delete mínimo puede seguir (§12.4) vía camino ya cableado |
| No es galería | Una sola representación; sin tira/contador/visor; copy/docs lo dejan explícito |

### 13.6 Ready y habilitación

| Fase | Qué |
|------|-----|
| Desarrollo Paso 2 | Código UI + tests con registry stub ready / `is_active=1` (como IMG-5 / Paso 1) |
| Producto visible | Requiere autorización conjunta: `is_ready=true` + bump `DEFAULTS_VERSION` + ensure seeds + UI completa de este paso |
| Mientras `is_ready=false` | Checkbox lista y picker registro **no** aparecen en UI normal; attach producto → 409 `capability_not_ready` |

**Conclusión honesta:** Paso 2 puede desarrollarse y probarse aislado con stubs; **aceptación de producto** exige incremento de readiness (o cierre conjunto al final de la etapa visible). **No** bypass para policyytest.

### 13.7 Archivos que cambiarían / no tocar

**Cambiarían (propuesta):** `canonical-shell-images-field.js` (nuevo); `canonical-shell-record-form.js`; `index.php` (markup+boot+enqueue); `record-card.php` y/o presenter images mínimo; CSS admin (clases adjunto/thumb); tests JS/AC; docs al cerrar.

**No tocar:** Upload/Sign/Delete UCs; schema/purge; worker; backend; amount WriteHandler; Access Policy; `expediente-registros.js` como dependencia; flip ready / `DEFAULTS_VERSION` / `DB_VERSION` en este incremento de UI (salvo encargo explícito de cierre).

### 13.8 Matriz de pruebas (futuro; no ejecutar ahora)

| Caso | Tipo |
|------|------|
| Lista activa vs inactiva (picker/offered; fresh inactive) | AC + JS stub |
| Guardar registro + selección + attach OK (double authorize/PUT/confirm) | AC/JS doubles |
| Archivo inválido (validator) | AC/JS |
| Retry resume mismo `upload_operation_id` | AC/JS |
| Fallo attach conserva registro (no re-create) | JS orquestación |
| Última tras reload / nueva instancia SSR | AC contributor + manual futura |
| 2ª imagen: collection[0] nueva; filas previas intactas | AC |
| Desactivar bloquea fresh; admitida previa resume OK | AC estático + stub (ya parcial §12) |
| Purge blocking intacto | Suites IMG-5 existentes |
| Amount + delete imagen no regresionan | Suites amount + delete image |

Manual integrada (post-ready): policyytest UI create→attach→thumb→reload→lista inactiva.

### 13.9 Riesgos / preguntas de producto

1. **¿Redirect inmediato tras attach OK vs permanecer en la vista con thumb optimista?** El shell hoy siempre redirige; la propuesta **conserva redirect SSR** (menos superficie). Solo pregunta si se prefiere UX tipo Expedientes sin reload.
2. **¿Variant de card: `summary` vs `display`?** Técnica; default propuesto `summary` (thumb). No bloquea el modelo.
3. **¿Mostrar Eliminar junto al thumb mínimo en el mismo incremento?** Fuera del objetivo declarado («No eliminar desde esa presentación»); el delete mínimo actual por «Imagen #id» puede permanecer hasta unificar. Confirmar si al cablear thumb se oculta la lista textual o coexisten temporalmente.

**No son preguntas:** post-save; JPEG; conservación al fallar; última por `id`; no galería; no flip ready en este paso; resume tras desactivar (ya cerrado).

---

## 14. Paso 5 — flip de readiness + validación integrada: **implementado**

**Naturaleza:** `images.is_ready=true` + `DEFAULTS_VERSION=3` en el mismo commit. Matriz seeds sin cambio (`archive` on; `finance`/`catalog`/`contact` off). Sin `DB_VERSION`/schema/backend/worker. Ensure insert-if-missing materializa repertorio; no reescribe defaults ni `container_capabilities` existentes.

**Validación:** AC Paso 5 (lifecycle/gates/orquestación doubles) + suites actualizadas post-flip. **Sin** Storage/HTTP/Node real. Galería/`display`/visor quedan fuera.

## Fuera de alcance restante (post-eliminaciones + §§10–13)

- Observaciones residuales §9.2.
- Activar worker / cron / `is_ready` / Render / bump `DEFAULTS_VERSION` sin encargo.
- Galería/`display`/visor / delete UI final sin autorización.
- Galería completa; framework insertables; permisos ajenos.
- Reabrir eliminaciones / fixtures miniaturas.
- Cambiar runtime resume/confirm (solo documentado).

**Backend Storage fixture (no reejecutada):** immediate / later / reappear **PASS**. Worker apagado.
