'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
const adminSourceCssPath = path.join(__dirname, '../../includes/admin/ui/assets/css/admin.source.css');
const renderer = require(rendererPath);

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
        is_executive_candidate: false
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
        title: 'Recomendaciones',
        description: 'Sugerencias del sistema.',
        importance: 0,
        position: 0,
        status: 'active',
        capabilities: { can_archive: false },
        buckets: []
    }, overrides || {});
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

    it('renderiza bucket primary y secondary con labels', () => {
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

        assert.match(html, />Principales</);
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
        assert.match(html, />Ignorar</);
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

    it('showReactivate no afecta Ignorar en fallback legacy', () => {
        var item = baseItem({
            origin_key: 'install_pwa',
            capabilities: {
                can_dismiss: true,
                can_reactivate: true
            }
        });

        var html = renderer.renderItem(item);

        assert.match(html, /data-learning-action="dismiss"/);
        assert.match(html, />Ignorar</);
        assert.doesNotMatch(html, /data-learning-action="reactivate"/);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
        assert.doesNotMatch(html, />Ahora no</);
    });

    it('can_archive genera data-tasks-action archive-list', () => {
        var list = baseList({
            id: '7',
            source: 'user',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /data-tasks-action="archive-list"/);
        assert.match(html, /data-list-id="7"/);
    });

    it('source=system muestra label sutil Agenda app', () => {
        var list = baseList({
            source: 'system',
            source_category: 'agenda_app',
            source_label: 'Agenda app',
            title: 'Recomendaciones',
            capabilities: { can_archive: false },
            buckets: [{ key: 'primary', label: 'Principales', items: [baseItem()] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /aa-executable-list-source-label/);
        assert.match(html, />Agenda app</);
        assert.doesNotMatch(html, /aa-executable-list-source-badge/);
        assert.doesNotMatch(html, />Recomendado</);
        assert.doesNotMatch(html, /data-tasks-action="archive-list"/);
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
        assert.match(html, />Principales</);
        assert.match(html, /aa-executable-item/);
    });

    it('MC13L archive-list en summary usa stopPropagation', () => {
        var list = baseList({
            id: '7',
            source: 'user',
            capabilities: { can_archive: true },
            buckets: [{ key: 'default', label: '', items: [] }]
        });

        var html = renderer.renderList(list);

        assert.match(html, /data-tasks-action="archive-list"/);
        assert.match(html, /onclick="event\.stopPropagation\(\)"/);
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
                                    label: 'Ignorar',
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
        assert.doesNotMatch(userHtml, />Ahora no</);
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
                    label: 'Ignorar',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-learning-action="dismiss"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.match(html, />Ignorar</);
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
                    label: 'Ignorar',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="dismiss"/);
        assert.match(html, /data-task-id="500"/);
        assert.match(html, />Ignorar</);
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
                    label: 'Ignorar',
                    placement: 'secondary',
                    url: null,
                    handler: null
                })
            ]
        }));

        assert.match(html, /data-tasks-action="dismiss"/);
        assert.match(html, /data-task-id="43"/);
        assert.match(html, />Ignorar</);
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
                    label: 'Ignorar',
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
                    label: 'Ignorar',
                    placement: 'secondary'
                })
            ]
        }));

        assert.match(html, /aa-executable-item-desc-full/);
        assert.match(html, />Detalles completos de la tarea</);
        assert.match(html, /Vence: 2026-06-20 08:37:00/);
        assert.match(html, /Importancia: 3/);
        assert.match(html, /aa-executable-item-actions/);
        assert.match(html, /onclick="event\.stopPropagation\(\)"/);
        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-tasks-action="dismiss"/);
        assert.doesNotMatch(html, /aa-executable-item-expanded-footer/);
        assert.doesNotMatch(html, /aa-executable-item-expanded[\s\S]*aa-executable-item-chevron/);
    });

    it('muestra chevron en summary y botón editar en summary solo si can_edit es true', () => {
        var html = renderer.renderItem(baseItem({
            id: '99',
            source: 'user',
            description: 'Notas de la tarea',
            due_at: '2026-06-21 10:00:00',
            importance: 2,
            default_bucket: 'secondary',
            capabilities: {
                can_complete: true,
                can_edit: true
            }
        }));
        var summary = extractSummary(html);

        assert.match(summary, /aa-executable-item-chevron/);
        assert.match(summary, /aa-executable-item-summary-actions/);
        assert.match(summary, /data-aa-task-edit="1"/);
        assert.match(summary, /aria-label="Editar tarea"/);
        assert.match(summary, /data-task-id="99"/);
        assert.match(summary, /data-task-default-bucket="secondary"/);
        assert.match(summary, /aa-executable-item-summary-edit/);
        assert.doesNotMatch(html, /aa-executable-item-expanded-footer/);
    });

    it('no muestra botón editar si can_edit es false o ausente', () => {
        var withoutFlag = renderer.renderItem(baseItem({
            source: 'user',
            description: 'Solo lectura'
        }));
        var explicitFalse = renderer.renderItem(baseItem({
            source: 'user',
            description: 'Solo lectura',
            capabilities: {
                can_edit: false
            }
        }));

        assert.doesNotMatch(withoutFlag, /data-aa-task-edit/);
        assert.doesNotMatch(explicitFalse, /data-aa-task-edit/);
    });

    it('no muestra botón eliminar tarea en panel expandido', () => {
        var html = renderer.renderItem(baseItem({
            source: 'user',
            description: 'Editable',
            capabilities: {
                can_edit: true
            }
        }));

        assert.doesNotMatch(html, /data-aa-task-delete/);
        assert.doesNotMatch(html, /Eliminar tarea/);
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
