/**
 * Offline start-up for the TV player (web/display.php only — the scope is set on registration).
 *
 * With Wi-Fi off, a TV browser cannot even open the player page unless something on the TV can
 * answer for the server. This worker keeps a copy of everything the player needs:
 *   - the player page itself          → network first (4 s), the saved copy when offline;
 *   - scripts / styles (web/assets)    → saved once per version (URLs carry ?v=mtime);
 *   - fonts (Google Fonts)             → saved once;
 *   - images and video posters         → saved once per media file (media files never change);
 * Videos are NOT handled here: the page stores them itself (render.js VideoCache).
 *
 * Everything is kept in IndexedDB (database "sg-offline"), the same store the videos use, because
 * on LG webOS IndexedDB survives restarts more reliably than the HTTP cache. Responses that cannot
 * be read (opaque) fall back to Cache Storage.
 */
const DB = 'sg-offline', STORE = 'files', OPAQUE = 'sg-offline-opaque';
const PAGE_TIMEOUT = 4000;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

let dbp = null;
function idb() {
  if (dbp) return dbp;
  dbp = new Promise((res, rej) => {
    const r = indexedDB.open(DB, 1);
    r.onupgradeneeded = () => r.result.createObjectStore(STORE);
    r.onsuccess = () => res(r.result);
    r.onerror = () => rej(r.error);
  }).catch((e) => { dbp = null; throw e; });
  return dbp;
}
const tx = (mode, fn) => idb().then((db) => new Promise((res, rej) => {
  const t = db.transaction(STORE, mode); const q = fn(t.objectStore(STORE));
  t.oncomplete = () => res(q && q.result); t.onerror = () => rej(t.error); t.onabort = () => rej(t.error);
}));
const getRec = (k) => tx('readonly', (s) => s.get(k)).catch(() => null);
const delRec = (k) => tx('readwrite', (s) => s.delete(k)).catch(() => {});
const allKeys = () => tx('readonly', (s) => s.getAllKeys()).catch(() => []);

/** Key for a media file: id + variant, without the expiring signature. */
function mediaKey(url) {
  const u = new URL(url, self.location.href);
  return 'm:' + u.searchParams.get('id') + ':' + (u.searchParams.get('v') || 'file');
}
function keyFor(req) {
  const u = new URL(req.url);
  if (req.mode === 'navigate') return u.origin === self.location.origin && /\/display\.php$/.test(u.pathname) ? 'page:' + u.pathname + u.search : null;
  if (u.origin === self.location.origin && /\/web\/assets\//.test(u.pathname)) return 'asset:' + u.pathname + u.search;
  if (/\/api\/media\/file\.php$/.test(u.pathname) && req.destination === 'image') return mediaKey(req.url);
  if (u.hostname === 'fonts.googleapis.com' || u.hostname === 'fonts.gstatic.com') return 'font:' + u.href;
  return null;                                   // everything else (API, videos) goes to the network untouched
}

async function save(k, res) {
  if (res.type === 'opaque') { try { await (await caches.open(OPAQUE)).put(k, res); } catch (e) {} return; }
  if (!res.ok) return;
  try {
    const blob = await res.blob();
    await tx('readwrite', (s) => s.put({ blob, type: res.headers.get('Content-Type') || blob.type || '', t: Date.now() }, k));
    if (k.startsWith('asset:')) {                // drop older versions of the same script/style
      const path = k.split('?')[0] + '?';
      for (const old of await allKeys()) if (old !== k && String(old).startsWith(path)) await delRec(old);
    }
  } catch (e) { /* storage full: still served from the network */ }
}
async function load(k) {
  const r = await getRec(k);
  if (r && r.blob) return new Response(r.blob, { status: 200, headers: { 'Content-Type': r.type || 'application/octet-stream', 'X-Offline-Copy': '1' } });
  try { return (await (await caches.open(OPAQUE)).match(k)) || null; } catch (e) { return null; }
}

async function networkFirst(req, k) {
  try {
    const res = await Promise.race([fetch(req), new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), PAGE_TIMEOUT))]);
    if (res.ok) save(k, res.clone());
    return res;
  } catch (e) {
    return (await load(k)) || new Response('<!doctype html><meta charset="utf-8"><body style="background:#000;color:#fff;font:24px sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0">No connection, and this display has not been opened online yet.</body>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }
}
async function cacheFirst(req, k) {
  const have = await load(k);
  if (have) return have;
  const res = await fetch(req);
  save(k, res.clone());
  return res;
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || req.headers.has('range')) return;
  const k = keyFor(req);
  if (!k) return;
  e.respondWith(k.startsWith('page:') ? networkFirst(req, k) : cacheFirst(req, k));
});

/**
 * From the player page: { type: 'sync', images: [url…], assets: [url…], page } — keep exactly these
 * images of the current content (download missing ones now, delete the rest), make sure the page and
 * its scripts, styles and fonts are saved. Replies { images, saved, page }.
 */
self.addEventListener('message', (e) => {
  const d = e.data || {}, port = e.ports && e.ports[0];
  if (d.type !== 'sync') return;
  e.waitUntil((async () => {
    const urls = (d.images || []).filter(Boolean);
    const wanted = new Map(urls.map((u) => [mediaKey(u), u]));
    const keys = await allKeys();
    for (const k of keys) if (String(k).startsWith('m:') && !wanted.has(k)) await delRec(k);
    let saved = 0;
    for (const [k, u] of wanted) {
      if (await getRec(k)) { saved++; continue; }
      try { const same = new URL(u, self.location.href).origin === self.location.origin; const res = await fetch(u, same ? {} : { mode: 'no-cors' }); if (res.ok || res.type === 'opaque') { await save(k, res); saved++; } continue; } catch (err) { /* offline: try again next sync */ }
    }
    // Files the page loaded before this worker was running (first visit): save them now.
    for (const u of d.assets || []) {
      let k = null;
      try { k = keyFor({ url: u, mode: 'cors', destination: '' }); } catch (err) {}
      if (!k || (await load(k))) continue;
      try { const res = await fetch(u, { mode: 'cors' }); await save(k, res); } catch (err) {}
    }
    let page = null;
    if (d.page) {
      const pk = 'page:' + new URL(d.page, self.location.href).pathname + new URL(d.page, self.location.href).search;
      if (!(await getRec(pk))) { try { const res = await fetch(d.page, { credentials: 'same-origin' }); await save(pk, res); } catch (err) {} }
      page = !!(await getRec(pk));
    }
    if (port) port.postMessage({ images: wanted.size, saved, page });
  })());
});
