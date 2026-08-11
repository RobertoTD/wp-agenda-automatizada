'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const placementPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/executable-options-menu-placement.js'
);
const placementSrc = fs.readFileSync(placementPath, 'utf8');

function loadPlacement() {
    var context = {
        window: {},
        module: { exports: {} }
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(placementSrc, context, { filename: placementPath });

    return context.module.exports;
}

describe('executable-options-menu-placement', () => {
    it('espacio abajo suficiente usa trigger.bottom + gap', () => {
        var api = loadPlacement();
        var result = api.resolveOptionsMenuPlacement({
            triggerRect: {
                top: 100,
                left: 300,
                right: 400,
                bottom: 120,
                width: 100,
                height: 20
            },
            menuRect: {
                width: 192,
                height: 154
            },
            viewportWidth: 1024,
            viewportHeight: 800,
            margin: 8,
            gap: 8
        });

        assert.equal(result.mode, 'down');
        assert.equal(result.top, 128);
        assert.equal(result.left, 208);
    });

    it('sin espacio abajo y con espacio arriba abre hacia arriba', () => {
        var api = loadPlacement();
        var result = api.resolveOptionsMenuPlacement({
            triggerRect: {
                top: 700,
                left: 300,
                right: 400,
                bottom: 728,
                width: 100,
                height: 28
            },
            menuRect: {
                width: 192,
                height: 154
            },
            viewportWidth: 1024,
            viewportHeight: 800,
            margin: 8,
            gap: 8
        });

        assert.equal(result.mode, 'up');
        assert.equal(result.top, 538);
    });

    it('sin espacio completo clamp dentro del viewport', () => {
        var api = loadPlacement();
        var result = api.resolveOptionsMenuPlacement({
            triggerRect: {
                top: 360,
                left: 300,
                right: 400,
                bottom: 388,
                width: 100,
                height: 28
            },
            menuRect: {
                width: 192,
                height: 500
            },
            viewportWidth: 1024,
            viewportHeight: 400,
            margin: 8,
            gap: 8
        });

        assert.equal(result.mode, 'clamped');
        assert.equal(result.top, 8);
    });

    it('alineación derecha con clamp horizontal', () => {
        var api = loadPlacement();
        var result = api.resolveOptionsMenuPlacement({
            triggerRect: {
                top: 100,
                left: 10,
                right: 20,
                bottom: 120,
                width: 10,
                height: 20
            },
            menuRect: {
                width: 192,
                height: 120
            },
            viewportWidth: 300,
            viewportHeight: 800,
            margin: 8,
            gap: 8
        });

        assert.equal(result.left, 8);
    });

    it('reset limpia estilos inline y restaura top-full mt-2', () => {
        var api = loadPlacement();
        var menu = {
            style: {
                position: 'fixed',
                top: '10px',
                left: '20px',
                right: '30px',
                bottom: '40px',
                zIndex: '70'
            },
            classList: {
                classes: ['bottom-full', 'mb-2'],
                remove: function (cls) {
                    this.classes = this.classes.filter(function (item) {
                        return item !== cls;
                    });
                },
                add: function () {
                    for (var i = 0; i < arguments.length; i++) {
                        if (this.classes.indexOf(arguments[i]) === -1) {
                            this.classes.push(arguments[i]);
                        }
                    }
                }
            }
        };

        api.resetOptionsMenuPlacement(menu);

        assert.equal(menu.style.position, '');
        assert.equal(menu.style.top, '');
        assert.equal(menu.style.left, '');
        assert.equal(menu.style.right, '');
        assert.equal(menu.style.bottom, '');
        assert.equal(menu.style.zIndex, '');
        assert.ok(menu.classList.classes.indexOf('top-full') !== -1);
        assert.ok(menu.classList.classes.indexOf('mt-2') !== -1);
        assert.ok(menu.classList.classes.indexOf('bottom-full') === -1);
    });

    it('positionOptionsMenu aplica fixed y coordenadas', () => {
        var context = {
            window: {},
            module: { exports: {} },
            innerWidth: 1024,
            innerHeight: 800,
            document: {
                documentElement: {
                    clientWidth: 1024,
                    clientHeight: 800
                }
            }
        };
        context.window = context;
        context.globalThis = context;

        vm.runInNewContext(placementSrc, context, { filename: placementPath });
        var api = context.module.exports;

        var menu = {
            style: {},
            classList: {
                classes: ['top-full', 'mt-2'],
                remove: function () {
                    var args = Array.prototype.slice.call(arguments);
                    this.classes = this.classes.filter(function (item) {
                        return args.indexOf(item) === -1;
                    });
                },
                add: function () {
                    for (var i = 0; i < arguments.length; i++) {
                        if (this.classes.indexOf(arguments[i]) === -1) {
                            this.classes.push(arguments[i]);
                        }
                    }
                }
            },
            getBoundingClientRect: function () {
                return {
                    top: 0,
                    left: 0,
                    right: 192,
                    bottom: 154,
                    width: 192,
                    height: 154
                };
            }
        };
        var trigger = {
            getBoundingClientRect: function () {
                return {
                    top: 200,
                    left: 400,
                    right: 500,
                    bottom: 228,
                    width: 100,
                    height: 28
                };
            }
        };

        api.positionOptionsMenu(menu, trigger);

        assert.equal(menu.style.position, 'fixed');
        assert.equal(menu.style.zIndex, '70');
        assert.equal(menu.style.right, 'auto');
        assert.equal(menu.style.top, '236px');
        assert.equal(menu.style.left, '308px');
    });
});
