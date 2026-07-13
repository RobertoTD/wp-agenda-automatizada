# Push notifications — arquitectura

## Dos conceptos independientes

### Suscripción DEOIA (`app_subscription_active`)

Indica si la instalación tiene acceso activo a la app según Cuenta:

- `app_subscription_active = true` solo cuando `account_status.billing_state === 'active'`.
- Cubre Freemium y Pro con acceso vigente.
- Una sola consulta por carga de Agenda App vía `aa_get_account_status` → `GetAccountStatusUseCase` → `/oauth/account-status`, con memoria de promesa en la página.

No sustituye este flag:

- `aa_client_secret`;
- provisioning histórico;
- `effective_access_tier` aislado;
- permiso `Notification`;
- existencia de `PushSubscription`.

Si account-status falla o devuelve un estado distinto de `active`, se trata como `app_subscription_active=false`: la tarea push se oculta y no se ejecuta el pipeline Push; Agenda App carga con normalidad.

### Readiness Push del contexto actual (`push_ready`)

Describe el navegador, perfil o PWA **abierto ahora**:

1. Push API soportada;
2. `Notification.permission === 'granted'`;
3. `serviceWorkerRegistration.pushManager.getSubscription()` devuelve una suscripción;
4. esa suscripción se registró o reconcilió con backend (`data.ok === true`).

`first_test.status` es informativo; el registro es exitoso por `data.ok === true`.

La comprobación al cargar Agenda App es **pasiva**: no llama `pushManager.subscribe()`. Si no hay suscripción local, `push_ready=false` y la tarea puede mostrarse cuando `app_subscription_active=true`.

## Tarea global `enable_push`

Una sola fila por instalación WordPress:

| Campo | Valor |
|---|---|
| `source_category` | `agenda_app` |
| `origin_key` | `enable_push` |
| Lista | `learning.recommendations` / Activación de tu agenda |
| `default_bucket` | `primary` |
| `importance` | `110` |
| `completion_type` | `system` |
| `completion_fact_key` | `null` |
| Handler | `push.activate` |
| Label | `Activar notificaciones` |

La tarea permanece **`pending`**. No se completa globalmente cuando un navegador se suscribe. No almacena `device_key`, endpoint, `p256dh` ni `auth`. No participa en routing ni selección de dispositivos.

Origins legacy `enable_push:*` se ocultan siempre en feed y Propuesta ejecutiva; no hay compatibilidad runtime ni migración automática.

## Visibilidad en feed

Proyección antes de conteos, orden, meta, enrich y render:

| `app_subscription_active` | `push_ready` | resultado |
|---|---:|---|
| false | false | ocultar |
| false | true | ocultar |
| true | false | mostrar |
| true | true | ocultar |

El request del feed recibe `app_subscription_active` y `push_ready` como booleanos de proyección; no conceden acceso a funcionalidades protegidas.

## Propuesta ejecutiva

`enable_push` y cualquier `enable_push:*` se excluyen siempre. La tarea solo aparece en **Activación de tu agenda**.

## Endpoint ensure

`aa_reconcile_push_activation_task` es **ensure-only** (sin `readiness`, `device_key` ni rama `prepared`):

- Asegura la tarea global `enable_push` en `pending` y la acción `push.activate`.
- Respuesta: `{ task, created, retryable }`.
- El cliente lo invoca solo cuando `app_subscription_active=true` y `push_ready=false`.

## Alcance de PushSubscription

Las suscripciones Web Push son **endpoint-scoped**:

- Cada navegador/perfil registra su propio `endpoint` en `push_subscriptions` (backend OAuth).
- Una instalación puede tener múltiples filas activas.
- El registro usa `endpoint`, `p256dh` y `auth`; no se envían al feed.

## Envío (broadcast)

Los workers cargan todas las suscripciones de la instalación (`installation_id`) y envían a cada endpoint activo. La tarea WordPress no interviene en el broadcast.

## Flujo en Agenda App

Orden obligatorio al cargar:

1. Resolver `app_subscription_active`.
2. Si es `false`: no comprobar Push; feed con ambos flags en `false`.
3. Si es `true`: evaluar pasivamente `push_ready`; si `false`, llamar ensure.
4. Primera carga del feed con los valores resueltos (secuencial, sin paralelizar ensure y feed).

Tras click exitoso en `push.activate`: marcar `push_ready=true` y recargar el feed con `forceFresh` (nueva petición real; respuestas obsoletas no sobrescriben cargas más nuevas).

`maybeAttemptAutomaticRecovery()` solo corre tras confirmar `app_subscription_active=true`.

## “Ahora no”

La tarea sigue `pending`. `dismiss_until` la oculta temporalmente (24 h, efecto global aceptado por ahora).
