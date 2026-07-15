'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/dashboard/dashboard-module.js');

globalThis.window = globalThis;

const hooks = require(modulePath);

function makeClassList(initialClasses) {
    var classes = Array.isArray(initialClasses) ? initialClasses.slice() : [];

    return {
        classes: classes,
        add: function () {
            Array.prototype.forEach.call(arguments, function (cls) {
                if (classes.indexOf(cls) === -1) {
                    classes.push(cls);
                }
            });
        },
        remove: function () {
            Array.prototype.forEach.call(arguments, function (cls) {
                var idx = classes.indexOf(cls);
                if (idx !== -1) {
                    classes.splice(idx, 1);
                }
            });
        },
        toggle: function (cls, force) {
            var has = classes.indexOf(cls) !== -1;
            var next = typeof force === 'boolean' ? force : !has;

            if (next && !has) {
                classes.push(cls);
            } else if (!next && has) {
                var idx = classes.indexOf(cls);
                if (idx !== -1) {
                    classes.splice(idx, 1);
                }
            }
        }
    };
}

function makeElement(id, options) {
    var opts = options || {};

    return {
        id: id,
        classList: makeClassList(opts.classes || []),
        innerHTML: opts.innerHTML || '',
        textContent: opts.textContent || '',
        style: opts.style || {},
        disabled: !!opts.disabled
    };
}

function makeAlertsTestDom() {
    var alertsInner = {
        innerHTML: '',
        appendChild: function (node) {
            if (node && node.innerHTML) {
                this.innerHTML += node.innerHTML;
            }
        }
    };

    var alertsContainer = {
        id: 'aa-dash-alerts',
        querySelector: function (sel) {
            return sel === '.space-y-2' ? alertsInner : null;
        }
    };

    var section = makeElement('aa-dash-alerts-section', { classes: ['hidden'] });

    return {
        section: section,
        alertsInner: alertsInner,
        alertsContainer: alertsContainer
    };
}

describe('dashboard-module alerts', () => {
    let alertsDom;
    let originalDocument;
    let originalCreateElement;

    beforeEach(() => {
        alertsDom = makeAlertsTestDom();

        originalDocument = globalThis.document;
        originalCreateElement = globalThis.document && globalThis.document.createElement;

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-dash-alerts-section') {
                    return alertsDom.section;
                }

                if (id === 'aa-dash-alerts') {
                    return alertsDom.alertsContainer;
                }

                return null;
            },
            createElement: function () {
                return {
                    className: '',
                    innerHTML: ''
                };
            }
        };

        globalThis.window = globalThis;
        globalThis.window.self = globalThis.window;
        globalThis.window.top = globalThis.window;
        globalThis.window.requestAnimationFrame = function (cb) {
            cb();
        };
    });

    afterEach(() => {
        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }

        if (originalCreateElement === undefined && globalThis.document) {
            delete globalThis.document.createElement;
        }
    });

    it('cero alertas oculta la sección y no muestra mensaje vacío', () => {
        hooks.renderAlertsData({
            pendingTodayRemaining: 0,
            pendingNext15Days: 0
        });

        assert.equal(alertsDom.section.classList.classes.indexOf('hidden'), 0);
        assert.doesNotMatch(alertsDom.alertsInner.innerHTML, /Sin alertas por ahora/);
    });

    it('alerta hoy muestra la sección y renderiza contenido', () => {
        hooks.renderAlertsData({
            pendingTodayRemaining: 2,
            pendingNext15Days: 0
        });

        assert.equal(alertsDom.section.classList.classes.indexOf('hidden'), -1);
        assert.match(alertsDom.alertsInner.innerHTML, /2 citas sin confirmar para hoy/);
    });

    it('alerta próximos 15 días muestra la sección y renderiza contenido', () => {
        hooks.renderAlertsData({
            pendingTodayRemaining: 0,
            pendingNext15Days: 1
        });

        assert.equal(alertsDom.section.classList.classes.indexOf('hidden'), -1);
        assert.match(alertsDom.alertsInner.innerHTML, /1 cita sin confirmar en los próximos 15 días/);
    });
});
