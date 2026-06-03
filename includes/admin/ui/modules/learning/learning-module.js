/**
 * Learning Module - Guías / Aprendizaje
 *
 * Carga, renderiza y acciones sobre recomendaciones vía LearningService.
 */

(function () {
    'use strict';

    var isActionPending = false;
    var FADE_MS = 150;
    var lastRecommendationsPayload = null;
    var isAvailabilityRerenderBound = false;

    function getRenderer() {
        return window.AALearningRecommendationRenderer || null;
    }

    /**
     * @param {HTMLElement|null} el
     * @param {number} opacity
     */
    function setRootFade(el, opacity) {
        if (!el) {
            return;
        }

        el.style.transition = 'opacity ' + FADE_MS + 'ms ease';
        el.style.opacity = String(opacity);
    }

    /**
     * @param {HTMLElement} listEl
     * @param {Array} items
     */
    function renderList(listEl, items) {
        var renderer = getRenderer();

        if (!listEl) {
            return;
        }

        if (!renderer || typeof renderer.renderRecommendationCard !== 'function') {
            console.warn('[Learning Module] AALearningRecommendationRenderer not available');
            listEl.innerHTML = '';
            return;
        }

        if (!items || items.length === 0) {
            listEl.innerHTML = '';
            return;
        }

        listEl.innerHTML = items.map(renderer.renderRecommendationCard).join('');
    }

    function setVisible(el, visible) {
        if (!el) {
            return;
        }

        if (visible) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }

    function setCardsDisabled(disabled) {
        var buttons = document.querySelectorAll('[data-learning-action]');

        buttons.forEach(function (button) {
            button.disabled = disabled;

            if (disabled) {
                button.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    }

    function showActionError(message) {
        var errorEl = document.getElementById('aa-learning-error');
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo completar la acción.';
        }
    }

    /**
     * @param {{list_1?:Array,list_2?:Array,all_visible?:Array}} data
     */
    function renderRecommendationsPayload(data) {
        var emptyEl = document.getElementById('aa-learning-empty');
        var primaryWrap = document.getElementById('aa-learning-list-primary-wrap');
        var secondaryWrap = document.getElementById('aa-learning-list-secondary-wrap');
        var primaryList = document.getElementById('aa-learning-list-primary');
        var secondaryList = document.getElementById('aa-learning-list-secondary');

        var renderer = getRenderer();
        var filterFn = renderer && typeof renderer.filterRecommendationsForRender === 'function'
            ? renderer.filterRecommendationsForRender.bind(renderer)
            : function (items) { return items || []; };

        var list1 = filterFn(data.list_1 || []);
        var list2 = filterFn(data.list_2 || []);
        var hasAny = list1.length > 0 || list2.length > 0;

        setVisible(emptyEl, !hasAny);
        setVisible(primaryWrap, list1.length > 0);
        setVisible(secondaryWrap, list2.length > 0);

        renderList(primaryList, list1);
        renderList(secondaryList, list2);
    }

    /**
     * @param {string} recommendationKey
     * @returns {object|null}
     */
    function findRecommendationItem(recommendationKey) {
        var payload = lastRecommendationsPayload || {};
        var items = []
            .concat(payload.all_visible || [])
            .concat(payload.list_1 || [])
            .concat(payload.list_2 || []);
        var found = null;

        items.some(function (item) {
            if (item && item.key === recommendationKey) {
                found = item;
                return true;
            }

            return false;
        });

        return found;
    }

    /**
     * @param {{silent?:boolean}} [options]
     * @returns {Promise<void>}
     */
    function loadRecommendations(options) {
        var opts = options || {};
        var silent = opts.silent === true;

        var root = document.getElementById('aa-learning-recommendations-root');
        var loadingEl = document.getElementById('aa-learning-loading');
        var errorEl = document.getElementById('aa-learning-error');
        var emptyEl = document.getElementById('aa-learning-empty');

        if (!root) {
            return Promise.resolve();
        }

        if (!window.LearningService || typeof window.LearningService.getRecommendations !== 'function') {
            setVisible(loadingEl, false);
            showActionError('No se pudo inicializar el servicio de recomendaciones.');
            return Promise.resolve();
        }

        if (silent) {
            setRootFade(root, 0.45);
        } else {
            setVisible(loadingEl, true);
        }

        setVisible(errorEl, false);

        return window.LearningService.getRecommendations()
            .then(function (data) {
                setVisible(loadingEl, false);

                lastRecommendationsPayload = data;
                renderRecommendationsPayload(data);

                if (silent) {
                    window.requestAnimationFrame(function () {
                        setRootFade(root, 1);
                    });
                }
            })
            .catch(function (err) {
                setVisible(loadingEl, false);
                renderRecommendationsPayload({
                    list_1: [],
                    list_2: [],
                    all_visible: []
                });
                setVisible(emptyEl, false);
                setRootFade(root, 1);
                showActionError((err && err.message) ? err.message : 'No se pudieron cargar las recomendaciones.');
                console.error('[Learning Module]', err);
            });
    }

    /**
     * @param {string} recommendationKey
     */
    function runPrimaryHandler(recommendationKey) {
        var registry = window.LearningActionHandlers;
        var item = findRecommendationItem(recommendationKey);
        var action = item && item.action;

        if (
            isActionPending
            || !recommendationKey
            || !item
            || !action
            || action.type !== 'handler'
            || !registry
            || typeof registry.isAvailable !== 'function'
            || typeof registry.run !== 'function'
            || !registry.isAvailable(action, item)
        ) {
            return;
        }

        isActionPending = true;
        setCardsDisabled(true);

        registry.run(action, item, {
            key: recommendationKey,
            item: item,
            reload: function () {
                return loadRecommendations({ silent: true });
            },
            showError: showActionError
        })
            .then(function (result) {
                if (result && result.reload) {
                    return loadRecommendations({ silent: true });
                }

                return null;
            })
            .catch(function (err) {
                showActionError((err && err.message) ? err.message : 'No se pudo completar la acción.');
                console.error('[Learning Module] handler action failed:', err);
            })
            .finally(function () {
                isActionPending = false;
                setCardsDisabled(false);
            });
    }

    /**
     * @param {string} action
     * @param {string} recommendationKey
     */
    function runLearningAction(action, recommendationKey) {
        if (isActionPending || !recommendationKey) {
            return;
        }

        var service = window.LearningService;
        var fn = null;

        if (action === 'defer' && typeof service.ignoreRecommendation === 'function') {
            fn = service.ignoreRecommendation.bind(service);
        } else if (action === 'dismiss' && typeof service.dismissRecommendation === 'function') {
            fn = service.dismissRecommendation.bind(service);
        }

        if (!fn) {
            return;
        }

        isActionPending = true;
        setCardsDisabled(true);

        fn(recommendationKey)
            .then(function () {
                return loadRecommendations({ silent: true });
            })
            .catch(function (err) {
                showActionError((err && err.message) ? err.message : 'No se pudo completar la acción.');
                console.error('[Learning Module] action failed:', err);
            })
            .finally(function () {
                isActionPending = false;
                setCardsDisabled(false);
            });
    }

    function bindActionDelegation() {
        var root = document.getElementById('aa-learning-recommendations-root');

        if (!root || root.dataset.learningActionsBound === '1') {
            return;
        }

        root.dataset.learningActionsBound = '1';

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-learning-action]');

            if (!button || button.disabled) {
                return;
            }

            var action = button.getAttribute('data-learning-action');
            var key = button.getAttribute('data-recommendation-key');

            if (!action || !key) {
                return;
            }

            event.preventDefault();

            if (action === 'primary-handler') {
                runPrimaryHandler(key);
                return;
            }

            runLearningAction(action, key);
        });
    }

    function bindAvailabilityRerender() {
        var registry = window.LearningActionHandlers;

        if (
            isAvailabilityRerenderBound
            || !registry
            || typeof registry.onAvailabilityChange !== 'function'
        ) {
            return;
        }

        isAvailabilityRerenderBound = true;

        registry.onAvailabilityChange(function () {
            if (lastRecommendationsPayload) {
                renderRecommendationsPayload(lastRecommendationsPayload);
            }
        });
    }

    function initLearningModule() {
        bindActionDelegation();
        bindAvailabilityRerender();
        loadRecommendations();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLearningModule);
    } else {
        initLearningModule();
    }
})();
