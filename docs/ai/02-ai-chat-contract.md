# AI Chat Contract

## Propósito

Este documento describe el contrato esperado para el flujo `admin chat -> chat service -> backend AI gateway`.

El plugin no llama proveedores LLM directamente. Toda inferencia sale por
el backend Node mediante `POST /ai/parse`.

## Flujo objetivo

1. La UI del calendario envía un mensaje del usuario admin.
2. El endpoint `aa_admin_ai_chat` valida permisos, nonce y payload.
3. El validator normaliza el request.
4. El chat service construye contexto y prompt.
5. El backend Node ejecuta la inferencia vía su proveedor configurado.
6. La respuesta vuelve a la UI para mostrarse inicialmente como JSON.

## Gateway LLM

Todas las agendas usan Node `POST /ai/parse` (`AA_Backend_LLM_Client`).
No hay fallback a proveedores LLM desde WordPress.

Resolución: `AA_AI_LLM_Client_Factory` (log `AA_AI_LLM_RESOLVE`).

Errores con `code` preservado hacia el AJAX (no colapsados a
`ai_unavailable`): `quota_exceeded`, `backend_disabled`,
`no_installation_id`, `ai_not_configured`, `quota_service_unavailable`,
`quota_denied`, `ai_backend_not_configured`.

El desarrollo local también debe usar backend Node local. Si falta
`AA_API_BASE_URL`, `aa_send_authenticated_request` o `aa_client_secret`,
el chat devuelve `ai_backend_not_configured`.

## Request lógico esperado

```json
{
  "message": "Agendame una cita mañana a las 4 con Juan Perez para masaje",
  "context": {
    "surface": "calendar-admin-chat",
    "timezone": "America/Mexico_City"
  }
}
```

## Respuesta inicial esperada

Respuesta interna esperada desde el gateway backend:

```json
{
  "ok": true,
  "data": {
    "message": {
      "content": "{\"intent\":\"create_booking\"}"
    }
  }
}
```

## Reglas del contrato

- El request HTTP no debe hablar directamente con clases del dominio de citas.
- El controller solo orquesta, no interpreta intención de negocio.
- El chat service no debe depender del DOM ni de HTML.
- El proveedor devuelve datos del runtime LLM, no comandos de negocio finales.
- El mapeo al dominio de citas ocurrirá en una capa separada.

## Evolución esperada

Más adelante este contrato podrá extenderse con:

- metadatos de tool or skill
- intent detectada
- objeto normalizado para creación de cita
- validaciones de seguridad por acción

No se debe introducir esa complejidad hasta que la primera vuelta de chat en calendar esté funcionando.

---

## Extensión del `parsed`: subintenciones conversacionales

> **Estado**: Fases 3 + 3.5 del plan de reorganización conversacional de
> `create_booking`. El merger consume `affected_fields` (Paso 2);
> `sub_intent` gobierna cancelación y confirmación server-side (Paso 3),
> endurecida por **Paso 3.5** (puerta de afirmación explícita, guard
> sobre resolution con errores, pin de `intent` para
> `ask_availability` dentro de `create_booking`, y copy de bloqueo /
> collect más clara). Las heurísticas legacy (`is_cancel_message` y
> `isPureConfirmMessage`) siguen como red de seguridad.

### Motivación

Originalmente la decisión de cancelar / confirmar / modificar /
completar un borrador estaba repartida entre heurísticas en
`AA_Admin_AI_Chat_Service::is_cancel_message`,
`has_change_intent`, `lock_resolved_fields_from_previous`, la
política interna del merger y el detector `isPureConfirmMessage` del
frontend (`aichat.js`). Esto producía falsos negativos (p. ej. "ya
no gracias" no se reconocía como cancelar) y reglas en tensión (el
lock pre-merge y la política del merger terminaban a veces
ignorando una intención de cambio).

La Fase 2 resuelve la mitad estructural del problema: el merger ya
NO depende de `lock_resolved_fields_from_previous` ni de
`has_change_intent` — esos métodos fueron **eliminados** del service.
El merger usa `affected_fields` como señal explícita: si el usuario
dijo "quiero cambiar el servicio" el campo se limpia (y el draft
queda incompleto), no se conserva por inercia.

Lo que sigue para Fase 3 es mover `sub_intent` al dispatcher para
que `cancel_draft` y `confirm_draft` dejen de depender de
`is_cancel_message` + `isPureConfirmMessage`.

### Campos nuevos en `parsed`

Se añaden 3 campos al objeto `parsed` que hoy tiene 8 claves canónicas:

```jsonc
{
  // 8 campos legacy (sin cambios):
  "intent":       "create_booking|create_client|check_availability|find_client|list_services|unknown",
  "client_name":  "string|null",
  "service_name": "string|null",
  "staff_name":   "string|null",
  "zone_name":    "string|null",
  "date_text":    "string|null",
  "time_text":    "string|null",
  "notes":        "string|null",

  // 3 campos nuevos (Fase 1):
  "sub_intent":      "new_booking|fill_missing_fields|modify_fields|confirm_draft|cancel_draft|ask_availability|ask_draft_state|other",
  "affected_fields": ["client", "service", "staff", "zone", "date", "time", "notes"],
  "confidence":      0.0
}
```

### Semántica de `sub_intent`

| Valor                 | Uso                                                                 |
|-----------------------|---------------------------------------------------------------------|
| `new_booking`         | Primera mención de una cita (sin contexto previo relevante).        |
| `fill_missing_fields` | El usuario aporta datos que faltaban, sin modificar nada ya fijado. |
| `modify_fields`       | El usuario cambia un campo ya fijado. Aplica también cuando pide cambiar sin dar valor nuevo (“quiero cambiar el servicio”). |
| `confirm_draft`       | Afirmación pura sobre borrador ya propuesto (“sí”, “ok”).           |
| `cancel_draft`        | Abortar el borrador actual (“cancela”, “ya no gracias”).            |
| `ask_availability`    | Pregunta sobre disponibilidad sin proponer agendar aún.             |
| `ask_draft_state`     | Pregunta sobre el estado del borrador actual.                       |
| `other`               | Saludo, charla fuera de alcance, o nada encaja. **Default** cuando el LLM falla o emite un valor desconocido. |

### Semántica de `affected_fields`

Lista normalizada y cerrada con un subconjunto de:
`["client", "service", "staff", "zone", "date", "time", "notes"]`.

Convenciones:

- Solo contiene los campos que el usuario está creando, completando o
  modificando en **este** turno.
- Para `confirm_draft`, `cancel_draft`, `ask_draft_state` y `other` es `[]`.
- Para `modify_fields` puede traer el campo aunque el usuario todavía
  no haya aportado el nuevo valor (“quiero cambiar el servicio” →
  `["service"]` con `service_name=null`).
- Las claves son los alias cortos de entidad (no los nombres internos
  del parsed como `client_name`). Esto las alinea con el vocabulario
  que ya consumen `AA_Booking_Draft_Aggregator` y
  `AA_Booking_Reply_Builder`.

### Semántica de `confidence`

Número en `[0.0, 1.0]` o `null`. Representa la confianza del LLM en la
clasificación de `sub_intent`. Se usará a futuro como gate para caer
en fallbacks heurísticos cuando la confianza sea baja.

### Defaults y normalización

El contrato lo define
`AA_AI_Conversation_Contract`
(`includes/domain/ai/class-aa-ai-conversation-contract.php`). Es una
pieza de **dominio puro**: solo constantes y normalización, sin
estado, sin SQL, sin hooks.

- Entrada no válida en `sub_intent` → `AA_AI_Conversation_Contract::DEFAULT_SUB_INTENT` (= `other`).
- Entrada no válida en `affected_fields` → `[]`. Se filtran valores
  fuera del enum, se aplica trim+lowercase, se deduplica.
- Entrada no válida en `confidence` → `null`. Se acepta numérico
  dentro de `[0, 1]` (incluye strings numéricos).

La normalización ocurre en dos lugares:

- `AA_Admin_AI_Chat_Service::normalize_parsed()` para el parsed raw
  del turno actual (antes del merge).
- `AA_AI_Parsed_Merger::merge()` delega en el contrato para
  normalizar ambos lados (previous y current) al entrar. Esto es lo
  que hace que desde Fase 2 ya no haga falta un parche post-merge:
  el merger entiende el shape completo (11 claves canónicas) y
  emite las 3 señales siempre desde el current (nunca se heredan
  del previous porque son **por turno**, no estado acumulado).

### Estado actual (Fase 3)

**Merger (Paso 2)** — `AA_AI_Parsed_Merger` emite 11 claves canónicas
(`intent`, 7 campos de datos, `sub_intent`, `affected_fields`,
`confidence`) y aplica `affected_fields` como regla central:

| Campo está en `affected_fields` | Current significativo | Current vacío/null  |
|---------------------------------|-----------------------|---------------------|
| **Sí**                          | usar current          | **limpiar (null)**  |
| **No**                          | usar current          | preservar previous  |

`has_change_intent`, `lock_resolved_fields_from_previous` y
`preserve_conversation_fields_after_merge` están retirados.

**Dispatch de cancelación (Paso 3)** — el chat service intercepta
`sub_intent === 'cancel_draft'` **justo después** de normalizar el
parsed del LLM, antes del merge y antes del dispatch por intent.
Reutiliza `build_cancel_success_response()`: devuelve mensaje de
cancelación, resetea `parsed` a todos-null con
`sub_intent=cancel_draft` y `confidence=1.0`, y pone `draft_state=null`.

La heurística previa `is_cancel_message()` sigue viva como
short-circuit pre-LLM: cuando matchea, ahorra la llamada al modelo.
No es ya la fuente principal de cancelación; es red de seguridad
para frases obvias.

**Dispatch de confirmación (Paso 3)** — el chat service intercepta
`sub_intent === 'confirm_draft'` **después** del merge y **después**
de `dispatch_intent()`, porque necesita el `draft_state` ya
construido por `handle_create_booking`.

Camino server-side:

1. **Paso 3.5 — afirmación**: el texto del usuario debe pasar
   `is_message_affirmation_for_server_booking()` (afirmación corta o
   frases tipo «dale, agéndala»; rechaza comas, horas, «profesional»,
   «existe», etc.). Si no, log `rejected_not_affirmation_utterance` y
   no se confirma.
2. **Paso 3.5 — guard de resolution**: si hay `blockers`, `state`
   incompatible, `required_literal` pendiente, `ambiguous_fields`,
   alguna fila de `feasibility` con `status: incompatible`, o `lookup`
   con `no_match` / `ambiguous` / `missing`, no se confirma. Log
   `rejected_resolution_guard`.
3. Si `draft_state.state !== 'ready_for_confirmation'` → fallback al
   reply builder normal (ya explica qué falta). Log:
   `confirm_action: rejected_not_ready`.
4. Traduce `draft_state.draft` al input de
   `AA_AI_Confirm_Booking_Use_Case::execute()` — el **mismo** use
   case que consume el endpoint `aa_ai_confirm_booking`. Cero
   duplicación de reglas de reserva/assignment/auto-confirm.
5. Si el use case responde `ok` → respuesta `booking_confirmed` con
   `parsed` reseteado, `draft_state=null` y
   `resolution.confirmation = { reservation_id, assignment_id, confirmed, ... }`.
   Cuando `confirmed === true` y la confirmación backend devolvió `success`,
   se añade **`confirm_notification`**: mismo shape que
   `aa_build_confirm_cita_ajax_success_payload` (UX-6H.1). El frontend
   (`aichat.js` vía `AdminConfirmController.showAutoConfirmBookingNotifications`)
   muestra toasts operativos UX-6 sin alterar el mensaje conversacional (UX-6H.2).
6. Si el use case responde error → `booking_confirm_failed`,
   preserva `parsed` para reintento, anexa
   `resolution.confirmation_error = { stage, message }`.

**Paso 3.5 — post-merge (mismo `handle`)**: si
`previous_parsed.intent === 'create_booking'` y el modelo clasifica
`sub_intent === 'ask_availability'`, se fuerza `intent = create_booking`
para no caer en `handle_unimplemented_intent` con copy genérico. Si
el modelo clasifica `confirm_draft` pero el mensaje no es afirmación
válida, se rebaja `sub_intent` a `other` antes del dispatch.

El frontend (`aichat.js`) detecta `intent_result.status === 'booking_confirmed'`
y dispara `aa-assignment-created` para refrescar el calendario +
deshabilita el CTA del turno anterior (mismo efecto que ya hacía
tras `runConfirmBookingAjax`). Si `confirmation.confirm_notification`
está presente, también dispara toasts UX-6 (local + externos o warning
de automatización) vía `showAutoConfirmBookingNotifications`.

`isPureConfirmMessage` en `aichat.js` y el endpoint
`aa_ai_confirm_booking` permanecen: el botón "Confirmar cita" y
las frases puras tipo "sí" siguen POSTeando al endpoint directo
sin pasar por el chat. Cuando esa ruta **no** se activa (frase
menos obvia, LLM confirma con matiz, etc.) el camino server-side
del chat se encarga.

El dispatcher sigue enrutando por `intent` para `create_booking`;
`sub_intent` gobierna sólo los dos casos de borde
(`cancel_draft`, `confirm_draft`). El reply builder sigue sin
consumir `sub_intent`.

### Extensión inicial: `create_client`

`create_client` es un intent top-level reconocido solo en clasificación
inicial (`previous_parsed === null`). No inicia conversación multi-turn,
no pide campos faltantes, no crea clientes y no toca SQL. El chat service
devuelve una respuesta textual controlada con `reply_ui.cta = "noop"`:

> Por ahora no puedo crear clientes desde este asistente, pero puedes crearlo manualmente en la sección de Clientes.

La detección combina prompt LLM y un detector puro/conservador en
`includes/domain/ai/`: exige señal explícita de cliente ("crear cliente",
"agregar cliente", "registrar nuevo cliente", "dar de alta cliente") y
rechaza menciones de cita/disponibilidad para no colisionar con
`create_booking` ni `check_availability`.

Cuando esa misma petición aparece dentro de un flujo activo de
`create_booking`, no cambia el intent principal ni limpia el draft. El
service conserva el flujo normal de booking y adjunta a
`reply_ui.assistive_notice` un bloque secundario con texto y actions
(`clients_create`) para navegar a Clientes. El frontend renderiza ese
bloque debajo de la respuesta principal sin reemplazar el CTA normal
(`collect_input`, `confirm`, `pick_ambiguous`, etc.).

### Telemetría

Cuando el constant PHP `AA_AI_CHAT_DEBUG` está definido y truthy, el
service emite una línea JSON por turno vía `error_log`, con la raíz
`AA_AI_CHAT_TURN`:

```json
{"AA_AI_CHAT_TURN":{
  "message":"cambia la hora a las 6",
  "previous_parsed":{"intent":"create_booking","client_name":"…", "…":"…"},
  "parsed_raw":{"intent":"create_booking","time_text":"a las 6","sub_intent":"modify_fields","affected_fields":["time"],"confidence":0.95},
  "parsed":{"intent":"create_booking","client_name":"…","time_text":"a las 6","sub_intent":"modify_fields","affected_fields":["time"]},
  "sub_intent":"modify_fields",
  "affected_fields":["time"]
}}
```

Filtra en `wp-content/debug.log` con `grep AA_AI_CHAT_TURN`.

Para activar en desarrollo:

```php
define('AA_AI_CHAT_DEBUG', true);
```

Por default el logger es no-op: no hay ruido en producción si el
define no está presente.

**Campos adicionales (Paso 3)** en la línea de log cuando corresponda:

- `short_circuit: "is_cancel_message"` → canceló por heurística pre-LLM.
- `short_circuit: "sub_intent_cancel_draft"` → canceló por clasificación
  del LLM.
- `confirm_action: "ok"` + `reservation_id`, `assignment_id` → se ejecutó
  la confirmación server-side con éxito.
- `confirm_action: "error"` + `stage`, `error_message` → el use case
  rechazó la confirmación.
- `confirm_action: "rejected_not_ready"` + `draft_state` → el usuario
  quiso confirmar pero el borrador no está listo (fallback al reply
  builder).
- `confirm_action: "rejected_invalid_draft"` → salvaguarda defensiva:
  `ready_for_confirmation` pero al intentar traducir el draft algo
  esencial no estaba presente.

### Roadmap de fases siguientes

- **Fase 2 (hecha)**: `AA_AI_Parsed_Merger` consume `affected_fields`
  como regla central. Se retiran `lock_resolved_fields_from_previous`,
  `has_change_intent` y `preserve_conversation_fields_after_merge`.
- **Fase 3 (hecha)**: `sub_intent` gobierna server-side
  `cancel_draft` (pre-merge) y `confirm_draft` (post-dispatch,
  reutilizando `AA_AI_Confirm_Booking_Use_Case`).
  `is_cancel_message` e `isPureConfirmMessage` siguen como red de
  seguridad temporal.
- **Fase 4**: rama dedicada `ask_availability` que, sobre el draft en
  curso, propone huecos alternativos en lugar de repetir blockers
  rígidos.
- **Fase 5**: retirar las heurísticas legacy de cancel/change/confirm
  y el endpoint directo `aa_ai_confirm_booking` del camino del
  botón (o convertirlo en fino wrapper sobre la misma ruta de
  chat). Consolidar el contrato nuevo como única fuente de verdad.
