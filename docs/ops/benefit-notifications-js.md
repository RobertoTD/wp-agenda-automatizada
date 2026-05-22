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
- Integrado en cancelación admin (UX-4A.1); otros flujos en UX-4B+.

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
- Integración en controladores: UX-4A.1 (cancelación) y siguientes microciclos.
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

## Tests

```bash
node --test tests/js/benefitNotificationMapper.test.js
```

## Referencia backend

Contrato de notices en Node: `deoia-oauth-backend/docs/ops/benefit-notices.md`.
