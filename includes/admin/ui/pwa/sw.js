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
