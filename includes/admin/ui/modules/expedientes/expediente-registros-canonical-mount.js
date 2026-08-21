/**
 * Expediente Registros Canonical Mount (C1c1).
 *
 * Bootstrap presentacional: build adapter → init renderer en live oculto →
 * swap SSR/live tras onInitialLoad({ok:true}). Create sigue provisional.
 *
 * API: AAAdmin.ExpedienteRegistrosCanonicalMount.mount() / .destroy()
 */
(function () {
    'use strict';

    window.AAAdmin = window.AAAdmin || {};

    var mountState = {
        mounted: false,
        swapped: false,
        ssrRoot: null,
        liveRoot: null
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

            // Paginación es opcional (una sola página); si existe debe estar fuera de live/ssr.
            if (pagination
                && (liveRoot.contains(pagination) || ssrRoot.contains(pagination))) {
                return false;
            }

            hideLive(liveRoot);

            mountState.mounted = true;
            mountState.swapped = false;
            mountState.ssrRoot = ssrRoot;
            mountState.liveRoot = liveRoot;

            renderer.init({
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
                        mountState.mounted = false;
                        mountState.swapped = false;
                        mountState.ssrRoot = null;
                        mountState.liveRoot = null;
                        return;
                    }
                    if (mountState.swapped) {
                        return;
                    }
                    mountState.swapped = true;
                    applySwap(ssrRoot, liveRoot);
                }
            });

            return true;
        } catch (err) {
            console.error('[ExpedienteRegistrosCanonicalMount] mount failed:', err);
            hideLive(mountState.liveRoot || document.getElementById('aa-expediente-detail-registros-live'));
            safeDestroyRenderer();
            mountState.mounted = false;
            mountState.swapped = false;
            mountState.ssrRoot = null;
            mountState.liveRoot = null;
            return false;
        }
    }

    function destroy() {
        safeDestroyRenderer();
        if (mountState.liveRoot) {
            hideLive(mountState.liveRoot);
        }
        // Tras swap exitoso no reponemos SSR automáticamente (estado puede estar viejo).
        mountState.mounted = false;
        mountState.swapped = false;
        mountState.ssrRoot = null;
        mountState.liveRoot = null;
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
