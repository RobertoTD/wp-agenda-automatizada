/**
 * AI Chat Assistant — Panel UI (Step 6.b: multi-turn + sessionStorage)
 *
 * Self-mounts on calendar admin by locating the shared FAB stack
 * (`.fixed.bottom-6.right-6.z-50.flex.flex-col.items-end.gap-3`).
 * If the container is absent (non-calendar admin), mounting aborts
 * silently and leaves no DOM trace.
 *
 * Backend wiring:
 *   - `sendMessage(text)` POSTs to `aa_admin_ai_chat` with
 *     `window.AA_AI_CHAT.nonce` and maps `reply_ui` → Message.
 *     Sends `previous_parsed` (last server-side merged snapshot) on
 *     follow-up turns so the server can fuse the new utterance with
 *     the previous draft (paso 6.a).
 *   - The "Confirmar cita" button POSTs to `aa_ai_confirm_booking`
 *     with `window.AA_AI_CHAT.confirm_nonce` using the last received
 *     `draft_state.draft`, and dispatches `aa-assignment-created`
 *     so the calendar timeline (calendar-module.js:275) refreshes.
 *
 * Persistence (paso 6.b):
 *   - `messages`, `lastParsedInput` and `lastDraftState` are persisted
 *     in `sessionStorage` under `AA_AI_CHAT_STORAGE_KEY` and rehydrated
 *     on mount. `isOpen`, `isTyping`, `lastUserChatSendAt` and
 *     `isConfirmBookingRequest` are intentionally NOT persisted.
 *   - Storage is cleared on (a) explicit "Nueva conversación" button,
 *     (b) hydration failures (corrupt JSON or unknown message kinds).
 *
 * Paso 6.h: confirmación por lenguaje natural (solo afirmación pura,
 * sin `aa_admin_ai_chat`) reutiliza el mismo POST que el botón.
 *
 * Paso 6.i: tras confirmar con éxito se mantiene el historial; la última
 * tarjeta `confirm_cta` queda con el botón deshabilitado (`confirmDisabled`).
 */
(function () {
    'use strict';

    // ============================================================
    // Constants
    // ============================================================
    const AA_AI_CHAT_STORAGE_KEY = 'aa_ai_chat_state_v1';
    /** Paso 6.g: límite de caracteres y cooldown entre envíos (solo frontend). */
    const AA_AI_CHAT_MAX_MESSAGE_LENGTH = 300;
    const AA_AI_CHAT_SEND_COOLDOWN_MS = 2000;
    const KNOWN_MESSAGE_KINDS = [
        'text',
        'confirm_cta',
        'ambiguous_choices',
        'highlights',
        'fix_blocker'
    ];

    // ============================================================
    // State (in-memory, per page load; subset persisted)
    // ============================================================
    const state = {
        isOpen: false,
        isTyping: false,
        messages: [],
        // Last `draft_state` returned by the chat endpoint. Source of
        // truth for the Confirm button payload. Reset to null after a
        // successful confirmation.
        lastDraftState: null,
        // Last `parsed` snapshot returned by the chat endpoint (already
        // merged server-side). Reused as `previous_parsed` on the next
        // turn so the conversation accumulates context. Reset to null
        // after a successful confirmation or an explicit reset.
        lastParsedInput: null,
        // Timestamp (ms) del último envío de chat aceptado (pasa validación).
        // No persistido; independiente de `isTyping` (paso 6.g).
        lastUserChatSendAt: null,
        // POST `aa_ai_confirm_booking` en curso (botón o afirmación pura).
        isConfirmBookingRequest: false
    };

    // ============================================================
    // DOM refs (populated on mount)
    // ============================================================
    const dom = {
        fab: null,
        panel: null,
        closeBtn: null,
        resetBtn: null,
        history: null,
        composer: null,
        input: null,
        sendBtn: null,
        lastFocusedBeforeOpen: null
    };

    // ============================================================
    // Utils
    // ============================================================
    function uid() {
        return 'm_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cloneTemplateHtml(templateId) {
        const tpl = document.getElementById(templateId);
        if (!tpl || !tpl.content) return '';
        const wrap = document.createElement('div');
        wrap.appendChild(tpl.content.cloneNode(true));
        return wrap.innerHTML;
    }

    // ============================================================
    // Mounting
    // ============================================================
    function mount() {
        const stack = document.querySelector(
            '.fixed.bottom-6.right-6.z-50.flex.flex-col.items-end.gap-3'
        );
        if (!stack) {
            // Not on calendar → bail silently, leave no trace.
            return;
        }

        // Insert FAB as the FIRST child of the flex-col stack
        // (visually appears above the "Cita rápida" FAB).
        const fabHtml = cloneTemplateHtml('aa-ai-chat-fab-template');
        if (!fabHtml) return;
        const fabWrap = document.createElement('div');
        fabWrap.innerHTML = fabHtml.trim();
        const fabNode = fabWrap.firstElementChild;
        if (!fabNode) return;
        stack.insertBefore(fabNode, stack.firstChild);
        dom.fab = fabNode;

        // Inject the panel into <body>.
        const panelHtml = cloneTemplateHtml('aa-ai-chat-panel-template');
        if (!panelHtml) return;
        const panelWrap = document.createElement('div');
        panelWrap.innerHTML = panelHtml.trim();
        const panelNode = panelWrap.firstElementChild;
        if (!panelNode) return;
        document.body.appendChild(panelNode);
        dom.panel = panelNode;

        dom.closeBtn = panelNode.querySelector('#aa-ai-chat-close');
        dom.resetBtn = panelNode.querySelector('#aa-ai-chat-reset');
        dom.history = panelNode.querySelector('#aa-ai-chat-history');
        dom.composer = panelNode.querySelector('#aa-ai-chat-composer');
        dom.input = panelNode.querySelector('#aa-ai-chat-input');
        dom.sendBtn = panelNode.querySelector('#aa-ai-chat-send');

        hydrateState();
        bindEvents();
        render();
    }

    // ============================================================
    // Event binding
    // ============================================================
    function bindEvents() {
        dom.fab.addEventListener('click', togglePanel);
        dom.closeBtn.addEventListener('click', closePanel);
        if (dom.resetBtn) {
            dom.resetBtn.addEventListener('click', function () {
                resetConversation({ confirm: true });
            });
        }

        dom.composer.addEventListener('submit', function (ev) {
            ev.preventDefault();
            submitFromComposer();
        });

        dom.input.addEventListener('input', function () {
            autoresizeInput();
            updateSendDisabled();
        });

        dom.input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                submitFromComposer();
            }
        });

        // Escape closes the panel; Tab gets trapped inside the panel.
        document.addEventListener('keydown', function (ev) {
            if (!state.isOpen) return;
            if (ev.key === 'Escape') {
                ev.preventDefault();
                closePanel();
                return;
            }
            if (ev.key === 'Tab') {
                trapFocus(ev);
            }
        });

        // Delegated clicks inside history: chips + confirm button.
        dom.history.addEventListener('click', function (ev) {
            const chip = ev.target.closest('[data-aa-ai-chat-chip]');
            if (chip) {
                const label = chip.getAttribute('data-label') || chip.textContent.trim();
                sendMessage(label);
                return;
            }
            const confirmBtn = ev.target.closest('[data-aa-ai-chat-confirm]');
            if (confirmBtn) {
                if (confirmBtn.disabled) {
                    return;
                }
                confirmDraft(confirmBtn);
                return;
            }
        });
    }

    function submitFromComposer() {
        const raw = dom.input.value;
        const text = raw.replace(/\s+$/g, '');
        if (!text || state.isTyping) return;

        const outboundBlock = getUserOutboundSendBlockReason(text);
        if (outboundBlock) {
            pushUserOutboundRejected(outboundBlock);
            return;
        }

        dom.input.value = '';
        autoresizeInput();
        updateSendDisabled();
        dom.input.focus();
        sendMessage(text);
    }

    function autoresizeInput() {
        const el = dom.input;
        el.style.height = 'auto';
        const next = Math.min(el.scrollHeight, 96); // CSS cap ~4 lines
        el.style.height = next + 'px';
    }

    function updateSendDisabled() {
        const hasText = dom.input.value.trim().length > 0;
        dom.sendBtn.disabled = !hasText || state.isTyping || state.isConfirmBookingRequest;
    }

    // ============================================================
    // Open / close
    // ============================================================
    function togglePanel() {
        if (state.isOpen) closePanel();
        else openPanel();
    }

    function openPanel() {
        if (state.isOpen) return;
        state.isOpen = true;
        dom.lastFocusedBeforeOpen = document.activeElement;

        dom.panel.classList.remove('hidden');
        dom.panel.classList.add('flex');
        dom.panel.classList.remove('aa-ai-chat-panel-leave');
        dom.panel.classList.add('aa-ai-chat-panel-enter');
        dom.fab.setAttribute('aria-expanded', 'true');

        // Focus the textarea after the animation frame so screen readers
        // pick it up cleanly.
        requestAnimationFrame(function () {
            if (dom.input) dom.input.focus();
        });
    }

    function closePanel() {
        if (!state.isOpen) return;
        state.isOpen = false;
        dom.fab.setAttribute('aria-expanded', 'false');

        dom.panel.classList.remove('aa-ai-chat-panel-enter');
        dom.panel.classList.add('aa-ai-chat-panel-leave');

        // Give the exit animation time, then actually hide.
        const panel = dom.panel;
        setTimeout(function () {
            if (state.isOpen) return; // user re-opened during animation
            panel.classList.add('hidden');
            panel.classList.remove('flex');
            panel.classList.remove('aa-ai-chat-panel-leave');
        }, 150);

        // Return focus to the FAB so keyboard users don't get lost.
        if (dom.fab) dom.fab.focus();
    }

    // ============================================================
    // Focus trap
    // ============================================================
    function getFocusable() {
        if (!dom.panel) return [];
        const sel = [
            'a[href]',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(',');
        return Array.from(dom.panel.querySelectorAll(sel))
            .filter(function (el) { return el.offsetParent !== null || el === document.activeElement; });
    }

    function trapFocus(ev) {
        const focusables = getFocusable();
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement;

        if (ev.shiftKey) {
            if (active === first || !dom.panel.contains(active)) {
                ev.preventDefault();
                last.focus();
            }
        } else {
            if (active === last) {
                ev.preventDefault();
                first.focus();
            }
        }
    }

    // ============================================================
    // Messaging
    // ============================================================

    /**
     * @param {string} text Mensaje tal como se enviaría al backend.
     * @returns {'length'|'cooldown'|null} null si el envío puede proceder.
     */
    function getUserOutboundSendBlockReason(text) {
        const t = String(text);
        if (t.length > AA_AI_CHAT_MAX_MESSAGE_LENGTH) {
            return 'length';
        }
        if (state.lastUserChatSendAt != null) {
            const elapsed = Date.now() - state.lastUserChatSendAt;
            if (elapsed < AA_AI_CHAT_SEND_COOLDOWN_MS) {
                return 'cooldown';
            }
        }
        return null;
    }

    /**
     * Aviso local (sin AJAX) cuando longitud o cooldown bloquean el envío.
     */
    function pushUserOutboundRejected(reason) {
        if (reason === 'length') {
            pushMessage({
                id: uid(),
                role: 'assistant',
                kind: 'fix_blocker',
                text: 'El mensaje es demasiado largo. Resume la solicitud en menos de 300 caracteres.',
                payload: { blocker: 'Máximo 300 caracteres' },
                ts: Date.now()
            });
            return;
        }
        pushMessage({
            id: uid(),
            role: 'assistant',
            kind: 'fix_blocker',
            text: 'Espera un momento antes de enviar otro mensaje.',
            payload: { blocker: 'Espera 2 segundos' },
            ts: Date.now()
        });
    }

    /**
     * Single entry point for outgoing user messages.
     *
     * Pushes the user bubble synchronously, shows typing indicator,
     * POSTs to `aa_admin_ai_chat`, then maps the response into a
     * Message and persists `draft_state` in `state.lastDraftState`.
     */
    function sendMessage(text) {
        const outboundBlock = getUserOutboundSendBlockReason(text);
        if (outboundBlock) {
            pushUserOutboundRejected(outboundBlock);
            return;
        }

        if (state.isConfirmBookingRequest) {
            return;
        }

        // Paso 6.h: afirmación pura + borrador confirmable → mismo POST que el botón (sin chat).
        if (isPureConfirmMessage(text) && lastDraftStateIsConfirmableShell()) {
            const prep = validateDraftStateForConfirmBooking();
            if (prep.ok) {
                state.isConfirmBookingRequest = true;
                updateSendDisabled();
                runConfirmBookingAjax(prep.body, function () {
                    state.isConfirmBookingRequest = false;
                    updateSendDisabled();
                });
                return;
            }
        }

        state.lastUserChatSendAt = Date.now();

        pushMessage({
            id: uid(),
            role: 'user',
            kind: 'text',
            text: String(text),
            ts: Date.now()
        });

        state.isTyping = true;
        updateSendDisabled();
        render();

        const extraBody = { message: String(text) };
        if (state.lastParsedInput !== null) {
            try {
                extraBody.previous_parsed = JSON.stringify(state.lastParsedInput);
            } catch (_e) {
                // If stringify fails (circular ref shouldn't occur on a
                // server-shaped POJO, but defensive), skip the field.
                // The server treats absent `previous_parsed` as a fresh turn.
            }
        }

        ajaxPost('aa_admin_ai_chat', extraBody)
            .then(function (res) {
                state.isTyping = false;
                if (res.ok) {
                    const result = (res.data && res.data.intent_result) || {};
                    const resolution = (result && result.resolution) || {};
                    const replyUi = resolution.reply_ui || null;
                    const draftState = resolution.draft_state || null;
                    state.lastDraftState = draftState;
                    state.lastParsedInput = (res.data && res.data.parsed) ? res.data.parsed : null;
                    persistState();
                    const fallbackText = res.data && res.data.reply_text;
                    pushMessage(mapReplyUiToMessage(replyUi, draftState, fallbackText));
                } else {
                    const serverMsg = (res.data && res.data.message) || 'Error desconocido';
                    pushMessage({
                        id: uid(),
                        role: 'assistant',
                        kind: 'fix_blocker',
                        text: 'No pude procesar la solicitud.',
                        payload: { blocker: serverMsg },
                        ts: Date.now()
                    });
                }
            })
            .catch(function (err) {
                state.isTyping = false;
                pushMessage(buildNetworkErrorMessage(err, 'No pude conectar con el servidor.'));
            })
            .then(function () {
                updateSendDisabled();
            });
    }

    function pushMessage(msg) {
        state.messages.push(msg);
        persistState();
        render();
    }

    /**
     * Maps a server-side `reply_ui` payload to a UI-side Message.
     *
     * | reply_ui.cta        | Message.kind        | payload                                                                  |
     * |---------------------|---------------------|--------------------------------------------------------------------------|
     * | confirm             | confirm_cta         | { confirmLabel:'Confirmar cita', draftEcho: buildDraftEcho(draft_echo) } |
     * | confirm_heuristics  | confirm_cta         | (idéntico a `confirm`)                                                   |
     * | pick_ambiguous      | ambiguous_choices   | { field: choices[0].field, choices: choices[0].candidates }              |
     * | collect_input       | text                | (sin payload)                                                            |
     * | fix_blocker         | fix_blocker         | { blocker: '' }                                                          |
     * | noop                | text                | (sin payload)                                                            |
     *
     * Si `reply_ui` viene ausente/vacío, cae a `text` con `fallbackText`
     * (típicamente `data.reply_text`). `draft_state` no se usa aquí
     * directamente — se persiste en `state.lastDraftState` desde el caller.
     */
    function mapReplyUiToMessage(reply_ui, draft_state, fallbackText) {
        if (!reply_ui) {
            return {
                id: uid(),
                role: 'assistant',
                kind: 'text',
                text: String(fallbackText || ''),
                ts: Date.now()
            };
        }

        const text = String(reply_ui.text || fallbackText || '');
        const cta = reply_ui.cta || 'noop';
        const base = { id: uid(), role: 'assistant', text: text, ts: Date.now() };

        switch (cta) {
            case 'confirm':
            case 'confirm_heuristics':
                return Object.assign(base, {
                    kind: 'confirm_cta',
                    payload: {
                        confirmLabel: 'Confirmar cita',
                        draftEcho: buildDraftEcho(reply_ui.draft_echo)
                    }
                });

            case 'pick_ambiguous': {
                const first = (Array.isArray(reply_ui.choices) && reply_ui.choices[0]) || null;
                const field = first && first.field ? String(first.field) : '';
                const candidates = (first && Array.isArray(first.candidates))
                    ? first.candidates.map(function (c) {
                        return { id: c.id, label: String(c.label) };
                    })
                    : [];
                return Object.assign(base, {
                    kind: 'ambiguous_choices',
                    payload: { field: field, choices: candidates }
                });
            }

            case 'fix_blocker':
                return Object.assign(base, {
                    kind: 'fix_blocker',
                    payload: { blocker: '' }
                });

            case 'collect_input':
            case 'noop':
            default:
                return Object.assign(base, { kind: 'text' });
        }
    }

    /**
     * Converts the server's `reply_ui.draft_echo`
     * `{client, service, staff, zone, datetime}` into the
     * `[{label, value}]` array consumed by `renderAssistantConfirmCta`.
     * Skips fields whose value is null/empty. Labels in Spanish.
     * `datetime` already comes pre-formatted by reply_builder.
     */
    function buildDraftEcho(draftEcho) {
        if (!draftEcho || typeof draftEcho !== 'object') return [];
        const order = [
            ['client',   'Cliente'],
            ['service',  'Servicio'],
            ['staff',    'Profesional'],
            ['zone',     'Zona'],
            ['datetime', 'Fecha']
        ];
        const rows = [];
        for (let i = 0; i < order.length; i++) {
            const key = order[i][0];
            const label = order[i][1];
            const value = draftEcho[key];
            if (value == null || value === '') continue;
            rows.push({ label: label, value: String(value) });
        }
        return rows;
    }

    // ============================================================
    // Confirmation
    // ============================================================

    /**
     * @returns {boolean} true si hay shell de borrador listo (sin validar IDs).
     */
    function lastDraftStateIsConfirmableShell() {
        const ds = state.lastDraftState;
        return !!(ds && ds.state === 'ready_for_confirmation' && ds.draft);
    }

    /**
     * Valida `state.lastDraftState` y construye el body del POST de confirmación.
     *
     * @returns {{ ok:true, body:object }|{ ok:false, reason:'no_draft'|'missing', missing?:string[] }}
     */
    function validateDraftStateForConfirmBooking() {
        const draftState = state.lastDraftState;
        if (!draftState || draftState.state !== 'ready_for_confirmation' || !draftState.draft) {
            return { ok: false, reason: 'no_draft' };
        }

        const draft = draftState.draft;
        const required = {
            client_id:        draft.client && draft.client.id,
            service_id:       draft.service && draft.service.id,
            staff_id:         draft.staff && draft.staff.id,
            zone_id:          draft.zone && draft.zone.id,
            start_datetime:   draft.datetime && draft.datetime.local_datetime,
            duration_minutes: draft.duration && draft.duration.minutes,
            assignment_mode:  draft.assignment && draft.assignment.mode
        };
        const missing = Object.keys(required).filter(function (k) {
            const v = required[k];
            return v == null || v === 0 || v === '';
        });
        if (missing.length > 0) {
            return { ok: false, reason: 'missing', missing: missing };
        }

        const body = {
            client_id:        required.client_id,
            service_id:       required.service_id,
            staff_id:         required.staff_id,
            zone_id:          required.zone_id,
            start_datetime:   required.start_datetime,
            duration_minutes: required.duration_minutes,
            assignment_mode:  required.assignment_mode
        };
        if (required.assignment_mode === 'reuse') {
            body.assignment_id = draft.assignment.assignment_id;
        }

        return { ok: true, body: body };
    }

    /**
     * Marca la última burbuja `confirm_cta` del historial como ya usada (paso 6.i).
     * El render deshabilita el botón y cambia el label.
     */
    function markLastConfirmCtaMessageDisabled() {
        for (let i = state.messages.length - 1; i >= 0; i--) {
            const m = state.messages[i];
            if (m && m.role === 'assistant' && m.kind === 'confirm_cta') {
                m.confirmDisabled = true;
                return;
            }
        }
    }

    /**
     * POST `aa_ai_confirm_booking` — única implementación (botón y paso 6.h).
     *
     * @param {object} body Payload ya validado.
     * @param {function} [onComplete] Siempre al terminar (éxito, error o red).
     */
    function runConfirmBookingAjax(body, onComplete) {
        const done = typeof onComplete === 'function' ? onComplete : function () {};

        ajaxPost('aa_ai_confirm_booking', body)
            .then(function (res) {
                if (res.ok) {
                    state.lastDraftState = null;
                    state.lastParsedInput = null;
                    state.lastUserChatSendAt = null;
                    state.isConfirmBookingRequest = false;
                    markLastConfirmCtaMessageDisabled();
                    pushMessage({
                        id: uid(),
                        role: 'assistant',
                        kind: 'text',
                        text: 'Cita agendada.',
                        ts: Date.now()
                    });
                    document.dispatchEvent(new CustomEvent('aa-assignment-created'));
                } else {
                    const stage = (res.data && res.data.stage) || 'unknown';
                    const serverMsg = (res.data && res.data.message) || 'No pude confirmar la cita.';
                    pushMessage({
                        id: uid(),
                        role: 'assistant',
                        kind: 'fix_blocker',
                        text: serverMsg,
                        payload: { blocker: 'Fase: ' + stage },
                        ts: Date.now()
                    });
                }
            })
            .catch(function (err) {
                pushMessage(buildNetworkErrorMessage(err, 'No pude confirmar: error de red.'));
            })
            .then(function () {
                done();
            });
    }

    /**
     * Afirmación pura conservadora (paso 6.h). Falso negativo preferible a falso positivo.
     *
     * @param {string} text
     * @returns {boolean}
     */
    function isPureConfirmMessage(text) {
        const raw = String(text);
        const norm = raw.trim().toLowerCase().replace(/\s+/g, ' ');
        if (norm === '') {
            return false;
        }

        if (/,/.test(raw)) {
            return false;
        }
        if (/\bpero\b/i.test(raw)) {
            return false;
        }
        if (/\bcambia(d|mos|r)?\b/i.test(raw) || /\bcambiar\b/i.test(raw)) {
            return false;
        }
        if (/\bmejor\b/i.test(raw)) {
            return false;
        }
        if (/\bexcepto\b/i.test(raw)) {
            return false;
        }
        if (/\bmañana\b/i.test(raw) || /\bhoy\b/i.test(raw) || /\bayer\b/i.test(raw)) {
            return false;
        }
        if (/\bpasado\b/i.test(raw)) {
            return false;
        }
        if (/\d{1,2}:\d{2}/.test(raw)) {
            return false;
        }
        if (/\ba las\s+\d/i.test(raw)) {
            return false;
        }
        if (/\d{1,2}\/\d{1,2}/.test(raw)) {
            return false;
        }
        if (/\b(?:am|pm)\b/i.test(raw)) {
            return false;
        }

        const phrases = [
            'sí',
            'si',
            'confirmado',
            'confirmar',
            'ok',
            'vale',
            'dale',
            'listo',
            'correcto',
            'de acuerdo'
        ];
        return phrases.indexOf(norm) !== -1;
    }

    /**
     * Handles a click on a "Confirmar cita" button. Reads the last
     * `draft_state.draft`, validates required IDs, POSTs to
     * `aa_ai_confirm_booking`, then pushes a result message and
     * dispatches `aa-assignment-created` so the calendar timeline
     * recargue (calendar-module.js:275).
     *
     * The clicked button is disabled in-place for visual feedback
     * during the in-flight request. Note: `pushMessage` calls
     * `render()` which replaces the history's innerHTML, so the
     * original button DOM node is destroyed once the request resolves.
     * That's intentional — a stale CTA from the previous turn should
     * not stay clickable after a new assistant message arrives.
     */
    function confirmDraft(btn) {
        if (state.isConfirmBookingRequest) {
            return;
        }

        const prep = validateDraftStateForConfirmBooking();
        if (!prep.ok) {
            if (prep.reason === 'no_draft') {
                pushMessage({
                    id: uid(),
                    role: 'assistant',
                    kind: 'fix_blocker',
                    text: 'No hay borrador listo para confirmar.',
                    payload: { blocker: 'Borrador no disponible' },
                    ts: Date.now()
                });
            } else {
                pushMessage({
                    id: uid(),
                    role: 'assistant',
                    kind: 'fix_blocker',
                    text: 'Faltan datos en el borrador para confirmar.',
                    payload: { blocker: 'Campos faltantes: ' + (prep.missing || []).join(', ') },
                    ts: Date.now()
                });
            }
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Confirmando…';

        state.isConfirmBookingRequest = true;
        updateSendDisabled();
        runConfirmBookingAjax(prep.body, function () {
            state.isConfirmBookingRequest = false;
            updateSendDisabled();
        });
    }

    // ============================================================
    // Persistence (sessionStorage)
    // ============================================================

    /**
     * Serializes the persistable subset of `state` into sessionStorage.
     * Wrapped in try/catch: if storage is full, blocked by privacy
     * settings, or otherwise unavailable, the UX continues unaffected
     * (in-memory state is the source of truth for the live session).
     *
     * Only `messages`, `lastParsedInput` and `lastDraftState` are
     * persisted. `isOpen`, `isTyping`, `lastUserChatSendAt` and
     * `isConfirmBookingRequest` are intentionally excluded:
     *   - `isOpen` should always start `false` after a reload (the
     *     panel re-opening on its own would be invasive).
     *   - `isTyping` is meaningless across reloads (no in-flight
     *     fetch can survive a page navigation).
     *   - `isConfirmBookingRequest` is in-memory only.
     */
    function persistState() {
        try {
            const payload = JSON.stringify({
                messages:        state.messages,
                lastParsedInput: state.lastParsedInput,
                lastDraftState:  state.lastDraftState
            });
            window.sessionStorage.setItem(AA_AI_CHAT_STORAGE_KEY, payload);
        } catch (_e) {
            // Silent: persistence failure must never break the UI.
        }
    }

    /**
     * Reads sessionStorage and rehydrates the persistable subset of
     * `state`. Validates structure and message kinds against a
     * whitelist; any mismatch (corrupt JSON, unknown kind, wrong
     * shape, leftover from a previous schema version) purges the
     * key and starts fresh. `isOpen` and `isTyping` from storage
     * are always ignored.
     */
    function hydrateState() {
        let raw;
        try {
            raw = window.sessionStorage.getItem(AA_AI_CHAT_STORAGE_KEY);
        } catch (_e) {
            return;
        }
        if (!raw) return;

        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch (_e) {
            clearStorage();
            return;
        }

        if (!parsed || typeof parsed !== 'object') {
            clearStorage();
            return;
        }

        const messages = parsed.messages;
        const lastParsedInput = parsed.lastParsedInput;
        const lastDraftState = parsed.lastDraftState;

        if (!Array.isArray(messages)) {
            clearStorage();
            return;
        }
        if (lastParsedInput !== null && (typeof lastParsedInput !== 'object' || Array.isArray(lastParsedInput))) {
            clearStorage();
            return;
        }
        if (lastDraftState !== null && (typeof lastDraftState !== 'object' || Array.isArray(lastDraftState))) {
            clearStorage();
            return;
        }

        for (let i = 0; i < messages.length; i++) {
            const m = messages[i];
            if (!m || typeof m !== 'object') {
                clearStorage();
                return;
            }
            if (m.role !== 'user' && m.role !== 'assistant') {
                clearStorage();
                return;
            }
            if (KNOWN_MESSAGE_KINDS.indexOf(m.kind) === -1) {
                clearStorage();
                return;
            }
        }

        state.messages = messages;
        state.lastParsedInput = lastParsedInput == null ? null : lastParsedInput;
        state.lastDraftState = lastDraftState == null ? null : lastDraftState;
    }

    function clearStorage() {
        try {
            window.sessionStorage.removeItem(AA_AI_CHAT_STORAGE_KEY);
        } catch (_e) {
            // Silent.
        }
    }

    /**
     * Wipes the conversation: messages, parsed snapshot, draft state,
     * and the persistent copy. Used by:
     *   - The header "Nueva conversación" button (with `confirm:true`).
     *
     * If `options.confirm === true` and there is something to lose,
     * shows a `window.confirm` first and aborts on cancel. With an
     * empty history the prompt is suppressed (nothing to confirm).
     *
     * Returns focus to the textarea if the panel is open.
     */
    function resetConversation(options) {
        const opts = options || {};
        const askConfirmation = opts.confirm !== false;

        if (askConfirmation && state.messages.length > 0) {
            const ok = window.confirm('¿Iniciar una nueva conversación? Se perderá el historial actual.');
            if (!ok) return;
        }

        state.messages = [];
        state.lastParsedInput = null;
        state.lastDraftState = null;
        state.lastUserChatSendAt = null;
        state.isConfirmBookingRequest = false;
        clearStorage();
        render();

        if (state.isOpen && dom.input) {
            dom.input.focus();
        }
        updateSendDisabled();
    }

    // ============================================================
    // AJAX helper
    // ============================================================

    /**
     * POST to admin-ajax.php for either the chat or the confirm action.
     *
     * Returns a Promise that resolves to `{ ok, data, httpStatus }`.
     * Logical (non-2xx but valid JSON) errors are surfaced via `ok:false`.
     * Pure network failures (fetch reject) and missing nonces throw
     * with `err.code = 'AA_NETWORK_ERROR'` or `'AA_NONCE_MISSING'`.
     * If the server returns non-JSON (typically an HTML login page on
     * expired session), throws with `err.code = 'AA_BAD_JSON'`.
     */
    function ajaxPost(action, extraBody) {
        return new Promise(function (resolve, reject) {
            if (!window.AA_AI_CHAT) {
                const e = new Error('AA_NONCE_MISSING');
                e.code = 'AA_NONCE_MISSING';
                reject(e);
                return;
            }
            const nonce = action === 'aa_ai_confirm_booking'
                ? window.AA_AI_CHAT.confirm_nonce
                : window.AA_AI_CHAT.nonce;
            if (!nonce) {
                const e = new Error('AA_NONCE_MISSING');
                e.code = 'AA_NONCE_MISSING';
                reject(e);
                return;
            }

            const params = new URLSearchParams();
            params.set('action', action);
            params.set('nonce', nonce);
            const body = extraBody || {};
            Object.keys(body).forEach(function (k) {
                const v = body[k];
                if (v == null) return;
                params.set(k, String(v));
            });

            const url = (typeof window.ajaxurl === 'string' && window.ajaxurl)
                ? window.ajaxurl
                : '/wp-admin/admin-ajax.php';

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                body: params
            }).then(function (response) {
                return response.json().then(function (data) {
                    resolve({
                        ok: !!(data && data.success),
                        data: (data && data.data) ? data.data : {},
                        httpStatus: response.status
                    });
                }).catch(function () {
                    const e = new Error('AA_BAD_JSON');
                    e.code = 'AA_BAD_JSON';
                    reject(e);
                });
            }).catch(function () {
                const e = new Error('AA_NETWORK_ERROR');
                e.code = 'AA_NETWORK_ERROR';
                reject(e);
            });
        });
    }

    /**
     * Builds a `fix_blocker` Message from a network-layer error.
     * `AA_BAD_JSON` typically means the server returned HTML (session
     * expired, redirect to wp-login). Everything else is treated as a
     * generic network/connectivity failure with `defaultText`.
     */
    function buildNetworkErrorMessage(err, defaultText) {
        const isAuth = err && err.code === 'AA_BAD_JSON';
        return {
            id: uid(),
            role: 'assistant',
            kind: 'fix_blocker',
            text: isAuth ? 'Sesión expirada, recarga la página.' : defaultText,
            payload: { blocker: 'Error de red' },
            ts: Date.now()
        };
    }

    // ============================================================
    // Render
    // ============================================================
    function render() {
        if (!dom.history) return;

        const parts = state.messages.map(renderMessage);
        if (state.isTyping) parts.push(renderTypingIndicator());

        dom.history.innerHTML = parts.join('');

        // Autoscroll to bottom on any render.
        dom.history.scrollTop = dom.history.scrollHeight;
    }

    function renderMessage(msg) {
        if (msg.role === 'user') {
            return renderUserBubble(msg);
        }
        switch (msg.kind) {
            case 'confirm_cta':        return renderAssistantConfirmCta(msg);
            case 'ambiguous_choices':  return renderAssistantAmbiguousChoices(msg);
            case 'highlights':         return renderAssistantHighlights(msg);
            case 'fix_blocker':        return renderAssistantFixBlocker(msg);
            case 'text':
            default:                   return renderAssistantText(msg);
        }
    }

    function renderUserBubble(msg) {
        return (
            '<div class="flex justify-end">' +
                '<div class="max-w-[85%] px-3 py-2 text-sm bg-indigo-600 text-white rounded-2xl rounded-br-sm whitespace-pre-wrap break-words shadow-sm">' +
                    escapeHtml(msg.text) +
                '</div>' +
            '</div>'
        );
    }

    // Shared wrapper for assistant-side content (left column + bubble).
    function wrapAssistant(innerHtml) {
        return (
            '<div class="flex justify-start">' +
                '<div class="max-w-[85%] space-y-2">' +
                    innerHtml +
                '</div>' +
            '</div>'
        );
    }

    function assistantBubble(text, extraClasses) {
        const cls = extraClasses || 'bg-slate-100 text-slate-800';
        return (
            '<div class="px-3 py-2 text-sm ' + cls + ' rounded-2xl rounded-bl-sm whitespace-pre-wrap break-words shadow-sm">' +
                escapeHtml(text) +
            '</div>'
        );
    }

    function renderAssistantText(msg) {
        return wrapAssistant(assistantBubble(msg.text));
    }

    function renderAssistantConfirmCta(msg) {
        const p = msg.payload || {};
        const rows = Array.isArray(p.draftEcho) ? p.draftEcho : [];
        const used = !!msg.confirmDisabled;
        const confirmLabelActive = p.confirmLabel || 'Confirmar cita';
        const confirmLabelShown = used ? 'Cita confirmada' : confirmLabelActive;

        const rowsHtml = rows.map(function (row) {
            return (
                '<div class="flex justify-between gap-3 text-xs">' +
                    '<span class="text-slate-500">' + escapeHtml(row.label) + '</span>' +
                    '<span class="text-slate-800 font-medium text-right">' + escapeHtml(row.value) + '</span>' +
                '</div>'
            );
        }).join('');

        const miniCard = (
            '<div class="px-3 py-2 bg-white border border-slate-200 rounded-xl space-y-1">' +
                rowsHtml +
            '</div>'
        );

        const btnActiveCls =
            'w-full px-3 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/40';
        const btnUsedCls =
            'w-full px-3 py-2 text-sm font-semibold text-slate-500 bg-slate-200 border border-slate-300 rounded-xl cursor-not-allowed opacity-95 focus:outline-none focus:ring-2 focus:ring-slate-400/30';

        const btn = (
            '<button type="button" data-aa-ai-chat-confirm' +
                (used ? ' disabled aria-disabled="true"' : '') +
                ' class="' + (used ? btnUsedCls : btnActiveCls) + '">' +
                escapeHtml(confirmLabelShown) +
            '</button>'
        );

        return wrapAssistant(
            assistantBubble(msg.text) +
            miniCard +
            btn
        );
    }

    function renderAssistantAmbiguousChoices(msg) {
        const p = msg.payload || {};
        const choices = Array.isArray(p.choices) ? p.choices.slice(0, 4) : [];

        const chipsHtml = choices.map(function (c) {
            return (
                '<button type="button" data-aa-ai-chat-chip data-label="' + escapeHtml(c.label) + '" ' +
                    'class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-white border border-indigo-200 hover:bg-indigo-50 hover:border-indigo-300 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/40">' +
                    escapeHtml(c.label) +
                '</button>'
            );
        }).join('');

        const chipsRow = (
            '<div class="flex flex-wrap gap-2">' + chipsHtml + '</div>'
        );

        return wrapAssistant(
            assistantBubble(msg.text) +
            chipsRow
        );
    }

    function renderAssistantHighlights(msg) {
        const p = msg.payload || {};
        const rows = Array.isArray(p.highlights) ? p.highlights : [];

        const rowsHtml = rows.map(function (row) {
            return (
                '<div class="flex justify-between gap-3 text-xs">' +
                    '<span class="text-slate-500">' + escapeHtml(row.label) + '</span>' +
                    '<span class="text-slate-800 font-medium text-right">' + escapeHtml(row.value) + '</span>' +
                '</div>'
            );
        }).join('');

        const miniCard = (
            '<div class="px-3 py-2 bg-white/80 border border-slate-200 rounded-xl space-y-1">' +
                rowsHtml +
            '</div>'
        );

        return wrapAssistant(
            assistantBubble(msg.text) +
            miniCard
        );
    }

    function renderAssistantFixBlocker(msg) {
        const p = msg.payload || {};
        const blocker = p.blocker ? escapeHtml(p.blocker) : '';
        const warningIcon = (
            '<svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>' +
            '</svg>'
        );
        const bubble = (
            '<div class="flex items-start gap-2 px-3 py-2 text-sm border border-amber-200 bg-amber-50 text-amber-900 rounded-2xl rounded-bl-sm whitespace-pre-wrap break-words shadow-sm">' +
                warningIcon +
                '<div class="space-y-0.5">' +
                    '<div>' + escapeHtml(msg.text) + '</div>' +
                    (blocker ? '<div class="text-xs font-medium text-amber-700">' + blocker + '</div>' : '') +
                '</div>' +
            '</div>'
        );
        return wrapAssistant(bubble);
    }

    function renderTypingIndicator() {
        return (
            '<div class="flex justify-start" aria-label="Asistente escribiendo">' +
                '<div class="px-3 py-2 bg-slate-100 rounded-2xl rounded-bl-sm shadow-sm">' +
                    '<span class="aa-ai-chat-typing-dot"></span>' +
                    '<span class="aa-ai-chat-typing-dot ml-1"></span>' +
                    '<span class="aa-ai-chat-typing-dot ml-1"></span>' +
                '</div>' +
            '</div>'
        );
    }

    // ============================================================
    // Boot
    // ============================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
