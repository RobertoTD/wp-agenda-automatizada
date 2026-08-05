'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it, afterEach } = require('node:test');

const jsPath = path.join(__dirname, '../../includes/admin/ui/legal-gate/legal-gate.js');
const jsSrc = fs.readFileSync(jsPath, 'utf8');

function makeEl(initial) {
    const el = Object.assign({
        textContent: '',
        disabled: false,
        checked: false,
        classList: {
            _set: new Set(),
            add(cls) { this._set.add(cls); },
            remove(cls) { this._set.delete(cls); },
            contains(cls) { return this._set.has(cls); }
        },
        _listeners: {},
        addEventListener(type, fn) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(fn);
        },
        click() {
            (this._listeners.click || []).forEach((fn) => fn({ preventDefault() {}, stopPropagation() {} }));
        },
        change() {
            (this._listeners.change || []).forEach((fn) => fn({ preventDefault() {}, stopPropagation() {} }));
        }
    }, initial || {});
    return el;
}

function flush() {
    return new Promise((resolve) => setImmediate(resolve));
}

function loadGate(options) {
    options = options || {};
    const withAccept = options.withAccept !== false;
    const isDual = !!options.dual;
    const termsConsent = makeEl({ checked: !!options.checked });
    const privacyConsent = makeEl({ checked: !!options.privacyChecked });
    const acceptBtn = makeEl({ disabled: true, textContent: 'Aceptar y continuar' });
    const retryBtn = makeEl({ disabled: false, textContent: 'Reintentar' });
    const errorEl = makeEl({});

    const byId = {
        'aa-legal-gate-root': makeEl({}),
        'aa-legal-gate-consent': withAccept ? termsConsent : null,
        'aa-legal-gate-privacy-consent': withAccept && isDual ? privacyConsent : null,
        'aa-legal-gate-accept': withAccept ? acceptBtn : null,
        'aa-legal-gate-retry': withAccept ? null : retryBtn,
        'aa-legal-gate-error': errorEl
    };

    let reloadCount = 0;
    const fetches = [];

    globalThis.window = {
        AA_LEGAL_GATE_DATA: {
            ajaxUrl: 'https://agenda.test/wp-admin/admin-ajax.php',
            statusAction: 'aa_get_legal_gate_status',
            acceptAction: 'aa_accept_agenda_terms',
            acceptDualAction: 'aa_accept_agenda_privacy_and_terms',
            nonce: 'nonce-test',
            termsVersion: options.termsVersion || '2026-08-03.1',
            privacyVersion: options.privacyVersion || '2026-08-04.1',
            canAccept: withAccept && !isDual && options.canAccept !== false,
            canAcceptDual: withAccept && isDual,
            initialStatus: options.status || (isDual ? 'needs_privacy_and_terms' : 'needs_terms')
        },
        location: {
            reload() { reloadCount += 1; }
        }
    };
    globalThis.document = {
        getElementById(id) { return byId[id] || null; },
        addEventListener() {}
    };
    globalThis.fetch = function (url, init) {
        fetches.push({ url, init });
        const responder = options.fetchImpl || function () {
            return Promise.resolve({
                ok: true,
                json() {
                    return Promise.resolve({ success: true, data: { already_accepted: false } });
                }
            });
        };
        return responder(url, init, fetches.length);
    };

    // eslint-disable-next-line no-eval
    eval(jsSrc);

    return {
        consent: termsConsent,
        privacyConsent,
        acceptBtn,
        retryBtn,
        errorEl,
        fetches,
        get reloadCount() { return reloadCount; }
    };
}

describe('legal-gate UI', () => {
    afterEach(() => {
        delete globalThis.window;
        delete globalThis.document;
        delete globalThis.fetch;
    });

    it('keeps accept disabled while checkbox unchecked', () => {
        const ui = loadGate({ checked: false });
        assert.equal(ui.acceptBtn.disabled, true);
        ui.consent.checked = true;
        ui.consent.change();
        assert.equal(ui.acceptBtn.disabled, false);
        ui.consent.checked = false;
        ui.consent.change();
        assert.equal(ui.acceptBtn.disabled, true);
    });

    it('dual requires both checkboxes before enabling accept', () => {
        const ui = loadGate({ dual: true, checked: false, privacyChecked: false });
        assert.equal(ui.acceptBtn.disabled, true);

        ui.privacyConsent.checked = true;
        ui.privacyConsent.change();
        assert.equal(ui.acceptBtn.disabled, true);

        ui.consent.checked = true;
        ui.consent.change();
        assert.equal(ui.acceptBtn.disabled, false);

        ui.privacyConsent.checked = false;
        ui.privacyConsent.change();
        assert.equal(ui.acceptBtn.disabled, true);
    });

    it('posts shown version and blocks double submit', async () => {
        let resolveFetch;
        const ui = loadGate({
            checked: true,
            fetchImpl() {
                return new Promise((resolve) => {
                    resolveFetch = resolve;
                });
            }
        });
        ui.consent.checked = true;
        ui.consent.change();
        ui.acceptBtn.click();
        ui.acceptBtn.click();

        assert.equal(ui.fetches.length, 1);
        const body = ui.fetches[0].init.body;
        assert.match(body, /action=aa_accept_agenda_terms/);
        assert.match(body, /terms_consent=1/);
        assert.match(body, /terms_document_version=2026-08-03\.1/);
        assert.doesNotMatch(body, /account_id/);
        assert.doesNotMatch(body, /installation_id/);
        assert.doesNotMatch(body, /subscription_request_id/);
        assert.doesNotMatch(body, /wp_user_id/);
        assert.equal(ui.acceptBtn.disabled, true);

        resolveFetch({
            ok: true,
            json() {
                return Promise.resolve({ success: true, data: {} });
            }
        });
        await flush();
        assert.equal(ui.reloadCount, 1);
    });

    it('dual posts both versions without wp_user_id and reloads on success', async () => {
        let resolveFetch;
        const ui = loadGate({
            dual: true,
            checked: true,
            privacyChecked: true,
            fetchImpl() {
                return new Promise((resolve) => {
                    resolveFetch = resolve;
                });
            }
        });
        ui.privacyConsent.checked = true;
        ui.consent.checked = true;
        ui.privacyConsent.change();
        ui.consent.change();
        ui.acceptBtn.click();
        ui.acceptBtn.click();

        assert.equal(ui.fetches.length, 1);
        const body = ui.fetches[0].init.body;
        assert.match(body, /action=aa_accept_agenda_privacy_and_terms/);
        assert.match(body, /privacy_consent=1/);
        assert.match(body, /terms_consent=1/);
        assert.match(body, /privacy_document_version=2026-08-04\.1/);
        assert.match(body, /terms_document_version=2026-08-03\.1/);
        assert.doesNotMatch(body, /wp_user_id/);
        assert.equal(ui.acceptBtn.disabled, true);

        resolveFetch({
            ok: true,
            json() {
                return Promise.resolve({ success: true, data: { already_accepted: true } });
            }
        });
        await flush();
        assert.equal(ui.reloadCount, 1);
    });

    it('keeps gate on accept error and re-enables submit', async () => {
        const ui = loadGate({
            checked: true,
            fetchImpl() {
                return Promise.resolve({
                    ok: false,
                    json() {
                        return Promise.resolve({
                            success: false,
                            data: { message: 'falló', code: 'legal_gate_backend_error' }
                        });
                    }
                });
            }
        });

        ui.consent.checked = true;
        ui.consent.change();
        ui.acceptBtn.click();
        await flush();

        assert.equal(ui.reloadCount, 0);
        assert.equal(ui.errorEl.classList.contains('is-visible'), true);
        assert.match(ui.errorEl.textContent, /falló/);
        assert.equal(ui.acceptBtn.disabled, false);
        assert.equal(ui.acceptBtn.textContent, 'Aceptar y continuar');
    });

    it('dual outdated privacy shows message and reloads', async () => {
        const ui = loadGate({
            dual: true,
            checked: true,
            privacyChecked: true,
            fetchImpl() {
                return Promise.resolve({
                    ok: false,
                    json() {
                        return Promise.resolve({
                            success: false,
                            data: {
                                message: 'outdated',
                                code: 'privacy_notice_version_outdated'
                            }
                        });
                    }
                });
            }
        });

        ui.privacyConsent.checked = true;
        ui.consent.checked = true;
        ui.privacyConsent.change();
        ui.consent.change();
        ui.acceptBtn.click();
        await flush();

        assert.match(ui.errorEl.textContent, /documentos legales se actualizaron/i);
        assert.equal(ui.acceptBtn.disabled, false);

        await new Promise((resolve) => setTimeout(resolve, 1300));
        assert.equal(ui.reloadCount, 1);
    });

    it('dual partial acceptance stays blocked and schedules reload', async () => {
        const ui = loadGate({
            dual: true,
            checked: true,
            privacyChecked: true,
            fetchImpl() {
                return Promise.resolve({
                    ok: false,
                    json() {
                        return Promise.resolve({
                            success: false,
                            data: {
                                message: 'partial',
                                code: 'partial_acceptance_exists'
                            }
                        });
                    }
                });
            }
        });

        ui.privacyConsent.checked = true;
        ui.consent.checked = true;
        ui.privacyConsent.change();
        ui.consent.change();
        ui.acceptBtn.click();
        await flush();

        assert.equal(ui.errorEl.classList.contains('is-visible'), true);
        assert.match(ui.errorEl.textContent, /estado legal cambió/i);
        await new Promise((resolve) => setTimeout(resolve, 1300));
        assert.equal(ui.reloadCount, 1);
    });

    it('retry re-queries status and reloads on ready', async () => {
        const ui = loadGate({
            withAccept: false,
            status: 'privacy_required',
            fetchImpl() {
                return Promise.resolve({
                    ok: true,
                    json() {
                        return Promise.resolve({ success: true, data: { status: 'ready' } });
                    }
                });
            }
        });

        ui.retryBtn.click();
        await flush();
        assert.equal(ui.reloadCount, 1);
        assert.match(ui.fetches[0].init.body, /aa_get_legal_gate_status/);
    });

    it('source omits internal ids and auth secrets', () => {
        assert.doesNotMatch(jsSrc, /\baccount_id\b|\binstallation_id\b|\bsubscription_request_id\b|\bclient_secret\b|\bhmac\b|\bwp_user_id\b/i);
    });
});
