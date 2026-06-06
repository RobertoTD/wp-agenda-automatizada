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

        assert.match(moduleSrc, /if \(!isDebugEnabled\(\)\) \{\s*setExperimentalSectionVisible\(false\);\s*return;/);
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

describe('executable-lists-module MC13A visible user feed', () => {
    let originalVisibleFeed;
    let originalData;
    let originalSessionStorage;
    let originalRenderer;
    let originalService;
    let originalCoordinator;
    let originalDocument;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalData = globalThis.AA_EXECUTABLE_LISTS_DATA;
        originalSessionStorage = globalThis.sessionStorage;
        originalRenderer = globalThis.AAExecutableListRenderer;
        originalService = globalThis.ExecutableListsService;
        originalCoordinator = globalThis.ExecutableActionsCoordinator;
        originalDocument = globalThis.document;
    });

    afterEach(() => {
        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }

        if (originalData === undefined) {
            delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        } else {
            globalThis.AA_EXECUTABLE_LISTS_DATA = originalData;
        }

        if (originalSessionStorage === undefined) {
            delete globalThis.sessionStorage;
        } else {
            globalThis.sessionStorage = originalSessionStorage;
        }

        if (originalRenderer === undefined) {
            delete globalThis.AAExecutableListRenderer;
        } else {
            globalThis.AAExecutableListRenderer = originalRenderer;
        }

        if (originalService === undefined) {
            delete globalThis.ExecutableListsService;
        } else {
            globalThis.ExecutableListsService = originalService;
        }

        if (originalCoordinator === undefined) {
            delete globalThis.ExecutableActionsCoordinator;
        } else {
            globalThis.ExecutableActionsCoordinator = originalCoordinator;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
    });

    it('isVisibleUserFeedEnabled es false sin flag', () => {
        delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        assert.equal(hooks.isVisibleUserFeedEnabled(), false);
    });

    it('isVisibleUserFeedEnabled respeta window.AA_EXECUTABLE_VISIBLE_FEED=user', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user';

        assert.equal(hooks.isVisibleUserFeedEnabled(), true);
    });

    it('isVisibleUserFeedEnabled respeta AA_EXECUTABLE_LISTS_DATA.visibleFeed', () => {
        globalThis.AA_EXECUTABLE_LISTS_DATA = {
            visibleFeed: 'user'
        };

        assert.equal(hooks.isVisibleUserFeedEnabled(), true);
    });

    it('isVisibleUserFeedEnabled respeta sessionStorage AA_EXECUTABLE_VISIBLE_FEED=user', () => {
        globalThis.sessionStorage = {
            getItem: function (key) {
                return key === 'AA_EXECUTABLE_VISIBLE_FEED' ? 'user' : null;
            }
        };

        assert.equal(hooks.isVisibleUserFeedEnabled(), true);
        assert.equal(hooks.isSessionStorageVisibleFeedUserEnabled(), true);
    });

    it('filterUserLists deja solo source=user', () => {
        var lists = [
            { source: 'user', id: '1' },
            { source: 'system', id: 'system:learning.recommendations' },
            { source: 'user', id: '2' }
        ];

        var filtered = hooks.filterUserLists(lists);

        assert.equal(filtered.length, 2);
        assert.equal(filtered[0].id, '1');
        assert.equal(filtered[1].id, '2');
    });

    it('renderVisibleUserPayload no incluye system:learning.recommendations', () => {
        var renderedLists = null;

        globalThis.AAExecutableListRenderer = {
            renderFeed: function (lists) {
                renderedLists = lists;
                return '<div>feed</div>';
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return { innerHTML: '' };
                }

                return null;
            }
        };

        hooks.renderVisibleUserPayload({
            lists: [
                { source: 'user', id: '10', buckets: [] },
                { source: 'system', id: 'system:learning.recommendations', buckets: [] }
            ]
        });

        assert.ok(renderedLists);
        assert.equal(renderedLists.length, 1);
        assert.equal(renderedLists[0].id, '10');
    });

    it('loadVisibleUserFeed llama getFeed y re-renderiza user lists', async () => {
        var getFeedCalls = 0;
        var root = { innerHTML: '' };

        globalThis.ExecutableListsService = {
            getFeed: function () {
                getFeedCalls += 1;
                return Promise.resolve({
                    lists: [
                        { source: 'user', id: '7', buckets: [] },
                        { source: 'system', id: 'system:learning.recommendations', buckets: [] }
                    ]
                });
            }
        };

        globalThis.AAExecutableListRenderer = {
            renderFeed: function (lists) {
                assert.equal(lists.length, 1);
                assert.equal(lists[0].id, '7');
                return '<div>user-feed</div>';
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return root;
                }

                return null;
            }
        };

        await hooks.loadVisibleUserFeed();

        assert.equal(getFeedCalls, 1);
        assert.match(root.innerHTML, /user-feed/);
    });

    it('AAExecutableUserListsVisibleFeed.reload expone loadVisibleUserFeed', async () => {
        var getFeedCalls = 0;
        var root = { innerHTML: '' };

        globalThis.ExecutableListsService = {
            getFeed: function () {
                getFeedCalls += 1;
                return Promise.resolve({ lists: [] });
            }
        };

        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<p>empty</p>';
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return root;
                }

                return null;
            }
        };

        await hooks.visibleUserFeedApi.reload();

        assert.equal(getFeedCalls, 1);
    });

    it('initVisibleUserCoordinator inicializa coordinator con acciones activas', () => {
        var initCalls = [];

        globalThis.ExecutableActionsCoordinator = {
            init: function (opts) {
                initCalls.push(opts);
            }
        };

        var root = { id: 'aa-executable-user-lists-root' };

        hooks.initVisibleUserCoordinator(root);
        hooks.initVisibleUserCoordinator(root);

        assert.equal(initCalls.length, 1);
        assert.equal(initCalls[0].root, root);
        assert.equal(typeof initCalls[0].reload, 'function');
        assert.equal(initCalls[0].findLearningItem(), null);
    });

    it('flag off mantiene root visible user oculto en init', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /if \(!isVisibleUserFeedEnabled\(\)\) \{\s*setVisibleUserSectionVisible\(false\);\s*setLegacyBoardVisible\(true\);\s*return;/);
    });

    it('visible user feed no depende de AA_EXECUTABLE_LISTS_ACTIONS_DEBUG', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');
        var visibleInitBlock = moduleSrc.match(/function initVisibleUserFeedModule\(\)[\s\S]{0,700}/);

        assert.ok(visibleInitBlock, 'initVisibleUserFeedModule definido');
        assert.match(visibleInitBlock[0], /enableInteractiveRoot\(root\)/);
        assert.match(visibleInitBlock[0], /initVisibleUserCoordinator\(root\)/);
        assert.doesNotMatch(visibleInitBlock[0], /isActionsEnabled/);
    });

    it('experimental debug sigue gated por AA_EXECUTABLE_LISTS_DEBUG', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /function initExperimentalModule\(\)/);
        assert.match(moduleSrc, /if \(!isDebugEnabled\(\)\)/);
    });
});

describe('executable-lists-module MC13B user-swap', () => {
    let originalVisibleFeed;
    let originalBoard;
    let originalDocument;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalBoard = globalThis.AATasksBoard;
        originalDocument = globalThis.document;
    });

    afterEach(() => {
        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }

        if (originalBoard === undefined) {
            delete globalThis.AATasksBoard;
        } else {
            globalThis.AATasksBoard = originalBoard;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
    });

    it('isUserSwapEnabled es true con user-swap', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        assert.equal(hooks.isUserSwapEnabled(), true);
        assert.equal(hooks.isVisibleUserFeedEnabled(), true);
        assert.equal(hooks.readVisibleFeedFlag(), 'user-swap');
    });

    it('user conserva semántica parallel sin swap', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user';

        assert.equal(hooks.isVisibleUserFeedEnabled(), true);
        assert.equal(hooks.isUserSwapEnabled(), false);
    });

    it('getVisibleUserEmptyMessage en swap incluye CTA FAB', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        assert.match(hooks.getVisibleUserEmptyMessage(), /botón flotante/i);
    });

    it('applyUserSwapLayout oculta legacy board en user-swap', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        var legacyBoard = {
            classList: {
                classes: [],
                add: function (name) {
                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                },
                remove: function (name) {
                    this.classes = this.classes.filter(function (item) {
                        return item !== name;
                    });
                }
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-tasks-board-root') {
                    return legacyBoard;
                }

                return null;
            }
        };

        hooks.applyUserSwapLayout();

        assert.equal(legacyBoard.classList.classes.includes('hidden'), true);
    });

    it('applyUserSwapLayout mantiene legacy visible en modo user', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user';

        var legacyBoard = {
            classList: {
                classes: ['hidden'],
                add: function (name) {
                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                },
                remove: function (name) {
                    this.classes = this.classes.filter(function (item) {
                        return item !== name;
                    });
                }
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-tasks-board-root') {
                    return legacyBoard;
                }

                return null;
            }
        };

        hooks.applyUserSwapLayout();

        assert.equal(legacyBoard.classList.classes.includes('hidden'), false);
    });

    it('reloadVisibleUserFeedWithBoardSync recarga board en swap', async () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        var boardReloadCalls = 0;
        var getFeedCalls = 0;
        var root = { innerHTML: '' };

        globalThis.AATasksBoard = {
            reload: function (options) {
                boardReloadCalls += 1;
                assert.equal(options.silent, true);
                return Promise.resolve();
            }
        };

        globalThis.ExecutableListsService = {
            getFeed: function () {
                getFeedCalls += 1;
                return Promise.resolve({ lists: [] });
            }
        };

        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<p>empty</p>';
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return root;
                }

                return null;
            }
        };

        await hooks.reloadVisibleUserFeedWithBoardSync();

        assert.equal(getFeedCalls, 1);
        assert.equal(boardReloadCalls, 1);
    });

    it('initVisibleUserCoordinator usa reload compuesto en swap', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /reloadVisibleUserFeedWithBoardSync/);
        assert.match(moduleSrc, /reload: reloadVisibleUserFeedWithBoardSync/);
    });
});

describe('executable-lists-module MC13C user-swap hardening', () => {
    let originalVisibleFeed;
    let originalRenderer;
    let originalService;
    let originalDocument;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalRenderer = globalThis.AAExecutableListRenderer;
        originalService = globalThis.ExecutableListsService;
        originalDocument = globalThis.document;
    });

    afterEach(() => {
        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }

        if (originalRenderer === undefined) {
            delete globalThis.AAExecutableListRenderer;
        } else {
            globalThis.AAExecutableListRenderer = originalRenderer;
        }

        if (originalService === undefined) {
            delete globalThis.ExecutableListsService;
        } else {
            globalThis.ExecutableListsService = originalService;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
    });

    it('loadVisibleUserFeed muestra loading durante fetch', async () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        var resolveFeed;
        var loadingVisible = false;
        var root = { innerHTML: 'prev' };
        var loadingEl = {
            textContent: '',
            classList: {
                classes: ['hidden'],
                add: function (name) {
                    if (name === 'hidden') {
                        loadingVisible = false;
                    }

                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                },
                remove: function (name) {
                    if (name === 'hidden') {
                        loadingVisible = true;
                    }

                    this.classes = this.classes.filter(function (item) {
                        return item !== name;
                    });
                }
            }
        };
        var errorEl = {
            textContent: '',
            classList: {
                classes: ['hidden'],
                add: function (name) {
                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                },
                remove: function () {}
            }
        };

        globalThis.ExecutableListsService = {
            getFeed: function () {
                return new Promise(function (resolve) {
                    resolveFeed = resolve;
                });
            }
        };

        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<div>feed</div>';
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return root;
                }

                if (id === 'aa-executable-user-lists-loading') {
                    return loadingEl;
                }

                if (id === 'aa-executable-user-lists-error') {
                    return errorEl;
                }

                return null;
            }
        };

        var pending = hooks.loadVisibleUserFeed();

        assert.equal(loadingVisible, true);
        assert.equal(root.innerHTML, '');

        resolveFeed({ lists: [{ source: 'user', id: '1', buckets: [] }] });
        await pending;

        assert.equal(loadingVisible, false);
        assert.match(root.innerHTML, /feed/);
    });

    it('error de carga usa #aa-executable-user-lists-error', async () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        var errorMessage = '';
        var errorVisible = false;
        var root = { innerHTML: 'content' };
        var loadingEl = {
            textContent: '',
            classList: {
                classes: [],
                add: function (name) {
                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                },
                remove: function () {}
            }
        };
        var errorEl = {
            textContent: '',
            classList: {
                classes: ['hidden'],
                add: function (name) {
                    if (name === 'hidden') {
                        errorVisible = false;
                    }
                },
                remove: function (name) {
                    if (name === 'hidden') {
                        errorVisible = true;
                    }
                }
            }
        };

        globalThis.ExecutableListsService = {
            getFeed: function () {
                return Promise.reject(new Error('feed caído'));
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return root;
                }

                if (id === 'aa-executable-user-lists-loading') {
                    return loadingEl;
                }

                if (id === 'aa-executable-user-lists-error') {
                    return errorEl;
                }

                return null;
            }
        };

        await hooks.loadVisibleUserFeed();

        assert.equal(errorVisible, true);
        assert.match(errorEl.textContent, /feed caído/);
        assert.equal(root.innerHTML, '');
    });

    it('reload exitoso limpia error previo', async () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        var errorEl = {
            textContent: 'error previo',
            classList: {
                classes: [],
                add: function (name) {
                    if (!this.classes.includes(name)) {
                        this.classes.push(name);
                    }
                },
                remove: function (name) {
                    this.classes = this.classes.filter(function (item) {
                        return item !== name;
                    });
                }
            }
        };
        var loadingEl = {
            textContent: '',
            classList: {
                classes: [],
                add: function () {},
                remove: function () {}
            }
        };
        var root = { innerHTML: '' };

        globalThis.ExecutableListsService = {
            getFeed: function () {
                return Promise.resolve({ lists: [] });
            }
        };

        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '';
            }
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-user-lists-root') {
                    return root;
                }

                if (id === 'aa-executable-user-lists-loading') {
                    return loadingEl;
                }

                if (id === 'aa-executable-user-lists-error') {
                    return errorEl;
                }

                return null;
            }
        };

        await hooks.loadVisibleUserFeed();

        assert.equal(errorEl.textContent, '');
        assert.equal(errorEl.classList.classes.includes('hidden'), true);
    });

    it('index.php incluye contenedor de loading del feed user', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /id="aa-executable-user-lists-loading"/);
        assert.match(indexSrc, /Cargando listas/);
    });

    it('showVisibleUserLoadError delega al canal de error dedicado', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');
        var loadErrorBlock = moduleSrc.match(/function showVisibleUserLoadError[\s\S]{0,350}/);

        assert.ok(loadErrorBlock, 'showVisibleUserLoadError definido');
        assert.match(loadErrorBlock[0], /showVisibleUserError/);
        assert.doesNotMatch(loadErrorBlock[0], /innerHTML = '<p class="text-sm text-red-600"/);
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
        assert.match(indexSrc, /id="aa-executable-user-lists-visible"/);
        assert.match(indexSrc, /id="aa-executable-user-lists-root"/);
        assert.match(indexSrc, /id="aa-executable-user-lists-error"/);
    });

    it('modo preview aplica interaction guard cuando acciones no están activas', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /if \(isActionsEnabled\(\)\)/);
        assert.match(moduleSrc, /bindInteractionGuard\(root\)/);
        assert.match(moduleSrc, /enableInteractiveRoot\(root\)/);
        assert.match(moduleSrc, /initExperimentalCoordinator\(root\)/);
        assert.match(moduleSrc, /initVisibleUserCoordinator\(root\)/);
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
        assert.match(moduleSrc, /AA_EXECUTABLE_VISIBLE_FEED/);
    });
});
