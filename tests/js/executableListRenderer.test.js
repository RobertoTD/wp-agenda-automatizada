'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
const adminSourceCssPath = path.join(__dirname, '../../includes/admin/ui/assets/css/admin.source.css');
const renderer = require(rendererPath);

function formatDueAtFromDate(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    var h = String(date.getHours()).padStart(2, '0');
    var min = String(date.getMinutes()).padStart(2, '0');
    var s = String(date.getSeconds()).padStart(2, '0');

    return y + '-' + m + '-' + d + ' ' + h + ':' + min + ':' + s;
}

function dueAtInHoursFromNow(hours) {
    return formatDueAtFromDate(new Date(Date.now() + (hours * 3600000)));
}

function baseItem(overrides) {
    return Object.assign({
        id: 'item-1',
        source: 'system',
        origin_key: 'configure_services',
        title: 'Configura servicios',
        description: 'Define servicios.',
        importance: 0,
        due_at: null,
        status: 'pending',
        state: {
            completed: false,
            ignored: false,
            dismissed: false,
            dismiss_active: false,
            auto_completed: false
        },
        capabilities: {
            can_complete: false,
            can_reopen: false,
            can_defer: false,
            can_dismiss: false,
            can_reactivate: false
        },
        primary_action: null,
        visible_actions: [],
        is_executive_candidate: false,
        is_overdue: false
    }, overrides || {});
}

function visibleAction(overrides) {
    return Object.assign({
        key: 'navigate',
        type: 'navigate',
        category: 'mechanical',
        label: 'Ir',
        placement: 'primary',
        target_status: null,
        url: 'https://example.test/admin-post.php?module=assignments',
        handler: null
    }, overrides || {});
}

function baseList(overrides) {
    return Object.assign({
        id: 'system:learning.recommendations',
        source: 'system',
        source_category: 'agenda_app',
        source_label: 'Agenda app',
        origin_key: 'learning.recommendations',
        title: 'Activación de tu agenda',
        description: 'Sugerencias del sistema.',
        importance: 0,
        position: 0,
        status: 'active',
        capabilities: { can_archive: false },
        buckets: []
    }, overrides || {});
}

function extractTopUl(html) {
    var match = html.match(/<ul class="aa-executable-bucket-items aa-executable-bucket-items-top[\s\S]*?<\/ul>/);

    return match ? match[0] : '';
}

function extractFollowingContent(html) {
    var openTag = '<div class="aa-executable-following-tasks-content';
    var start = html.indexOf(openTag);

    if (start === -1) {
        return '';
    }

    var depth = 0;
    var index = start;

    while (index < html.length) {
        if (html.slice(index, index + 4) === '<div') {
            depth += 1;
        }

        if (html.slice(index, index + 6) === '</div>') {
            depth -= 1;

            if (depth === 0) {
                return html.slice(start, index + 6);
            }
        }

        index += 1;
    }

    return '';
}

describe('AAExecutableListRenderer', () => {
    it('exporta API usable en Node', () => {
        assert.equal(typeof renderer.renderFeed, 'function');
        assert.equal(typeof renderer.renderList, 'function');
        assert.equal(typeof renderer.renderBucket, 'function');
        assert.equal(typeof renderer.renderItem, 'function');
        assert.equal(typeof renderer.escapeHtml, 'function');
    });

    it('renderiza lista vacía o bucket vacío sin romper', () => {
        assert.equal(renderer.renderFeed([]), '');
        assert.equal(renderer.renderList(null), '');
        assert.equal(renderer.renderBucket(null), '');
        assert.equal(renderer.renderItem(null), '');

        var emptyList = baseList({
            buckets: [{ key: 'default', label: '', items: [] }]
        });
        var html = renderer.renderList(emptyList);

        assert.match(html, /aa-executable-list-card/);
        assert.doesNotMatch(html, /aa-executable-item/);
    });

    it('renderiza bucket secondary con label y primary sin label Principales', () => {
        var list = baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [baseItem({ id: 'a', origin_key: 'a' })]
                },
                {
                    key: 'secondary',
                    label: 'Secundarias',
                    items: [baseItem({ id: 'b', origin_key: 'b' })]
                }
            ]
        });

        var html = renderer.renderFeed([list]);

        assert.doesNotMatch(html, />Principales</);
        assert.match(html, />Secundarias</);
        assert.match(html, /data-bucket-key="primary"/);
        assert.match(html, /data-bucket-key="secondary"/);
    });

    it('renderiza bucket default sin label ruidoso', () => {
        var list = baseList({
            id: '1',
            source: 'user',
            title: 'Mi lista',
            buckets: [
                {
                    key: 'default',
                    label: '',
                    items: [baseItem({
                        id: '10',
                        source: 'user',
                        origin_key: null,
                        title: 'Tarea'
                    })]
                }
            ]
        });

        var html = renderer.renderList(list);

        assert.match(html, /data-bucket-key="default"/);
        assert.doesNotMatch(html, /aa-executable-bucket-label-wrap/);
    });

    it('escapa HTML en title y description', () => {
        var item = baseItem({
            title: '<script>alert(1)</script>',
            description: '"><img onerror=1>'
        });

        var html = renderer.renderItem(item);

        assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
        assert.match(html, /&quot;&gt;&lt;img onerror=1&gt;/);
        assert.doesNotMatch(html, /<script>/);
    });

    it('navigate genera link', () => {
        var item = baseItem({
            primary_action: {
                type: 'navigate',
                label: 'Ir',
                url: 'https://example.test/admin-post.php?module=assignments'
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /<a href="https:\/\/example\.test\/admin-post\.php\?module=assignments"/);
        assert.match(html, />Ir</);
    });

    it('handler genera data-learning-action primary-handler y recommendation-key', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            primary_action: {
                type: 'handler',
                label: 'Instalar',
                handler: 'pwa.install'
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-learning-action="primary-handler"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.match(html, /data-learning-handler="pwa\.install"/);
    });

    it('status to done genera data-tasks-action complete', () => {
        var item = baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            primary_action: {
                type: 'status',
                label: 'Completar',
                to: 'done'
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-task-id="42"/);
    });

    it('status to pending genera data-tasks-action pending', () => {
        var item = baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            status: 'done',
            primary_action: {
                type: 'status',
                label: 'Reabrir',
                to: 'pending'
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-tasks-action="pending"/);
        assert.match(html, /data-task-id="42"/);
    });

    it('[dormido] fallback legacy: can_defer sin visible_actions genera defer', () => {
        var item = baseItem({
            origin_key: 'configure_services',
            capabilities: {
                can_defer: true
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-learning-action="defer"/);
        assert.match(html, /data-recommendation-key="configure_services"/);
        assert.match(html, />Ahora no</);
    });

    it('can_dismiss genera data-learning-action dismiss', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            capabilities: {
                can_dismiss: true
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-learning-action="dismiss"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.match(html, />Ahora no</);
    });

    it('can_reactivate no genera botón Reactivar por defecto', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            capabilities: {
                can_reactivate: true
            }
        });

        var html = renderer.renderItem(item);

        assert.doesNotMatch(html, /data-learning-action="reactivate"/);
        assert.doesNotMatch(html, />Reactivar</);
    });

    it('showReactivate true permite renderizar Reactivar explícitamente', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            capabilities: {
                can_reactivate: true
            }
        });

        var html = renderer.renderItem(item, { showReactivate: true });

        assert.match(html, /data-learning-action="reactivate"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.match(html, />Reactivar</);
    });

    it('showReactivate no afecta Ahora no en fallback legacy', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            capabilities: {
                can_dismiss: true,
                can_reactivate: true
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-learning-action="dismiss"/);
        assert.match(html, />Ahora no</);
        assert.doesNotMatch(html, /data-learning-action="reactivate"/);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
    });

    it('can_archive renderiza menú ⋮ con Archivar lista y sin botón suelto', () => {
        var list = baseList({
            id: '7',
            source: 'user',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /data-aa-list-options-trigger="1"/);
        assert.match(html, /aa-executable-list-options-menu/);
        assert.match(html, /role="menuitem"[\s\S]*data-tasks-action="archive-list"/);
        assert.match(html, />Archivar lista</);
        assert.match(html, /data-list-id="7"/);
        assert.doesNotMatch(html, />Archivar</);
        assert.doesNotMatch(html, /data-aa-list-edit/);
    });

    it('can_edit sin can_archive renderiza menú con Editar lista', () => {
        var html = renderer.renderList(baseList({
            id: '21',
            source: 'user',
            title: 'Lista editable',
            description: 'Objetivo',
            importance: 3,
            capabilities: { can_edit: true, can_archive: false },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /data-aa-list-options-trigger="1"/);
        assert.match(html, /data-aa-list-edit="1"/);
        assert.match(html, />Editar lista</);
        assert.match(html, /data-list-id="21"/);
        assert.match(html, /data-list-title="Lista editable"/);
        assert.match(html, /data-list-description="Objetivo"/);
        assert.match(html, /data-list-importance="3"/);
        assert.doesNotMatch(html, /data-tasks-action="archive-list"/);
    });

    it('source=system muestra label sutil Agenda app', () => {
        var list = baseList({
            source: 'system',
            source_category: 'agenda_app',
            source_label: 'Agenda app',
            title: 'Activación de tu agenda',
            capabilities: { can_archive: false },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /aa-executable-list-source-label/);
        assert.match(html, />Agenda app</);
        assert.doesNotMatch(html, /aa-executable-list-source-badge/);
        assert.doesNotMatch(html, />Recomendado</);
        assert.doesNotMatch(html, /data-tasks-action="archive-list"/);
        assert.doesNotMatch(html, /data-aa-list-options-trigger/);
    });

    it('source=user muestra label sutil Mis listas', () => {
        var list = baseList({
            source: 'user',
            source_category: 'user',
            source_label: 'Mis listas',
            title: 'Lista de casa',
            capabilities: { can_archive: true },
            buckets: [{ key: 'primary', label: 'Principales', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /aa-executable-list-source-label/);
        assert.match(html, />Mis listas</);
        assert.doesNotMatch(html, /aa-executable-list-source-badge/);
        assert.match(html, /data-tasks-action="archive-list"/);
        assert.match(html, /data-aa-list-options-trigger="1"/);
    });

    it('MC13L renderiza lista como details colapsable cerrada por defecto', () => {
        var list = baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [baseItem({ id: 'cfg', origin_key: 'configure_services' })]
                }
            ]
        });

        var html = renderer.renderList(list);

        assert.match(html, /<details class="aa-executable-list-card/);
        assert.doesNotMatch(html, /<details[^>]*\sopen(?:=|>)/);
        assert.match(html, /<summary class="[^"]*cursor-pointer list-none/);
        assert.match(html, /aa-chevron/);
        assert.match(html, /aa-executable-list-body/);
        assert.doesNotMatch(html, />Principales</);
        assert.match(html, /aa-executable-item/);
    });

    it('MC13L menú de lista en summary usa stopPropagation en trigger y archivar', () => {
        var list = baseList({
            id: '7',
            source: 'user',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /data-aa-list-options-trigger="1"/);
        assert.match(html, /data-tasks-action="archive-list"/);
        assert.match(html, /aa-executable-list-options-trigger[\s\S]*onclick="event\.stopPropagation\(\)"/);
        assert.doesNotMatch(html, /class="[^"]*text-xs[^"]*"[^>]*>Archivar</);
    });

    it('MC13L system list no muestra Archivar y conserva source label', () => {
        var list = baseList({
            source: 'system',
            source_category: 'agenda_app',
            source_label: 'Agenda app',
            capabilities: { can_archive: false },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, />Agenda app</);
        assert.doesNotMatch(html, />Recomendado</);
        assert.doesNotMatch(html, /data-tasks-action="archive-list"/);
        assert.doesNotMatch(html, /data-aa-list-options-trigger/);
        assert.match(html, /<details/);
    });

    it('MC13L acciones item Learning y Tasks siguen en el body sin defer', () => {
        var list = baseList({
            source: 'user',
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [
                        baseItem({
                            id: '10',
                            source: 'user',
                            origin_key: null,
                            capabilities: { can_dismiss: true },
                            visible_actions: [
                                visibleAction({
                                    key: 'dismiss',
                                    type: 'intent',
                                    category: 'intent',
                                    label: 'Ahora no',
                                    placement: 'secondary',
                                    url: null,
                                    handler: null
                                })
                            ]
                        })
                    ]
                }
            ]
        });
        var learningList = baseList({
            source: 'system',
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [
                        baseItem({
                            capabilities: { can_defer: true },
                            visible_actions: [
                                visibleAction({
                                    key: 'navigate',
                                    type: 'navigate',
                                    label: 'Ir',
                                    url: 'https://example.test/admin-post.php?module=assignments'
                                })
                            ]
                        })
                    ]
                }
            ]
        });

        var userHtml = renderer.renderList(list);
        var learningHtml = renderer.renderList(learningList);

        assert.match(userHtml, /data-tasks-action="dismiss"/);
        assert.match(userHtml, /data-task-id="10"/);
        assert.match(learningHtml, /https:\/\/example\.test\/admin-post\.php\?module=assignments/);
        assert.doesNotMatch(userHtml, /data-tasks-action="defer"/);
        assert.doesNotMatch(learningHtml, /data-learning-action="defer"/);
        assert.match(userHtml, />Ahora no</);
        assert.doesNotMatch(learningHtml, />Ahora no</);
    });

    it('resolveSourceLabel prioriza list.source_label', () => {
        assert.equal(renderer.resolveSourceLabel({
            source: 'system',
            source_category: 'agenda_app',
            source_label: 'Etiqueta custom'
        }), 'Etiqueta custom');
    });

    it('resolveSourceLabel hace fallback por source si falta label', () => {
        assert.equal(renderer.resolveSourceLabel({
            source: 'ai',
            source_category: 'ai'
        }), 'IA');
        assert.equal(renderer.resolveSourceLabel({
            source: 'system'
        }), 'Agenda app');
    });

    it('source label queda en columna izquierda del header', () => {
        var list = baseList({
            source: 'user',
            source_category: 'user',
            source_label: 'Mis listas',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(
            html,
            /min-w-0 flex-1[\s\S]*<h4[^>]*>[\s\S]*aa-executable-list-source-label[\s\S]*Mis listas/
        );
        assert.match(html, /data-tasks-action="archive-list"/);
        assert.match(html, /aa-chevron/);
        assert.doesNotMatch(html, /Eliminar lista/);
    });

    it('lista user sin items visibles muestra mensaje de tareas pendientes', () => {
        var list = baseList({
            id: '9',
            source: 'user',
            title: 'Lista vacía pending',
            buckets: [{ key: 'default', label: '', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /No hay tareas pendientes en esta lista/);
        assert.doesNotMatch(html, /data-tasks-action="pending"/);
        assert.doesNotMatch(html, /Reabrir/);
    });

    it('lista system sin items no muestra mensaje de tareas pendientes user', () => {
        var list = baseList({
            id: 'system:learning.recommendations',
            source: 'system',
            buckets: [{ key: 'primary', label: 'Principales', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.doesNotMatch(html, /No hay tareas pendientes en esta lista/);
    });

    it('conserva comportamiento default cuando no recibe callbacks', () => {
        var list = baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [
                        baseItem({
                            id: 'install_pwa',
                            origin_key: 'install_pwa',
                            capabilities: {
                                can_defer: true,
                                can_dismiss: true
                            },
                            primary_action: {
                                type: 'handler',
                                label: 'Instalar',
                                handler: 'pwa.install'
                            }
                        })
                    ]
                }
            ]
        });

        assert.equal(renderer.renderFeed([list], {}), renderer.renderFeed([list]));
    });

    it('shouldRenderItem puede ocultar un item completo', () => {
        var list = baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [
                        baseItem({ id: 'visible', title: 'Visible' }),
                        baseItem({ id: 'hidden', title: 'Oculto' })
                    ]
                }
            ]
        });

        var html = renderer.renderFeed([list], {
            shouldRenderItem: function (item) {
                return item.id !== 'hidden';
            }
        });

        assert.match(html, /Visible/);
        assert.doesNotMatch(html, /Oculto/);
        assert.doesNotMatch(html, /data-item-id="hidden"/);
    });

    it('[dormido] shouldRenderPrimaryAction oculta handler y expone fallback legacy defer/dismiss', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            capabilities: {
                can_defer: true,
                can_dismiss: true
            },
            primary_action: {
                type: 'handler',
                label: 'Instalar',
                handler: 'pwa.install'
            }
        });

        var html = renderer.renderItem(item, {
            shouldRenderPrimaryAction: function (action) {
                return action.type !== 'handler';
            }
        });

        assert.doesNotMatch(html, /data-learning-action="primary-handler"/);
        assert.doesNotMatch(html, />Instalar</);
        assert.match(html, /data-learning-action="defer"/);
        assert.match(html, /data-learning-action="dismiss"/);
    });

    it('shouldRenderPrimaryAction puede ocultar una acción navigate', () => {
        var item = baseItem({
            primary_action: {
                type: 'navigate',
                label: 'Ir',
                url: 'https://example.test/admin-post.php?module=assignments'
            }
        });

        var html = renderer.renderItem(item, {
            shouldRenderPrimaryAction: function (action) {
                return action.type !== 'navigate';
            }
        });

        assert.doesNotMatch(html, /<a href="https:\/\/example\.test/);
        assert.doesNotMatch(html, />Ir</);
    });

    it('shouldRenderPrimaryAction puede ocultar una acción status', () => {
        var item = baseItem({
            id: '42',
            source: 'user',
            primary_action: {
                type: 'status',
                label: 'Completar',
                to: 'done'
            }
        });

        var html = renderer.renderItem(item, {
            shouldRenderPrimaryAction: function (action) {
                return action.type !== 'status';
            }
        });

        assert.doesNotMatch(html, /data-tasks-action="complete"/);
        assert.doesNotMatch(html, />Completar</);
    });

    it('callbacks reciben context con list, bucket, item e índices', () => {
        var capturedItemContext = null;
        var capturedActionContext = null;
        var item = baseItem({
            id: 'ctx-item',
            source: 'user',
            primary_action: {
                type: 'status',
                label: 'Completar',
                to: 'done'
            }
        });
        var bucket = { key: 'default', label: '', items: [item] };
        var list = baseList({
            id: 'ctx-list',
            source: 'user',
            buckets: [bucket]
        });

        renderer.renderFeed([list], {
            shouldRenderItem: function (_item, context) {
                capturedItemContext = context;
                return true;
            },
            shouldRenderPrimaryAction: function (_action, _item, context) {
                capturedActionContext = context;
                return true;
            }
        });

        assert.equal(capturedItemContext.list, list);
        assert.equal(capturedItemContext.bucket, bucket);
        assert.equal(capturedItemContext.item, item);
        assert.equal(capturedItemContext.source, 'user');
        assert.equal(capturedItemContext.listIndex, 0);
        assert.equal(capturedItemContext.bucketIndex, 0);
        assert.equal(capturedItemContext.itemIndex, 0);
        assert.deepEqual(capturedActionContext, capturedItemContext);
    });

    it('no muta el fixture original', () => {
        var list = baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [baseItem({
                        title: 'Original',
                        primary_action: {
                            type: 'navigate',
                            label: 'Ir',
                            url: 'https://example.test'
                        }
                    })]
                }
            ]
        });

        var snapshot = JSON.stringify(list);
        renderer.renderFeed([list]);

        assert.equal(JSON.stringify(list), snapshot);
    });
});

describe('AAExecutableListRenderer visible_actions', () => {
    it('prefiere visible_actions sobre primary_action y capabilities sin inferir defer', () => {
        var item = baseItem({
            capabilities: {
                can_defer: true,
                can_dismiss: true,
                can_complete: true
            },
            primary_action: {
                type: 'navigate',
                label: 'Legacy Ir',
                url: 'https://example.test/legacy'
            },
            visible_actions: [
                visibleAction({
                    key: 'navigate',
                    type: 'navigate',
                    label: 'Ir',
                    url: 'https://example.test/admin-post.php?module=assignments'
                })
            ]
        });

        var html = renderer.renderItem(item);

        assert.match(html, /https:\/\/example\.test\/admin-post\.php\?module=assignments/);
        assert.match(html, />Ir</);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
        assert.doesNotMatch(html, />Ahora no</);
        assert.doesNotMatch(html, /Legacy Ir/);
        assert.doesNotMatch(html, /https:\/\/example\.test\/legacy/);
        assert.doesNotMatch(html, /data-learning-action="dismiss"/);
        assert.doesNotMatch(html, /data-tasks-action="complete"/);
    });

    it('visible_action navigate genera link', () => {
        var html = renderer.renderItem(baseItem({
            visible_actions: [
                visibleAction({
                    key: 'navigate',
                    type: 'navigate',
                    label: 'Ir',
                    url: 'https://example.test/admin-post.php?module=assignments'
                })
            ]
        }));

        assert.match(html, /<a href="https:\/\/example\.test\/admin-post\.php\?module=assignments"/);
        assert.match(html, />Ir</);
    });

    it('visible_action handler genera primary-handler y learning-handler', () => {
        var html = renderer.renderItem(baseItem({
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'pwa.install',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Instalar',
                    placement: 'primary',
                    url: null,
                    handler: 'pwa.install'
                })
            ]
        }));

        assert.match(html, /data-learning-action="primary-handler"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.match(html, /data-learning-handler="pwa\.install"/);
    });

    it('visible_action appointment.confirm genera botón Confirmar cuando el filtro lo permite', () => {
        var html = renderer.renderItem(baseItem({
            origin_key: 'appointment_confirmation:42',
            visible_actions: [
                visibleAction({
                    key: 'appointment.confirm',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Confirmar',
                    placement: 'primary',
                    url: null,
                    handler: 'appointment.confirm'
                })
            ]
        }), {}, {
            shouldRenderPrimaryAction: function () {
                return true;
            }
        });

        assert.match(html, /data-learning-action="primary-handler"/);
        assert.match(html, /data-recommendation-key="appointment_confirmation:42"/);
        assert.match(html, /data-learning-handler="appointment\.confirm"/);
        assert.match(html, />Confirmar</);
    });

    it('visible_actions sin appointment.confirm no genera botón Confirmar', () => {
        var html = renderer.renderItem(baseItem({
            origin_key: 'appointment_confirmation:42',
            is_overdue: true,
            visible_actions: [
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    target_status: null,
                    url: null,
                    handler: null
                })
            ]
        }), {}, {
            shouldRenderPrimaryAction: function () {
                return true;
            }
        });

        assert.doesNotMatch(html, /data-learning-handler="appointment\.confirm"/);
        assert.doesNotMatch(html, />Confirmar</);
        assert.match(html, />Ahora no</);
    });

    it('visible_action status done genera complete en canal Tasks para user', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            visible_actions: [
                visibleAction({
                    key: 'complete',
                    type: 'status',
                    category: 'declarative',
                    label: 'Completar',
                    placement: 'secondary',
                    target_status: 'done',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-task-id="42"/);
        assert.doesNotMatch(html, /data-learning-action="complete"/);
    });

    it('visible_action status missed genera botón No realizada y conserva Ahora no (MC4)', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            is_overdue: true,
            visible_actions: [
                visibleAction({
                    key: 'complete',
                    type: 'status',
                    category: 'declarative',
                    label: 'Completar',
                    placement: 'secondary',
                    target_status: 'done',
                    url: null,
                    handler: null
                }),
                visibleAction({
                    key: 'missed',
                    type: 'status',
                    category: 'declarative',
                    label: 'No realizada',
                    placement: 'secondary',
                    target_status: 'missed',
                    url: null,
                    handler: null
                }),
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    target_status: null,
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="missed"/);
        assert.match(html, /data-task-id="42"/);
        assert.match(html, />No realizada</);
        assert.match(html, />Ahora no</);
        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-tasks-action="missed"[^>]*text-amber-700/);
    });

    it('visible_action status missed en confirmación vencida convive sin Confirmar (MC4)', () => {
        var html = renderer.renderItem(baseItem({
            id: '501',
            source: 'system',
            source_category: 'agenda_app',
            origin_key: 'appointment_confirmation:42',
            is_overdue: true,
            visible_actions: [
                visibleAction({
                    key: 'missed',
                    type: 'status',
                    category: 'declarative',
                    label: 'No realizada',
                    placement: 'secondary',
                    target_status: 'missed',
                    url: null,
                    handler: null
                }),
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    target_status: null,
                    url: null,
                    handler: null
                })
            ]
        }), {}, {
            shouldRenderPrimaryAction: function () {
                return true;
            }
        });

        assert.match(html, /data-tasks-action="missed"/);
        assert.match(html, /data-task-id="501"/);
        assert.match(html, />No realizada</);
        assert.match(html, />Ahora no</);
        assert.doesNotMatch(html, />Confirmar</);
    });

    it('visible_action status done genera complete en canal Learning para system', () => {
        var html = renderer.renderItem(baseItem({
            id: 'install_pwa',
            source: 'system',
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'complete',
                    type: 'status',
                    category: 'declarative',
                    label: 'Completar',
                    placement: 'secondary',
                    target_status: 'done',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-learning-action="complete"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.doesNotMatch(html, /data-tasks-action="complete"/);
        assert.doesNotMatch(html, /data-task-id="/);
    });

    it('visible_action status done genera complete en canal Tasks para agenda_app numerico', () => {
        var html = renderer.renderItem(baseItem({
            id: '501',
            source: 'system',
            source_category: 'agenda_app',
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'complete',
                    type: 'status',
                    category: 'declarative',
                    label: 'Completar',
                    placement: 'secondary',
                    target_status: 'done',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-task-id="501"/);
        assert.doesNotMatch(html, /data-learning-action="complete"/);
        assert.doesNotMatch(html, /data-recommendation-key=/);
    });

    it('visible_action status pending genera pending solo para user', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            status: 'done',
            visible_actions: [
                visibleAction({
                    key: 'reopen',
                    type: 'status',
                    category: 'recovery',
                    label: 'Reabrir',
                    placement: 'secondary',
                    target_status: 'pending',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="pending"/);
        assert.match(html, /data-task-id="42"/);
    });

    it('visible_action status pending no renderiza para system', () => {
        var html = renderer.renderItem(baseItem({
            id: 'install_pwa',
            source: 'system',
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'reopen',
                    type: 'status',
                    category: 'recovery',
                    label: 'Reabrir',
                    placement: 'secondary',
                    target_status: 'pending',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.doesNotMatch(html, /data-tasks-action="pending"/);
        assert.doesNotMatch(html, /data-learning-action="/);
        assert.doesNotMatch(html, />Reabrir</);
    });

    it('active view no muestra reactivate si no viene en visible_actions', () => {
        var html = renderer.renderItem(baseItem({
            capabilities: {
                can_reactivate: true
            },
            visible_actions: [
                visibleAction({
                    key: 'navigate',
                    type: 'navigate',
                    label: 'Ir',
                    url: 'https://example.test/admin-post.php?module=assignments'
                })
            ]
        }));

        assert.doesNotMatch(html, /data-learning-action="reactivate"/);
        assert.doesNotMatch(html, />Reactivar</);
    });

    it('[dormido] payload legacy manual: visible_action intent defer genera defer', () => {
        var html = renderer.renderItem(baseItem({
            origin_key: 'configure_services',
            visible_actions: [
                visibleAction({
                    key: 'defer',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-learning-action="defer"/);
        assert.match(html, /data-recommendation-key="configure_services"/);
        assert.match(html, />Ahora no</);
    });

    it('[dormido] payload legacy manual: intent defer user genera data-tasks-action defer', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            visible_actions: [
                visibleAction({
                    key: 'defer',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="defer"/);
        assert.match(html, /data-task-id="42"/);
        assert.match(html, />Ahora no</);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
        assert.doesNotMatch(html, /data-recommendation-key=/);
    });

    it('visible_action intent dismiss legacy Learning genera data-learning-action dismiss', () => {
        var html = renderer.renderItem(baseItem({
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-learning-action="dismiss"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.match(html, />Ahora no</);
        assert.doesNotMatch(html, /data-tasks-action="dismiss"/);
    });

    it('visible_action intent dismiss agenda_app DB común genera data-tasks-action dismiss', () => {
        var html = renderer.renderItem(baseItem({
            id: '500',
            source: 'system',
            source_category: 'agenda_app',
            origin_key: 'complete_business_data',
            visible_actions: [
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="dismiss"/);
        assert.match(html, /data-task-id="500"/);
        assert.match(html, />Ahora no</);
        assert.doesNotMatch(html, /data-learning-action="dismiss"/);
        assert.doesNotMatch(html, /data-recommendation-key=/);
    });

    it('visible_action intent dismiss user genera data-tasks-action dismiss', () => {
        var html = renderer.renderItem(baseItem({
            id: '43',
            source: 'user',
            origin_key: null,
            visible_actions: [
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="dismiss"/);
        assert.match(html, /data-task-id="43"/);
        assert.match(html, />Ahora no</);
        assert.doesNotMatch(html, /data-learning-action="dismiss"/);
        assert.doesNotMatch(html, /data-recommendation-key=/);
    });

    it('[dormido] fallback legacy sin visible_actions sigue renderizando defer', () => {
        var item = baseItem({
            capabilities: {
                can_defer: true
            },
            primary_action: {
                type: 'navigate',
                label: 'Ir',
                url: 'https://example.test/admin-post.php?module=assignments'
            }
        });

        delete item.visible_actions;

        var html = renderer.renderItem(item);

        assert.match(html, /https:\/\/example\.test\/admin-post\.php\?module=assignments/);
        assert.match(html, /data-learning-action="defer"/);
    });

    it('fallback legacy dismiss sin task id común sigue en canal Learning', () => {
        var item = baseItem({
            visible_actions: [],
            capabilities: {
                can_dismiss: true
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-learning-action="dismiss"/);
        assert.doesNotMatch(html, /data-tasks-action="dismiss"/);
    });

    it('fallback agenda_app DB común dismiss usa canal Tasks', () => {
        var item = baseItem({
            id: '501',
            source_category: 'agenda_app',
            origin_key: 'install_pwa',
            visible_actions: [],
            capabilities: {
                can_dismiss: true
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-tasks-action="dismiss"/);
        assert.match(html, /data-task-id="501"/);
        assert.doesNotMatch(html, /data-learning-action="dismiss"/);
    });

    it('resolveTasksChannelTaskId distingue user, agenda_app numérico y legacy', () => {
        assert.equal(renderer.resolveTasksChannelTaskId({ id: '10', source: 'user' }), '10');
        assert.equal(renderer.resolveTasksChannelTaskId({
            id: '500',
            source: 'system',
            source_category: 'agenda_app',
            origin_key: 'complete_business_data'
        }), '500');
        assert.equal(renderer.resolveTasksChannelTaskId({
            id: 'install_pwa',
            source: 'system',
            source_category: 'agenda_app',
            origin_key: 'install_pwa'
        }), '');
        assert.equal(renderer.resolveTasksChannelTaskId({
            id: 'item-1',
            source: 'system',
            origin_key: 'configure_services'
        }), '');
    });

    it('shouldRenderAction puede ocultar handler visible_action y conservar dismiss', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'pwa.install',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Instalar',
                    placement: 'primary',
                    url: null,
                    handler: 'pwa.install'
                }),
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        });

        var html = renderer.renderItem(item, {
            shouldRenderAction: function (action) {
                return action.type !== 'handler';
            }
        });

        assert.doesNotMatch(html, /data-learning-action="primary-handler"/);
        assert.doesNotMatch(html, />Instalar</);
        assert.match(html, /data-learning-action="dismiss"/);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
    });

    it('shouldRenderPrimaryAction sigue filtrando visible_action handler por compatibilidad', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            visible_actions: [
                visibleAction({
                    key: 'pwa.install',
                    type: 'handler',
                    category: 'mechanical',
                    label: 'Instalar',
                    placement: 'primary',
                    url: null,
                    handler: 'pwa.install'
                })
            ]
        });

        var html = renderer.renderItem(item, {
            shouldRenderPrimaryAction: function (action) {
                return action.type !== 'handler';
            }
        });

        assert.doesNotMatch(html, /data-learning-action="primary-handler"/);
    });

    it('escapa HTML en visible_actions label y url', () => {
        var html = renderer.renderItem(baseItem({
            visible_actions: [
                visibleAction({
                    label: '<script>alert(1)</script>',
                    url: 'https://example.test/?q="><img onerror=1>'
                })
            ]
        }));

        assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
        assert.match(html, /&quot;&gt;&lt;img onerror=1&gt;/);
        assert.doesNotMatch(html, /<script>/);
    });

    it('no muta fixture con visible_actions', () => {
        var item = baseItem({
            visible_actions: [
                visibleAction({ label: 'Ir' })
            ]
        });
        var snapshot = JSON.stringify(item);

        renderer.renderItem(item);

        assert.equal(JSON.stringify(item), snapshot);
    });
});

describe('executableListRenderer MC13 expandable items', () => {
    function extractSummary(html) {
        var match = html.match(/<summary[^>]*>[\s\S]*?<\/summary>/);

        return match ? match[0] : '';
    }

    it('renderiza item como details colapsable cerrado por defecto', () => {
        var html = renderer.renderItem(baseItem({
            description: 'Detalle de la tarea'
        }));

        assert.match(html, /<details class="aa-executable-item/);
        assert.doesNotMatch(html, /<details[^>]*\sopen(?:=|>)/);
        assert.match(html, /<summary class="[^"]*aa-executable-item-summary/);
        assert.match(html, /aa-executable-item-expanded/);
    });

    it('summary colapsado muestra título y preview sin acciones ni meta', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            description: 'Descripción larga de la tarea para preview',
            due_at: '2026-06-20 08:37:00',
            importance: 5,
            visible_actions: [
                visibleAction({
                    key: 'complete',
                    type: 'status',
                    category: 'declarative',
                    label: 'Completar',
                    placement: 'secondary',
                    target_status: 'done'
                })
            ]
        }));
        var summary = extractSummary(html);

        assert.match(summary, />Configura servicios</);
        assert.match(summary, /aa-executable-item-desc-preview/);
        assert.doesNotMatch(summary, /aa-executable-item-actions/);
        assert.doesNotMatch(summary, /Vence:/);
        assert.doesNotMatch(summary, /Importancia:/);
        assert.match(summary, /aa-executable-item-chevron/);
        assert.match(summary, /aa-executable-item-summary-actions/);
        assert.doesNotMatch(summary, /data-aa-task-edit/);
        assert.doesNotMatch(summary, /data-tasks-action="complete"/);
    });

    it('summary muestra badge Vencida cuando is_overdue es true', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Tarea vencida',
            is_overdue: true,
            due_at: '2026-06-01 08:00:00'
        }));
        var summary = extractSummary(html);

        assert.match(summary, />Vencida</);
        assert.match(summary, /border-red-200/);
        assert.match(summary, /text-red-700/);
        assert.match(summary, />Tarea vencida</);
    });

    it('summary no muestra badge Vencida cuando is_overdue es false', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Tarea al día',
            is_overdue: false,
            due_at: '2026-06-20 08:00:00'
        }));
        var summary = extractSummary(html);

        assert.doesNotMatch(summary, />Vencida</);
        assert.doesNotMatch(summary, /border-red-200/);
    });

    it('summary no muestra badge Vencida en item done aunque is_overdue sea true', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Tarea completada',
            status: 'done',
            is_overdue: true,
            due_at: '2026-06-01 08:00:00'
        }));
        var summary = extractSummary(html);

        assert.doesNotMatch(summary, />Vencida</);
    });

    it('summary muestra badge Vence pronto cuando due_at vence dentro de 24h', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Tarea pronto',
            is_overdue: false,
            due_at: dueAtInHoursFromNow(12)
        }));
        var summary = extractSummary(html);

        assert.match(summary, />Vence pronto</);
        assert.match(summary, /border-amber-200/);
        assert.match(summary, /text-amber-700/);
        assert.doesNotMatch(summary, />Vencida</);
    });

    it('summary no muestra Vence pronto cuando due_at vence después de 24h', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Tarea lejana',
            is_overdue: false,
            due_at: dueAtInHoursFromNow(30)
        }));
        var summary = extractSummary(html);

        assert.doesNotMatch(summary, />Vence pronto</);
        assert.doesNotMatch(summary, />Vencida</);
    });

    it('summary no muestra badge de vencimiento sin due_at', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Sin vencimiento',
            is_overdue: false,
            due_at: null
        }));
        var summary = extractSummary(html);

        assert.doesNotMatch(summary, />Vence pronto</);
        assert.doesNotMatch(summary, />Vencida</);
    });

    it('summary prioriza Vencida sobre Vence pronto si is_overdue es true', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            title: 'Tarea vencida pronto',
            is_overdue: true,
            due_at: dueAtInHoursFromNow(6)
        }));
        var summary = extractSummary(html);

        assert.match(summary, />Vencida</);
        assert.doesNotMatch(summary, />Vence pronto</);
    });

    it('contenido expandido incluye descripción completa, meta y acciones sin editar ni chevron', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            origin_key: null,
            description: 'Detalles completos de la tarea',
            due_at: '2026-06-20 08:37:00',
            importance: 3,
            visible_actions: [
                visibleAction({
                    key: 'complete',
                    type: 'status',
                    category: 'declarative',
                    label: 'Completar',
                    placement: 'secondary',
                    target_status: 'done'
                }),
                visibleAction({
                    key: 'dismiss',
                    type: 'intent',
                    category: 'intent',
                    label: 'Ahora no',
                    placement: 'secondary'
                })
            ]
        }));

        assert.match(html, /aa-executable-item-desc-full/);
        assert.match(html, />Detalles completos de la tarea</);
        assert.match(html, /Vence: 2026-06-20 08:37:00/);
        assert.match(html, /Importancia: 3/);
        assert.doesNotMatch(html, /aa-executable-item-expanded[\s\S]*>Vencida</);
        assert.match(html, /aa-executable-item-actions/);
        assert.match(html, /onclick="event\.stopPropagation\(\)"/);
        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-tasks-action="dismiss"/);
        assert.doesNotMatch(html, /aa-executable-item-expanded-footer/);
        assert.doesNotMatch(html, /aa-executable-item-expanded[\s\S]*aa-executable-item-chevron/);
    });

    it('contenido expandido muestra Realizar a partir de diferenciado de Vence', () => {
        var html = renderer.renderItem(baseItem({
            id: '42',
            source: 'user',
            execution_available_at: '2026-06-18 14:30:00',
            due_at: '2026-06-20 08:37:00'
        }));

        assert.match(html, /text-slate-600[\s\S]*Realizar a partir de: 2026-06-18 14:30:00/);
        assert.match(html, /text-gray-500[\s\S]*Vence: 2026-06-20 08:37:00/);

        var realizarPos = html.indexOf('Realizar a partir de:');
        var vencePos = html.indexOf('Vence:');

        assert.notEqual(realizarPos, -1);
        assert.ok(realizarPos < vencePos);
    });

    it('menú editar incluye data-task-execution-available-at para prefill', () => {
        var html = renderer.renderItem(baseItem({
            id: '99',
            source: 'user',
            execution_available_at: '2026-06-18 14:30:00',
            due_at: '2026-06-20 08:37:00',
            capabilities: {
                can_edit: true
            }
        }));
        var summary = extractSummary(html);

        assert.match(summary, /data-task-execution-available-at="2026-06-18 14:30:00"/);
        assert.match(summary, /data-task-due-at="2026-06-20 08:37:00"/);
    });

    it('tarea user con can_edit y can_archive renderiza menú ⋮ en summary', () => {
        var html = renderer.renderItem(baseItem({
            id: '99',
            source: 'user',
            description: 'Notas de la tarea',
            due_at: '2026-06-21 10:00:00',
            importance: 2,
            default_bucket: 'secondary',
            capabilities: {
                can_complete: true,
                can_edit: true,
                can_archive: true,
                can_delete: true
            }
        }));
        var summary = extractSummary(html);

        assert.match(summary, /aa-executable-item-chevron/);
        assert.match(summary, /aa-executable-item-summary-actions/);
        assert.match(summary, /data-aa-task-options-trigger/);
        assert.match(summary, /aa-executable-task-options-menu/);
        assert.match(summary, />Editar tarea</);
        assert.match(summary, />Archivar tarea</);
        assert.match(summary, />Eliminar tarea</);
        assert.match(summary, /data-aa-task-edit="1"/);
        assert.match(summary, /data-task-id="99"/);
        assert.match(summary, /data-task-default-bucket="secondary"/);
        assert.match(summary, /data-tasks-action="archive-task"/);
        assert.match(summary, /data-tasks-action="delete-task"/);
        assert.match(summary, /text-red-600/);
        assert.match(summary, /aria-label="Opciones de tarea"/);
        assert.doesNotMatch(summary, /aa-executable-item-summary-edit/);
        assert.doesNotMatch(html, /aa-executable-item-expanded-footer/);
    });

    it('tarea user con can_delete renderiza Eliminar tarea en menú', () => {
        var html = renderer.renderItem(baseItem({
            id: '77',
            source: 'user',
            capabilities: {
                can_delete: true
            }
        }));
        var summary = extractSummary(html);

        assert.match(summary, />Eliminar tarea</);
        assert.match(summary, /data-tasks-action="delete-task"/);
        assert.match(summary, /data-task-id="77"/);
        assert.match(summary, /text-red-600/);
    });

    it('agenda_app no muestra Eliminar tarea aunque can_delete sea false explícito', () => {
        var html = renderer.renderItem(baseItem({
            source: 'agenda_app',
            capabilities: {
                can_edit: false,
                can_archive: false,
                can_delete: false
            }
        }));

        assert.doesNotMatch(html, />Eliminar tarea</);
        assert.doesNotMatch(html, /data-tasks-action="delete-task"/);
    });

    it('no muestra menú de tarea si can_edit, can_archive y can_delete son false o ausentes', () => {
        var withoutFlag = renderer.renderItem(baseItem({
            source: 'user',
            description: 'Solo lectura'
        }));
        var explicitFalse = renderer.renderItem(baseItem({
            source: 'user',
            description: 'Solo lectura',
            capabilities: {
                can_edit: false,
                can_archive: false,
                can_delete: false
            }
        }));
        var agendaApp = renderer.renderItem(baseItem({
            source: 'agenda_app',
            description: 'Sistema',
            capabilities: {
                can_edit: false,
                can_archive: false
            }
        }));

        assert.doesNotMatch(withoutFlag, /data-aa-task-options-trigger/);
        assert.doesNotMatch(explicitFalse, /data-aa-task-options-trigger/);
        assert.doesNotMatch(agendaApp, /data-aa-task-options-trigger/);
        assert.doesNotMatch(withoutFlag, /data-aa-task-edit/);
        assert.doesNotMatch(explicitFalse, /data-aa-task-edit/);
    });

    it('Eliminar tarea vive en menú summary y no en panel expandido', () => {
        var html = renderer.renderItem(baseItem({
            source: 'user',
            description: 'Editable',
            capabilities: {
                can_edit: true,
                can_delete: true
            }
        }));
        var summary = extractSummary(html);
        var expanded = html.replace(summary, '');

        assert.match(summary, />Eliminar tarea</);
        assert.match(summary, /data-tasks-action="delete-task"/);
        assert.doesNotMatch(expanded, /data-tasks-action="delete-task"/);
        assert.doesNotMatch(expanded, />Eliminar tarea</);
    });

    it('no muestra importancia en expandido cuando es 0', () => {
        var html = renderer.renderItem(baseItem({
            description: 'Solo descripción',
            importance: 0,
            due_at: null
        }));

        assert.doesNotMatch(html, /Importancia:/);
    });

    it('conserva visible_actions navigate en panel expandido', () => {
        var html = renderer.renderItem(baseItem({
            visible_actions: [
                visibleAction({
                    key: 'navigate',
                    type: 'navigate',
                    label: 'Ir',
                    url: 'https://example.test/admin-post.php?module=assignments'
                })
            ]
        }));

        assert.match(html, /https:\/\/example\.test\/admin-post\.php\?module=assignments/);
        assert.match(html, />Ir</);
    });
});

describe('executableListRenderer MC13L-B list options menu', () => {
    it('lista user con can_delete renderiza Eliminar lista en menú', () => {
        var html = renderer.renderList(baseList({
            id: '12',
            source: 'user',
            capabilities: {
                can_archive: true,
                can_edit: true,
                can_delete: true
            },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /aa-executable-list-options-trigger/);
        assert.match(html, />Editar lista</);
        assert.match(html, />Archivar lista</);
        assert.match(html, />Eliminar lista</);
        assert.match(html, /data-tasks-action="delete-list"/);
        assert.match(html, /data-list-id="12"/);
        assert.match(html, /data-tasks-action="archive-list"[^>]*class="[^"]*text-gray-700/);
        assert.match(html, /data-tasks-action="delete-list"[^>]*class="[^"]*text-red-600/);
        assert.match(html, /aa-chevron/);
    });

    it('lista user sin can_delete no muestra Eliminar lista', () => {
        var html = renderer.renderList(baseList({
            id: '12',
            source: 'user',
            capabilities: { can_archive: true, can_edit: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /aa-executable-list-options-trigger/);
        assert.match(html, />Editar lista</);
        assert.match(html, />Archivar lista</);
        assert.doesNotMatch(html, />Eliminar lista</);
        assert.doesNotMatch(html, /data-tasks-action="delete-list"/);
    });

    it('lista agenda_app no renderiza menú de opciones', () => {
        var html = renderer.renderList(baseList({
            source: 'system',
            source_category: 'agenda_app',
            capabilities: { can_archive: false, can_edit: false },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        }));

        assert.doesNotMatch(html, /aa-executable-list-options/);
        assert.doesNotMatch(html, />Editar lista</);
        assert.match(html, /aa-chevron/);
    });
});

describe('executableListRenderer MC13L-C list edit menu', () => {
    it('Editar lista solo aparece con can_edit=true', () => {
        var editable = renderer.renderList(baseList({
            capabilities: { can_edit: true, can_archive: false },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var notEditable = renderer.renderList(baseList({
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(editable, /data-aa-list-edit="1"/);
        assert.doesNotMatch(notEditable, /data-aa-list-edit/);
    });

    it('data attrs de prefill escapan comillas en title y description', () => {
        var html = renderer.renderList(baseList({
            id: '55',
            title: 'Lista "urgente"',
            description: 'Objetivo con "comillas"',
            importance: 0,
            capabilities: { can_edit: true, can_archive: false },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /data-list-title="Lista &quot;urgente&quot;"/);
        assert.match(html, /data-list-description="Objetivo con &quot;comillas&quot;"/);
        assert.match(html, /data-list-importance="0"/);
    });

    it('sin capabilities no renderiza Editar lista ni Archivar lista', () => {
        var html = renderer.renderList(baseList({
            capabilities: {},
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, />Editar lista</);
        assert.doesNotMatch(html, />Archivar lista</);
        assert.doesNotMatch(html, /data-aa-list-options-trigger/);
    });
});

describe('executableListRenderer MC4b restore archived tasks menu', () => {
    it('lista user con can_restore_archived_tasks muestra Desarchivar tareas', () => {
        var html = renderer.renderList(baseList({
            id: '88',
            source: 'user',
            capabilities: {
                can_edit: true,
                can_archive: true,
                can_restore_archived_tasks: true
            },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, />Desarchivar tareas</);
        assert.match(html, /data-aa-list-restore-archived-tasks="1"/);
        assert.match(html, /data-list-id="88"/);
        assert.match(html, />Editar lista</);
        assert.match(html, />Archivar lista</);
        assert.doesNotMatch(html, /Desarchivar todas/);
        assert.doesNotMatch(html, /Eliminar lista/);
    });

    it('lista user sin can_restore_archived_tasks no muestra Desarchivar tareas', () => {
        var html = renderer.renderList(baseList({
            capabilities: { can_edit: true, can_archive: true, can_restore_archived_tasks: false },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, />Desarchivar tareas</);
        assert.doesNotMatch(html, /data-aa-list-restore-archived-tasks/);
    });

    it('lista agenda_app no muestra Desarchivar tareas', () => {
        var html = renderer.renderList(baseList({
            source: 'system',
            source_category: 'agenda_app',
            capabilities: { can_restore_archived_tasks: false, can_edit: false, can_archive: false },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        }));

        assert.doesNotMatch(html, />Desarchivar tareas</);
        assert.doesNotMatch(html, /aa-executable-list-options/);
    });

    it('menú aparece solo con can_restore_archived_tasks', () => {
        var html = renderer.renderList(baseList({
            capabilities: { can_restore_archived_tasks: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /data-aa-list-options-trigger/);
        assert.match(html, />Desarchivar tareas</);
        assert.doesNotMatch(html, />Editar lista</);
        assert.doesNotMatch(html, />Archivar lista</);
    });
});

describe('executableListRenderer MC13 UX-A visual polish', () => {
    it('lista agenda_app usa header neutral sin from-amber-50', () => {
        var html = renderer.renderList(baseList({
            source: 'system',
            source_category: 'agenda_app',
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        }));

        assert.match(html, /from-gray-50 to-white/);
        assert.doesNotMatch(html, /from-amber-50/);
    });

    it('lista user usa el mismo header neutral', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /from-gray-50 to-white/);
        assert.doesNotMatch(html, /from-amber-50/);
    });

    it('label user usa modificador verde y agenda_app azul', () => {
        var userHtml = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            source_label: 'Mis listas',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var agendaHtml = renderer.renderList(baseList({
            source: 'system',
            source_category: 'agenda_app',
            source_label: 'Agenda app',
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        }));

        assert.match(userHtml, /aa-executable-list-source-label--user/);
        assert.match(userHtml, /text-emerald-700/);
        assert.match(agendaHtml, /aa-executable-list-source-label--agenda-app/);
        assert.match(agendaHtml, /text-blue-700/);
    });

    it('label de fuente desconocida conserva gris sin modificador', () => {
        var html = renderer.renderList(baseList({
            source: 'ai',
            source_category: 'ai',
            source_label: 'IA',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /text-gray-500/);
        assert.doesNotMatch(html, /aa-executable-list-source-label--user/);
        assert.doesNotMatch(html, /aa-executable-list-source-label--agenda-app/);
    });

    it('solo la primera tarea global recibe aa-executable-item-first', () => {
        var html = renderer.renderList(baseList({
            buckets: [{
                key: 'default',
                label: '',
                items: [
                    baseItem({ id: 't1', title: 'Primera' }),
                    baseItem({ id: 't2', title: 'Segunda' })
                ]
            }]
        }));

        assert.equal((html.match(/aa-executable-item-first/g) || []).length, 1);
        assert.match(html, /aa-executable-item-first" data-item-id="t1"/);
        assert.doesNotMatch(html, /aa-executable-item-first" data-item-id="t2"/);
    });

    it('con bucket primary vacío marca la primera tarea de secondary', () => {
        var html = renderer.renderList(baseList({
            buckets: [
                { key: 'primary', label: 'Principales', items: [] },
                {
                    key: 'secondary',
                    label: 'Secundarias',
                    items: [
                        baseItem({ id: 'sec1', title: 'Secundaria 1' }),
                        baseItem({ id: 'sec2', title: 'Secundaria 2' })
                    ]
                }
            ]
        }));

        assert.equal((html.match(/aa-executable-item-first/g) || []).length, 1);
        assert.match(html, /aa-executable-item-first" data-item-id="sec1"/);
        assert.doesNotMatch(html, /aa-executable-item-first" data-item-id="sec2"/);
    });

    it('lista sin tareas no falla ni emite aa-executable-item-first', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, /aa-executable-item-first/);
        assert.match(html, /aa-executable-list-empty-pending/);
    });

    it('conserva chevron y menú ⋮ en listas user editables', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            capabilities: { can_edit: true, can_archive: true },
            buckets: [{
                key: 'default',
                label: '',
                items: [baseItem({ id: 't1' })]
            }]
        }));

        assert.match(html, /aa-chevron/);
        assert.match(html, /aa-executable-list-options-trigger/);
        assert.match(html, /aa-executable-item-first/);
    });

    it('CSS contiene resaltado de primera tarea expandida', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-item\.aa-executable-item-first\[open\]/);
        assert.match(css, /border-blue-100 bg-blue-50\/50/);
    });
});

describe('executableListRenderer chevron rotation CSS', () => {
    it('usa selectores acotados por lista y tarea sin regla genérica anidada', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card\[open\] > summary \.aa-chevron/);
        assert.match(css, /details\.aa-executable-item\[open\] > summary \.aa-executable-item-chevron/);
        assert.doesNotMatch(css, /details\[open\] summary \.aa-chevron/);
        assert.match(css, /details\[open\] > summary \.aa-chevron:not\(\.aa-executable-item-chevron\)/);
    });

    it('renderer expone clases de chevron compatibles con rotación acotada', () => {
        var listHtml = renderer.renderList(baseList({
            buckets: [
                {
                    key: 'default',
                    label: '',
                    items: [baseItem({ description: 'Preview' })]
                }
            ]
        }));
        var itemHtml = renderer.renderItem(baseItem({
            description: 'Detalle',
            capabilities: { can_edit: true }
        }));

        assert.match(listHtml, /aa-executable-list-card/);
        assert.match(listHtml, /aa-chevron/);
        assert.match(itemHtml, /aa-executable-item-chevron/);
        assert.match(itemHtml, /aa-executable-item-chevron aa-chevron/);
    });
});

describe('executableListRenderer MC-UX-A visual polish', () => {
    it('CSS oculta #aa-btn-open-aichat dentro de #aa-tasks-fab-stack', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /#aa-tasks-fab-stack #aa-btn-open-aichat/);
        assert.match(css, /display:\s*none/);
    });

    it('Archivar lista usa text-gray-700 y Eliminar lista sigue en text-red-600', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            capabilities: { can_archive: true, can_delete: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /data-tasks-action="archive-list"[^>]*class="[^"]*text-gray-700/);
        assert.match(html, /data-tasks-action="delete-list"[^>]*class="[^"]*text-red-600/);
        assert.doesNotMatch(html, /data-tasks-action="archive-list"[^>]*class="[^"]*text-red-600/);
    });

    it('Eliminar tarea sigue en text-red-600 y Archivar tarea en text-gray-700', () => {
        var html = renderer.renderItem(baseItem({
            source: 'user',
            capabilities: { can_archive: true, can_delete: true }
        }));

        assert.match(html, /data-tasks-action="archive-task"[^>]*class="[^"]*text-gray-700/);
        assert.match(html, /data-tasks-action="delete-task"[^>]*class="[^"]*text-red-600/);
    });

    it('renderer agrega clase aa-executable-item-title al título', () => {
        var html = renderer.renderItem(baseItem({ title: 'Título largo de prueba' }));

        assert.match(html, /class="aa-executable-item-title text-sm font-semibold text-gray-900"/);
    });

    it('CSS trunca título colapsado y lo muestra completo expandido', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-item:not\(\[open\]\) \.aa-executable-item-title/);
        assert.match(css, /-webkit-line-clamp:\s*1/);
        assert.match(css, /details\.aa-executable-item\[open\] \.aa-executable-item-title/);
        assert.match(css, /-webkit-mask-image:\s*none/);
    });

    it('CSS oculta .aa-executable-task-options cuando la tarea está colapsada', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-item:not\(\[open\]\) \.aa-executable-task-options/);
        assert.match(css, /display:\s*none/);
    });

    it('CSS mantiene overflow visible en card lista y z-index del menú de tarea', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card[\s\S]*overflow:\s*visible/);
        assert.match(css, /\.aa-executable-task-options-menu[\s\S]*z-index:\s*30/);
    });

    it('renderer no incluye overflow-hidden en aa-executable-list-card', () => {
        var html = renderer.renderList(baseList({
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /aa-executable-list-card/);
        assert.doesNotMatch(html, /aa-executable-list-card[\s\S]*overflow-hidden/);
    });
});

describe('executableListRenderer MC-UX-C list card rounding', () => {
    it('summary de lista no incluye border-b fijo en el HTML', () => {
        var html = renderer.renderList(baseList({
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /<summary class="px-4 py-4 bg-gradient-to-r/);
        assert.doesNotMatch(html, /summary class="[^"]*border-b border-gray-100/);
    });

    it('CSS redondea summary colapsado con rounded-xl', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card:not\(\[open\]\) > summary/);
        assert.match(css, /rounded-xl/);
    });

    it('CSS redondea summary expandido con rounded-t-xl y border-b', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card\[open\] > summary/);
        assert.match(css, /rounded-t-xl/);
        assert.match(css, /border-b border-gray-100/);
    });

    it('CSS mantiene overflow visible y body expandido con rounded-b-xl', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card[\s\S]*overflow:\s*visible/);
        assert.match(css, /details\.aa-executable-list-card\[open\] > \.aa-executable-list-body/);
        assert.match(css, /rounded-b-xl/);
    });
});

describe('executableListRenderer MC-UX-D list details on demand', () => {
    it('lista con descripción no renderiza p permanente en header', () => {
        var html = renderer.renderList(baseList({
            description: 'Objetivo de la lista',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, /summary[\s\S]*<p class="text-sm text-gray-600 mt-1">/);
        assert.match(html, /aa-executable-list-details-description/);
        assert.match(html, />Objetivo de la lista</);
        assert.match(html, /data-aa-list-details-toggle="1"/);
        assert.match(html, />Ver más</);
    });

    it('lista con importance distinta de 0 renderiza Importancia en detalles', () => {
        var html = renderer.renderList(baseList({
            description: '',
            importance: 3,
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /aa-executable-list-details-importance/);
        assert.match(html, />Importancia: 3</);
        assert.match(html, /data-aa-list-details-toggle="1"/);
        assert.doesNotMatch(html, /aa-executable-list-details-description/);
    });

    it('lista sin descripción ni importancia no renderiza Ver más ni bloque de detalles', () => {
        var html = renderer.renderList(baseList({
            description: '',
            importance: 0,
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, /data-aa-list-details-toggle/);
        assert.doesNotMatch(html, /aa-executable-list-details/);
    });

    it('descripción con HTML se escapa en bloque de detalles', () => {
        var html = renderer.renderList(baseList({
            description: '"><img onerror=1>',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /aa-executable-list-details-description[\s\S]*&quot;&gt;&lt;img onerror=1&gt;/);
        assert.doesNotMatch(html, /onerror=1>/);
    });

    it('CSS oculta toggle y detalles en lista colapsada y hasta is-visible', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card:not\(\[open\]\) \.aa-executable-list-details/);
        assert.match(css, /details\.aa-executable-list-card:not\(\[open\]\) \.aa-executable-list-details-toggle/);
        assert.match(css, /details\.aa-executable-list-card\[open\] \.aa-executable-list-details:not\(\.is-visible\)/);
        assert.match(css, /details\.aa-executable-list-card[\s\S]*overflow:\s*visible/);
    });
});

describe('executableListRenderer MC-UX-E following tasks block', () => {
    it('no renderiza label Principales y Secundarias queda dentro del bloque cuando hay top primary', () => {
        var html = renderer.renderList(baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [baseItem({ id: 'p1', title: 'Primary 1' })]
                },
                {
                    key: 'secondary',
                    label: 'Secundarias',
                    items: [baseItem({ id: 's1', title: 'Secondary 1' })]
                }
            ]
        }));

        assert.doesNotMatch(html, />Principales</);
        assert.match(html, />Secundarias</);
        assert.match(html, />Siguientes tareas \(1\)</);
        assert.match(html, /aa-executable-following-tasks[\s\S]*>Secundarias</);
        assert.doesNotMatch(extractTopUl(html), />Secundarias</);
    });

    it('bucket default con 1 item no renderiza Siguientes tareas', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            buckets: [{
                key: 'default',
                label: '',
                items: [baseItem({ id: 't1', title: 'Solo una' })]
            }]
        }));

        assert.doesNotMatch(html, /aa-executable-following-tasks/);
        assert.doesNotMatch(html, />Siguientes tareas</);
    });

    it('bucket default con 3 items separa top y bloque Siguientes tareas (2)', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            buckets: [{
                key: 'default',
                label: '',
                items: [
                    baseItem({ id: 't1', title: 'Top' }),
                    baseItem({ id: 't2', title: 'Siguiente 2' }),
                    baseItem({ id: 't3', title: 'Siguiente 3' })
                ]
            }]
        }));

        assert.match(html, /aa-executable-bucket-items-top[\s\S]*?data-item-id="t1"/);
        assert.match(html, /aa-executable-following-tasks/);
        assert.match(html, />Siguientes tareas \(2\)</);

        var topUlMatch = extractTopUl(html);
        var followingContentMatch = extractFollowingContent(html);

        assert.ok(topUlMatch);
        assert.ok(followingContentMatch);
        assert.match(topUlMatch, /data-item-id="t1"/);
        assert.doesNotMatch(topUlMatch, /data-item-id="t2"/);
        assert.match(followingContentMatch, /data-item-id="t2"/);
        assert.match(followingContentMatch, /data-item-id="t3"/);
        assert.doesNotMatch(followingContentMatch, /data-item-id="t1"/);
    });

    it('primary con 2 items y secondary con 1 agrupa todo en Siguientes tareas (2)', () => {
        var html = renderer.renderList(baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [
                        baseItem({ id: 'p1', title: 'Primary top' }),
                        baseItem({ id: 'p2', title: 'Primary next' })
                    ]
                },
                {
                    key: 'secondary',
                    label: 'Secundarias',
                    items: [baseItem({ id: 's1', title: 'Secondary' })]
                }
            ]
        }));

        assert.match(html, />Siguientes tareas \(2\)</);
        assert.match(extractTopUl(html), /data-item-id="p1"/);
        assert.doesNotMatch(extractTopUl(html), /data-item-id="p2"/);

        var followingContentMatch = extractFollowingContent(html);

        assert.ok(followingContentMatch);
        assert.match(followingContentMatch, /data-item-id="p2"/);
        assert.match(followingContentMatch, />Secundarias</);
        assert.match(followingContentMatch, /data-item-id="s1"/);
    });

    it('top primary con solo secundarias muestra Siguientes tareas con contador de secundarias', () => {
        var html = renderer.renderList(baseList({
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [baseItem({ id: 'p1', title: 'Solo primary' })]
                },
                {
                    key: 'secondary',
                    label: 'Secundarias',
                    items: [
                        baseItem({ id: 's1', title: 'Secondary 1' }),
                        baseItem({ id: 's2', title: 'Secondary 2' })
                    ]
                }
            ]
        }));

        assert.match(html, />Siguientes tareas \(2\)</);
        assert.match(html, /aa-executable-following-tasks[\s\S]*>Secundarias</);
        assert.doesNotMatch(extractTopUl(html), />Secundarias</);

        var followingContentMatch = extractFollowingContent(html);

        assert.ok(followingContentMatch);
        assert.match(followingContentMatch, /data-item-id="s1"/);
        assert.match(followingContentMatch, /data-item-id="s2"/);
    });

    it('primary vacío con una secundaria no crea bloque Siguientes tareas', () => {
        var html = renderer.renderList(baseList({
            buckets: [
                { key: 'primary', label: 'Principales', items: [] },
                {
                    key: 'secondary',
                    label: 'Secundarias',
                    items: [baseItem({ id: 's1', title: 'Secondary' })]
                }
            ]
        }));

        assert.doesNotMatch(html, /aa-executable-following-tasks/);
        assert.doesNotMatch(html, />Secundarias</);
        assert.match(html, /aa-executable-item-first" data-item-id="s1"/);
    });

    it('summary es compacto, chevron junto al texto y sin hover tipo botón', () => {
        var html = renderer.renderList(baseList({
            buckets: [{
                key: 'default',
                label: '',
                items: [
                    baseItem({ id: 't1', title: 'Top' }),
                    baseItem({ id: 't2', title: 'Siguiente' })
                ]
            }]
        }));

        assert.match(html, /aa-executable-following-tasks-summary[^>]*text-xs/);
        assert.match(html, /aa-executable-following-tasks-summary[^>]*inline-flex items-center gap-1\.5/);
        assert.doesNotMatch(html, /aa-executable-following-tasks-summary[^>]*justify-between/);
        assert.doesNotMatch(html, /aa-executable-following-tasks-summary[^>]*hover:border-gray-200/);
        assert.doesNotMatch(html, /aa-executable-following-tasks-summary[^>]*hover:bg-gray-50/);
        assert.match(
            html,
            /aa-executable-following-tasks-label[\s\S]*aa-executable-following-tasks-chevron/
        );
    });

    it('triggers ⋮ de lista y tarea usan estilo plano por defecto', () => {
        var html = renderer.renderList(baseList({
            capabilities: { can_edit: true, can_archive: true },
            buckets: [{
                key: 'default',
                label: '',
                items: [baseItem({
                    id: 't1',
                    capabilities: { can_edit: true, can_delete: true }
                })]
            }]
        }));

        assert.match(html, /aa-executable-list-options-trigger aa-options-trigger-flat/);
        assert.match(html, /aa-executable-task-options-trigger aa-options-trigger-flat/);
        assert.doesNotMatch(html, /aa-options-trigger-flat[^>]*bg-white border border-gray-200/);
    });

    it('CSS define triggers ⋮ planos y estado activo por aria-expanded', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /\.aa-options-trigger-flat[\s\S]*border-transparent/);
        assert.match(css, /\.aa-options-trigger-flat[\s\S]*hover:border-gray-200/);
        assert.match(css, /\.aa-options-trigger-flat\[aria-expanded="true"\]/);
    });

    it('CSS alterna copy colapsado/expandido, rota chevron y mantiene overflow visible', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-following-tasks\[open\] \.aa-following-label-collapsed/);
        assert.match(css, /details\.aa-executable-following-tasks:not\(\[open\]\) \.aa-following-label-expanded/);
        assert.match(css, /details\.aa-executable-following-tasks\[open\] > summary \.aa-executable-following-tasks-chevron/);
        assert.match(css, /details\.aa-executable-list-card[\s\S]*overflow:\s*visible/);
    });
});

describe('executableListRenderer add-task button and chevron placement', () => {
    function extractSummary(html) {
        var match = html.match(/<summary[^>]*>[\s\S]*?<\/summary>/);

        return match ? match[0] : '';
    }

    it('isUserManualList identifica listas manuales de usuario', () => {
        assert.equal(renderer.isUserManualList({
            source_category: 'user',
            managed_by: 'user'
        }), true);

        assert.equal(renderer.isUserManualList({
            source_category: 'user'
        }), true);

        assert.equal(renderer.isUserManualList({
            source_category: 'agenda_app',
            managed_by: 'developer'
        }), false);

        assert.equal(renderer.isUserManualList({
            source_category: 'user',
            managed_by: 'developer'
        }), false);

        assert.equal(renderer.isUserManualList(null), false);
    });

    it('lista user manual renderiza botón + tarea con data-aa-list-add-task', () => {
        var html = renderer.renderList(baseList({
            id: '7',
            source: 'user',
            source_category: 'user',
            managed_by: 'user',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /data-aa-list-add-task="1"/);
        assert.match(html, /aa-executable-list-add-task/);
        assert.match(html, />\+ tarea</);
        assert.match(html, /data-aa-list-add-task="1"[^>]*data-list-id="7"/);
    });

    it('lista sistema no renderiza botón + tarea', () => {
        var html = renderer.renderList(baseList({
            source: 'system',
            source_category: 'agenda_app',
            managed_by: 'developer',
            capabilities: { can_archive: false },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        }));

        assert.doesNotMatch(html, /data-aa-list-add-task/);
        assert.doesNotMatch(html, /aa-executable-list-add-task/);
        assert.doesNotMatch(html, />\+ tarea</);
    });

    it('lista user managed_by developer no renderiza + tarea', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            managed_by: 'developer',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, /data-aa-list-add-task/);
    });

    it('chevron aparece en bloque izquierdo junto al título', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            title: 'Mi lista',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var summary = extractSummary(html);

        assert.match(
            summary,
            /min-w-0 flex-1[\s\S]*<h4[^>]*>Mi lista<\/h4>[\s\S]*aa-chevron/
        );
    });

    it('chevron no está en el bloque derecho de acciones', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.doesNotMatch(html, /aa-executable-list-options[\s\S]*aa-chevron[\s\S]*<\/div>\s*<\/div>\s*<\/summary>/);
    });

    it('título y chevron comparten fila flex', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            title: 'Título de prueba',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(
            html,
            /<div class="flex items-center gap-1\.5 min-w-0">[\s\S]*?<h4[^>]*>[\s\S]*?<\/h4>[\s\S]*?aa-chevron[\s\S]*?<\/div>/
        );
    });

    it('+ tarea y opciones de lista no se renderizan simultáneamente en el mismo contenedor', () => {
        var html = renderer.renderList(baseList({
            id: '7',
            source: 'user',
            source_category: 'user',
            managed_by: 'user',
            capabilities: { can_archive: true, can_edit: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        assert.match(html, /aa-executable-list-add-task/);
        assert.match(html, /aa-executable-list-options/);
    });

    it('CSS oculta + tarea con lista abierta', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card\[open\] \.aa-executable-list-add-task/);
        assert.match(css, /display:\s*none/);
    });

    it('CSS existente oculta opciones con lista cerrada', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card:not\(\[open\]\) \.aa-executable-list-options/);
    });

    it('rotación de chevron CSS sigue funcionando con chevron en bloque izquierdo', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /details\.aa-executable-list-card\[open\] > summary \.aa-chevron/);
    });

    it('metadata permanece debajo del título', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            source_label: 'Mis listas',
            title: 'Mi lista',
            buckets: [{ key: 'default', label: '', items: [] }]
        }));

        var titlePos = html.indexOf('>Mi lista</h4>');
        var chevronPos = html.indexOf('aa-chevron');
        var metaPos = html.indexOf('aa-executable-list-header-meta');

        assert.notEqual(titlePos, -1);
        assert.notEqual(chevronPos, -1);
        assert.notEqual(metaPos, -1);
        assert.ok(titlePos < chevronPos);
        assert.ok(chevronPos < metaPos);
    });
});

describe('executableListRenderer add-task menu item', () => {
    function extractMenu(html) {
        var match = html.match(/<div[^>]*aa-executable-list-options-menu[^>]*>[\s\S]*?<\/div>/);

        return match ? match[0] : '';
    }

    function extractMenuItems(html) {
        var menu = extractMenu(html);
        var items = [];
        var re = /<button[^>]*role="menuitem"[^>]*>([\s\S]*?)<\/button>/g;
        var m;

        while ((m = re.exec(menu)) !== null) {
            items.push(m[1].trim());
        }

        return items;
    }

    it('lista manual del usuario muestra + tarea como primer menuitem', () => {
        var html = renderer.renderList(baseList({
            id: '12',
            source: 'user',
            source_category: 'user',
            managed_by: 'user',
            capabilities: { can_edit: true, can_archive: true, can_delete: true, can_restore_archived_tasks: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var items = extractMenuItems(html);

        assert.equal(items[0], '+ tarea');
        assert.equal(items[1], 'Editar lista');
        assert.equal(items[2], 'Desarchivar tareas');
        assert.equal(items[3], 'Archivar lista');
        assert.equal(items[4], 'Eliminar lista');
    });

    it('menuitem + tarea usa data-aa-list-add-task y data-list-id', () => {
        var html = renderer.renderList(baseList({
            id: '55',
            source: 'user',
            source_category: 'user',
            managed_by: 'user',
            capabilities: { can_edit: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var menu = extractMenu(html);

        assert.match(menu, /role="menuitem"[\s\S]*?data-aa-list-add-task="1"/);
        assert.match(menu, /data-list-id="55"/);
    });

    it('lista sistema no incluye + tarea en menú', () => {
        var html = renderer.renderList(baseList({
            source: 'system',
            source_category: 'agenda_app',
            managed_by: 'developer',
            capabilities: { can_archive: true },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        }));
        var items = extractMenuItems(html);

        assert.ok(!items.includes('+ tarea'));
    });

    it('lista user managed_by developer no incluye + tarea en menú', () => {
        var html = renderer.renderList(baseList({
            source: 'user',
            source_category: 'user',
            managed_by: 'developer',
            capabilities: { can_edit: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var items = extractMenuItems(html);

        assert.ok(!items.includes('+ tarea'));
    });

    it('lista manual sin capabilities igual renderiza menú con + tarea', () => {
        var html = renderer.renderList(baseList({
            id: '33',
            source: 'user',
            source_category: 'user',
            managed_by: 'user',
            capabilities: {},
            buckets: [{ key: 'default', label: '', items: [] }]
        }));
        var items = extractMenuItems(html);

        assert.equal(items.length, 1);
        assert.equal(items[0], '+ tarea');
    });
});
