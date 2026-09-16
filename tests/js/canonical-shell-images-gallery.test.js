'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-gallery.js'
);

function createClassList(initial) {
    const set = new Set(initial || []);
    return {
        add(name) { set.add(name); },
        remove(name) { set.delete(name); },
        contains(name) { return set.has(name); },
        _set: set
    };
}

function createEl(tag, attrs) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        classList: createClassList(),
        attributes: Object.assign({}, attrs || {}),
        children: [],
        parentNode: null,
        textContent: '',
        disabled: false,
        isConnected: true,
        _listeners: {},
        style: {},
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        removeAttribute(name) {
            delete this.attributes[name];
        },
        appendChild(child) {
            child.parentNode = this;
            this.children.push(child);
            return child;
        },
        removeChild(child) {
            const idx = this.children.indexOf(child);
            if (idx >= 0) {
                this.children.splice(idx, 1);
                child.parentNode = null;
            }
            return child;
        },
        querySelector(sel) {
            return queryAll(this, sel)[0] || null;
        },
        querySelectorAll(sel) {
            return queryAll(this, sel);
        },
        closest(sel) {
            let cur = this;
            while (cur) {
                if (matches(cur, sel)) {
                    return cur;
                }
                cur = cur.parentNode;
            }
            return null;
        },
        contains(node) {
            if (!node) {
                return false;
            }
            let cur = node;
            while (cur) {
                if (cur === this) {
                    return true;
                }
                cur = cur.parentNode;
            }
            return false;
        },
        addEventListener(type, fn) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(fn);
        },
        focus() {}
    };
    if (attrs) {
        Object.keys(attrs).forEach((k) => {
            if (k === 'class') {
                el.className = attrs[k];
                String(attrs[k]).split(/\s+/).filter(Boolean).forEach((c) => el.classList.add(c));
            }
        });
    }
    return el;
}

function matches(el, sel) {
    if (!el || !sel) {
        return false;
    }
    if (sel.startsWith('.')) {
        return el.classList.contains(sel.slice(1));
    }
    if (sel.startsWith('#')) {
        return el.id === sel.slice(1);
    }
    if (sel.startsWith('[') && sel.endsWith(']')) {
        const inner = sel.slice(1, -1);
        const eq = inner.indexOf('=');
        if (eq === -1) {
            return el.getAttribute(inner) !== null;
        }
        const name = inner.slice(0, eq);
        let val = inner.slice(eq + 1);
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
            val = val.slice(1, -1);
        }
        return el.getAttribute(name) === val;
    }
    if (sel.includes('.')) {
        const [tag, cls] = sel.split('.');
        return (!tag || el.tagName === tag.toUpperCase()) && el.classList.contains(cls);
    }
    return el.tagName === sel.toUpperCase();
}

function queryAll(root, sel) {
    const out = [];
    function walk(node) {
        if (!node || !node.children) {
            return;
        }
        for (let i = 0; i < node.children.length; i++) {
            const child = node.children[i];
            if (matches(child, sel)) {
                out.push(child);
            }
            // compound: .a.b or tag.class[attr]
            if (sel.includes('[') && sel.includes('.')) {
                const parts = sel.match(/^(\.[a-z0-9_-]+)+(?:\[.+\])?$/i);
                if (parts) {
                    // handled by matches for simple cases
                }
            }
            walk(child);
        }
    }
    // Support "div.foo" and ".foo.bar" and ".foo[attr]"
    const complex = sel.match(/^(\.[a-z0-9_-]+)(\[.+\])$/i);
    if (complex) {
        const cls = complex[1].slice(1);
        const attrSel = complex[2];
        function walk2(node) {
            if (!node || !node.children) {
                return;
            }
            for (let i = 0; i < node.children.length; i++) {
                const child = node.children[i];
                if (child.classList.contains(cls) && matches(child, attrSel)) {
                    out.push(child);
                }
                walk2(child);
            }
        }
        walk2(root);
        return out;
    }
    walk(root);
    return out;
}

function buildGalleryDom(ids) {
    const root = createEl('div', {
        class: 'aa-shell-record-gallery',
        'data-aa-gallery': '',
        'data-aa-gallery-record-id': '10',
        'data-aa-selected-id': String(ids[0])
    });
    root.classList.add('aa-shell-record-gallery');

    const wrap = createEl('div', { class: 'aa-shell-record-gallery-main-wrap' });
    wrap.classList.add('aa-shell-record-gallery-main-wrap');
    const main = createEl('button', {
        class: 'aa-shell-record-gallery-main',
        'data-aa-image-id': String(ids[0]),
        'data-aa-read-version': 'display'
    });
    main.classList.add('aa-shell-record-gallery-main');
    const status = createEl('span', { class: 'aa-shell-record-gallery-main-status' });
    status.classList.add('aa-shell-record-gallery-main-status');
    status.textContent = 'Cargando imagen';
    main.appendChild(status);
    wrap.appendChild(main);

    const del = createEl('button', {
        class: 'aa-shell-record-gallery-delete aa-shell-delete-image-btn',
        'data-aa-image': JSON.stringify({ id: ids[0], record_id: 10 })
    });
    del.classList.add('aa-shell-record-gallery-delete');
    del.classList.add('aa-shell-delete-image-btn');
    wrap.appendChild(del);
    root.appendChild(wrap);

    let strip = null;
    let counter = null;
    if (ids.length > 1) {
        strip = createEl('div', { class: 'aa-shell-record-gallery-strip' });
        strip.classList.add('aa-shell-record-gallery-strip');
        ids.forEach((id, index) => {
            const mini = createEl('button', {
                class: 'aa-shell-record-gallery-mini' + (id === ids[0] ? ' aa-shell-record-gallery-mini-selected' : ''),
                'data-aa-image-id': String(id),
                'data-aa-read-version': 'gallery',
                'aria-pressed': id === ids[0] ? 'true' : 'false'
            });
            mini.classList.add('aa-shell-record-gallery-mini');
            if (id === ids[0]) {
                mini.classList.add('aa-shell-record-gallery-mini-selected');
            }
            strip.appendChild(mini);
            void index;
        });
        root.appendChild(strip);
        counter = createEl('p', { class: 'aa-shell-record-gallery-counter', 'data-aa-gallery-counter': '' });
        counter.classList.add('aa-shell-record-gallery-counter');
        counter.textContent = '1 de ' + String(ids.length);
        root.appendChild(counter);
    }

    const err = createEl('p', { class: 'aa-shell-record-gallery-error hidden', 'data-aa-gallery-error': '' });
    err.classList.add('aa-shell-record-gallery-error');
    err.classList.add('hidden');
    err.setAttribute('hidden', '');
    root.appendChild(err);

    return { root, main, del, strip, counter };
}

function bootGallery(options) {
    options = options || {};
    const ids = options.ids || [7, 5, 3];
    const gallery = buildGalleryDom(ids);
    const viewerModal = createEl('div', { id: 'aa-shell-image-viewer-modal', class: 'hidden' });
    viewerModal.id = 'aa-shell-image-viewer-modal';
    viewerModal.classList.add('hidden');
    const viewerBody = createEl('div', { id: 'aa-shell-image-viewer-body' });
    viewerBody.id = 'aa-shell-image-viewer-body';
    const viewerClose = createEl('button', { id: 'aa-shell-image-viewer-modal-close-btn' });
    viewerClose.id = 'aa-shell-image-viewer-modal-close-btn';
    const viewerBackdrop = createEl('div', { id: 'aa-shell-image-viewer-modal-backdrop' });
    viewerBackdrop.id = 'aa-shell-image-viewer-modal-backdrop';
    viewerModal.appendChild(viewerBackdrop);
    viewerModal.appendChild(viewerClose);
    viewerModal.appendChild(viewerBody);

    const host = createEl('div');
    host.appendChild(gallery.root);
    host.appendChild(viewerModal);

    const signCalls = [];
    let fetchImpl = options.fetchImpl || ((body) => {
        const variant = body.variant;
        const imageId = body.image_id;
        signCalls.push({ variant, imageId });
        return Promise.resolve({
            ok: true,
            text: () => Promise.resolve(JSON.stringify({
                success: true,
                data: {
                    url: 'https://signed.example/' + variant + '-' + imageId + '.jpg',
                    expires_in: 600,
                    variant: variant
                }
            }))
        });
    });

    const documentRef = {
        readyState: 'complete',
        body: host,
        getElementById(id) {
            if (id === 'aa-shell-image-viewer-modal') return viewerModal;
            if (id === 'aa-shell-image-viewer-body') return viewerBody;
            if (id === 'aa-shell-image-viewer-modal-close-btn') return viewerClose;
            if (id === 'aa-shell-image-viewer-modal-backdrop') return viewerBackdrop;
            return null;
        },
        querySelectorAll(sel) {
            if (sel === '[data-aa-gallery]') {
                return [gallery.root];
            }
            if (sel.startsWith('[data-aa-summary-for-record=')) {
                return [];
            }
            if (sel.startsWith('[data-aa-image-id=')) {
                return queryAll(host, sel.replace(/^\[/, '.').length ? sel : sel);
            }
            return queryAll(host, sel);
        },
        querySelector(sel) {
            return this.querySelectorAll(sel)[0] || null;
        },
        createElement(tag) {
            return createEl(tag);
        },
        addEventListener() {}
    };

    // Fix querySelectorAll for data-aa-image-id
    documentRef.querySelectorAll = function (sel) {
        if (sel === '[data-aa-gallery]') {
            return [gallery.root];
        }
        if (sel.indexOf('data-aa-summary-for-record') !== -1) {
            return [];
        }
        if (sel.indexOf('data-aa-image-id=') !== -1) {
            const m = sel.match(/data-aa-image-id="(\d+)"/);
            if (!m) {
                return [];
            }
            return queryAll(host, '.aa-shell-record-gallery-mini[data-aa-image-id="' + m[1] + '"]')
                .concat(queryAll(host, '.aa-shell-record-gallery-main[data-aa-image-id="' + m[1] + '"]'));
        }
        return queryAll(host, sel);
    };

    const windowRef = {
        AA_CANONICAL_SHELL_RECORD_FORM: {
            ajaxUrl: '/admin-ajax.php',
            signReadAction: 'aa_sign_canonical_record_image_read',
            signReadNonce: 'sign-nonce',
            familyKey: 'archive',
            containerId: 1
        }
    };

    const env = {
        window: windowRef,
        document: documentRef,
        FormData: class {
            constructor() { this._data = {}; }
            append(k, v) { this._data[k] = v; }
        },
        fetch(url, opts) {
            const body = opts && opts.body ? opts.body._data : {};
            return fetchImpl(body, opts);
        },
        AbortController: class {
            constructor() {
                this.signal = { aborted: false };
                this._aborted = false;
            }
            abort() {
                this._aborted = true;
                this.signal.aborted = true;
            }
        },
        IntersectionObserver: null,
        console,
        setTimeout,
        clearTimeout,
        Date,
        JSON,
        parseInt,
        String,
        Object,
        Promise,
        Error
    };
    env.global = env;
    env.window.AACanonicalShellImagesGallery = undefined;

    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, { filename: 'canonical-shell-images-gallery.js' });

    return {
        api: env.window.AACanonicalShellImagesGallery,
        gallery,
        viewerModal,
        viewerBody,
        signCalls,
        host,
        setFetch(fn) { fetchImpl = fn; }
    };
}

describe('canonical-shell-images-gallery', () => {
    it('init firma display de la seleccionada y actualiza contador al seleccionar mini', async () => {
        const ctx = bootGallery({ ids: [7, 5, 3] });
        assert.ok(ctx.api);
        ctx.api.init();

        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        assert.ok(ctx.signCalls.some((c) => c.variant === 'display' && c.imageId === '7'));
        assert.equal(ctx.gallery.counter.textContent, '1 de 3');

        const mini5 = ctx.gallery.strip.children[1];
        const clickHandlers = mini5._listeners.click || [];
        // strip delegates click — fire on strip with closest
        const stripHandlers = ctx.gallery.strip._listeners.click || [];
        assert.ok(stripHandlers.length >= 1);
        stripHandlers[0]({
            target: mini5,
            preventDefault() {},
            closest(sel) {
                return matches(mini5, sel) ? mini5 : null;
            }
        });

        // Patch event: strip handler uses e.target.closest
        // Our handler: e.target.closest('.aa-shell-record-gallery-mini')
        // Need target with closest pointing to mini5
        const evt = {
            target: {
                closest(sel) {
                    return sel === '.aa-shell-record-gallery-mini' ? mini5 : null;
                }
            },
            preventDefault() {}
        };
        stripHandlers[0](evt);

        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        assert.equal(ctx.gallery.root.getAttribute('data-aa-selected-id'), '5');
        assert.equal(ctx.gallery.counter.textContent, '2 de 3');
        assert.ok(ctx.signCalls.some((c) => c.variant === 'display' && c.imageId === '5'));
    });

    it('respuesta tardía de firma no sobrescribe selección nueva', async () => {
        let resolveFirst = null;
        const pending = Object.create(null);
        const ctx = bootGallery({
            ids: [7, 5],
            fetchImpl(body) {
                const variant = body.variant;
                const imageId = body.image_id;
                const key = variant + ':' + imageId;
                if (variant === 'display' && imageId === '7' && !pending[key]) {
                    pending[key] = true;
                    return new Promise((resolve) => {
                        resolveFirst = () => resolve({
                            ok: true,
                            text: () => Promise.resolve(JSON.stringify({
                                success: true,
                                data: {
                                    url: 'https://signed.example/stale-7.jpg',
                                    expires_in: 600,
                                    variant: 'display'
                                }
                            }))
                        });
                    });
                }
                return Promise.resolve({
                    ok: true,
                    text: () => Promise.resolve(JSON.stringify({
                        success: true,
                        data: {
                            url: 'https://signed.example/' + variant + '-' + imageId + '.jpg',
                            expires_in: 600,
                            variant: variant
                        }
                    }))
                });
            }
        });
        ctx.api.init();
        await new Promise((resolve) => setTimeout(resolve, 0));

        const stripHandlers = ctx.gallery.strip._listeners.click || [];
        const mini5 = ctx.gallery.strip.children[1];
        stripHandlers[0]({
            target: {
                closest(sel) {
                    return sel === '.aa-shell-record-gallery-mini' ? mini5 : null;
                }
            },
            preventDefault() {}
        });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        assert.ok(typeof resolveFirst === 'function');
        resolveFirst();
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        const imgs = ctx.gallery.main.children.filter((c) => c.tagName === 'IMG');
        assert.ok(imgs.length >= 1);
        assert.equal(imgs[imgs.length - 1].src, 'https://signed.example/display-5.jpg');
        assert.ok(imgs.every((img) => img.src !== 'https://signed.example/stale-7.jpg'));
    });

    it('afterRetire elimina y ajusta selección; una restante quita tira', async () => {
        const ctx = bootGallery({ ids: [7, 5] });
        ctx.api.init();
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        ctx.api.afterRetire(7);
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        assert.equal(ctx.gallery.root.getAttribute('data-aa-selected-id'), '5');
        assert.equal(ctx.gallery.strip.parentNode, null);
        assert.ok(!ctx.gallery.root.children.some((c) => c.classList.contains('aa-shell-record-gallery-strip')));
    });

    it('visor cerrado ignora firma tardía display', async () => {
        let resolveSign = null;
        const ctx = bootGallery({
            ids: [9],
            fetchImpl(body) {
                if (body.variant === 'display') {
                    return new Promise((resolve) => {
                        resolveSign = () => resolve({
                            ok: true,
                            text: () => Promise.resolve(JSON.stringify({
                                success: true,
                                data: {
                                    url: 'https://signed.example/display-late.jpg',
                                    expires_in: 600,
                                    variant: 'display'
                                }
                            }))
                        });
                    });
                }
                return Promise.resolve({
                    ok: true,
                    text: () => Promise.resolve(JSON.stringify({
                        success: true,
                        data: { url: 'https://x', expires_in: 60, variant: body.variant }
                    }))
                });
            }
        });
        // Don't auto-init loadMain with hanging promise forever — override after construct
        // Re-boot with hanging only for viewer
        let viewerHang = false;
        const ctx2 = bootGallery({
            ids: [9],
            fetchImpl(body) {
                if (viewerHang && body.variant === 'display') {
                    return new Promise((resolve) => {
                        resolveSign = () => resolve({
                            ok: true,
                            text: () => Promise.resolve(JSON.stringify({
                                success: true,
                                data: {
                                    url: 'https://signed.example/display-late.jpg',
                                    expires_in: 600,
                                    variant: 'display'
                                }
                            }))
                        });
                    });
                }
                return Promise.resolve({
                    ok: true,
                    text: () => Promise.resolve(JSON.stringify({
                        success: true,
                        data: {
                            url: 'https://signed.example/' + body.variant + '-' + body.image_id + '.jpg',
                            expires_in: 600,
                            variant: body.variant
                        }
                    }))
                });
            }
        });
        ctx2.api.init();
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));

        viewerHang = true;
        ctx2.api.openViewer(10, 9, ctx2.gallery.main);
        assert.equal(ctx2.viewerModal.classList.contains('hidden'), false);
        ctx2.api.closeViewer();
        assert.equal(ctx2.viewerModal.classList.contains('hidden'), true);
        assert.ok(typeof resolveSign === 'function');
        resolveSign();
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        const lateImgs = ctx2.viewerBody.children.filter((c) => c.tagName === 'IMG');
        assert.equal(lateImgs.length, 0);
        void ctx;
    });
});
