'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('path');
const { describe, it, afterEach } = require('node:test');

const uxPath = path.join(__dirname, '../../assets/js/services/trainingPortalUx.js');
const ux = require(uxPath);
const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/training/module.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function el(id, extras) {
    const node = {
        id: id,
        classList: {
            _hidden: false,
            toggle: function (cls, force) {
                if (cls === 'hidden') {
                    this._hidden = !!force;
                }
            },
            add: function (cls) {
                if (cls === 'hidden') {
                    this._hidden = true;
                }
            },
            remove: function (cls) {
                if (cls === 'hidden') {
                    this._hidden = false;
                }
            },
            contains: function (cls) {
                return cls === 'hidden' ? this._hidden : false;
            }
        },
        style: {},
        children: [],
        attributes: {},
        textContent: '',
        innerHTML: '',
        href: '',
        disabled: false,
        setAttribute: function (k, v) {
            this.attributes[k] = String(v);
        },
        getAttribute: function (k) {
            return this.attributes[k] || null;
        },
        appendChild: function (child) {
            this.children.push(child);
            return child;
        },
        addEventListener: function () {},
        closest: function () {
            return null;
        }
    };
    return Object.assign(node, extras || {});
}

function loadModule(serviceImpl) {
    const els = {};
    [
        'aa-training-shell-root',
        'aa-training-shell-loading',
        'aa-training-shell-loading-message',
        'aa-training-shell-error',
        'aa-training-shell-error-message',
        'aa-training-shell-error-actions',
        'aa-training-catalog-root',
        'aa-training-catalog-title',
        'aa-training-catalog-description',
        'aa-training-catalog-lessons',
        'aa-training-lesson-root',
        'aa-training-lesson-back',
        'aa-training-lesson-loading',
        'aa-training-lesson-error',
        'aa-training-lesson-error-message',
        'aa-training-lesson-error-actions',
        'aa-training-lesson-content',
        'aa-training-lesson-title',
        'aa-training-lesson-blocks',
        'aa-training-lesson-completion'
    ].forEach(function (id) {
        els[id] = el(id);
    });

    globalThis.document = {
        getElementById: function (id) {
            return els[id] || null;
        },
        createElement: function (tag) {
            return el(tag, { tagName: tag.toUpperCase() });
        },
        addEventListener: function () {}
    };
    globalThis.TrainingPortalUx = ux;
    globalThis.TrainingService = serviceImpl;
    globalThis.AA_TRAINING_DATA = {
        ajaxUrl: 'https://agenda.test/wp-admin/admin-ajax.php',
        nonce: 'n',
        accountModuleUrl: 'https://agenda.test/account',
        actions: {}
    };

    delete require.cache[modulePath];
    const mod = require(modulePath);
    return { mod: mod, els: els };
}

function flushMicrotasks() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

describe('TrainingPortalUx C9A5a helpers', () => {
    it('normalizeLessonAccessState y canOpen', () => {
        assert.equal(ux.normalizeLessonAccessState('available'), 'available');
        assert.equal(ux.normalizeLessonAccessState('locked'), 'locked');
        assert.equal(ux.normalizeLessonAccessState('draft'), null);
        assert.equal(ux.canOpenTrainingLesson({ access_state: 'available' }), true);
        assert.equal(ux.canOpenTrainingLesson({ access_state: 'locked' }), false);
        assert.equal(ux.canOpenTrainingLesson({ availability: 'available' }), false);
    });

    it('mapCatalogLessonPresentation available / locked / upcoming / completed', () => {
        assert.deepEqual(
            ux.mapCatalogLessonPresentation({
                access_state: 'available',
                progress: { opened: false, completed: false }
            }),
            {
                access_state: 'available',
                showOpen: true,
                showCompletedBadge: false,
                statusLabel: null
            }
        );
        assert.deepEqual(
            ux.mapCatalogLessonPresentation({
                access_state: 'locked',
                progress: { opened: false, completed: false }
            }),
            {
                access_state: 'locked',
                showOpen: false,
                showCompletedBadge: false,
                statusLabel: 'Completa la lección anterior'
            }
        );
        assert.deepEqual(
            ux.mapCatalogLessonPresentation({
                access_state: 'upcoming',
                progress: { opened: false, completed: false }
            }),
            {
                access_state: 'upcoming',
                showOpen: false,
                showCompletedBadge: false,
                statusLabel: 'Próximamente'
            }
        );
        const completed = ux.mapCatalogLessonPresentation({
            access_state: 'available',
            progress: { opened: true, completed: true }
        });
        assert.equal(completed.showOpen, true);
        assert.equal(completed.showCompletedBadge, true);
    });

    it('mapLessonCompletionFooter completed vs CTA vs sin flow', () => {
        assert.deepEqual(
            ux.mapLessonCompletionFooter({
                lessonMeta: { progress: { completed: true } },
                completion_flow: { trigger_label: 'Completar' }
            }),
            { mode: 'completed', label: 'Lección completada' }
        );
        assert.deepEqual(
            ux.mapLessonCompletionFooter({
                lessonMeta: { progress: { completed: false } },
                completion_flow: { trigger_label: 'Completar lección' }
            }),
            { mode: 'cta', label: 'Completar lección' }
        );
        assert.deepEqual(
            ux.mapLessonCompletionFooter({
                lessonMeta: { progress: { completed: false } },
                completion_flow: null
            }),
            { mode: 'none', label: null }
        );
    });

    it('módulo no inspecciona requirements', () => {
        assert.doesNotMatch(moduleSrc, /\.requirements\b|requirements\s*===|requirements\[/);
        assert.doesNotMatch(
            fs.readFileSync(uxPath, 'utf8'),
            /\.requirements\b|requirements\s*===|requirements\[/
        );
    });
});

describe('Training module C9A5a catalog + opened', () => {
    afterEach(() => {
        delete globalThis.document;
        delete globalThis.TrainingService;
        delete globalThis.TrainingPortalUx;
        delete globalThis.TrainingModule;
        delete globalThis.AA_TRAINING_DATA;
        delete require.cache[modulePath];
    });

    it('1-5. catálogo: available, locked, upcoming, completed badge', async () => {
        const { mod, els } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'Curso', description: 'D' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'Bienvenida',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: true, completed: true }
                            },
                            {
                                key: 'siguiente',
                                title: 'Siguiente',
                                position: 2,
                                availability: 'available',
                                access_state: 'locked',
                                progress: { opened: false, completed: false }
                            },
                            {
                                key: 'planeacion',
                                title: 'Planeación',
                                position: 3,
                                availability: 'upcoming',
                                access_state: 'upcoming',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                return Promise.resolve({ success: true, data: { lesson: {}, blocks: [] } });
            },
            markOpened: function () {
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        const items = els['aa-training-catalog-lessons'].children;
        assert.equal(items.length, 3);

        const bienvenidaRow = items[0].children[0];
        assert.equal(bienvenidaRow.children[0].children[0].textContent, 'Bienvenida');
        assert.equal(bienvenidaRow.children[0].children[1].textContent, 'Completada');
        assert.equal(bienvenidaRow.children[1].children[0].textContent, 'Abrir');

        const lockedRow = items[1].children[0];
        assert.equal(lockedRow.children[1].children[0].textContent, 'Completa la lección anterior');
        assert.equal(
            lockedRow.children[1].children[0].getAttribute('data-aa-training-open-lesson'),
            null
        );

        const upcomingRow = items[2].children[0];
        assert.equal(upcomingRow.children[1].children[0].textContent, 'Próximamente');
    });

    it('7-8. opened se llama después del render, no antes', async () => {
        const order = [];
        let resolveLesson;
        const lessonPromise = new Promise(function (resolve) {
            resolveLesson = resolve;
        });

        const { mod, els } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'B',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                order.push('getLesson');
                return lessonPromise;
            },
            markOpened: function () {
                order.push('markOpened');
                assert.equal(els['aa-training-lesson-title'].textContent, 'Rendered');
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        const openPromise = mod.openLesson('bienvenida');
        assert.deepEqual(order, ['getLesson']);
        resolveLesson({
            success: true,
            data: {
                lesson: { title: 'Rendered' },
                blocks: [{ type: 'rich_text', html: '<p>x</p>' }]
            }
        });
        await openPromise;
        await flushMicrotasks();
        assert.deepEqual(order, ['getLesson', 'markOpened']);
    });

    it('9. opened no se llama si getLesson falla', async () => {
        let opened = 0;
        const { mod } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'B',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                return Promise.reject({ code: 'training_network_error' });
            },
            markOpened: function () {
                opened += 1;
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        await mod.openLesson('bienvenida');
        await flushMicrotasks();
        assert.equal(opened, 0);
    });

    it('10-11. opened no se llama para stale ni tras backToCatalog', async () => {
        let opened = 0;
        let resolveSlow;
        const slow = new Promise(function (resolve) {
            resolveSlow = resolve;
        });

        const { mod } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'B',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                return slow;
            },
            markOpened: function () {
                opened += 1;
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        const pending = mod.openLesson('bienvenida');
        mod.backToCatalog();
        resolveSlow({
            success: true,
            data: { lesson: { title: 'Late' }, blocks: [] }
        });
        await pending;
        await flushMicrotasks();
        assert.equal(opened, 0);
    });

    it('12. opened una sola vez por apertura', async () => {
        let opened = 0;
        const { mod } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'B',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                return Promise.resolve({
                    success: true,
                    data: { lesson: { title: 'L' }, blocks: [] }
                });
            },
            markOpened: function () {
                opened += 1;
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        await mod.openLesson('bienvenida');
        await flushMicrotasks();
        assert.equal(opened, 1);
    });

    it('13. fallo de markOpened no oculta el contenido', async () => {
        const { mod, els } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'B',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        lesson: { title: 'Visible' },
                        blocks: [{ type: 'rich_text', html: '<p>ok</p>' }]
                    }
                });
            },
            markOpened: function () {
                return Promise.reject({ code: 'training_network_error' });
            }
        });

        await mod.loadCourse();
        await mod.openLesson('bienvenida');
        await flushMicrotasks();
        assert.equal(els['aa-training-lesson-title'].textContent, 'Visible');
        assert.equal(els['aa-training-lesson-content'].classList.contains('hidden'), false);
        assert.equal(els['aa-training-lesson-error'].classList.contains('hidden'), true);
    });

    it('14. opened/completed ya presentes omiten markOpened', async () => {
        let opened = 0;
        const { mod } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'bienvenida',
                                title: 'B',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: true, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                return Promise.resolve({
                    success: true,
                    data: { lesson: { title: 'L' }, blocks: [] }
                });
            },
            markOpened: function () {
                opened += 1;
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        await mod.openLesson('bienvenida');
        await flushMicrotasks();
        assert.equal(opened, 0);
    });

    it('15-17. footer completed estático; pendiente muestra CTA; sin flow vacío', async () => {
        const { mod, els } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'done',
                                title: 'Done',
                                position: 1,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: true, completed: true }
                            },
                            {
                                key: 'pending',
                                title: 'Pending',
                                position: 2,
                                availability: 'available',
                                access_state: 'available',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function (key) {
                if (key === 'done') {
                    return Promise.resolve({
                        success: true,
                        data: {
                            lesson: { title: 'Done' },
                            blocks: [],
                            completion_flow: { trigger_label: 'Completar lección' }
                        }
                    });
                }
                return Promise.resolve({
                    success: true,
                    data: {
                        lesson: { title: 'Pending' },
                        blocks: [],
                        completion_flow: { trigger_label: 'Completar lección' }
                    }
                });
            },
            markOpened: function () {
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        await mod.openLesson('done');
        assert.equal(els['aa-training-lesson-completion'].classList.contains('hidden'), false);
        assert.equal(
            els['aa-training-lesson-completion'].children[0].textContent,
            'Lección completada'
        );

        await mod.openLesson('pending');
        assert.equal(els['aa-training-lesson-completion'].classList.contains('hidden'), false);
        assert.equal(
            els['aa-training-lesson-completion'].children[0].textContent,
            'Completar lección'
        );
        assert.equal(
            els['aa-training-lesson-completion'].children[0].getAttribute('data-aa-training-completion-cta'),
            '1'
        );

        mod._setCachedManifestForTests({
            course: { title: 'C' },
            lessons: [
                {
                    key: 'nofloat',
                    title: 'No flow',
                    position: 1,
                    availability: 'available',
                    access_state: 'available',
                    progress: { opened: false, completed: false }
                }
            ]
        });
        globalThis.TrainingService.getLesson = function () {
            return Promise.resolve({
                success: true,
                data: { lesson: { title: 'No flow' }, blocks: [] }
            });
        };
        await mod.openLesson('nofloat');
        assert.equal(els['aa-training-lesson-completion'].classList.contains('hidden'), true);
    });

    it('no usa markCompleted ni overlay paralelo en el módulo', () => {
        assert.doesNotMatch(moduleSrc, /markCompleted/);
        assert.doesNotMatch(moduleSrc, /aa-modal-overlay/);
        assert.match(moduleSrc, /TrainingCompletionModal/);
    });

    it('locked no abre getLesson ni markOpened', async () => {
        let lessons = 0;
        let opened = 0;
        const { mod } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: '' },
                        lessons: [
                            {
                                key: 'siguiente',
                                title: 'S',
                                position: 1,
                                availability: 'available',
                                access_state: 'locked',
                                progress: { opened: false, completed: false }
                            }
                        ]
                    }
                });
            },
            getLesson: function () {
                lessons += 1;
                return Promise.resolve({ success: true, data: { lesson: {}, blocks: [] } });
            },
            markOpened: function () {
                opened += 1;
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        await mod.openLesson('siguiente');
        assert.equal(lessons, 0);
        assert.equal(opened, 0);
    });
});
