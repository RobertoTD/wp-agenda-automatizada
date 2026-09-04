'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/settings/canonical-family-toggles.js'
);

function createEl(attrs) {
    const el = {
        id: attrs.id || '',
        checked: !!attrs.checked,
        disabled: !!attrs.disabled,
        attributes: {},
        children: [],
        parentElement: attrs.parentElement || null,
        textContent: attrs.textContent || '',
        className: attrs.className || '',
        href: attrs.href || '',
        firstChild: null,
        _listeners: {},
        matches(sel) {
            if (sel === '[data-aa-canonical-family-toggle]') {
                return !!this.attributes['data-aa-canonical-family-toggle']
                    || sel in this;
            }
            return false;
        },
        getAttribute(name) {
            return this.attributes[name] == null ? null : String(this.attributes[name]);
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        removeAttribute(name) {
            delete this.attributes[name];
        },
        closest(sel) {
            if (sel === '[data-aa-canonical-family-row]') {
                return this.parentElement;
            }
            return null;
        },
        querySelector(sel) {
            if (sel === '[data-aa-family-status]') {
                return this._status || null;
            }
            return null;
        },
        appendChild(child) {
            this.children.push(child);
            if (!this.firstChild) {
                this.firstChild = child;
            }
            child.parentElement = this;
            return child;
        },
        removeChild(child) {
            const i = this.children.indexOf(child);
            if (i >= 0) {
                this.children.splice(i, 1);
            }
            this.firstChild = this.children[0] || null;
            return child;
        },
        addEventListener(type, fn) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(fn);
        }
    };
    Object.keys(attrs).forEach((k) => {
        if (k === 'checked' || k === 'disabled' || k === 'id' || k === 'parentElement' || k === 'textContent' || k === 'className') {
            return;
        }
        el.attributes[k] = attrs[k];
    });
    if (attrs['data-aa-canonical-family-toggle'] !== undefined) {
        el.matches = function (sel) {
            return sel === '[data-aa-canonical-family-toggle]';
        };
    }
    return el;
}

function loadHarness(options) {
    const fetchCalls = [];
    const postMessages = [];
    let fetchImpl = options.fetchImpl;

    const statusFinance = createEl({ textContent: '' });
    const statusArchive = createEl({ textContent: '' });

    const rowFinance = createEl({ 'data-aa-canonical-family-row': '', 'data-family-key': 'finance' });
    rowFinance._status = statusFinance;
    const rowArchive = createEl({ 'data-aa-canonical-family-row': '', 'data-family-key': 'archive' });
    rowArchive._status = statusArchive;

    const toggleFinance = createEl({
        'data-aa-canonical-family-toggle': '',
        'data-family-key': 'finance',
        checked: false,
        parentElement: rowFinance
    });
    const toggleArchive = createEl({
        'data-aa-canonical-family-toggle': '',
        'data-family-key': 'archive',
        checked: false,
        parentElement: rowArchive
    });

    const nav = createEl({ id: 'aa-canonical-record-types-nav' });
    nav.children = [];
    Object.defineProperty(nav, 'firstChild', {
        get() {
            return this.children[0] || null;
        },
        set() {}
    });

    const root = createEl({ id: 'aa-canonical-record-types-root' });
    let rootChangeHandler = null;
    root.addEventListener = function (type, fn) {
        if (type === 'change') {
            rootChangeHandler = fn;
        }
    };

    const elements = {
        'aa-canonical-record-types-root': root,
        'aa-canonical-record-types-nav': nav
    };

    const document = {
        readyState: 'complete',
        getElementById(id) {
            return elements[id] || null;
        },
        createElement(tag) {
            return createEl({ tag });
        },
        addEventListener() {}
    };

    const parent = {
        postMessage(payload, origin) {
            postMessages.push({ payload, origin });
        }
    };

    const windowObj = {
        AA_CANONICAL_FAMILY_ENABLED: {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            action: 'aa_update_canonical_family_enabled',
            nonce: 'test-nonce',
            targetOrigin: 'https://example.test'
        },
        location: { origin: 'https://example.test' },
        parent,
        fetch(url, init) {
            fetchCalls.push({ url, init });
            return fetchImpl(url, init);
        }
    };

    const code = fs.readFileSync(jsPath, 'utf8');
    vm.runInNewContext(code, {
        window: windowObj,
        document,
        URL,
        URLSearchParams,
        console
    });

    return {
        windowObj,
        toggleFinance,
        toggleArchive,
        rowFinance,
        rowArchive,
        statusFinance,
        statusArchive,
        nav,
        rootChangeHandler,
        fetchCalls,
        postMessages,
        fire(toggle) {
            rootChangeHandler({ target: toggle, preventDefault() {} });
        },
        setFetch(fn) {
            fetchImpl = fn;
        }
    };
}

function okResponse(data) {
    return Promise.resolve({
        ok: true,
        text() {
            return Promise.resolve(JSON.stringify({ success: true, data }));
        }
    });
}

function errResponse() {
    return Promise.resolve({
        ok: false,
        text() {
            return Promise.resolve(JSON.stringify({
                success: false,
                data: { code: 'persistence_failed', message: 'x' }
            }));
        }
    });
}

describe('canonical-family-toggles', () => {
    it('change dispara una sola petición con payload exacto', async () => {
        const h = loadHarness({
            fetchImpl: () => okResponse({
                family_key: 'finance',
                is_enabled: true,
                changed: true,
                nav: [{
                    family_key: 'finance',
                    label: 'Finanzas',
                    url: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance&variant=general'
                }]
            })
        });

        h.toggleFinance.checked = true;
        h.fire(h.toggleFinance);
        assert.equal(h.fetchCalls.length, 1);
        assert.equal(h.toggleFinance.disabled, true);
        assert.equal(h.toggleFinance.getAttribute('aria-busy'), 'true');
        assert.equal(h.statusFinance.textContent, 'Guardando…');

        const body = h.fetchCalls[0].init.body;
        assert.ok(body.includes('action=aa_update_canonical_family_enabled'));
        assert.ok(body.includes('nonce=test-nonce'));
        assert.ok(body.includes('family_key=finance'));
        assert.ok(body.includes('enabled=1'));
        assert.equal(h.fetchCalls[0].init.credentials, 'same-origin');

        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        assert.equal(h.toggleFinance.checked, true);
        assert.equal(h.toggleFinance.disabled, false);
        assert.equal(h.statusFinance.textContent, 'Activado');
        assert.equal(h.postMessages.length, 1);
        assert.equal(h.postMessages[0].origin, 'https://example.test');
        assert.equal(h.postMessages[0].payload.type, 'aa-canonical-family-enabled-changed');
        assert.equal(h.nav.children.length, 1);
    });

    it('fallo restaura estado anterior', async () => {
        const h = loadHarness({ fetchImpl: () => errResponse() });
        h.toggleFinance.checked = true;
        h.fire(h.toggleFinance);
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(h.toggleFinance.checked, false);
        assert.equal(h.statusFinance.textContent, 'No se pudo guardar. Intenta de nuevo.');
        assert.equal(h.toggleFinance.disabled, false);
        assert.equal(h.postMessages.length, 0);
    });

    it('no segunda petición misma familia en vuelo', () => {
        let resolveFetch;
        const h = loadHarness({
            fetchImpl: () => new Promise((resolve) => {
                resolveFetch = resolve;
            })
        });
        h.toggleFinance.checked = true;
        h.fire(h.toggleFinance);
        assert.equal(h.fetchCalls.length, 1);
        h.toggleFinance.checked = false;
        h.fire(h.toggleFinance);
        assert.equal(h.fetchCalls.length, 1);
        assert.equal(h.toggleFinance.checked, true);
        resolveFetch(okResponse({
            family_key: 'finance',
            is_enabled: true,
            changed: true,
            nav: []
        }));
    });

    it('locks independientes entre familias', () => {
        const pending = [];
        const h = loadHarness({
            fetchImpl: () => new Promise((resolve) => {
                pending.push(resolve);
            })
        });
        h.toggleFinance.checked = true;
        h.fire(h.toggleFinance);
        h.toggleArchive.checked = true;
        h.fire(h.toggleArchive);
        assert.equal(h.fetchCalls.length, 2);
    });

    it('servidor domina estado final', async () => {
        const h = loadHarness({
            fetchImpl: () => okResponse({
                family_key: 'finance',
                is_enabled: false,
                changed: false,
                nav: []
            })
        });
        h.toggleFinance.checked = true;
        h.fire(h.toggleFinance);
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(h.toggleFinance.checked, false);
        assert.equal(h.statusFinance.textContent, 'Desactivado');
    });

    it('rechaza URL nav no allowlisted', () => {
        const h = loadHarness({
            fetchImpl: () => okResponse({
                family_key: 'finance',
                is_enabled: true,
                changed: true,
                nav: [{
                    family_key: 'finance',
                    label: 'Finanzas',
                    url: 'https://evil.test/phish'
                }]
            })
        });
        h.toggleFinance.checked = true;
        h.fire(h.toggleFinance);
        return new Promise((resolve) => setTimeout(resolve, 0)).then(() =>
            new Promise((r) => setTimeout(r, 0))
        ).then(() => {
            assert.equal(h.nav.children.length, 0);
        });
    });
});
