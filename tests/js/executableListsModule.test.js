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
    let originalData;
    let originalHandlers;
    let originalSessionStorage;

    beforeEach(() => {
        originalDebug = globalThis.AA_EXECUTABLE_LISTS_DEBUG;
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
});

describe('executable-lists-module wiring', () => {
    it('index.php carga renderer y módulo experimental', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /executableListRenderer\.js/);
        assert.match(indexSrc, /executable-lists-module\.js/);
        assert.match(indexSrc, /id="aa-executable-lists-experimental"/);
        assert.match(indexSrc, /id="aa-executable-lists-root"/);
    });

    it('módulo bloquea interacción con inert y capture listener', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /setAttribute\('inert'/);
        assert.match(moduleSrc, /pointer-events-none/);
        assert.match(moduleSrc, /stopPropagation\(\)/);
        assert.match(moduleSrc, /service\.getFeed\(\)/);
        assert.match(moduleSrc, /renderFeed\(lists, buildRenderOptions\(\)\)/);
        assert.match(moduleSrc, /sessionStorage/);
        assert.match(moduleSrc, /AA_EXECUTABLE_LISTS_DEBUG/);
    });
});
