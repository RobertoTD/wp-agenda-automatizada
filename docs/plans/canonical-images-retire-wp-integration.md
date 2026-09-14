# Exploración: retiro canónico WP, mandatos de limpieza y cuota

**Estado:** incrementos 1–4 implementados en `dev/canonical-images-retire`. Incremento 3 cerrado en validación policyytest (A/D/B + UI vacío/Cancelar; Continuar no PASS; C integrado pendiente). **Incremento 4 (retiro de lista) — código y AC aislados; prueba manual no ejecutada.** Todavía sin cron/workers ni `is_ready`. Retiro de **una imagen** conservando el registro: pendiente como incremento aparte (§8).
**Fecha:** 2026-09-14.
**Ámbito:** integración WordPress del retiro de registros/adjuntos canónicos y liberación de cuota, reutilizando `accept` / `seal` / `status` del backend.

No modifica el state de la prueba Backend 3 (worker local Storage, `docs/ops/attachment-delete-worker-local-storage-state.json`).

## Referencias actuales

| Repo | Rama | HEAD |
|------|------|------|
| `wp-agenda-automatizada` | `dev/canonical-images-retire` | `17cf69e13257bcb4a90e57c8c1446b47fe294f9e` (inc. 4) |
| `deoia-oauth-backend` (solo contratos) | `dev/backend-recovered` | `26452b3ba4ceb24b7bf46271fd30a0c58318ceed` (parche `authorize-upload`; untracked ajeno: `scripts/runner-from-pack.sh`) |

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

`aa_delete_canonical_container` orquesta mandatos `scope=container` (`RetireCanonicalContainerUseCase`). Seal obligatorio (incl. `expected_batch_count=0`). Chunks locales post-sello. Detalle: **§7**.

### Incremento 5 — retiro de **una imagen** (registro vivo) — **pendiente, fuera del 4**

Norma §12.4: «Eliminar una imagen elimina sus objetos asociados.» No hay AJAX/UseCase canónico de detach. No se inventa exclusión: queda **§8** como incremento acotado aparte. No es parte del entregable del 4.

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

**No cerrado tras el 3:** retiro de una imagen (§8); Continuar UI; C integrado; `is_ready`; cron/worker. El incremento 4 está en código (§7); su prueba manual en policyytest **no** se ejecutó en el turno de implementación.

---

## 7. Incremento 4 — retiro de una lista completa — **completado** (`DB_VERSION=30`)

**Estado:** implementado en código + AC MySQL aislado / Ajax / JS. **Prueba manual no ejecutada.** No se migró policyytest. No se tocó backend ni Storage.

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

`DB_VERSION=30`: `aa_canonical_purge_runs.local_retire_after_inventory_id` y `local_retire_after_record_id` (`bigint unsigned NOT NULL DEFAULT 0`). `ensure_purge_container_local_retire_v30()` es idempotente. No se migró policyytest en este turno.

### 7.6 Pruebas automatizadas (este turno)

MySQL prefijo temporal + HMAC simulado; lock con **segunda conexión** real. Cubierto: lista vacía seal 0; registros sin imágenes; images+ops no confirmadas; 51 identidades (tandas 0/1); accept/seal/status perdidos; overlap ambos órdenes; writers bloqueados; chunks + cuota SUM; commit ambiguo; reintento con contenedor ya ausente; corrida/inventario conservados; `resource_busy`; acceso indebido; fail-closed fila extra; Ajax estados; JS Continuar/Cancelar/Cerrar.

### 7.7 Límites (no declarar de más)

- Prueba **manual** de lista **no ejecutada**.
- Continuar UI del incremento 3 y caso C integrado: **siguen sin PASS** (no se reabrieron).
- No hay garantía de que un `UPDATE` de pertenencia hecho a mano conserve imágenes del registro movido (las identidades inventariadas se retiran).
- Worker periódico apagado: residuos Storage esperables.
- `Write::delete` no es el camino productivo; sigue fallando por RESTRICT si alguien lo llama con images/ops.

### 7.8 Procedimiento manual propuesto — **LISTA SINTÉTICA NUEVA** (no ejecutar aún)

No usar la lista **17** ni los registros 32/33/34. Worker periódico **apagado**. Sin `is_ready=true`. Sin `npm start` extra ni Storage real. Sin migrar policyytest salvo lo que el propietario haga luego.

1. En finance, **crear una lista nueva** (título inequívoco, p. ej. `INC4-empty`).
2. **Vacía:** Eliminar lista → confirmar. Esperado: `confirmed`, redirect a listas, corrida `completed`, `prepared_batch_count=0`, `seal_intent_at` y `sealed_at` no NULL, inventario 0, contenedor ausente. Si el modal pide Continuar, pulsar hasta `confirmed`. **No** Cancelar tras el primer intento si ya hubo red.
3. **Otra lista nueva** (`INC4-records`) con 1–2 registros sin imagen. Eliminar lista. Esperado: igual, seal 0, registros y contenedor ausentes.
4. **Otra lista nueva** (`INC4-image`) con un registro y **una** imagen por el camino de desarrollo acotado (mismo que B del 3). Eliminar lista. Continuar si `incomplete`. Esperado: contenedor/registro/imagen/op locales ausentes; corrida+inventario conservados; cuota SUM baja. **No** tocar Backend 3 ni worker.
5. Conservar evidencia SQL (ids de lista/corrida/mandato). No borrar la 17.

### 7.9 Bloqueos

Ninguno de contrato backend para el 4. Fixture lista 17 / registro 33 se conservan. Incremento 5 = una imagen con registro vivo (§8).

---

## 8. Retiro de una imagen (registro vivo)

**Norma:** `docs/05-canonical-capabilities.md` §12.4 — «Eliminar una imagen elimina sus objetos asociados.»

**Código canónico:** no existe AJAX ni UseCase de detach (`aa_attach` / `aa_sign` solamente). Legacy Expedientes (`DeleteExpedienteAdjunto*`) es otro mundo (Storage-first síncrono); no es el camino canónico.

**Conclusión:** pendiente **dentro** del alcance normativo de eliminaciones, **fuera** del primer entregable del incremento 4. Ubicar como **incremento 5** (o etiqueta IMG-5b): un mandato/corrida de alcance imagen u operación puntual, reutilizando accept/seal si el contrato lo permite, o el mínimo protocolo durable acordado — **sin implementarlo ahora**. No se declara exclusión inventada.

---

## 9. Pendientes para cerrar la etapa de eliminaciones

| Capa | Pendiente |
|------|-----------|
| **Implementación** | Inc. 5 una imagen (§8) |
| **Validación** | Continuar UI (sin evidencia); C integrado; **manual del 4** (lista sintética nueva, §7.8) |
| **Activación** | `is_ready` / seeds / UI galería — **fuera** hasta cerrar recorridos de borrado acordados |
| **Ops** | Worker periódico Storage; no WP ledger físico |

---

## Fuera de alcance restante

- Implementar incremento 5 (una imagen, §8).
- Prueba manual del 4; caso C integrado; Continuar UI sin evidencia.
- Activar polling del worker, cron o `is_ready`.
- Cambiar TTL, tombstones o identidades Storage Backend 3.
- Expedientes Ciclo B; reabrir subida.

**Backend Storage fixture (no reejecutada):** immediate / later / reappear **PASS**; reappear fue sintético. Worker periódico apagado.
