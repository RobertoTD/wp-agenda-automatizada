/**
 * Expediente Registros Canonical Mount (C1c1 + C1c2).
 *
 * Bootstrap: build adapter → init en live oculto → swap tras onInitialLoad →
 * adoptar FAB canónico (C1c2) y navegar a successUrl tras onCreateComplete.
 * Create provisional permanece como fallback hasta readiness (+ successUrl).
 *
 * API: AAAdmin.ExpedienteRegistrosCanonicalMount.mount() / .destroy()
 */
(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var mountState = {
        mounted: false,
        swapped: false,
        fabAdopted: false,
        navigationScheduled: false,
        ssrRoot: null,
        liveRoot: null,
        originalFab: null,
        canonicalFab: null,
        successUrl: ''
    };

    function getConfig() {
        return window.AA_EXPEDIENTE_DETAIL_DATA || null;
    }

    function getAdapterApi() {
        return window.AAAdmin && window.AAAdmin.ExpedienteRegistrosCanonicalAdapter
            ? window.AAAdmin.ExpedienteRegistrosCanonicalAdapter
            : null;
    }

    function getRendererApi() {
        return window.AAAdmin && window.AAAdmin.ExpedienteRegistros
            ? window.AAAdmin.ExpedienteRegistros
            : null;
    }

    function isValidSuccessUrl(value) {
        return typeof value === 'string' && value.trim() !== '';
    }

    function hideLive(liveRoot) {
        if (!liveRoot) {
            return;
        }
        liveRoot.hidden = true;
        if (liveRoot.classList) {
            liveRoot.classList.add('hidden');
        }
        liveRoot.setAttribute('aria-hidden', 'true');
        if (!liveRoot.hasAttribute('hidden')) {
            liveRoot.setAttribute('hidden', '');
        }
    }

    function applySwap(ssrRoot, liveRoot) {
        if (!ssrRoot || !liveRoot) {
            return;
        }
        ssrRoot.hidden = true;
        if (ssrRoot.classList) {
            ssrRoot.classList.add('hidden');
        }
        ssrRoot.setAttribute('aria-hidden', 'true');
        if (!ssrRoot.hasAttribute('hidden')) {
            ssrRoot.setAttribute('hidden', '');
        }

        liveRoot.hidden = false;
        if (liveRoot.classList) {
            liveRoot.classList.remove('hidden');
        }
        liveRoot.removeAttribute('hidden');
        liveRoot.removeAttribute('aria-hidden');
    }

    function safeDestroyRenderer() {
        var renderer = getRendererApi();
        if (renderer && typeof renderer.destroy === 'function') {
            try {
                renderer.destroy();
            } catch (err) {
                console.error('[ExpedienteRegistrosCanonicalMount] destroy failed:', err);
            }
        }
    }

    function onCanonicalFabClick(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        var renderer = getRendererApi();
        if (!renderer || typeof renderer.openCreate !== 'function') {
            return;
        }
        renderer.openCreate(mountState.canonicalFab || null);
    }

    /**
     * Sustituye el FAB provisional por un clon con listener canónico.
     * @param {HTMLElement} fab
     * @returns {boolean}
     */
    function adoptFab(fab) {
        if (!fab || !fab.parentNode || mountState.fabAdopted) {
            return false;
        }
        var renderer = getRendererApi();
        if (!renderer || typeof renderer.openCreate !== 'function') {
            return false;
        }

        var clone = fab.cloneNode(true);
        fab.parentNode.replaceChild(clone, fab);
        clone.addEventListener('click', onCanonicalFabClick);

        mountState.originalFab = fab;
        mountState.canonicalFab = clone;
        mountState.fabAdopted = true;
        return true;
    }

    function restoreFab() {
        if (!mountState.fabAdopted) {
            mountState.originalFab = null;
            mountState.canonicalFab = null;
            return;
        }
        var clone = mountState.canonicalFab;
        var original = mountState.originalFab;
        if (clone && typeof clone.removeEventListener === 'function') {
            clone.removeEventListener('click', onCanonicalFabClick);
        }
        if (clone && original && clone.parentNode) {
            clone.parentNode.replaceChild(original, clone);
        }
        mountState.originalFab = null;
        mountState.canonicalFab = null;
        mountState.fabAdopted = false;
    }

    function scheduleNavigation() {
        if (!mountState.mounted || !mountState.swapped || !mountState.fabAdopted) {
            return;
        }
        if (mountState.navigationScheduled) {
            return;
        }
        var url = mountState.successUrl;
        if (!isValidSuccessUrl(url)) {
            return;
        }
        mountState.navigationScheduled = true;
        try {
            window.location.assign(url);
        } catch (err) {
            console.error('[ExpedienteRegistrosCanonicalMount] navigation failed:', err);
        }
    }

    function handleCreateComplete(payload) {
        if (!mountState.mounted || !mountState.swapped || !mountState.fabAdopted) {
            return;
        }
        if (!payload || !(parseInt(payload.recordId, 10) > 0)) {
            return;
        }
        var outcome = payload.imageOutcome;
        if (outcome !== 'none' && outcome !== 'saved' && outcome !== 'failed' && outcome !== 'abandoned') {
            return;
        }
        scheduleNavigation();
    }

    function resetMountFlags() {
        mountState.mounted = false;
        mountState.swapped = false;
        mountState.navigationScheduled = false;
        mountState.ssrRoot = null;
        mountState.liveRoot = null;
        mountState.successUrl = '';
    }

    /**
     * @returns {boolean}
     */
    function mount() {
        if (mountState.mounted) {
            return false;
        }

        try {
            var config = getConfig();
            if (!config || typeof config !== 'object') {
                return false;
            }

            var adapterApi = getAdapterApi();
            if (!adapterApi || typeof adapterApi.build !== 'function') {
                return false;
            }

            var built = adapterApi.build(config);
            if (!built || !built.scopeKey || !built.ports || !built.capabilities) {
                return false;
            }

            var renderer = getRendererApi();
            if (!renderer || typeof renderer.init !== 'function') {
                return false;
            }

            var section = document.getElementById('aa-expediente-detail-registros');
            var ssrRoot = document.getElementById('aa-expediente-detail-registros-ssr');
            var liveRoot = document.getElementById('aa-expediente-detail-registros-live');
            var fab = document.getElementById('aa-expediente-detail-new-registro');
            var pagination = section
                ? section.querySelector('.aa-expediente-detail-pagination')
                : null;
            var title = section
                ? section.querySelector('.aa-expediente-detail-registros-title')
                : null;

            if (!section || !ssrRoot || !liveRoot || !fab || !title) {
                return false;
            }

            if (pagination
                && (liveRoot.contains(pagination) || ssrRoot.contains(pagination))) {
                return false;
            }

            hideLive(liveRoot);

            var successUrl = isValidSuccessUrl(config.successUrl)
                ? String(config.successUrl).trim()
                : '';

            mountState.mounted = true;
            mountState.swapped = false;
            mountState.fabAdopted = false;
            mountState.navigationScheduled = false;
            mountState.ssrRoot = ssrRoot;
            mountState.liveRoot = liveRoot;
            mountState.successUrl = successUrl;
            mountState.originalFab = null;
            mountState.canonicalFab = null;

            var initOptions = {
                recordsRoot: liveRoot,
                scopeKey: built.scopeKey,
                ports: built.ports,
                capabilities: built.capabilities,
                onInitialLoad: function (outcome) {
                    if (!mountState.mounted) {
                        return;
                    }
                    if (!outcome || outcome.ok !== true) {
                        hideLive(liveRoot);
                        safeDestroyRenderer();
                        restoreFab();
                        resetMountFlags();
                        return;
                    }
                    if (mountState.swapped) {
                        return;
                    }
                    mountState.swapped = true;
                    applySwap(ssrRoot, liveRoot);

                    // FAB canónico solo con successUrl válida (si no, provisional intacto).
                    if (successUrl) {
                        var liveFab = document.getElementById('aa-expediente-detail-new-registro');
                        adoptFab(liveFab || fab);
                    }
                }
            };

            if (successUrl) {
                initOptions.onCreateComplete = handleCreateComplete;
            }

            renderer.init(initOptions);

            return true;
        } catch (err) {
            console.error('[ExpedienteRegistrosCanonicalMount] mount failed:', err);
            hideLive(mountState.liveRoot || document.getElementById('aa-expediente-detail-registros-live'));
            safeDestroyRenderer();
            restoreFab();
            resetMountFlags();
            return false;
        }
    }

    function destroy() {
        mountState.navigationScheduled = true;
        restoreFab();
        safeDestroyRenderer();
        if (mountState.liveRoot) {
            hideLive(mountState.liveRoot);
        }
        resetMountFlags();
        mountState.navigationScheduled = false;
    }

    window.AAAdmin.ExpedienteRegistrosCanonicalMount = {
        mount: mount,
        destroy: destroy
    };

    function autoMount() {
        mount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoMount);
    } else {
        autoMount();
    }
})();
