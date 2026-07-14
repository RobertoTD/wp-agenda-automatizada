'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { URL } = require('node:url');
const { describe, it, beforeEach } = require('node:test');

const swPath = path.join(__dirname, '../../includes/admin/ui/pwa/sw.js');
const SCOPE = 'https://tenant.example.com/wp-admin/';
const DEFAULT_MODULE_URL = 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=calendar';
const API_BASE = 'https://api.deoia.com';
const VALIDATE_URL = API_BASE + '/push/task-execution-available-notifications/validate';

const APPOINTMENT_PUSH_TYPE = 'upcoming_confirmed_appointment';
const TASK_PUSH_TYPE = 'task_execution_available';
const EXPECTED_OLD = '2026-07-12T20:00:00.000Z';
const EXPECTED_NEW = '2026-07-13T20:00:00.000Z';

function loadServiceWorker(options) {
    options = options || {};

    var handlers = {};
    var showNotificationCalls = [];
    var matchAllClients = options.clients || [];
    var openWindowCalls = [];
    var claimCalls = 0;
    var activeNotifications = options.notifications || [];
    var claimPromise = Promise.resolve();
    var fetchCalls = [];
    var subscription = options.subscription === undefined
        ? {
            toJSON: function () {
                return {
                    endpoint: 'https://push.example.test/sub/1',
                    keys: { p256dh: 'p256dh-key', auth: 'auth-key' }
                };
            }
        }
        : options.subscription;

    var registration = {
        scope: options.scope || SCOPE,
        showNotification: function (title, opts) {
            showNotificationCalls.push({ title: title, opts: opts, at: Date.now() });
            if (typeof options.onShow === 'function') {
                options.onShow(title, opts);
            }
            return Promise.resolve();
        },
        getNotifications: function () {
            if (typeof options.getNotifications === 'function') {
                return options.getNotifications();
            }

            return Promise.resolve(activeNotifications);
        },
        pushManager: {
            getSubscription: function () {
                if (typeof options.getSubscription === 'function') {
                    return options.getSubscription();
                }

                return Promise.resolve(subscription);
            }
        }
    };

    var self = {
        __AA_PUSH_API_BASE__: options.apiBase === undefined ? API_BASE : options.apiBase,
        registration: registration,
        clients: {
            matchAll: function () {
                return Promise.resolve(matchAllClients);
            },
            openWindow: function (url) {
                openWindowCalls.push(url);
                return Promise.resolve(null);
            },
            claim: function () {
                claimCalls += 1;
                return claimPromise;
            }
        },
        skipWaiting: function () {},
        addEventListener: function (type, handler) {
            handlers[type] = handler;
        }
    };

    var defaultFetch = function (url, requestOptions) {
        fetchCalls.push({ url: url, options: requestOptions || {} });

        if (typeof options.fetchImpl === 'function') {
            return options.fetchImpl(url, requestOptions, fetchCalls);
        }

        return Promise.resolve({
            status: 200,
            json: function () {
                return Promise.resolve({
                    ok: true,
                    results: []
                });
            }
        });
    };

    var context = {
        self: self,
        console: console,
        fetch: defaultFetch,
        URL: URL,
        Date: options.Date || Date,
        AbortController: options.AbortController || AbortController,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout,
        Number: Number,
        Math: Math,
        JSON: JSON,
        Promise: Promise,
        Array: Array,
        Object: Object,
        String: String
    };

    vm.createContext(context);
    vm.runInContext(fs.readFileSync(swPath, 'utf8'), context);

    return {
        handlers: handlers,
        fetchCalls: fetchCalls,
        get showNotificationCalls() {
            return showNotificationCalls;
        },
        set clientsList(clients) {
            matchAllClients = clients;
        },
        get openWindowCalls() {
            return openWindowCalls;
        },
        get claimCalls() {
            return claimCalls;
        },
        set notifications(list) {
            activeNotifications = list;
        }
    };
}

function createPushEvent(payload) {
    var event = {
        waitUntil: function (promise) {
            event._waitUntilPromise = promise;
            return promise;
        }
    };

    if (payload === null) {
        event.data = null;
        return event;
    }

    if (payload === undefined) {
        return event;
    }

    if (payload === 'invalid-json') {
        event.data = {
            json: function () {
                throw new Error('Invalid JSON');
            }
        };
        return event;
    }

    event.data = {
        json: function () {
            return payload;
        }
    };

    return event;
}

async function runPush(sw, payload) {
    var event = createPushEvent(payload);
    sw.handlers.push(event);
    await event._waitUntilPromise;
    return sw.showNotificationCalls[sw.showNotificationCalls.length - 1];
}

async function runActivate(sw) {
    var event = {
        waitUntil: function (promise) {
            event._waitUntilPromise = promise;
            return promise;
        }
    };

    sw.handlers.activate(event);
    await event._waitUntilPromise;
    return event;
}

async function runMessage(sw, data) {
    var event = {
        data: data,
        waitUntil: function (promise) {
            event._waitUntilPromise = promise;
            return promise;
        }
    };

    sw.handlers.message(event);
    if (event._waitUntilPromise) {
        await event._waitUntilPromise;
    }
    return event;
}

function createMockNotification(data, options) {
    options = options || {};
    var closed = false;

    return {
        data: data,
        close: function () {
            if (options.closeThrows) {
                throw new Error('close failed');
            }
            closed = true;
        },
        wasClosed: function () {
            return closed;
        }
    };
}

function createNotificationClickEvent(notification) {
    var closed = false;
    var notif = Object.assign({}, notification, {
        close: function () {
            closed = true;
        }
    });

    return {
        notification: notif,
        wasClosed: function () {
            return closed;
        },
        waitUntil: function (promise) {
            this._waitUntilPromise = promise;
            return promise;
        }
    };
}

async function runNotificationClick(sw, notification, clientsList) {
    if (clientsList) {
        sw.clientsList = clientsList;
    }

    var event = createNotificationClickEvent(notification);
    sw.handlers.notificationclick(event);
    await event._waitUntilPromise;
    return event;
}

function taskPayload(overrides) {
    overrides = overrides || {};
    return {
        title: overrides.title || 'Es momento de realizar esta tarea',
        body: overrides.body || 'Tarea · Lista',
        tag: overrides.tag || 'task-execution-available-12',
        data: {
            type: TASK_PUSH_TYPE,
            taskId: overrides.taskId === undefined ? 12 : overrides.taskId,
            expectedExecutionAvailableAt: overrides.expectedExecutionAvailableAt || EXPECTED_NEW,
            url: overrides.url || DEFAULT_MODULE_URL
        }
    };
}

function resultsFor(map) {
    return {
        ok: true,
        results: Object.keys(map).map(function (requestId) {
            return { requestId: requestId, status: map[requestId] };
        })
    };
}

describe('PWA service worker push handlers', () => {
    var sw;

    beforeEach(() => {
        sw = loadServiceWorker();
    });

    it('valid JSON push calls showNotification with title, body, tag and safe data.url', async () => {
        var call = await runPush(sw, {
            title: 'Cita de prueba en 15 minutos',
            body: 'Cliente de Prueba · Análisis Clínicos',
            tag: 'deoia-first-push-test',
            data: {
                url: 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=dashboard'
            }
        });

        assert.equal(call.title, 'Cita de prueba en 15 minutos');
        assert.equal(call.opts.body, 'Cliente de Prueba · Análisis Clínicos');
        assert.equal(call.opts.tag, 'deoia-first-push-test');
        assert.equal(
            call.opts.data.url,
            'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=dashboard'
        );
    });

    it('absent payload uses generic fallback notification without throwing', async () => {
        var call = await runPush(sw, null);

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.body, 'Tienes una nueva notificación.');
        assert.equal(call.opts.tag, 'deoia-web-push');
        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
    });

    it('invalid JSON uses generic fallback notification without throwing', async () => {
        var call = await runPush(sw, 'invalid-json');

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.body, 'Tienes una nueva notificación.');
        assert.equal(call.opts.tag, 'deoia-web-push');
        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
    });

    it('primitive payload uses generic fallback notification', async () => {
        var call = await runPush(sw, 'hello');

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
    });

    it('array payload uses generic fallback notification', async () => {
        var call = await runPush(sw, ['ignored']);

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
    });

    it('notificationclick closes notification and focuses existing DEOIA window', async () => {
        var focused = false;
        var client = {
            url: 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=calendar',
            focus: function () {
                focused = true;
                return Promise.resolve(client);
            }
        };

        var event = await runNotificationClick(
            sw,
            {
                data: {
                    url: 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=dashboard'
                }
            },
            [client]
        );

        assert.equal(event.wasClosed(), true);
        assert.equal(focused, true);
        assert.equal(sw.openWindowCalls.length, 0);
    });

    it('notificationclick opens target URL when no DEOIA window exists', async () => {
        await runNotificationClick(
            sw,
            {
                data: {
                    url: 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=calendar'
                }
            },
            []
        );

        assert.equal(sw.openWindowCalls.length, 1);
        assert.equal(sw.openWindowCalls[0], DEFAULT_MODULE_URL);
    });

    it('notificationclick uses default module fallback for external or invalid URLs', async () => {
        await runNotificationClick(
            sw,
            {
                data: {
                    url: 'https://evil.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=dashboard'
                }
            },
            []
        );

        assert.equal(sw.openWindowCalls.length, 1);
        assert.equal(sw.openWindowCalls[0], DEFAULT_MODULE_URL);

        sw.openWindowCalls.length = 0;

        await runNotificationClick(
            sw,
            {
                data: {
                    url: 'http://['
                }
            },
            []
        );

        assert.equal(sw.openWindowCalls.length, 1);
        assert.equal(sw.openWindowCalls[0], DEFAULT_MODULE_URL);
    });

    it('push resolves external data.url to default module fallback in notification data', async () => {
        var call = await runPush(sw, {
            title: 'Test',
            body: 'Body',
            tag: 'tag',
            data: {
                url: 'https://evil.example.com/steal'
            }
        });

        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
    });

    it('shows valid upcoming appointment push with type and expiresAt in notification data', async () => {
        var call = await runPush(sw, {
            title: 'Cita en 15 minutos',
            body: 'Cliente · Servicio',
            tag: 'upcoming-confirmed-appointment-123',
            data: {
                type: APPOINTMENT_PUSH_TYPE,
                expiresAt: '2099-01-01T00:00:00.000Z',
                url: DEFAULT_MODULE_URL
            }
        });

        assert.equal(call.opts.tag, 'upcoming-confirmed-appointment-123');
        assert.equal(call.opts.data.type, APPOINTMENT_PUSH_TYPE);
        assert.equal(call.opts.data.expiresAt, '2099-01-01T00:00:00.000Z');
        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
    });

    it('does not show expired upcoming appointment push', async () => {
        var fixedDate = class extends Date {
            constructor() {
                super('2026-07-09T22:30:00.000Z');
            }

            static now() {
                return new Date('2026-07-09T22:30:00.000Z').getTime();
            }
        };

        sw = loadServiceWorker({ Date: fixedDate });

        var call = await runPush(sw, {
            title: 'Cita ahora',
            body: 'Cliente · Servicio',
            tag: 'upcoming-confirmed-appointment-123',
            data: {
                type: APPOINTMENT_PUSH_TYPE,
                expiresAt: '2026-07-09T22:00:00.000Z',
                url: DEFAULT_MODULE_URL
            }
        });

        assert.equal(call, undefined);
        assert.equal(sw.showNotificationCalls.length, 0);
    });

    it('keeps generic and legacy task pushes without type as showable without validate identity', async () => {
        var call = await runPush(sw, {
            title: 'Es momento de realizar esta tarea',
            body: 'Tarea · Lista',
            tag: 'task-execution-available-42',
            data: {
                url: DEFAULT_MODULE_URL
            }
        });

        assert.equal(call.opts.tag, 'task-execution-available-42');
        assert.equal(call.opts.data.url, DEFAULT_MODULE_URL);
        assert.equal('type' in call.opts.data, false);
        assert.equal('expiresAt' in call.opts.data, false);
    });

    it('cleanup closes only expired appointment notifications', async () => {
        var expiredAppointment = createMockNotification({
            type: APPOINTMENT_PUSH_TYPE,
            expiresAt: '2020-01-01T00:00:00.000Z',
            url: DEFAULT_MODULE_URL
        });
        var validAppointment = createMockNotification({
            type: APPOINTMENT_PUSH_TYPE,
            expiresAt: '2099-01-01T00:00:00.000Z',
            url: DEFAULT_MODULE_URL
        });
        var taskNotification = createMockNotification({
            url: DEFAULT_MODULE_URL
        });

        sw = loadServiceWorker({
            notifications: [expiredAppointment, validAppointment, taskNotification]
        });

        await runPush(sw, {
            title: 'Cita en 5 minutos',
            body: 'Cliente · Servicio',
            tag: 'upcoming-confirmed-appointment-999',
            data: {
                type: APPOINTMENT_PUSH_TYPE,
                expiresAt: '2099-01-01T00:00:00.000Z',
                url: DEFAULT_MODULE_URL
            }
        });

        assert.equal(expiredAppointment.wasClosed(), true);
        assert.equal(validAppointment.wasClosed(), false);
        assert.equal(taskNotification.wasClosed(), false);
        assert.equal(sw.showNotificationCalls.length, 1);
    });

    it('cleanup failure does not block a valid new appointment notification', async () => {
        sw = loadServiceWorker({
            getNotifications: function () {
                return Promise.reject(new Error('getNotifications failed'));
            }
        });

        var call = await runPush(sw, {
            title: 'Cita en 5 minutos',
            body: 'Cliente · Servicio',
            tag: 'upcoming-confirmed-appointment-999',
            data: {
                type: APPOINTMENT_PUSH_TYPE,
                expiresAt: '2099-01-01T00:00:00.000Z',
                url: DEFAULT_MODULE_URL
            }
        });

        assert.equal(call.opts.tag, 'upcoming-confirmed-appointment-999');
        assert.equal(sw.showNotificationCalls.length, 1);
    });

    it('activate still claims clients when cleanup fails', async () => {
        sw = loadServiceWorker({
            getNotifications: function () {
                return Promise.reject(new Error('getNotifications failed'));
            }
        });

        await runActivate(sw);

        assert.equal(sw.claimCalls, 1);
    });
});

describe('PWA service worker task push validation', () => {
    it('shows eligible incoming task push and stores identity in notification data', async () => {
        var sw = loadServiceWorker({
            fetchImpl: function () {
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor({ incoming: 'eligible' }));
                    }
                });
            }
        });

        var call = await runPush(sw, taskPayload());

        assert.equal(sw.fetchCalls.length, 1);
        assert.equal(sw.fetchCalls[0].url, VALIDATE_URL);
        assert.equal(call.opts.data.type, TASK_PUSH_TYPE);
        assert.equal(call.opts.data.taskId, 12);
        assert.equal(call.opts.data.expectedExecutionAvailableAt, EXPECTED_NEW);
        assert.equal(sw.showNotificationCalls.length, 1);
    });

    it('does not show stale or ineligible incoming task push', async () => {
        var swStale = loadServiceWorker({
            fetchImpl: function () {
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor({ incoming: 'stale' }));
                    }
                });
            }
        });
        assert.equal(await runPush(swStale, taskPayload()), undefined);
        assert.equal(swStale.showNotificationCalls.length, 0);

        var swIneligible = loadServiceWorker({
            fetchImpl: function () {
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor({ incoming: 'ineligible' }));
                    }
                });
            }
        });
        assert.equal(await runPush(swIneligible, taskPayload()), undefined);
        assert.equal(swIneligible.showNotificationCalls.length, 0);
    });

    it('shows incoming task on timeout, network error, 401 or 503', async () => {
        async function assertShows(fetchImpl) {
            var sw = loadServiceWorker({ fetchImpl: fetchImpl });
            var call = await runPush(sw, taskPayload());
            assert.ok(call);
            assert.equal(sw.showNotificationCalls.length, 1);
        }

        await assertShows(function (_url, opts) {
            return new Promise(function (_resolve, reject) {
                if (opts && opts.signal) {
                    opts.signal.addEventListener('abort', function () {
                        reject(Object.assign(new Error('aborted'), { name: 'AbortError' }));
                    });
                }
            });
        });

        await assertShows(function () {
            return Promise.reject(new Error('network'));
        });

        await assertShows(function () {
            return Promise.resolve({
                status: 401,
                json: function () {
                    return Promise.resolve({ ok: false, error: 'unauthorized' });
                }
            });
        });

        await assertShows(function () {
            return Promise.resolve({
                status: 503,
                json: function () {
                    return Promise.resolve({ ok: false, error: 'validation_unavailable' });
                }
            });
        });
    });

    it('closes obsolete existing task and shows eligible incoming', async () => {
        var obsolete = createMockNotification({
            type: TASK_PUSH_TYPE,
            taskId: 12,
            expectedExecutionAvailableAt: EXPECTED_OLD,
            url: DEFAULT_MODULE_URL
        });

        var sw = loadServiceWorker({
            notifications: [obsolete],
            fetchImpl: function () {
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor({
                            'n-0': 'stale',
                            incoming: 'eligible'
                        }));
                    }
                });
            }
        });

        var call = await runPush(sw, taskPayload());
        assert.equal(obsolete.wasClosed(), true);
        assert.ok(call);
        assert.equal(sw.showNotificationCalls.length, 1);
    });

    it('caps batch at 50 with priority for incoming and leaves extras intact', async () => {
        var notifications = [];
        var i;
        for (i = 0; i < 60; i += 1) {
            notifications.push(createMockNotification({
                type: TASK_PUSH_TYPE,
                taskId: 100 + i,
                expectedExecutionAvailableAt: EXPECTED_OLD,
                url: DEFAULT_MODULE_URL
            }));
        }

        var capturedBody = null;
        var sw = loadServiceWorker({
            notifications: notifications,
            fetchImpl: function (_url, opts) {
                capturedBody = JSON.parse(opts.body);
                var map = { incoming: 'eligible' };
                capturedBody.tasks.forEach(function (task) {
                    if (task.requestId !== 'incoming') {
                        map[task.requestId] = 'stale';
                    }
                });
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor(map));
                    }
                });
            }
        });

        await runPush(sw, taskPayload());

        assert.equal(capturedBody.tasks.length, 50);
        assert.equal(capturedBody.tasks[49].requestId, 'incoming');
        assert.equal(notifications[0].wasClosed(), true);
        assert.equal(notifications[48].wasClosed(), true);
        assert.equal(notifications[49].wasClosed(), false);
        assert.equal(notifications[59].wasClosed(), false);
        assert.equal(sw.showNotificationCalls.length, 1);
    });

    it('ignores legacy notifications without task identity', async () => {
        var legacy = createMockNotification({ url: DEFAULT_MODULE_URL });
        var capturedBody = null;
        var sw = loadServiceWorker({
            notifications: [legacy],
            fetchImpl: function (_url, opts) {
                capturedBody = JSON.parse(opts.body);
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor({ incoming: 'eligible' }));
                    }
                });
            }
        });

        await runPush(sw, taskPayload());
        assert.equal(capturedBody.tasks.length, 1);
        assert.equal(capturedBody.tasks[0].requestId, 'incoming');
        assert.equal(legacy.wasClosed(), false);
    });

    it('appointment and generic pushes do not wait for task validate before show', async () => {
        var resolveValidate;
        var validateStarted = false;
        var shownBeforeValidateResolved = false;

        var sw = loadServiceWorker({
            notifications: [
                createMockNotification({
                    type: TASK_PUSH_TYPE,
                    taskId: 1,
                    expectedExecutionAvailableAt: EXPECTED_OLD,
                    url: DEFAULT_MODULE_URL
                })
            ],
            onShow: function () {
                if (!resolveValidate) {
                    // fetch may not have assigned resolveValidate yet on same tick; mark when validate pending
                }
                shownBeforeValidateResolved = validateStarted && typeof resolveValidate === 'function'
                    ? true
                    : shownBeforeValidateResolved;
                if (validateStarted) {
                    shownBeforeValidateResolved = true;
                }
            },
            fetchImpl: function () {
                validateStarted = true;
                return new Promise(function (resolve) {
                    resolveValidate = function () {
                        resolve({
                            status: 200,
                            json: function () {
                                return Promise.resolve(resultsFor({ 'n-0': 'stale' }));
                            }
                        });
                    };
                });
            }
        });

        var event = createPushEvent({
            title: 'Cita',
            body: 'Body',
            tag: 'upcoming-confirmed-appointment-1',
            data: {
                type: APPOINTMENT_PUSH_TYPE,
                expiresAt: '2099-01-01T00:00:00.000Z',
                url: DEFAULT_MODULE_URL
            }
        });

        sw.handlers.push(event);

        await new Promise(function (resolve) {
            setTimeout(resolve, 20);
        });

        assert.equal(sw.showNotificationCalls.length, 1);
        assert.equal(sw.showNotificationCalls[0].opts.tag, 'upcoming-confirmed-appointment-1');
        assert.equal(validateStarted, true);
        assert.equal(typeof resolveValidate, 'function');

        resolveValidate();
        await event._waitUntilPromise;
        assert.equal(shownBeforeValidateResolved || sw.showNotificationCalls.length === 1, true);
    });

    it('task cleanup failure does not block appointment show', async () => {
        var sw = loadServiceWorker({
            fetchImpl: function () {
                return Promise.reject(new Error('validate failed'));
            },
            notifications: [
                createMockNotification({
                    type: TASK_PUSH_TYPE,
                    taskId: 9,
                    expectedExecutionAvailableAt: EXPECTED_OLD,
                    url: DEFAULT_MODULE_URL
                })
            ]
        });

        var call = await runPush(sw, {
            title: 'Cita',
            body: 'Body',
            tag: 'appt-1',
            data: {
                type: APPOINTMENT_PUSH_TYPE,
                expiresAt: '2099-01-01T00:00:00.000Z',
                url: DEFAULT_MODULE_URL
            }
        });

        assert.equal(call.opts.tag, 'appt-1');
        assert.equal(sw.showNotificationCalls.length, 1);
    });

    it('cleanup message uses the same batch flow without incoming', async () => {
        var obsolete = createMockNotification({
            type: TASK_PUSH_TYPE,
            taskId: 12,
            expectedExecutionAvailableAt: EXPECTED_OLD,
            url: DEFAULT_MODULE_URL
        });
        var capturedBody = null;
        var sw = loadServiceWorker({
            notifications: [obsolete],
            fetchImpl: function (_url, opts) {
                capturedBody = JSON.parse(opts.body);
                return Promise.resolve({
                    status: 200,
                    json: function () {
                        return Promise.resolve(resultsFor({ 'n-0': 'ineligible' }));
                    }
                });
            }
        });

        await runMessage(sw, { type: 'aa_cleanup_task_push_notifications' });
        assert.ok(capturedBody);
        assert.equal(capturedBody.tasks.length, 1);
        assert.equal(capturedBody.tasks[0].requestId, 'n-0');
        assert.equal(obsolete.wasClosed(), true);
        assert.equal(sw.showNotificationCalls.length, 0);
    });

    it('missing api base or subscription falls back to showing task push', async () => {
        var swNoApi = loadServiceWorker({ apiBase: '' });
        assert.ok(await runPush(swNoApi, taskPayload()));
        assert.equal(swNoApi.fetchCalls.length, 0);

        var swNoSub = loadServiceWorker({
            subscription: null,
            getSubscription: function () {
                return Promise.resolve(null);
            }
        });
        assert.ok(await runPush(swNoSub, taskPayload()));
        assert.equal(swNoSub.fetchCalls.length, 0);
    });
});
