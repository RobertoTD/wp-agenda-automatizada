/**
 * Tutorial engine — reusable contextual coach mark engine (MC3B).
 *
 * Isolated engine only: it does not register real onboarding steps, call AJAX,
 * navigate, open modals, or integrate with the activation coordinator.
 */
(function () {
    'use strict';

    var SESSION_VERSION = 1;
    var SESSION_KEY_PREFIX = 'aa_tutorial_session_v1';
    var ROOT_ID = 'aa-tutorial-root';
    var DEFAULT_TARGET_TIMEOUT_MS = 3000;
    var DEFAULT_TARGET_INTERVAL_MS = 100;
    var DEFAULT_MARGIN = 12;
    var DEFAULT_GAP = 12;
    var VALID_ADVANCE_MODES = ['button', 'dismiss', 'target_click', 'event'];
    var VALID_STATUSES = ['active', 'paused', 'paused_missing_target'];
    var VALID_PLACEMENTS = ['center', 'top', 'bottom', 'left', 'right'];

    var runtime = {
        config: null,
        currentStepId: null,
        currentStep: null,
        root: null,
        cleanupCallbacks: [],
        status: 'idle',
        activeRunToken: 0,
        pendingTargetTimer: null,
        advanceInFlight: false
    };

    function getGlobalRoot() {
        if (typeof window !== 'undefined') {
            return window;
        }

        if (typeof globalThis !== 'undefined') {
            return globalThis;
        }

        return {};
    }

    function warn(message) {
        var root = getGlobalRoot();
        if (root.console && typeof root.console.warn === 'function') {
            root.console.warn('[AATutorial] ' + message);
        }
    }

    function nowMs() {
        return Date.now ? Date.now() : new Date().getTime();
    }

    function isPlainObject(value) {
        return !!value && typeof value === 'object' && !Array.isArray(value);
    }

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function normalizePlacement(value) {
        var placement = normalizeString(value);
        return VALID_PLACEMENTS.indexOf(placement) !== -1 ? placement : 'bottom';
    }

    function normalizeAdvance(step) {
        var advance = isPlainObject(step.advance) ? step.advance : {};
        var mode = normalizeString(advance.mode || 'button');

        if (VALID_ADVANCE_MODES.indexOf(mode) === -1) {
            mode = 'button';
        }

        var navigation = normalizeString(advance.navigation);
        if (navigation !== 'follow_target') {
            navigation = 'none';
        }

        return {
            mode: mode,
            label: normalizeString(advance.label) || 'Continuar',
            eventName: normalizeString(advance.eventName),
            eventDetail: isPlainObject(advance.eventDetail) ? advance.eventDetail : null,
            navigation: navigation
        };
    }

    function normalizeStep(step) {
        if (!isPlainObject(step)) {
            return null;
        }

        var id = normalizeString(step.id);
        if (!id) {
            return null;
        }

        return {
            id: id,
            title: normalizeString(step.title),
            text: normalizeString(step.text),
            target: normalizeString(step.target) || null,
            placement: normalizePlacement(step.placement || (step.target ? 'bottom' : 'center')),
            advance: normalizeAdvance(step),
            beforeAction: normalizeString(step.beforeAction) || null,
            beforeAdvanceAction: normalizeString(step.beforeAdvanceAction) || null,
            afterAction: normalizeString(step.afterAction) || null,
            nextStepId: normalizeString(step.nextStepId) || null,
            waitFor: isPlainObject(step.waitFor) ? step.waitFor : null
        };
    }

    function normalizeConfig(config) {
        if (!isPlainObject(config)) {
            return null;
        }

        var flowId = normalizeString(config.flowId);
        var rawSteps = Array.isArray(config.steps) ? config.steps : [];
        var steps = [];
        var stepById = {};

        rawSteps.forEach(function (rawStep) {
            var step = normalizeStep(rawStep);
            if (!step || stepById[step.id]) {
                return;
            }

            stepById[step.id] = step;
            steps.push(step);
        });

        if (!flowId || steps.length === 0) {
            return null;
        }

        var initialStepId = normalizeString(config.initialStepId) || steps[0].id;
        if (!stepById[initialStepId]) {
            initialStepId = steps[0].id;
        }

        return {
            flowId: flowId,
            initialStepId: initialStepId,
            steps: steps,
            stepById: stepById,
            onGlobalClose: typeof config.onGlobalClose === 'function' ? config.onGlobalClose : null
        };
    }

    function getStepIds(config) {
        if (!config || !Array.isArray(config.steps)) {
            return [];
        }

        return config.steps.map(function (step) {
            return step.id;
        });
    }

    function getNextStepId(config, step) {
        if (!config || !step) {
            return null;
        }

        if (step.nextStepId && config.stepById[step.nextStepId]) {
            return step.nextStepId;
        }

        var index = config.steps.findIndex(function (candidate) {
            return candidate.id === step.id;
        });

        if (index === -1 || index + 1 >= config.steps.length) {
            return null;
        }

        return config.steps[index + 1].id;
    }

    function resolveBlogId(context) {
        var source = context || getGlobalRoot().AA_ADMIN_CONTEXT || null;
        var blogId = source && source.blogId;

        if (typeof blogId === 'number' && isFinite(blogId) && blogId > 0) {
            return String(Math.floor(blogId));
        }

        if (typeof blogId === 'string' && blogId.trim() !== '') {
            return blogId.trim();
        }

        return null;
    }

    function buildSessionKey(blogId, flowId) {
        var normalizedBlogId = normalizeString(blogId);
        var normalizedFlowId = normalizeString(flowId);

        if (!normalizedBlogId || !normalizedFlowId) {
            return null;
        }

        return SESSION_KEY_PREFIX + ':' + normalizedBlogId + ':' + normalizedFlowId;
    }

    function getStorage() {
        var root = getGlobalRoot();
        return root.sessionStorage || null;
    }

    function sanitizeSession(raw, flowId, validStepIds) {
        var parsed = raw;
        if (typeof raw === 'string') {
            try {
                parsed = JSON.parse(raw);
            } catch (err) {
                return null;
            }
        }

        if (!isPlainObject(parsed)) {
            return null;
        }

        if ((parsed.version || 0) !== SESSION_VERSION) {
            return null;
        }

        if (parsed.flowId !== flowId) {
            return null;
        }

        if (validStepIds.indexOf(parsed.currentStepId) === -1) {
            return null;
        }

        if (VALID_STATUSES.indexOf(parsed.status) === -1) {
            return null;
        }

        var updatedAt = Number(parsed.updatedAt || 0);
        if (!isFinite(updatedAt) || updatedAt < 0) {
            return null;
        }

        return {
            version: SESSION_VERSION,
            flowId: flowId,
            currentStepId: parsed.currentStepId,
            status: parsed.status,
            updatedAt: updatedAt
        };
    }

    function readSession(blogId, flowId, config) {
        var key = buildSessionKey(blogId, flowId);
        var storage = getStorage();

        if (!key) {
            warn('AA_ADMIN_CONTEXT.blogId no disponible; sessionStorage deshabilitado para este flujo.');
            return null;
        }

        if (!storage || typeof storage.getItem !== 'function') {
            return null;
        }

        var raw;
        try {
            raw = storage.getItem(key);
        } catch (err) {
            return null;
        }

        if (!raw) {
            return null;
        }

        var session = sanitizeSession(raw, flowId, getStepIds(config));
        if (!session) {
            try {
                storage.removeItem(key);
            } catch (err2) {
                // Ignore storage cleanup failures.
            }
        }

        return session;
    }

    function writeSession(blogId, flowId, payload) {
        var key = buildSessionKey(blogId, flowId);
        var storage = getStorage();

        if (!key) {
            warn('AA_ADMIN_CONTEXT.blogId no disponible; no se persistira sessionStorage compartido.');
            return false;
        }

        if (!storage || typeof storage.setItem !== 'function') {
            return false;
        }

        try {
            storage.setItem(key, JSON.stringify(payload));
            return true;
        } catch (err) {
            return false;
        }
    }

    function clearSession(blogId, flowId) {
        var key = buildSessionKey(blogId, flowId);
        var storage = getStorage();

        if (!key || !storage || typeof storage.removeItem !== 'function') {
            return false;
        }

        try {
            storage.removeItem(key);
            return true;
        } catch (err) {
            return false;
        }
    }

    var ActionRegistry = (function () {
        var actions = {};

        function register(name, handler) {
            var key = normalizeString(name);
            if (!key || typeof handler !== 'function') {
                return false;
            }

            actions[key] = handler;
            return true;
        }

        function has(name) {
            return !!actions[normalizeString(name)];
        }

        function run(name, ctx) {
            var key = normalizeString(name);
            if (!key) {
                return undefined;
            }

            if (!actions[key]) {
                warn('Accion no registrada: ' + key);
                return undefined;
            }

            return actions[key](ctx || {});
        }

        function resetForTests() {
            actions = {};
            registerDefaults();
        }

        function registerDefaults() {
            register('noop', function () {});
        }

        registerDefaults();

        return {
            register: register,
            run: run,
            has: has,
            resetForTests: resetForTests
        };
    })();

    var SessionAdapter = {
        buildKey: buildSessionKey,
        read: readSession,
        write: writeSession,
        clear: clearSession,
        sanitize: sanitizeSession,
        resolveBlogId: resolveBlogId
    };

    function dispatchTutorialEvent(type, detail) {
        var root = getGlobalRoot();
        if (!root.document || typeof root.document.dispatchEvent !== 'function' || typeof root.CustomEvent !== 'function') {
            return;
        }

        root.document.dispatchEvent(new root.CustomEvent(type, {
            detail: detail || {}
        }));
    }

    function addCleanup(callback) {
        runtime.cleanupCallbacks.push(callback);
    }

    function addEvent(target, type, handler, options) {
        if (!target || typeof target.addEventListener !== 'function') {
            return;
        }

        target.addEventListener(type, handler, options);
        addCleanup(function () {
            if (typeof target.removeEventListener === 'function') {
                target.removeEventListener(type, handler, options);
            }
        });
    }

    function clearPendingTargetTimer() {
        if (runtime.pendingTargetTimer !== null) {
            clearTimeout(runtime.pendingTargetTimer);
            runtime.pendingTargetTimer = null;
        }
    }

    function cleanupCurrentStep() {
        clearPendingTargetTimer();

        while (runtime.cleanupCallbacks.length) {
            var callback = runtime.cleanupCallbacks.pop();
            try {
                callback();
            } catch (err) {
                // Cleanup must be best-effort.
            }
        }

        if (runtime.root && runtime.root.parentNode) {
            runtime.root.parentNode.removeChild(runtime.root);
        }

        runtime.root = null;
        runtime.currentStep = null;
    }

    function ensureDocument() {
        var root = getGlobalRoot();
        return root.document || null;
    }

    function createElement(tag, className) {
        var doc = ensureDocument();
        if (!doc || typeof doc.createElement !== 'function') {
            return null;
        }

        var element = doc.createElement(tag);
        if (className) {
            element.className = className;
        }

        return element;
    }

    function setText(element, value) {
        if (element) {
            element.textContent = value || '';
        }
    }

    function resolveTarget(step) {
        var doc = ensureDocument();
        if (!doc || !step || !step.target || typeof doc.querySelector !== 'function') {
            return null;
        }

        try {
            return doc.querySelector(step.target);
        } catch (err) {
            warn('Selector invalido: ' + step.target);
            return null;
        }
    }

    function getViewport() {
        var root = getGlobalRoot();
        var doc = ensureDocument();
        var docEl = doc && doc.documentElement ? doc.documentElement : {};

        return {
            width: root.innerWidth || docEl.clientWidth || 0,
            height: root.innerHeight || docEl.clientHeight || 0
        };
    }

    function clamp(value, min, max) {
        if (max < min) {
            return min;
        }

        return Math.max(min, Math.min(value, max));
    }

    function resolveTutorialPlacement(input) {
        var placement = normalizePlacement(input.placement);
        var card = input.cardRect || { width: 320, height: 160 };
        var viewport = input.viewport || { width: 0, height: 0 };
        var target = input.targetRect || null;
        var margin = typeof input.margin === 'number' ? input.margin : DEFAULT_MARGIN;
        var gap = typeof input.gap === 'number' ? input.gap : DEFAULT_GAP;
        var top;
        var left;

        if (!target || placement === 'center') {
            top = (viewport.height - card.height) / 2;
            left = (viewport.width - card.width) / 2;
        } else if (placement === 'top') {
            top = target.top - card.height - gap;
            left = target.left + (target.width - card.width) / 2;
        } else if (placement === 'left') {
            top = target.top + (target.height - card.height) / 2;
            left = target.left - card.width - gap;
        } else if (placement === 'right') {
            top = target.top + (target.height - card.height) / 2;
            left = target.right + gap;
        } else {
            top = target.bottom + gap;
            left = target.left + (target.width - card.width) / 2;
        }

        return {
            top: clamp(Math.round(top), margin, viewport.height - card.height - margin),
            left: clamp(Math.round(left), margin, viewport.width - card.width - margin)
        };
    }

    function positionElements(root, card, highlight, target, step) {
        if (!card) {
            return;
        }

        var targetRect = target && typeof target.getBoundingClientRect === 'function'
            ? target.getBoundingClientRect()
            : null;

        if (highlight) {
            if (targetRect) {
                highlight.style.display = 'block';
                highlight.style.top = Math.round(targetRect.top - 4) + 'px';
                highlight.style.left = Math.round(targetRect.left - 4) + 'px';
                highlight.style.width = Math.round(targetRect.width + 8) + 'px';
                highlight.style.height = Math.round(targetRect.height + 8) + 'px';
            } else {
                highlight.style.display = 'none';
            }
        }

        var cardRect = typeof card.getBoundingClientRect === 'function'
            ? card.getBoundingClientRect()
            : { width: 320, height: 160 };
        var placement = resolveTutorialPlacement({
            placement: step.placement,
            targetRect: targetRect,
            cardRect: {
                width: cardRect.width || 320,
                height: cardRect.height || 160
            },
            viewport: getViewport()
        });

        card.style.top = placement.top + 'px';
        card.style.left = placement.left + 'px';

        if (root) {
            root.setAttribute('data-aa-tutorial-step', step.id);
        }
    }

    function persistCurrentStep(stepId, status) {
        if (!runtime.config) {
            return false;
        }

        var blogId = resolveBlogId();
        var payload = {
            version: SESSION_VERSION,
            flowId: runtime.config.flowId,
            currentStepId: stepId,
            status: status || 'active',
            updatedAt: nowMs()
        };

        return writeSession(blogId, runtime.config.flowId, payload);
    }

    function actionContext(step, extras) {
        var ctx = {
            tutorial: Tutor,
            config: runtime.config,
            step: step || runtime.currentStep,
            state: Tutor.getState()
        };

        if (isPlainObject(extras)) {
            Object.keys(extras).forEach(function (key) {
                ctx[key] = extras[key];
            });
        }

        return ctx;
    }

    /**
     * @param {object} step
     * @param {object} ctx
     * @returns {Promise<void>}
     */
    function runAdvanceGate(step, ctx) {
        var actionName = step.beforeAdvanceAction || step.afterAction;

        if (!actionName) {
            return Promise.resolve();
        }

        return Promise.resolve(ActionRegistry.run(actionName, ctx)).then(function (result) {
            if (result === false) {
                var blocked = new Error('Advance blocked by action.');
                blocked.code = 'advance_blocked';
                throw blocked;
            }
        });
    }

    /**
     * @param {object} step
     * @param {object} ctx
     * @returns {boolean}
     */
    function finishAdvance(step, ctx) {
        if (ctx && ctx.trigger === 'target_click' && step.advance.navigation === 'follow_target') {
            var target = ctx.target;
            var href = target && typeof target.href === 'string' ? target.href : '';

            if (!href && target && typeof target.getAttribute === 'function') {
                href = normalizeString(target.getAttribute('href'));
            }

            if (href) {
                getGlobalRoot().location.assign(href);
                return true;
            }
        }

        return Tutor.continueAdvance();
    }

    /**
     * @param {object} [ctx]
     * @returns {Promise<boolean>}
     */
    function tryAdvance(ctx) {
        if (runtime.advanceInFlight) {
            return Promise.resolve(false);
        }

        if (!runtime.config || !runtime.currentStep) {
            return Promise.resolve(false);
        }

        var step = runtime.currentStep;
        var advanceCtx = actionContext(step, isPlainObject(ctx) ? ctx : {});

        runtime.advanceInFlight = true;

        return runAdvanceGate(step, advanceCtx)
            .then(function () {
                return finishAdvance(step, advanceCtx);
            })
            .catch(function (err) {
                warn('Advance bloqueado: ' + (err && err.message ? err.message : 'unknown'));
                dispatchTutorialEvent('aa:tutorial:advance-blocked', {
                    stepId: step.id,
                    reason: err && err.message ? err.message : 'advance_blocked',
                    code: err && err.code ? err.code : 'advance_blocked'
                });
                return false;
            })
            .finally(function () {
                runtime.advanceInFlight = false;
            });
    }

    function handleGlobalClose() {
        var config = runtime.config;

        if (!config || typeof config.onGlobalClose !== 'function') {
            return;
        }

        try {
            config.onGlobalClose({
                flowId: config.flowId,
                stepId: runtime.currentStepId,
                tutorial: Tutor
            });
        } catch (err) {
            warn('onGlobalClose failed: ' + (err && err.message ? err.message : String(err)));
        }
    }

    function createRoot(step, target) {
        var doc = ensureDocument();
        if (!doc || !doc.body) {
            return null;
        }

        var root = createElement('div', 'aa-tutorial-root');
        var backdrop = createElement('div', 'aa-tutorial-backdrop');
        var highlight = createElement('div', 'aa-tutorial-highlight');
        var card = createElement('div', 'aa-tutorial-card');
        var title = createElement('h3', 'aa-tutorial-title');
        var text = createElement('p', 'aa-tutorial-text');
        var actions = createElement('div', 'aa-tutorial-actions');

        if (!root || !backdrop || !highlight || !card || !title || !text || !actions) {
            return null;
        }

        root.id = ROOT_ID;
        root.setAttribute('data-aa-tutorial', '1');
        setText(title, step.title);
        setText(text, step.text);

        card.appendChild(title);
        if (step.text) {
            card.appendChild(text);
        }

        if (step.advance.mode === 'button') {
            var button = createElement('button', 'aa-tutorial-button');
            if (button) {
                button.type = 'button';
                setText(button, step.advance.label);
                actions.appendChild(button);
                card.appendChild(actions);
                addEvent(button, 'click', function (event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }
                    tryAdvance(actionContext(step, {
                        trigger: 'button',
                        event: event,
                        target: button
                    }));
                });
            }
        }

        if (step.advance.mode === 'dismiss') {
            root.className += ' is-interactive';
            addEvent(backdrop, 'click', function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                if (event && typeof event.stopPropagation === 'function') {
                    event.stopPropagation();
                }
                tryAdvance(actionContext(step, {
                    trigger: 'dismiss',
                    event: event,
                    target: backdrop
                }));
            });
        }

        if (step.advance.mode === 'target_click') {
            root.className += ' is-target-click';
            if (target) {
                addEvent(target, 'click', function (event) {
                    var advanceCtx = actionContext(step, {
                        trigger: 'target_click',
                        event: event,
                        target: target
                    });

                    if (step.advance.navigation === 'follow_target') {
                        if (event && typeof event.preventDefault === 'function') {
                            event.preventDefault();
                        }
                        if (event && typeof event.stopPropagation === 'function') {
                            event.stopPropagation();
                        }
                    }

                    tryAdvance(advanceCtx);
                });
            }
        }

        if (step.advance.mode === 'event' && step.advance.eventName) {
            addEvent(doc, step.advance.eventName, function (event) {
                if (eventMatches(step.advance.eventDetail, event && event.detail)) {
                    tryAdvance(actionContext(step, {
                        trigger: 'event',
                        event: event,
                        target: null
                    }));
                }
            });
        }

        root.appendChild(backdrop);
        root.appendChild(highlight);
        root.appendChild(card);

        if (runtime.config && typeof runtime.config.onGlobalClose === 'function') {
            var closeButton = createElement('button', 'aa-tutorial-global-close');
            if (closeButton) {
                closeButton.type = 'button';
                closeButton.setAttribute('aria-label', 'Cerrar tutorial');
                setText(closeButton, '\u00d7');
                addEvent(closeButton, 'click', function (event) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }
                    if (event && typeof event.stopPropagation === 'function') {
                        event.stopPropagation();
                    }
                    handleGlobalClose();
                });
                root.appendChild(closeButton);
            }
        }

        doc.body.appendChild(root);

        positionElements(root, card, highlight, target, step);

        var reposition = function () {
            positionElements(root, card, highlight, target, step);
        };

        addEvent(getGlobalRoot(), 'resize', reposition);
        addEvent(getGlobalRoot(), 'scroll', reposition, true);

        return root;
    }

    function eventMatches(expected, actual) {
        if (!expected) {
            return true;
        }

        if (!isPlainObject(actual)) {
            return false;
        }

        return Object.keys(expected).every(function (key) {
            return actual[key] === expected[key];
        });
    }

    function waitForTarget(step, runToken, callback) {
        var selector = step.waitFor && normalizeString(step.waitFor.selector)
            ? normalizeString(step.waitFor.selector)
            : step.target;
        var timeoutMs = step.waitFor && typeof step.waitFor.timeoutMs === 'number'
            ? Math.max(0, step.waitFor.timeoutMs)
            : DEFAULT_TARGET_TIMEOUT_MS;
        var intervalMs = step.waitFor && typeof step.waitFor.intervalMs === 'number'
            ? Math.max(10, step.waitFor.intervalMs)
            : DEFAULT_TARGET_INTERVAL_MS;
        var startedAt = nowMs();

        function tick() {
            if (runtime.activeRunToken !== runToken) {
                return;
            }

            var target = resolveTarget({ target: selector });
            if (target) {
                callback(target);
                return;
            }

            if (nowMs() - startedAt >= timeoutMs) {
                Tutor.pause('missing_target', { status: 'paused_missing_target' });
                return;
            }

            runtime.pendingTargetTimer = setTimeout(tick, intervalMs);
        }

        tick();
    }

    var Tutor = {
        start: function (config) {
            var normalized = normalizeConfig(config);
            if (!normalized) {
                warn('Config de tutor invalida.');
                return false;
            }

            this.destroy();
            runtime.config = normalized;
            runtime.status = 'active';

            var blogId = resolveBlogId();
            var session = blogId ? readSession(blogId, normalized.flowId, normalized) : null;
            if (!blogId) {
                warn('AA_ADMIN_CONTEXT.blogId no disponible; el tutor correra sin persistencia de sessionStorage.');
            }

            var stepId = session && session.currentStepId
                ? session.currentStepId
                : normalized.initialStepId;

            return this.show(stepId);
        },

        show: function (stepId) {
            if (!runtime.config || !runtime.config.stepById[stepId]) {
                warn('Paso de tutor no encontrado: ' + stepId);
                return false;
            }

            cleanupCurrentStep();
            runtime.activeRunToken += 1;
            runtime.currentStepId = stepId;
            runtime.currentStep = runtime.config.stepById[stepId];
            runtime.status = 'active';
            persistCurrentStep(stepId, 'active');

            var step = runtime.currentStep;
            if (step.beforeAction) {
                ActionRegistry.run(step.beforeAction, actionContext(step));
            }

            var runToken = runtime.activeRunToken;

            if (step.target) {
                waitForTarget(step, runToken, function (target) {
                    if (runtime.activeRunToken !== runToken) {
                        return;
                    }
                    runtime.root = createRoot(step, target);
                    dispatchTutorialEvent('aa:tutorial:step-shown', { stepId: step.id });
                });
                return true;
            }

            runtime.root = createRoot(step, null);
            dispatchTutorialEvent('aa:tutorial:step-shown', { stepId: step.id });
            return true;
        },

        continueAdvance: function () {
            if (!runtime.config || !runtime.currentStep) {
                return false;
            }

            var step = runtime.currentStep;
            var nextStepId = getNextStepId(runtime.config, step);

            if (!nextStepId) {
                this.complete();
                return true;
            }

            return this.show(nextStepId);
        },

        next: function (ctx) {
            return tryAdvance(isPlainObject(ctx) ? ctx : {});
        },

        tryAdvance: function (ctx) {
            return tryAdvance(isPlainObject(ctx) ? ctx : {});
        },

        pause: function (reason, options) {
            var opts = options || {};
            var status = opts.status || 'paused';
            if (VALID_STATUSES.indexOf(status) === -1) {
                status = 'paused';
            }

            if (runtime.currentStepId) {
                persistCurrentStep(runtime.currentStepId, status);
            }

            cleanupCurrentStep();
            runtime.status = status;
            dispatchTutorialEvent('aa:tutorial:paused', {
                reason: reason || 'paused',
                status: status,
                stepId: runtime.currentStepId
            });
            return true;
        },

        resume: function () {
            if (!runtime.config) {
                return false;
            }

            var blogId = resolveBlogId();
            var session = blogId ? readSession(blogId, runtime.config.flowId, runtime.config) : null;
            if (!session || !session.currentStepId) {
                return this.show(runtime.config.initialStepId);
            }

            return this.show(session.currentStepId);
        },

        complete: function () {
            var config = runtime.config;
            cleanupCurrentStep();

            if (config) {
                clearSession(resolveBlogId(), config.flowId);
            }

            runtime.status = 'completed';
            runtime.currentStepId = null;
            runtime.config = null;
            dispatchTutorialEvent('aa:tutorial:completed', {});
            return true;
        },

        destroy: function () {
            // Runtime cleanup only. Does not complete the flow, clear session, or emit completed.
            cleanupCurrentStep();
            runtime.status = 'idle';
            runtime.currentStepId = null;
            runtime.config = null;
            runtime.activeRunToken += 1;
            return true;
        },

        getState: function () {
            return {
                flowId: runtime.config ? runtime.config.flowId : null,
                currentStepId: runtime.currentStepId,
                status: runtime.status,
                hasRoot: !!runtime.root
            };
        }
    };

    var api = {
        AATutorial: Tutor,
        AATutorialActions: ActionRegistry,
        AATutorialSession: SessionAdapter,
        normalizeConfig: normalizeConfig,
        resolveTutorialPlacement: resolveTutorialPlacement,
        getNextStepId: getNextStepId,
        constants: {
            SESSION_VERSION: SESSION_VERSION,
            SESSION_KEY_PREFIX: SESSION_KEY_PREFIX,
            ROOT_ID: ROOT_ID
        }
    };

    var root = getGlobalRoot();
    if (typeof window !== 'undefined') {
        window.AATutorial = Tutor;
        window.AATutorialActions = ActionRegistry;
        window.AATutorialSession = SessionAdapter;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
