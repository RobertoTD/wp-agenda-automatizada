'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { URL } = require('node:url');
const { describe, it, beforeEach } = require('node:test');

const swPath = path.join(__dirname, '../../includes/admin/ui/pwa/sw.js');
const SCOPE = 'https://tenant.example.com/wp-admin/';
const DASHBOARD_URL = 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=dashboard';

function loadServiceWorker(options) {
    options = options || {};

    var handlers = {};
    var showNotificationCalls = [];
    var matchAllClients = options.clients || [];
    var openWindowCalls = [];
    var claimPromise = Promise.resolve();

    var registration = {
        scope: options.scope || SCOPE,
        showNotification: function (title, opts) {
            showNotificationCalls.push({ title: title, opts: opts });
            return Promise.resolve();
        }
    };

    var self = {
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
                return claimPromise;
            }
        },
        skipWaiting: function () {},
        addEventListener: function (type, handler) {
            handlers[type] = handler;
        }
    };

    var context = {
        self: self,
        console: console,
        fetch: function () {
            return Promise.resolve({});
        },
        URL: URL
    };

    vm.createContext(context);
    vm.runInContext(fs.readFileSync(swPath, 'utf8'), context);

    return {
        handlers: handlers,
        get showNotificationCalls() {
            return showNotificationCalls;
        },
        set clientsList(clients) {
            matchAllClients = clients;
        },
        get openWindowCalls() {
            return openWindowCalls;
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

function createNotificationClickEvent(notification, clientsList) {
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
        },
        _clientsList: clientsList
    };
}

async function runNotificationClick(sw, notification, clientsList) {
    if (clientsList) {
        sw.clientsList = clientsList;
    }

    var event = createNotificationClickEvent(notification, clientsList);
    sw.handlers.notificationclick(event);
    await event._waitUntilPromise;
    return event;
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
        assert.equal(call.opts.data.url, DASHBOARD_URL);
    });

    it('invalid JSON uses generic fallback notification without throwing', async () => {
        var call = await runPush(sw, 'invalid-json');

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.body, 'Tienes una nueva notificación.');
        assert.equal(call.opts.tag, 'deoia-web-push');
        assert.equal(call.opts.data.url, DASHBOARD_URL);
    });

    it('primitive payload uses generic fallback notification', async () => {
        var call = await runPush(sw, 'hello');

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.data.url, DASHBOARD_URL);
    });

    it('array payload uses generic fallback notification', async () => {
        var call = await runPush(sw, ['ignored']);

        assert.equal(call.title, 'DEOIA');
        assert.equal(call.opts.data.url, DASHBOARD_URL);
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
                    url: 'https://tenant.example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=dashboard'
                }
            },
            []
        );

        assert.equal(sw.openWindowCalls.length, 1);
        assert.equal(sw.openWindowCalls[0], DASHBOARD_URL);
    });

    it('notificationclick uses dashboard fallback for external or invalid URLs', async () => {
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
        assert.equal(sw.openWindowCalls[0], DASHBOARD_URL);

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
        assert.equal(sw.openWindowCalls[0], DASHBOARD_URL);
    });

    it('push resolves external data.url to dashboard fallback in notification data', async () => {
        var call = await runPush(sw, {
            title: 'Test',
            body: 'Body',
            tag: 'tag',
            data: {
                url: 'https://evil.example.com/steal'
            }
        });

        assert.equal(call.opts.data.url, DASHBOARD_URL);
    });
});
