'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/executable-lists-module.js');
const hooks = require(modulePath);

function baseItem(overrides) {
    return Object.assign({
        id: 'install_pwa',
        source: 'system',
        origin_key: 'install_pwa',
        primary_action: {
            type: 'handler',
            label: 'Instalar',
            handler: 'pwa.install'
        }
    }, overrides || {});
}

describe('executable-lists-module hooks', () => {
    let originalDebug;
    let originalActionsDebug;
    let originalData;
    let originalHandlers;
    let originalSessionStorage;

    beforeEach(() => {
        originalDebug = globalThis.AA_EXECUTABLE_LISTS_DEBUG;
        originalActionsDebug = globalThis.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG;
        originalData = globalThis.AA_EXECUTABLE_LISTS_DATA;
        originalHandlers = globalThis.LearningActionHandlers;
        originalSessionStorage = globalThis.sessionStorage;
    });

    afterEach(() => {
        if (originalDebug === undefined) {
            delete globalThis.AA_EXECUTABLE_LISTS_DEBUG;
        } else {
            globalThis.AA_EXECUTABLE_LISTS_DEBUG = originalDebug;
        }

        if (originalActionsDebug === undefined) {
            delete globalThis.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG;
        } else {
            globalThis.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG = originalActionsDebug;
        }

        if (originalData === undefined) {
            delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        } else {
            globalThis.AA_EXECUTABLE_LISTS_DATA = originalData;
        }

        if (originalHandlers === undefined) {
            delete globalThis.LearningActionHandlers;
        } else {
            globalThis.LearningActionHandlers = originalHandlers;
        }

        if (originalSessionStorage === undefined) {
            delete globalThis.sessionStorage;
        } else {
            globalThis.sessionStorage = originalSessionStorage;
        }
    });

    it('isDebugEnabled es false por defecto', () => {
        delete globalThis.AA_EXECUTABLE_LISTS_DEBUG;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        assert.equal(hooks.isDebugEnabled(), false);
    });

    it('isDebugEnabled respeta window.AA_EXECUTABLE_LISTS_DEBUG', () => {
        globalThis.AA_EXECUTABLE_LISTS_DEBUG = true;

        assert.equal(hooks.isDebugEnabled(), true);
    });

    it('isDebugEnabled respeta AA_EXECUTABLE_LISTS_DATA.debug', () => {
        globalThis.AA_EXECUTABLE_LISTS_DATA = {
            ajaxUrl: 'https://example.test/admin-ajax.php',
            action: 'aa_get_executable_lists_feed',
            nonce: 'test',
            debug: true
        };

        assert.equal(hooks.isDebugEnabled(), true);
    });

    it('isDebugEnabled respeta sessionStorage AA_EXECUTABLE_LISTS_DEBUG=1', () => {
        globalThis.sessionStorage = {
            getItem: function (key) {
                return key === 'AA_EXECUTABLE_LISTS_DEBUG' ? '1' : null;
            }
        };

        assert.equal(hooks.isDebugEnabled(), true);
        assert.equal(hooks.isSessionStorageDebugEnabled(), true);
    });

    it('sessionStorage ausente no rompe isDebugEnabled', () => {
        delete globalThis.AA_EXECUTABLE_LISTS_DEBUG;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        assert.equal(hooks.isSessionStorageDebugEnabled(), false);
        assert.equal(hooks.isDebugEnabled(), false);
    });

    it('sessionStorage con error no rompe isDebugEnabled', () => {
        globalThis.sessionStorage = {
            getItem: function () {
                throw new Error('blocked');
            }
        };

        assert.equal(hooks.isSessionStorageDebugEnabled(), false);
        assert.equal(hooks.isDebugEnabled(), false);
    });

    it('debug false mantiene rama que oculta la sección experimental', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /if \(!isDebugEnabled\(\)\) \{\s*setSectionVisible\(false\);\s*return;/);
    });

    it('isActionsEnabled es false sin debug visible', () => {
        globalThis.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG = true;
        delete globalThis.AA_EXECUTABLE_LISTS_DEBUG;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        assert.equal(hooks.isActionsEnabled(), false);
    });

    it('isActionsEnabled requiere debug visible y flag de acciones', () => {
        globalThis.AA_EXECUTABLE_LISTS_DEBUG = true;
        globalThis.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG = true;

        assert.equal(hooks.isActionsEnabled(), true);
    });

    it('isActionsEnabled respeta sessionStorage AA_EXECUTABLE_LISTS_ACTIONS_DEBUG=1', () => {
        globalThis.sessionStorage = {
            getItem: function (key) {
                if (key === 'AA_EXECUTABLE_LISTS_DEBUG') {
                    return '1';
                }

                if (key === 'AA_EXECUTABLE_LISTS_ACTIONS_DEBUG') {
                    return '1';
                }

                return null;
            }
        };

        assert.equal(hooks.isActionsEnabled(), true);
    });

    it('enableInteractiveRoot quita inert y pointer-events-none', () => {
        var root = {
            attributes: { inert: 'inert' },
            classList: {
                classes: ['pointer-events-none', 'p-4'],
                remove: function (name) {
                    this.classes = this.classes.filter(function (item) {
                        return item !== name;
                    });
                }
            },
            removeAttribute: function (name) {
                delete this.attributes[name];
            }
        };

        hooks.enableInteractiveRoot(root);

        assert.equal(root.attributes.inert, undefined);
        assert.equal(root.classList.classes.includes('pointer-events-none'), false);
    });

    it('enablePreviewRoot aplica inert y pointer-events-none', () => {
        var root = {
            attributes: {},
            classList: {
                classes: ['p-4'],
                add: function (name) {
                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                }
            },
            setAttribute: function (name, value) {
                this.attributes[name] = value;
            }
        };

        hooks.enablePreviewRoot(root);

        assert.notEqual(root.attributes.inert, undefined);
        assert.equal(root.classList.classes.includes('pointer-events-none'), true);
    });

    it('buildRenderOptions consulta LearningActionHandlers para handler items', () => {
        var calls = { show: 0, available: 0 };

        globalThis.LearningActionHandlers = {
            shouldShowRecommendation: function () {
                calls.show += 1;
                return false;
            },
            isAvailable: function () {
                calls.available += 1;
                return false;
            }
        };

        var options = hooks.buildRenderOptions();
        var item = baseItem();

        assert.equal(options.shouldRenderItem(item, {}), false);
        assert.equal(options.shouldRenderPrimaryAction(item.primary_action, item, {}), false);
        assert.equal(calls.show, 1);
        assert.equal(calls.available, 1);
    });

    it('buildRenderOptions permite navigate y status sin registry', () => {
        delete globalThis.LearningActionHandlers;

        var options = hooks.buildRenderOptions();
        var navigateItem = baseItem({
            primary_action: { type: 'navigate', label: 'Ir', url: 'https://example.test' }
        });
        var statusItem = baseItem({
            id: '42',
            source: 'user',
            primary_action: { type: 'status', label: 'Completar', to: 'done' }
        });

        assert.equal(options.shouldRenderItem(navigateItem, {}), true);
        assert.equal(options.shouldRenderPrimaryAction(navigateItem.primary_action, navigateItem, {}), true);
        assert.equal(options.shouldRenderPrimaryAction(statusItem.primary_action, statusItem, {}), true);
    });

    it('buildRenderOptions filtra handler desde visible_actions sin primary_action', () => {
        var calls = { show: 0, available: 0 };

        globalThis.LearningActionHandlers = {
            shouldShowRecommendation: function (action) {
                calls.show += 1;
                return action && action.handler === 'pwa.install';
            },
            isAvailable: function (action) {
                calls.available += 1;
                return action && action.handler === 'pwa.install';
            }
        };

        var options = hooks.buildRenderOptions();
        var item = baseItem({
            primary_action: null,
            visible_actions: [
                {
                    key: 'pwa.install',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Instalar',
                    placement: 'primary',
                    target_status: null,
                    url: null,
                    handler: 'pwa.install'
                }
            ]
        });

        assert.equal(options.shouldRenderItem(item, {}), true);
        assert.equal(
            options.shouldRenderPrimaryAction(item.visible_actions[0], item, {}),
            true
        );
        assert.equal(calls.show, 1);
        assert.equal(calls.available, 1);
    });

    it('buildRenderOptions oculta item cuando visible_actions handler no debe mostrarse', () => {
        globalThis.LearningActionHandlers = {
            shouldShowRecommendation: function () {
                return false;
            },
            isAvailable: function () {
                return false;
            }
        };

        var options = hooks.buildRenderOptions();
        var item = baseItem({
            primary_action: null,
            visible_actions: [
                {
                    key: 'pwa.install',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Instalar',
                    placement: 'primary',
                    target_status: null,
                    url: null,
                    handler: 'pwa.install'
                }
            ]
        });

        assert.equal(options.shouldRenderItem(item, {}), false);
        assert.equal(
            options.shouldRenderPrimaryAction(item.visible_actions[0], item, {}),
            false
        );
    });
});

describe('executable-lists-module wiring', () => {
    it('index.php carga renderer, coordinator y módulo experimental', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /executableListRenderer\.js/);
        assert.match(indexSrc, /executable-actions-coordinator\.js/);
        assert.match(indexSrc, /executable-lists-module\.js/);
        assert.match(indexSrc, /id="aa-executable-lists-experimental"/);
        assert.match(indexSrc, /id="aa-executable-lists-root"/);
        assert.match(indexSrc, /id="aa-executable-lists-error"/);
    });

    it('modo preview aplica interaction guard cuando acciones no están activas', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /if \(isActionsEnabled\(\)\)/);
        assert.match(moduleSrc, /bindInteractionGuard\(root\)/);
        assert.match(moduleSrc, /enableInteractiveRoot\(root\)/);
        assert.match(moduleSrc, /initActionsCoordinator\(root\)/);
    });

    it('modo preview bloquea interacción con inert y capture listener', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /setAttribute\('inert'/);
        assert.match(moduleSrc, /pointer-events-none/);
        assert.match(moduleSrc, /stopPropagation\(\)/);
        assert.match(moduleSrc, /service\.getFeed\(\)/);
        assert.match(moduleSrc, /renderFeed\(lists, buildRenderOptions\(\)\)/);
        assert.match(moduleSrc, /AA_EXECUTABLE_LISTS_DEBUG/);
        assert.match(moduleSrc, /AA_EXECUTABLE_LISTS_ACTIONS_DEBUG/);
    });
});
