/**
 * Expediente Registros Canonical Adapter (C1b).
 *
 * Factory sin efectos secundarios: convierte AA_EXPEDIENTE_DETAIL_DATA (o un
 * config equivalente) en { scopeKey, capabilities, ports } para el renderer.
 * No monta DOM, no autoejecuta, no lee globals legacy, no hardcodea actions.
 *
 * API: AAAdmin.ExpedienteRegistrosCanonicalAdapter.build(config)
 */
(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var ACTION_KEYS = [
        'listRegistros',
        'createRegistro',
        'updateRegistro',
        'attachRegistro',
        'signAdjuntoRead',
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

    /**
     * Decimal positivo canónico (alineado a AA_Expediente_Id_Policy).
     * @param {*} value
     * @returns {string|null} representación estable para POST
     */
    function normalizeExpedienteId(value) {
        if (typeof value === 'number') {
            if (!Number.isInteger(value) || value < 1) {
                return null;
            }
            return String(value);
        }
        if (typeof value !== 'string') {
            return null;
        }
        if (!/^[1-9][0-9]{0,18}$/.test(value)) {
            return null;
        }
        var asInt = parseInt(value, 10);
        if (!(asInt > 0) || String(asInt) !== value) {
            return null;
        }
        return value;
    }

    /**
     * Página positiva canónica (≥ 1). Sin fallback silencioso a 1.
     * @param {*} value
     * @returns {number|null}
     */
    function normalizeRecordsPage(value) {
        if (typeof value === 'number') {
            if (!Number.isInteger(value) || value < 1) {
                return null;
            }
            return value;
        }
        if (typeof value !== 'string') {
            return null;
        }
        if (!/^[1-9][0-9]{0,18}$/.test(value)) {
            return null;
        }
        var page = parseInt(value, 10);
        if (!(page > 0) || String(page) !== value) {
            return null;
        }
        return page;
    }

    /**
     * @param {*} raw
     * @returns {Object<string,string>|null}
     */
    function normalizeActions(raw) {
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
            return null;
        }
        var keys = Object.keys(raw);
        if (keys.length !== ACTION_KEYS.length) {
            return null;
        }
        var i;
        for (i = 0; i < keys.length; i++) {
            if (ACTION_KEYS.indexOf(keys[i]) === -1) {
                return null;
            }
        }
        var out = {};
        for (i = 0; i < ACTION_KEYS.length; i++) {
            var key = ACTION_KEYS[i];
            if (!Object.prototype.hasOwnProperty.call(raw, key)) {
                return null;
            }
            var value = raw[key];
            if (typeof value !== 'string' || value.trim() === '') {
                return null;
            }
            out[key] = value;
        }
        return out;
    }

    /**
     * Contrato exacto C1a (sin reutilizar el monolito).
     * @param {*} raw
     * @returns {Object<string,boolean>|null}
     */
    function normalizeCapabilities(raw) {
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
            return null;
        }
        var keys = Object.keys(raw);
        if (keys.length !== CAPABILITY_KEYS.length) {
            return null;
        }
        var i;
        for (i = 0; i < keys.length; i++) {
            if (CAPABILITY_KEYS.indexOf(keys[i]) === -1) {
                return null;
            }
        }
        var out = {};
        for (i = 0; i < CAPABILITY_KEYS.length; i++) {
            var key = CAPABILITY_KEYS[i];
            if (!Object.prototype.hasOwnProperty.call(raw, key)) {
                return null;
            }
            if (typeof raw[key] !== 'boolean') {
                return null;
            }
            out[key] = raw[key];
        }
        if (out.attach === true && out.createRegistro !== true && out.updateRegistro !== true) {
            return null;
        }
        if (out.deleteAdjunto === true && out.signRead !== true) {
            return null;
        }
        return out;
    }

    /**
     * @param {*} config
     * @returns {{
     *   ajaxUrl:string,
     *   nonce:string,
     *   expedienteId:string,
     *   recordsPage:number,
     *   scopeKey:string,
     *   actions:Object<string,string>,
     *   capabilities:Object<string,boolean>
     * }|null}
     */
    function normalizeConfig(config) {
        if (!config || typeof config !== 'object' || Array.isArray(config)) {
            return null;
        }

        if (typeof config.ajaxUrl !== 'string' || config.ajaxUrl.trim() === '') {
            return null;
        }
        if (typeof config.nonce !== 'string' || config.nonce === '') {
            return null;
        }

        var expedienteId = normalizeExpedienteId(config.expedienteId);
        if (expedienteId === null) {
            return null;
        }

        var recordsPage = normalizeRecordsPage(config.recordsPage);
        if (recordsPage === null) {
            return null;
        }

        if (typeof config.scopeKey !== 'string' || config.scopeKey.trim() === '') {
            return null;
        }

        var actions = normalizeActions(config.actions);
        if (!actions) {
            return null;
        }

        var capabilities = normalizeCapabilities(config.capabilities);
        if (!capabilities) {
            return null;
        }

        return {
            ajaxUrl: config.ajaxUrl.trim(),
            nonce: config.nonce,
            expedienteId: expedienteId,
            recordsPage: recordsPage,
            scopeKey: config.scopeKey.trim(),
            actions: actions,
            capabilities: capabilities
        };
    }

    /**
     * @param {string} ajaxUrl
     * @param {string} nonce
     * @param {string} action
     * @param {Object<string, string>} fields
     * @param {AbortSignal=} signal
     * @returns {Promise<{httpStatus:number, result:object}>}
     */
    function postJsonForm(ajaxUrl, nonce, action, fields, signal) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', nonce);
        Object.keys(fields).forEach(function (key) {
            formData.append(key, fields[key]);
        });
        var options = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        };
        if (signal) {
            options.signal = signal;
        }
        return fetch(ajaxUrl, options).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    /**
     * @param {{
     *   ajaxUrl:string,
     *   nonce:string,
     *   expedienteId:string,
     *   recordsPage:number,
     *   actions:Object<string,string>
     * }} ctx
     */
    function buildPorts(ctx) {
        var ajaxUrl = ctx.ajaxUrl;
        var nonce = ctx.nonce;
        var expedienteId = ctx.expedienteId;
        var recordsPage = ctx.recordsPage;
        var actions = ctx.actions;

        return {
            list: function () {
                return postJsonForm(ajaxUrl, nonce, actions.listRegistros, {
                    expediente_id: expedienteId,
                    page: String(recordsPage)
                });
            },
            create: function (draft) {
                draft = draft || {};
                return postJsonForm(ajaxUrl, nonce, actions.createRegistro, {
                    expediente_id: expedienteId,
                    title: draft.title == null ? '' : String(draft.title),
                    body: draft.body == null ? '' : String(draft.body)
                });
            },
            update: function (recordId, draft) {
                draft = draft || {};
                return postJsonForm(ajaxUrl, nonce, actions.updateRegistro, {
                    expediente_id: expedienteId,
                    record_id: String(recordId || ''),
                    title: draft.title == null ? '' : String(draft.title),
                    body: draft.body == null ? '' : String(draft.body)
                });
            },
            attach: function (recordId, fileBlob, uploadOperationId) {
                var formData = new FormData();
                formData.append('action', actions.attachRegistro);
                formData.append('_wpnonce', nonce);
                formData.append('expediente_id', expedienteId);
                formData.append('record_id', String(recordId || ''));
                formData.append('upload_operation_id', uploadOperationId);
                formData.append('file', fileBlob, 'adjunto.jpg');
                return fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().then(function (result) {
                        return { httpStatus: response.status, result: result };
                    });
                });
            },
            signRead: function (recordId, attachmentId, variant, signal) {
                return postJsonForm(
                    ajaxUrl,
                    nonce,
                    actions.signAdjuntoRead,
                    {
                        expediente_id: expedienteId,
                        record_id: String(recordId),
                        attachment_id: String(attachmentId),
                        variant: variant
                    },
                    signal
                );
            },
            deleteAdjunto: function (recordId, attachmentId) {
                return postJsonForm(ajaxUrl, nonce, actions.deleteAdjunto, {
                    expediente_id: expedienteId,
                    record_id: String(recordId),
                    attachment_id: String(attachmentId)
                });
            }
        };
    }

    /**
     * @param {*} config
     * @returns {{scopeKey:string, capabilities:Object<string,boolean>, ports:Object}|null}
     */
    function build(config) {
        var normalized = normalizeConfig(config);
        if (!normalized) {
            return null;
        }

        return {
            scopeKey: normalized.scopeKey,
            capabilities: {
                createRegistro: normalized.capabilities.createRegistro,
                updateRegistro: normalized.capabilities.updateRegistro,
                deleteRegistro: normalized.capabilities.deleteRegistro,
                attach: normalized.capabilities.attach,
                signRead: normalized.capabilities.signRead,
                deleteAdjunto: normalized.capabilities.deleteAdjunto
            },
            ports: buildPorts({
                ajaxUrl: normalized.ajaxUrl,
                nonce: normalized.nonce,
                expedienteId: normalized.expedienteId,
                recordsPage: normalized.recordsPage,
                actions: normalized.actions
            })
        };
    }

    window.AAAdmin.ExpedienteRegistrosCanonicalAdapter = {
        build: build
    };
})();
