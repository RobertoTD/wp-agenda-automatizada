'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const contextPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialFastAppointmentContext.js'
);
const modalPath = path.join(
    __dirname,
    '../../includes/admin/ui/modals/fastappointment/fastappointment.js'
);
const contextSrc = fs.readFileSync(contextPath, 'utf8');
const modalSrc = fs.readFileSync(modalPath, 'utf8');

const VALID_PAYLOAD = {
    tutorialId: 'create_test_appointment_v1',
    stepId: 'create_test_appointment',
    source: 'tutorial'
};

function makeTemplate(id, html) {
    var container = {
        innerHTML: html,
        appendChild: function () {},
        cloneNode: function () {
            return {
                innerHTML: html
            };
        }
    };

    return {
        id: id,
        content: container
    };
}

function loadModal(options) {
    var opts = options || {};
    var modalClosedEvents = [];
    var openModalCalls = [];
    var initCalls = [];

    var modalRoot = {
        classList: {
            contains: function (name) {
                return name === 'hidden' ? !!opts.modalHidden : false;
            }
        }
    };

    var bodyTemplate = makeTemplate(
        'aa-fastappointment-modal-template',
        '<form id="aa-fastappointment-form"></form>'
    );
    var footerTemplate = makeTemplate(
        'aa-fastappointment-modal-footer-template',
        '<div></div>'
    );

    var context = {
        window: {},
        document: {
            getElementById: function (id) {
                if (id === 'aa-modal-root') {
                    return modalRoot;
                }
                if (id === 'aa-fastappointment-modal-template') {
                    return bodyTemplate;
                }
                if (id === 'aa-fastappointment-modal-footer-template') {
                    return footerTemplate;
                }
                if (id === 'aa-fastappointment-form') {
                    return opts.modalHidden ? null : { id: 'aa-fastappointment-form' };
                }
                return null;
            },
            createElement: function (tag) {
                return {
                    tagName: tag.toUpperCase(),
                    innerHTML: '',
                    appendChild: function (child) {
                        this.innerHTML += child.innerHTML || '';
                    }
                };
            },
            addEventListener: function () {},
            dispatchEvent: function (event) {
                if (event && event.type === 'aa:fastappointment:modal-closed') {
                    modalClosedEvents.push(event);
                }
                return true;
            }
        },
        console: {
            log: function () {},
            warn: function () {},
            error: function () {}
        },
        setTimeout: function (fn) {
            fn();
            return 1;
        },
        clearTimeout: function () {},
        CustomEvent: function (type) {
            this.type = type;
        },
        MutationObserver: function (callback) {
            this.callback = callback;
            this.observe = function () {};
            this.disconnect = function () {};
        }
    };

    context.window = context;
    context.window.AAAdmin = {
        openModal: function (payload) {
            openModalCalls.push(payload);
            opts.modalHidden = false;
        }
    };
    context.window.AdminFastappointmentController = {
        init: function(opts) {
            initCalls.push(opts || {});
            return { destroy: function() {} };
        }
    };

    vm.runInNewContext(contextSrc, context, { filename: contextPath });
    vm.runInNewContext(modalSrc, context, { filename: modalPath });

    return {
        FastAppointmentModal: context.window.FastAppointmentModal,
        TutorialFastAppointmentContext: context.window.TutorialFastAppointmentContext,
        metrics: {
            get openModalCalls() { return openModalCalls; },
            get modalClosedEvents() { return modalClosedEvents; },
            get initCalls() { return initCalls; }
        },
        setModalHidden: function (hidden) {
            opts.modalHidden = hidden;
        },
        triggerObserver: function () {
            if (context.window.FastAppointmentModal.modalObserver) {
                context.window.FastAppointmentModal.modalObserver.callback();
            }
        }
    };
}

describe('FastAppointmentModal tutorial context B1', () => {
    it('open sin contexto activo deja snapshot null', () => {
        var env = loadModal();
        env.FastAppointmentModal.open();

        assert.equal(env.FastAppointmentModal._tutorialContext, null);
        assert.equal(env.metrics.openModalCalls.length, 1);
    });

    it('open con contexto activo guarda snapshot', () => {
        var env = loadModal();
        env.TutorialFastAppointmentContext.activate(VALID_PAYLOAD);
        env.FastAppointmentModal.open();

        assert.deepEqual(
            Object.assign({}, env.FastAppointmentModal._tutorialContext),
            VALID_PAYLOAD
        );
    });

    it('cierre del modal limpia contexto y snapshot', () => {
        var env = loadModal();
        env.TutorialFastAppointmentContext.activate(VALID_PAYLOAD);
        env.FastAppointmentModal.open();

        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
        assert.deepEqual(
            Object.assign({}, env.FastAppointmentModal._tutorialContext),
            VALID_PAYLOAD
        );

        env.setModalHidden(true);
        env.triggerObserver();

        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);
        assert.equal(env.FastAppointmentModal._tutorialContext, null);
        assert.equal(env.metrics.modalClosedEvents.length, 1);
    });

    it('open con contexto activo pasa snapshot al controller', () => {
        var env = loadModal();
        env.TutorialFastAppointmentContext.activate(VALID_PAYLOAD);
        env.FastAppointmentModal.open();

        assert.equal(env.metrics.initCalls.length, 1);
        assert.deepEqual(Object.assign({}, env.metrics.initCalls[0].tutorialContext), VALID_PAYLOAD);
    });

    it('open sin contexto activo pasa tutorialContext null', () => {
        var env = loadModal();
        env.FastAppointmentModal.open();

        assert.equal(env.metrics.initCalls.length, 1);
        assert.equal(env.metrics.initCalls[0].tutorialContext, null);
    });

    it('segundo open sin reactivate no hereda contexto', () => {
        var env = loadModal();
        env.TutorialFastAppointmentContext.activate(VALID_PAYLOAD);
        env.FastAppointmentModal.open();

        env.setModalHidden(true);
        env.triggerObserver();

        env.FastAppointmentModal.open();
        assert.equal(env.FastAppointmentModal._tutorialContext, null);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);
    });
});
