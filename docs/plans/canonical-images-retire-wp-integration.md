# Exploración: retiro canónico WP, mandatos de limpieza y cuota

**Estado:** incremento 1 (cliente HMAC accept/seal/status) implementado en `dev/canonical-images-retire`. Delete de shell, schema WP, cuota, UI e `is_ready` siguen sin cambios.
**Fecha:** 2026-09-14.
**Ámbito:** integración WordPress del retiro de registros/adjuntos canónicos y liberación de cuota, reutilizando `accept` / `seal` / `status` del backend.

No modifica el state de la prueba Backend 3 (worker local Storage, `docs/ops/attachment-delete-worker-local-storage-state.json`).

## Ramas inspectadas (sin cambios)

| Repo | Rama | HEAD |
|------|------|------|
| `wp-agenda-automatizada` | `main` | `41c5609399ae200ea48734856189c5a9b28580af` (ahead 85 de `origin/main`; untracked: `.cursor/hooks/__pycache__/`) |
| `deoia-oauth-backend` | `dev/backend-recovered` | `71b7129b1071bb1b7a734d226837babdf5ef337e` (untracked: `scripts/runner-from-pack.sh`) |

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
5. `aa_canonical_purge_runs` existe (`DB_VERSION` 26+). Application solo consulta `has_blocking_purge` (bloquea attach). No hay runner, no persiste `mandate_id`.
6. Cuota: `AA_Installation_Storage_Usage::{confirmed_bytes, reserved_bytes, admission_used_bytes}`. Confirmados = `SUM(aa_expediente_adjuntos.byte_size)` + `SUM(aa_canonical_record_images.byte_size)`. Reservados = ops `admitted` vigentes. **No hay RPC de «liberar cuota»:** desaparece la fila confirmada (o la reserva) y el SUM baja.
7. Lectura de imágenes para UI (`find_public_rows_by_record_ids_for_container`) **no** incluye `upload_operation_id`, `content_sha256` ni `storage_path`. Accept necesita esos campos.
8. Backend: max 50 ítems/tanda; fingerprint por tanda; replay idéntico → `already_accepted`; misma identidad+metadatos otro mandato → `already_obligated` persistido; `status` recupera respuesta perdida; seal vacío (`expected_batch_count=0`) acredita inventario **entregado vacío** (WP no debe sellar vacío si el alcance local tenía imágenes); tombstone + advisory lock de identidad serializa accept vs `begin_canonical_upload_issuance`.
9. UI canónica de delete: éxito `confirmed` + redirect; `uncertain` 409 bloquea retry («Recargar lista»). No existe aún el estado «Eliminación incompleta».
10. `images` permanece `is_ready=false`. IMG-4 es el último incremento de imágenes cerrado. Borrado/purge está explícitamente excluido.

### Decisiones pendientes (no congelar en código)

- ¿Reutilizar `aa_canonical_purge_runs` (añadir `mandate_id` / `batch_seq`) o tabla de intención aparte? Recomendación abajo: **extender purge_runs**.
- ¿Un mandato por registro vs un mandato por contenedor (y registros hijos como tandas del mismo mandato)? Recomendación: **un mandato por corrida de alcance** (record XOR container).
- ¿Ops `admitted`/`cleanup_needed` entran en el mismo mandato que imágenes confirmadas? Recomendación: **sí** (inventario del plan = imágenes ∪ ops).
- ¿Cron WP para Continuar si el usuario cierra el navegador, o solo persistencia + botón? Recomendación incremento 1: **persistir + Continuar**; cron opcional después.
- ¿Expedientes Ciclo B migra a mandatos en este hito? **No.**

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
| `aa_canonical_purge_runs` | Cursor de corrida abierta | Solo se lee para bloquear attach |

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
2. **GET_LOCK** `canonical_container` (y, al mutar cuota, `storage_quota`). Sin `BEGIN`.
3. Si no hay corrida abierta: INSERT `aa_canonical_purge_runs` (`scope`, `target_id`, `status=in_progress`, cursor 0). Eso es la **intención local** que sobrevive al cierre del navegador.
4. Snapshot de inventario (keyset): imágenes confirmadas ∪ ops `admitted|cleanup_needed` del alcance, **aunque** capability inactive.
5. Si el snapshot está vacío: `seal(mandate_id?, expected_batch_count=0)` — solo si el snapshot **en este paso** está vacío. Luego DELETE local (ver 2.3).
6. Si hay ítems: crear o reutilizar `mandate_id` (UUID v4 local, persistido en la corrida). Enviar tandas ≤50 a `POST …/delete-mandates/accept` con `upload_operation_id`, `wp_record_id`, `content_sha256`, `byte_size`, `storage_path` opcional. `batch_seq` contiguo. Releer snapshot **antes de cada tanda** (ver §3).
7. Cuando el cursor local cubre el inventario actual **y** no hay ítems nuevos: `POST …/delete-mandates/seal` con `expected_batch_count` = tandas aceptadas. Exigir `inventory_status=sealed` / `structural_retire_authorized`.
8. **TX corta InnoDB** (tras HTTP cerrado): DELETE imágenes y ops del alcance acreditado; luego DELETE registro(s); si alcance contenedor, DELETE contenedor. Commit → cuota confirmada baja sola.
9. Marcar corrida `completed`. Soltar locks.
10. Storage: el worker backend (otro proceso, aún opt-in) limpia objetos. WP no espera.

### 2.2 Piezas a reutilizar

| Pieza | Uso |
|-------|-----|
| `AA_Expediente_Attachments_Backend_Client` + `aa_send_authenticated_request` | **Hecho (inc. 1):** `accept_delete_batch` / `seal_delete_mandate` / `get_delete_mandate_status` |
| Repos de imágenes y ops | Nuevos `list_inventory_*` keyset (hoy no existen) |
| `CanonicalPurgeRunsRepository` | Extender: crear/reanudar corrida, persistir `mandate_id`, cursor, incomplete |
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

- **WP:** lock `canonical_container` durante snapshot + HTTP + DELETE local. Attach ya espera ese lock y además `has_blocking_purge`. Abrir la corrida **antes** del primer accept hace que attach nuevo reciba bloqueo de purge (código ya existe).
- **Backend:** advisory lock de identidad: `begin` tras tombstone → `deletion_accepted`. Un PUT huérfano no crea fila WP si confirm está bloqueado; ops admitted del inventario se mandan al mandato.
- Confirm que gane el lock **antes** de abrir purge: la imagen entra en el siguiente snapshot. Confirm **después** de seal: o bien el lock lo impide, o el seal ya cerró y un accept nuevo → `inventory_sealed`. WP no debe sellar si el re-snapshot muestra filas no cubiertas.

### 3.2 Inventario que cambia durante el procesamiento

Antes de cada tanda y antes del seal: re-leer keyset. Reglas:

- Ítem ya aceptado (mismo fingerprint) → replay `already_accepted`; avanzar cursor.
- Ítem nuevo (confirm tardío que escapó — no debería con lock) → **no sellar**; nueva tanda o `incomplete`.
- Ítem desaparecido localmente (otro request) → no re-accept; status para saber si ya estaba obligado; no DELETE de fila inexistente.
- Seal solo si `count(local cubiertos por tandas 1..N) == count(snapshot actual)` y N = `contiguous_prefix_len`.

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

### Incremento 2 — inventario + persistencia de corrida

**Schema (sí, necesario):** extender `aa_canonical_purge_runs` con `mandate_id CHAR(36) NULL` (y quizá `sealed_at` / último `batch_seq` acreditado). Sin tabla nueva si el motor permite el ALTER aditivo (`DB_VERSION` bump). UNIQUE parcial de corrida abierta: **invariante Application** (ya documentado).

**Repos:** `list_inventory_after_id` en images; `list_ops_after_operation_id` en ops; `open/resume/complete` en purge_runs. Deduplicar por `upload_operation_id` en el snapshot.

**Pruebas:** SQL/AC de listados y bloqueo attach (ya hay `has_blocking_purge`), más exclusión de confirm concurrente a nivel de corrida abierta.

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

## Fuera de alcance de esta exploración

- Declarar Backend 3 validado (`later` / `reappear` pendientes).
- Activar polling del worker.
- Cambiar TTL, tombstones o identidades de la fixture Storage.
- Implementar runtime WP.
