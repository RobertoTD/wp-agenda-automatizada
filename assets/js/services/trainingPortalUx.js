/**
 * Training portal UX helpers — catalog / lesson presentation (C8A3 / C9A5a).
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

    /**
     * @param {unknown} value
     * @returns {'available'|'locked'|'upcoming'|null}
     */
    function normalizeLessonAccessState(value) {
        if (value === 'available' || value === 'locked' || value === 'upcoming') {
            return value;
        }
        return null;
    }

    /**
     * Opening is gated solely by access_state === available.
     *
     * @param {object|null|undefined} lesson
     * @returns {boolean}
     */
    function canOpenTrainingLesson(lesson) {
        return normalizeLessonAccessState(lesson && lesson.access_state) === 'available';
    }

    /**
     * @param {object|null|undefined} lesson
     * @returns {boolean}
     */
    function isTrainingLessonCompleted(lesson) {
        var progress = lesson && lesson.progress && typeof lesson.progress === 'object'
            ? lesson.progress
            : null;
        return !!(progress && progress.completed === true);
    }

    /**
     * @param {object|null|undefined} lesson
     * @returns {boolean}
     */
    function isTrainingLessonOpened(lesson) {
        var progress = lesson && lesson.progress && typeof lesson.progress === 'object'
            ? lesson.progress
            : null;
        if (progress && progress.opened === true) {
            return true;
        }
        return isTrainingLessonCompleted(lesson);
    }

    /**
     * Catalog row presentation. Does not inspect unlock prerequisites.
     *
     * @param {object|null|undefined} lesson
     * @returns {{
     *   access_state: 'available'|'locked'|'upcoming'|null,
     *   showOpen: boolean,
     *   showCompletedBadge: boolean,
     *   statusLabel: string|null
     * }}
     */
    function mapCatalogLessonPresentation(lesson) {
        var accessState = normalizeLessonAccessState(lesson && lesson.access_state);
        var completed = isTrainingLessonCompleted(lesson);

        if (accessState === 'available') {
            return {
                access_state: 'available',
                showOpen: true,
                showCompletedBadge: completed,
                statusLabel: null
            };
        }

        if (accessState === 'locked') {
            return {
                access_state: 'locked',
                showOpen: false,
                showCompletedBadge: false,
                statusLabel: 'Completa la lección anterior'
            };
        }

        if (accessState === 'upcoming') {
            return {
                access_state: 'upcoming',
                showOpen: false,
                showCompletedBadge: false,
                statusLabel: 'Próximamente'
            };
        }

        return {
            access_state: null,
            showOpen: false,
            showCompletedBadge: false,
            statusLabel: null
        };
    }

    /**
     * Reader footer for C9A5a (static only; no actionable CTA).
     *
     * @param {{
     *   lessonMeta?: object|null,
     *   completion_flow?: object|null
     * }} input
     * @returns {{ mode: 'completed'|'none', label: string|null }}
     */
    function mapLessonCompletionFooter(input) {
        var meta = input && input.lessonMeta ? input.lessonMeta : null;
        if (isTrainingLessonCompleted(meta)) {
            return {
                mode: 'completed',
                label: 'Lección completada'
            };
        }

        // Pending completion_flow: reserved for C9A5b — no non-functional CTA.
        return {
            mode: 'none',
            label: null
        };
    }

    /**
     * @param {object|null|undefined} manifest
     * @param {string} lessonKey
     * @returns {object|null}
     */
    function findLessonInManifest(manifest, lessonKey) {
        if (!manifest || !Array.isArray(manifest.lessons) || typeof lessonKey !== 'string' || !lessonKey) {
            return null;
        }
        for (var i = 0; i < manifest.lessons.length; i += 1) {
            var entry = manifest.lessons[i];
            if (entry && entry.key === lessonKey) {
                return entry;
            }
        }
        return null;
    }

    /**
     * Whether markOpened may be skipped because progress already reflects open/complete.
     *
     * @param {object|null|undefined} lessonMeta
     * @returns {boolean}
     */
    function shouldSkipMarkOpened(lessonMeta) {
        return isTrainingLessonOpened(lessonMeta);
    }

    var api = {
        sortLessonsByPosition: sortLessonsByPosition,
        mapCatalogError: mapCatalogError,
        mapLessonError: mapLessonError,
        isRenderableBlock: isRenderableBlock,
        normalizeLessonAccessState: normalizeLessonAccessState,
        canOpenTrainingLesson: canOpenTrainingLesson,
        isTrainingLessonCompleted: isTrainingLessonCompleted,
        isTrainingLessonOpened: isTrainingLessonOpened,
        mapCatalogLessonPresentation: mapCatalogLessonPresentation,
        mapLessonCompletionFooter: mapLessonCompletionFooter,
        findLessonInManifest: findLessonInManifest,
        shouldSkipMarkOpened: shouldSkipMarkOpened
    };

    root.TrainingPortalUx = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
