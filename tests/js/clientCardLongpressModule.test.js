'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/client-card-longpress-module.js'
);
const indexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/index.php'
);
const headerPath = path.join(
    __dirname,
    '../../includes/admin/ui/shared/header.php'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const indexSrc = fs.readFileSync(indexPath, 'utf8');
const headerSrc = fs.readFileSync(headerPath, 'utf8');

function loadModule(documentStub) {
    var context = {
        window: {},
        console: console,
        document: documentStub
            ? Object.assign({
                readyState: 'complete',
                addEventListener: function () {},
                getElementById: function () { return null; }
            }, documentStub)
            : undefined,
        module: { exports: {} },
        setTimeout: setTimeout,
        clearTimeout: clearTimeout
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return context.module.exports;
}

describe('client-card-longpress-module', () => {
    it('index.php encola client-card-longpress-module.js después de clients-module', () => {
        var clientsPos = indexSrc.indexOf('clients-module.js');
        var longpressPos = indexSrc.indexOf('client-card-longpress-module.js');

        assert.ok(clientsPos !== -1, 'clients-module.js debe encolarse');
        assert.ok(longpressPos !== -1, 'client-card-longpress-module.js debe encolarse');
        assert.ok(clientsPos < longpressPos, 'longpress debe ir después de clients-module');
    });

    it('expone constantes y resolvers para header de tarjeta de cliente', () => {
        var exports = loadModule();

        assert.equal(exports.LONG_PRESS_MS, 500);
        assert.equal(exports.MOVE_TOLERANCE_PX, 10);
        assert.equal(typeof exports.resolveClientCardHeader, 'function');
        assert.equal(typeof exports.resolveClientsSectionHeader, 'function');
        assert.equal(typeof exports.resolveEditButton, 'function');
        assert.equal(typeof exports.isInteractiveTarget, 'function');
    });

    it('tools de clientes viven en header; index ya no tiene section header', () => {
        assert.match(headerSrc, /id="aa-clients-area-tools"/);
        assert.match(headerSrc, /id="aa-clients-options-trigger"/);
        assert.doesNotMatch(indexSrc, /id="aa-clients-section-header"/);
        assert.doesNotMatch(indexSrc, /id="aa-clients-area-tools"/);
    });

    it('resolveClientsSectionHeader acepta #aa-page-title si la lista está visible', () => {
        var pageTitle = { id: 'aa-page-title' };
        var listRoot = {
            id: 'aa-clients-list-root',
            classList: {
                contains: function (cls) {
                    return cls === 'hidden' ? false : false;
                }
            }
        };

        var exports = loadModule({
            getElementById: function (id) {
                return id === 'aa-clients-list-root' ? listRoot : null;
            }
        });

        pageTitle.closest = function (sel) {
            return sel === '#aa-page-title' ? pageTitle : null;
        };

        var target = {
            closest: function (sel) {
                return pageTitle.closest(sel);
            }
        };

        var outside = {
            closest: function () {
                return null;
            }
        };

        assert.equal(exports.resolveClientsSectionHeader(target), pageTitle);
        assert.equal(exports.resolveClientsSectionHeader(outside), null);
    });

    it('resolveClientsSectionHeader rechaza #aa-page-title si la lista está oculta', () => {
        var pageTitle = { id: 'aa-page-title' };
        var listRoot = {
            id: 'aa-clients-list-root',
            classList: {
                contains: function (cls) {
                    return cls === 'hidden';
                }
            }
        };

        var exports = loadModule({
            getElementById: function (id) {
                return id === 'aa-clients-list-root' ? listRoot : null;
            }
        });

        pageTitle.closest = function (sel) {
            return sel === '#aa-page-title' ? pageTitle : null;
        };

        var target = {
            closest: function (sel) {
                return pageTitle.closest(sel);
            }
        };

        assert.equal(exports.resolveClientsSectionHeader(target), null);
    });

    it('module abre create modal en long-press del título de página', () => {
        assert.match(moduleSrc, /ACTION_CREATE/);
        assert.match(moduleSrc, /ClientCreateModal\.openCreate/);
        assert.match(moduleSrc, /aa-page-title/);
    });

    it('resolveClientCardHeader solo acepta headers dentro de #aa-clients-grid', () => {
        var exports = loadModule();

        var gridHeader = { id: 'grid-header' };
        var otherHeader = { id: 'other-header' };

        var gridCard = {
            closest: function (sel) {
                return sel === '#aa-clients-grid' ? {} : null;
            }
        };
        var otherCard = {
            closest: function () {
                return null;
            }
        };

        gridHeader.closest = function (sel) {
            if (sel === '.aa-appointment-header') {
                return gridHeader;
            }
            if (sel === '.aa-appointment-card') {
                return gridCard;
            }
            return null;
        };

        otherHeader.closest = function (sel) {
            if (sel === '.aa-appointment-header') {
                return otherHeader;
            }
            if (sel === '.aa-appointment-card') {
                return otherCard;
            }
            return null;
        };

        var gridTarget = {
            closest: function (sel) {
                return sel === '.aa-appointment-header' ? gridHeader : null;
            }
        };
        var otherTarget = {
            closest: function (sel) {
                return sel === '.aa-appointment-header' ? otherHeader : null;
            }
        };

        assert.equal(exports.resolveClientCardHeader(gridTarget), gridHeader);
        assert.equal(exports.resolveClientCardHeader(otherTarget), null);
    });

    it('resolveEditButton busca .aa-btn-editar-cliente dentro de la card', () => {
        var exports = loadModule();
        var editButton = { id: 'edit-btn' };
        var card = {
            querySelector: function (sel) {
                return sel === '.aa-btn-editar-cliente' ? editButton : null;
            }
        };
        var header = {
            closest: function (sel) {
                return sel === '.aa-appointment-card' ? card : null;
            }
        };

        assert.equal(exports.resolveEditButton(header), editButton);
        assert.equal(exports.resolveEditButton({
            closest: function () { return null; }
        }), null);
    });

    it('isInteractiveTarget ignora botones y controles', () => {
        var exports = loadModule();

        assert.equal(exports.isInteractiveTarget({
            closest: function (sel) {
                return String(sel).indexOf('button') !== -1 ? {} : null;
            }
        }), true);

        assert.equal(exports.isInteractiveTarget({
            closest: function () { return null; }
        }), false);
    });

    it('longpress dispara click en .aa-btn-editar-cliente (modal editar)', () => {
        assert.match(moduleSrc, /\.aa-btn-editar-cliente/);
        assert.match(moduleSrc, /editButton\.click/);
        assert.match(moduleSrc, /#aa-clients-grid/);
        assert.match(moduleSrc, /ACTION_EDIT/);
    });
});
