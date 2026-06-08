'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/learning-module.js');
const hooks = require(modulePath);

function makeEl(options) {
    var opts = options || {};

    return {
        classList: {
            classes: opts.hidden ? ['hidden'] : [],
            add: function (cls) {
                if (this.classes.indexOf(cls) === -1) {
                    this.classes.push(cls);
                }
            },
            remove: function (cls) {
                this.classes = this.classes.filter(function (item) {
                    return item !== cls;
                });
            }
        },
        textContent: opts.textContent || '',
        innerHTML: opts.innerHTML || '',
        dataset: opts.dataset || {},
        style: {},
        addEventListener: function () {},
        querySelectorAll: function () {
            return [];
        }
    };
}

function buildLearningDom() {
    var loading = makeEl({ hidden: true });
    var error = makeEl({ hidden: true });
    var empty = makeEl({ hidden: true });
    var primaryList = makeEl();
    var secondaryList = makeEl();
    var root = makeEl({ dataset: {} });

    return {
        'aa-learning-recommendations-root': root,
        'aa-learning-loading': loading,
        'aa-learning-error': error,
        'aa-learning-empty': empty,
        'aa-learning-list-primary-wrap': makeEl({ hidden: true }),
        'aa-learning-list-secondary-wrap': makeEl({ hidden: true }),
        'aa-learning-list-primary': primaryList,
        'aa-learning-list-secondary': secondaryList
    };
}

describe('learning-module MC13J-2A feed mode', () => {
    let originalVisibleFeed;
    let originalData;
    let originalSessionStorage;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalData = globalThis.AA_EXECUTABLE_LISTS_DATA;
        originalSessionStorage = globalThis.sessionStorage;
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
    });

    it('default sin flags resuelve unified y no es legacy', () => {
        delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        assert.equal(hooks.resolveEffectiveFeedMode(), 'unified');
        assert.equal(hooks.isLegacyListsViewEnabled(), false);
    });

    it('off normaliza a legacy', () => {
        assert.equal(hooks.normalizeVisibleFeedFlag('off'), 'legacy');
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'off';

        assert.equal(hooks.resolveEffectiveFeedMode(), 'legacy');
        assert.equal(hooks.isLegacyListsViewEnabled(), true);
    });

    it('AA_EXECUTABLE_VISIBLE_FEED=legacy activa legacy', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'legacy';

        assert.equal(hooks.isLegacyListsViewEnabled(), true);
    });

    it('AA_EXECUTABLE_VISIBLE_FEED=unified no activa legacy', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'unified';

        assert.equal(hooks.isLegacyListsViewEnabled(), false);
    });

    it('AA_EXECUTABLE_LISTS_DATA.visibleFeed=unified no activa legacy', () => {
        globalThis.AA_EXECUTABLE_LISTS_DATA = {
            visibleFeed: 'unified'
        };

        assert.equal(hooks.isLegacyListsViewEnabled(), false);
    });

    it('sessionStorage legacy gana sin window ni cfg', () => {
        globalThis.sessionStorage = {
            getItem: function (key) {
                if (key === 'AA_EXECUTABLE_VISIBLE_FEED') {
                    return 'legacy';
                }

                return null;
            }
        };

        assert.equal(hooks.isLegacyListsViewEnabled(), true);
    });
});

describe('learning-module MC13J-2A init short-circuit', () => {
    let originalVisibleFeed;
    let originalData;
    let originalSessionStorage;
    let originalLearningService;
    let originalRenderer;
    let originalHandlers;
    let getRecommendationsCalls;

    beforeEach(() => {
        originalVisibleFeed = globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        originalData = globalThis.AA_EXECUTABLE_LISTS_DATA;
        originalSessionStorage = globalThis.sessionStorage;
        originalLearningService = globalThis.LearningService;
        originalRenderer = globalThis.AALearningRecommendationRenderer;
        originalHandlers = globalThis.LearningActionHandlers;
        getRecommendationsCalls = 0;

        globalThis.LearningService = {
            getRecommendations: function () {
                getRecommendationsCalls += 1;
                return Promise.resolve({
                    list_1: [],
                    list_2: [],
                    all_visible: []
                });
            }
        };

        globalThis.AALearningRecommendationRenderer = {
            renderRecommendationCard: function () {
                return '<div>card</div>';
            },
            filterRecommendationsForRender: function (items) {
                return items || [];
            }
        };

        globalThis.LearningActionHandlers = {
            onAvailabilityChange: function () {}
        };

        var dom = buildLearningDom();

        globalThis.document = {
            getElementById: function (id) {
                return dom[id] || null;
            },
            querySelectorAll: function () {
                return [];
            }
        };
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

        if (originalLearningService === undefined) {
            delete globalThis.LearningService;
        } else {
            globalThis.LearningService = originalLearningService;
        }

        if (originalRenderer === undefined) {
            delete globalThis.AALearningRecommendationRenderer;
        } else {
            globalThis.AALearningRecommendationRenderer = originalRenderer;
        }

        if (originalHandlers === undefined) {
            delete globalThis.LearningActionHandlers;
        } else {
            globalThis.LearningActionHandlers = originalHandlers;
        }

        delete globalThis.document;
    });

    it('initLearningModule en unified no llama getRecommendations', async () => {
        delete globalThis.AA_EXECUTABLE_VISIBLE_FEED;
        delete globalThis.AA_EXECUTABLE_LISTS_DATA;
        delete globalThis.sessionStorage;

        hooks.initLearningModule();

        assert.equal(getRecommendationsCalls, 0);
    });

    it('initLearningModule en legacy llama getRecommendations', async () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'legacy';

        hooks.initLearningModule();

        assert.equal(getRecommendationsCalls, 1);
    });

    it('initLearningModule con off ejecuta flujo legacy', () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'off';

        hooks.initLearningModule();

        assert.equal(getRecommendationsCalls, 1);
    });

    it('loadRecommendations directo sigue disponible para legacy', async () => {
        globalThis.AA_EXECUTABLE_VISIBLE_FEED = 'legacy';

        await hooks.loadRecommendations();

        assert.equal(getRecommendationsCalls, 1);
    });
});

describe('learning-module MC13J-2A wiring', () => {
    it('initLearningModule short-circuits cuando no es legacy', () => {
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /function initLearningModule\(\)/);
        assert.match(moduleSrc, /if \(!isLegacyListsViewEnabled\(\)\)/);
        assert.match(moduleSrc, /function resolveEffectiveFeedMode\(\)/);
    });
});
