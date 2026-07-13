/**
 * DEOIA Citas admin PWA — network-only service worker (installability only).
 * Does not cache admin-post, admin-ajax, or any dynamic responses.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(Promise.all([
        self.clients.claim(),
        cleanupExpiredAppointmentNotifications()
    ]));
});

self.addEventListener('fetch', function (event) {
    event.respondWith(fetch(event.request));
});

var FALLBACK_TITLE = 'DEOIA';
var FALLBACK_BODY = 'Tienes una nueva notificación.';
var FALLBACK_TAG = 'deoia-web-push';
var APPOINTMENT_PUSH_TYPE = 'upcoming_confirmed_appointment';

function getDashboardUrl() {
    return new URL(
        'admin-post.php?action=aa_iframe_content&module=dashboard',
        self.registration.scope
    ).href;
}

function resolveSafeUrl(rawUrl) {
    var fallbackUrl = getDashboardUrl();

    if (typeof rawUrl !== 'string' || rawUrl.trim() === '') {
        return fallbackUrl;
    }

    try {
        var candidate = new URL(rawUrl, self.registration.scope);
        var scopeUrl = new URL(self.registration.scope);

        if (candidate.origin !== scopeUrl.origin) {
            return fallbackUrl;
        }

        if (candidate.pathname.indexOf(scopeUrl.pathname) !== 0) {
            return fallbackUrl;
        }

        return candidate.href;
    } catch (err) {
        return fallbackUrl;
    }
}

function parsePushPayload(event) {
    if (!event.data) {
        return {};
    }

    try {
        var payload = event.data.json();

        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
            return {};
        }

        return payload;
    } catch (err) {
        return {};
    }
}

function parseExpiresAtMs(expiresAt) {
    if (typeof expiresAt !== 'string' || expiresAt.trim() === '') {
        return null;
    }

    var ms = Date.parse(expiresAt);

    if (Number.isNaN(ms)) {
        return null;
    }

    return ms;
}

function isAppointmentNotificationData(data) {
    return data
        && typeof data === 'object'
        && !Array.isArray(data)
        && data.type === APPOINTMENT_PUSH_TYPE;
}

function isExpiredAppointmentData(data) {
    if (!isAppointmentNotificationData(data)) {
        return false;
    }

    var expiresMs = parseExpiresAtMs(data.expiresAt);

    if (expiresMs === null) {
        return false;
    }

    return Date.now() >= expiresMs;
}

function buildNotificationData(data) {
    var notificationData = {
        url: resolveSafeUrl(data.url)
    };

    if (isAppointmentNotificationData(data)) {
        notificationData.type = data.type;

        if (typeof data.expiresAt === 'string' && data.expiresAt.trim() !== '') {
            notificationData.expiresAt = data.expiresAt.trim();
        }
    }

    return notificationData;
}

function cleanupExpiredAppointmentNotifications() {
    if (!self.registration || typeof self.registration.getNotifications !== 'function') {
        return Promise.resolve();
    }

    return self.registration.getNotifications().then(function (notifications) {
        var i;
        var notification;
        var notifData;

        for (i = 0; i < notifications.length; i += 1) {
            notification = notifications[i];
            notifData = notification && notification.data;

            if (!isExpiredAppointmentData(notifData)) {
                continue;
            }

            try {
                if (typeof notification.close === 'function') {
                    notification.close();
                }
            } catch (err) {
                // best effort
            }
        }
    }).catch(function () {
        // best effort
    });
}

function isDeoiaWindow(client) {
    try {
        var clientUrl = new URL(client.url);
        var scopeUrl = new URL(self.registration.scope);

        return clientUrl.origin === scopeUrl.origin
            && clientUrl.pathname.indexOf(scopeUrl.pathname) === 0
            && clientUrl.searchParams.get('action') === 'aa_iframe_content';
    } catch (err) {
        return false;
    }
}

self.addEventListener('push', function (event) {
    var payload = parsePushPayload(event);
    var data = payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)
        ? payload.data
        : {};

    var title = typeof payload.title === 'string' && payload.title.trim() !== ''
        ? payload.title.trim()
        : FALLBACK_TITLE;

    var body = typeof payload.body === 'string' && payload.body.trim() !== ''
        ? payload.body.trim()
        : FALLBACK_BODY;

    var tag = typeof payload.tag === 'string' && payload.tag.trim() !== ''
        ? payload.tag.trim()
        : FALLBACK_TAG;

    event.waitUntil(
        cleanupExpiredAppointmentNotifications().then(function () {
            if (isExpiredAppointmentData(data)) {
                return undefined;
            }

            return self.registration.showNotification(title, {
                body: body,
                tag: tag,
                data: buildNotificationData(data)
            });
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var targetUrl = resolveSafeUrl(
        event.notification
            && event.notification.data
            && event.notification.data.url
    );

    event.waitUntil(
        self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function (clientList) {
            var i;

            for (i = 0; i < clientList.length; i += 1) {
                if (isDeoiaWindow(clientList[i]) && typeof clientList[i].focus === 'function') {
                    return clientList[i].focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        })
    );
});
