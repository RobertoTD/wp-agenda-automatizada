'use strict';

/**
 * C1c2 — onCreateComplete en ExpedienteRegistros.
 * Ejecutar: node --test tests/js/expedienteRegistrosOnCreateComplete.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('path');
const vm = require('node:vm');

const moduleSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/clients/expediente-registros.js'),
    'utf8'
);

const CAPS = {
    createRegistro: true,
    updateRegistro: false,
    deleteRegistro: false,
    attach: true,
    signRead: true,
    deleteAdjunto: true
};

function wait() {
    return new Promise((r) => setImmediate(r));
}

function createEl(tag) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: '',
        type: '',
        textContent: '',
        value: '',
        disabled: false,
        children: [],
        attributes: Object.create(null),
        parentNode: null,
        files: null,
        width: 0,
        height: 0,
        classList: null,
        _listeners: Object.create(null),
        setAttribute(name, value) { el.attributes[name] = String(value); },
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(el.attributes, name)
                ? el.attributes[name]
                : null;
        },
        removeAttribute(name) { delete el.attributes[name]; },
        appendChild(child) {
            child.parentNode = el;
            el.children.push(child);
            return child;
        },
        removeChild(child) {
            el.children = el.children.filter((c) => c !== child);
            child.parentNode = null;
            return child;
        },
        querySelector(sel) {
            return el.querySelectorAll(sel)[0] || null;
        },
        querySelectorAll(sel) {
            const out = [];
            function walk(node) {
                (node.children || []).forEach((child) => {
                    const cn = child.className || '';
                    const id = child.id || '';
                    if (sel.charAt(0) === '#' && id === sel.slice(1)) out.push(child);
                    if (sel.charAt(0) === '.' && cn.indexOf(sel.slice(1)) !== -1) out.push(child);
                    if (sel === 'img' && child.tagName === 'IMG') out.push(child);
                    walk(child);
                });
            }
            walk(el);
            return out;
        },
        addEventListener(type, fn) {
            el._listeners[type] = el._listeners[type] || [];
            el._listeners[type].push(fn);
        },
        removeEventListener() {},
        focus() {},
        click() {
            (el._listeners.click || []).forEach((fn) => fn({
                preventDefault() {},
                stopPropagation() {}
            }));
        },
        dispatch(type) {
            (el._listeners[type] || []).forEach((fn) => fn({
                preventDefault() {},
                stopPropagation() {}
            }));
        },
        getContext() {
            return {
                drawImage() {},
                clearRect() {},
                fillRect() {},
                fillStyle: '#ffffff'
            };
        },
        toBlob(cb) {
            const blob = { size: 120, type: 'image/jpeg' };
            cb(blob);
        }
    };
    el.classList = {
        _set: new Set(),
        add(c) {
            this._set.add(c);
            if (c === 'hidden' && el.id === 'aa-modal-root') {
                FakeMutationObserver.notify(el);
            }
        },
        remove(c) { this._set.delete(c); },
        contains(c) { return this._set.has(c); }
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

class FakeMutationObserver {
    static active = [];
    constructor(cb) { this.cb = cb; }
    observe() { FakeMutationObserver.active.push(this); }
    disconnect() {
        FakeMutationObserver.active = FakeMutationObserver.active.filter((o) => o !== this);
    }
    static notify() {
        FakeMutationObserver.active.slice().forEach((o) => o.cb());
    }
}

function okPayload(data) {
    return Promise.resolve({ httpStatus: 200, result: { success: true, data } });
}

function loadHarness(portsOverrides) {
    FakeMutationObserver.active = [];
    const modalRoot = createEl('div');
    modalRoot.id = 'aa-modal-root';
    modalRoot.classList.add('hidden');

    let modalCapture = null;
    const completes = [];

    const windowObj = {
        AAAdmin: {
            openModal(opts) {
                modalCapture = opts;
                modalRoot.classList.remove('hidden');
            },
            closeModal() {
                modalCapture = null;
                modalRoot.classList.add('hidden');
            },
            modal: { isOpen() { return !modalRoot.classList.contains('hidden'); } },
            toast: { show() {} }
        },
        AA_CLIENTS_DATA: {},
        AA_CLIENTS_NONCES: {},
        confirm: () => true,
        addEventListener() {},
        removeEventListener() {},
        setTimeout: (fn) => fn(),
        console: { error() {}, log() {} },
        location: { assign() {} }
    };
    windowObj.window = windowObj;

    const sandbox = {
        window: windowObj,
        document: {
            createElement: createEl,
            getElementById: (id) => (id === 'aa-modal-root' ? modalRoot : null),
            querySelector: (sel) => {
                if (sel === '#aa-modal-root .aa-modal-title') return null;
                return null;
            },
            contains: () => true,
            addEventListener() {},
            removeEventListener() {}
        },
        console: windowObj.console,
        fetch: () => Promise.resolve({
            status: 200,
            json: async () => ({ success: true, data: { records: [] } })
        }),
        FormData: class {
            constructor() { this.entries = []; }
            append(k, v) { this.entries.push([k, v]); }
        },
        MutationObserver: FakeMutationObserver,
        AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        createImageBitmap: async () => ({
            width: 100,
            height: 100,
            close() {}
        }),
        setTimeout: (fn) => fn(),
        Object,
        JSON,
        Math,
        parseInt,
        String,
        Array,
        Promise,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };

    vm.runInNewContext(moduleSrc, sandbox);

    const defaultPorts = {
        list: () => okPayload({ records: [] }),
        create: (draft) => okPayload({
            record: {
                id: 77,
                title: draft.title,
                body: draft.body,
                recorded_at: '2026-08-20 12:00:00',
                adjuntos: [],
                adjunto: null
            }
        }),
        attach: () => okPayload({
            record_id: 77,
            adjunto: {
                id: 501,
                width: 100,
                height: 100,
                byte_size: 120,
                created_at: '2026-08-20 12:01:00'
            }
        }),
        signRead: () => okPayload({ url: 'https://x', expires_in: 60, variant: 'summary' }),
        deleteAdjunto: () => okPayload({
            record_id: 77,
            deleted_attachment_id: 501,
            adjuntos: [],
            adjunto: null
        })
    };
    const ports = Object.assign({}, defaultPorts, portsOverrides || {});

    const root = createEl('div');
    sandbox.window.AAAdmin.ExpedienteRegistros.init({
        scopeKey: 'expediente:5',
        recordsRoot: root,
        ports,
        capabilities: CAPS,
        onCreateComplete(payload) {
            completes.push(payload);
        }
    });

    return {
        api: sandbox.window.AAAdmin.ExpedienteRegistros,
        testApi: sandbox.window.AAAdmin.ExpedienteRegistros.__test__,
        completes,
        getModal: () => modalCapture,
        modalRoot,
        find(sel) {
            if (!modalCapture) return null;
            const bodyHit = modalCapture.body && modalCapture.body.querySelector
                ? modalCapture.body.querySelector(sel)
                : null;
            if (bodyHit) return bodyHit;
            return modalCapture.footer && modalCapture.footer.querySelector
                ? modalCapture.footer.querySelector(sel)
                : null;
        },
        async ready() {
            await wait();
            await wait();
        }
    };
}

async function fillAndSave(h, withImage) {
    h.api.openCreate();
    const title = h.find('#aa-expediente-registro-title');
    const body = h.find('#aa-expediente-registro-body');
    title.value = 'Título';
    body.value = 'Cuerpo';
    if (withImage) {
        const fileInput = h.find('.aa-expediente-adjunto-input');
        assert.ok(fileInput, 'file input present');
        fileInput.files = [{ name: 'x.jpg', type: 'image/jpeg', size: 50 }];
        fileInput.dispatch('change');
        // Esperar a que prepareExpedienteImage termine (save se rehabilita).
        for (let i = 0; i < 20; i++) {
            await wait();
            const saveBtn = h.find('.aa-btn-guardar');
            const preview = h.find('.aa-expediente-adjunto-preview-wrap');
            if (saveBtn && !saveBtn.disabled && preview && !preview.classList.contains('hidden')) {
                break;
            }
        }
        const preview = h.find('.aa-expediente-adjunto-preview-wrap');
        assert.ok(preview && !preview.classList.contains('hidden'), 'preview visible after prepare');
    }
    h.find('.aa-btn-guardar').click();
    await wait();
    await wait();
    await wait();
}

describe('ExpedienteRegistros onCreateComplete (C1c2)', () => {
    it('sin callback → legacy intacto (create sin throw)', async () => {
        const h = loadHarness();
        // Re-init without callback
        const root = createEl('div');
        h.api.init({
            scopeKey: 'expediente:5',
            recordsRoot: root,
            ports: {
                list: () => okPayload({ records: [] }),
                create: (d) => okPayload({
                    record: {
                        id: 1,
                        title: d.title,
                        body: d.body,
                        recorded_at: '2026-08-20 12:00:00',
                        adjuntos: [],
                        adjunto: null
                    }
                }),
                attach: () => okPayload({ record_id: 1, adjunto: { id: 1, width: 1, height: 1, byte_size: 1, created_at: 'x' } }),
                signRead: () => okPayload({ url: 'u', expires_in: 1, variant: 'summary' }),
                deleteAdjunto: () => okPayload({ record_id: 1, deleted_attachment_id: 1, adjuntos: [], adjunto: null })
            },
            capabilities: CAPS
        });
        await h.ready();
        h.api.openCreate();
        h.find('#aa-expediente-registro-title').value = 'A';
        h.find('#aa-expediente-registro-body').value = 'B';
        h.find('.aa-btn-guardar').click();
        await wait();
        await wait();
        assert.equal(h.completes.length, 0);
    });

    it('create textual sin imagen → una sola none', async () => {
        const h = loadHarness();
        await h.ready();
        await fillAndSave(h, false);
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].recordId, 77);
        assert.equal(h.completes[0].imageOutcome, 'none');
    });

    it('attach inicial exitoso → una sola saved', async () => {
        const h = loadHarness();
        await h.ready();
        await fillAndSave(h, true);
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].imageOutcome, 'saved');
    });

    it('fallo reintentable no notifica mientras modal abierto', async () => {
        let n = 0;
        const h = loadHarness({
            attach: () => {
                n += 1;
                if (n === 1) {
                    return Promise.resolve({
                        httpStatus: 502,
                        result: { success: false, data: { code: 'upload_failed' } }
                    });
                }
                return okPayload({
                    record_id: 77,
                    adjunto: {
                        id: 501,
                        width: 1,
                        height: 1,
                        byte_size: 1,
                        created_at: 'x'
                    }
                });
            }
        });
        await h.ready();
        await fillAndSave(h, true);
        assert.equal(h.completes.length, 0);
        assert.ok(h.getModal());
        const retry = h.find('.aa-expediente-btn-reintentar-imagen');
        assert.ok(retry);
        assert.ok(!retry.classList.contains('hidden'));
    });

    it('retry exitoso → una sola saved', async () => {
        let n = 0;
        const h = loadHarness({
            attach: () => {
                n += 1;
                if (n === 1) {
                    return Promise.resolve({
                        httpStatus: 502,
                        result: { success: false, data: { code: 'upload_failed' } }
                    });
                }
                return okPayload({
                    record_id: 77,
                    adjunto: {
                        id: 501,
                        width: 1,
                        height: 1,
                        byte_size: 1,
                        created_at: 'x'
                    }
                });
            }
        });
        await h.ready();
        await fillAndSave(h, true);
        assert.equal(h.completes.length, 0);
        h.find('.aa-expediente-btn-reintentar-imagen').click();
        await wait();
        await wait();
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].imageOutcome, 'saved');
    });

    it('fallo no reintentable → failed', async () => {
        const h = loadHarness({
            attach: () => Promise.resolve({
                httpStatus: 403,
                result: {
                    success: false,
                    data: { code: 'storage_quota_exceeded' }
                }
            })
        });
        await h.ready();
        await fillAndSave(h, true);
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].imageOutcome, 'failed');
    });

    it('cierre tras persistencia con imagen reintentable → abandoned', async () => {
        const h = loadHarness({
            attach: () => Promise.resolve({
                httpStatus: 502,
                result: { success: false, data: { code: 'upload_failed' } }
            })
        });
        await h.ready();
        await fillAndSave(h, true);
        assert.equal(h.completes.length, 0);
        h.api.__test__ // keep lint calm
        ;
        // Cierre usuario: closeModal dispara MutationObserver
        h.modalRoot.classList.add('hidden');
        // openModal already removed hidden; simulate close
        if (typeof h.api !== 'undefined') {
            // force watcher: classList.add when already hidden may not re-fire
        }
        // Ensure observer sees transition: remove then add
        h.modalRoot.classList.remove('hidden');
        h.modalRoot.classList.add('hidden');
        await wait();
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].imageOutcome, 'abandoned');
    });

    it('cierre durante upload: no notifica hasta que termine; éxito → saved', async () => {
        let resolveAttach;
        const h = loadHarness({
            attach: () => new Promise((resolve) => {
                resolveAttach = resolve;
            })
        });
        await h.ready();
        await fillAndSave(h, true);
        // still uploading
        assert.equal(h.completes.length, 0);
        h.modalRoot.classList.remove('hidden');
        h.modalRoot.classList.add('hidden');
        await wait();
        assert.equal(h.completes.length, 0, 'no notify while upload pending');
        resolveAttach({
            httpStatus: 200,
            result: {
                success: true,
                data: {
                    record_id: 77,
                    adjunto: {
                        id: 501,
                        width: 1,
                        height: 1,
                        byte_size: 1,
                        created_at: 'x'
                    }
                }
            }
        });
        await wait();
        await wait();
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].imageOutcome, 'saved');
    });

    it('cierre programático por éxito no es abandoned', async () => {
        const h = loadHarness();
        await h.ready();
        await fillAndSave(h, false);
        assert.equal(h.completes.length, 1);
        assert.equal(h.completes[0].imageOutcome, 'none');
        // late watcher noise
        h.modalRoot.classList.remove('hidden');
        h.modalRoot.classList.add('hidden');
        await wait();
        assert.equal(h.completes.length, 1);
    });

    it('excepción del callback no rompe ni duplica', async () => {
        const root = createEl('div');
        FakeMutationObserver.active = [];
        const modalRoot = createEl('div');
        modalRoot.id = 'aa-modal-root';
        modalRoot.classList.add('hidden');
        let calls = 0;
        const windowObj = {
            AAAdmin: {
                openModal(opts) {
                    windowObj._modal = opts;
                    modalRoot.classList.remove('hidden');
                },
                closeModal() {
                    windowObj._modal = null;
                    modalRoot.classList.add('hidden');
                },
                modal: { isOpen() { return false; } },
                toast: { show() {} }
            },
            AA_CLIENTS_DATA: {},
            AA_CLIENTS_NONCES: {},
            confirm: () => true,
            addEventListener() {},
            removeEventListener() {},
            setTimeout: (fn) => fn(),
            console: { error() {}, log() {} }
        };
        windowObj.window = windowObj;
        const sandbox = {
            window: windowObj,
            document: {
                createElement: createEl,
                getElementById: (id) => (id === 'aa-modal-root' ? modalRoot : null),
                querySelector: () => null,
                contains: () => true,
                addEventListener() {},
                removeEventListener() {}
            },
            console: windowObj.console,
            fetch: () => Promise.resolve({
                status: 200,
                json: async () => ({ success: true, data: { records: [] } })
            }),
            FormData: class { constructor() { this.entries = []; } append(k, v) { this.entries.push([k, v]); } },
            MutationObserver: FakeMutationObserver,
            AbortController,
            URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
            createImageBitmap: async () => ({ width: 1, height: 1, close() {} }),
            setTimeout: (fn) => fn(),
            Object, JSON, Math, parseInt, String, Array, Promise,
            crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
        };
        vm.runInNewContext(moduleSrc, sandbox);
        const api = sandbox.window.AAAdmin.ExpedienteRegistros;
        api.init({
            scopeKey: 'expediente:1',
            recordsRoot: root,
            ports: {
                list: () => okPayload({ records: [] }),
                create: (d) => okPayload({
                    record: {
                        id: 9,
                        title: d.title,
                        body: d.body,
                        recorded_at: '2026-08-20 12:00:00',
                        adjuntos: [],
                        adjunto: null
                    }
                }),
                attach: () => okPayload({
                    record_id: 9,
                    adjunto: { id: 1, width: 1, height: 1, byte_size: 1, created_at: 'x' }
                }),
                signRead: () => okPayload({ url: 'u', expires_in: 1, variant: 'summary' }),
                deleteAdjunto: () => okPayload({
                    record_id: 9,
                    deleted_attachment_id: 1,
                    adjuntos: [],
                    adjunto: null
                })
            },
            capabilities: CAPS,
            onCreateComplete() {
                calls += 1;
                throw new Error('boom');
            }
        });
        await wait();
        api.openCreate();
        const modal = windowObj._modal;
        modal.body.querySelector('#aa-expediente-registro-title').value = 'T';
        modal.body.querySelector('#aa-expediente-registro-body').value = 'B';
        modal.footer.querySelector('.aa-btn-guardar').click();
        await wait();
        await wait();
        assert.equal(calls, 1);
        modalRoot.classList.remove('hidden');
        modalRoot.classList.add('hidden');
        await wait();
        assert.equal(calls, 1);
    });

    it('destroy/stale impide callback', async () => {
        let resolveAttach;
        const h = loadHarness({
            attach: () => new Promise((resolve) => { resolveAttach = resolve; })
        });
        await h.ready();
        await fillAndSave(h, true);
        h.api.destroy();
        resolveAttach({
            httpStatus: 200,
            result: {
                success: true,
                data: {
                    record_id: 77,
                    adjunto: {
                        id: 501,
                        width: 1,
                        height: 1,
                        byte_size: 1,
                        created_at: 'x'
                    }
                }
            }
        });
        await wait();
        await wait();
        assert.equal(h.completes.length, 0);
    });
});
