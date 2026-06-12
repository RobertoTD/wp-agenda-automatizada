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

/**
 * Fixture post-H3B-3: el feed común enriquecido no incluye visible_actions.defer.
 * capabilities.can_defer puede quedar true en Learning como metadata legacy.
 */
function activeFeedFixture() {
    return [
        {
            id: 'system:learning.recommendations',
            source: 'system',
            source_category: 'agenda_app',
            source_label: 'Agenda app',
            origin_key: 'learning.recommendations',
            title: 'Activación de tu agenda',
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
                                })
                            ],
                            is_executive_candidate: false
                        }
                    ]
                },
                {
                    key: 'secondary',
                    label: 'Secundarias',
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
            source_category: 'user',
            source_label: 'Mis listas',
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
                                can_defer: false,
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
    it('renderiza Learning navigate sin defer en feed post-H3B-3', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /https:\/\/example\.test\/admin-post\.php\?module=assignments/);
        assert.match(html, />Ir</);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
        assert.doesNotMatch(html, />Ahora no</);
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

    it('renderiza User complete/dismiss en canal Tasks sin defer', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-tasks-action="dismiss"/);
        assert.match(html, /data-task-id="10"/);
        assert.doesNotMatch(html, /data-tasks-action="defer"/);
        assert.doesNotMatch(html, /data-learning-action="defer"[^>]*data-task-id="10"/);
        assert.doesNotMatch(html, />Ahora no</);
    });

    it('feed común post-H3B-3 no incluye botón defer ni Ahora no', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.doesNotMatch(html, /data-tasks-action="defer"/);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
        assert.doesNotMatch(html, />Ahora no</);
    });

    it('active feed no muestra Reabrir ni Reactivar', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.doesNotMatch(html, /data-tasks-action="pending"/);
        assert.doesNotMatch(html, /data-learning-action="reactivate"/);
        assert.doesNotMatch(html, />Reabrir</);
        assert.doesNotMatch(html, />Reactivar</);
    });

    it('no infiere defer desde capabilities.can_defer cuando hay visible_actions', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        var configureServicesSection = html.split('Configura tus servicios')[1] || '';
        var installPwaSection = html.split('Instala la app')[1] || '';

        assert.doesNotMatch(configureServicesSection, /data-learning-action="defer"/);
        assert.doesNotMatch(configureServicesSection, />Ahora no</);
        assert.match(installPwaSection, /data-learning-action="dismiss"/);
        assert.doesNotMatch(installPwaSection, /data-learning-action="defer"/);
        assert.doesNotMatch(installPwaSection, /data-learning-action="reactivate"/);
    });

    it('feed mixto system+user se renderiza en una sola salida', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /data-list-source="system"/);
        assert.match(html, /data-list-source="user"/);
        assert.match(html, /aa-executable-list-source-label/);
        assert.match(html, />Agenda app</);
        assert.match(html, />Mis listas</);
        assert.doesNotMatch(html, />Recomendado</);
        assert.doesNotMatch(html, /aa-executable-list-source-badge/);
    });

    it('feed mixto mantiene canales Learning y Tasks sin defer', () => {
        var html = renderer.renderFeed(activeFeedFixture());

        assert.match(html, /data-learning-action="complete"/);
        assert.match(html, /data-tasks-action="complete"/);
        assert.match(html, /data-tasks-action="dismiss"/);
        assert.doesNotMatch(html, /data-learning-action="defer"/);
        assert.doesNotMatch(html, /data-tasks-action="defer"/);
    });

    it('MC13L feed mixto renderiza listas como details cerradas', () => {
        var html = renderer.renderFeed(activeFeedFixture());
        var detailsCount = (html.match(/<details class="aa-executable-list-card/g) || []).length;

        assert.equal(detailsCount, 2);
        assert.doesNotMatch(html, /<details[^>]*\sopen(?:=|>)/);
        assert.match(html, /aa-chevron/);
        assert.match(html, /aa-executable-list-body/);
    });
});
