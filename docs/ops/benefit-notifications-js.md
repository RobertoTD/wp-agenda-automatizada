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
- **No** está integrado en controladores todavía (UX-4).

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

### Alcance UX-3B (explícito)

- **No** reemplaza `alert()` ni `confirm()`.
- **No** integra controladores (UX-4).
- **No** conoce `benefit_notices` ni hace AJAX.
- **No** rediseño visual final (sin `DESIGN_BRIEF`).

## Tests

```bash
node --test tests/js/benefitNotificationMapper.test.js
```

## Referencia backend

Contrato de notices en Node: `deoia-oauth-backend/docs/ops/benefit-notices.md`.
