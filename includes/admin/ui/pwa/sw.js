/**
 * DEOIA Citas admin PWA — network-only service worker (installability only).
 * Does not cache admin-post, admin-ajax, or any dynamic responses.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    event.respondWith(fetch(event.request));
});

var FALLBACK_TITLE = 'DEOIA';
var FALLBACK_BODY = 'Tienes una nueva notificación.';
var FALLBACK_TAG = 'deoia-web-push';

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

    event.waitUntil(self.registration.showNotification(title, {
        body: body,
        tag: tag,
        data: {
            url: resolveSafeUrl(data.url)
        }
    }));
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
