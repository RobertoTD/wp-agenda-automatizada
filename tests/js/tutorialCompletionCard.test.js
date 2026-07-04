'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const cardPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialCompletionCard.js'
);
const cardSrc = fs.readFileSync(cardPath, 'utf8');

function loadCompletionCard(options) {
    var opts = options || {};
    var elements = Object.create(null);
    var bodyChildren = [];
    var transitionCalls = 0;
    var completeCalls = 0;

    var body = {
        appendChild: function (node) {
            node.parentNode = body;
            bodyChildren.push(node);
            elements[node.id || ('node-' + bodyChildren.length)] = node;
        },
        removeChild: function (node) {
            node.parentNode = null;
            bodyChildren = bodyChildren.filter(function (child) {
                return child !== node;
            });
        }
    };

    var document = {
        body: body,
        createElement: function (tag) {
            return {
                tagName: tag.toUpperCase(),
                id: '',
                className: '',
                style: {},
                textContent: '',
                type: '',
                classList: {
                    add: function () {},
                    remove: function () {},
                    toggle: function () {}
                },
                appendChild: function (child) {
                    if (!this.children) {
                        this.children = [];
                    }
                    this.children.push(child);
                },
                addEventListener: function (type, handler) {
                    if (!this._handlers) {
                        this._handlers = {};
                    }
                    this._handlers[type] = handler;
                },
                click: function () {
                    if (this._handlers && this._handlers.click) {
                        this._handlers.click({ preventDefault: function () {} });
                    }
                }
            };
        },
        getElementById: function (id) {
            if (id === cardApi.ROOT_ID) {
                return bodyChildren.find(function (node) {
                    return node.id === cardApi.ROOT_ID;
                }) || null;
            }

            if (id === cardApi.TUTORIAL_ENGINE_ROOT_ID) {
                return elements[id] || null;
            }

            return elements[id] || null;
        }
    };

    var context = {
        window: {},
        document: document,
        module: { exports: {} }
    };

    context.window = context;

    vm.runInNewContext(cardSrc, context, { filename: cardPath });

    var cardApi = context.window.TutorialCompletionCard || context.module.exports;

    if (opts.seedTutorialEngineRoot) {
        var engineRoot = document.createElement('div');
        engineRoot.id = cardApi.TUTORIAL_ENGINE_ROOT_ID;
        elements[cardApi.TUTORIAL_ENGINE_ROOT_ID] = engineRoot;
        body.appendChild(engineRoot);
    }

    context.window.TutorialStateService = {
        transition: function () {
            transitionCalls++;
            return Promise.resolve({});
        }
    };
    context.window.AATutorial = {
        complete: function () {
            completeCalls++;
            return true;
        }
    };

    function findCompletionRoot() {
        return bodyChildren.find(function (node) {
            return node.id === cardApi.ROOT_ID;
        }) || null;
    }

    function findButton(root) {
        if (!root || !root.children) {
            return null;
        }

        var card = root.children.find(function (node) {
            return node.className === 'aa-tutorial-card';
        });

        if (!card || !card.children) {
            return null;
        }

        var actions = card.children.find(function (node) {
            return node.className === 'aa-tutorial-actions';
        });

        if (!actions || !actions.children || !actions.children.length) {
            return null;
        }

        return actions.children[0];
    }

    return {
        card: cardApi,
        findCompletionRoot: findCompletionRoot,
        findButton: findButton,
        metrics: {
            get transitionCalls() { return transitionCalls; },
            get completeCalls() { return completeCalls; }
        },
        getEngineRoot: function () {
            return elements[cardApi.TUTORIAL_ENGINE_ROOT_ID] || null;
        }
    };
}

describe('TutorialCompletionCard D1', () => {
    it('show() crea #aa-tutorial-completion-card-root', () => {
        var env = loadCompletionCard();

        assert.equal(env.card.show(), true);

        var root = env.findCompletionRoot();
        assert.ok(root);
        assert.equal(root.id, env.card.ROOT_ID);
        assert.match(root.className, /aa-tutorial-root/);
    });

    it('renderiza título, texto y botón con copy por defecto', () => {
        var env = loadCompletionCard();
        env.card.show();

        var root = env.findCompletionRoot();
        var card = root.children.find(function (node) {
            return node.className === 'aa-tutorial-card';
        });

        var title = card.children.find(function (node) {
            return node.className === 'aa-tutorial-title';
        });
        var text = card.children.find(function (node) {
            return node.className === 'aa-tutorial-text';
        });
        var button = env.findButton(root);

        assert.equal(title.textContent, env.card.DEFAULT_TITLE);
        assert.equal(text.textContent, env.card.DEFAULT_TEXT);
        assert.equal(button.textContent, env.card.DEFAULT_BUTTON_LABEL);
    });

    it('click en Cerrar elimina el root', () => {
        var env = loadCompletionCard();
        env.card.show();

        var button = env.findButton(env.findCompletionRoot());
        button.click();

        assert.equal(env.findCompletionRoot(), null);
    });

    it('show() dos veces deja una sola tarjeta', () => {
        var env = loadCompletionCard();
        env.card.show();
        env.card.show();

        var count = env.findCompletionRoot() ? 1 : 0;
        assert.equal(count, 1);
    });

    it('dismiss() es idempotente', () => {
        var env = loadCompletionCard();
        env.card.show();

        env.card.dismiss();
        env.card.dismiss();

        assert.equal(env.findCompletionRoot(), null);
    });

    it('no toca #aa-tutorial-root', () => {
        var env = loadCompletionCard({ seedTutorialEngineRoot: true });
        var engineRoot = env.getEngineRoot();

        env.card.show();
        env.card.dismiss();

        assert.ok(engineRoot);
        assert.equal(env.getEngineRoot(), engineRoot);
    });

    it('no llama TutorialStateService.transition ni AATutorial.complete', () => {
        var env = loadCompletionCard();
        env.card.show();

        var button = env.findButton(env.findCompletionRoot());
        button.click();

        assert.equal(env.metrics.transitionCalls, 0);
        assert.equal(env.metrics.completeCalls, 0);
    });
});
