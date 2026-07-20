/**
 * Training Module — catalog + lesson reader (C8A3).
 *
 * Uses TrainingService.getCourse / getLesson only. HTML arrives pre-sanitized by PHP.
 */
(function (root) {
    'use strict';

    var PRIMARY_BTN =
        'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';
    var SECONDARY_BTN =
        'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';

    var courseRequestId = 0;
    var lessonRequestId = 0;
    var cachedManifest = null;
    var activeLessonKey = null;
    var handlersBound = false;

    function getEl(id) {
        return document.getElementById(id);
    }

    function getConfig() {
        return root.AA_TRAINING_DATA || {};
    }

    function resolveUx() {
        if (root.TrainingPortalUx && typeof root.TrainingPortalUx.sortLessonsByPosition === 'function') {
            return root.TrainingPortalUx;
        }
        return null;
    }

    function resolveService() {
        if (root.TrainingService && typeof root.TrainingService.getCourse === 'function') {
            return root.TrainingService;
        }
        return null;
    }

    function setAriaHidden(el, hidden) {
        if (!el) {
            return;
        }
        el.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    }

    /**
     * Mutually exclusive surfaces: loading | error | catalog | lesson
     * @param {'loading'|'error'|'catalog'|'lesson'} mode
     */
    function showSurface(mode) {
        var loadingEl = getEl('aa-training-shell-loading');
        var errorEl = getEl('aa-training-shell-error');
        var catalogEl = getEl('aa-training-catalog-root');
        var lessonEl = getEl('aa-training-lesson-root');

        if (loadingEl) {
            loadingEl.classList.toggle('hidden', mode !== 'loading');
        }
        if (errorEl) {
            errorEl.classList.toggle('hidden', mode !== 'error');
        }
        if (catalogEl) {
            catalogEl.classList.toggle('hidden', mode !== 'catalog');
            setAriaHidden(catalogEl, mode !== 'catalog');
        }
        if (lessonEl) {
            lessonEl.classList.toggle('hidden', mode !== 'lesson');
            setAriaHidden(lessonEl, mode !== 'lesson');
        }
    }

    function showLessonInner(mode) {
        var loadingEl = getEl('aa-training-lesson-loading');
        var errorEl = getEl('aa-training-lesson-error');
        var contentEl = getEl('aa-training-lesson-content');

        if (loadingEl) {
            loadingEl.classList.toggle('hidden', mode !== 'loading');
        }
        if (errorEl) {
            errorEl.classList.toggle('hidden', mode !== 'error');
        }
        if (contentEl) {
            contentEl.classList.toggle('hidden', mode !== 'content');
        }
    }

    function clearHost(el) {
        if (el) {
            el.innerHTML = '';
        }
    }

    function appendRetryButton(host, onClick, label) {
        if (!host) {
            return;
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = PRIMARY_BTN;
        btn.textContent = label || 'Reintentar';
        btn.addEventListener('click', onClick);
        host.appendChild(btn);
    }

    function showCatalogError() {
        var ux = resolveUx();
        var mapped = ux ? ux.mapCatalogError() : { text: 'No pudimos cargar el curso.', retry: true };
        var msgEl = getEl('aa-training-shell-error-message');
        var actionsEl = getEl('aa-training-shell-error-actions');

        if (msgEl) {
            msgEl.textContent = mapped.text;
        }
        clearHost(actionsEl);
        if (mapped.retry) {
            appendRetryButton(actionsEl, function () {
                loadCourse();
            });
        }
        showSurface('error');
    }

    /**
     * @param {object} err
     * @param {string} lessonKey
     */
    function showLessonError(err, lessonKey) {
        var ux = resolveUx();
        var mapped = ux
            ? ux.mapLessonError(err)
            : { text: 'No pudimos conectarnos.', retry: true, showAccountLink: false };
        var msgEl = getEl('aa-training-lesson-error-message');
        var actionsEl = getEl('aa-training-lesson-error-actions');
        var cfg = getConfig();

        if (msgEl) {
            msgEl.textContent = mapped.text;
        }
        clearHost(actionsEl);

        if (mapped.showAccountLink) {
            var link = document.createElement('a');
            link.className = PRIMARY_BTN;
            link.href = typeof cfg.accountModuleUrl === 'string' ? cfg.accountModuleUrl : '#';
            link.textContent = 'Ir a Cuenta';
            if (actionsEl) {
                actionsEl.appendChild(link);
            }
        } else if (mapped.retry) {
            appendRetryButton(actionsEl, function () {
                openLesson(lessonKey);
            });
        }

        showSurface('lesson');
        showLessonInner('error');
    }

    /**
     * @param {object} manifest { course, lessons }
     */
    function renderCatalog(manifest) {
        var ux = resolveUx();
        var course = manifest && manifest.course ? manifest.course : {};
        var lessons = ux
            ? ux.sortLessonsByPosition(manifest.lessons)
            : (Array.isArray(manifest.lessons) ? manifest.lessons.slice() : []);

        var titleEl = getEl('aa-training-catalog-title');
        var descEl = getEl('aa-training-catalog-description');
        var listEl = getEl('aa-training-catalog-lessons');

        if (titleEl) {
            titleEl.textContent = typeof course.title === 'string' ? course.title : '';
        }
        if (descEl) {
            descEl.textContent = typeof course.description === 'string' ? course.description : '';
        }

        clearHost(listEl);

        lessons.forEach(function (lesson) {
            if (!lesson || typeof lesson !== 'object') {
                return;
            }

            var li = document.createElement('li');
            li.className = 'rounded-lg border border-gray-200 bg-white p-4';

            var row = document.createElement('div');
            row.className = 'flex flex-wrap items-center justify-between gap-3';

            var meta = document.createElement('div');
            meta.className = 'min-w-0';

            var lessonTitle = document.createElement('p');
            lessonTitle.className = 'text-sm font-medium text-gray-900';
            lessonTitle.textContent = typeof lesson.title === 'string' ? lesson.title : '';

            meta.appendChild(lessonTitle);
            row.appendChild(meta);

            if (lesson.availability === 'available') {
                var openBtn = document.createElement('button');
                openBtn.type = 'button';
                openBtn.className = PRIMARY_BTN;
                openBtn.textContent = 'Abrir';
                openBtn.setAttribute('data-aa-training-open-lesson', String(lesson.key || ''));
                row.appendChild(openBtn);
            } else if (lesson.availability === 'upcoming') {
                var badge = document.createElement('span');
                badge.className = 'text-sm text-gray-500';
                badge.textContent = 'Próximamente';
                row.appendChild(badge);
            }

            li.appendChild(row);
            if (listEl) {
                listEl.appendChild(li);
            }
        });

        showSurface('catalog');
    }

    /**
     * @param {object} lessonPayload
     */
    function renderLesson(lessonPayload) {
        var lesson = lessonPayload && lessonPayload.lesson ? lessonPayload.lesson : {};
        var blocks = lessonPayload && Array.isArray(lessonPayload.blocks) ? lessonPayload.blocks : [];
        var ux = resolveUx();

        var titleEl = getEl('aa-training-lesson-title');
        var blocksEl = getEl('aa-training-lesson-blocks');

        if (titleEl) {
            titleEl.textContent = typeof lesson.title === 'string' ? lesson.title : '';
        }

        clearHost(blocksEl);

        blocks.forEach(function (block) {
            if (!block || typeof block !== 'object') {
                return;
            }
            if (ux && !ux.isRenderableBlock(block)) {
                return;
            }
            if (!ux && block.type !== 'rich_text' && block.type !== 'exercise') {
                return;
            }

            if (block.type === 'rich_text') {
                var rich = document.createElement('div');
                rich.className = 'aa-training-rich-text space-y-3 leading-relaxed';
                rich.innerHTML = typeof block.html === 'string' ? block.html : '';
                if (blocksEl) {
                    blocksEl.appendChild(rich);
                }
                return;
            }

            if (block.type === 'exercise') {
                var section = document.createElement('section');
                section.className = 'rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-2';

                var exTitle = document.createElement('h5');
                exTitle.className = 'text-sm font-semibold text-gray-900';
                exTitle.textContent = typeof block.title === 'string' ? block.title : '';

                var exBody = document.createElement('p');
                exBody.className = 'text-sm text-gray-700 whitespace-pre-wrap';
                exBody.textContent = typeof block.instructions === 'string' ? block.instructions : '';

                section.appendChild(exTitle);
                section.appendChild(exBody);
                if (blocksEl) {
                    blocksEl.appendChild(section);
                }
            }
        });

        showSurface('lesson');
        showLessonInner('content');
    }

    function loadCourse() {
        var service = resolveService();
        var loadingMsg = getEl('aa-training-shell-loading-message');
        if (loadingMsg) {
            loadingMsg.textContent = 'Cargando el curso…';
        }

        if (!service) {
            showCatalogError();
            return Promise.resolve();
        }

        var requestId = ++courseRequestId;
        showSurface('loading');

        return service.getCourse()
            .then(function (result) {
                if (requestId !== courseRequestId) {
                    return;
                }
                var data = result && result.data && typeof result.data === 'object' ? result.data : null;
                if (!data || !data.course) {
                    showCatalogError();
                    return;
                }
                cachedManifest = data;
                renderCatalog(data);
            })
            .catch(function () {
                if (requestId !== courseRequestId) {
                    return;
                }
                showCatalogError();
            });
    }

    /**
     * @param {string} lessonKey
     */
    function openLesson(lessonKey) {
        var key = typeof lessonKey === 'string' ? lessonKey : '';
        var service = resolveService();

        if (!key || !service) {
            return Promise.resolve();
        }

        var requestId = ++lessonRequestId;
        activeLessonKey = key;
        showSurface('lesson');
        showLessonInner('loading');

        return service.getLesson(key)
            .then(function (result) {
                if (requestId !== lessonRequestId) {
                    return;
                }
                var data = result && result.data && typeof result.data === 'object' ? result.data : null;
                if (!data) {
                    showLessonError({ code: 'training_invalid_response' }, key);
                    return;
                }
                renderLesson(data);
            })
            .catch(function (err) {
                if (requestId !== lessonRequestId) {
                    return;
                }
                showLessonError(err || {}, key);
            });
    }

    /**
     * Returns to catalog without re-fetching the manifest when cached.
     */
    function backToCatalog() {
        lessonRequestId += 1;
        activeLessonKey = null;

        if (cachedManifest) {
            renderCatalog(cachedManifest);
            return;
        }

        loadCourse();
    }

    function handleRootClick(event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }

        var openEl = target.closest('[data-aa-training-open-lesson]');
        if (openEl) {
            event.preventDefault();
            var key = openEl.getAttribute('data-aa-training-open-lesson') || '';
            if (key) {
                openLesson(key);
            }
        }
    }

    function bindHandlers() {
        if (handlersBound) {
            return;
        }
        var rootEl = getEl('aa-training-shell-root');
        var backBtn = getEl('aa-training-lesson-back');

        if (rootEl) {
            rootEl.addEventListener('click', handleRootClick);
        }
        if (backBtn) {
            backBtn.addEventListener('click', function () {
                backToCatalog();
            });
        }
        handlersBound = true;
    }

    function init() {
        var rootEl = getEl('aa-training-shell-root');
        if (!rootEl) {
            return;
        }
        bindHandlers();
        loadCourse();
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', init);
    }

    var api = {
        init: init,
        loadCourse: loadCourse,
        openLesson: openLesson,
        backToCatalog: backToCatalog,
        renderCatalog: renderCatalog,
        renderLesson: renderLesson,
        showCatalogError: showCatalogError,
        showLessonError: showLessonError,
        getCachedManifest: function () {
            return cachedManifest;
        },
        _setCachedManifestForTests: function (value) {
            cachedManifest = value;
        },
        _getRequestIdsForTests: function () {
            return { courseRequestId: courseRequestId, lessonRequestId: lessonRequestId };
        }
    };

    root.TrainingModule = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
