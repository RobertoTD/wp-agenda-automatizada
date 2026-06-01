/**
 * Learning Module - Guías / Aprendizaje
 *
 * Carga y renderiza recomendaciones vía LearningService (read-only).
 */

(function () {
    'use strict';

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderRecommendationCard(item) {
        var title = escapeHtml(item.title || '');
        var description = escapeHtml(item.description || '');
        var actionUrl = item.action_url || '';
        var actionLabel = escapeHtml(item.action_label || 'Ir');

        var ctaHtml = '';

        if (actionUrl) {
            ctaHtml = '<a href="' + escapeHtml(actionUrl) + '"'
                + ' class="inline-flex items-center mt-3 px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition-colors">'
                + actionLabel
                + '</a>';
        }

        return '<li class="rounded-lg border border-gray-200 bg-gray-50/80 p-4">'
            + '<p class="text-sm font-semibold text-gray-900">' + title + '</p>'
            + '<p class="text-sm text-gray-600 mt-1">' + description + '</p>'
            + ctaHtml
            + '</li>';
    }

    /**
     * @param {HTMLElement} listEl
     * @param {Array} items
     */
    function renderList(listEl, items) {
        if (!listEl) {
            return;
        }

        if (!items || items.length === 0) {
            listEl.innerHTML = '';
            return;
        }

        listEl.innerHTML = items.map(renderRecommendationCard).join('');
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

    function loadRecommendations() {
        var root = document.getElementById('aa-learning-recommendations-root');
        var loadingEl = document.getElementById('aa-learning-loading');
        var errorEl = document.getElementById('aa-learning-error');
        var emptyEl = document.getElementById('aa-learning-empty');
        var primaryWrap = document.getElementById('aa-learning-list-primary-wrap');
        var secondaryWrap = document.getElementById('aa-learning-list-secondary-wrap');
        var primaryList = document.getElementById('aa-learning-list-primary');
        var secondaryList = document.getElementById('aa-learning-list-secondary');

        if (!root) {
            return;
        }

        if (!window.LearningService || typeof window.LearningService.getRecommendations !== 'function') {
            setVisible(loadingEl, false);
            setVisible(errorEl, true);
            if (errorEl) {
                errorEl.textContent = 'No se pudo inicializar el servicio de recomendaciones.';
            }
            return;
        }

        window.LearningService.getRecommendations()
            .then(function (data) {
                setVisible(loadingEl, false);
                setVisible(errorEl, false);

                var list1 = data.list_1 || [];
                var list2 = data.list_2 || [];
                var hasAny = list1.length > 0 || list2.length > 0;

                setVisible(emptyEl, !hasAny);
                setVisible(primaryWrap, list1.length > 0);
                setVisible(secondaryWrap, list2.length > 0);

                renderList(primaryList, list1);
                renderList(secondaryList, list2);
            })
            .catch(function (err) {
                setVisible(loadingEl, false);
                setVisible(emptyEl, false);
                setVisible(primaryWrap, false);
                setVisible(secondaryWrap, false);
                setVisible(errorEl, true);

                if (errorEl) {
                    errorEl.textContent = (err && err.message)
                        ? err.message
                        : 'No se pudieron cargar las recomendaciones.';
                }

                console.error('[Learning Module]', err);
            });
    }

    function initLearningModule() {
        loadRecommendations();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLearningModule);
    } else {
        initLearningModule();
    }
})();
