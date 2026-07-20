'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it, beforeEach, afterEach } = require('node:test');

const uxPath = path.join(__dirname, '../../assets/js/services/trainingPortalUx.js');
const ux = require(uxPath);

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/training/module.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const indexSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/training/index.php'),
    'utf8'
);
const accountIndexSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/account/index.php'),
    'utf8'
);
const routerSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/index.php'),
    'utf8'
);

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
    const els = {
        'aa-training-shell-root': el('aa-training-shell-root'),
        'aa-training-shell-loading': el('aa-training-shell-loading'),
        'aa-training-shell-loading-message': el('aa-training-shell-loading-message'),
        'aa-training-shell-error': el('aa-training-shell-error', { classList: el('x').classList }),
        'aa-training-shell-error-message': el('aa-training-shell-error-message'),
        'aa-training-shell-error-actions': el('aa-training-shell-error-actions'),
        'aa-training-catalog-root': el('aa-training-catalog-root'),
        'aa-training-catalog-title': el('aa-training-catalog-title'),
        'aa-training-catalog-description': el('aa-training-catalog-description'),
        'aa-training-catalog-lessons': el('aa-training-catalog-lessons'),
        'aa-training-lesson-root': el('aa-training-lesson-root'),
        'aa-training-lesson-back': el('aa-training-lesson-back'),
        'aa-training-lesson-loading': el('aa-training-lesson-loading'),
        'aa-training-lesson-error': el('aa-training-lesson-error'),
        'aa-training-lesson-error-message': el('aa-training-lesson-error-message'),
        'aa-training-lesson-error-actions': el('aa-training-lesson-error-actions'),
        'aa-training-lesson-content': el('aa-training-lesson-content'),
        'aa-training-lesson-title': el('aa-training-lesson-title'),
        'aa-training-lesson-blocks': el('aa-training-lesson-blocks')
    };

    // Ensure each has independent classList
    Object.keys(els).forEach(function (id) {
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
        accountModuleUrl: 'https://agenda.test/wp-admin/admin-post.php?action=aa_iframe_content&module=account',
        trainingModuleUrl: 'https://agenda.test/wp-admin/admin-post.php?action=aa_iframe_content&module=training',
        actions: {}
    };

    delete require.cache[modulePath];
    const mod = require(modulePath);
    return { mod: mod, els: els };
}

describe('TrainingPortalUx', () => {
    it('ordena lecciones por position', () => {
        const sorted = ux.sortLessonsByPosition([
            { key: 'b', position: 2, title: 'B', availability: 'upcoming' },
            { key: 'a', position: 1, title: 'A', availability: 'available' }
        ]);
        assert.equal(sorted[0].key, 'a');
        assert.equal(sorted[1].key, 'b');
    });

    it('mapCatalogError copy + retry', () => {
        const e = ux.mapCatalogError();
        assert.equal(e.text, 'No pudimos cargar el curso.');
        assert.equal(e.retry, true);
        assert.doesNotMatch(e.text, /training_/);
    });

    it('mapLessonError estados', () => {
        assert.equal(
            ux.mapLessonError({ code: 'training_content_lesson_unavailable' }).text,
            'Esta lección estará disponible pronto.'
        );
        assert.equal(
            ux.mapLessonError({ code: 'training_content_lesson_not_found' }).text,
            'No encontramos esta lección.'
        );
        const noAccess = ux.mapLessonError({ code: 'training_enrollment_not_active' });
        assert.equal(noAccess.text, 'Tu acceso al curso ya no está disponible.');
        assert.equal(noAccess.showAccountLink, true);
        assert.equal(
            ux.mapLessonError({ code: 'training_content_render_failed' }).text,
            'No pudimos mostrar el contenido.'
        );
        assert.equal(ux.mapLessonError({ code: 'training_network_error' }).text, 'No pudimos conectarnos.');
        assert.equal(ux.mapLessonError({ kind: 'network' }).text, 'No pudimos conectarnos.');
    });

    it('isRenderableBlock solo rich_text y exercise', () => {
        assert.equal(ux.isRenderableBlock({ type: 'rich_text' }), true);
        assert.equal(ux.isRenderableBlock({ type: 'exercise' }), true);
        assert.equal(ux.isRenderableBlock({ type: 'video' }), false);
        assert.equal(ux.isRenderableBlock(null), false);
    });
});

describe('Training module C8A3 portal', () => {
    afterEach(() => {
        delete globalThis.document;
        delete globalThis.TrainingService;
        delete globalThis.TrainingPortalUx;
        delete globalThis.TrainingModule;
        delete globalThis.AA_TRAINING_DATA;
        delete require.cache[modulePath];
    });

    it('carga inicial llama getCourse y renderiza catálogo', async () => {
        const calls = [];
        const { mod, els } = loadModule({
            getCourse: function () {
                calls.push('getCourse');
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { key: 'fundamentos-deoia', title: 'Curso T', description: 'Desc' },
                        lessons: [
                            { key: 'planeacion', title: 'Planeación', position: 2, availability: 'upcoming' },
                            { key: 'bienvenida', title: 'Bienvenida', position: 1, availability: 'available' }
                        ]
                    }
                });
            },
            getLesson: function () {
                calls.push('getLesson');
                return Promise.resolve({ success: true, data: {} });
            }
        });

        await mod.loadCourse();
        assert.deepEqual(calls, ['getCourse']);
        assert.equal(els['aa-training-catalog-title'].textContent, 'Curso T');
        assert.equal(els['aa-training-catalog-description'].textContent, 'Desc');
        assert.equal(els['aa-training-catalog-lessons'].children.length, 2);
        const firstRow = els['aa-training-catalog-lessons'].children[0].children[0];
        const firstTitle = firstRow.children[0].children[0];
        assert.equal(firstTitle.textContent, 'Bienvenida');
        const openBtn = firstRow.children[1];
        assert.equal(openBtn.textContent, 'Abrir');
        assert.equal(openBtn.getAttribute('data-aa-training-open-lesson'), 'bienvenida');
        const secondRow = els['aa-training-catalog-lessons'].children[1].children[0];
        assert.equal(secondRow.children[1].textContent, 'Próximamente');
    });

    it('upcoming no solicita lección; available abre getLesson', async () => {
        const lessonCalls = [];
        const { mod } = loadModule({
            getCourse: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: 'D' },
                        lessons: [
                            { key: 'bienvenida', title: 'Bienvenida', position: 1, availability: 'available' },
                            { key: 'planeacion', title: 'Planeación', position: 2, availability: 'upcoming' }
                        ]
                    }
                });
            },
            getLesson: function (key) {
                lessonCalls.push(key);
                return Promise.resolve({
                    success: true,
                    data: {
                        lesson: { key: key, title: 'Bienvenida lección' },
                        blocks: [
                            { type: 'rich_text', html: '<p>Hola</p>' },
                            { type: 'exercise', title: 'Ej', instructions: 'Hazlo' },
                            { type: 'video', url: 'x' }
                        ]
                    }
                });
            }
        });

        await mod.loadCourse();
        assert.deepEqual(lessonCalls, []);
        await mod.openLesson('bienvenida');
        assert.deepEqual(lessonCalls, ['bienvenida']);
    });

    it('render rich_text usa innerHTML; exercise usa textContent; ignora desconocidos', async () => {
        const { mod, els } = loadModule({
            getCourse: function () {
                return Promise.resolve({ success: true, data: { course: {}, lessons: [] } });
            },
            getLesson: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        lesson: { title: 'L1' },
                        blocks: [
                            { type: 'rich_text', html: '<p>OK</p>' },
                            { type: 'exercise', title: 'Ej <b>x</b>', instructions: 'Instr <script>' },
                            { type: 'quiz', q: 1 }
                        ]
                    }
                });
            }
        });

        await mod.openLesson('bienvenida');
        assert.equal(els['aa-training-lesson-title'].textContent, 'L1');
        const blocks = els['aa-training-lesson-blocks'].children;
        assert.equal(blocks.length, 2);
        assert.equal(blocks[0].innerHTML, '<p>OK</p>');
        assert.equal(blocks[1].children[0].textContent, 'Ej <b>x</b>');
        assert.equal(blocks[1].children[1].textContent, 'Instr <script>');
    });

    it('volver al catálogo no vuelve a pedir manifiesto', async () => {
        let courseCalls = 0;
        const { mod } = loadModule({
            getCourse: function () {
                courseCalls += 1;
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'C', description: 'D' },
                        lessons: [{ key: 'bienvenida', title: 'B', position: 1, availability: 'available' }]
                    }
                });
            },
            getLesson: function () {
                return Promise.resolve({
                    success: true,
                    data: { lesson: { title: 'L' }, blocks: [] }
                });
            }
        });

        await mod.loadCourse();
        assert.equal(courseCalls, 1);
        await mod.openLesson('bienvenida');
        mod.backToCatalog();
        assert.equal(courseCalls, 1);
        assert.ok(mod.getCachedManifest());
    });

    it('error de catálogo permite retry copy', async () => {
        const { mod, els } = loadModule({
            getCourse: function () {
                return Promise.reject({ code: 'training_network_error', kind: 'network' });
            },
            getLesson: function () {
                return Promise.resolve({ success: true, data: {} });
            }
        });
        await mod.loadCourse();
        assert.equal(els['aa-training-shell-error-message'].textContent, 'No pudimos cargar el curso.');
        assert.equal(els['aa-training-shell-error-actions'].children[0].textContent, 'Reintentar');
    });

    it('ignora respuesta obsoleta de curso', async () => {
        let resolveFirst;
        const first = new Promise(function (resolve) {
            resolveFirst = resolve;
        });
        let call = 0;
        const { mod, els } = loadModule({
            getCourse: function () {
                call += 1;
                if (call === 1) {
                    return first;
                }
                return Promise.resolve({
                    success: true,
                    data: {
                        course: { title: 'Segundo', description: '' },
                        lessons: []
                    }
                });
            },
            getLesson: function () {
                return Promise.resolve({ success: true, data: {} });
            }
        });

        const p1 = mod.loadCourse();
        const p2 = mod.loadCourse();
        resolveFirst({
            success: true,
            data: { course: { title: 'Primero', description: '' }, lessons: [] }
        });
        await Promise.all([p1, p2]);
        assert.equal(els['aa-training-catalog-title'].textContent, 'Segundo');
    });

    it('loading inicial usa copy Cargando el curso', () => {
        assert.match(indexSrc, /Cargando el curso…/);
        assert.match(indexSrc, /Cargando la lección…/);
        assert.match(moduleSrc, /getCourse\(/);
        assert.match(moduleSrc, /getLesson\(/);
    });
});

describe('C8A2 navigation intacta', () => {
    it('trainingModuleUrl usa wp_json_encode y &module=training', () => {
        assert.match(indexSrc, /trainingModuleUrl:\s*<\?php echo wp_json_encode\(/);
        assert.match(indexSrc, /&module=training/);
        assert.doesNotMatch(indexSrc, /trainingModuleUrl:[^\n]*esc_js/);
        assert.doesNotMatch(indexSrc, /trainingModuleUrl:[^\n]*&amp;/);
    });

    it('Volver a Cuenta y allowlist intactos; Cuenta no tocada en C8A3', () => {
        assert.match(indexSrc, /module=account/);
        assert.match(indexSrc, /Volver a Cuenta/);
        assert.match(routerSrc, /'training'/);
        assert.match(accountIndexSrc, /aa-training-card-root/);
        assert.match(accountIndexSrc, /trainingModuleUrl:\s*<\?php echo wp_json_encode\(/);
    });
});
