'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/canonical-shell-container-options.js'
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

function createEl(attrs) {
    const el = {
        id: '',
        className: '',
        classList: createClassList(),
        attributes: Object.assign({}, attrs || {}),
        children: [],
        parentNode: null,
        _listeners: {},
        focusCalls: 0,
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
        hasAttribute(name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name);
        },
        appendChild(child) {
            child.parentNode = this;
            this.children.push(child);
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
        focus() {
            this.focusCalls += 1;
        }
    };
    if (attrs) {
        Object.keys(attrs).forEach((k) => {
            if (k === 'id') {
                el.id = attrs[k];
            }
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
    if (sel.includes(',')) {
        return sel.split(',').map((s) => s.trim()).some((part) => matches(el, part));
    }
    if (sel.startsWith('.')) {
        const parts = sel.slice(1).split('.').filter(Boolean);
        return parts.every((p) => el.classList.contains(p));
    }
    if (sel.startsWith('#')) {
        return el.id === sel.slice(1);
    }
    return false;
}

function queryAll(root, sel) {
    const out = [];
    const walk = (node) => {
        if (matches(node, sel)) {
            out.push(node);
        }
        (node.children || []).forEach(walk);
    };
    (root.children || []).forEach(walk);
    return out;
}

function emit(target, type, event) {
    const list = (target._listeners && target._listeners[type]) || [];
    list.forEach((fn) => fn(event));
}

function boot() {
    const root = createEl({ id: 'aa-canonical-shell-root' });
    const wrapA = createEl({ class: 'aa-shell-container-options' });
    const triggerA = createEl({ class: 'aa-shell-container-options-trigger aa-options-trigger-flat' });
    triggerA.setAttribute('aria-expanded', 'false');
    const popupA = createEl({ class: 'aa-shell-container-options-popup hidden' });
    popupA.classList.add('hidden');
    popupA.setAttribute('hidden', '');
    const editA = createEl({ class: 'aa-shell-edit-container-btn' });
    const viewsTriggerA = createEl({ class: 'aa-shell-record-views-trigger' });
    viewsTriggerA.setAttribute('aria-expanded', 'false');
    const viewsPanelA = createEl({ class: 'aa-shell-record-views-panel hidden' });
    viewsPanelA.classList.add('hidden');
    viewsPanelA.setAttribute('hidden', '');
    const viewLinkA = createEl({ class: 'aa-shell-record-view-link' });
    viewsPanelA.appendChild(viewLinkA);
    popupA.appendChild(editA);
    popupA.appendChild(viewsTriggerA);
    popupA.appendChild(viewsPanelA);
    wrapA.appendChild(triggerA);
    wrapA.appendChild(popupA);

    const wrapB = createEl({ class: 'aa-shell-container-options' });
    const triggerB = createEl({ class: 'aa-shell-container-options-trigger aa-options-trigger-flat' });
    triggerB.setAttribute('aria-expanded', 'false');
    const popupB = createEl({ class: 'aa-shell-container-options-popup hidden' });
    popupB.classList.add('hidden');
    popupB.setAttribute('hidden', '');
    wrapB.appendChild(triggerB);
    wrapB.appendChild(popupB);

    root.appendChild(wrapA);
    root.appendChild(wrapB);

    const createModal = createEl({ id: 'aa-shell-container-modal', class: 'hidden' });
    createModal.classList.add('hidden');
    const deleteModal = createEl({ id: 'aa-shell-delete-container-modal', class: 'hidden' });
    deleteModal.classList.add('hidden');

    const byId = {
        'aa-canonical-shell-root': root,
        'aa-shell-container-modal': createModal,
        'aa-shell-delete-container-modal': deleteModal
    };

    const documentRef = {
        _listeners: {},
        getElementById(id) {
            return byId[id] || null;
        },
        addEventListener(type, fn) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(fn);
        }
    };

    const context = {
        document: documentRef,
        console
    };
    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), context, { filename: 'canonical-shell-container-options.js' });

    return {
        root,
        wrapA,
        triggerA,
        popupA,
        editA,
        viewsTriggerA,
        viewsPanelA,
        viewLinkA,
        wrapB,
        triggerB,
        popupB,
        createModal,
        deleteModal,
        documentRef,
        clickRoot(target) {
            emit(root, 'click', {
                target,
                preventDefault() {},
                stopPropagation() {}
            });
        },
        clickDocument(target) {
            emit(documentRef, 'click', { target });
        },
        keydown(key, opts) {
            const event = Object.assign({
                key,
                defaultPrevented: false,
                preventDefault() {
                    this.defaultPrevented = true;
                }
            }, opts || {});
            emit(documentRef, 'keydown', event);
            return event;
        },
        isOpen(popup, trigger) {
            return !popup.classList.contains('hidden')
                && !popup.hasAttribute('hidden')
                && trigger.getAttribute('aria-expanded') === 'true';
        },
        isClosed(popup, trigger) {
            return popup.classList.contains('hidden')
                && popup.hasAttribute('hidden')
                && trigger.getAttribute('aria-expanded') === 'false';
        }
    };
}

describe('canonical-shell-container-options', () => {
    let harness;

    beforeEach(() => {
        harness = boot();
    });

    afterEach(() => {
        harness = null;
    });

    it('opens popup and sets aria-expanded', () => {
        harness.clickRoot(harness.triggerA);
        assert.ok(harness.isOpen(harness.popupA, harness.triggerA), 'popup A open');
    });

    it('toggles closed on second trigger click and restores focus', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.triggerA);
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'popup A closed');
        assert.ok(harness.triggerA.focusCalls >= 1, 'focus restored');
    });

    it('keeps a single popup open', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.triggerB);
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'A closed');
        assert.ok(harness.isOpen(harness.popupB, harness.triggerB), 'B open');
    });

    it('closes on outside click without focus restore', () => {
        harness.clickRoot(harness.triggerA);
        const before = harness.triggerA.focusCalls;
        harness.clickDocument(createEl({ class: 'outside' }));
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'closed by outside');
        assert.equal(harness.triggerA.focusCalls, before, 'no focus restore on outside');
    });

    it('Escape closes and restores focus when modals are hidden', () => {
        harness.clickRoot(harness.triggerA);
        const event = harness.keydown('Escape');
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'closed by Escape');
        assert.ok(harness.triggerA.focusCalls >= 1, 'focus restored');
        assert.ok(event.defaultPrevented, 'Escape prevented');
    });

    it('Escape ignored while container modal is open', () => {
        harness.clickRoot(harness.triggerA);
        harness.createModal.classList.remove('hidden');
        harness.keydown('Escape');
        assert.ok(harness.isOpen(harness.popupA, harness.triggerA), 'still open under modal');
    });

    it('closes popup when edit action inside popup is clicked', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.editA);
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'closed after edit');
    });

    it('opens and toggles the inline record views disclosure', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.viewsTriggerA);
        assert.ok(harness.isOpen(harness.viewsPanelA, harness.viewsTriggerA), 'views panel open');
        harness.clickRoot(harness.viewsTriggerA);
        assert.ok(harness.isClosed(harness.viewsPanelA, harness.viewsTriggerA), 'views panel closed');
        assert.ok(harness.viewsTriggerA.focusCalls >= 1, 'views trigger regains focus');
    });

    it('first Escape closes views and second Escape closes outer popup', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.viewsTriggerA);

        const first = harness.keydown('Escape');
        assert.ok(harness.isClosed(harness.viewsPanelA, harness.viewsTriggerA), 'views closed first');
        assert.ok(harness.isOpen(harness.popupA, harness.triggerA), 'outer popup remains open');
        assert.ok(first.defaultPrevented, 'first Escape prevented');

        const second = harness.keydown('Escape');
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'outer popup closed second');
        assert.ok(second.defaultPrevented, 'second Escape prevented');
    });

    it('closing or switching the outer popup resets the views disclosure', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.viewsTriggerA);
        harness.clickRoot(harness.triggerB);
        assert.ok(harness.isClosed(harness.viewsPanelA, harness.viewsTriggerA), 'views reset');
        assert.ok(harness.isClosed(harness.popupA, harness.triggerA), 'first outer popup closed');
        assert.ok(harness.isOpen(harness.popupB, harness.triggerB), 'second outer popup open');
    });

    it('keeps the outer popup open when a view link is clicked', () => {
        harness.clickRoot(harness.triggerA);
        harness.clickRoot(harness.viewsTriggerA);
        harness.clickRoot(harness.viewLinkA);
        assert.ok(harness.isOpen(harness.popupA, harness.triggerA), 'browser navigation owns link click');
        assert.ok(harness.isOpen(harness.viewsPanelA, harness.viewsTriggerA), 'disclosure remains until navigation');
    });

    it('source avoids menu roles', () => {
        const src = fs.readFileSync(jsPath, 'utf8');
        assert.doesNotMatch(src, /role=["']menu["']/);
        assert.doesNotMatch(src, /menuitem/);
        assert.match(src, /aa-shell-container-options-trigger/);
        assert.match(src, /aa-shell-container-options-popup/);
        assert.match(src, /aa-shell-record-views-trigger/);
        assert.match(src, /aa-shell-record-views-panel/);
    });
});
