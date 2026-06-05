'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
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
        is_executive_candidate: false
    }, overrides || {});
}

function baseList(overrides) {
    return Object.assign({
        id: 'system:learning.recommendations',
        source: 'system',
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
                    label: 'Otras sugerencias',
                    items: [baseItem({ id: 'b', origin_key: 'b' })]
                }
            ]
        });

        var html = renderer.renderFeed([list]);

        assert.match(html, />Principales</);
        assert.match(html, />Otras sugerencias</);
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

    it('can_defer genera data-learning-action defer', () => {
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
