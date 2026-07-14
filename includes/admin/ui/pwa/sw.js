/**
 * DEOIA admin PWA — network-only service worker (installability + push).
 * Does not cache admin-post, admin-ajax, or any dynamic responses.
 *
 * self.__AA_PUSH_API_BASE__ is injected by AA_Pwa_Routes::serve_service_worker.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(Promise.all([
        self.clients.claim(),
        cleanupExpiredAppointmentNotifications(),
        cleanupTaskPushNotificationsBestEffort(null)
    ]));
});

self.addEventListener('fetch', function (event) {
    event.respondWith(fetch(event.request));
});

var FALLBACK_TITLE = 'DEOIA';
var FALLBACK_BODY = 'Tienes una nueva notificación.';
var FALLBACK_TAG = 'deoia-web-push';
var APPOINTMENT_PUSH_TYPE = 'upcoming_confirmed_appointment';
var TASK_PUSH_TYPE = 'task_execution_available';
var TASK_PUSH_VALIDATE_TIMEOUT_MS = 3000;
var TASK_PUSH_VALIDATE_MAX = 50;
var TASK_PUSH_INCOMING_REQUEST_ID = 'incoming';
var TASK_PUSH_CLEANUP_MESSAGE_TYPE = 'aa_cleanup_task_push_notifications';

function getDefaultModuleUrl() {
    return new URL(
        'admin-post.php?action=aa_iframe_content&module=calendar',
        self.registration.scope
    ).href;
}

function resolveSafeUrl(rawUrl) {
    var fallbackUrl = getDefaultModuleUrl();

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

function normalizeTaskId(value) {
    if (typeof value === 'number' && Number.isInteger(value) && value >= 1) {
        return value;
    }

    return null;
}

function normalizeExpectedExecutionAvailableAt(value) {
    if (typeof value !== 'string') {
        return null;
    }

    var trimmed = value.trim();
    if (trimmed === '') {
        return null;
    }

    var ms = Date.parse(trimmed);
    if (Number.isNaN(ms)) {
        return null;
    }

    return trimmed;
}

function isTaskExecutionAvailableData(data) {
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        return false;
    }

    if (data.type !== TASK_PUSH_TYPE) {
        return false;
    }

    return normalizeTaskId(data.taskId) !== null
        && normalizeExpectedExecutionAvailableAt(data.expectedExecutionAvailableAt) !== null;
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

    if (isTaskExecutionAvailableData(data)) {
        notificationData.type = TASK_PUSH_TYPE;
        notificationData.taskId = normalizeTaskId(data.taskId);
        notificationData.expectedExecutionAvailableAt = normalizeExpectedExecutionAvailableAt(
            data.expectedExecutionAvailableAt
        );
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

function resolvePushApiBase() {
    var raw = typeof self.__AA_PUSH_API_BASE__ === 'string'
        ? self.__AA_PUSH_API_BASE__.trim()
        : '';

    if (raw === '') {
        return null;
    }

    try {
        var parsed = new URL(raw);
        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
            return null;
        }

        return parsed.origin + parsed.pathname.replace(/\/+$/, '');
    } catch (err) {
        return null;
    }
}

function getPushSubscriptionJson() {
    if (!self.registration
        || !self.registration.pushManager
        || typeof self.registration.pushManager.getSubscription !== 'function') {
        return Promise.resolve(null);
    }

    return self.registration.pushManager.getSubscription()
        .then(function (subscription) {
            if (!subscription || typeof subscription.toJSON !== 'function') {
                return null;
            }

            var json = subscription.toJSON();
            if (!json || typeof json !== 'object' || Array.isArray(json)) {
                return null;
            }

            var endpoint = typeof json.endpoint === 'string' ? json.endpoint.trim() : '';
            var keys = json.keys && typeof json.keys === 'object' ? json.keys : null;
            var p256dh = keys && typeof keys.p256dh === 'string' ? keys.p256dh.trim() : '';
            var auth = keys && typeof keys.auth === 'string' ? keys.auth.trim() : '';

            if (!endpoint || !p256dh || !auth) {
                return null;
            }

            return {
                endpoint: endpoint,
                keys: {
                    p256dh: p256dh,
                    auth: auth
                }
            };
        })
        .catch(function () {
            return null;
        });
}

function collectExistingTaskWorkItems(notifications) {
    var items = [];
    var i;
    var notification;
    var notifData;
    var taskId;
    var expectedAt;

    for (i = 0; i < notifications.length; i += 1) {
        notification = notifications[i];
        notifData = notification && notification.data;

        if (!isTaskExecutionAvailableData(notifData)) {
            continue;
        }

        taskId = normalizeTaskId(notifData.taskId);
        expectedAt = normalizeExpectedExecutionAvailableAt(notifData.expectedExecutionAvailableAt);

        items.push({
            requestId: 'n-' + String(i),
            taskId: taskId,
            expectedExecutionAvailableAt: expectedAt,
            notification: notification
        });
    }

    return items;
}

function selectTaskValidateBatch(existingItems, incomingItem) {
    var maxExisting = incomingItem ? (TASK_PUSH_VALIDATE_MAX - 1) : TASK_PUSH_VALIDATE_MAX;
    var selectedExisting = existingItems.slice(0, Math.max(0, maxExisting));
    var tasks = [];
    var i;

    for (i = 0; i < selectedExisting.length; i += 1) {
        tasks.push({
            requestId: selectedExisting[i].requestId,
            taskId: selectedExisting[i].taskId,
            expectedExecutionAvailableAt: selectedExisting[i].expectedExecutionAvailableAt
        });
    }

    if (incomingItem) {
        tasks.push({
            requestId: incomingItem.requestId,
            taskId: incomingItem.taskId,
            expectedExecutionAvailableAt: incomingItem.expectedExecutionAvailableAt
        });
    }

    return {
        selectedExisting: selectedExisting,
        tasks: tasks
    };
}

function mapValidateResultsByRequestId(payload) {
    var map = {};
    var i;
    var entry;

    if (!payload || payload.ok !== true || !Array.isArray(payload.results)) {
        return null;
    }

    for (i = 0; i < payload.results.length; i += 1) {
        entry = payload.results[i];
        if (!entry || typeof entry !== 'object' || Array.isArray(entry)) {
            continue;
        }

        if (typeof entry.requestId !== 'string' || entry.requestId === '') {
            continue;
        }

        if (typeof entry.status !== 'string') {
            continue;
        }

        map[entry.requestId] = entry.status.trim();
    }

    return map;
}

function validateTaskNotificationsWithBackend(tasks) {
    var apiBase = resolvePushApiBase();

    if (!apiBase || !tasks || tasks.length < 1) {
        return Promise.resolve({ ok: false });
    }

    return getPushSubscriptionJson().then(function (subscription) {
        if (!subscription) {
            return { ok: false };
        }

        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = null;
        var url = apiBase + '/push/task-execution-available-notifications/validate';
        var fetchOpts = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                subscription: subscription,
                tasks: tasks
            })
        };

        if (controller) {
            fetchOpts.signal = controller.signal;
            timer = setTimeout(function () {
                try {
                    controller.abort();
                } catch (err) {
                    // ignore
                }
            }, TASK_PUSH_VALIDATE_TIMEOUT_MS);
        }

        return fetch(url, fetchOpts)
            .then(function (response) {
                if (!response || typeof response.status !== 'number') {
                    return { ok: false };
                }

                if (response.status < 200 || response.status >= 300) {
                    return { ok: false };
                }

                return response.json().then(function (body) {
                    var byId = mapValidateResultsByRequestId(body);
                    if (!byId) {
                        return { ok: false };
                    }

                    return { ok: true, byRequestId: byId };
                }).catch(function () {
                    return { ok: false };
                });
            })
            .catch(function () {
                return { ok: false };
            })
            .finally(function () {
                if (timer !== null) {
                    clearTimeout(timer);
                }
            });
    });
}

function closeWorkItemNotification(item) {
    try {
        if (item && item.notification && typeof item.notification.close === 'function') {
            item.notification.close();
        }
    } catch (err) {
        // best effort
    }
}

function applyTaskValidateResults(selectedExisting, byRequestId) {
    var i;
    var item;
    var status;

    if (!byRequestId) {
        return;
    }

    for (i = 0; i < selectedExisting.length; i += 1) {
        item = selectedExisting[i];
        status = byRequestId[item.requestId];

        if (status === 'stale' || status === 'ineligible') {
            closeWorkItemNotification(item);
        }
    }
}

function shouldShowIncomingTask(byRequestId, requestId) {
    if (!byRequestId) {
        return true;
    }

    var status = byRequestId[requestId];

    if (status === 'stale' || status === 'ineligible') {
        return false;
    }

    // eligible, unknown, missing → show (conservative)
    return true;
}

/**
 * @param {{ requestId: string, taskId: number, expectedExecutionAvailableAt: string }|null} incomingItem
 * @returns {Promise<{ ok: boolean, byRequestId: Object|null, showedDecision: boolean }>}
 */
function runTaskNotificationValidation(incomingItem) {
    if (!self.registration || typeof self.registration.getNotifications !== 'function') {
        return Promise.resolve({ ok: false, byRequestId: null, showedDecision: true });
    }

    return self.registration.getNotifications()
        .then(function (notifications) {
            var existingItems = collectExistingTaskWorkItems(notifications || []);
            var batch = selectTaskValidateBatch(existingItems, incomingItem);

            if (batch.tasks.length < 1) {
                return { ok: true, byRequestId: {}, showedDecision: true };
            }

            return validateTaskNotificationsWithBackend(batch.tasks).then(function (result) {
                if (!result || result.ok !== true) {
                    return { ok: false, byRequestId: null, showedDecision: true };
                }

                applyTaskValidateResults(batch.selectedExisting, result.byRequestId);

                return {
                    ok: true,
                    byRequestId: result.byRequestId,
                    showedDecision: shouldShowIncomingTask(
                        result.byRequestId,
                        incomingItem ? incomingItem.requestId : null
                    )
                };
            });
        })
        .catch(function () {
            return { ok: false, byRequestId: null, showedDecision: true };
        });
}

function cleanupTaskPushNotificationsBestEffort(incomingItem) {
    return runTaskNotificationValidation(incomingItem).catch(function () {
        return { ok: false, byRequestId: null, showedDecision: true };
    });
}

function showPushNotification(title, body, tag, data) {
    return self.registration.showNotification(title, {
        body: body,
        tag: tag,
        data: buildNotificationData(data)
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

            if (isTaskExecutionAvailableData(data)) {
                var incomingItem = {
                    requestId: TASK_PUSH_INCOMING_REQUEST_ID,
                    taskId: normalizeTaskId(data.taskId),
                    expectedExecutionAvailableAt: normalizeExpectedExecutionAvailableAt(
                        data.expectedExecutionAvailableAt
                    )
                };

                return runTaskNotificationValidation(incomingItem).then(function (result) {
                    if (result && result.showedDecision === false) {
                        return undefined;
                    }

                    return showPushNotification(title, body, tag, data);
                }).catch(function () {
                    return showPushNotification(title, body, tag, data);
                });
            }

            // Appointment / generic: show immediately; task tray cleanup is independent best-effort.
            var showPromise = showPushNotification(title, body, tag, data);
            var taskCleanupPromise = cleanupTaskPushNotificationsBestEffort(null);

            return Promise.all([showPromise, taskCleanupPromise]).then(function () {
                return undefined;
            });
        })
    );
});

self.addEventListener('message', function (event) {
    var message = event && event.data;

    if (!message || typeof message !== 'object' || Array.isArray(message)) {
        return;
    }

    if (message.type !== TASK_PUSH_CLEANUP_MESSAGE_TYPE) {
        return;
    }

    var cleanupPromise = cleanupTaskPushNotificationsBestEffort(null);

    if (event && typeof event.waitUntil === 'function') {
        event.waitUntil(cleanupPromise);
    }
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
