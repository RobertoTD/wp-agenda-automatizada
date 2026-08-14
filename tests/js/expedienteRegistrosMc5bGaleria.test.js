'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const cssSourcePath = path.join(
    __dirname,
    '../../includes/admin/ui/assets/css/admin.source.css'
);

function createEl(tag) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: '',
        type: '',
        textContent: '',
        open: false,
        disabled: false,
        isConnected: true,
        children: [],
        attributes: Object.create(null),
        parentNode: null,
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
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
        addEventListener(type, handler) {
            el._listeners = el._listeners || {};
            el._listeners[type] = el._listeners[type] || [];
            el._listeners[type].push(handler);
        },
        dispatch(type, event) {
            ((el._listeners && el._listeners[type]) || []).forEach((fn) => fn(event || { preventDefault() {} }));
        },
        focus() { el._focused = true; },
        querySelector(selector) { return findFirst(el, selector); },
        querySelectorAll(selector) {
            const out = [];
            collect(el, selector, out);
            return out;
        }
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

function matches(el, selector) {
    if (!el || !el.tagName) {
        return false;
    }
    if (selector.startsWith('.')) {
        const cls = selector.slice(1);
        return String(el.className).split(/\s+/).includes(cls) || el.classList.contains(cls);
    }
    return el.tagName === selector.toUpperCase();
}

function collect(root, selector, out) {
    (root.children || []).forEach((child) => {
        if (matches(child, selector)) {
            out.push(child);
        }
        collect(child, selector, out);
    });
}

function findFirst(root, selector) {
    const out = [];
    collect(root, selector, out);
    return out[0] || null;
}

const DTO_3 = { id: 3, width: 400, height: 300, byte_size: 12000, created_at: '2026-07-31 06:00:00' };
const DTO_A = { id: 5, width: 800, height: 600, byte_size: 40000, created_at: '2026-07-31 08:00:00' };
const DTO_B = { id: 9, width: 1024, height: 768, byte_size: 65000, created_at: '2026-07-31 09:00:00' };
const DTO_C = { id: 12, width: 640, height: 480, byte_size: 30000, created_at: '2026-07-31 10:00:00' };
const DTO_NEW = { id: 20, width: 900, height: 900, byte_size: 50000, created_at: '2026-07-31 11:00:00' };

function baseRecord(overrides) {
    const record = Object.assign({
        id: 1,
        client_id: 7,
        title: 'Registro',
        body: 'Texto',
        recorded_at: '2026-07-31 08:00:00',
        created_at: '2026-07-31 08:00:00',
        updated_at: null,
        adjuntos: [],
        adjunto: null
    }, overrides || {});
    if (record.adjuntos.length && !record.adjunto) {
        record.adjunto = record.adjuntos[0];
    }
    return record;
}

function signUrlFor(id) {
    return 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/a' + id + '.jpg?token=t' + id;
}

function signOkResponse(dto, url) {
    return {
        status: 200,
        json: async () => ({
            success: true,
            data: { url: url || signUrlFor(dto.id), expires_in: 600, adjunto: dto }
        })
    };
}

function makeSandbox(options) {
    options = options || {};
    const fetchCalls = [];
    let fetchImpl = () => Promise.resolve({
        status: 200,
        json: async () => ({ success: false })
    });

    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
    }

    const modalRoot = createEl('div');
    modalRoot.id = 'aa-modal-root';
    modalRoot.classList.add('hidden');

    const modalCalls = { open: [], close: 0 };

    const sandbox = {
        window: {
            AAAdmin: {
                openModal(opts) {
                    modalCalls.open.push(opts);
                    modalRoot.children = [];
                    if (opts && opts.body && typeof opts.body === 'object') {
                        modalRoot.appendChild(opts.body);
                    }
                    modalRoot.classList.remove('hidden');
                },
                closeModal() {
                    modalCalls.close += 1;
                    modalRoot.children = [];
                    modalRoot.classList.add('hidden');
                }
            },
            AA_CLIENTS_DATA: {
                ajaxUrl: '/admin-ajax.php',
                actions: {
                    signAdjuntoRead: 'aa_sign_expediente_adjunto_read',
                    listRegistros: 'aa_list_expediente_registros'
                }
            },
            AA_CLIENTS_NONCES: { expediente_registros: 'nonce-1' },
            setTimeout: (fn) => fn()
        },
        document: {
            createElement: createEl,
            getElementById: (id) => (id === 'aa-modal-root' ? modalRoot : null),
            querySelector: () => null,
            contains: () => true
        },
        console: { error: () => {}, log: () => {} },
        fetch: function (url, opts) {
            fetchCalls.push({ url, opts });
            return fetchImpl(url, opts, fetchCalls.length);
        },
        FormData: FakeFormData,
        AbortController: AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        Image: function () {},
        setTimeout: (fn) => fn(),
        Date: Date,
        Object: Object,
        JSON: JSON,
        Math: Math,
        parseInt: parseInt,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };
    if (options.IntersectionObserver) {
        sandbox.IntersectionObserver = options.IntersectionObserver;
    }

    vm.runInNewContext(moduleSrc, sandbox);
    const api = sandbox.window.AAAdmin.ExpedienteRegistros.__test__;

    return {
        sandbox,
        api,
        fetchCalls,
        modalRoot,
        modalCalls,
        setFetch(fn) { fetchImpl = fn; }
    };
}

function mount(ctx, records) {
    const root = createEl('div');
    ctx.api.setState({ clientId: 7, recordsRoot: root, records: records, loading: false });
    return root;
}

function fieldsOf(fetchCall) {
    return Object.fromEntries(fetchCall.opts.body.entries);
}

/**
 * setFetch diferido: guarda un resolver por attachment_id solicitado.
 */
function deferSignFetch(ctx) {
    const pending = {};
    ctx.setFetch((url, opts) => new Promise((resolve) => {
        pending[Object.fromEntries(opts.body.entries).attachment_id] = resolve;
    }));
    return pending;
}

function settle() {
    return new Promise((resolve) => setImmediate(resolve));
}

describe('expediente MC5b — minigalería y visor', () => {
    it('0 imágenes: panel sin galería (comportamiento actual intacto)', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord()]);
        ctx.api.renderRecordsList();

        assert.equal(findFirst(root, '.aa-expediente-galeria'), null);
        assert.equal(findFirst(root, '.aa-expediente-adjunto-thumb'), null);
        assert.equal(ctx.fetchCalls.length, 0);
    });

    it('1 imagen: solo imagen principal, sin tira ni contador, abre visor', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_A] })]);
        ctx.api.renderRecordsList();

        const gallery = findFirst(root, '.aa-expediente-galeria');
        assert.ok(gallery, 'galería presente');
        const main = findFirst(gallery, '.aa-expediente-galeria-main');
        assert.ok(main, 'imagen principal presente');
        assert.equal(main.tagName, 'BUTTON');
        assert.equal(main.getAttribute('data-adjunto-id'), '5');
        assert.equal(main.getAttribute('aria-label'), 'Ver imagen ampliada');
        assert.equal(findFirst(gallery, '.aa-expediente-galeria-strip'), null, 'sin tira');
        assert.equal(findFirst(gallery, '.aa-expediente-galeria-counter'), null, 'sin contador');

        main.dispatch('click');
        assert.equal(ctx.modalCalls.open.length, 1, 'clic en principal abre el visor');
        assert.ok(findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer'));
    });

    it('N imágenes: galería debajo del cuerpo, selección inicial adjuntos[0], contador y aria', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();

        const panel = findFirst(root, '.aa-expediente-registro-panel');
        const classesInOrder = panel.children.map((c) => String(c.className).split(/\s+/)[0]);
        assert.deepEqual(classesInOrder, [
            'aa-expediente-registro-body',
            'aa-expediente-galeria'
        ], 'galería debajo del cuerpo; acciones en el menú del header');

        const gallery = findFirst(panel, '.aa-expediente-galeria');
        const main = findFirst(gallery, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-adjunto-id'), '12', 'selección inicial adjuntos[0]');

        const strip = findFirst(gallery, '.aa-expediente-galeria-strip');
        assert.ok(strip, 'tira presente');
        assert.equal(strip.getAttribute('role'), 'group');
        assert.equal(strip.getAttribute('aria-label'), 'Imágenes del registro');
        assert.equal(strip.getAttribute('aria-pressed'), null, 'contenedor sin aria-pressed');

        const minis = [];
        collect(strip, '.aa-expediente-galeria-mini', minis);
        assert.equal(minis.length, 3);
        assert.deepEqual(minis.map((m) => m.getAttribute('data-adjunto-id')), ['12', '9', '5'], 'orden id DESC intacto');
        assert.deepEqual(minis.map((m) => m.getAttribute('aria-pressed')), ['true', 'false', 'false']);
        assert.equal(minis[1].getAttribute('aria-label'), 'Ver imagen 2 de 3');
        assert.ok(minis[0].classList.contains('aa-expediente-galeria-mini-selected'));

        const counter = findFirst(gallery, '.aa-expediente-galeria-counter');
        assert.equal(counter.textContent, '1 de 3');
    });

    it('clic en mini cambia selección, contador y main sin mutar adjuntos[]', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();

        const gallery = findFirst(root, '.aa-expediente-galeria');
        const minis = [];
        collect(gallery, '.aa-expediente-galeria-mini', minis);

        minis[1].dispatch('click');

        const main = findFirst(gallery, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-adjunto-id'), '9', 'main con la identidad seleccionada');
        assert.equal(findFirst(gallery, '.aa-expediente-galeria-counter').textContent, '2 de 3');
        assert.deepEqual(minis.map((m) => m.getAttribute('aria-pressed')), ['false', 'true', 'false']);
        assert.ok(minis[1].classList.contains('aa-expediente-galeria-mini-selected'));
        assert.ok(!minis[0].classList.contains('aa-expediente-galeria-mini-selected'));

        const record = ctx.api.getState().records[0];
        assert.deepEqual(Array.from(record.adjuntos, (d) => d.id), [12, 9, 5], 'adjuntos[] no mutado');
        assert.equal(ctx.api.getThumbs().selectedByRecord[1], 9, 'selección solo en UI');
    });

    it('CSS: tira con ~3 minis relativas y scroll horizontal (contrato de clases)', () => {
        const css = fs.readFileSync(cssSourcePath, 'utf8');
        assert.ok(css.includes('.aa-expediente-galeria-mini'), 'clase mini definida');
        assert.ok(css.includes('calc((100% - 1rem) / 3)'), 'flex-basis relativo ≈ 3 visibles');
        assert.match(css, /\.aa-expediente-galeria-strip\s*\{[^}]*overflow-x-auto/, 'tira con scroll horizontal');
        assert.match(css, /\.aa-expediente-galeria-mini\s*\{[^}]*min-width:\s*2\.75rem/, 'área táctil ≥44px');
    });

    it('tarjeta cerrada no firma la galería (fallback sin IO)', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();
        assert.equal(ctx.fetchCalls.length, 0, 'sin firmas con tarjeta cerrada');
    });

    it('fallback sin IO: tarjeta abierta firma solo main + primeras tres minis', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjuntos: [DTO_NEW, DTO_C, DTO_B, DTO_A, DTO_3] })]);
        deferSignFetch(ctx);

        ctx.api.renderRecordsList({ expandId: 1 });

        const requested = ctx.fetchCalls.map((c) => fieldsOf(c).attachment_id).sort();
        // main(20) comparte identidad con mini[0] → deduplicado.
        assert.deepEqual(requested, ['12', '20', '9'], 'main + minis 0-2; jamás la colección completa');
    });

    it('una petición por identidad aplicada a todos los suscriptores vigentes', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A] })]);
        const pending = deferSignFetch(ctx);
        ctx.api.renderRecordsList();

        const details = findFirst(root, '.aa-expediente-registro');
        details.open = true;
        details.dispatch('toggle');

        // summary(9) + main(9) + mini(9) → 1 sola firma; mini(5) → otra.
        assert.equal(ctx.fetchCalls.length, 2);
        pending['9'](signOkResponse(DTO_B));
        pending['5'](signOkResponse(DTO_A));
        await settle();

        const summaryBox = findFirst(root, '.aa-expediente-adjunto-thumb');
        const main = findFirst(root, '.aa-expediente-galeria-main');
        const minis = [];
        collect(root, '.aa-expediente-galeria-mini', minis);

        assert.equal(findFirst(summaryBox, 'img').src, signUrlFor(9));
        assert.equal(findFirst(main, 'img').src, signUrlFor(9));
        assert.equal(findFirst(minis[0], 'img').src, signUrlFor(9));
        assert.equal(findFirst(minis[1], 'img').src, signUrlFor(5));
        assert.equal(findFirst(main, 'img').alt, '', 'img decorativa dentro de botón');
    });

    it('la respuesta de una selección anterior no se aplica al main tras cambiar de identidad', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A] })]);
        const pending = deferSignFetch(ctx);
        ctx.api.renderRecordsList({ expandId: 1 });

        const gallery = findFirst(root, '.aa-expediente-galeria');
        const main = findFirst(gallery, '.aa-expediente-galeria-main');
        const minis = [];
        collect(gallery, '.aa-expediente-galeria-mini', minis);

        // Con la firma de 9 aún en vuelo, cambiar la selección a 5.
        minis[1].dispatch('click');
        assert.equal(main.getAttribute('data-adjunto-id'), '5');

        pending['9'](signOkResponse(DTO_B));
        await settle();
        assert.equal(findFirst(main, 'img'), null, 'URL de la selección anterior no aplicada al main');
        assert.equal(findFirst(minis[0], 'img').src, signUrlFor(9), 'la mini de 9 sí la recibe');

        pending['5'](signOkResponse(DTO_A));
        await settle();
        assert.equal(findFirst(main, 'img').src, signUrlFor(5), 'main recibe su propia identidad');
    });

    it('la selección sobrevive a un update de texto si el adjunto existe', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();

        const minis = [];
        collect(root, '.aa-expediente-galeria-mini', minis);
        minis[1].dispatch('click');

        ctx.api.replaceRecord({ id: 1, title: 'Editado', body: 'Nuevo' });

        const main = findFirst(root, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-adjunto-id'), '9', 'selección conservada tras re-render');
        assert.equal(findFirst(root, '.aa-expediente-galeria-counter').textContent, '2 de 3');
    });

    it('tras attach la imagen nueva queda seleccionada', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();

        ctx.api.applyAdjuntoToRecord(1, DTO_NEW);

        assert.equal(ctx.api.getThumbs().selectedByRecord[1], 20);
        const main = findFirst(root, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-adjunto-id'), '20');
        assert.equal(findFirst(root, '.aa-expediente-galeria-counter').textContent, '1 de 3');
    });

    it('recarga autoritativa: selección inexistente vuelve a adjuntos[0]', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();
        const minis = [];
        collect(root, '.aa-expediente-galeria-mini', minis);
        minis[1].dispatch('click');
        assert.equal(ctx.api.getThumbs().selectedByRecord[1], 9);

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: { records: [baseRecord({ adjuntos: [DTO_C, DTO_A] })] }
            })
        }));
        ctx.api.loadRecords();
        await settle();

        const main = findFirst(ctx.api.getState().recordsRoot, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-adjunto-id'), '12', 'vuelve determinísticamente a adjuntos[0]');
        assert.equal(ctx.api.getThumbs().selectedByRecord[1], 12, 'mapa corregido');
    });

    it('visor: abre sincrónicamente con estado de carga y luego muestra la imagen', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ title: 'Mi registro', adjuntos: [DTO_B, DTO_A] })]);
        const pending = deferSignFetch(ctx);
        ctx.api.renderRecordsList();

        findFirst(root, '.aa-expediente-galeria-main').dispatch('click');

        assert.equal(ctx.modalCalls.open.length, 1);
        assert.equal(ctx.modalCalls.open[0].title, 'Mi registro');
        const viewer = findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer');
        assert.ok(viewer, 'marcador del visor montado');
        assert.equal(viewer.getAttribute('data-adjunto-id'), '9');
        assert.equal(
            findFirst(viewer, '.aa-expediente-adjunto-viewer-status').textContent,
            'Cargando imagen...',
            'estado de carga síncrono antes de resolver la firma'
        );

        pending['9'](signOkResponse(DTO_B));
        await settle();

        const img = findFirst(viewer, '.aa-expediente-adjunto-viewer-img');
        assert.ok(img, 'imagen ampliada montada');
        assert.equal(img.src, signUrlFor(9));
        assert.equal(img.alt, 'Mi registro');
        assert.equal(img.attributes.referrerpolicy, 'no-referrer');
    });

    it('visor: caché fresco se usa sin firma nueva; caché vencido fuerza firma', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B] })]);
        ctx.api.renderRecordsList();
        const thumbs = ctx.api.getThumbs();
        thumbs.thumbnailCache[ctx.api.thumbKey(1, 9)] = { url: signUrlFor(9), deadlineMs: Date.now() + 60000 };

        findFirst(root, '.aa-expediente-galeria-main').dispatch('click');
        assert.equal(ctx.fetchCalls.length, 0, 'caché fresco: sin firma nueva');
        assert.equal(findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer-img').src, signUrlFor(9));

        ctx.sandbox.window.AAAdmin.closeModal();
        thumbs.thumbnailCache[ctx.api.thumbKey(1, 9)] = { url: signUrlFor(9), deadlineMs: Date.now() - 1000 };
        const pending = deferSignFetch(ctx);
        findFirst(root, '.aa-expediente-galeria-main').dispatch('click');
        assert.equal(ctx.fetchCalls.length, 1, 'caché vencido: firma nueva antes de mostrar');
        pending['9'](signOkResponse(DTO_B));
        await settle();
        assert.ok(findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer-img'));
    });

    it('visor: máximo una refirma por identidad ante error de imagen', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B] })]);
        ctx.setFetch(() => Promise.resolve(signOkResponse(DTO_B)));
        ctx.api.renderRecordsList();

        findFirst(root, '.aa-expediente-galeria-main').dispatch('click');
        await settle();
        assert.equal(ctx.fetchCalls.length, 1);

        const viewer = findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer');
        findFirst(viewer, 'img').onerror();
        await settle();
        assert.equal(ctx.fetchCalls.length, 2, 'una refirma automática');
        assert.ok(findFirst(viewer, '.aa-expediente-adjunto-viewer-img'), 'imagen reintentada');

        findFirst(viewer, 'img').onerror();
        await settle();
        assert.equal(ctx.fetchCalls.length, 2, 'sin tercera firma');
        const error = findFirst(viewer, '.aa-expediente-adjunto-viewer-error');
        assert.ok(error, 'error discreto dentro del visor');
        assert.equal(error.attributes.role, 'alert');
    });

    it('resultado tardío del visor no reemplaza ni reabre otro modal', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B] })]);
        const pending = deferSignFetch(ctx);
        ctx.api.renderRecordsList();

        findFirst(root, '.aa-expediente-galeria-main').dispatch('click');
        const viewer = findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer');
        assert.ok(viewer);

        // Otro contenido sustituye al visor en el modal compartido.
        const otherBody = createEl('div');
        otherBody.className = 'otro-modal-cualquiera';
        ctx.sandbox.window.AAAdmin.openModal({ title: 'Otro', body: otherBody });

        pending['9'](signOkResponse(DTO_B));
        await settle();

        assert.equal(ctx.modalCalls.open.length, 2, 'el visor no se reabre');
        assert.ok(findFirst(ctx.modalRoot, '.otro-modal-cualquiera'), 'modal ajeno intacto');
        assert.equal(findFirst(ctx.modalRoot, '.aa-expediente-adjunto-viewer'), null);
        assert.equal(findFirst(viewer, 'img'), null, 'resultado tardío descartado');
    });

    it('destroy cierra el visor propio pero jamás un modal ajeno', () => {
        // Modal ajeno abierto: destroy no lo toca.
        const ctxA = makeSandbox();
        mount(ctxA, [baseRecord({ adjuntos: [DTO_B] })]);
        const foreign = createEl('div');
        foreign.className = 'otro-modal-cualquiera';
        ctxA.sandbox.window.AAAdmin.openModal({ title: 'Otro', body: foreign });
        ctxA.api.destroy();
        assert.equal(ctxA.modalCalls.close, 0, 'modal ajeno no cerrado');
        assert.ok(findFirst(ctxA.modalRoot, '.otro-modal-cualquiera'));

        // Visor propio abierto: destroy sí lo cierra y limpia la selección.
        const ctxB = makeSandbox();
        const rootB = mount(ctxB, [baseRecord({ adjuntos: [DTO_B] })]);
        ctxB.api.renderRecordsList();
        findFirst(rootB, '.aa-expediente-galeria-main').dispatch('click');
        assert.ok(findFirst(ctxB.modalRoot, '.aa-expediente-adjunto-viewer'));
        ctxB.api.destroy();
        assert.equal(ctxB.modalCalls.close, 1, 'visor propio cerrado');
        assert.deepEqual(Object.keys(ctxB.api.getThumbs().selectedByRecord), [], 'selección limpiada');
    });

    it('IO: minis observadas se firman al intersectar (scroll horizontal incluido)', async () => {
        const observed = [];
        let ioCallback = null;
        class FakeIO {
            constructor(cb) { ioCallback = cb; }
            observe(node) { observed.push(node); }
            unobserve() {}
            disconnect() {}
        }
        const ctx = makeSandbox({ IntersectionObserver: FakeIO });
        mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        const pending = deferSignFetch(ctx);
        ctx.api.renderRecordsList();

        // summary + main + 3 minis observados; nada firmado sin intersección.
        assert.equal(observed.length, 5);
        assert.equal(ctx.fetchCalls.length, 0);

        const lastMini = observed[observed.length - 1];
        assert.equal(lastMini.getAttribute('data-adjunto-id'), '5');
        ioCallback([{ isIntersecting: true, target: lastMini }]);
        assert.equal(ctx.fetchCalls.length, 1, 'solo la mini que intersectó');
        assert.equal(fieldsOf(ctx.fetchCalls[0]).attachment_id, '5');

        pending['5'](signOkResponse(DTO_A));
        await settle();
        assert.equal(findFirst(lastMini, 'img').src, signUrlFor(5));
    });
});
