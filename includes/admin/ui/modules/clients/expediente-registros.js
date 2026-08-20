/**
 * Expediente registros — chronology list + create/edit modal (MC2/MC3/MC4b/MC4c/MC5b).
 *
 * Loaded only on view=expediente. Create and edit share one modal form.
 * Adjunto opcional: texto primero (create/update), luego aa_attach_expediente_registro.
 * MC4c: miniatura privada lazy por tarjeta vía sign-read; caché solo en memoria.
 * MC5b: minigalería por registro (panel expandido) + visor sobre AAAdmin.modal.
 * La selección de imagen es estado exclusivamente de UI; adjuntos[] manda.
 */

(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var MAX_IMAGE_EDGE = 2048;
    var MAX_IMAGE_BYTES = 1048576;
    var HEIC_UNSUPPORTED_MESSAGE =
        'Este formato no se puede procesar aquí. Guarda o exporta la foto como JPG e inténtalo de nuevo.';
    var PARTIAL_ATTACH_MESSAGE = 'Registro guardado. No se pudo subir la imagen.';
    var THUMB_ERROR_MESSAGE = 'No se pudo cargar la imagen.';

    var UNKNOWN_ACCOUNT = Object.freeze({
        commercialState: 'unknown',
        upgradeAvailable: false
    });

    var PAST_DUE_SUCCESS_FALLBACK =
        'Tus beneficios Pro están suspendidos por un pago pendiente. Actualiza tu pago para recuperarlos.';
    var IMAGE_SAVED_MESSAGE = 'Imagen añadida.';
    var STORAGE_NOT_INCLUDED_TOAST_MESSAGE =
        'La imagen no se guardó: tu plan no incluye almacenamiento de imágenes.';
    var STORAGE_QUOTA_FREEMIUM_MESSAGE =
        'La imagen no se guardó porque agotaste el almacenamiento de tu plan Freemium.';
    var STORAGE_QUOTA_PAST_DUE_MESSAGE =
        'La imagen no se guardó porque tus beneficios Pro están suspendidos y se aplica el límite Freemium.';
    var STORAGE_QUOTA_PRO_MESSAGE =
        'La imagen no se guardó porque alcanzaste el límite de almacenamiento de tu plan. Elimina imágenes que ya no necesites.';
    var STORAGE_QUOTA_GENERIC_MESSAGE =
        'La imagen no se guardó porque alcanzaste el límite de almacenamiento de tu plan.';

    var TOAST_DURATION_MS = {
        success: 3500,
        warning: 5000
    };

    var ACCOUNT_BILLING_URL_FALLBACK =
        'admin-post.php?action=aa_iframe_content&module=account#aa-account-billing-button';
    var ACCOUNT_UPGRADE_URL_FALLBACK =
        'admin-post.php?action=aa_iframe_content&module=account#aa-account-upgrade-section';
    var SETTINGS_FREEMIUM_URL_FALLBACK =
        'admin-post.php?action=aa_iframe_content&module=settings&setup_focus=google_calendar#aa-google-calendar-root';

    /** Promesa de estado comercial iniciada por Expedientes (nunca rechaza). */
    var accountPromise = null;

    /**
     * Clasifica el payload de account_status ya obtenido.
     * Puro: sin fetch ni lectura global.
     *
     * @param {object|null|undefined} status
     * @returns {{ commercialState: string, upgradeAvailable: boolean }}
     */
    function resolveAccount(status) {
        if (!status || typeof status !== 'object') {
            return UNKNOWN_ACCOUNT;
        }

        var upgradeAvailable = status.upgrade_to_pro_available === true;

        if (status.sync_pending === true) {
            return { commercialState: 'unknown', upgradeAvailable: upgradeAvailable };
        }

        if (status.payment_action_required === true) {
            if (status.plan_tier === 'pro' && status.effective_access_tier === 'freemium') {
                return { commercialState: 'pro_past_due', upgradeAvailable: upgradeAvailable };
            }
            return { commercialState: 'unknown', upgradeAvailable: upgradeAvailable };
        }

        if (status.effective_access_tier === 'pro') {
            return { commercialState: 'pro_active', upgradeAvailable: upgradeAvailable };
        }
        if (status.effective_access_tier === 'free') {
            return { commercialState: 'free', upgradeAvailable: upgradeAvailable };
        }
        if (status.plan_tier === 'freemium' && status.effective_access_tier === 'freemium') {
            return { commercialState: 'freemium', upgradeAvailable: upgradeAvailable };
        }

        return { commercialState: 'unknown', upgradeAvailable: upgradeAvailable };
    }

    /**
     * Inicia (o reutiliza) la obtención memorizada de account-status.
     * Nunca rechaza: degrada a UNKNOWN_ACCOUNT.
     *
     * @returns {Promise<{ commercialState: string, upgradeAvailable: boolean }>}
     */
    function primeAccountStatus() {
        if (accountPromise) {
            return accountPromise;
        }

        var svc = typeof window !== 'undefined' ? window.AccountStatusService : null;
        if (!svc || typeof svc.fetchStatus !== 'function') {
            accountPromise = Promise.resolve(UNKNOWN_ACCOUNT);
            return accountPromise;
        }

        accountPromise = svc.fetchStatus()
            .then(function (payload) {
                return resolveAccount(payload && payload.account_status);
            })
            .catch(function () {
                return UNKNOWN_ACCOUNT;
            });

        return accountPromise;
    }

    /**
     * @param {string} recordOutcome
     * @returns {string}
     */
    function recordToastTitle(recordOutcome) {
        return recordOutcome === 'updated' ? 'Registro actualizado' : 'Registro guardado';
    }

    /**
     * @param {{ commercialState?: string, upgradeAvailable?: boolean }|null|undefined} account
     * @returns {{ commercialState: string, upgradeAvailable: boolean }}
     */
    function normalizeAccountInput(account) {
        if (!account || typeof account !== 'object') {
            return UNKNOWN_ACCOUNT;
        }
        var state = account.commercialState;
        if (
            state !== 'free' &&
            state !== 'freemium' &&
            state !== 'pro_active' &&
            state !== 'pro_past_due' &&
            state !== 'unknown'
        ) {
            return UNKNOWN_ACCOUNT;
        }
        return {
            commercialState: state,
            upgradeAvailable: account.upgradeAvailable === true
        };
    }

    /**
     * Mapper puro: resultado operativo + estado comercial → modelo de toast.
     * Acciones usan `target` simbólico (la orquestación resuelve URL).
     *
     * @param {{
     *   recordOutcome: 'created'|'updated',
     *   imageOutcome: 'none'|'saved'|'failed',
     *   failureCode: string,
     *   account: { commercialState: string, upgradeAvailable: boolean }|null
     * }} input
     * @returns {object}
     */
    function buildSaveNotification(input) {
        var opts = input || {};
        var recordOutcome = opts.recordOutcome === 'updated' ? 'updated' : 'created';
        var imageOutcome = opts.imageOutcome === 'saved' || opts.imageOutcome === 'failed'
            ? opts.imageOutcome
            : 'none';
        var failureCode = opts.failureCode == null ? '' : String(opts.failureCode);
        var account = normalizeAccountInput(opts.account);
        var title = recordToastTitle(recordOutcome);

        if (imageOutcome === 'none') {
            return {
                severity: 'success',
                title: title,
                message: '',
                details: [],
                fallback: null,
                durationMs: TOAST_DURATION_MS.success,
                blocking: false,
                actions: [],
                notices: []
            };
        }

        if (imageOutcome === 'saved') {
            var savedNotification = {
                severity: 'success',
                title: title,
                message: IMAGE_SAVED_MESSAGE,
                details: [],
                fallback: null,
                durationMs: TOAST_DURATION_MS.success,
                blocking: false,
                actions: [],
                notices: []
            };
            if (account.commercialState === 'pro_past_due') {
                savedNotification.fallback = PAST_DUE_SUCCESS_FALLBACK;
                savedNotification.actions = [
                    { label: 'Actualizar pago', target: 'account_billing' }
                ];
            }
            return savedNotification;
        }

        // imageOutcome === 'failed'
        if (failureCode === 'storage_not_included') {
            var notIncluded = {
                severity: 'warning',
                title: title,
                message: STORAGE_NOT_INCLUDED_TOAST_MESSAGE,
                details: [],
                fallback: null,
                durationMs: TOAST_DURATION_MS.warning,
                blocking: false,
                actions: [],
                notices: []
            };
            if (account.commercialState === 'free') {
                notIncluded.actions = [
                    { label: 'Suscribirme', target: 'settings_freemium' }
                ];
            }
            return notIncluded;
        }

        if (failureCode === 'storage_quota_exceeded') {
            var quotaNotification = {
                severity: 'warning',
                title: title,
                message: STORAGE_QUOTA_GENERIC_MESSAGE,
                details: [],
                fallback: null,
                durationMs: TOAST_DURATION_MS.warning,
                blocking: false,
                actions: [],
                notices: []
            };

            if (account.commercialState === 'freemium') {
                quotaNotification.message = STORAGE_QUOTA_FREEMIUM_MESSAGE;
                if (account.upgradeAvailable) {
                    quotaNotification.actions = [
                        { label: 'Adquirir Pro', target: 'account_upgrade' }
                    ];
                }
            } else if (account.commercialState === 'pro_past_due') {
                quotaNotification.message = STORAGE_QUOTA_PAST_DUE_MESSAGE;
                quotaNotification.actions = [
                    { label: 'Actualizar pago', target: 'account_billing' }
                ];
            } else if (account.commercialState === 'pro_active') {
                quotaNotification.message = STORAGE_QUOTA_PRO_MESSAGE;
            }

            return quotaNotification;
        }

        // Código técnico o desconocido: la orquestación no debería llegar aquí.
        return {
            severity: 'warning',
            title: title,
            message: PARTIAL_ATTACH_MESSAGE,
            details: [],
            fallback: null,
            durationMs: TOAST_DURATION_MS.warning,
            blocking: false,
            actions: [],
            notices: []
        };
    }

    /**
     * Mensaje inline para fallos técnicos reintentables (modal abierto).
     * Los rechazos comerciales ya no usan este camino.
     *
     * @param {string} code
     * @returns {string}
     */
    function messageForAttachFailure(code) {
        void code;
        return PARTIAL_ATTACH_MESSAGE;
    }

    /**
     * @param {string} moduleName
     * @param {string} hash
     * @param {Object<string, string>} [extraParams]
     * @param {string} fallback
     * @returns {string}
     */
    function buildAdminModuleUrl(moduleName, hash, extraParams, fallback) {
        if (typeof window !== 'undefined' && window.location && window.location.href) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('action', 'aa_iframe_content');
                url.searchParams.set('module', moduleName);
                if (extraParams && typeof extraParams === 'object') {
                    Object.keys(extraParams).forEach(function (key) {
                        url.searchParams.set(key, extraParams[key]);
                    });
                }
                url.hash = hash || '';
                return url.toString();
            } catch (_err) {
                // fall through
            }
        }
        return fallback;
    }

    /**
     * @param {string} target
     * @returns {string}
     */
    function urlForToastTarget(target) {
        if (target === 'account_billing') {
            return buildAdminModuleUrl(
                'account',
                '#aa-account-billing-button',
                null,
                ACCOUNT_BILLING_URL_FALLBACK
            );
        }
        if (target === 'account_upgrade') {
            return buildAdminModuleUrl(
                'account',
                '#aa-account-upgrade-section',
                null,
                ACCOUNT_UPGRADE_URL_FALLBACK
            );
        }
        if (target === 'settings_freemium') {
            return buildAdminModuleUrl(
                'settings',
                '#aa-google-calendar-root',
                { setup_focus: 'google_calendar' },
                SETTINGS_FREEMIUM_URL_FALLBACK
            );
        }
        return '';
    }

    /**
     * Traduce targets simbólicos a URLs y muestra un único toast.
     *
     * @param {object} notification
     */
    function emitToast(notification) {
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (!toastApi || typeof toastApi.show !== 'function' || !notification) {
            return;
        }

        var actions = [];
        var rawActions = Array.isArray(notification.actions) ? notification.actions : [];
        for (var i = 0; i < rawActions.length; i++) {
            var action = rawActions[i];
            if (!action || !action.label || !action.target) {
                continue;
            }
            var url = urlForToastTarget(String(action.target));
            if (!url) {
                continue;
            }
            actions.push({ label: String(action.label), url: url });
        }

        toastApi.show(
            {
                severity: notification.severity,
                title: notification.title,
                message: notification.message || '',
                details: Array.isArray(notification.details) ? notification.details : [],
                fallback: notification.fallback || null,
                durationMs: notification.durationMs,
                blocking: false,
                actions: actions,
                notices: []
            },
            { autoDismiss: actions.length === 0 }
        );
    }
    var DELETE_IMAGE_CONFIRM =
        '¿Eliminar esta imagen? Esta acción no se puede deshacer. El registro se conservará.';
    var DELETE_IMAGE_ERROR_MESSAGE = 'No se pudo eliminar la imagen.';
    var DELETE_RECORD_CONFIRM_EMPTY =
        '¿Eliminar este registro? Esta acción no se puede deshacer.';
    var DELETE_RECORD_ERROR_MESSAGE = 'No se pudo eliminar el registro.';
    // Margen antes de la expiración real (600s backend) para no usar URLs al límite.
    var THUMB_TTL_SAFETY_SECONDS = 60;

    var state = {
        clientId: 0,
        /**
         * Identidad opaca de montaje/cache (C1a). Nunca se envía en requests.
         * Legacy: se deriva de clientId cuando no se pasa scopeKey.
         */
        scopeKey: '',
        recordsRoot: null,
        actionsRoot: null,
        records: [],
        loading: false,
        /**
         * Transporte AJAX del montaje vigente (ciclo A).
         * null = no modalidad transport; puede usarse globals si tampoco hay ports.
         */
        transport: null,
        /**
         * Ports ejecutables del montaje vigente (ciclo B1).
         * null = no modalidad ports → transport o globals.
         * objeto = modalidad exclusiva (sin mezcla con transport/globals).
         */
        ports: null,
        /**
         * Capabilities explícitas (C1a). null = legacy (todas habilitadas, sin
         * validación anticipada de ports). Solo aplica en modalidad ports.
         */
        capabilities: null
    };

    var PORT_KEYS = [
        'list',
        'create',
        'update',
        'deleteRegistro',
        'attach',
        'signRead',
        'deleteAdjunto'
    ];

    var CAPABILITY_KEYS = [
        'createRegistro',
        'updateRegistro',
        'deleteRegistro',
        'attach',
        'signRead',
        'deleteAdjunto'
    ];

    var CAPABILITY_TO_PORT = {
        createRegistro: 'create',
        updateRegistro: 'update',
        deleteRegistro: 'deleteRegistro',
        attach: 'attach',
        signRead: 'signRead',
        deleteAdjunto: 'deleteAdjunto'
    };

    var TRANSPORT_ACTION_KEYS = [
        'listRegistros',
        'createRegistro',
        'updateRegistro',
        'deleteRegistro',
        'attachRegistro',
        'signAdjuntoRead',
        'deleteAdjunto'
    ];

    var LEGACY_ACTION_DEFAULTS = {
        listRegistros: 'aa_list_expediente_registros',
        createRegistro: 'aa_create_expediente_registro',
        updateRegistro: 'aa_update_expediente_registro',
        deleteRegistro: 'aa_delete_expediente_registro',
        attachRegistro: 'aa_attach_expediente_registro',
        signAdjuntoRead: 'aa_sign_expediente_adjunto_read',
        deleteAdjunto: 'aa_delete_expediente_adjunto'
    };

    var registroOptionsUiBound = false;
    var openRegistroOptionsId = '';

    /**
     * Controlador de miniaturas (MC4c/MC5b). Todo vive solo en memoria.
     * Claves de cache/requests: "<scopeKey>:<record_id>:<adjunto.id>:<variant>".
     * selectedByRecord (MC5b): recordId → attachment_id seleccionado (solo UI).
     */
    var thumbs = {
        viewEpoch: 0,
        observer: null,
        thumbnailCache: {},
        thumbnailRequests: {},
        resignedIdentities: {},
        selectedByRecord: {},
        deletingKeys: {},
        deletingRecords: {}
    };

    // Solo para abortar el watcher de cierre del modal (foco / limpieza).
    var modalCloseAbort = null;

    function isPortsMode() {
        return state.ports !== null;
    }

    function isInjectedTransportMode() {
        return !isPortsMode() && state.transport !== null;
    }

    /**
     * Legacy (capabilities null) ⇒ todas habilitadas. Explícitas ⇒ booleano estricto.
     * @param {string} name
     * @returns {boolean}
     */
    function isCapEnabled(name) {
        if (state.capabilities === null) {
            return true;
        }
        return state.capabilities[name] === true;
    }

    /**
     * @param {*} raw
     * @returns {{ok:true, capabilities:Object<string,boolean>}|{ok:false, reason:string}}
     */
    function normalizeCapabilities(raw) {
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
            return { ok: false, reason: 'capabilities_type' };
        }

        var keys = Object.keys(raw);
        if (keys.length !== CAPABILITY_KEYS.length) {
            return { ok: false, reason: 'capabilities_keys' };
        }

        var i;
        for (i = 0; i < keys.length; i++) {
            if (CAPABILITY_KEYS.indexOf(keys[i]) === -1) {
                return { ok: false, reason: 'capabilities_unknown' };
            }
        }

        var out = {};
        for (i = 0; i < CAPABILITY_KEYS.length; i++) {
            var key = CAPABILITY_KEYS[i];
            if (!Object.prototype.hasOwnProperty.call(raw, key)) {
                return { ok: false, reason: 'capabilities_missing' };
            }
            if (typeof raw[key] !== 'boolean') {
                return { ok: false, reason: 'capabilities_value' };
            }
            out[key] = raw[key];
        }

        if (out.attach === true && out.createRegistro !== true && out.updateRegistro !== true) {
            return { ok: false, reason: 'capabilities_attach_dependency' };
        }
        if (out.deleteAdjunto === true && out.signRead !== true) {
            return { ok: false, reason: 'capabilities_deleteAdjunto_dependency' };
        }

        return { ok: true, capabilities: out };
    }

    /**
     * Valida options antes de destroy/DOM. No muta state.
     * @param {*} options
     * @returns {{ok:true, config:object}|{ok:false, reason:string}}
     */
    function resolveInitConfig(options) {
        if (!options || !options.recordsRoot) {
            return { ok: false, reason: 'records_root' };
        }

        var hasCapabilities = Object.prototype.hasOwnProperty.call(options, 'capabilities');
        var hasPorts = Object.prototype.hasOwnProperty.call(options, 'ports');
        var hasTransport = Object.prototype.hasOwnProperty.call(options, 'transport');

        if (hasCapabilities && !hasPorts) {
            return { ok: false, reason: 'capabilities_without_ports' };
        }

        var capabilities = null;
        if (hasCapabilities) {
            var capResult = normalizeCapabilities(options.capabilities);
            if (!capResult.ok) {
                return { ok: false, reason: capResult.reason };
            }
            capabilities = capResult.capabilities;
        }

        var ports = null;
        var transport = null;

        if (hasPorts) {
            ports = normalizePorts(options.ports);
            if (!ports) {
                return { ok: false, reason: 'ports_invalid' };
            }
            if (capabilities) {
                if (typeof ports.list !== 'function') {
                    return { ok: false, reason: 'list_required' };
                }
                for (var i = 0; i < CAPABILITY_KEYS.length; i++) {
                    var capName = CAPABILITY_KEYS[i];
                    if (capabilities[capName] !== true) {
                        continue;
                    }
                    var portName = CAPABILITY_TO_PORT[capName];
                    if (typeof ports[portName] !== 'function') {
                        return { ok: false, reason: 'port_required:' + portName };
                    }
                }
            }
        } else if (hasTransport) {
            transport = normalizeTransport(options.transport);
            if (!transport) {
                return { ok: false, reason: 'transport_invalid' };
            }
        }

        var scopeKey = '';
        if (Object.prototype.hasOwnProperty.call(options, 'scopeKey')) {
            if (typeof options.scopeKey !== 'string') {
                return { ok: false, reason: 'scope_key_type' };
            }
            scopeKey = String(options.scopeKey).trim();
            if (scopeKey === '') {
                return { ok: false, reason: 'scope_key_empty' };
            }
        }

        var clientId = parseInt(options.clientId, 10);
        if (!(clientId > 0)) {
            clientId = 0;
        }

        if (scopeKey === '') {
            if (clientId > 0) {
                scopeKey = String(clientId);
            } else {
                return { ok: false, reason: 'identity_missing' };
            }
        }

        // Transport/globals siempre necesitan clientId para payloads legacy.
        if (!ports && !(clientId > 0)) {
            return { ok: false, reason: 'client_id_invalid' };
        }

        return {
            ok: true,
            config: {
                clientId: clientId,
                scopeKey: scopeKey,
                recordsRoot: options.recordsRoot,
                actionsRoot: options.actionsRoot || null,
                ports: ports,
                transport: transport,
                capabilities: capabilities
            }
        };
    }

    /**
     * Copia allowlist de ports (solo funciones). Objeto vacío = modalidad ports
     * con todas las ops fallando de forma controlada al usarse (sin híbrido).
     * @param {*} raw
     * @returns {Object<string, Function>|null}
     */
    function normalizePorts(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }
        var ports = {};
        for (var i = 0; i < PORT_KEYS.length; i++) {
            var key = PORT_KEYS[i];
            if (typeof raw[key] === 'function') {
                ports[key] = raw[key];
            }
        }
        return ports;
    }

    /**
     * Invoca un port o falla controlado sin tocar transport/globals.
     * @param {string} name
     * @param {...*} args
     * @returns {Promise<{httpStatus:number, result:object}>}
     */
    function callPort(name) {
        if (state.capabilities) {
            var capName = null;
            for (var i = 0; i < CAPABILITY_KEYS.length; i++) {
                if (CAPABILITY_TO_PORT[CAPABILITY_KEYS[i]] === name) {
                    capName = CAPABILITY_KEYS[i];
                    break;
                }
            }
            if (capName && state.capabilities[capName] !== true) {
                console.error('[ExpedienteRegistros] port deshabilitado por capabilities:', name);
                return Promise.resolve(transportIncompletePayload());
            }
        }

        var fn = state.ports && state.ports[name];
        if (typeof fn !== 'function') {
            console.error('[ExpedienteRegistros] port ausente:', name);
            return Promise.resolve(transportIncompletePayload());
        }
        var args = Array.prototype.slice.call(arguments, 1);
        try {
            return Promise.resolve(fn.apply(null, args)).then(function (payload) {
                if (payload && typeof payload === 'object' && Object.prototype.hasOwnProperty.call(payload, 'result')) {
                    return payload;
                }
                console.error('[ExpedienteRegistros] port devolvió forma inesperada:', name);
                return transportIncompletePayload();
            }, function (err) {
                return Promise.reject(err);
            });
        } catch (err) {
            return Promise.reject(err);
        }
    }

    /**
     * Copia allowlist de transporte. No completa con globals.
     * @param {*} raw
     * @returns {{ajaxUrl:string, nonce:string, actions:Object<string,string>}|null}
     */
    function normalizeTransport(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }
        var ajaxUrl = typeof raw.ajaxUrl === 'string' ? String(raw.ajaxUrl).trim() : '';
        var nonce = typeof raw.nonce === 'string' ? raw.nonce : '';
        if (!ajaxUrl || !nonce) {
            return null;
        }
        if (!raw.actions || typeof raw.actions !== 'object') {
            return null;
        }
        var actions = {};
        for (var i = 0; i < TRANSPORT_ACTION_KEYS.length; i++) {
            var key = TRANSPORT_ACTION_KEYS[i];
            var value = raw.actions[key];
            if (typeof value === 'string' && value !== '') {
                actions[key] = value;
            }
        }
        return {
            ajaxUrl: ajaxUrl,
            nonce: nonce,
            actions: actions
        };
    }

    function getLegacyConfig() {
        return (typeof window !== 'undefined' && window.AA_CLIENTS_DATA)
            ? window.AA_CLIENTS_DATA
            : {};
    }

    function resolveAjaxUrl() {
        if (isInjectedTransportMode()) {
            return state.transport.ajaxUrl || '';
        }
        var data = getLegacyConfig();
        return data.ajaxUrl || (typeof window !== 'undefined' && window.ajaxurl) || '';
    }

    function resolveNonce() {
        if (isInjectedTransportMode()) {
            return state.transport.nonce || '';
        }
        var nonces = (typeof window !== 'undefined' && window.AA_CLIENTS_NONCES)
            ? window.AA_CLIENTS_NONCES
            : {};
        return nonces.expediente_registros || '';
    }

    /**
     * @param {string} key
     * @returns {string}
     */
    function resolveAction(key) {
        if (isInjectedTransportMode()) {
            var injected = state.transport.actions && state.transport.actions[key];
            return (typeof injected === 'string' && injected !== '') ? injected : '';
        }
        var data = getLegacyConfig();
        var fromGlobal = data.actions && data.actions[key];
        if (typeof fromGlobal === 'string' && fromGlobal !== '') {
            return fromGlobal;
        }
        return LEGACY_ACTION_DEFAULTS[key] || '';
    }

    /**
     * Falla controlada sin lanzar: no envía petición híbrida/incorrecta.
     * @param {string} action
     * @returns {boolean}
     */
    function canSendTransportRequest(action) {
        return !!(action && resolveAjaxUrl() && resolveNonce());
    }

    function transportIncompletePayload() {
        return {
            httpStatus: 0,
            result: {
                success: false,
                data: {
                    message: 'Transporte incompleto.',
                    code: 'transport_incomplete'
                }
            }
        };
    }

    var RECORDED_AT_MONTHS_ES = [
        'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
        'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'
    ];

    function formatRecordedAt(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        // MySQL datetime → D/MesAbrev/YYYY (sin hora; digitos tal cual vienen de BD)
        var m = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) {
            return value;
        }
        var monthIndex = parseInt(m[2], 10) - 1;
        var monthLabel = RECORDED_AT_MONTHS_ES[monthIndex];
        if (!monthLabel) {
            return value;
        }
        return parseInt(m[3], 10) + '/' + monthLabel + '/' + m[1];
    }

    /**
     * MySQL datetime → HTML time[datetime] without inventing timezone.
     * @param {string} value
     * @returns {string}
     */
    function toDatetimeAttr(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var m = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!m) {
            return '';
        }
        var seconds = m[6] || '00';
        return m[1] + '-' + m[2] + '-' + m[3] + 'T' + m[4] + ':' + m[5] + ':' + seconds;
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function renderStatusMessage(text, className) {
        clearNode(state.recordsRoot);
        var p = document.createElement('p');
        p.className = className || 'text-sm text-gray-500';
        p.textContent = text;
        state.recordsRoot.appendChild(p);
    }

    function renderEmpty() {
        renderStatusMessage('Aún no hay registros en este expediente', 'text-sm text-gray-500');
    }

    function renderError(message) {
        renderStatusMessage(message || 'No se pudieron cargar los registros.', 'text-sm text-red-600');
    }

    function findRecordById(recordId) {
        var id = parseInt(recordId, 10);
        if (!(id > 0)) {
            return null;
        }
        for (var i = 0; i < state.records.length; i++) {
            if (parseInt(state.records[i].id, 10) === id) {
                return state.records[i];
            }
        }
        return null;
    }

    function focusEditButtonById(recordId) {
        var id = parseInt(recordId, 10);
        if (!(id > 0) || !state.recordsRoot) {
            return;
        }
        var btn = state.recordsRoot.querySelector(
            '.aa-expediente-btn-editar[data-registro-id="' + id + '"]'
        );
        if (btn && typeof btn.focus === 'function') {
            try {
                btn.focus({ preventScroll: true });
            } catch (e) {
                btn.focus();
            }
        }
    }

    function focusElement(el) {
        if (!el || typeof el.focus !== 'function') {
            return;
        }
        if (typeof document !== 'undefined' && document.contains && !document.contains(el)) {
            return;
        }
        try {
            el.focus({ preventScroll: true });
        } catch (e) {
            el.focus();
        }
    }

    /**
     * Observa el cierre del modal (Cancelar / X / Escape / overlay) sin listeners documentales extra.
     * @param {function():void} onClosed
     * @returns {function(Object=):void} abort
     */
    function watchModalClose(onClosed) {
        var root = typeof document !== 'undefined'
            ? document.getElementById('aa-modal-root')
            : null;
        if (!root || typeof MutationObserver === 'undefined') {
            return function abort() {};
        }

        var done = false;
        var observer = new MutationObserver(function () {
            if (done) {
                return;
            }
            if (root.classList && root.classList.contains('hidden')) {
                done = true;
                observer.disconnect();
                onClosed();
            }
        });
        observer.observe(root, { attributes: true, attributeFilter: ['class'] });

        return function abort(options) {
            if (done) {
                return;
            }
            done = true;
            observer.disconnect();
            if (options && options.runCallback) {
                onClosed();
            }
        };
    }

    function disarmModalCloseWatcher(options) {
        if (!modalCloseAbort) {
            return;
        }
        var abort = modalCloseAbort;
        modalCloseAbort = null;
        abort(options || {});
    }

    function armModalCloseFocus(focusReturnEl) {
        disarmModalCloseWatcher();
        modalCloseAbort = watchModalClose(function () {
            modalCloseAbort = null;
            focusElement(focusReturnEl);
        });
    }

    // ── Miniaturas MC4c ─────────────────────────────────────────────

    function hasValidAdjunto(record) {
        return !!(record && record.adjunto && parseInt(record.adjunto.id, 10) > 0);
    }

    /**
     * Normaliza una colección de adjuntos (MC5a): filtra DTOs inválidos,
     * deduplica por id y ordena id DESC. `adjuntos[]` es la fuente de verdad.
     */
    function normalizeAdjuntosList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        var seen = {};
        var out = [];
        list.forEach(function (dto) {
            if (!isValidAdjuntoDto(dto)) {
                return;
            }
            var id = parseInt(dto.id, 10);
            if (seen[id]) {
                return;
            }
            seen[id] = true;
            out.push(dto);
        });
        out.sort(function (a, b) {
            return parseInt(b.id, 10) - parseInt(a.id, 10);
        });
        return out;
    }

    /**
     * Colección vigente de un registro en estado. Puente MC4c: un registro
     * sin clave `adjuntos` pero con alias `adjunto` válido se trata como
     * colección de un elemento.
     */
    function getRecordAdjuntos(record) {
        if (!record) {
            return [];
        }
        if (Array.isArray(record.adjuntos)) {
            return record.adjuntos;
        }
        if (record.adjunto && isValidAdjuntoDto(record.adjunto)) {
            return [record.adjunto];
        }
        return [];
    }

    /**
     * Fija en el registro la colección normalizada y deriva el alias
     * `adjunto` = adjuntos[0] | null. Muta y devuelve el mismo objeto.
     */
    function setRecordAdjuntos(record, list) {
        record.adjuntos = normalizeAdjuntosList(list);
        record.adjunto = record.adjuntos.length ? record.adjuntos[0] : null;
        return record;
    }

    /**
     * Normaliza un registro recibido del servidor. Prioridad: clave
     * `adjuntos` (autoritativa, incluso []) > clave `adjunto` (puente MC4c,
     * singleton) > colección vacía.
     */
    function normalizeIncomingRecord(record) {
        if (!record) {
            return record;
        }
        if (Object.prototype.hasOwnProperty.call(record, 'adjuntos')) {
            return setRecordAdjuntos(record, record.adjuntos);
        }
        if (Object.prototype.hasOwnProperty.call(record, 'adjunto')) {
            return setRecordAdjuntos(record, record.adjunto ? [record.adjunto] : []);
        }
        return setRecordAdjuntos(record, []);
    }

    /**
     * DTO público de adjunto: { id, width, height, byte_size, created_at }.
     */
    function isValidAdjuntoDto(adjunto) {
        return !!(
            adjunto
            && parseInt(adjunto.id, 10) > 0
            && parseInt(adjunto.width, 10) > 0
            && parseInt(adjunto.height, 10) > 0
            && parseInt(adjunto.byte_size, 10) > 0
            && typeof adjunto.created_at === 'string'
        );
    }

    var READ_VARIANTS = ['summary', 'gallery', 'display'];

    function variantForKind(kind) {
        if (kind === 'summary') {
            return 'summary';
        }
        if (kind === 'mini') {
            return 'gallery';
        }
        if (kind === 'main' || kind === 'viewer') {
            return 'display';
        }
        return '';
    }

    function thumbKey(recordId, adjuntoId, variant) {
        var scope = state.scopeKey !== ''
            ? state.scopeKey
            : (state.clientId > 0 ? String(state.clientId) : '');
        return String(scope) + ':' + String(recordId) + ':' + String(adjuntoId) + ':' + String(variant);
    }

    function attachmentLockKey(recordId, adjuntoId) {
        var scope = state.scopeKey !== ''
            ? state.scopeKey
            : (state.clientId > 0 ? String(state.clientId) : '');
        return String(scope) + ':' + String(recordId) + ':' + String(adjuntoId);
    }

    function abortThumbRequest(key) {
        var pending = thumbs.thumbnailRequests[key];
        if (!pending) {
            return;
        }
        delete thumbs.thumbnailRequests[key];
        if (pending.controller && typeof pending.controller.abort === 'function') {
            try {
                pending.controller.abort();
            } catch (e) {
                // ignore
            }
        }
    }

    function abortAllThumbRequests() {
        Object.keys(thumbs.thumbnailRequests).forEach(abortThumbRequest);
    }

    /**
     * Poda cache/requests/resigned contra las identidades vigentes de state.records.
     * MC5a: válidas todas las identidades de `adjuntos[]`, aunque el render
     * solo muestre adjuntos[0]. Conserva solo URLs cargadas y vigentes.
     */
    function pruneThumbState() {
        var valid = {};
        var validRecordIds = {};
        state.records.forEach(function (record) {
            validRecordIds[parseInt(record.id, 10)] = true;
            getRecordAdjuntos(record).forEach(function (dto) {
                if (isValidAdjuntoDto(dto)) {
                    READ_VARIANTS.forEach(function (variant) {
                        valid[thumbKey(record.id, dto.id, variant)] = true;
                    });
                }
            });
        });

        Object.keys(thumbs.thumbnailCache).forEach(function (key) {
            var entry = thumbs.thumbnailCache[key];
            var fresh = entry && typeof entry.deadlineMs === 'number' && entry.deadlineMs > Date.now();
            if (!valid[key] || !fresh) {
                delete thumbs.thumbnailCache[key];
            }
        });

        Object.keys(thumbs.thumbnailRequests).forEach(function (key) {
            if (!valid[key]) {
                abortThumbRequest(key);
            }
        });

        Object.keys(thumbs.resignedIdentities).forEach(function (key) {
            if (!valid[key]) {
                delete thumbs.resignedIdentities[key];
            }
        });

        // MC5b: la selección de registros inexistentes se descarta; la
        // selección inválida dentro de un registro vigente se corrige en
        // resolveSelectedAdjuntoId al renderizar.
        Object.keys(thumbs.selectedByRecord).forEach(function (recordId) {
            if (!validRecordIds[recordId]) {
                delete thumbs.selectedByRecord[recordId];
            }
        });
    }

    /**
     * Invalida toda entrada (cache/requests/resigned) de un registro del cliente actual.
     */
    function invalidateThumbForRecord(recordId) {
        var scope = state.scopeKey !== ''
            ? state.scopeKey
            : (state.clientId > 0 ? String(state.clientId) : '');
        var prefix = String(scope) + ':' + String(recordId) + ':';
        Object.keys(thumbs.thumbnailCache).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                delete thumbs.thumbnailCache[key];
            }
        });
        Object.keys(thumbs.thumbnailRequests).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                abortThumbRequest(key);
            }
        });
        Object.keys(thumbs.resignedIdentities).forEach(function (key) {
            if (key.indexOf(prefix) === 0) {
                delete thumbs.resignedIdentities[key];
            }
        });
    }

    function disconnectThumbObserver() {
        if (thumbs.observer && typeof thumbs.observer.disconnect === 'function') {
            try {
                thumbs.observer.disconnect();
            } catch (e) {
                // ignore
            }
        }
        thumbs.observer = null;
    }

    /**
     * Antes de cada re-render del mismo cliente: observer fuera, firmas en
     * vuelo abortadas, cache podado a identidades vigentes.
     */
    function prepareThumbsForRender() {
        disconnectThumbObserver();
        abortAllThumbRequests();
        pruneThumbState();
    }

    /**
     * POST sign-read con señal de aborto. MC5a: siempre lectura dirigida con
     * attachment_id; el fallback sin attachment_id vive solo en PHP.
     */
    function postSignRead(recordId, attachmentId, variant, signal) {
        if (!isCapEnabled('signRead')) {
            console.error('[ExpedienteRegistros] signRead deshabilitado');
            return Promise.resolve(transportIncompletePayload());
        }

        if (isPortsMode()) {
            return callPort('signRead', recordId, attachmentId, variant, signal);
        }

        var action = resolveAction('signAdjuntoRead');
        if (!canSendTransportRequest(action)) {
            console.error('[ExpedienteRegistros] transporte incompleto para sign-read');
            return Promise.resolve(transportIncompletePayload());
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', resolveNonce());
        formData.append('client_id', String(state.clientId));
        formData.append('record_id', String(recordId));
        formData.append('attachment_id', String(attachmentId));
        formData.append('variant', variant);

        var options = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        };
        if (signal) {
            options.signal = signal;
        }

        return fetch(resolveAjaxUrl(), options).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    function isNodeConnected(node) {
        if (!node) {
            return false;
        }
        if (typeof node.isConnected === 'boolean') {
            return node.isConnected;
        }
        if (typeof document !== 'undefined' && document.contains) {
            return document.contains(node);
        }
        return true;
    }

    function showThumbErrorState(box) {
        if (!box) {
            return;
        }
        box.classList.add('aa-expediente-adjunto-thumb-error');
        box.classList.remove('aa-expediente-adjunto-thumb-loaded');
        var img = box.querySelector('img');
        if (img && img.parentNode === box) {
            box.removeChild(img);
        }
        box.setAttribute('title', THUMB_ERROR_MESSAGE);
    }

    function handleThumbImgError(box, key, recordId) {
        delete thumbs.thumbnailCache[key];
        var img = box ? box.querySelector('img') : null;
        if (img) {
            img.onerror = null;
            img.onload = null;
            img.removeAttribute('src');
            if (img.parentNode === box) {
                box.removeChild(img);
            }
        }

        // Como máximo una refirma automática por identidad durante la vista.
        if (!thumbs.resignedIdentities[key]) {
            thumbs.resignedIdentities[key] = true;
            requestThumbFor(box, recordId);
            return;
        }

        showThumbErrorState(box);
    }

    function applyThumbUrl(box, url, key, recordId) {
        if (!box) {
            return;
        }
        box.classList.remove('aa-expediente-adjunto-thumb-error');
        box.removeAttribute('title');

        var img = box.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            img.className = 'aa-expediente-adjunto-thumb-img';
            // Dentro de un botón el nombre accesible lo aporta el botón.
            img.alt = box.tagName === 'BUTTON' ? '' : 'Imagen adjunta';
            img.setAttribute('referrerpolicy', 'no-referrer');
            img.setAttribute('decoding', 'async');
            img.setAttribute('draggable', 'false');
            box.appendChild(img);
        }
        img.onload = function () {
            box.classList.add('aa-expediente-adjunto-thumb-loaded');
        };
        img.onerror = function () {
            handleThumbImgError(box, key, recordId);
        };
        img.src = url;
    }

    /**
     * ¿La identidad solicitada sigue existiendo en la colección del registro?
     */
    function recordHasAdjuntoId(record, adjuntoId) {
        var id = parseInt(adjuntoId, 10);
        return getRecordAdjuntos(record).some(function (dto) {
            return parseInt(dto.id, 10) === id;
        });
    }

    /**
     * Entrega la URL a un suscriptor. Un suscriptor-box solo la recibe si
     * sigue conectado y su data-adjunto-id no cambió (una respuesta de una
     * selección anterior jamás se aplica al nodo tras cambiar de identidad).
     */
    function deliverSignedUrl(sub, url, key, rid, adjuntoId) {
        if (sub.box) {
            if (isNodeConnected(sub.box)
                && parseInt(sub.box.getAttribute('data-adjunto-id') || '0', 10) === adjuntoId) {
                applyThumbUrl(sub.box, url, key, rid);
            }
            return;
        }
        if (typeof sub.onUrl === 'function') {
            sub.onUrl(url);
        }
    }

    function deliverSignError(sub, adjuntoId) {
        if (sub.box) {
            if (isNodeConnected(sub.box)
                && parseInt(sub.box.getAttribute('data-adjunto-id') || '0', 10) === adjuntoId) {
                showThumbErrorState(sub.box);
            }
            return;
        }
        if (typeof sub.onError === 'function') {
            sub.onError();
        }
    }

    /**
     * Single-flight con suscriptores (MC5b): una sola firma en vuelo por
     * identidad; cada nodo/callback interesado se suma como suscriptor y la
     * URL se aplica a todos los vigentes al resolver. Identidad capturada:
     * viewEpoch + scopeKey + recordId + adjuntoId.
     *
     * @param {number} rid
     * @param {number} adjuntoId
     * @param {string} variant
     * @param {{box?:HTMLElement, onUrl?:function(string):void, onError?:function():void}} subscriber
     */
    function requestSignedUrl(rid, adjuntoId, variant, subscriber) {
        if (!isCapEnabled('signRead')) {
            deliverSignError(subscriber, adjuntoId);
            return;
        }

        if (READ_VARIANTS.indexOf(variant) === -1) {
            deliverSignError(subscriber, adjuntoId);
            return;
        }

        var key = thumbKey(rid, adjuntoId, variant);

        var cached = thumbs.thumbnailCache[key];
        if (cached && typeof cached.deadlineMs === 'number' && cached.deadlineMs > Date.now()) {
            deliverSignedUrl(subscriber, cached.url, key, rid, adjuntoId);
            return;
        }
        if (cached) {
            delete thumbs.thumbnailCache[key];
        }

        var pending = thumbs.thumbnailRequests[key];
        if (pending) {
            pending.subscribers.push(subscriber);
            return;
        }

        var epoch = thumbs.viewEpoch;
        var scopeKey = state.scopeKey;
        var controller = null;
        var signal = null;
        if (typeof AbortController !== 'undefined') {
            controller = new AbortController();
            signal = controller.signal;
        }

        var subscribers = [subscriber];
        thumbs.thumbnailRequests[key] = { controller: controller, subscribers: subscribers };

        postSignRead(rid, adjuntoId, variant, signal)
            .then(function (payload) {
                delete thumbs.thumbnailRequests[key];

                // Vista/scope inactivos: descartar sin tocar DOM ni caché.
                if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                    return;
                }

                var record = findRecordById(rid);
                if (!record) {
                    return;
                }

                var result = payload.result;
                var data = result && result.success ? result.data : null;
                var validPayload = !!(
                    data
                    && typeof data.url === 'string'
                    && data.url !== ''
                    && data.variant === variant
                );

                if (!validPayload || !recordHasAdjuntoId(record, adjuntoId)) {
                    subscribers.forEach(function (sub) {
                        deliverSignError(sub, adjuntoId);
                    });
                    return;
                }

                var expiresIn = parseInt(data.expires_in, 10);
                var windowSeconds = expiresIn > THUMB_TTL_SAFETY_SECONDS
                    ? expiresIn - THUMB_TTL_SAFETY_SECONDS
                    : expiresIn;
                if (!(windowSeconds > 0)) {
                    windowSeconds = 1;
                }

                thumbs.thumbnailCache[key] = {
                    url: data.url,
                    deadlineMs: Date.now() + windowSeconds * 1000
                };

                subscribers.forEach(function (sub) {
                    deliverSignedUrl(sub, data.url, key, rid, adjuntoId);
                });
            })
            .catch(function (err) {
                delete thumbs.thumbnailRequests[key];
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                    return;
                }
                subscribers.forEach(function (sub) {
                    deliverSignError(sub, adjuntoId);
                });
            });
    }

    /**
     * Solicita (o reutiliza) la firma para un nodo de imagen (miniatura de
     * summary, imagen principal o mini de la galería).
     */
    function requestThumbFor(box, recordId) {
        if (!isCapEnabled('signRead')) {
            return;
        }
        if (!box) {
            return;
        }
        var adjuntoId = parseInt(box.getAttribute('data-adjunto-id') || '0', 10);
        var variant = box.getAttribute('data-variant') || '';
        var rid = parseInt(recordId, 10);
        if (!(adjuntoId > 0) || !(rid > 0) || READ_VARIANTS.indexOf(variant) === -1) {
            return;
        }
        requestSignedUrl(rid, adjuntoId, variant, { box: box });
    }

    /**
     * Observa los thumb boxes recién renderizados (summary + galería). Cache
     * fresco se aplica de inmediato sin firma nueva; el resto espera
     * intersección (o toggle como fallback sin IntersectionObserver).
     *
     * Fallback sin IntersectionObserver (MC5b): al abrir la tarjeta se firma
     * solo lo visible sin scroll — summary, imagen principal y las primeras
     * tres minis. Nunca la colección completa.
     */
    function setupThumbObserver(boxes) {
        if (!boxes.length) {
            return;
        }

        var pendingBoxes = [];

        boxes.forEach(function (entry) {
            var variant = variantForKind(entry.kind);
            var key = thumbKey(entry.recordId, entry.adjuntoId, variant);
            var cached = thumbs.thumbnailCache[key];
            if (cached && typeof cached.deadlineMs === 'number' && cached.deadlineMs > Date.now()) {
                applyThumbUrl(entry.box, cached.url, key, entry.recordId);
                return;
            }
            pendingBoxes.push(entry);
        });

        if (!pendingBoxes.length) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            pendingBoxes.forEach(function (entry) {
                if (!entry.details || typeof entry.details.addEventListener !== 'function') {
                    return;
                }
                if (entry.kind === 'mini' && entry.miniIndex >= 3) {
                    return;
                }
                var requestNow = function () {
                    requestThumbFor(entry.box, entry.recordId);
                };
                // Galería renderizada ya expandida (attach/expandId): visible,
                // firmar de inmediato. La summary conserva su regla de toggle.
                if (entry.kind !== 'summary' && entry.details.open) {
                    requestNow();
                }
                entry.details.addEventListener('toggle', function () {
                    if (entry.details.open) {
                        requestNow();
                    }
                });
            });
            return;
        }

        var byBox = [];
        thumbs.observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (ioEntry) {
                if (!ioEntry.isIntersecting) {
                    return;
                }
                for (var i = 0; i < byBox.length; i++) {
                    if (byBox[i].box === ioEntry.target) {
                        if (thumbs.observer) {
                            thumbs.observer.unobserve(ioEntry.target);
                        }
                        requestThumbFor(byBox[i].box, byBox[i].recordId);
                        return;
                    }
                }
            });
        }, { rootMargin: '200px 0px' });

        pendingBoxes.forEach(function (entry) {
            byBox.push(entry);
            thumbs.observer.observe(entry.box);
        });
    }

    /**
     * Integra un DTO en la colección del registro (MC5a): lo agrega o
     * sustituye por id dentro de `adjuntos[]`, deduplica, ordena id DESC y
     * deriva el alias `adjunto`. Invalida la caché de ese cliente+registro y
     * re-renderiza expandido.
     */
    function applyAdjuntoToRecord(recordId, adjunto) {
        var id = parseInt(recordId, 10);
        if (!(id > 0) || !isValidAdjuntoDto(adjunto)) {
            return false;
        }

        var found = false;
        state.records = state.records.map(function (existing) {
            if (parseInt(existing.id, 10) !== id) {
                return existing;
            }
            found = true;
            var merged = {};
            Object.keys(existing).forEach(function (k) {
                merged[k] = existing[k];
            });
            var adjuntoId = parseInt(adjunto.id, 10);
            var next = getRecordAdjuntos(existing).filter(function (dto) {
                return parseInt(dto.id, 10) !== adjuntoId;
            });
            next.push(adjunto);
            return setRecordAdjuntos(merged, next);
        });

        if (!found) {
            return false;
        }

        // MC5b: la imagen recién adjuntada pasa a ser la seleccionada.
        thumbs.selectedByRecord[id] = parseInt(adjunto.id, 10);

        invalidateThumbForRecord(id);
        renderRecordsList({ expandId: id });
        return true;
    }

    /**
     * Libera todos los recursos de la vista: época, observer, firmas en vuelo,
     * caché, selección, referencias e identidad del cliente. Cierra el visor
     * solo si el modal compartido contiene el marcador de esta instancia.
     */
    function destroy() {
        unbindRegistroOptionsUi();
        closeOwnViewerModal();

        thumbs.viewEpoch += 1;
        disconnectThumbObserver();
        abortAllThumbRequests();
        thumbs.thumbnailCache = {};
        thumbs.thumbnailRequests = {};
        thumbs.resignedIdentities = {};
        thumbs.selectedByRecord = {};
        thumbs.deletingKeys = {};
        thumbs.deletingRecords = {};

        state.records = [];
        state.recordsRoot = null;
        state.actionsRoot = null;
        state.clientId = 0;
        state.scopeKey = '';
        state.loading = false;
        state.transport = null;
        state.ports = null;
        state.capabilities = null;
    }

    // ── Fin miniaturas MC4c ─────────────────────────────────────────

    // ── Minigalería y visor MC5b ────────────────────────────────────

    /**
     * Selección vigente de un registro (solo UI). Si la selección guardada ya
     * no existe en adjuntos[], vuelve determinísticamente a adjuntos[0] y
     * corrige el mapa.
     */
    function resolveSelectedAdjuntoId(record) {
        var adjuntos = getRecordAdjuntos(record);
        if (!adjuntos.length) {
            return 0;
        }
        var rid = parseInt(record.id, 10);
        var selected = parseInt(thumbs.selectedByRecord[rid] || 0, 10);
        if (selected > 0 && recordHasAdjuntoId(record, selected)) {
            return selected;
        }
        var fallback = parseInt(adjuntos[0].id, 10);
        thumbs.selectedByRecord[rid] = fallback;
        return fallback;
    }

    /**
     * Limpia un nodo de imagen (main/mini) para reutilizarlo con otra
     * identidad sin heredar img, estados de error ni title.
     */
    function resetThumbBox(box) {
        box.classList.remove('aa-expediente-adjunto-thumb-error');
        box.classList.remove('aa-expediente-adjunto-thumb-loaded');
        box.removeAttribute('title');
        var img = box.querySelector('img');
        if (img) {
            img.onload = null;
            img.onerror = null;
            img.removeAttribute('src');
            if (img.parentNode === box) {
                box.removeChild(img);
            }
        }
    }

    function galleryCounterText(adjuntos, selectedId) {
        for (var i = 0; i < adjuntos.length; i++) {
            if (parseInt(adjuntos[i].id, 10) === selectedId) {
                return String(i + 1) + ' de ' + String(adjuntos.length);
            }
        }
        return '1 de ' + String(adjuntos.length);
    }

    /**
     * Cambia la imagen seleccionada de una galería sin re-renderizar la lista
     * ni tocar el orden de adjuntos[]. La respuesta en vuelo de la selección
     * anterior no se aplicará al main porque su data-adjunto-id cambió.
     */
    function selectGalleryAdjunto(gallery, recordId, adjuntoId) {
        var rid = parseInt(recordId, 10);
        var id = parseInt(adjuntoId, 10);
        var record = findRecordById(rid);
        if (!record || !recordHasAdjuntoId(record, id)) {
            return;
        }

        thumbs.selectedByRecord[rid] = id;
        var adjuntos = getRecordAdjuntos(record);

        gallery.querySelectorAll('.aa-expediente-galeria-mini').forEach(function (mini) {
            var isSelected = parseInt(mini.getAttribute('data-adjunto-id') || '0', 10) === id;
            mini.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            if (isSelected) {
                mini.classList.add('aa-expediente-galeria-mini-selected');
            } else {
                mini.classList.remove('aa-expediente-galeria-mini-selected');
            }
        });

        var counter = gallery.querySelector('.aa-expediente-galeria-counter');
        if (counter) {
            counter.textContent = galleryCounterText(adjuntos, id);
        }

        var main = gallery.querySelector('.aa-expediente-galeria-main');
        if (main) {
            resetThumbBox(main);
            main.setAttribute('data-adjunto-id', String(id));
            main.setAttribute('data-variant', 'display');
            requestThumbFor(main, rid);
        }

        var deleteBtn = gallery.querySelector('.aa-expediente-galeria-delete');
        if (deleteBtn) {
            deleteBtn.setAttribute('data-adjunto-id', String(id));
        }
    }

    /**
     * Construye la minigalería del panel expandido: imagen principal (abre el
     * visor) y, con varias imágenes, tira horizontal de minis + contador.
     * Registra cada nodo de imagen en entriesOut para la carga progresiva.
     */
    function buildRecordGallery(record, entriesOut, details) {
        if (!isCapEnabled('signRead')) {
            return null;
        }

        var adjuntos = getRecordAdjuntos(record);
        if (!adjuntos.length) {
            return null;
        }

        var rid = parseInt(record.id, 10);
        var selectedId = resolveSelectedAdjuntoId(record);
        var canDeleteAdjunto = isCapEnabled('deleteAdjunto');

        var gallery = document.createElement('div');
        gallery.className = 'aa-expediente-galeria';
        gallery.setAttribute('data-galeria-record-id', String(rid));

        // Envoltura relativa: principal y papelera como hermanos (sin botones anidados).
        var mainWrap = document.createElement('div');
        mainWrap.className = 'aa-expediente-galeria-main-wrap';

        var mainBtn = document.createElement('button');
        mainBtn.type = 'button';
        mainBtn.className = 'aa-expediente-galeria-main';
        mainBtn.setAttribute('data-adjunto-id', String(selectedId));
        mainBtn.setAttribute('data-variant', 'display');
        mainBtn.setAttribute('aria-label', 'Ver imagen ampliada');
        mainBtn.addEventListener('click', function (event) {
            event.preventDefault();
            openAdjuntoViewer(rid, mainBtn);
        });

        mainWrap.appendChild(mainBtn);

        if (canDeleteAdjunto) {
            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'aa-expediente-galeria-delete';
            deleteBtn.setAttribute('aria-label', 'Eliminar imagen');
            deleteBtn.setAttribute('title', 'Eliminar imagen');
            deleteBtn.setAttribute('data-adjunto-id', String(selectedId));
            deleteBtn.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
                '<path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9zm-1 12h12V8H6v13z"/>' +
                '</svg>';
            deleteBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                confirmAndDeleteAdjunto(rid, deleteBtn);
            });
            mainWrap.appendChild(deleteBtn);
        }

        gallery.appendChild(mainWrap);

        entriesOut.push({
            box: mainBtn,
            details: details,
            recordId: rid,
            adjuntoId: selectedId,
            kind: 'main'
        });

        if (adjuntos.length > 1) {
            var strip = document.createElement('div');
            strip.className = 'aa-expediente-galeria-strip';
            strip.setAttribute('role', 'group');
            strip.setAttribute('aria-label', 'Imágenes del registro');

            adjuntos.forEach(function (dto, index) {
                var adjuntoId = parseInt(dto.id, 10);
                var mini = document.createElement('button');
                mini.type = 'button';
                mini.className = 'aa-expediente-galeria-mini';
                mini.setAttribute('data-adjunto-id', String(adjuntoId));
                mini.setAttribute('data-variant', 'gallery');
                mini.setAttribute('aria-label', 'Ver imagen ' + String(index + 1) + ' de ' + String(adjuntos.length));
                mini.setAttribute('aria-pressed', adjuntoId === selectedId ? 'true' : 'false');
                if (adjuntoId === selectedId) {
                    mini.classList.add('aa-expediente-galeria-mini-selected');
                }
                mini.addEventListener('click', function (event) {
                    event.preventDefault();
                    selectGalleryAdjunto(gallery, rid, adjuntoId);
                });
                strip.appendChild(mini);

                entriesOut.push({
                    box: mini,
                    details: details,
                    recordId: rid,
                    adjuntoId: adjuntoId,
                    kind: 'mini',
                    miniIndex: index
                });
            });
            gallery.appendChild(strip);

            var counter = document.createElement('p');
            counter.className = 'aa-expediente-galeria-counter';
            counter.textContent = galleryCounterText(adjuntos, selectedId);
            gallery.appendChild(counter);
        }

        return gallery;
    }

    /**
     * Tras borrar el adjunto en posición i: restantes[i] ?? restantes[i-1] ?? null.
     *
     * @param {Array<{id:number}>} before
     * @param {number} deletedId
     * @param {Array<{id:number}>} remaining
     * @returns {number} attachment id o 0 si no quedan
     */
    function pickSelectionAfterDelete(before, deletedId, remaining) {
        var list = Array.isArray(before) ? before : [];
        var rest = Array.isArray(remaining) ? remaining : [];
        var deleted = parseInt(deletedId, 10);
        var index = -1;
        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].id, 10) === deleted) {
                index = i;
                break;
            }
        }
        if (!rest.length) {
            return 0;
        }
        if (index >= 0 && rest[index]) {
            return parseInt(rest[index].id, 10);
        }
        if (index > 0 && rest[index - 1]) {
            return parseInt(rest[index - 1].id, 10);
        }
        if (index >= rest.length && rest[rest.length - 1]) {
            return parseInt(rest[rest.length - 1].id, 10);
        }
        return parseInt(rest[0].id, 10) || 0;
    }

    /**
     * Confirmación + delete de la imagen seleccionada (MC5c1).
     * Ids congelados al pulsar; cancelar no llama al endpoint.
     */
    function confirmAndDeleteAdjunto(recordId, deleteBtn) {
        if (!isCapEnabled('deleteAdjunto')) {
            return;
        }

        var rid = parseInt(recordId, 10);
        var attachmentId = deleteBtn
            ? parseInt(deleteBtn.getAttribute('data-adjunto-id') || '0', 10)
            : 0;
        if (!(rid > 0) || !(attachmentId > 0)) {
            return;
        }

        var key = attachmentLockKey(rid, attachmentId);
        if (thumbs.deletingKeys[key]) {
            return;
        }

        var confirmFn = typeof window !== 'undefined' && typeof window.confirm === 'function'
            ? window.confirm.bind(window)
            : null;
        if (!confirmFn || !confirmFn(DELETE_IMAGE_CONFIRM)) {
            return;
        }

        // Identidad inmutable capturada al confirmar.
        var epoch = thumbs.viewEpoch;
        var scopeKey = state.scopeKey;
        var clientId = state.clientId;
        var frozenRecordId = rid;
        var frozenAttachmentId = attachmentId;

        thumbs.deletingKeys[key] = true;
        if (deleteBtn) {
            deleteBtn.disabled = true;
        }

        var gallery = deleteBtn && deleteBtn.closest
            ? deleteBtn.closest('.aa-expediente-galeria')
            : null;
        if (gallery) {
            var prevErr = gallery.querySelector('.aa-expediente-galeria-error');
            if (prevErr && prevErr.parentNode) {
                prevErr.parentNode.removeChild(prevErr);
            }
        }

        var beforeAdjuntos = [];
        var recordBefore = findRecordById(frozenRecordId);
        if (recordBefore) {
            beforeAdjuntos = getRecordAdjuntos(recordBefore).slice();
        }

        var deleteAdjuntoRequest = isPortsMode()
            ? callPort('deleteAdjunto', frozenRecordId, frozenAttachmentId)
            : postForm(resolveAction('deleteAdjunto'), {
                client_id: String(clientId),
                record_id: String(frozenRecordId),
                attachment_id: String(frozenAttachmentId)
            });

        deleteAdjuntoRequest
            .then(function (payload) {
                delete thumbs.deletingKeys[key];

                if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                    return;
                }

                var result = payload.result;
                if (!(result && result.success && result.data)) {
                    if (deleteBtn && isNodeConnected(deleteBtn)) {
                        deleteBtn.disabled = false;
                    }
                    showGalleryDeleteError(gallery, DELETE_IMAGE_ERROR_MESSAGE);
                    return;
                }

                var responseData = result.data;
                var returnedRecordId = parseInt(responseData.record_id, 10);
                var returnedDeletedId = parseInt(responseData.deleted_attachment_id, 10);
                if (
                    returnedRecordId !== frozenRecordId
                    || returnedDeletedId !== frozenAttachmentId
                    || !Array.isArray(responseData.adjuntos)
                ) {
                    if (deleteBtn && isNodeConnected(deleteBtn)) {
                        deleteBtn.disabled = false;
                    }
                    showGalleryDeleteError(gallery, DELETE_IMAGE_ERROR_MESSAGE);
                    return;
                }

                if (!findRecordById(frozenRecordId)) {
                    return;
                }

                var remaining = normalizeAdjuntosList(responseData.adjuntos);
                var nextId = pickSelectionAfterDelete(
                    beforeAdjuntos,
                    frozenAttachmentId,
                    remaining
                );
                if (nextId > 0) {
                    thumbs.selectedByRecord[frozenRecordId] = nextId;
                } else {
                    delete thumbs.selectedByRecord[frozenRecordId];
                }

                replaceRecord({
                    id: frozenRecordId,
                    adjuntos: remaining,
                    adjunto: remaining.length ? remaining[0] : null
                });
            })
            .catch(function (err) {
                delete thumbs.deletingKeys[key];
                if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                    return;
                }
                console.error('[ExpedienteRegistros] delete adjunto failed:', err);
                if (deleteBtn && isNodeConnected(deleteBtn)) {
                    deleteBtn.disabled = false;
                }
                showGalleryDeleteError(gallery, DELETE_IMAGE_ERROR_MESSAGE);
            });
    }

    function showGalleryDeleteError(gallery, message) {
        if (!gallery || !isNodeConnected(gallery)) {
            return;
        }
        var err = gallery.querySelector('.aa-expediente-galeria-error');
        if (!err) {
            err = document.createElement('p');
            err.className = 'aa-expediente-galeria-error';
            err.setAttribute('role', 'alert');
            gallery.appendChild(err);
        }
        err.textContent = message || DELETE_IMAGE_ERROR_MESSAGE;
    }

    function deleteRecordConfirmMessage(record) {
        var n = getRecordAdjuntos(record).length;
        if (n < 1) {
            return DELETE_RECORD_CONFIRM_EMPTY;
        }
        return '¿Eliminar este registro? También se eliminarán sus '
            + String(n)
            + ' imágenes. Esta acción no se puede deshacer.';
    }

    /**
     * Retira un registro del estado y re-renderiza (MC5c2).
     */
    function removeRecordFromState(recordId) {
        var rid = parseInt(recordId, 10);
        if (!(rid > 0)) {
            return;
        }
        invalidateThumbForRecord(rid);
        delete thumbs.selectedByRecord[rid];
        delete thumbs.deletingRecords[rid];
        state.records = state.records.filter(function (record) {
            return parseInt(record.id, 10) !== rid;
        });
        pruneThumbState();
        renderRecordsList();
    }

    /**
     * Confirmación + delete del registro completo (MC5c2).
     */
    function confirmAndDeleteRegistro(recordId, deleteBtn) {
        if (!isCapEnabled('deleteRegistro')) {
            return;
        }

        if (window.AAAdmin && window.AAAdmin.modal && typeof window.AAAdmin.modal.isOpen === 'function'
            && window.AAAdmin.modal.isOpen()) {
            return;
        }

        var rid = parseInt(recordId, 10);
        if (!(rid > 0)) {
            return;
        }
        if (thumbs.deletingRecords[rid]) {
            return;
        }

        var record = findRecordById(rid);
        if (!record) {
            return;
        }

        var confirmFn = typeof window !== 'undefined' && typeof window.confirm === 'function'
            ? window.confirm.bind(window)
            : null;
        if (!confirmFn || !confirmFn(deleteRecordConfirmMessage(record))) {
            return;
        }

        var epoch = thumbs.viewEpoch;
        var scopeKey = state.scopeKey;
        var clientId = state.clientId;
        var frozenRecordId = rid;

        thumbs.deletingRecords[frozenRecordId] = true;
        if (deleteBtn) {
            deleteBtn.disabled = true;
        }

        var details = deleteBtn && deleteBtn.closest
            ? deleteBtn.closest('.aa-expediente-registro')
            : null;
        if (details) {
            var prevErr = details.querySelector('.aa-expediente-registro-delete-error');
            if (prevErr && prevErr.parentNode) {
                prevErr.parentNode.removeChild(prevErr);
            }
        }

        var deleteRegistroRequest = isPortsMode()
            ? callPort('deleteRegistro', frozenRecordId)
            : postForm(resolveAction('deleteRegistro'), {
                client_id: String(clientId),
                record_id: String(frozenRecordId)
            });

        deleteRegistroRequest
            .then(function (payload) {
                delete thumbs.deletingRecords[frozenRecordId];

                if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                    return;
                }

                var result = payload.result;
                if (!(result && result.success && result.data && result.data.deleted === true
                    && parseInt(result.data.record_id, 10) === frozenRecordId)) {
                    if (deleteBtn && isNodeConnected(deleteBtn)) {
                        deleteBtn.disabled = false;
                    }
                    showRegistroDeleteError(details, DELETE_RECORD_ERROR_MESSAGE);
                    return;
                }

                removeRecordFromState(frozenRecordId);
            })
            .catch(function (err) {
                delete thumbs.deletingRecords[frozenRecordId];
                if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                    return;
                }
                console.error('[ExpedienteRegistros] delete registro failed:', err);
                if (deleteBtn && isNodeConnected(deleteBtn)) {
                    deleteBtn.disabled = false;
                }
                showRegistroDeleteError(details, DELETE_RECORD_ERROR_MESSAGE);
            });
    }

    function showRegistroDeleteError(details, message) {
        if (!details || !isNodeConnected(details)) {
            return;
        }
        var panel = details.querySelector('.aa-expediente-registro-panel');
        var err = details.querySelector('.aa-expediente-registro-delete-error');
        if (!err) {
            err = document.createElement('p');
            err.className = 'aa-expediente-registro-delete-error';
            err.setAttribute('role', 'alert');
            if (panel) {
                panel.appendChild(err);
            } else {
                details.appendChild(err);
            }
        }
        err.textContent = message || DELETE_RECORD_ERROR_MESSAGE;
    }

    function getModalRootNode() {
        return typeof document !== 'undefined'
            ? document.getElementById('aa-modal-root')
            : null;
    }

    /**
     * Marcador del visor de esta instancia dentro del modal compartido, o
     * null si el modal está vacío o lo ocupa otro contenido.
     */
    function findOwnViewerMarker() {
        var root = getModalRootNode();
        if (!root) {
            return null;
        }
        var marker = root.querySelector('.aa-expediente-adjunto-viewer');
        if (!marker) {
            return null;
        }
        if (marker.getAttribute('data-aa-viewer-epoch') !== String(thumbs.viewEpoch)) {
            return null;
        }
        return marker;
    }

    /**
     * Cierra el modal compartido únicamente si contiene el visor de esta
     * instancia. Jamás cierra ni reemplaza un modal ajeno.
     */
    function closeOwnViewerModal() {
        if (!findOwnViewerMarker()) {
            return;
        }
        if (window.AAAdmin && typeof window.AAAdmin.closeModal === 'function') {
            window.AAAdmin.closeModal();
        }
    }

    /**
     * Visor MC5b sobre AAAdmin.modal: abre sincrónicamente con estado de
     * carga y resuelve la URL firmada después (caché fresco o firma nueva).
     * Cierre por X/Escape/overlay: mecanismos nativos del modal compartido.
     */
    function openAdjuntoViewer(recordId, focusReturnEl) {
        if (!isCapEnabled('signRead')) {
            return;
        }

        var rid = parseInt(recordId, 10);
        var record = findRecordById(rid);
        if (!record) {
            return;
        }
        var adjuntoId = resolveSelectedAdjuntoId(record);
        if (!(adjuntoId > 0)) {
            return;
        }
        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[ExpedienteRegistros] AAAdmin.openModal no disponible');
            return;
        }

        var epoch = thumbs.viewEpoch;
        var scopeKey = state.scopeKey;
        var key = thumbKey(rid, adjuntoId, 'display');

        var viewerBody = document.createElement('div');
        viewerBody.className = 'aa-expediente-adjunto-viewer';
        viewerBody.setAttribute('data-aa-viewer-epoch', String(epoch));
        viewerBody.setAttribute('data-adjunto-id', String(adjuntoId));

        function showViewerStatus(text, isError) {
            clearNode(viewerBody);
            var status = document.createElement('p');
            status.className = isError
                ? 'aa-expediente-adjunto-viewer-error'
                : 'aa-expediente-adjunto-viewer-status';
            if (isError) {
                status.setAttribute('role', 'alert');
            }
            status.textContent = text;
            viewerBody.appendChild(status);
        }

        /**
         * El resultado asíncrono solo se aplica si la vista, el cliente y el
         * registro siguen vigentes y este visor concreto sigue montado en el
         * modal root (otro modal pudo sustituirlo; no se reabre).
         */
        function isViewerCurrent() {
            if (epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey) {
                return false;
            }
            if (!findRecordById(rid)) {
                return false;
            }
            return findOwnViewerMarker() === viewerBody;
        }

        function showViewerImage(url) {
            clearNode(viewerBody);
            var img = document.createElement('img');
            img.className = 'aa-expediente-adjunto-viewer-img';
            img.alt = record.title || 'Imagen adjunta';
            img.setAttribute('referrerpolicy', 'no-referrer');
            img.setAttribute('decoding', 'async');
            img.onerror = function () {
                // URL expirada o inválida en pantalla: como máximo una
                // refirma automática por identidad, luego error discreto.
                delete thumbs.thumbnailCache[key];
                if (!isViewerCurrent()) {
                    return;
                }
                if (!thumbs.resignedIdentities[key]) {
                    thumbs.resignedIdentities[key] = true;
                    showViewerStatus('Cargando imagen...', false);
                    requestSignedUrl(rid, adjuntoId, 'display', viewerSubscriber);
                    return;
                }
                showViewerStatus(THUMB_ERROR_MESSAGE, true);
            };
            img.src = url;
            viewerBody.appendChild(img);
        }

        var viewerSubscriber = {
            onUrl: function (url) {
                if (!isViewerCurrent()) {
                    return;
                }
                showViewerImage(url);
            },
            onError: function () {
                if (!isViewerCurrent()) {
                    return;
                }
                showViewerStatus(THUMB_ERROR_MESSAGE, true);
            }
        };

        showViewerStatus('Cargando imagen...', false);

        window.AAAdmin.openModal({
            title: record.title || 'Imagen adjunta',
            body: viewerBody
        });

        // Foco de vuelta al botón principal al cerrar (si sigue conectado).
        armModalCloseFocus(focusReturnEl);

        // Caché fresco (ventana de seguridad ya descontada) o firma nueva.
        requestSignedUrl(rid, adjuntoId, 'display', viewerSubscriber);
    }

    // ── Fin minigalería y visor MC5b ────────────────────────────────

    function getRegistroOptionsPlacement() {
        return typeof window !== 'undefined' ? window.AAExecutableOptionsMenuPlacement : null;
    }

    function resetRegistroOptionsPlacement(menu) {
        var placement = getRegistroOptionsPlacement();
        if (placement && typeof placement.resetOptionsMenuPlacement === 'function') {
            placement.resetOptionsMenuPlacement(menu);
            return;
        }
        if (!menu || !menu.style) {
            return;
        }
        menu.style.position = '';
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.zIndex = '';
    }

    function setRegistroOptionsVisible(menu, visible) {
        if (!menu || !menu.classList) {
            return;
        }
        if (visible) {
            menu.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
        }
    }

    function queryRegistroOptions(selector) {
        var root = state.recordsRoot;
        if (root && typeof root.querySelectorAll === 'function') {
            return root.querySelectorAll(selector);
        }
        if (typeof document !== 'undefined' && typeof document.querySelectorAll === 'function') {
            return document.querySelectorAll(selector);
        }
        return [];
    }

    function closeAllRegistroOptionsMenus() {
        queryRegistroOptions('.aa-expediente-registro-options-menu').forEach(function (menu) {
            resetRegistroOptionsPlacement(menu);
            setRegistroOptionsVisible(menu, false);
        });
        queryRegistroOptions('.aa-expediente-registro-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });
        openRegistroOptionsId = '';
    }

    function positionRegistroOptionsMenu(menu, trigger) {
        var placement = getRegistroOptionsPlacement();
        if (!menu || !trigger || !placement || typeof placement.positionOptionsMenu !== 'function') {
            return;
        }
        placement.positionOptionsMenu(menu, trigger);
    }

    function openRegistroOptionsMenu(recordId, trigger, menu) {
        if (!menu) {
            return;
        }
        closeAllRegistroOptionsMenus();
        setRegistroOptionsVisible(menu, true);
        positionRegistroOptionsMenu(menu, trigger);
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
        openRegistroOptionsId = String(recordId);
    }

    function toggleRegistroOptionsMenu(recordId, trigger, menu) {
        if (openRegistroOptionsId === String(recordId)) {
            closeAllRegistroOptionsMenus();
            return;
        }
        openRegistroOptionsMenu(recordId, trigger, menu);
    }

    function isInsideRegistroOptions(target) {
        return !!(target && target.closest && target.closest('.aa-expediente-registro-options'));
    }

    function handleRegistroOptionsDocumentClick(event) {
        var target = event && event.target;
        if (openRegistroOptionsId !== '' && !isInsideRegistroOptions(target)) {
            closeAllRegistroOptionsMenus();
        }
    }

    function handleRegistroOptionsKeydown(event) {
        if (!event || event.key !== 'Escape' || openRegistroOptionsId === '') {
            return;
        }
        closeAllRegistroOptionsMenus();
    }

    function handleRegistroOptionsToggle(event) {
        var details = event && event.target;
        if (!details || !details.classList || !details.classList.contains('aa-expediente-registro')) {
            return;
        }
        closeAllRegistroOptionsMenus();
    }

    function handleRegistroOptionsViewportChange() {
        if (openRegistroOptionsId !== '') {
            closeAllRegistroOptionsMenus();
        }
    }

    function bindRegistroOptionsUi() {
        if (registroOptionsUiBound || typeof document === 'undefined' || !document.addEventListener) {
            return;
        }
        registroOptionsUiBound = true;
        document.addEventListener('click', handleRegistroOptionsDocumentClick);
        document.addEventListener('keydown', handleRegistroOptionsKeydown);
        document.addEventListener('toggle', handleRegistroOptionsToggle, true);
        if (typeof window !== 'undefined' && window.addEventListener) {
            window.addEventListener('scroll', handleRegistroOptionsViewportChange, true);
            window.addEventListener('resize', handleRegistroOptionsViewportChange);
        }
    }

    function unbindRegistroOptionsUi() {
        if (!registroOptionsUiBound || typeof document === 'undefined' || !document.removeEventListener) {
            return;
        }
        document.removeEventListener('click', handleRegistroOptionsDocumentClick);
        document.removeEventListener('keydown', handleRegistroOptionsKeydown);
        document.removeEventListener('toggle', handleRegistroOptionsToggle, true);
        if (typeof window !== 'undefined' && window.removeEventListener) {
            window.removeEventListener('scroll', handleRegistroOptionsViewportChange, true);
            window.removeEventListener('resize', handleRegistroOptionsViewportChange);
        }
        registroOptionsUiBound = false;
        closeAllRegistroOptionsMenus();
    }

    function createRegistroOptions(record) {
        var canUpdate = isCapEnabled('updateRegistro');
        var canDelete = isCapEnabled('deleteRegistro');
        if (!canUpdate && !canDelete) {
            return null;
        }

        var recordId = String(record.id);
        var wrap = document.createElement('div');
        wrap.className = 'relative aa-expediente-registro-options shrink-0';

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'aa-expediente-registro-options-trigger aa-options-trigger-flat';
        trigger.setAttribute('data-aa-registro-options-trigger', '1');
        trigger.setAttribute('data-registro-id', recordId);
        trigger.setAttribute('title', 'Opciones de registro');
        trigger.setAttribute('aria-label', 'Opciones de registro');
        trigger.setAttribute('aria-haspopup', 'menu');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = ''
            + '<svg class="w-6 h-6 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            + '<circle cx="5" cy="12" r="1.75"/>'
            + '<circle cx="12" cy="12" r="1.75"/>'
            + '<circle cx="19" cy="12" r="1.75"/>'
            + '</svg>';

        var menu = document.createElement('div');
        menu.className = 'hidden aa-expediente-registro-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg';
        menu.setAttribute('role', 'menu');
        menu.setAttribute('data-registro-id', recordId);

        if (canUpdate) {
            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'aa-expediente-btn-editar flex w-full items-center gap-2 px-4 py-2.5 text-left text-base text-gray-700 hover:bg-gray-50';
            editBtn.setAttribute('role', 'menuitem');
            editBtn.setAttribute('data-registro-id', recordId);
            editBtn.textContent = 'Editar';
            editBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeAllRegistroOptionsMenus();
                openEditForm(record.id, editBtn);
            });
            menu.appendChild(editBtn);
        }

        if (canDelete) {
            var deleteRecordBtn = document.createElement('button');
            deleteRecordBtn.type = 'button';
            deleteRecordBtn.className = 'aa-expediente-btn-eliminar flex w-full items-center gap-2 px-4 py-2.5 text-left text-base text-red-600 hover:bg-gray-50';
            deleteRecordBtn.setAttribute('role', 'menuitem');
            deleteRecordBtn.setAttribute('data-registro-id', recordId);
            deleteRecordBtn.textContent = 'Eliminar';
            deleteRecordBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeAllRegistroOptionsMenus();
                confirmAndDeleteRegistro(record.id, deleteRecordBtn);
            });
            menu.appendChild(deleteRecordBtn);
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleRegistroOptionsMenu(recordId, trigger, menu);
        });

        wrap.appendChild(trigger);
        wrap.appendChild(menu);
        return wrap;
    }

    /**
     * @param {object} record
     * @param {{open?: boolean}} [options]
     * @returns {HTMLDetailsElement}
     */
    function createRecordDetails(record, options) {
        options = options || {};

        var details = document.createElement('details');
        details.className = 'aa-expediente-registro';
        details.setAttribute('data-registro-id', String(record.id));
        if (options.open) {
            details.open = true;
        }

        var summary = document.createElement('summary');
        summary.className = 'aa-expediente-registro-summary';

        var thumbBox = null;
        if (isCapEnabled('signRead') && hasValidAdjunto(record)) {
            thumbBox = document.createElement('div');
            thumbBox.className = 'aa-expediente-adjunto-thumb';
            thumbBox.setAttribute('data-thumb-record-id', String(record.id));
            thumbBox.setAttribute('data-adjunto-id', String(record.adjunto.id));
            thumbBox.setAttribute('data-variant', 'summary');
            summary.appendChild(thumbBox);
        }

        var summaryMain = document.createElement('div');
        summaryMain.className = 'aa-expediente-registro-summary-main';

        var titleSpan = document.createElement('span');
        titleSpan.className = 'aa-expediente-registro-title';
        titleSpan.textContent = record.title || 'Sin título';

        var meta = document.createElement('div');
        meta.className = 'aa-expediente-registro-meta';

        var folioSpan = document.createElement('span');
        folioSpan.className = 'aa-expediente-registro-folio';
        folioSpan.textContent = 'Folio #' + String(record.id);

        var timeEl = document.createElement('time');
        timeEl.className = 'aa-expediente-registro-date';
        var datetimeAttr = toDatetimeAttr(record.recorded_at);
        if (datetimeAttr) {
            timeEl.setAttribute('datetime', datetimeAttr);
        }
        timeEl.textContent = formatRecordedAt(record.recorded_at);

        meta.appendChild(folioSpan);
        meta.appendChild(timeEl);

        summaryMain.appendChild(titleSpan);
        summaryMain.appendChild(meta);
        summary.appendChild(summaryMain);
        var registroOptions = createRegistroOptions(record);
        if (registroOptions) {
            summary.appendChild(registroOptions);
        }

        var panel = document.createElement('div');
        panel.className = 'aa-expediente-registro-panel';

        var body = document.createElement('div');
        body.className = 'aa-expediente-registro-body';
        body.textContent = record.body || '';

        var thumbEntries = [];
        if (thumbBox) {
            thumbEntries.push({
                box: thumbBox,
                details: details,
                recordId: parseInt(record.id, 10),
                adjuntoId: parseInt(record.adjunto.id, 10),
                kind: 'summary'
            });
        }

        panel.appendChild(body);

        var gallery = buildRecordGallery(record, thumbEntries, details);
        if (gallery) {
            panel.appendChild(gallery);
        }

        details.appendChild(summary);
        details.appendChild(panel);

        if (thumbEntries.length) {
            details._aaThumbEntries = thumbEntries;
        }

        return details;
    }

    /**
     * @param {{expandId?: number|string}} [options]
     */
    function renderRecordsList(options) {
        options = options || {};
        var expandId = options.expandId != null ? parseInt(options.expandId, 10) : 0;
        if (!(expandId > 0)) {
            expandId = 0;
        }

        // MC4c: observer fuera, firmas en vuelo abortadas, caché podado.
        prepareThumbsForRender();

        clearNode(state.recordsRoot);
        if (state.actionsRoot) {
            clearNode(state.actionsRoot);
        }

        if (!state.records.length) {
            var empty = document.createElement('p');
            empty.className = 'text-sm text-gray-500 aa-expediente-registros-empty';
            empty.textContent = 'Aún no hay registros en este expediente';
            state.recordsRoot.appendChild(empty);
            return;
        }

        var list = document.createElement('div');
        list.className = 'aa-expediente-registros-list';
        var thumbEntries = [];
        state.records.forEach(function (record) {
            var shouldOpen = expandId > 0 && parseInt(record.id, 10) === expandId;
            var details = createRecordDetails(record, { open: shouldOpen });
            if (details._aaThumbEntries) {
                details._aaThumbEntries.forEach(function (entry) {
                    thumbEntries.push(entry);
                });
            }
            list.appendChild(details);
        });
        state.recordsRoot.appendChild(list);

        setupThumbObserver(thumbEntries);
    }

    function sortRecordsDesc(records) {
        return records.slice().sort(function (a, b) {
            var ra = String(a.recorded_at || '');
            var rb = String(b.recorded_at || '');
            if (ra !== rb) {
                return ra < rb ? 1 : -1;
            }
            return (b.id || 0) - (a.id || 0);
        });
    }

    function prependRecord(record) {
        // Create recién guardado: normalizar colección (sin claves → []).
        if (record) {
            normalizeIncomingRecord(record);
        }
        state.records = sortRecordsDesc([record].concat(state.records));
        renderRecordsList({ expandId: record && record.id });
    }

    /**
     * Combina el registro existente con los campos recibidos, sin sustituirlo
     * íntegramente. Prioridad de la colección (MC5a): clave `adjuntos`
     * presente (incluso []) = autoritativa; sin ella, clave `adjunto`
     * (incluso null) = autoritativa como singleton (puente MC4c); sin ambas
     * = conservar la colección existente. El alias `adjunto` siempre se
     * deriva de adjuntos[0]. Mantiene posición cronológica y tarjeta abierta.
     *
     * @param {object} record
     */
    function replaceRecord(record) {
        if (!record || !(parseInt(record.id, 10) > 0)) {
            return;
        }
        var id = parseInt(record.id, 10);
        var replaced = false;
        state.records = state.records.map(function (existing) {
            if (parseInt(existing.id, 10) !== id) {
                return existing;
            }
            replaced = true;

            var merged = {};
            Object.keys(existing).forEach(function (key) {
                merged[key] = existing[key];
            });
            Object.keys(record).forEach(function (key) {
                merged[key] = record[key];
            });

            if (Object.prototype.hasOwnProperty.call(record, 'adjuntos')) {
                setRecordAdjuntos(merged, record.adjuntos);
            } else if (Object.prototype.hasOwnProperty.call(record, 'adjunto')) {
                setRecordAdjuntos(merged, record.adjunto ? [record.adjunto] : []);
            } else {
                setRecordAdjuntos(merged, getRecordAdjuntos(existing));
            }
            return merged;
        });
        if (!replaced) {
            return;
        }
        renderRecordsList({ expandId: id });
    }

    function postForm(action, fields) {
        if (!canSendTransportRequest(action)) {
            console.error('[ExpedienteRegistros] transporte incompleto para', action || '(sin action)');
            return Promise.resolve(transportIncompletePayload());
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', resolveNonce());
        Object.keys(fields).forEach(function (key) {
            formData.append(key, fields[key]);
        });

        return fetch(resolveAjaxUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    /**
     * Multipart attach: solo contexto mínimo + upload_operation_id + file.
     * @param {string} action
     * @param {{client_id:string, record_id:string, upload_operation_id:string}} fields
     * @param {Blob} fileBlob
     * @param {string} fileName
     */
    function postAttach(action, fields, fileBlob, fileName) {
        if (!canSendTransportRequest(action)) {
            console.error('[ExpedienteRegistros] transporte incompleto para attach');
            return Promise.resolve(transportIncompletePayload());
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', resolveNonce());
        formData.append('client_id', fields.client_id);
        formData.append('record_id', fields.record_id);
        formData.append('upload_operation_id', fields.upload_operation_id);
        formData.append('file', fileBlob, fileName || 'adjunto.jpg');

        return fetch(resolveAjaxUrl(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    function generateUploadOperationId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        // Fallback UUID v4
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function revokePreviewUrl(url) {
        if (!url) {
            return;
        }
        try {
            if (typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
                URL.revokeObjectURL(url);
            }
        } catch (e) {
            // ignore
        }
    }

    function clearPendingImage(pending) {
        if (!pending) {
            return null;
        }
        revokePreviewUrl(pending.previewUrl);
        pending.previewUrl = '';
        pending.blob = null;
        pending.operationId = '';
        return null;
    }

    function isLikelyHeic(file) {
        if (!file) {
            return false;
        }
        var type = String(file.type || '').toLowerCase();
        if (type === 'image/heic' || type === 'image/heif') {
            return true;
        }
        var name = String(file.name || '').toLowerCase();
        return /\.(heic|heif)$/.test(name);
    }

    function loadImageElement(src) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () {
                resolve(img);
            };
            img.onerror = function () {
                reject(new Error('decode_failed'));
            };
            img.src = src;
        });
    }

    function canvasToJpegBlob(canvas, quality) {
        return new Promise(function (resolve, reject) {
            if (!canvas || typeof canvas.toBlob !== 'function') {
                reject(new Error('canvas_unavailable'));
                return;
            }
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('toblob_failed'));
                    return;
                }
                resolve(blob);
            }, 'image/jpeg', quality);
        });
    }

    function disposeCanvas(canvas) {
        if (!canvas) {
            return;
        }
        try {
            canvas.width = 0;
            canvas.height = 0;
        } catch (e) {
            // ignore
        }
    }

    /**
     * Normaliza a JPEG ≤2048 px y ≤1_048_576 bytes. Nunca devuelve el original.
     * Genera upload_operation_id solo tras preparar OK.
     *
     * @param {File|Blob} file
     * @returns {Promise<{ok:true, pending:object}|{ok:false, message:string}>}
     */
    function prepareExpedienteImage(file) {
        if (!file) {
            return Promise.resolve({
                ok: false,
                message: 'Selecciona una imagen válida.'
            });
        }

        var objectUrl = '';
        var canvas = null;

        function fail(message) {
            revokePreviewUrl(objectUrl);
            disposeCanvas(canvas);
            return { ok: false, message: message };
        }

        var decodePromise;
        if (typeof createImageBitmap === 'function') {
            decodePromise = createImageBitmap(file).then(function (bitmap) {
                return { width: bitmap.width, height: bitmap.height, source: bitmap, kind: 'bitmap' };
            });
        } else {
            objectUrl = URL.createObjectURL(file);
            decodePromise = loadImageElement(objectUrl).then(function (img) {
                return { width: img.naturalWidth || img.width, height: img.naturalHeight || img.height, source: img, kind: 'img' };
            });
        }

        return decodePromise
            .catch(function () {
                if (isLikelyHeic(file)) {
                    throw new Error('heic_unsupported');
                }
                throw new Error('decode_failed');
            })
            .then(function (decoded) {
                var srcW = decoded.width;
                var srcH = decoded.height;
                if (!(srcW > 0) || !(srcH > 0)) {
                    throw new Error('invalid_dimensions');
                }

                var scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(srcW, srcH));
                var targetW = Math.max(1, Math.round(srcW * scale));
                var targetH = Math.max(1, Math.round(srcH * scale));

                canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    throw new Error('canvas_unavailable');
                }

                var qualities = [0.85, 0.75, 0.65, 0.55, 0.45];
                var maxPasses = 8;
                var pass = 0;
                var lastBlob = null;

                function tryEncode() {
                    if (pass >= maxPasses) {
                        return Promise.reject(new Error('too_large'));
                    }
                    pass += 1;
                    canvas.width = targetW;
                    canvas.height = targetH;
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, targetW, targetH);
                    ctx.drawImage(decoded.source, 0, 0, targetW, targetH);

                    var qIndex = 0;

                    function tryQuality() {
                        if (qIndex >= qualities.length) {
                            // Reducir dimensiones y reiniciar calidades (terminación acotada).
                            if (targetW <= 320 && targetH <= 320) {
                                return Promise.reject(new Error('too_large'));
                            }
                            targetW = Math.max(1, Math.round(targetW * 0.75));
                            targetH = Math.max(1, Math.round(targetH * 0.75));
                            return tryEncode();
                        }
                        var quality = qualities[qIndex];
                        qIndex += 1;
                        return canvasToJpegBlob(canvas, quality).then(function (blob) {
                            lastBlob = blob;
                            if (blob.size <= MAX_IMAGE_BYTES) {
                                return blob;
                            }
                            return tryQuality();
                        });
                    }

                    return tryQuality();
                }

                return tryEncode().then(function (blob) {
                    if (!blob || blob.type !== 'image/jpeg' || blob.size < 1 || blob.size > MAX_IMAGE_BYTES) {
                        throw new Error('too_large');
                    }

                    if (decoded.kind === 'bitmap' && typeof decoded.source.close === 'function') {
                        try {
                            decoded.source.close();
                        } catch (e) {
                            // ignore
                        }
                    }

                    disposeCanvas(canvas);
                    canvas = null;
                    revokePreviewUrl(objectUrl);
                    objectUrl = '';

                    var previewUrl = URL.createObjectURL(blob);
                    var operationId = generateUploadOperationId();

                    return {
                        ok: true,
                        pending: {
                            operationId: operationId,
                            blob: blob,
                            previewUrl: previewUrl,
                            width: targetW,
                            height: targetH,
                            byteSize: blob.size
                        }
                    };
                });
            })
            .catch(function (err) {
                var code = err && err.message ? String(err.message) : 'decode_failed';
                if (code === 'heic_unsupported') {
                    return fail(HEIC_UNSUPPORTED_MESSAGE);
                }
                if (code === 'too_large') {
                    return fail('La imagen es demasiado grande. Prueba con otra foto más liviana.');
                }
                return fail('No se pudo procesar la imagen. Prueba con JPG o PNG.');
            });
    }

    function loadRecords() {
        // MC5a: una respuesta posterior a destroy(), cambio de scope o
        // re-init no debe sobrescribir el estado vigente.
        var epoch = thumbs.viewEpoch;
        var scopeKey = state.scopeKey;
        var clientId = state.clientId;

        function isStale() {
            return epoch !== thumbs.viewEpoch || scopeKey !== state.scopeKey;
        }

        state.loading = true;
        renderStatusMessage('Cargando registros...', 'text-sm text-gray-500');

        var listRequest = isPortsMode()
            ? callPort('list')
            : postForm(resolveAction('listRegistros'), { client_id: String(clientId) });

        return listRequest
            .then(function (payload) {
                if (isStale()) {
                    return;
                }
                state.loading = false;
                var result = payload.result;
                if (result && result.success && result.data && Array.isArray(result.data.records)) {
                    state.records = sortRecordsDesc(result.data.records.map(normalizeIncomingRecord));
                    renderRecordsList();
                    return;
                }
                var message = 'No se pudieron cargar los registros.';
                if (result && result.data && result.data.message) {
                    message = String(result.data.message);
                }
                renderError(message);
            })
            .catch(function (err) {
                if (isStale()) {
                    return;
                }
                state.loading = false;
                console.error('[ExpedienteRegistros] list failed:', err);
                renderError('No se pudieron cargar los registros.');
            });
    }

    /**
     * Shared create/edit form. Mode and record_id live in this opening's closure.
     *
     * @param {{mode:string, record?:object, recordId?:number, focusReturnEl?:HTMLElement}} options
     */
    function openRegistroForm(options) {
        options = options || {};
        var mode = options.mode || 'create';
        if (mode === 'edit') {
            if (!isCapEnabled('updateRegistro')) {
                return;
            }
        } else if (!isCapEnabled('createRegistro')) {
            return;
        }

        var record = options.record || null;
        var recordId = options.recordId != null
            ? parseInt(options.recordId, 10)
            : (record && record.id != null ? parseInt(record.id, 10) : 0);
        if (!(recordId > 0)) {
            recordId = 0;
        }
        var focusReturnEl = options.focusReturnEl || null;

        /** @type {{operationId:string, blob:Blob, previewUrl:string, width:number, height:number, byteSize:number}|null} */
        var pendingImage = null;
        /** @type {'idle'|'saving_record'|'uploading_attachment'|'partial_attachment_failed'} */
        var flowState = 'idle';
        var cleanedUp = false;

        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[ExpedienteRegistros] AAAdmin.openModal no disponible');
            return;
        }

        var bodyWrap = document.createElement('div');
        bodyWrap.className = 'aa-expediente-registro-form space-y-3';

        var titleLabel = document.createElement('label');
        titleLabel.className = 'block text-base font-medium text-gray-600';
        titleLabel.setAttribute('for', 'aa-expediente-registro-title');
        titleLabel.textContent = 'Título';

        var titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.id = 'aa-expediente-registro-title';
        titleInput.className = 'aa-expediente-registro-input';
        titleInput.maxLength = 200;
        titleInput.required = true;
        titleInput.value = '';
        if (mode === 'edit' && record) {
            titleInput.value = record.title || '';
        }

        var bodyLabel = document.createElement('label');
        bodyLabel.className = 'block text-base font-medium text-gray-600';
        bodyLabel.setAttribute('for', 'aa-expediente-registro-body');
        bodyLabel.textContent = 'Detalles';

        var bodyInput = document.createElement('textarea');
        bodyInput.id = 'aa-expediente-registro-body';
        bodyInput.className = 'aa-expediente-registro-textarea';
        bodyInput.rows = 6;
        bodyInput.required = true;
        bodyInput.value = '';
        if (mode === 'edit' && record) {
            bodyInput.value = record.body || '';
        }

        var attachEnabled = isCapEnabled('attach');

        var adjuntoBlock = null;
        var fileInput = null;
        var adjuntoTrigger = null;
        var previewWrap = null;
        var previewImg = null;
        var previewMeta = null;
        var removeBtn = null;
        var retryBtn = null;

        if (attachEnabled) {
            adjuntoBlock = document.createElement('div');
            adjuntoBlock.className = 'aa-expediente-adjunto-block';

            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.id = 'aa-expediente-registro-adjunto';
            fileInput.className = 'aa-expediente-adjunto-input';
            fileInput.accept = 'image/jpeg,image/png,image/webp,image/heic,image/heif';

            adjuntoTrigger = document.createElement('label');
            adjuntoTrigger.className = 'aa-expediente-adjunto-trigger';
            adjuntoTrigger.setAttribute('for', 'aa-expediente-registro-adjunto');
            adjuntoTrigger.innerHTML =
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>' +
                '</svg>' +
                '<span>Añadir imagen</span>';

            previewWrap = document.createElement('div');
            previewWrap.className = 'aa-expediente-adjunto-preview-wrap hidden';

            previewImg = document.createElement('img');
            previewImg.className = 'aa-expediente-adjunto-preview';
            previewImg.alt = 'Vista previa de la imagen adjunta';

            previewMeta = document.createElement('p');
            previewMeta.className = 'aa-expediente-adjunto-meta';

            removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'aa-expediente-adjunto-remove';
            removeBtn.textContent = 'Quitar imagen';

            previewWrap.appendChild(previewImg);
            previewWrap.appendChild(previewMeta);
            previewWrap.appendChild(removeBtn);

            adjuntoBlock.appendChild(fileInput);
            adjuntoBlock.appendChild(adjuntoTrigger);
            adjuntoBlock.appendChild(previewWrap);

            retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'aa-expediente-btn-reintentar-imagen hidden';
            retryBtn.textContent = 'Reintentar imagen';
        }

        var errorEl = document.createElement('p');
        errorEl.className = 'aa-expediente-registro-form-error text-sm text-red-600 hidden';
        errorEl.setAttribute('role', 'alert');

        bodyWrap.appendChild(titleLabel);
        bodyWrap.appendChild(titleInput);
        bodyWrap.appendChild(bodyLabel);
        bodyWrap.appendChild(bodyInput);
        if (adjuntoBlock) {
            bodyWrap.appendChild(adjuntoBlock);
        }
        bodyWrap.appendChild(errorEl);
        if (retryBtn) {
            bodyWrap.appendChild(retryBtn);
        }

        var footer = document.createElement('div');
        footer.className = 'flex flex-wrap gap-2 justify-end';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');
        cancelBtn.textContent = 'Cancelar';

        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        window.AAAdmin.openModal({
            title: mode === 'edit' ? 'Editar registro' : 'Nuevo registro',
            body: bodyWrap,
            footer: footer
        });

        function cleanupPendingOnClose() {
            if (cleanedUp) {
                return;
            }
            cleanedUp = true;
            pendingImage = clearPendingImage(pendingImage);
            if (fileInput) {
                fileInput.value = '';
            }
            if (previewWrap) {
                previewWrap.classList.add('hidden');
            }
            if (previewImg) {
                previewImg.removeAttribute('src');
            }
        }

        disarmModalCloseWatcher();
        modalCloseAbort = watchModalClose(function () {
            modalCloseAbort = null;
            cleanupPendingOnClose();
            focusElement(focusReturnEl);
        });

        window.setTimeout(function () {
            titleInput.focus();
        }, 50);

        // Capturado antes de promoteCreateToEdit (create no debe reportarse como updated).
        var savedRecordOutcome = null;

        function showFormError(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }

        function hideRetry() {
            if (!retryBtn) {
                return;
            }
            retryBtn.classList.add('hidden');
            retryBtn.disabled = false;
            retryBtn.textContent = 'Reintentar imagen';
        }

        /**
         * Camino terminal con toast: espera account si hace falta, cierra modal, emite un toast.
         *
         * @param {'created'|'updated'} recordOutcome
         * @param {'none'|'saved'|'failed'} imageOutcome
         * @param {string} failureCode
         */
        function finishWithToast(recordOutcome, imageOutcome, failureCode) {
            var ready = imageOutcome === 'none'
                ? Promise.resolve(UNKNOWN_ACCOUNT)
                : primeAccountStatus();

            ready.then(function (account) {
                var notification = buildSaveNotification({
                    recordOutcome: recordOutcome,
                    imageOutcome: imageOutcome,
                    failureCode: failureCode || '',
                    account: account
                });
                flowState = 'idle';
                closeAfterFullSuccess({ id: recordId });
                emitToast(notification);
            });
        }

        function showPartialAttachFailure(attachResult) {
            var code = attachResult && attachResult.code ? String(attachResult.code) : '';
            // Rechazo comercial: toast + cierre (reintento no puede resolverlo).
            if (code === 'storage_not_included' || code === 'storage_quota_exceeded') {
                finishWithToast(savedRecordOutcome || 'created', 'failed', code);
                return;
            }
            flowState = 'partial_attachment_failed';
            showFormError(messageForAttachFailure(code));
            if (retryBtn) {
                retryBtn.classList.remove('hidden');
            }
            reenableSave();
        }

        function reenableSave() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Guardar';
        }

        function setModalTitle(text) {
            var titleNode = typeof document !== 'undefined'
                ? document.querySelector('#aa-modal-root .aa-modal-title')
                : null;
            if (titleNode) {
                titleNode.textContent = text;
            }
        }

        function renderPendingPreview() {
            if (!previewWrap || !previewImg || !previewMeta) {
                return;
            }
            if (!pendingImage) {
                previewWrap.classList.add('hidden');
                previewImg.removeAttribute('src');
                previewMeta.textContent = '';
                return;
            }
            previewImg.src = pendingImage.previewUrl;
            previewMeta.textContent =
                Math.round(pendingImage.byteSize / 1024) + ' KB · JPEG';
            previewWrap.classList.remove('hidden');
        }

        function removePendingImage() {
            pendingImage = clearPendingImage(pendingImage);
            if (fileInput) {
                fileInput.value = '';
            }
            renderPendingPreview();
            if (flowState === 'partial_attachment_failed') {
                flowState = 'idle';
                errorEl.classList.add('hidden');
                hideRetry();
            }
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                    return;
                }
                removePendingImage();
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                    fileInput.value = '';
                    return;
                }

                errorEl.classList.add('hidden');
                hideRetry();
                if (flowState === 'partial_attachment_failed') {
                    flowState = 'idle';
                }

                var files = fileInput.files;
                var file = files && files.length ? files[0] : null;
                pendingImage = clearPendingImage(pendingImage);
                renderPendingPreview();

                if (!file) {
                    return;
                }

                saveBtn.disabled = true;
                fileInput.disabled = true;

                prepareExpedienteImage(file)
                    .then(function (result) {
                        fileInput.disabled = false;
                        saveBtn.disabled = false;
                        if (!result || !result.ok) {
                            fileInput.value = '';
                            showFormError(
                                (result && result.message) || 'No se pudo procesar la imagen.'
                            );
                            return;
                        }
                        pendingImage = result.pending;
                        renderPendingPreview();
                    })
                    .catch(function (err) {
                        fileInput.disabled = false;
                        saveBtn.disabled = false;
                        fileInput.value = '';
                        console.error('[ExpedienteRegistros] prepare image failed:', err);
                        showFormError('No se pudo procesar la imagen.');
                    });
            });
        }

        function persistRecordInList(saved) {
            if (mode === 'edit') {
                replaceRecord(saved);
            } else {
                prependRecord(saved);
            }
        }

        /**
         * Tras create exitoso: si update está habilitado, promueve a edición
         * textual. Si no, bloquea título/cuerpo y solo permite attach/retry.
         */
        function promoteCreateToEdit(saved) {
            recordId = parseInt(saved.id, 10);
            record = saved;
            if (isCapEnabled('updateRegistro')) {
                mode = 'edit';
                setModalTitle('Editar registro');
                return;
            }
            titleInput.disabled = true;
            bodyInput.disabled = true;
            if (fileInput && !pendingImage) {
                fileInput.disabled = true;
            }
        }

        function closeAfterFullSuccess(saved) {
            disarmModalCloseWatcher();
            cleanupPendingOnClose();
            if (typeof window.AAAdmin.closeModal === 'function') {
                window.AAAdmin.closeModal();
            }
            if (mode === 'edit' && isCapEnabled('updateRegistro')) {
                focusEditButtonById(saved && saved.id);
            }
        }

        function attachPendingImage(savedRecord) {
            if (!attachEnabled || !pendingImage || !pendingImage.blob || !pendingImage.operationId) {
                return Promise.resolve({ ok: true, skipped: true });
            }

            var operationId = pendingImage.operationId;
            var requestedRecordId = parseInt(recordId || (savedRecord && savedRecord.id) || 0, 10);

            flowState = 'uploading_attachment';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Subiendo imagen...';
            if (retryBtn) {
                retryBtn.disabled = true;
            }

            var attachRequest = isPortsMode()
                ? callPort('attach', requestedRecordId, pendingImage.blob, operationId)
                : postAttach(
                    resolveAction('attachRegistro'),
                    {
                        client_id: String(state.clientId),
                        record_id: String(requestedRecordId || ''),
                        upload_operation_id: operationId
                    },
                    pendingImage.blob,
                    'adjunto.jpg'
                );

            return attachRequest.then(function (payload) {
                var result = payload.result;
                if (result && result.success) {
                    // MC4c: validar identidad y DTO público antes de aplicarlo.
                    var responseData = result.data || {};
                    var returnedRecordId = parseInt(responseData.record_id, 10);
                    if (returnedRecordId === requestedRecordId && isValidAdjuntoDto(responseData.adjunto)) {
                        return { ok: true, recordId: returnedRecordId, adjunto: responseData.adjunto };
                    }
                    // Respuesta malformada: tratar como parcial (reintento idempotente).
                    return { ok: false, message: PARTIAL_ATTACH_MESSAGE, code: '' };
                }
                var errData = (result && result.data) || {};
                var errCode = errData.code ? String(errData.code) : '';
                return {
                    ok: false,
                    message: messageForAttachFailure(errCode),
                    code: errCode
                };
            });
        }

        function runAttachRetry() {
            if (!attachEnabled) {
                return;
            }
            if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                return;
            }
            if (!pendingImage || !(recordId > 0)) {
                showFormError(PARTIAL_ATTACH_MESSAGE);
                return;
            }

            // Anticipar account-status en paralelo al reintento (idempotente).
            primeAccountStatus();

            errorEl.classList.add('hidden');
            if (retryBtn) {
                retryBtn.disabled = true;
                retryBtn.textContent = 'Reintentando...';
            }

            attachPendingImage({ id: recordId })
                .then(function (attachResult) {
                    if (attachResult && attachResult.ok) {
                        if (!attachResult.skipped) {
                            applyAdjuntoToRecord(attachResult.recordId, attachResult.adjunto);
                        }
                        hideRetry();
                        finishWithToast(savedRecordOutcome || 'created', 'saved', '');
                        return;
                    }
                    showPartialAttachFailure(attachResult);
                })
                .catch(function (err) {
                    console.error('[ExpedienteRegistros] attach retry failed:', err);
                    showPartialAttachFailure(null);
                });
        }

        if (retryBtn) {
            retryBtn.addEventListener('click', function (event) {
                event.preventDefault();
                runAttachRetry();
            });
        }

        saveBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (flowState === 'saving_record' || flowState === 'uploading_attachment') {
                return;
            }

            // Tras fallo parcial, Guardar reintenta solo la imagen (nunca create/update).
            if (flowState === 'partial_attachment_failed') {
                runAttachRetry();
                return;
            }

            // Create ya persistido sin update: solo attach pendiente, nunca update/re-create.
            if (recordId > 0 && mode !== 'edit' && !isCapEnabled('updateRegistro')) {
                if (pendingImage) {
                    runAttachRetry();
                }
                return;
            }

            errorEl.classList.add('hidden');
            hideRetry();

            var title = String(titleInput.value || '').trim();
            var body = String(bodyInput.value || '').trim();

            if (!title) {
                showFormError('El título es obligatorio.');
                titleInput.focus();
                return;
            }
            if (!body) {
                showFormError('El texto es obligatorio.');
                bodyInput.focus();
                return;
            }

            var draft = { title: title, body: body };
            var saveRequest;

            if (mode === 'edit') {
                if (!isCapEnabled('updateRegistro')) {
                    return;
                }
                if (!(recordId > 0)) {
                    showFormError('Registro no válido.');
                    return;
                }
                saveRequest = isPortsMode()
                    ? callPort('update', recordId, draft)
                    : postForm(resolveAction('updateRegistro'), {
                        client_id: String(state.clientId),
                        title: title,
                        body: body,
                        record_id: String(recordId)
                    });
            } else {
                saveRequest = isPortsMode()
                    ? callPort('create', draft)
                    : postForm(resolveAction('createRegistro'), {
                        client_id: String(state.clientId),
                        title: title,
                        body: body
                    });
            }

            // Anticipar account-status en paralelo al guardado (no bloquea el flujo).
            if (pendingImage) {
                primeAccountStatus();
            }

            flowState = 'saving_record';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
            if (fileInput) {
                fileInput.disabled = true;
            }

            saveRequest
                .then(function (payload) {
                    var result = payload.result;
                    if (!(result && result.success && result.data && result.data.record)) {
                        var message = mode === 'edit'
                            ? 'No se pudo actualizar el registro.'
                            : 'No se pudo guardar el registro.';
                        if (result && result.data && result.data.message) {
                            message = String(result.data.message);
                        }
                        flowState = 'idle';
                        if (fileInput) {
                            fileInput.disabled = false;
                        }
                        showFormError(message);
                        reenableSave();
                        return null;
                    }

                    var saved = result.data.record;
                    // Capturar outcome antes de promover create → edit.
                    var recordOutcome = mode === 'edit' ? 'updated' : 'created';
                    savedRecordOutcome = recordOutcome;

                    // Actualizar lista de inmediato (también ante fallo posterior de imagen).
                    persistRecordInList(saved);

                    if (mode === 'create') {
                        promoteCreateToEdit(saved);
                    } else {
                        recordId = parseInt(saved.id, 10);
                        record = saved;
                    }

                    if (!pendingImage) {
                        finishWithToast(recordOutcome, 'none', '');
                        return null;
                    }

                    return attachPendingImage(saved).then(function (attachResult) {
                        if (fileInput && isCapEnabled('updateRegistro')) {
                            fileInput.disabled = false;
                        }
                        if (attachResult && attachResult.ok) {
                            if (!attachResult.skipped) {
                                applyAdjuntoToRecord(attachResult.recordId, attachResult.adjunto);
                            }
                            finishWithToast(recordOutcome, 'saved', '');
                            return;
                        }
                        showPartialAttachFailure(attachResult);
                    });
                })
                .catch(function (err) {
                    console.error('[ExpedienteRegistros] save failed:', err);
                    flowState = 'idle';
                    if (fileInput) {
                        fileInput.disabled = false;
                    }
                    showFormError(
                        mode === 'edit'
                            ? 'No se pudo actualizar el registro.'
                            : 'No se pudo guardar el registro.'
                    );
                    reenableSave();
                });
        });
    }

    function openCreateForm(focusReturnEl) {
        if (!isCapEnabled('createRegistro')) {
            return;
        }
        openRegistroForm({
            mode: 'create',
            focusReturnEl: focusReturnEl || null
        });
    }

    function openEditForm(recordId, focusReturnEl) {
        if (!isCapEnabled('updateRegistro')) {
            return;
        }
        var record = findRecordById(recordId);
        if (!record) {
            console.error('[ExpedienteRegistros] registro no encontrado en estado');
            return;
        }
        openRegistroForm({
            mode: 'edit',
            record: record,
            recordId: parseInt(record.id, 10),
            focusReturnEl: focusReturnEl || null
        });
    }

    /**
     * @param {{
     *   clientId?:number,
     *   scopeKey?:string,
     *   recordsRoot:HTMLElement,
     *   actionsRoot?:HTMLElement|null,
     *   capabilities?:Object<string,boolean>,
     *   transport?:{ajaxUrl:string, nonce:string, actions:Object<string,string>},
     *   ports?:{
     *     list?:Function,
     *     create?:Function,
     *     update?:Function,
     *     deleteRegistro?:Function,
     *     attach?:Function,
     *     signRead?:Function,
     *     deleteAdjunto?:Function
     *   }
     * }} options
     */
    function init(options) {
        var resolved = resolveInitConfig(options);
        if (!resolved.ok) {
            console.error('[ExpedienteRegistros] init inválido:', resolved.reason);
            return;
        }

        // Solo tras validar: libera montaje previo y adopta roots.
        destroy();

        var config = resolved.config;
        state.clientId = config.clientId;
        state.scopeKey = config.scopeKey;
        state.capabilities = config.capabilities;
        state.ports = config.ports;
        state.transport = config.transport;
        state.recordsRoot = config.recordsRoot;
        state.actionsRoot = config.actionsRoot;
        state.records = [];

        if (isCapEnabled('updateRegistro') || isCapEnabled('deleteRegistro')) {
            bindRegistroOptionsUi();
        }
        loadRecords();
    }

    window.AAAdmin.ExpedienteRegistros = {
        init: init,
        destroy: destroy,
        openRegistroForm: openRegistroForm,
        openCreate: openCreateForm,
        __test__: {
            createRecordDetails: createRecordDetails,
            toDatetimeAttr: toDatetimeAttr,
            formatRecordedAt: formatRecordedAt,
            sortRecordsDesc: sortRecordsDesc,
            renderRecordsList: renderRecordsList,
            prependRecord: prependRecord,
            replaceRecord: replaceRecord,
            findRecordById: findRecordById,
            prepareExpedienteImage: prepareExpedienteImage,
            generateUploadOperationId: generateUploadOperationId,
            clearPendingImage: clearPendingImage,
            postAttach: postAttach,
            applyAdjuntoToRecord: applyAdjuntoToRecord,
            normalizeAdjuntosList: normalizeAdjuntosList,
            normalizeIncomingRecord: normalizeIncomingRecord,
            getRecordAdjuntos: getRecordAdjuntos,
            loadRecords: loadRecords,
            requestThumbFor: requestThumbFor,
            requestSignedUrl: requestSignedUrl,
            handleThumbImgError: handleThumbImgError,
            resolveSelectedAdjuntoId: resolveSelectedAdjuntoId,
            selectGalleryAdjunto: selectGalleryAdjunto,
            openAdjuntoViewer: openAdjuntoViewer,
            pickSelectionAfterDelete: pickSelectionAfterDelete,
            confirmAndDeleteAdjunto: confirmAndDeleteAdjunto,
            confirmAndDeleteRegistro: confirmAndDeleteRegistro,
            deleteRecordConfirmMessage: deleteRecordConfirmMessage,
            removeRecordFromState: removeRecordFromState,
            DELETE_IMAGE_CONFIRM: DELETE_IMAGE_CONFIRM,
            DELETE_RECORD_CONFIRM_EMPTY: DELETE_RECORD_CONFIRM_EMPTY,
            pruneThumbState: pruneThumbState,
            invalidateThumbForRecord: invalidateThumbForRecord,
            thumbKey: thumbKey,
            variantForKind: variantForKind,
            READ_VARIANTS: READ_VARIANTS,
            isValidAdjuntoDto: isValidAdjuntoDto,
            destroy: destroy,
            getThumbs: function () { return thumbs; },
            MAX_IMAGE_BYTES: MAX_IMAGE_BYTES,
            MAX_IMAGE_EDGE: MAX_IMAGE_EDGE,
            HEIC_UNSUPPORTED_MESSAGE: HEIC_UNSUPPORTED_MESSAGE,
            PARTIAL_ATTACH_MESSAGE: PARTIAL_ATTACH_MESSAGE,
            UNKNOWN_ACCOUNT: UNKNOWN_ACCOUNT,
            PAST_DUE_SUCCESS_FALLBACK: PAST_DUE_SUCCESS_FALLBACK,
            IMAGE_SAVED_MESSAGE: IMAGE_SAVED_MESSAGE,
            STORAGE_NOT_INCLUDED_TOAST_MESSAGE: STORAGE_NOT_INCLUDED_TOAST_MESSAGE,
            STORAGE_QUOTA_FREEMIUM_MESSAGE: STORAGE_QUOTA_FREEMIUM_MESSAGE,
            STORAGE_QUOTA_PAST_DUE_MESSAGE: STORAGE_QUOTA_PAST_DUE_MESSAGE,
            STORAGE_QUOTA_PRO_MESSAGE: STORAGE_QUOTA_PRO_MESSAGE,
            STORAGE_QUOTA_GENERIC_MESSAGE: STORAGE_QUOTA_GENERIC_MESSAGE,
            resolveAccount: resolveAccount,
            buildSaveNotification: buildSaveNotification,
            messageForAttachFailure: messageForAttachFailure,
            urlForToastTarget: urlForToastTarget,
            primeAccountStatus: primeAccountStatus,
            emitToast: emitToast,
            resetAccountPromise: function () { accountPromise = null; },
            THUMB_ERROR_MESSAGE: THUMB_ERROR_MESSAGE,
            isCapEnabled: isCapEnabled,
            normalizeCapabilities: normalizeCapabilities,
            resolveInitConfig: resolveInitConfig,
            CAPABILITY_KEYS: CAPABILITY_KEYS,
            getState: function () { return state; },
            setState: function (partial) {
                Object.keys(partial || {}).forEach(function (key) {
                    state[key] = partial[key];
                });
            }
        }
    };
})();
