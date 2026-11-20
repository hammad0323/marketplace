/* PrintHub customer website: API helper, toasts, notifications, PWA install, app banner. */
(function () {
    'use strict';
    var PH = window.PH || {};
    var API_ROOT = typeof PH.api === 'string' ? PH.api : '/api/v1';

    /** Calls the REST API with the session cookie. Returns a Promise of {status, message, data}. */
    PH.api = function (method, path, body, opts) {
        opts = opts || {};
        var init = { method: method, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
        if (body instanceof FormData) { init.body = body; }
        else if (body !== undefined && body !== null) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(body); }
        return fetch(PH.api_base() + path, init).then(function (r) {
            return r.json().catch(function () { return { status: false, message: 'Unexpected response (' + r.status + ').', data: {} }; });
        }, function () { return { status: false, message: 'No internet connection. Please check your network and try again.', data: {} }; });
    };
    PH.api_base = function () { return API_ROOT; };
    /** Upload with progress (XHR). */
    PH.upload = function (path, formData, onProgress) {
        return new Promise(function (resolve) {
            var x = new XMLHttpRequest();
            x.open('POST', PH.api_base() + path);
            x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            x.withCredentials = true;
            x.upload.onprogress = function (e) { if (e.lengthComputable && onProgress) { onProgress(e.loaded / e.total); } };
            x.onload = function () { try { resolve(JSON.parse(x.responseText)); } catch (e) { resolve({ status: false, message: x.status === 413 ? 'This file is too large for the server.' : 'Upload failed (' + x.status + ').' }); } };
            x.onerror = function () { resolve({ status: false, message: 'Upload failed. Check your connection and try again.' }); };
            x.send(formData);
        });
    };
    PH.toast = function (msg, icon) {
        if (!window.Swal) { return; }
        Swal.fire({ toast: true, position: 'top', timer: 2600, showConfirmButton: false, icon: icon || 'success', title: msg });
    };
    PH.alert = function (msg, icon) { return window.Swal ? Swal.fire({ text: msg, icon: icon || 'warning', confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--c-brand') || '#0F766E' }) : Promise.resolve(alert(msg)); };
    PH.money = function (n) { return 'Rs. ' + Math.round(n).toLocaleString('en-US'); };
    PH.esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    PH.store = {
        get: function (k, d) { try { var v = localStorage.getItem('ph_' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
        set: function (k, v) { try { localStorage.setItem('ph_' + k, JSON.stringify(v)); } catch (e) { /* storage unavailable */ } }
    };
    window.PH = PH;

    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]');
        if (!b) { return; }
        var t = b.getAttribute('data-copy');
        (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { PH.toast('Copied'); }, function () {
            var i = document.createElement('textarea'); i.value = t; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); PH.toast('Copied');
        });
    });

    // ---- notification centre: unread badge + browser notifications while the site is open
    var lastId = PH.store.get('notif_last', 0), first = true;
    function poll() {
        PH.api('GET', '/customer/notifications?since=' + lastId).then(function (r) {
            if (!r.status) { return; }
            var badge = document.querySelector('[data-c-unread]');
            if (badge) { badge.hidden = !r.data.unread; badge.textContent = r.data.unread; }
            if (r.data.items.length) {
                var max = Math.max.apply(null, r.data.items.map(function (n) { return n.id; }));
                if (!first && window.Notification && Notification.permission === 'granted') {
                    r.data.items.slice(0, 3).forEach(function (n) {
                        try { var no = new Notification(n.title, { body: n.body || '', icon: PH.base + 'assets/img/icon-192.png', tag: 'ph-' + n.id }); no.onclick = function () { window.focus(); if (n.link) { location.href = n.link; } }; } catch (e) { /* ignore */ }
                    });
                }
                if (max > lastId) { lastId = max; PH.store.set('notif_last', lastId); }
                document.dispatchEvent(new CustomEvent('ph:notifications', { detail: r.data.items }));
            }
            first = false;
        });
    }
    if (document.cookie.indexOf('ph_cs=') >= 0 || PH.store.get('has_session')) { poll(); setInterval(poll, 25000); }
    PH.enableNotifications = function () {
        if (!window.Notification) { PH.alert('This browser does not support notifications. You will still see updates on this page.', 'info'); return; }
        Notification.requestPermission().then(function (p) { PH.toast(p === 'granted' ? 'Notifications on' : 'Notifications not allowed', p === 'granted' ? 'success' : 'info'); });
    };

    // ---- PWA
    if ('serviceWorker' in navigator && PH.sw) { window.addEventListener('load', function () { navigator.serviceWorker.register(PH.sw, { scope: PH.base }).catch(function () {}); }); }
    var deferred = null, pwaBanner = document.querySelector('[data-pwa-banner]');
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); deferred = e;
        if (pwaBanner && !PH.store.get('pwa_dismissed') && !document.querySelector('[data-app-banner]:not([hidden])')) { pwaBanner.hidden = false; }
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-pwa-install]') && deferred) { deferred.prompt(); deferred.userChoice.finally(function () { pwaBanner.hidden = true; deferred = null; }); }
        if (e.target.closest('[data-pwa-dismiss]')) { pwaBanner.hidden = true; PH.store.set('pwa_dismissed', 1); }
        if (e.target.closest('[data-app-dismiss]')) { e.target.closest('[data-app-banner]').hidden = true; PH.store.set('app_dismissed', 1); }
    });
    // ---- optional "get the app" banner (only on phones, never when running as an installed PWA)
    var app = document.querySelector('[data-app-banner]');
    if (app && !PH.store.get('app_dismissed') && !window.matchMedia('(display-mode: standalone)').matches) {
        var ua = navigator.userAgent, link = app.querySelector('[data-app-link]');
        var isAndroid = /Android/i.test(ua), isIOS = /iPhone|iPad|iPod/i.test(ua);
        if ((isAndroid && link.dataset.play) || (isIOS && link.dataset.ios)) { link.href = isAndroid ? link.dataset.play : link.dataset.ios; app.hidden = false; }
    }
})();
