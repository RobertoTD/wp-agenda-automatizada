# Benefit notifications — capa JS (UX-3A)

## Propósito

Traducir `benefit_notices[]` y campos legacy de respuestas AJAX en un **modelo de notificación** breve, no bloqueante y reutilizable.

Principio del sistema:

1. Node produce hechos.
2. PHP/WordPress propaga hechos.
3. **JS (este módulo) traduce hechos a notificaciones.**
4. **Renderer toast** (`AAAdmin.toast`, UX-3B) muestra esas notificaciones en el admin iframe.

## Módulo

| Archivo | Rol |
|---------|-----|
| `assets/js/services/benefitNotificationMapper.js` | Mapper/normalizer puro |

Expone:

```js
window.BenefitNotificationMapper = {
  extractBenefitNoticesFromResponse,
  normalizeBenefitNoticesFromResponse,
  mapBenefitResponseToNotifications
};
```

También exporta `module.exports` para tests Node ligeros.

## Modelo de notificación

Cada llamada a `mapBenefitResponseToNotifications` devuelve un **array** (normalmente un solo elemento compuesto):

```js
{
  severity: "success" | "warning" | "error" | "info",
  title: string,
  message: string,
  details: string[],
  fallback: string | null,
  durationMs: number,
  blocking: false,
  actions: [],
  notices: []  // notices normalizados usados
}
```

`durationMs` por defecto: success 3500, info 4000, warning 5000, error 7000.

## Contextos soportados (UX-3A)

| `context` | Flujo AJAX |
|-----------|------------|
| `cancel_admin` | `aa_cancelar_cita` |
| `confirm_admin` | `aa_confirmar_cita` |
| `send_confirmation_request` | `aa_enviar_confirmacion` |
| `ai_chat` | reservado (futuro) |

## Extracción de notices

`extractBenefitNoticesFromResponse` busca en orden:

1. `response.data.benefit_notices`
2. `response.benefit_notices`
3. `response.data.backend_response.benefit_notices`
4. `response.backend_response.benefit_notices`

No muta el objeto `response`.

## Fallback legacy (solo si no hay `benefit_notices`)

| Contexto | Campos legacy |
|----------|----------------|
| `cancel_admin` | `calendar_delete_skipped`, `calendar_quota_code` |
| `confirm_admin` | `calendar_skipped`, `data.calendarSkipped`, `calendar_quota_code`, `data.calendarQuotaCode`, `email.skipped` |
| `send_confirmation_request` | `success: false` + `code`/`error`, o `skipped` + `reason` |

Los notices sintetizados son **internos al mapper**; no se escriben de vuelta al payload AJAX.

## Severidad

- `error` — algún notice con `status: blocked`
- `warning` — hay notices y todos son `skipped`
- `success` — sin notices y `baseOutcome.status === "success"`
- `info` — fallback

## Alcance UX-3A (explícito)

- **No** renderiza UI.
- **No** reemplaza `alert()` ni `confirm()`.
- Integrado en cancelación (UX-4A.1) y confirmación admin (UX-4B.1); otros flujos pendientes.

## UX-3B — Renderer toast (admin iframe)

### Propósito

Mostrar **notification models** ya construidos por el mapper como toasts/snackbars no bloqueantes dentro del shell del admin iframe.

### Ubicación (shell admin, no `assets/js/ui/`)

| Archivo | Rol |
|---------|-----|
| `includes/admin/ui/assets/js/benefit-notification-toast.js` | Renderer DOM |
| `includes/admin/ui/assets/css/benefit-notification-toast.css` | Estilos provisionales prefijados `aa-benefit-toast-*` |
| `includes/admin/ui/shared/layout.php` | Enqueue de CSS + JS |

El mapper sigue en `assets/js/services/benefitNotificationMapper.js` (portable, sin DOM).

### API

```js
window.AAAdmin.toast = {
  show(notification, options),
  showMany(notifications, options),
  clear()
};

window.BenefitNotificationToast = window.AAAdmin.toast;
```

- `show(notification, options?)` — un toast; `options.autoDismiss === false` desactiva autocierre.
- `showMany(notifications, options?)` — varios toasts; máximo **3 visibles** (el más antiguo se elimina al mostrar el 4.º).
- `clear()` — elimina todos los toasts y timers.

### UX-3B.1 — Click para extender permanencia

- Por defecto el toast se autocierra (`durationMs` o default por severity).
- **Click en el cuerpo** (no en ×): cancela el timer actual y programa cierre **15 s** después (`DEFAULT_EXTEND_ON_CLICK_MS`). Clicks repetidos reinician otros 15 s. Añade clase `aa-benefit-toast-extended`.
- **Click en ×**: cierra al instante; `stopPropagation` evita extender.
- `options.extendOnClickMs` — override del tiempo de extensión (número > 0).
- `options.autoDismiss === false` — sin timer al mostrar ni al hacer click; el click solo puede añadir la clase visual extended.

**Pruebas manuales (consola iframe):**

| Caso | Qué hacer | Esperado |
|------|-----------|----------|
| A | `show({ durationMs: 3000, ... })`, click cuerpo antes de 3 s | ~15 s más visible + clase extended |
| B | Click en × | Cierra de inmediato |
| C | Varios clicks en cuerpo | Cada click reinicia 15 s |
| D | `showMany` con 4+ items | Máx. 3 visibles |
| E | `clear()` tras toast largo | Sin reaparición por timers |
| F | `show(n, { autoDismiss: false })`, click cuerpo | No autocierre |

**No** se modifica `AAAdmin.notify` (stub `console.log` en `main.js`).

### Relación mapper → toast

```js
var notifications = BenefitNotificationMapper.mapBenefitResponseToNotifications({
  response: ajaxPayload,
  context: 'confirm_admin'
});
BenefitNotificationToast.showMany(notifications);
```

El renderer **no** llama al mapper internamente.

### Carga en `layout.php`

1. `<head>`: `benefit-notification-toast.css` después de `admin.css`.
2. Scripts shared admin: `main.js` → `sidebar.js` → `notifications.js` → `benefit-notification-toast.js`.
3. Antes de Controllers: `assets/js/services/benefitNotificationMapper.js`.

### CSS provisional

Archivo standalone con comentario de migración futura a `admin.source.css` / Tailwind. `z-index: 9997` (debajo de modal `9999` y popover `9998`). Sin `@apply` ni dependencia de Tailwind en UX-3B.

### Prueba manual (consola del iframe admin)

Tras cargar la página:

```js
typeof AAAdmin.toast
typeof BenefitNotificationToast
typeof BenefitNotificationMapper

AAAdmin.toast.show({
  severity: "warning",
  title: "Cita confirmada",
  message: "No se creó el evento en Google Calendar.",
  details: ["Límite de sincronizaciones alcanzado."],
  fallback: "Puedes revisar Calendar manualmente.",
  durationMs: 5000,
  blocking: false,
  actions: [],
  notices: []
});

AAAdmin.toast.showMany([/* ... */]);
AAAdmin.toast.clear();

document.querySelectorAll('#aa-benefit-toast-root').length  // → 1
```

Cadena mapper → toast:

```js
BenefitNotificationToast.showMany(
  BenefitNotificationMapper.mapBenefitResponseToNotifications({
    response: { success: false, data: { code: "email_quota_exceeded", benefit_notices: [] } },
    context: "send_confirmation_request"
  })
);
```

### Alcance UX-3B / UX-3B.1 (explícito)

- **No** reemplaza `confirm()` destructivo.
- UX-3B.1 solo afecta el renderer toast (click-to-extend); no cambia el modelo de notificación UX-3A.
- Integración en controladores: UX-4A.1 (cancelación), UX-4B.1 (confirmación admin), UX-4C.1/4D.1/4D.2 (solicitud/confirmación desde modales).
- **No** conoce `benefit_notices` ni hace AJAX.
- **No** rediseño visual final (sin `DESIGN_BRIEF`).

## UX-4A.1 — Cancelación admin

### Flujo

`AdminCalendarController.handleCitaAction('cancelar')` → `confirm('¿Cancelar esta cita?')` (**intacto**) → `AdminConfirmController.onCancelar` → `ConfirmService.cancelar` → `aa_cancelar_cita`.

Tras `data.success === true`, el `alert()` de éxito posterior se reemplaza por `showCancelResultNotification(data)` en `assets/js/controllers/adminConfirmController.js` (mapper + `AAAdmin.toast`).

Errores (`success: false`, `.catch`, servicio no cargado) siguen con `alert` legacy.

### Escenarios

| Caso | Señales en `data` | Toast |
|------|-------------------|-------|
| 1. Solo cancelación local | `calendar_deleted: false`, sin `calendar_delete_skipped` / `benefit_notices` | success — “Cita cancelada.” sin mencionar Calendar |
| 2. Calendar eliminado | `calendar_deleted: true` | success + detail “Evento de Google Calendar eliminado.” (integrador) |
| 3. Calendar omitido (cuota) | `calendar_delete_skipped`, `benefit_notices` | warning vía mapper `cancel_admin` + billing si aplica |
| 4. Fallo técnico backend | Mismo shape que (1) en JS hoy | success sin Calendar — **UX-4A.2** (metadata PHP) |

### Post-proceso en el integrador (no en el mapper)

- Detail positivo Calendar solo si `calendar_deleted === true` y severidad no es `warning`/`error`.
- Si hay `details` y título “Cita cancelada”, `message` breve: “Cita cancelada.”
- Fallback a `alert` si faltan `BenefitNotificationMapper` o `AAAdmin.toast`.

### Pruebas manuales

**A — Cuota:** zorro8 past_due + syncs agotados, cita con `calendar_uid` → `confirm` sí, `alert` éxito no, toast warning; Network: `calendar_delete_skipped`, `benefit_notices`.

**B — Calendar eliminado:** cuenta active / cuota OK → toast success + detail Calendar eliminado.

**C — Sin Calendar:** sin `calendar_uid` o sin OAuth aplicable → toast success sin mencionar Calendar.

**D — Regresión:** `success: false` y errores de red → `alert`; `confirm` destructivo sin cambios.

## UX-4B.1 — Confirmación admin

### Flujo

`AdminCalendarController.handleCitaAction('confirmar')` → `AdminConfirmController.onConfirmar` → `ConfirmService.confirmar` → `aa_confirmar_cita`.

Tras `data.success === true`, el `alert()` fijo de éxito se reemplaza por `showConfirmResultNotification(data)` (mapper + `AAAdmin.toast`).

Errores (`success: false`, `.catch`, servicio no cargado) siguen con `alert` legacy.

**Fuera de alcance:** fast appointment (`ConfirmService.confirmar` directo), chat IA, frontend público. Solicitud de confirmación (`aa_enviar_confirmacion`) se cubre en UX-4C.1 para el modal admin clásico.

### Escenarios

| Caso | Toast |
|------|-------|
| Solo confirmación local | success — “Cita confirmada.” |
| Calendar creado | success/warning + detail “Evento de Google Calendar creado.” (o “ya existía” si `data.existed`) |
| Correo enviado | + detail “Correo de confirmación enviado.” solo si `email.sent === true` |
| Calendar/email omitidos (cuota) | warning vía mapper `confirm_admin` + billing |
| Mixto (Calendar OK + email omitido) | warning con detail positivo Calendar + detail negativo email (mapper) |
| Fallo técnico backend | Mismo shape ambiguo que éxito local — **UX-4B.2** |

### Post-proceso en el integrador

- Positivos Calendar: `calendar_uid` o `data.event_id`, sin `calendar_skipped` ni notice `create_event` skipped.
- Positivo email: `email.sent === true`, sin `email.skipped` ni notice `send_confirmed_email` skipped.
- Caso mixto: positivos y skips en el mismo toast si no se contradicen.
- Fallback: `alert('✅ ' + payload.message)` si faltan mapper/toast.

### Pruebas manuales

**A — Cuota:** zorro8 past_due, confirmar pending → toast warning, sin “correo enviado” ni “Calendar creado”; Network: `calendar_skipped`, `email.skipped`, `benefit_notices`.

**B — Happy path:** active + email → toast success con details Calendar + correo; `email.sent === true`, `calendar_uid` o `event_id`.

**C — Sin email:** no detail “Correo enviado”; mapper indica omisión si aplica.

**D — Error:** `success: false` → `alert` legacy.

## UX-4C.1 — Solicitud de confirmación desde modal admin clásico

### Flujo

`AdminReservationController` crea una reserva pending desde el modal clásico → `ReservationService.sendConfirmation(datos)` → `aa_enviar_confirmacion`.

El servicio `ReservationService` es compartido con frontend público, por eso **no** contiene lógica de toast. UX-4C.1 solo conecta el caller admin clásico con `AdminConfirmController.showSendConfirmationResultNotification(wpResponse)`.

**Fuera de alcance:** cita rápida (UX-4D.1), frontend público (`reservationController.js`), PHP, Node, mapper y renderer.

### Comportamiento

- Si `BenefitNotificationMapper` y `AAAdmin.toast` están cargados, el resultado se muestra como toast.
- Si faltan mapper/toast, se usa `alert` legacy mínimo.
- `wpResponse.success === false` con `benefit_notices` (por ejemplo cuota agotada) **también** se muestra como toast; no se trata como error técnico genérico.
- Error de red / JSON en `.catch` conserva `console.warn` y muestra alert corto de conexión.
- Si `datos.correo` está vacío, se mantiene el comportamiento previo: no se llama backend y solo se escribe en consola.

### Escenarios

| Caso | Señales | Toast |
|------|---------|-------|
| Happy path | `sent.client` truthy | success — “Solicitud enviada.” + “Correo de solicitud enviado al cliente.” |
| Correo al negocio | `sent.owner` truthy | detail opcional “Correo enviado al negocio.” |
| Cuota blocked | `success: false`, `code: email_quota_exceeded`, notice `send_confirmation_request` `blocked` | error vía mapper — “Solicitud no enviada” + cuota + billing si aplica |
| Skipped | `skipped: true` o notice `skipped` | mapper — `email_not_provided`, `duplicate_reminder`, `no_billable_recipients`, etc. |
| Error técnico sin notices | sin mapper output | toast error simple si toast existe; fallback alert solo si mapper/toast faltan |

### Post-proceso en el integrador

- Positivo cliente: solo si `sent.client` es truthy.
- Positivo negocio: solo si `sent.owner` es truthy.
- No se agregan positivos si `skipped === true` o existe notice `email/send_confirmation_request` con `status: skipped|blocked`.
- Si hay details y título “Solicitud enviada” / “Solicitud no enviada”, el mensaje queda breve.

### Pruebas manuales

**A — Cuota:** zorro8 past_due + `deoia_email_sends` agotado, crear reserva pending desde modal admin clásico con correo → toast “Solicitud no enviada”; Network: `aa_enviar_confirmacion`, `success: false`, `code: email_quota_exceeded`, notice `blocked`.

**B — Happy path:** zorro8 active, crear reserva pending con correo → toast success “Solicitud enviada” + detail cliente; Network: `success: true`, `sent.client` truthy.

**C — Skipped:** `email_not_provided` / `duplicate_reminder` / `no_billable_recipients` → toast de mapper; no dice “Correo enviado”.

**D — Regresión:** confirmación admin UX-4B y cancelación UX-4A siguen usando sus toasts; frontend público no cambia.

## UX-4D.1 — Cita rápida pending + solicitud de confirmación

### Flujo

`adminFastappointmentFlowController.js` crea la reserva con `ReservationService.saveReservation` (pending). Si el checkbox “Confirmar cita al agendar” **no** está marcado y el cliente tiene correo, en background llama `ReservationService.sendConfirmation(datos)` → `aa_enviar_confirmacion`.

El resultado se muestra con `AdminConfirmController.showSendConfirmationResultNotification(wpResponse)` (misma función que UX-4C.1).

`ReservationService` no se modifica: es compartido con frontend público.

### Alcance

| Cubre | No cubre (otro ciclo) |
|-------|------------------------|
| Fast pending + `datos.correo` + toast tras `sendConfirmation` | `autoConfirm` + `confirmar` → **UX-4D.2** (implementado) |
| Cuota blocked / happy / skipped con respuesta backend | Sin correo → **UX-4E** (implementado) |
| | Modal reserva legacy (ya UX-4C.1) |

### Comportamiento

- Background (sin `await`): no bloquea cierre del modal ni recarga del calendario.
- `wpResponse.success === false` con `benefit_notices` → toast (p. ej. cuota), no solo `console.warn`.
- `.catch` de red → `console.warn` + alert corto de conexión (igual que UX-4C.1).

### Pruebas manuales

**A — Happy:** cita rápida sin “Confirmar al agendar”, cliente con email, quota OK → toast “Solicitud enviada” + detail cliente; Network: `aa_enviar_confirmacion`, `success: true`, `sent.client` truthy.

**B — Cuota:** zorro8 past_due, mismo flujo → toast “Solicitud no enviada” + cuota/billing; Network: `success: false`, `code: email_quota_exceeded`, notice `blocked`.

**C — Regresión:** legacy UX-4C.1, confirmar UX-4B, cancelar UX-4A; fast pending sigue UX-4D.1.

## UX-4D.2 — Cita rápida con “Confirmar al agendar”

### Flujo

`adminFastappointmentFlowController.js` crea la reserva y, si el checkbox “Confirmar al agendar” está marcado (`autoConfirm`), llama en background `ConfirmService.confirmar(datos.id_reserva)` → `aa_confirmar_cita`.

Cuando `confirmResp.success === true`, muestra el resultado con `AdminConfirmController.showConfirmResultNotification(confirmResp)` — la misma función que UX-4B.1 en el botón confirmar del calendario (exportada en el `return` público de `AdminConfirmController`).

### Alcance

| Cubre | No cubre |
|-------|----------|
| Fast `autoConfirm` + `confirmResp.success === true` + toast | `confirmResp.success === false` (solo `console.warn` por ahora) |
| Happy path y cuota vía mapper `confirm_admin` | `.catch` de red (solo `console.error` por ahora) |
| Recarga de calendario tras toast (como antes) | Pending + `sendConfirmation` (UX-4D.1) |
| | Sin correo (UX-4E) |

### Pruebas manuales

**A — Happy:** cita rápida con checkbox marcado, email, quota OK → toast “Cita confirmada” + details Calendar/correo si la respuesta los trae; calendario recarga.

**B — Cuota:** zorro8 past_due, mismo flujo → toast warning con Calendar/email omitidos + billing; `confirmResp.success` sigue siendo `true` con `benefit_notices`.

**C — Regresión:** fast pending (UX-4D.1), legacy UX-4C.1, confirmar calendario UX-4B.1, cancelar UX-4A.1.

## UX-4E — Cita pendiente sin correo (notificación local)

### Flujo

Tras crear una cita **pending** desde admin, si `datos.correo` está vacío/falsy **no** se llama `ReservationService.sendConfirmation` → no hay `aa_enviar_confirmacion`, no hay `benefit_notices` reales.

`AdminConfirmController.showPendingCreatedWithoutEmailNotification()` construye el modelo de notificación y llama `AAAdmin.toast.showMany` **sin** `BenefitNotificationMapper` ni pseudo-response.

| Caller | Condición |
|--------|-----------|
| `adminReservationController.js` | `!autoConfirm && !datos.correo` |
| `adminFastappointmentFlowController.js` | `!autoConfirm && !datos.correo` |

### Copy

| Campo | Texto |
|-------|--------|
| `severity` | `warning` |
| `title` | Cita pendiente creada |
| `message` | Cita pendiente creada. |
| `details[0]` | El cliente no tiene correo electrónico registrado. |
| `fallback` | Puedes notificar al cliente manualmente. |

Si `AAAdmin.toast` no está disponible: `console.log` de fallback (sin error).

### Alcance

| Cubre | No cubre |
|-------|----------|
| Reserva legacy pending sin correo | `autoConfirm` sin correo → **UX-4D.2** / `showConfirmResultNotification` si el backend devuelve `email.skipped` |
| Cita rápida pending sin correo | Pending + correo (UX-4C.1 / UX-4D.1) |
| | Confirmación desde calendario (UX-4B), cancelación (UX-4A) |
| | Frontend público (`reservationController.js`), chat IA, recordatorios |

### Pruebas manuales

**A — Legacy sin correo:** modal reserva, sin auto-confirm, cliente sin email → toast warning UX-4E; sin request `aa_enviar_confirmacion`.

**B — Fast sin correo:** sin “Confirmar al agendar”, sin email → mismo toast; sin `aa_enviar_confirmacion`.

**C — Legacy con correo:** UX-4C.1; no toast UX-4E.

**D — Fast con correo:** UX-4D.1; no toast UX-4E.

**E — AutoConfirm sin correo:** no toast “Cita pendiente creada”; UX-4D.2 / confirmación según respuesta backend.

**F — Regresión:** confirmar calendario UX-4B, cancelar UX-4A.

## UX-6 — Normalización local vs. externa (etapa)

### Principio

1. **Toast local verde** — hecho confirmado en WordPress / BD (sin depender de Node).
2. **Toast externo** — automatización (email, Calendar, cuotas, conexión Node) vía mapper o helpers dedicados.
3. Un toast externo **no** debe reemplazar ni ocultar el toast local.

### UX-6A — Helper de toast local (`showLocalActionSuccessNotification`)

`AdminConfirmController.showLocalActionSuccessNotification(actionType, options?)` usa `AAAdmin.toast.showMany` con `severity: success`. No usa mapper ni `benefit_notices`.

| `actionType` | Título | Mensaje |
|--------------|--------|---------|
| `appointment_pending_created` | Cita pendiente creada | Cita pendiente de confirmación creada localmente. |
| `appointment_confirmed_local` | Cita confirmada | Cita confirmada localmente. |
| `appointment_cancelled_local` | Cita cancelada | Cita cancelada localmente. |
| `attendance_registered` | Asistencia registrada | Asistencia registrada. |
| `no_show_registered` | No asistencia registrada | No asistencia registrada. |

Opciones opcionales: `title`, `message`, `durationMs`. Fallback sin toast: `console.log('[LocalAction]', …)`.

**Integrado en 6A+6E:** `attendance_registered`, `no_show_registered`. **Pendiente:** UX-6B pending, UX-6C autoConfirm, UX-6D cancelación, UX-6F conexión, UX-6G copy externo.

### UX-6E — Asistencia / no asistencia

`adminCalendarController.js` — `aa_marcar_asistencia` / `aa_marcar_no_asistencia` (solo WP, sin Node).

| Comportamiento | Estado |
|----------------|--------|
| `confirm()` previo | **Se mantiene** |
| Éxito AJAX | Toast local UX-6A (sin `alert` de éxito) |
| Sin helper / sin toast | Fallback `alert` legacy |
| Error / `.catch` | `alert` legacy sin cambios |

### Pruebas manuales (6A + 6E)

**A — Asistió:** `confirm` → aceptar → toast verde “Asistencia registrada.”; sin alert de éxito.

**B — No asistió:** igual con “No asistencia registrada.”

**C — Cancelar `confirm()`:** sin AJAX ni toast.

**D — Error AJAX:** alerts de error igual que antes.

**E — Regresión:** confirmar/cancelar calendario, cita rápida, reserva legacy sin cambios en este ciclo.

## UX-5A.1 — Fallo de conexión con backend Node (cita rápida autoConfirm)

### Qué es

Fallo **técnico** al comunicar con el servicio de automatización (Node) vía PHP, cuando la acción **local en WordPress** (p. ej. confirmar la cita) ya pudo completarse. No es:

- Google Calendar desconectado / OAuth ausente
- Calendar o email omitidos por **cuota** (`benefit_notices`)
- Billing degraded
- Falta de correo (UX-4E)
- Retry automático desde frontend

### Comportamiento

En `adminFastappointmentFlowController.js`, rama **autoConfirm** (`ConfirmService.confirmar`):

1. Si `confirmResp.success === true`, se mantiene **`showConfirmResultNotification(confirmResp)`** (toast verde / mapper — éxito local).
2. Si además **`isConfirmAutomationIncomplete(confirmResp)`**, se muestra un **segundo toast warning** con `showAutomationConnectionFailedNotification('auto_confirm')`.
3. En **`.catch`** (fetch/JSON/red rota), solo el warning UX-5A (sin alert).

El renderer admite varios toasts visibles; el warning es **adicional**, no sustituye el éxito local.

### Detector (`isConfirmAutomationIncomplete`)

Devuelve `true` solo si (conservador):

- `wpResponse.success === true`
- `wpResponse.data.calendar_sync === false`
- Sin `benefit_notices` (o array vacío) — si hay notices, es cuota/beneficio y lo cubre el mapper
- `data.message` contiene indicios de fallo backend: `backend`, `notificar al backend`, `no se pudo notificar`, `no pudo notificar`

### Copy (warning adicional)

| Campo | Texto |
|-------|--------|
| `severity` | `warning` |
| `title` | Automatización incompleta |
| `message` | No se pudo conectar con el servicio de automatización. |
| `details[0]` | Revisa el estado de la cita. |
| `fallback` | Notifica manualmente al cliente si corresponde. |
| `durationMs` | `7000` |

Sin mencionar Calendar, OAuth, cuota ni “correo enviado”.

### Pruebas manuales

**A — Node caído + autoConfirm:** dos toasts (verde confirmación local + warning automatización); sin copy de Calendar/cuota/correo enviado.

**B — Cuota agotada (Node arriba):** solo toasts de `benefit_notices`/mapper; **no** warning UX-5A (hay `benefit_notices`).

**C — Happy path (Node arriba, cuota OK):** solo toast normal de confirmación; sin warning UX-5A.

**D — Catch (fetch/JSON roto):** warning UX-5A; sin alert.

### Fuera de alcance (5A.1)

Legacy autoConfirm, pending/`sendConfirmation`, confirmar/cancelar desde calendario, PHP, Node, mapper, toast renderer, servicios compartidos.

## Tests

```bash
node --test tests/js/benefitNotificationMapper.test.js
```

## Referencia backend

Contrato de notices en Node: `deoia-oauth-backend/docs/ops/benefit-notices.md`.
