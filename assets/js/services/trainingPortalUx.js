/**
 * Training portal UX helpers — catalog / lesson presentation (C8A3).
 * Pure functions; no DOM, no fetch.
 */
(function (root) {
    'use strict';

    /**
     * @param {Array<{position?: number}>|null|undefined} lessons
     * @returns {Array<object>}
     */
    function sortLessonsByPosition(lessons) {
        if (!Array.isArray(lessons)) {
            return [];
        }
        return lessons.slice().sort(function (a, b) {
            var pa = typeof a.position === 'number' ? a.position : 0;
            var pb = typeof b.position === 'number' ? b.position : 0;
            return pa - pb;
        });
    }

    /**
     * @param {object|null|undefined} err
     * @returns {{ text: string, retry: boolean }}
     */
    function mapCatalogError() {
        return {
            text: 'No pudimos cargar el curso.',
            retry: true
        };
    }

    /**
     * @param {object|null|undefined} err
     * @returns {{
     *   kind: string,
     *   text: string,
     *   retry: boolean,
     *   showAccountLink: boolean
     * }}
     */
    function mapLessonError(err) {
        var code = err && typeof err.code === 'string' ? err.code : '';

        if (code === 'training_content_lesson_unavailable') {
            return {
                kind: 'unavailable',
                text: 'Esta lección estará disponible pronto.',
                retry: false,
                showAccountLink: false
            };
        }

        if (
            code === 'training_content_lesson_not_found'
            || code === 'training_content_lesson_key_invalid'
        ) {
            return {
                kind: 'not_found',
                text: 'No encontramos esta lección.',
                retry: false,
                showAccountLink: false
            };
        }

        if (
            code === 'training_not_eligible'
            || code === 'training_enrollment_not_active'
            || code === 'training_forbidden'
            || code === 'training_enrollment_not_found'
        ) {
            return {
                kind: 'no_access',
                text: 'Tu acceso al curso ya no está disponible.',
                retry: false,
                showAccountLink: true
            };
        }

        if (
            code === 'training_content_invalid_lesson'
            || code === 'training_content_invalid_manifest'
            || code === 'training_content_render_failed'
        ) {
            return {
                kind: 'invalid',
                text: 'No pudimos mostrar el contenido.',
                retry: true,
                showAccountLink: false
            };
        }

        return {
            kind: 'network',
            text: 'No pudimos conectarnos.',
            retry: true,
            showAccountLink: false
        };
    }

    /**
     * @param {object|null|undefined} block
     * @returns {boolean}
     */
    function isRenderableBlock(block) {
        if (!block || typeof block !== 'object') {
            return false;
        }
        return block.type === 'rich_text' || block.type === 'exercise';
    }

    var api = {
        sortLessonsByPosition: sortLessonsByPosition,
        mapCatalogError: mapCatalogError,
        mapLessonError: mapLessonError,
        isRenderableBlock: isRenderableBlock
    };

    root.TrainingPortalUx = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
