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

    it('buildRenderOptions muestra appointment.confirm con origin_key válido', () => {
        const handlersPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/learning-action-handlers.js');

        delete require.cache[handlersPath];
        require(handlersPath);

        globalThis.ConfirmService = {
            confirmar: function () {
                return Promise.resolve({ success: true });
            }
        };
        globalThis.aa_asistant_vars = { nonce_confirmar: 'test-nonce' };

        var options = hooks.buildRenderOptions();
        var item = baseItem({
            origin_key: 'appointment_confirmation:42',
            primary_action: {
                type: 'handler',
                label: 'Confirmar',
                handler: 'appointment.confirm'
            },
            visible_actions: [
                {
                    key: 'appointment.confirm',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Confirmar',
                    placement: 'primary',
                    target_status: null,
                    url: null,
                    handler: 'appointment.confirm'
                }
            ]
        });

        assert.equal(options.shouldRenderItem(item, {}), true);
        assert.equal(
            options.shouldRenderPrimaryAction(item.visible_actions[0], item, {}),
            true
        );
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


describe('executable-lists-module MC13H unified feed', () => {
    let originalVisibleFeed;
    let originalDocument;
    let originalRenderer;
    let originalService;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalDocument = globalThis.document;
        originalRenderer = globalThis.AAExecutableListRenderer;
        originalService = globalThis.ExecutableListsService;
    });

    afterEach(() => {
        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
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
    });

    it('isUnifiedFeedEnabled es true con unified', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'unified';

        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        assert.equal(hooks.isExecutableVisibleFeedEnabled(), true);
        assert.equal(hooks.isUserSwapEnabled(), false);
        assert.equal(hooks.readVisibleFeedFlag(), 'unified');
    });

    it('unified no activa modo user-swap', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'unified';

        assert.equal(hooks.isUserSwapEnabled(), false);
    });

    it('filterListsForUnifiedRender incluye system con items y user lists', () => {
        var lists = [
            {
                id: 'system:learning.recommendations',
                source: 'system',
                buckets: [{ key: 'primary', items: [{ id: 'a' }] }]
            },
            {
                id: '7',
                source: 'user',
                buckets: [{ key: 'primary', items: [] }]
            }
        ];

        var filtered = hooks.filterListsForUnifiedRender(lists);

        assert.equal(filtered.length, 2);
        assert.equal(filtered[0].source, 'system');
        assert.equal(filtered[1].source, 'user');
    });

    it('filterListsForUnifiedRender omite system list vacía', () => {
        var lists = [
            {
                id: 'system:learning.recommendations',
                source: 'system',
                buckets: []
            },
            {
                id: '7',
                source: 'user',
                buckets: [{ key: 'primary', items: [] }]
            }
        ];

        var filtered = hooks.filterListsForUnifiedRender(lists);

        assert.equal(filtered.length, 1);
        assert.equal(filtered[0].source, 'user');
    });

    it('filterListsForUnifiedRender omite agenda_app seeded vacía por regla system', () => {
        var lists = [
            {
                id: '50',
                source: 'system',
                source_category: 'agenda_app',
                origin_key: 'learning.recommendations',
                buckets: []
            },
            {
                id: '7',
                source: 'user',
                buckets: []
            }
        ];

        var filtered = hooks.filterListsForUnifiedRender(lists);

        assert.equal(filtered.length, 1);
        assert.equal(filtered[0].source, 'user');
        assert.equal(filtered[0].id, '7');
    });

    it('filterListsForUnifiedRender omite system con buckets sin items', () => {
        var lists = [
            {
                id: 'system:learning.recommendations',
                source: 'system',
                buckets: [
                    { key: 'primary', label: 'Principales', items: [] },
                    { key: 'secondary', label: 'Secundarias', items: [] }
                ]
            }
        ];

        var filtered = hooks.filterListsForUnifiedRender(lists);

        assert.equal(filtered.length, 0);
    });

    it('filterListsForUnifiedRender omite appointment_actions vacía por regla explícita', () => {
        var lists = [
            {
                id: '88',
                source: 'system',
                source_category: 'agenda_app',
                origin_key: 'appointment_actions',
                buckets: []
            },
            {
                id: '7',
                source: 'user',
                buckets: []
            }
        ];

        var filtered = hooks.filterListsForUnifiedRender(lists);

        assert.equal(filtered.length, 1);
        assert.equal(filtered[0].id, '7');
    });

    it('filterListsForUnifiedRender incluye appointment_actions con items vigentes', () => {
        var lists = [
            {
                id: '88',
                source: 'system',
                source_category: 'agenda_app',
                origin_key: 'appointment_actions',
                buckets: [{ key: 'primary', items: [{ id: '42' }] }]
            }
        ];

        var filtered = hooks.filterListsForUnifiedRender(lists);

        assert.equal(filtered.length, 1);
        assert.equal(filtered[0].origin_key, 'appointment_actions');
    });

    it('renderUnifiedPayload conserva user list vacía con mensaje de pendientes', () => {
        var rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
        var renderer = require(rendererPath);
        var root = { innerHTML: '' };

        globalThis.AAExecutableListRenderer = renderer;
        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-lists-active-root') {
                    return root;
                }

                return null;
            }
        };

        hooks.renderUnifiedPayload({
            lists: [
                {
                    id: '7',
                    source: 'user',
                    source_label: 'Mis listas',
                    title: 'Vacía',
                    description: 'Sin pendientes',
                    buckets: []
                }
            ]
        });

        assert.match(root.innerHTML, /No hay tareas pendientes en esta lista/);
        assert.doesNotMatch(root.innerHTML, /aa-executable-list-empty-pending[\s\S]*system/);
    });

    it('renderUnifiedPayload renderiza system y user sin filtrar user-only', () => {
        globalThis.AAExecutableListRenderer = {
            renderFeed: function (lists) {
                return lists.map(function (list) {
                    return list.source;
                }).join(',');
            }
        };

        var root = { innerHTML: '' };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-lists-active-root') {
                    return root;
                }

                return null;
            }
        };

        hooks.renderUnifiedPayload({
            lists: [
                {
                    id: 'system:learning.recommendations',
                    source: 'system',
                    buckets: [{ key: 'primary', items: [{ id: 'rec' }] }]
                },
                {
                    id: '7',
                    source: 'user',
                    buckets: [{ key: 'primary', items: [{ id: '10' }] }]
                }
            ]
        });

        assert.match(root.innerHTML, /system,user/);
    });

    it('applyUnifiedLayout oculta board y muestra active root', () => {
        function makeEl() {
            return {
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
        }

        var board = makeEl();
        var unified = makeEl();
        unified.classList.classes = ['hidden'];

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-tasks-board-root') {
                    return board;
                }

                if (id === 'aa-executable-lists-active') {
                    return unified;
                }

                return null;
            }
        };

        hooks.applyUnifiedLayout();

        assert.equal(board.classList.classes.includes('hidden'), true);
        assert.equal(unified.classList.classes.includes('hidden'), false);
    });

    it('findUnifiedLearningItem resuelve item system desde payload unified', () => {
        var payload = {
            lists: [{
                source: 'system',
                buckets: [{
                    items: [{
                        id: 'install_pwa',
                        source: 'system',
                        origin_key: 'install_pwa'
                    }]
                }]
            }]
        };

        var item = hooks.findLearningItemInPayload(payload, 'install_pwa');

        assert.equal(item && item.origin_key, 'install_pwa');
    });

    it('initUnifiedCoordinator cablea findLearningItem funcional', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');
        var unifiedCoordinatorBlock = moduleSrc.match(/function initUnifiedCoordinator[\s\S]{0,450}/);

        assert.ok(unifiedCoordinatorBlock, 'initUnifiedCoordinator definido');
        assert.match(unifiedCoordinatorBlock[0], /findLearningItem: findUnifiedLearningItem/);
        assert.match(unifiedCoordinatorBlock[0], /reloadUnifiedFeedWithBoardSync/);
    });

    it('reloadExecutableVisibleFeed siempre usa flujo unified', async () => {
        var unifiedReloadCalls = 0;

        globalThis.ExecutableListsService = {
            getFeed: function () {
                unifiedReloadCalls += 1;
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
                if (id === 'aa-executable-lists-active-root') {
                    return { innerHTML: '' };
                }

                if (id === 'aa-executable-lists-active-loading') {
                    return {
                        textContent: '',
                        classList: {
                            classes: [],
                            add: function (name) {
                                this.classes.push(name);
                            },
                            remove: function () {}
                        }
                    };
                }

                if (id === 'aa-executable-lists-active-error') {
                    return {
                        textContent: '',
                        classList: {
                            classes: ['hidden'],
                            add: function () {},
                            remove: function () {}
                        }
                    };
                }

                return null;
            }
        };

        await hooks.reloadExecutableVisibleFeed();

        assert.equal(unifiedReloadCalls, 1);
    });

    it('visibleUserFeedApi expone reload e isEnabled', () => {
        assert.equal(typeof hooks.visibleUserFeedApi.reload, 'function');
        assert.equal(typeof hooks.visibleUserFeedApi.isEnabled, 'function');
        assert.equal(typeof hooks.visibleUserFeedApi.isUnifiedEnabled, 'function');
        assert.equal(hooks.visibleUserFeedApi.isSwapEnabled(), false);
    });

    it('debug experimental sigue gated por isDebugEnabled', () => {
        delete globalThis.AA_EXECUTABLE_LISTS_DEBUG;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        assert.equal(hooks.isDebugEnabled(), false);
        assert.equal(hooks.isActionsEnabled(), false);
    });

    it('index.php incluye contenedor unified active feed', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /id="aa-executable-lists-active"/);
        assert.match(indexSrc, /id="aa-executable-lists-active-root"/);
        assert.match(indexSrc, /id="aa-executable-lists-active-loading"/);
        assert.match(indexSrc, /id="aa-executable-lists-active-error"/);
    });
});

describe('executable-lists-module MC13J unified default', () => {
    let originalVisibleFeed;
    let originalDocument;
    let originalRenderer;
    let originalService;
    let originalData;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalDocument = globalThis.document;
        originalRenderer = globalThis.AAExecutableListRenderer;
        originalService = globalThis.ExecutableListsService;
        originalData = globalThis.AA_EXECUTABLE_LISTS_DATA;
    });

    afterEach(() => {
        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
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

        if (originalData === undefined) {
            delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        } else {
            globalThis.AA_EXECUTABLE_LISTS_DATA = originalData;
        }
    });

    function clearAllFeedFlags() {
        delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;
    }

    it('sin flag → resolveEffectiveFeedMode devuelve unified', () => {
        clearAllFeedFlags();

        assert.equal(hooks.readVisibleFeedFlag(), null);
        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        assert.equal(hooks.isExecutableVisibleFeedEnabled(), true);
    });

    it('sin flag → applyUnifiedLayout oculta board y muestra active root', () => {
        clearAllFeedFlags();

        function makeEl() {
            return {
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
        }

        var board = makeEl();
        var unified = makeEl();
        unified.classList.classes = ['hidden'];

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-tasks-board-root') {
                    return board;
                }

                if (id === 'aa-executable-lists-active') {
                    return unified;
                }

                return null;
            }
        };

        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        hooks.applyUnifiedLayout();

        assert.equal(board.classList.classes.includes('hidden'), true);
        assert.equal(unified.classList.classes.includes('hidden'), false);
    });

    it('legacy mapea a unified (MC13J-2B)', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'legacy';

        assert.equal(hooks.normalizeVisibleFeedFlag('legacy'), 'unified');
        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isLegacyListsViewEnabled(), false);
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        assert.equal(hooks.isExecutableVisibleFeedEnabled(), true);
    });

    it('off mapea a unified (MC13J-2B)', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'off';

        assert.equal(hooks.normalizeVisibleFeedFlag('off'), 'unified');
        assert.equal(hooks.readVisibleFeedFlag(), 'unified');
        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        assert.equal(hooks.isLegacyListsViewEnabled(), false);
    });

    it('unified explícito sigue activo', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'unified';

        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
    });

    it('user mapea a unified (MC13J-2C)', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user';

        assert.equal(hooks.normalizeVisibleFeedFlag('user'), 'unified');
        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        assert.equal(hooks.isUserSwapEnabled(), false);
        assert.equal(hooks.isExecutableVisibleFeedEnabled(), true);
    });

    it('user-swap mapea a unified (MC13J-2C)', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'user-swap';

        assert.equal(hooks.normalizeVisibleFeedFlag('user-swap'), 'unified');
        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
        assert.equal(hooks.isUserSwapEnabled(), false);
    });

    it('reload() por default usa flujo unified', async () => {
        clearAllFeedFlags();

        var unifiedReloadCalls = 0;

        globalThis.ExecutableListsService = {
            getFeed: function () {
                unifiedReloadCalls += 1;
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
                if (id === 'aa-executable-lists-active-root') {
                    return { innerHTML: '' };
                }

                if (id === 'aa-executable-lists-active-loading') {
                    return {
                        textContent: '',
                        classList: {
                            classes: [],
                            add: function () {},
                            remove: function () {}
                        }
                    };
                }

                if (id === 'aa-executable-lists-active-error') {
                    return {
                        textContent: '',
                        classList: {
                            classes: ['hidden'],
                            add: function () {},
                            remove: function () {}
                        }
                    };
                }

                return null;
            }
        };

        await hooks.reloadExecutableVisibleFeed();

        assert.equal(unifiedReloadCalls, 1);
    });

    it('window legacy mapea a unified aunque cfg diga unified (MC13J-2B)', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'legacy';
        globalThis.AA_EXECUTABLE_LISTS_DATA = {
            visibleFeed: 'unified'
        };

        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
    });

    it('sessionStorage legacy mapea a unified sin window ni cfg (MC13J-2B)', () => {
        globalThis.sessionStorage = {
            getItem: function (key) {
                if (key === 'AA_EXECUTABLE_VISIBLE_FEED') {
                    return 'legacy';
                }

                return null;
            }
        };

        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isUnifiedFeedEnabled(), true);
    });

    it('index.php ancla visibleFeed unified en AA_EXECUTABLE_LISTS_DATA', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /visibleFeed:\s*'unified'/);
    });

    it('index.php no incluye DOM Learning legacy (MC13J-2B)', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.doesNotMatch(indexSrc, /id="aa-learning-recommendations"/);
        assert.doesNotMatch(indexSrc, /learning-module\.js/);
        assert.doesNotMatch(indexSrc, /learningRecommendationRenderer\.js/);
        assert.match(indexSrc, /learningService\.js/);
        assert.match(indexSrc, /learning-action-handlers\.js/);
    });

    it('initExecutableListsModule inicia unified y experimental', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /function resolveEffectiveFeedMode\(\)/);
        assert.match(moduleSrc, /initUnifiedFeedModule\(\)/);
        assert.match(moduleSrc, /initExperimentalModule\(\)/);
        assert.match(moduleSrc, /initializePushActivationFeed\(\)/);
        assert.match(moduleSrc, /initializeFeedContext\(\)/);
        assert.doesNotMatch(moduleSrc, /triggerPushActivationReconcile\(\)/);
        assert.doesNotMatch(moduleSrc, /reconcileProducedFeedChanges/);
        assert.doesNotMatch(moduleSrc, /pushInitReconcileFeedReloadDone/);
        assert.doesNotMatch(moduleSrc, /initVisibleUserFeedModule/);
        assert.doesNotMatch(moduleSrc, /aa-executable-user-lists-visible/);
    });

    it('learning-action-handlers registra push.activate', () => {
        const fs = require('node:fs');
        const handlersPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/learning-action-handlers.js');
        const handlersSrc = fs.readFileSync(handlersPath, 'utf8');

        assert.match(handlersSrc, /register\('push\.activate'/);
        assert.match(handlersSrc, /activateRegisterAndMarkReady\(\)/);
        assert.doesNotMatch(handlersSrc, /reconcilePushActivationTask\('prepared'\)/);
    });
});

describe('executable-lists-module MC13J-2C user modes retired', () => {
    it('index.php no contiene DOM user-only', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.doesNotMatch(indexSrc, /id="aa-executable-user-lists-visible"/);
        assert.doesNotMatch(indexSrc, /id="aa-executable-user-lists-root"/);
        assert.doesNotMatch(indexSrc, /id="aa-executable-user-lists-error"/);
        assert.match(indexSrc, /id="aa-executable-lists-active-root"/);
    });

    it('reloadExecutableVisibleFeed delega a reloadUnifiedFeedWithBoardSync', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /function reloadExecutableVisibleFeed\(\)/);
        assert.match(moduleSrc, /return reloadUnifiedFeedWithBoardSync\(\)/);
    });

    it('API pública no expone filterUserLists ni renderVisibleUserPayload', () => {
        assert.equal(typeof hooks.visibleUserFeedApi.filterUserLists, 'undefined');
        assert.equal(typeof hooks.visibleUserFeedApi.renderVisibleUserPayload, 'undefined');
        assert.equal(typeof hooks.visibleUserFeedApi.filterListsForUnifiedRender, 'function');
        assert.equal(typeof hooks.visibleUserFeedApi.renderUnifiedPayload, 'function');
    });
});

describe('executable-lists-module restore open list card', () => {
    let originalListOptions;
    let originalService;
    let originalRenderer;
    let originalDocument;
    let originalVisibleFeed;

    function makeUnifiedDocumentMock(root) {
        return {
            getElementById: function (id) {
                if (id === 'aa-executable-lists-active-root') {
                    return root;
                }

                if (id === 'aa-executable-lists-active-loading') {
                    return {
                        textContent: '',
                        classList: {
                            classes: [],
                            add: function () {},
                            remove: function () {}
                        }
                    };
                }

                if (id === 'aa-executable-lists-active-error') {
                    return {
                        textContent: '',
                        classList: {
                            classes: ['hidden'],
                            add: function () {},
                            remove: function () {}
                        },
                        remove: function () {}
                    };
                }

                return null;
            }
        };
    }

    function makeTrackableRoot(initialInner, childElementCount) {
        var events = [];
        var busy = null;
        var root = {
            childElementCount: childElementCount,
            _inner: initialInner,
            get innerHTML() {
                return this._inner;
            },
            set innerHTML(value) {
                events.push({ type: 'innerHTML', value: value });
                this._inner = value;
            },
            setAttribute: function (name, value) {
                if (name === 'aria-busy') {
                    events.push({ type: 'aria-busy', value: value });
                    busy = value;
                }
            },
            removeAttribute: function (name) {
                if (name === 'aria-busy') {
                    events.push({ type: 'aria-busy', value: null });
                    busy = null;
                }
            },
            getAriaBusy: function () {
                return busy;
            },
            events: events
        };

        return root;
    }

    beforeEach(() => {
        originalListOptions = globalThis.AAListOptions;
        originalService = globalThis.ExecutableListsService;
        originalRenderer = globalThis.AAExecutableListRenderer;
        originalDocument = globalThis.document;
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;

        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'unified';
        globalThis.ExecutableListsService = {
            getFeed: function () {
                return Promise.resolve({ lists: [] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details class="aa-executable-list-card" data-list-id="7"></details>';
            }
        };
    });

    afterEach(() => {
        if (originalListOptions === undefined) {
            delete globalThis.AAListOptions;
        } else {
            globalThis.AAListOptions = originalListOptions;
        }

        if (originalService === undefined) {
            delete globalThis.ExecutableListsService;
        } else {
            globalThis.ExecutableListsService = originalService;
        }

        if (originalRenderer === undefined) {
            delete globalThis.AAExecutableListRenderer;
        } else {
            globalThis.AAExecutableListRenderer = originalRenderer;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }

        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }
    });

    it('reloadUnifiedFeedWithBoardSync captura snapshot, no vacía en loading y restaura tras render', async () => {
        var events = [];
        var root = makeTrackableRoot('has-content', 1);

        globalThis.AAListOptions = {
            getListRestoreSnapshot: function (activeRoot) {
                events.push({
                    type: 'capture',
                    innerHTML: activeRoot.innerHTML
                });

                return {
                    restoreOpenListId: '7',
                    restoreFollowingTasksOpen: true
                };
            },
            restoreListAfterReload: function (listId, root, options) {
                events.push({
                    type: 'restore',
                    listId: listId,
                    followingTasksOpen: options && options.followingTasksOpen
                });
            }
        };

        globalThis.document = makeUnifiedDocumentMock(root);

        await hooks.reloadUnifiedFeedWithBoardSync();

        var captureEvent = events.find(function (entry) {
            return entry.type === 'capture';
        });
        var clearEvent = root.events.find(function (entry) {
            return entry.type === 'innerHTML' && entry.value === '';
        });
        var renderEvent = root.events.find(function (entry) {
            return entry.type === 'innerHTML' && entry.value !== '';
        });
        var restoreEvent = events.find(function (entry) {
            return entry.type === 'restore';
        });

        assert.ok(captureEvent);
        assert.equal(captureEvent.innerHTML, 'has-content');
        assert.equal(clearEvent, undefined);
        assert.ok(renderEvent);
        assert.ok(restoreEvent);
        assert.equal(restoreEvent.listId, '7');
        assert.equal(restoreEvent.followingTasksOpen, true);
        assert.ok(events.indexOf(captureEvent) < events.indexOf(restoreEvent));
    });

    it('loadUnifiedFeed con root vacío vacía el root al iniciar loading', async () => {
        var root = makeTrackableRoot('', 0);

        globalThis.document = makeUnifiedDocumentMock(root);

        await hooks.loadUnifiedFeed();

        var clearEvent = root.events.find(function (entry) {
            return entry.type === 'innerHTML' && entry.value === '';
        });
        var renderEvent = root.events.find(function (entry) {
            return entry.type === 'innerHTML' && entry.value !== '';
        });

        assert.ok(clearEvent);
        assert.ok(renderEvent);
        assert.ok(root.events.indexOf(clearEvent) < root.events.indexOf(renderEvent));
    });

    it('loadUnifiedFeed con root pre-poblado no vacía hasta el render del payload', async () => {
        var root = makeTrackableRoot('stale-content', 1);

        globalThis.document = makeUnifiedDocumentMock(root);

        await hooks.loadUnifiedFeed();

        var clearEvent = root.events.find(function (entry) {
            return entry.type === 'innerHTML' && entry.value === '';
        });
        var renderEvent = root.events.find(function (entry) {
            return entry.type === 'innerHTML' && entry.value !== '';
        });

        assert.equal(clearEvent, undefined);
        assert.ok(renderEvent);
        assert.notEqual(renderEvent.value, 'stale-content');
    });

    it('loadUnifiedFeed en recarga con error conserva DOM, payload y retira aria-busy', async () => {
        var root = makeTrackableRoot('stale-content', 1);
        var loadCount = 0;

        globalThis.ExecutableListsService = {
            getFeed: function () {
                loadCount += 1;

                if (loadCount === 1) {
                    return Promise.resolve({
                        lists: [{
                            source: 'system',
                            buckets: [{
                                items: [baseItem({ id: 'install_pwa', origin_key: 'install_pwa' })]
                            }]
                        }]
                    });
                }

                return Promise.reject(new Error('feed unavailable'));
            }
        };

        globalThis.document = makeUnifiedDocumentMock(root);

        await hooks.loadUnifiedFeed();
        assert.equal(loadCount, 1);

        var innerAfterSuccess = root.innerHTML;

        await hooks.loadUnifiedFeed();
        assert.equal(loadCount, 2);

        var clearAfterFirstRender = root.events.find(function (entry, index) {
            return entry.type === 'innerHTML'
                && entry.value === ''
                && index > root.events.findIndex(function (e) {
                    return e.type === 'innerHTML' && e.value !== '';
                });
        });
        var busyRemoved = root.events.some(function (entry) {
            return entry.type === 'aria-busy' && entry.value === null;
        });

        assert.equal(clearAfterFirstRender, undefined);
        assert.equal(root.innerHTML, innerAfterSuccess);
        assert.ok(busyRemoved);
        assert.equal(root.getAriaBusy(), null);
        assert.ok(hooks.findUnifiedLearningItem('install_pwa'));
    });

    it('loadUnifiedFeed devuelve la promesa in-flight si ya hay una carga activa', async () => {
        var root = makeTrackableRoot('stale-content', 1);
        var resolveFeed;
        var getFeedCalls = 0;

        globalThis.ExecutableListsService = {
            getFeed: function () {
                getFeedCalls += 1;

                return new Promise(function (resolve) {
                    resolveFeed = resolve;
                });
            }
        };

        globalThis.document = makeUnifiedDocumentMock(root);

        var first = hooks.loadUnifiedFeed();
        var second = hooks.loadUnifiedFeed();

        assert.equal(getFeedCalls, 1);
        assert.strictEqual(first, second);

        resolveFeed({ lists: [] });

        await first;
        await second;
    });

    it('loadUnifiedFeed sin opciones no intenta restaurar', async () => {
        var restoreCalls = 0;

        globalThis.AAListOptions = {
            getListRestoreSnapshot: function () {
                return {
                    restoreOpenListId: '1',
                    restoreFollowingTasksOpen: true
                };
            },
            restoreListAfterReload: function () {
                restoreCalls += 1;
            }
        };

        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        await hooks.loadUnifiedFeed();

        assert.equal(restoreCalls, 0);
    });

    it('reloadFeedOnly no restaura listas', async () => {
        var restoreCalls = 0;

        globalThis.AAListOptions = {
            restoreListAfterReload: function () {
                restoreCalls += 1;
            }
        };

        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        await hooks.visibleUserFeedApi.reloadFeedOnly();

        assert.equal(restoreCalls, 0);
    });
});

describe('executable-lists-module push activation feed bootstrap', () => {
    let originalPushReconcileService;
    let originalService;
    let originalRenderer;
    let originalDocument;
    let originalVisibleFeed;

    function makeLoadingEl(initialHidden) {
        return {
            textContent: 'Cargando listas…',
            classList: {
                classes: initialHidden ? ['hidden'] : [],
                add: function (name) {
                    if (this.classes.indexOf(name) === -1) {
                        this.classes.push(name);
                    }
                },
                remove: function (name) {
                    this.classes = this.classes.filter(function (item) {
                        return item !== name;
                    });
                },
                contains: function (name) {
                    return this.classes.indexOf(name) !== -1;
                }
            }
        };
    }

    function makeUnifiedDocumentMock(root, loadingEl) {
        var loading = loadingEl || makeLoadingEl(false);

        return {
            getElementById: function (id) {
                if (id === 'aa-executable-lists-active-root') {
                    return root;
                }

                if (id === 'aa-executable-lists-active-loading') {
                    return loading;
                }

                if (id === 'aa-executable-lists-active-error') {
                    return {
                        textContent: '',
                        classList: {
                            classes: ['hidden'],
                            add: function (name) {
                                if (this.classes.indexOf(name) === -1) {
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
                }

                return null;
            }
        };
    }

    function makeTrackableRoot(initialInner, childElementCount) {
        return {
            childElementCount: childElementCount,
            _inner: initialInner,
            _attrs: {},
            get innerHTML() {
                return this._inner;
            },
            set innerHTML(value) {
                this._inner = value;
                this.childElementCount = value ? 1 : 0;
            },
            setAttribute: function (name, value) {
                this._attrs[name] = value;
            },
            removeAttribute: function (name) {
                delete this._attrs[name];
            }
        };
    }

    function deferred() {
        var resolveFn;
        var rejectFn;
        var promise = new Promise(function (resolve, reject) {
            resolveFn = resolve;
            rejectFn = reject;
        });

        return {
            promise: promise,
            resolve: resolveFn,
            reject: rejectFn
        };
    }

    beforeEach(() => {
        originalPushReconcileService = globalThis.PushActivationReconcileService;
        originalService = globalThis.ExecutableListsService;
        originalRenderer = globalThis.AAExecutableListRenderer;
        originalDocument = globalThis.document;
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;

        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'unified';
        hooks.__test.resetFeedState();
    });

    afterEach(() => {
        if (originalPushReconcileService === undefined) {
            delete globalThis.PushActivationReconcileService;
        } else {
            globalThis.PushActivationReconcileService = originalPushReconcileService;
        }

        if (originalService === undefined) {
            delete globalThis.ExecutableListsService;
        } else {
            globalThis.ExecutableListsService = originalService;
        }

        if (originalRenderer === undefined) {
            delete globalThis.AAExecutableListRenderer;
        } else {
            globalThis.AAExecutableListRenderer = originalRenderer;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }

        if (originalVisibleFeed === undefined) {
            delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        } else {
            globalThis.AA_EXECUTABLE_VISIBLE_FEED = originalVisibleFeed;
        }

        hooks.__test.resetFeedState();
    });

    it('isEnablePushVisibleForProjection mirrors PHP policy', () => {
        assert.equal(hooks.isEnablePushVisibleForProjection({
            app_subscription_active: false,
            push_ready: false
        }), false);
        assert.equal(hooks.isEnablePushVisibleForProjection({
            app_subscription_active: false,
            push_ready: true
        }), false);
        assert.equal(hooks.isEnablePushVisibleForProjection({
            app_subscription_active: true,
            push_ready: true
        }), false);
        assert.equal(hooks.isEnablePushVisibleForProjection({
            app_subscription_active: true,
            push_ready: false
        }), true);
    });

    it('feed local comienza antes de resolver initializeFeedContext', async () => {
        var events = [];
        var contextGate = deferred();
        var feedGate = deferred();

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                events.push('context-start');
                return contextGate.promise.then(function (context) {
                    events.push('context');
                    return context;
                });
            },
            getFeedContext: function () {
                return {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function (projection) {
                events.push('feed');
                assert.equal(projection.app_subscription_active, false);
                assert.equal(projection.push_ready, false);
                return feedGate.promise;
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details class="aa-executable-list-card"></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        var bootstrap = hooks.initializePushActivationFeed();

        assert.ok(events.indexOf('feed') !== -1);
        assert.ok(events.indexOf('context') === -1);

        feedGate.resolve({ lists: [] });
        contextGate.resolve({
            app_subscription_active: false,
            push_ready: false
        });
        await bootstrap;

        assert.equal(events[0], 'feed');
    });

    it('contexto mas lento y misma visibilidad: una solicitud', async () => {
        var feedLoads = 0;
        var contextGate = deferred();
        var feedGate = deferred();

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return contextGate.promise;
            },
            getFeedContext: function () {
                return {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function () {
                feedLoads += 1;
                return feedGate.promise;
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        var bootstrap = hooks.initializePushActivationFeed();
        feedGate.resolve({ lists: [] });
        contextGate.resolve({
            app_subscription_active: false,
            push_ready: false
        });
        await bootstrap;

        assert.equal(feedLoads, 1);
    });

    it('contexto mas rapido y visibilidad distinta: segunda espera a la inicial', async () => {
        var projections = [];
        var feedGate = deferred();
        var secondStarted = false;
        var resolvedContext = null;

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return Promise.resolve({
                    app_subscription_active: true,
                    push_ready: false
                }).then(function (context) {
                    resolvedContext = context;
                    return context;
                });
            },
            getFeedContext: function () {
                return resolvedContext || {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function (projection) {
                projections.push({
                    app_subscription_active: projection.app_subscription_active === true,
                    push_ready: projection.push_ready === true
                });

                if (projections.length === 1) {
                    return feedGate.promise;
                }

                secondStarted = true;
                return Promise.resolve({ lists: [{ id: '2' }] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        var bootstrap = hooks.initializePushActivationFeed();

        await Promise.resolve();
        assert.equal(projections.length, 1);
        assert.equal(secondStarted, false);

        feedGate.resolve({ lists: [{ id: '1' }] });
        await bootstrap;

        assert.equal(projections.length, 2);
        assert.deepEqual(projections[0], {
            app_subscription_active: false,
            push_ready: false
        });
        assert.deepEqual(projections[1], {
            app_subscription_active: true,
            push_ready: false
        });
        assert.equal(secondStarted, true);
    });

    it('contexto mas lento y visibilidad distinta: exactamente dos solicitudes', async () => {
        var projections = [];
        var contextGate = deferred();
        var feedGate = deferred();
        var resolvedContext = null;

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return contextGate.promise.then(function (context) {
                    resolvedContext = context;
                    return context;
                });
            },
            getFeedContext: function () {
                return resolvedContext || {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function (projection) {
                projections.push({
                    app_subscription_active: projection.app_subscription_active === true,
                    push_ready: projection.push_ready === true
                });

                if (projections.length === 1) {
                    return feedGate.promise;
                }

                return Promise.resolve({ lists: [{ id: 'push' }] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        var bootstrap = hooks.initializePushActivationFeed();
        feedGate.resolve({ lists: [] });
        contextGate.resolve({
            app_subscription_active: true,
            push_ready: false
        });
        await bootstrap;

        assert.equal(projections.length, 2);
        assert.deepEqual(projections[1], {
            app_subscription_active: true,
            push_ready: false
        });
    });

    it('{false,false} -> {true,true}: una sola solicitud porque enable_push permanece oculto', async () => {
        var feedLoads = 0;
        var contextGate = deferred();
        var feedGate = deferred();
        var resolvedContext = null;

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return contextGate.promise.then(function (context) {
                    resolvedContext = context;
                    return context;
                });
            },
            getFeedContext: function () {
                return resolvedContext || {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function () {
                feedLoads += 1;
                return feedGate.promise;
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        var bootstrap = hooks.initializePushActivationFeed();
        feedGate.resolve({ lists: [] });
        contextGate.resolve({
            app_subscription_active: true,
            push_ready: true
        });
        await bootstrap;

        assert.equal(feedLoads, 1);
    });

    it('{false,false} -> {true,false}: exactamente dos solicitudes con contexto definitivo', async () => {
        var projections = [];
        var resolvedContext = null;

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return Promise.resolve({
                    app_subscription_active: true,
                    push_ready: false
                }).then(function (context) {
                    resolvedContext = context;
                    return context;
                });
            },
            getFeedContext: function () {
                return resolvedContext || {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function (projection) {
                projections.push({
                    app_subscription_active: projection.app_subscription_active === true,
                    push_ready: projection.push_ready === true
                });
                return Promise.resolve({ lists: [] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        await hooks.initializePushActivationFeed();

        assert.equal(projections.length, 2);
        assert.deepEqual(projections[0], {
            app_subscription_active: false,
            push_ready: false
        });
        assert.deepEqual(projections[1], {
            app_subscription_active: true,
            push_ready: false
        });
    });

    it('rechazo del contexto: una solicitud, listas conservadas y sin error visual', async () => {
        var feedLoads = 0;
        var root = makeTrackableRoot('local-lists', 1);
        var loadingEl = makeLoadingEl(false);
        var errorEl = {
            textContent: '',
            classList: {
                classes: ['hidden'],
                add: function (name) {
                    if (this.classes.indexOf(name) === -1) {
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

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return Promise.reject(new Error('account down'));
            },
            getFeedContext: function () {
                return {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function () {
                feedLoads += 1;
                return Promise.resolve({ lists: [{ id: '1' }] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details class="kept"></details>';
            }
        };
        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executable-lists-active-root') {
                    return root;
                }
                if (id === 'aa-executable-lists-active-loading') {
                    return loadingEl;
                }
                if (id === 'aa-executable-lists-active-error') {
                    return errorEl;
                }
                return null;
            }
        };

        await hooks.initializePushActivationFeed();

        assert.equal(feedLoads, 1);
        assert.equal(errorEl.textContent, '');
        assert.ok(errorEl.classList.classes.indexOf('hidden') !== -1);
        assert.match(root.innerHTML, /kept|details/);
    });

    it('servicio de contexto ausente: una solicitud', async () => {
        var feedLoads = 0;

        delete globalThis.PushActivationReconcileService;
        globalThis.ExecutableListsService = {
            getFeed: function (projection) {
                feedLoads += 1;
                assert.equal(projection.app_subscription_active, false);
                assert.equal(projection.push_ready, false);
                return Promise.resolve({ lists: [] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        await hooks.initializePushActivationFeed();

        assert.equal(feedLoads, 1);
    });

    it('actualizacion condicional silenciosa: root preservado y loader no reaparece', async () => {
        var loadingEl = makeLoadingEl(false);
        var root = makeTrackableRoot('first-paint', 1);
        var clearedDuringSecond = false;
        var loaderShownDuringSecond = false;
        var feedCount = 0;
        var feedGate = deferred();
        var resolvedContext = null;

        Object.defineProperty(root, 'innerHTML', {
            configurable: true,
            get: function () {
                return this._inner;
            },
            set: function (value) {
                if (feedCount >= 1 && value === '') {
                    clearedDuringSecond = true;
                }
                this._inner = value;
                this.childElementCount = value ? 1 : 0;
            }
        });

        var originalAdd = loadingEl.classList.add.bind(loadingEl.classList);
        loadingEl.classList.remove('hidden');
        loadingEl.classList.add = function (name) {
            originalAdd(name);
        };
        loadingEl.classList.remove = function (name) {
            var before = this.classes.slice();
            this.classes = this.classes.filter(function (item) {
                return item !== name;
            });
            if (name === 'hidden' && feedCount >= 1 && before.indexOf('hidden') !== -1) {
                loaderShownDuringSecond = true;
            }
        };

        globalThis.PushActivationReconcileService = {
            initializeFeedContext: function () {
                return Promise.resolve({
                    app_subscription_active: true,
                    push_ready: false
                }).then(function (context) {
                    resolvedContext = context;
                    return context;
                });
            },
            getFeedContext: function () {
                return resolvedContext || {
                    app_subscription_active: false,
                    push_ready: false
                };
            }
        };
        globalThis.ExecutableListsService = {
            getFeed: function () {
                feedCount += 1;
                if (feedCount === 1) {
                    return feedGate.promise;
                }
                return Promise.resolve({ lists: [{ id: 'with-push' }] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details class="aa-executable-list-card"></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(root, loadingEl);

        var bootstrap = hooks.initializePushActivationFeed();
        feedGate.resolve({ lists: [{ id: 'local' }] });
        await bootstrap;

        assert.equal(feedCount, 2);
        assert.equal(clearedDuringSecond, false);
        assert.equal(loaderShownDuringSecond, false);
        assert.ok(loadingEl.classList.contains('hidden'));
    });

    it('forceFresh dispara una segunda peticion real del feed', async () => {
        var feedLoads = 0;

        globalThis.ExecutableListsService = {
            getFeed: function () {
                feedLoads += 1;
                return Promise.resolve({ lists: [] });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function () {
                return '<details class="aa-executable-list-card"></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('', 0));

        await hooks.loadUnifiedFeed();
        await hooks.loadUnifiedFeed({ forceFresh: true });

        assert.equal(feedLoads, 2);
    });

    it('respuesta vieja no sobrescribe una carga mas nueva', async () => {
        var resolveSlow;
        var resolveFast;
        var rendered = [];

        globalThis.ExecutableListsService = {
            getFeed: function () {
                if (!resolveSlow) {
                    return new Promise(function (resolve) {
                        resolveSlow = resolve;
                    });
                }

                return new Promise(function (resolve) {
                    resolveFast = resolve;
                });
            }
        };
        globalThis.AAExecutableListRenderer = {
            renderFeed: function (lists) {
                rendered.push(lists && lists[0] && lists[0].label);
                return '<details class="aa-executable-list-card"></details>';
            }
        };
        globalThis.document = makeUnifiedDocumentMock(makeTrackableRoot('stale', 1));

        var first = hooks.loadUnifiedFeed();
        var second = hooks.loadUnifiedFeed({ forceFresh: true });

        resolveFast({
            lists: [{ label: 'fresh' }]
        });
        await second;

        resolveSlow({
            lists: [{ label: 'stale' }]
        });
        await first;

        assert.deepEqual(rendered, ['fresh']);
        assert.equal(hooks.__test.getRequestGeneration(), 2);
    });

    it('index.php muestra loader unified sin hidden y no encola shadow', () => {
        const fs = require('node:fs');
        const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /id="aa-executable-lists-active"/);
        assert.doesNotMatch(indexSrc, /id="aa-executable-lists-active"\s+class="space-y-4"/);
        assert.match(indexSrc, /id="aa-executable-lists-active-loading"\s+class="text-sm text-gray-500"/);
        assert.match(indexSrc, /id="aa-tasks-board-root"\s+class="hidden"/);
        assert.doesNotMatch(indexSrc, /executable-lists-shadow-module\.js/);
    });
});
