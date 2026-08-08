/**
 * Shell Access Projection — proyección asíncrona SOLO de UX del gate legal.
 *
 * Reutiliza el endpoint existente `aa_get_legal_gate_status` (que ejecuta
 * ResolveShellAccessUseCase). NUNCA es autoridad: la seguridad real vive en las
 * URL/AJAX de Expedientes (síncronas y fail-closed) y en el router.
 *
 * Contrato de resolución (una respuesta actual y concluyente):
 *   - access === 'full'       → habilita UX de Expedientes (evento + flag).
 *   - access === 'free'       → shell general; Expedientes sigue bloqueado.
 *   - access === 'legal_gate' → navegación dirigida al marcador (evento único,
 *                               nunca cacheado, limpia estado antes de navegar).
 *   - pending/error/inválido  → no habilita nada, no cachea, no navega.
 *
 * Caché temporal (solo full/free concluyentes, TTL corto, aislada por
 * blogId+authSessionId). `legal_gate`, errores, timeouts y payloads inválidos
 * NO se cachean. Promesa compartida vía documento padre persistente cuando esté
 * disponible; generación para descartar respuestas antiguas.
 */
(function () {
    'use strict';

    // ---- Pure helpers (testable en Node) ----------------------------------

    function cacheKey(blogId, authSessionId) {
        return 'aa_shell_access:' + String(blogId == null ? '' : blogId) + ':' + String(authSessionId == null ? '' : authSessionId);
    }

    function isCacheable(access, reason) {
        if (access === 'full') {
            return true;
        }
        if (access === 'free') {
            // No cachear frees derivados de fallo de transporte / estado desconocido
            // (fail-open): se tratan como inconcluyentes para volver a consultar pronto.
            return reason !== 'transport_error' && reason !== 'unknown';
        }
        return false;
    }

    function isFresh(entry, nowMs, ttlMs) {
        return !!entry
            && typeof entry.ts === 'number'
            && (entry.access === 'full' || entry.access === 'free')
            && (nowMs - entry.ts) < ttlMs;
    }

    // ---- Browser projection ------------------------------------------------

    if (typeof window !== 'undefined' && window.document) {
        (function runProjection() {
            var cfg = window.AA_SHELL_ACCESS_DATA || null;
            if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
                return;
            }

            var TTL_MS = typeof cfg.ttlMs === 'number' ? cfg.ttlMs : 60000;
            var GATE_PARAM = cfg.gateParam || 'aa_gate';
            var KEY = cacheKey(cfg.blogId, cfg.authSessionId);

            // Documento padre persistente cuando sea accesible (mismo origen);
            // permite compartir la promesa en curso entre navegaciones del iframe.
            function sharedRoot() {
                try {
                    if (window.parent && window.parent !== window) {
                        // Lanza si es cross-origin: en ese caso usamos el propio window.
                        void window.parent.location.href;
                        return window.parent;
                    }
                } catch (e) { /* cross-origin: aislar en window */ }
                return window;
            }

            var store = sharedRoot();
            var ns = store.__aaShellAccessProjection = store.__aaShellAccessProjection || { promise: null, gen: 0 };

            // Nueva época por documento; descarta respuestas de épocas previas.
            var epoch = (ns.gen = (ns.gen | 0) + 1);

            // Guardia por documento (NO compartida): evita doble navegación dentro
            // de esta ejecución sin bloquear un re-gate legítimo más adelante.
            var navigating = false;

            function readCache() {
                try {
                    var raw = sessionStorage.getItem(KEY);
                    if (!raw) {
                        return null;
                    }
                    var entry = JSON.parse(raw);
                    return isFresh(entry, Date.now(), TTL_MS) ? String(entry.access) : null;
                } catch (e) {
                    return null;
                }
            }

            function writeCache(access, reason) {
                if (!isCacheable(access, reason)) {
                    return;
                }
                try {
                    sessionStorage.setItem(KEY, JSON.stringify({ access: access, ts: Date.now() }));
                } catch (e) { /* storage no disponible: proyección degradada, sin caché */ }
            }

            function clearCache() {
                try {
                    sessionStorage.removeItem(KEY);
                } catch (e) { /* noop */ }
            }

            function enableExpedienteUx() {
                try {
                    if (window.AA_CLIENTS_DATA) {
                        window.AA_CLIENTS_DATA.expedienteAccessAllowed = true;
                    }
                } catch (e) { /* noop */ }
                try {
                    document.dispatchEvent(new CustomEvent('aa:shell-access-resolved', { detail: { access: 'full' } }));
                } catch (e) { /* CustomEvent no soportado: flag ya cubre creación futura */ }
            }

            function navigateToGate() {
                if (navigating) {
                    return;
                }
                navigating = true;
                // legal_gate es evento de una sola actuación: limpiar estado antes
                // de navegar para impedir bucles tras aceptar los documentos.
                clearCache();
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set(GATE_PARAM, '1');
                    url.searchParams.set('_wpnonce', cfg.nonce);
                    window.location.assign(url.toString());
                } catch (e) {
                    var sep = window.location.href.indexOf('?') === -1 ? '?' : '&';
                    window.location.href = window.location.href
                        + sep + encodeURIComponent(GATE_PARAM) + '=1'
                        + '&_wpnonce=' + encodeURIComponent(cfg.nonce);
                }
            }

            function apply(access, reason) {
                if (access === 'full') {
                    writeCache('full', reason);
                    enableExpedienteUx();
                } else if (access === 'free') {
                    writeCache('free', reason);
                    // Expedientes permanece bloqueado; no se emite habilitación.
                } else if (access === 'legal_gate') {
                    navigateToGate();
                }
                // pending / error / inválido: no-op (bloqueado, sin caché, sin navegación).
            }

            // 1) Camino rápido: caché fresca concluyente (solo full/free).
            var cached = readCache();
            if (cached) {
                apply(cached, '');
                return;
            }

            // 2) Promesa compartida en curso (reutilizada entre navegaciones).
            if (!ns.promise) {
                var url = cfg.ajaxUrl
                    + (cfg.ajaxUrl.indexOf('?') === -1 ? '?' : '&')
                    + 'action=' + encodeURIComponent(cfg.statusAction || 'aa_get_legal_gate_status')
                    + '&_wpnonce=' + encodeURIComponent(cfg.nonce);

                ns.promise = fetch(url, { method: 'GET', credentials: 'same-origin' })
                    .then(function (response) {
                        return response.json().then(function (json) {
                            return { ok: response.ok, json: json };
                        });
                    });
            }

            ns.promise.then(function (result) {
                ns.promise = null; // permite reconsulta en épocas posteriores
                if (epoch !== ns.gen) {
                    return; // respuesta de una época antigua: descartar
                }
                var json = result && result.json;
                // Solo success:true es concluyente; success:false = inconcluso → bloqueado.
                if (!result.ok || !json || json.success !== true || !json.data) {
                    return;
                }
                var access = json.data.access ? String(json.data.access) : '';
                var reason = json.data.reason ? String(json.data.reason) : '';
                apply(access, reason);
            }).catch(function () {
                ns.promise = null; // error/timeout: no cachear, permanecer bloqueado
            });
        })();
    }

    // ---- Exports para pruebas ---------------------------------------------

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            __test: {
                cacheKey: cacheKey,
                isCacheable: isCacheable,
                isFresh: isFresh
            }
        };
    }
})();
