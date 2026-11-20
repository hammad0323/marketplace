/* PrintHub service worker: caches the website shell for quick loading and an offline page.
   Never caches API responses or customer files. */
var CACHE = 'printhub-shell-v1';
var SHELL = ['assets/css/customer.css', 'assets/js/customer.js', 'assets/vendor/bootstrap/bootstrap.min.css', 'assets/vendor/bootstrap/bootstrap.bundle.min.js',
    'assets/vendor/bootstrap-icons/bootstrap-icons.min.css', 'assets/vendor/sweetalert2/sweetalert2.all.min.js', 'assets/img/icon-192.png', 'assets/img/icon-512.png'];
var base = self.registration.scope;
self.addEventListener('install', function (e) {
    e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(SHELL.map(function (p) { return base + p; })); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
    e.waitUntil(caches.keys().then(function (keys) { return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); })); }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
    var req = e.request, url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin || /\/(api|files|admin|owner)\//.test(url.pathname)) { return; }
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(function () {
            return new Response('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="font-family:sans-serif;text-align:center;padding:60px 20px;color:#334155"><h1 style="color:#0F766E">You are offline</h1><p>Connect to the internet (or the shop Wi-Fi) and try again.</p><button onclick="location.reload()" style="padding:12px 20px;border-radius:12px;border:0;background:#0F766E;color:#fff;font-size:16px">Retry</button></body>', { headers: { 'Content-Type': 'text/html' } });
        }));
        return;
    }
    if (/\/assets\//.test(url.pathname)) {
        e.respondWith(caches.match(req, { ignoreSearch: true }).then(function (hit) {
            var net = fetch(req).then(function (res) { if (res.ok) { var copy = res.clone(); caches.open(CACHE).then(function (c) { c.put(req, copy); }); } return res; }).catch(function () { return hit; });
            return hit || net;
        }));
    }
});
