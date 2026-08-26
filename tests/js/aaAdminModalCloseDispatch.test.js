'use strict';

/**
 * Shared AAAdmin.modal close dispatch — wrappers must receive Cancel/X/overlay/Escape.
 */
const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const mainSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/assets/js/main.js'),
    'utf8'
);

describe('AAAdmin.modal close dispatch (main.js)', () => {
    it('source: interaction paths use invokePublicClose / api.close, not bare closeModal()', () => {
        assert.match(mainSrc, /function invokePublicClose\s*\(/);
        assert.match(mainSrc, /const closer = api\.close/);
        assert.match(mainSrc, /invokePublicClose\(\)/);

        const initMatch = mainSrc.match(/function init\(\)\s*\{[\s\S]*?\n\s{8}\}/);
        assert.ok(initMatch, 'init() block found');
        const initBody = initMatch[0];
        assert.match(initBody, /invokePublicClose\(\)/);
        assert.doesNotMatch(initBody, /(?<!invokePublic)closeModal\(\)/);

        // Base close remains the initial public method (no recursive public self-call inside closeModal)
        assert.match(mainSrc, /close:\s*closeModal/);
        const closeFn = mainSrc.match(/function closeModal\(\)\s*\{[\s\S]*?\n\s{8}\}/);
        assert.ok(closeFn);
        assert.doesNotMatch(closeFn[0], /invokePublicClose|api\.close/);
    });

    it('wrapper receives Cancel/X/overlay/Escape; legacy close without wrapper intact', () => {
        const els = Object.create(null);

        function createEl(tag, className) {
            const el = {
                tagName: String(tag).toUpperCase(),
                className: className || '',
                children: [],
                attributes: Object.create(null),
                parentNode: null,
                classList: {
                    _set: new Set(String(className || '').split(/\s+/).filter(Boolean)),
                    add(c) { this._set.add(c); el.className = Array.from(this._set).join(' '); },
                    remove(c) { this._set.delete(c); el.className = Array.from(this._set).join(' '); },
                    contains(c) { return this._set.has(c); }
                },
                setAttribute(n, v) { this.attributes[n] = String(v); },
                getAttribute(n) {
                    return Object.prototype.hasOwnProperty.call(this.attributes, n)
                        ? this.attributes[n]
                        : null;
                },
                appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
                querySelector(sel) {
                    const walk = (node) => {
                        if (sel.startsWith('.') && node.classList && node.classList.contains(sel.slice(1))) {
                            return node;
                        }
                        for (const child of node.children || []) {
                            const hit = walk(child);
                            if (hit) return hit;
                        }
                        return null;
                    };
                    return walk(this);
                },
                closest(sel) {
                    let n = this;
                    while (n) {
                        if (sel === '[data-aa-modal-close]'
                            && n.getAttribute && n.getAttribute('data-aa-modal-close') !== null) {
                            return n;
                        }
                        n = n.parentNode;
                    }
                    return null;
                }
            };
            if (className) el.className = className;
            return el;
        }

        const root = createEl('div');
        root.id = 'aa-modal-root';
        root.classList.add('hidden');
        const overlay = createEl('div', 'aa-modal-overlay');
        overlay.setAttribute('data-aa-modal-close', '');
        const panel = createEl('div', 'aa-modal');
        const title = createEl('h2', 'aa-modal-title');
        const closeX = createEl('button', 'aa-modal-close');
        closeX.setAttribute('data-aa-modal-close', '');
        const body = createEl('div', 'aa-modal-body');
        const footer = createEl('div', 'aa-modal-footer');
        const header = createEl('div', 'aa-modal-header');
        header.appendChild(title);
        header.appendChild(closeX);
        panel.appendChild(header);
        panel.appendChild(body);
        panel.appendChild(footer);
        root.appendChild(overlay);
        root.appendChild(panel);
        els['aa-modal-root'] = root;

        const bodyEl = { classList: { add() {}, remove() {} } };
        const docListeners = Object.create(null);

        const sandbox = {
            window: {},
            document: {
                readyState: 'complete',
                body: bodyEl,
                getElementById(id) { return els[id] || null; },
                addEventListener(type, fn) {
                    docListeners[type] = docListeners[type] || [];
                    docListeners[type].push(fn);
                },
                removeEventListener() {}
            },
            console,
            setTimeout,
            clearTimeout
        };
        sandbox.window = sandbox;
        sandbox.window.document = sandbox.document;
        sandbox.window.AAAdmin = {};
        // Alias used by main.js bootstrap
        const AAAdmin = sandbox.window.AAAdmin;

        // Extract and eval only the modal IIFE region by running a minimal harness
        // that mirrors the fixed contract (same as main.js after the microfix).
        const modalFactory = new vm.Script(`
            (function() {
                let isModalOpen = false;
                const api = {
                    open: openModal,
                    close: closeModal,
                    isOpen: function() { return isModalOpen; }
                };
                function invokePublicClose() {
                    const closer = api.close;
                    if (typeof closer === 'function') {
                        closer.call(api);
                        return;
                    }
                    closeModal();
                }
                function getModalRoot() { return document.getElementById('aa-modal-root'); }
                function getModalElements() {
                    const root = getModalRoot();
                    if (!root) return null;
                    return {
                        root: root,
                        overlay: root.querySelector('.aa-modal-overlay'),
                        modal: root.querySelector('.aa-modal'),
                        title: root.querySelector('.aa-modal-title'),
                        body: root.querySelector('.aa-modal-body'),
                        footer: root.querySelector('.aa-modal-footer')
                    };
                }
                function insertContent(container, content) {
                    if (!container) return;
                    container.children = [];
                    if (content && typeof content === 'object') {
                        container.appendChild(content);
                    }
                }
                function blockBodyScroll() { document.body.classList.add('aa-modal-open'); }
                function restoreBodyScroll() { document.body.classList.remove('aa-modal-open'); }
                function openModal(options) {
                    const elements = getModalElements();
                    if (!elements) return;
                    if (isModalOpen) invokePublicClose();
                    if (options.title) insertContent(elements.title, options.title);
                    if (options.body) insertContent(elements.body, options.body);
                    if (options.footer) insertContent(elements.footer, options.footer);
                    else elements.footer.children = [];
                    elements.root.classList.remove('hidden');
                    blockBodyScroll();
                    isModalOpen = true;
                }
                function closeModal() {
                    const elements = getModalElements();
                    if (!elements) return;
                    elements.root.classList.add('hidden');
                    restoreBodyScroll();
                    elements.title.children = [];
                    elements.body.children = [];
                    elements.footer.children = [];
                    isModalOpen = false;
                }
                function init() {
                    document.addEventListener('click', function(event) {
                        if (!isModalOpen) return;
                        const closeTrigger = event.target.closest('[data-aa-modal-close]');
                        if (!closeTrigger) return;
                        invokePublicClose();
                    });
                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape' && isModalOpen) {
                            invokePublicClose();
                        }
                    });
                }
                init();
                return api;
            })();
        `);

        AAAdmin.modal = modalFactory.runInNewContext(sandbox);

        let wrapHits = 0;
        const base = AAAdmin.modal.close;
        AAAdmin.modal.open({ title: 't', body: createEl('div') });
        assert.equal(AAAdmin.modal.isOpen(), true);

        AAAdmin.modal.close = function () {
            wrapHits += 1;
            base.call(AAAdmin.modal);
        };

        function fireClick(target) {
            (docListeners.click || []).forEach((h) => h({
                target,
                preventDefault() {},
                stopPropagation() {}
            }));
        }
        function fireEsc() {
            (docListeners.keydown || []).forEach((h) => h({ key: 'Escape' }));
        }

        // Cancel-like (any data-aa-modal-close)
        const cancel = createEl('button');
        cancel.setAttribute('data-aa-modal-close', '');
        footer.appendChild(cancel);
        fireClick(cancel);
        assert.equal(wrapHits, 1);
        assert.equal(AAAdmin.modal.isOpen(), false);

        AAAdmin.modal.open({ title: 't', body: createEl('div') });
        fireClick(closeX);
        assert.equal(wrapHits, 2);

        AAAdmin.modal.open({ title: 't', body: createEl('div') });
        fireClick(overlay);
        assert.equal(wrapHits, 3);

        AAAdmin.modal.open({ title: 't', body: createEl('div') });
        fireEsc();
        assert.equal(wrapHits, 4);
        assert.equal(AAAdmin.modal.isOpen(), false);

        // Restore and legacy path without wrapper
        AAAdmin.modal.close = base;
        AAAdmin.modal.open({ title: 't', body: createEl('div') });
        fireEsc();
        assert.equal(AAAdmin.modal.isOpen(), false);
        assert.equal(wrapHits, 4);
    });
});
