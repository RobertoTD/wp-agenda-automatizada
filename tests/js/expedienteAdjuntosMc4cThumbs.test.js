'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

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

const DTO_A = { id: 5, width: 800, height: 600, byte_size: 40000, created_at: '2026-07-31 08:00:00' };
const DTO_B = { id: 9, width: 1024, height: 768, byte_size: 65000, created_at: '2026-07-31 09:00:00' };

function baseRecord(overrides) {
    return Object.assign({
        id: 1,
        client_id: 7,
        title: 'Registro',
        body: 'Texto',
        recorded_at: '2026-07-31 08:00:00',
        created_at: '2026-07-31 08:00:00',
        updated_at: null,
        adjunto: null
    }, overrides || {});
}

function makeSandbox(options) {
    options = options || {};
    const fetchCalls = [];
    let fetchImpl = options.fetch || (() => Promise.resolve({
        status: 200,
        json: async () => ({ success: false })
    }));

    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
    }

    const sandbox = {
        window: {
            AAAdmin: {},
            AA_CLIENTS_DATA: {
                ajaxUrl: '/admin-ajax.php',
                actions: { signAdjuntoRead: 'aa_sign_expediente_adjunto_read' }
            },
            AA_CLIENTS_NONCES: { expediente_registros: 'nonce-1' },
            setTimeout: (fn) => fn()
        },
        document: {
            createElement: createEl,
            getElementById: () => null,
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
        setFetch(fn) { fetchImpl = fn; }
    };
}

function mount(ctx, records) {
    const root = createEl('div');
    ctx.api.setState({ clientId: 7, recordsRoot: root, records: records, loading: false });
    return root;
}

function signOkResponse(dto, url, variant) {
    return {
        status: 200,
        json: async () => ({
            success: true,
            data: {
                url: url || 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/x.jpg?token=t',
                expires_in: 600,
                variant: variant || 'summary'
            }
        })
    };
}

describe('expediente adjuntos MC4c — miniaturas', () => {
    it('editar solo texto conserva record.adjunto y la miniatura', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();

        ctx.api.replaceRecord({ id: 1, title: 'Editado', body: 'Nuevo' });

        const record = ctx.api.getState().records[0];
        assert.deepEqual(record.adjunto, DTO_A);
        assert.equal(record.title, 'Editado');
        assert.equal(record.recorded_at, '2026-07-31 08:00:00');
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');
        assert.ok(box, 'miniatura sigue renderizada');
        assert.equal(box.getAttribute('data-adjunto-id'), '5');
    });

    it('clave adjunto presente (incluso null) es autoritativa', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);

        ctx.api.replaceRecord({ id: 1, title: 'T', body: 'B', adjunto: null });
        assert.equal(ctx.api.getState().records[0].adjunto, null);
        assert.equal(findFirst(root, '.aa-expediente-adjunto-thumb'), null);

        ctx.api.replaceRecord({ id: 1, title: 'T', body: 'B', adjunto: DTO_B });
        assert.deepEqual(ctx.api.getState().records[0].adjunto, DTO_B);
    });

    it('primer attach actualiza la tarjeta sin recargar registros', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: null })]);
        ctx.api.renderRecordsList();
        assert.equal(findFirst(root, '.aa-expediente-adjunto-thumb'), null);

        const applied = ctx.api.applyAdjuntoToRecord(1, DTO_A);

        assert.equal(applied, true);
        assert.deepEqual(ctx.api.getState().records[0].adjunto, DTO_A);
        assert.ok(findFirst(root, '.aa-expediente-adjunto-thumb'));
        // MC5b: la tarjeta queda expandida y su imagen principal visible se
        // firma (fallback sin IO); jamás se recarga el listado.
        assert.equal(ctx.fetchCalls.length, 1, 'solo la firma del main visible');
        const fields = Object.fromEntries(ctx.fetchCalls[0].opts.body.entries);
        assert.equal(fields.action, 'aa_sign_expediente_adjunto_read');
        assert.equal(fields.attachment_id, '5');
        assert.equal(fields.variant, 'display', 'main expandido solicita display');
    });

    it('attach nuevo reemplaza DTO y poda la clave anterior', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        const thumbs = ctx.api.getThumbs();
        thumbs.thumbnailCache[ctx.api.thumbKey(1, 5, 'summary')] = { url: 'https://x/5', deadlineMs: Date.now() + 60000 };
        thumbs.thumbnailCache[ctx.api.thumbKey(1, 5, 'gallery')] = { url: 'https://x/5g', deadlineMs: Date.now() + 60000 };
        thumbs.thumbnailCache[ctx.api.thumbKey(1, 5, 'display')] = { url: 'https://x/5d', deadlineMs: Date.now() + 60000 };

        ctx.api.applyAdjuntoToRecord(1, DTO_B);

        assert.deepEqual(ctx.api.getState().records[0].adjunto, DTO_B);
        assert.equal(thumbs.thumbnailCache[ctx.api.thumbKey(1, 5, 'summary')], undefined, 'summary anterior podada');
        assert.equal(thumbs.thumbnailCache[ctx.api.thumbKey(1, 5, 'gallery')], undefined, 'gallery anterior podada');
        assert.equal(thumbs.thumbnailCache[ctx.api.thumbKey(1, 5, 'display')], undefined, 'display anterior podada');
        assert.equal(ctx.api.getState().records.length, 1);
    });

    it('reintento actualiza el mismo registro sin duplicarlo', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjunto: null }), baseRecord({ id: 2 })]);

        ctx.api.applyAdjuntoToRecord(1, DTO_A);
        ctx.api.applyAdjuntoToRecord(1, DTO_B);

        const records = ctx.api.getState().records;
        assert.equal(records.length, 2);
        assert.deepEqual(records.filter((r) => r.id === 1)[0].adjunto, DTO_B);
        assert.equal(records.filter((r) => r.id === 2)[0].adjunto, null);
    });

    it('fallo parcial no toca adjunto (fuente)', () => {
        const start = moduleSrc.indexOf('function showPartialAttachFailure');
        assert.ok(start > 0);
        const chunk = moduleSrc.slice(start, start + 400);
        assert.doesNotMatch(chunk, /applyAdjuntoToRecord/);
        // El apply solo ocurre en éxito validado de attach.
        assert.match(moduleSrc, /if \(!attachResult\.skipped\) \{\s*applyAdjuntoToRecord\(attachResult\.recordId, attachResult\.adjunto\);/);
        assert.match(moduleSrc, /returnedRecordId === requestedRecordId && isValidAdjuntoDto\(responseData\.adjunto\)/);
    });

    it('sign-read con variante diferente descarta URL sin cachear ni mutar estado', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');
        const discardedUrl = 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/newer.jpg?token=zz';

        ctx.setFetch(() => Promise.resolve(signOkResponse(DTO_A, discardedUrl, 'display')));
        ctx.api.requestThumbFor(box, 1);
        await new Promise((resolve) => setImmediate(resolve));

        // Con lectura dirigida obligatoria una respuesta discordante es
        // inválida: no se cachea, no se aplica y adjuntos[] no se toca.
        const thumbs = ctx.api.getThumbs();
        Object.keys(thumbs.thumbnailCache).forEach((key) => {
            assert.notEqual(thumbs.thumbnailCache[key].url, discardedUrl, 'URL discordante jamás cacheada');
        });
        assert.deepEqual(ctx.api.getState().records[0].adjunto, DTO_A, 'estado intacto');
        assert.equal(box.getAttribute('data-adjunto-id'), '5', 'identidad del nodo intacta');
        assert.equal(findFirst(box, 'img'), null, 'URL discordante no aplicada');
        assert.ok(box.classList.contains('aa-expediente-adjunto-thumb-error'), 'error discreto');
    });

    it('respuesta tardía tras destroy no modifica DOM ni caché', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');

        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));
        ctx.api.requestThumbFor(box, 1);
        assert.equal(ctx.fetchCalls.length, 1);

        ctx.api.destroy();
        resolveLate(signOkResponse(DTO_A));
        await new Promise((resolve) => setImmediate(resolve));

        const thumbs = ctx.api.getThumbs();
        assert.deepEqual(Object.keys(thumbs.thumbnailCache), []);
        assert.equal(findFirst(box, 'img'), null, 'DOM intacto');
        assert.equal(ctx.api.getState().records.length, 0);
    });

    it('re-render conserva solo identidades vigentes y URLs frescas', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        const thumbs = ctx.api.getThumbs();
        const validKey = ctx.api.thumbKey(1, 5, 'summary');
        const staleKey = ctx.api.thumbKey(99, 12, 'summary');
        const expiredKey = ctx.api.thumbKey(1, 5, 'summary') + 'expired-probe';
        thumbs.thumbnailCache[validKey] = { url: 'https://x/valid', deadlineMs: Date.now() + 60000 };
        thumbs.thumbnailCache[staleKey] = { url: 'https://x/stale', deadlineMs: Date.now() + 60000 };
        thumbs.thumbnailCache[expiredKey] = { url: 'https://x/expired', deadlineMs: Date.now() - 1000 };

        ctx.api.renderRecordsList();

        assert.ok(thumbs.thumbnailCache[validKey], 'identidad vigente conservada');
        assert.equal(thumbs.thumbnailCache[staleKey], undefined, 'registro inexistente podado');
        assert.equal(thumbs.thumbnailCache[expiredKey], undefined, 'URL vencida podada');
    });

    it('lazy: sin IntersectionObserver no firma hasta toggle; sin adjunto nunca firma', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A }), baseRecord({ id: 2, adjunto: null })]);
        ctx.api.renderRecordsList();
        assert.equal(ctx.fetchCalls.length, 0, 'render no dispara firmas');

        const detailsNodes = [];
        collect(root, '.aa-expediente-registro', detailsNodes);
        const withThumb = detailsNodes.filter((d) => findFirst(d, '.aa-expediente-adjunto-thumb'))[0];
        assert.ok(withThumb);
        ctx.setFetch(() => Promise.resolve(signOkResponse(DTO_A)));
        withThumb.open = true;
        (withThumb._listeners.toggle || []).forEach((fn) => fn());
        assert.equal(ctx.fetchCalls.length, 2, 'summary + display de la tarjeta con adjunto');
        assert.deepEqual(
            ctx.fetchCalls.map((c) => Object.fromEntries(c.opts.body.entries).variant).sort(),
            ['display', 'summary']
        );
    });

    it('lazy: IntersectionObserver firma al intersectar con una sola solicitud concurrente', async () => {
        const observed = [];
        let ioCallback = null;
        class FakeIO {
            constructor(cb) { ioCallback = cb; }
            observe(node) { observed.push(node); }
            unobserve() {}
            disconnect() {}
        }
        const ctx = makeSandbox({ IntersectionObserver: FakeIO });
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        let resolveSign;
        ctx.setFetch(() => new Promise((resolve) => { resolveSign = resolve; }));

        ctx.api.renderRecordsList();
        // MC5b: summary + imagen principal de la galería (misma identidad).
        assert.equal(observed.length, 2, 'summary y main observados');
        assert.equal(ctx.fetchCalls.length, 0);

        ioCallback([{ isIntersecting: true, target: observed[0] }]);
        assert.equal(ctx.fetchCalls.length, 1);

        // Single-flight: reintento de intersección no duplica la firma.
        ctx.api.requestThumbFor(observed[0], 1);
        assert.equal(ctx.fetchCalls.length, 1);

        resolveSign(signOkResponse(DTO_A));
        await new Promise((resolve) => setImmediate(resolve));
        const img = findFirst(root, 'img');
        assert.ok(img && img.src, 'miniatura renderizada');
        assert.equal(img.attributes.referrerpolicy, 'no-referrer');
        assert.equal(Object.fromEntries(ctx.fetchCalls[0].opts.body.entries).variant, 'summary');
    });

    it('error de img provoca como máximo una refirma y luego error discreto', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');
        ctx.setFetch(() => Promise.resolve(signOkResponse(DTO_A)));

        ctx.api.requestThumbFor(box, 1);
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.fetchCalls.length, 1);

        let img = findFirst(box, 'img');
        img.onerror();
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.fetchCalls.length, 2, 'una refirma automática');

        img = findFirst(box, 'img');
        img.onerror();
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.fetchCalls.length, 2, 'sin tercera firma');
        assert.ok(box.classList.contains('aa-expediente-adjunto-thumb-error'));
        assert.ok(findFirst(root, '.aa-expediente-registro'), 'el registro sigue visible');
    });

    it('destroy() desconecta, aborta y vacía recursos', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        const thumbs = ctx.api.getThumbs();
        const epochBefore = thumbs.viewEpoch;

        let disconnected = false;
        let aborted = false;
        thumbs.observer = { disconnect() { disconnected = true; } };
        thumbs.thumbnailRequests['7:1:5'] = { controller: { abort() { aborted = true; } } };
        thumbs.thumbnailCache['7:1:5'] = { url: 'https://x', deadlineMs: Date.now() + 60000 };
        thumbs.resignedIdentities['7:1:5'] = true;

        ctx.api.destroy();

        assert.equal(thumbs.viewEpoch, epochBefore + 1);
        assert.equal(disconnected, true);
        assert.equal(aborted, true);
        assert.equal(thumbs.observer, null);
        assert.deepEqual(Object.keys(thumbs.thumbnailCache), []);
        assert.deepEqual(Object.keys(thumbs.thumbnailRequests), []);
        assert.deepEqual(Object.keys(thumbs.resignedIdentities), []);
        const state = ctx.api.getState();
        assert.equal(state.clientId, 0);
        assert.equal(state.recordsRoot, null);
        assert.equal(state.records.length, 0);
    });

    it('no persiste signed URLs fuera de memoria (fuente)', () => {
        assert.doesNotMatch(moduleSrc, /localStorage/);
        assert.doesNotMatch(moduleSrc, /sessionStorage/);
        assert.doesNotMatch(moduleSrc, /setAttribute\('data-[^']*url/i);
        // init se protege con destroy previo; clients-module destruye al sustituir el shell.
        // C1a: validar config candidata antes de destroy/DOM.
        assert.match(moduleSrc, /function init\(options\) \{\s*var resolved = resolveInitConfig\(options\);/);
        assert.match(moduleSrc, /if \(!resolved\.ok\) \{/);
        assert.match(moduleSrc, /destroy\(\);\s*var config = resolved\.config;/);
        const clientsModuleSrc = fs.readFileSync(
            path.join(__dirname, '../../includes/admin/ui/modules/clients/clients-module.js'),
            'utf8'
        );
        assert.match(clientsModuleSrc, /ExpedienteRegistros\.destroy === 'function'/);
    });

    it('header solicita summary y clave de caché incluye variante', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');
        assert.equal(box.getAttribute('data-variant'), 'summary');

        ctx.setFetch(() => Promise.resolve(signOkResponse(DTO_A, 'https://signed/summary', 'summary')));
        ctx.api.requestThumbFor(box, 1);
        await new Promise((resolve) => setImmediate(resolve));

        const fields = Object.fromEntries(ctx.fetchCalls[0].opts.body.entries);
        assert.equal(fields.variant, 'summary');
        assert.equal(fields.attachment_id, '5');
        assert.ok(!Object.prototype.hasOwnProperty.call(fields, 'original'));
        const key = ctx.api.thumbKey(1, 5, 'summary');
        assert.equal(key, '7:1:5:summary');
        assert.equal(ctx.api.getThumbs().thumbnailCache[key].url, 'https://signed/summary');
        assert.equal(ctx.api.getThumbs().thumbnailCache[ctx.api.thumbKey(1, 5, 'display')], undefined);
    });

    it('summary y display no comparten single-flight ni reintento', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();
        const summary = findFirst(root, '.aa-expediente-adjunto-thumb');
        const main = findFirst(root, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-variant'), 'display');

        const resolvers = [];
        ctx.setFetch(() => new Promise((resolve) => { resolvers.push(resolve); }));
        ctx.api.requestThumbFor(summary, 1);
        ctx.api.requestThumbFor(main, 1);
        assert.equal(ctx.fetchCalls.length, 2, 'variantes distintas no deduplican');
        assert.deepEqual(
            ctx.fetchCalls.map((c) => Object.fromEntries(c.opts.body.entries).variant).sort(),
            ['display', 'summary']
        );

        resolvers[0](signOkResponse(DTO_A, 'https://s', 'summary'));
        resolvers[1](signOkResponse(DTO_A, 'https://d', 'display'));
        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(findFirst(summary, 'img').src, 'https://s');
        assert.equal(findFirst(main, 'img').src, 'https://d');

        findFirst(summary, 'img').onerror();
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.fetchCalls.length, 3);
        assert.equal(Object.fromEntries(ctx.fetchCalls[2].opts.body.entries).variant, 'summary');
    });

    it('TTL descuenta 60 segundos de margen', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjunto: DTO_A })]);
        ctx.api.renderRecordsList();
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');
        const now = Date.now();
        ctx.setFetch(() => Promise.resolve(signOkResponse(DTO_A)));
        ctx.api.requestThumbFor(box, 1);
        await new Promise((resolve) => setImmediate(resolve));
        const cached = ctx.api.getThumbs().thumbnailCache[ctx.api.thumbKey(1, 5, 'summary')];
        const windowMs = cached.deadlineMs - now;
        assert.ok(windowMs <= 540000 + 50, 'margen de 60s aplicado');
        assert.ok(windowMs >= 530000, 'no usa 600s crudos');
    });

    it('ningún POST de sign-read omite variant ni pide original (fuente)', () => {
        assert.match(moduleSrc, /formData\.append\('variant', variant\)/);
        assert.doesNotMatch(moduleSrc, /formData\.append\('variant', 'original'\)/);
        assert.match(moduleSrc, /thumbKey\(recordId, adjuntoId, variant\)/);
        assert.doesNotMatch(moduleSrc, /isValidAdjuntoDto\(data\.adjunto\)/);
        assert.match(moduleSrc, /data\.variant === variant/);
    });
});
