'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');

const toastHelper = require('../../assets/js/ui/taskCompletedToast.js');

function makeDomButton(tree) {
    return {
        closest: function (selector) {
            return typeof tree.closest === 'function'
                ? tree.closest(selector)
                : null;
        }
    };
}

function makeExecutableDom(taskTitle, listTitle) {
    var listCard = {
        className: 'aa-executable-list-card',
        querySelector: function (selector) {
            if (selector === 'h4') {
                return { textContent: listTitle };
            }

            return null;
        },
        closest: function (selector) {
            if (selector.indexOf('aa-executable-list-card') !== -1 || selector.indexOf('aa-task-list-card') !== -1) {
                return listCard;
            }

            return null;
        }
    };

    var item = {
        className: 'aa-executable-item',
        querySelector: function (selector) {
            if (selector === '.aa-executable-item-title') {
                return { textContent: taskTitle };
            }

            return null;
        },
        closest: function (selector) {
            if (selector === '.aa-executable-item') {
                return item;
            }

            if (selector.indexOf('aa-executable-list-card') !== -1 || selector.indexOf('aa-task-list-card') !== -1) {
                return listCard;
            }

            return null;
        }
    };

    return makeDomButton(item);
}

describe('taskCompletedToast', () => {
    describe('buildMessage', () => {
        it('incluye título y lista', () => {
            assert.equal(
                toastHelper.buildMessage({ taskTitle: 'Llamar a Ana', listTitle: 'Seguimiento' }),
                '"Llamar a Ana" en Lista: Seguimiento'
            );
        });

        it('incluye solo título si no hay lista', () => {
            assert.equal(
                toastHelper.buildMessage({ taskTitle: 'Llamar a Ana', listTitle: '' }),
                '"Llamar a Ana"'
            );
        });

        it('usa fallback si no hay título', () => {
            assert.equal(
                toastHelper.buildMessage({ taskTitle: '', listTitle: 'Seguimiento' }),
                toastHelper.FALLBACK_MESSAGE
            );
        });
    });

    describe('enrichContext', () => {
        it('completa título desde respuesta AJAX', () => {
            assert.deepEqual(
                toastHelper.enrichContext({ taskTitle: '', listTitle: 'Foco' }, { task: { title: 'Desde API' } }),
                { taskTitle: 'Desde API', listTitle: 'Foco' }
            );
        });
    });

    describe('resolveFromButton', () => {
        it('resuelve título y lista desde feed executable', () => {
            var button = makeExecutableDom('Llamar a Ana', 'Seguimiento');

            assert.deepEqual(
                toastHelper.resolveFromButton(button),
                { taskTitle: 'Llamar a Ana', listTitle: 'Seguimiento' }
            );
        });

        it('devuelve vacío si no hay DOM reconocible', () => {
            assert.deepEqual(
                toastHelper.resolveFromButton({ closest: function () { return null; } }),
                { taskTitle: '', listTitle: '' }
            );
        });
    });

    describe('show', () => {
        let originalToast;
        let showCalls;

        beforeEach(() => {
            showCalls = [];
            originalToast = globalThis.AAAdmin;

            globalThis.AAAdmin = {
                toast: {
                    show: function (notification) {
                        showCalls.push(notification);
                    }
                }
            };
        });

        afterEach(() => {
            globalThis.AAAdmin = originalToast;
        });

        it('llama AAAdmin.toast.show con severity success y título fijo', () => {
            toastHelper.show({ taskTitle: 'Llamar a Ana', listTitle: 'Seguimiento' });

            assert.equal(showCalls.length, 1);
            assert.equal(showCalls[0].severity, 'success');
            assert.equal(showCalls[0].title, toastHelper.TOAST_TITLE);
            assert.equal(showCalls[0].message, '"Llamar a Ana" en Lista: Seguimiento');
        });

        it('no rompe si AAAdmin.toast no existe', () => {
            globalThis.AAAdmin = {};

            assert.doesNotThrow(function () {
                toastHelper.show({ taskTitle: 'Tarea' });
            });
        });
    });
});
