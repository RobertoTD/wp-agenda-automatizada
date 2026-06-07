'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
const renderer = require(rendererPath);

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

function activeFeedFixture() {
    return [
        {
            id: 'system:learning.recommendations',
            source: 'system',
            origin_key: 'learning.recommendations',
            title: 'Recomendaciones',
            description: 'Sugerencias para configurar y usar tu agenda.',
            importance: 0,
            position: 0,
            status: 'active',
            capabilities: { can_archive: false },
            buckets: [
                {
                    key: 'primary',
                    label: 'Principales',
                    items: [
                        {
                            id: 'configure_services',
                            source: 'system',
                            origin_key: 'configure_services',
                            title: 'Configura tus servicios',
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
                                can_defer: true,
                                can_dismiss: false,
                                can_reactivate: false
                            },
                            primary_action: {
                                type: 'navigate',
                                label: 'Ir',
                                url: 'https://example.test/admin-post.php?module=assignments'
                            },
                            visible_actions: [
                                visibleAction({
                                    key: 'navigate',
                                    type: 'navigate',
                                    label: 'Ir',
                                    url: 'https://example.test/admin-post.php?module=assignments'
                                }),
                                visibleAction({
                                    key: 'defer',
                                    type: 'intent',
                                    category: 'intent',
                                    label: 'Ahora no',
                                    placement: 'secondary',
                                    url: null,
                                    handler: null
                                })
                            ],
                            is_executive_candidate: false
                        }
                    ]
                },
                {
                    key: 'secondary',
                    label: 'Otras sugerencias',
                    items: [
                        {
                            id: 'install_pwa',
                            source: 'system',
                            origin_key: 'install_pwa',
                            title: 'Instala la app',
                            description: 'PWA.',
                            importance: 100,
                            due_at: null,
                            status: 'pending',
                            state: {
                                completed: false,
                                ignored: true,
                                dismissed: true,
                                dismiss_active: false,
                                auto_completed: false
                            },
                            capabilities: {
                                can_complete: true,
                                can_reopen: false,
                                can_defer: false,
                                can_dismiss: true,
                                can_reactivate: true
                            },
                            primary_action: {
                                type: 'handler',
                                label: 'Instalar',
                                handler: 'pwa.install'
                            },
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
                                    key: 'dismiss',
                                    type: 'intent',
                                    category: 'intent',
                                    label: 'Ignorar',
                                    placement: 'secondary',
                                    url: null,
                                    handler: null
                                })
                            ],
                            is_executive_candidate: false
                        }
                    ]
                }
            ]
        },
        {
            id: '1',
            source: 'user',
            origin_key: null,
            title: 'Clientes',
            description: 'Pendientes',
            importance: 0,
            position: 0,
            status: 'active',
            capabilities: { can_archive: true },
            buckets: [
                {
                    key: 'default',
                    label: '',
                    items: [
                        {
                            id: '10',
                            source: 'user',
                            origin_key: null,
                            title: 'Llamar cliente',
                            description: 'Seguimiento',
                            importance: 2,
                            due_at: '2026-06-08 10:00:00',
                            status: 'pending',
                            state: {
                                completed: false,
                                ignored: false,
                                dismissed: false,
                                dismiss_active: false,
                                auto_completed: false
                            },
                            capabilities: {
                                can_complete: true,
                                can_reopen: false,
                                can_defer: true,
                                can_dismiss: true,
                                can_reactivate: false
                            },
                            primary_action: {
                                type: 'status',
                                label: 'Completar',
                                to: 'done'
                            },
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
                                    key: 'defer',
                                    type: 'intent',
                                    category: 'intent',
                                    label: 'Ahora no',
                                    placement: 'secondary',
                                    url: null,
                                    handler: null
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
                            ],
                            is_executive_candidate: true
                        }
                    ]
                }
            ]
        }
    ];
}

describe('executable feed render parity', () => {
    it('renderiza Learning navigate y defer desde visible_actions', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /https:\/\/example\.test\/admin-post\.php\?module=assignments/);
        assert.match(html, /data-learning-action="defer"/);
        assert.match(html, /data-recommendation-key="configure_services"/);
    });

    it('renderiza Learning complete en canal Learning', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /data-learning-action="complete"/);
        assert.match(html, /data-recommendation-key="install_pwa"/);
        assert.doesNotMatch(html, /data-task-id="install_pwa"/);
    });

    it('renderiza User complete en canal Tasks', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-task-id="10"/);
    });

    it('renderiza User defer/dismiss en canal Tasks', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /data-tasks-action="defer"/);
        assert.match(html, /data-task-id="10"/);
        assert.match(html, /data-tasks-action="dismiss"/);
        assert.doesNotMatch(html, /data-learning-action="defer"[^>]*data-task-id="10"/);
    });

    it('active feed no muestra Reabrir ni Reactivar', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.doesNotMatch(html, /data-tasks-action="pending"/);
        assert.doesNotMatch(html, /data-learning-action="reactivate"/);
        assert.doesNotMatch(html, />Reabrir</);
        assert.doesNotMatch(html, />Reactivar</);
    });

    it('no infiere acciones extra desde capabilities cuando hay visible_actions', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        var installPwaSection = html.split('Instala la app')[1] || '';

        assert.match(installPwaSection, /data-learning-action="dismiss"/);
        assert.doesNotMatch(installPwaSection, /data-learning-action="defer"/);
        assert.doesNotMatch(installPwaSection, /data-learning-action="reactivate"/);
    });
});
