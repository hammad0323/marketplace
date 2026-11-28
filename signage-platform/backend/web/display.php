<?php
/**
 * Browser player for smart TVs / signage PCs that have a web browser:
 * open /web/display.php, enter the display code (or ?code=), and the published
 * screen or playlist plays full screen. Content is re-checked every minute.
 * Public page — the code itself is the access key (same as the app's "Show Display").
 */
require __DIR__ . '/_bootstrap.php';
$base = web_base();
$code = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['code'] ?? ''));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Show Display · <?= h(app_setting('app_name', 'Signal Signage')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" crossorigin="anonymous" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Amatic+SC:wght@700&family=Bebas+Neue&family=Oswald:wght@400;600;700&family=Caveat:wght@500;700&family=Pacifico&family=Playfair+Display:wght@400;700&family=Montserrat:wght@400;600;800&family=Lobster&family=Dancing+Script:wght@600&family=Anton&family=Archivo+Black&family=Permanent+Marker&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Lato:wght@400;700&family=Raleway:wght@400;700&family=Merriweather:wght@400;700&family=Nunito:wght@400;700&family=Cairo:wght@400;700&family=Noto+Sans:wght@400;700&display=swap">
<link rel="stylesheet" crossorigin="anonymous" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
<link rel="stylesheet" href="<?= h(web_asset('web.css')) ?>">
<style>
  html, body { background: #000; height: 100%; overflow: hidden; }
  #player { position: fixed; inset: 0; }
  #emergency { position: fixed; inset: 0; z-index: 50; display: none; }
  #diag { position: fixed; left: 14px; top: 14px; z-index: 70; max-width: 62vw; color: #fff; background: rgba(0,0,0,.78); padding: 12px 14px; border-radius: 12px; font: 13px/1.45 monospace; white-space: pre-wrap; display: none; }
  #corner { position: fixed; right: 14px; bottom: 14px; z-index: 60; color: #fff; background: rgba(0,0,0,.55); padding: 8px 12px; border-radius: 10px; font-size: 13px; display: none; }
  body.auth { background: #17191C; }
</style>
<script src="<?= h(web_asset('vendor/qrcode.js')) ?>"></script>
<script>window.SIGNAGE = <?= json_encode(['api' => $base . '/api/', 'web' => $base . '/web/', 'token' => '']) ?>;</script>
<script src="<?= h(web_asset('vendor/mp4box.all.min.js')) ?>"></script>
<script src="<?= h(web_asset('render.js')) ?>"></script>
<script src="<?= h(web_asset('player.js')) ?>"></script>
</head>
<body class="auth">
<form class="auth-card" id="form" style="display:none">
  <span class="brand-mark big ms">tv</span>
  <h1>Show Display</h1>
  <p class="muted">Enter the display code of a screen or playlist. Find it in the app or web dashboard under <b>Display code</b>.</p>
  <div class="alert error" id="err" style="display:none"></div>
  <label>Display code<input id="code" maxlength="12" autocomplete="off" autocapitalize="characters" style="font-size:28px;letter-spacing:6px;text-transform:uppercase;text-align:center" required></label>
  <label style="flex-direction:row;align-items:center;gap:8px;color:var(--text)"><input type="checkbox" id="remember" checked> Remember this code on this TV</label>
  <button class="btn primary block" type="submit">Start display</button>
  <p class="muted small center">Only published content is shown. Press <b>Esc</b> or <b>Back</b> three times to change the code.</p>
</form>
<div id="player" style="display:none"></div>
<div id="emergency"></div>
<div id="corner"></div>
<div id="diag"></div>
<script>
(function () {
  const S = window.SIGNAGE, R = window.SignRender;
  const form = document.getElementById('form'), player = document.getElementById('player'), err = document.getElementById('err'), corner = document.getElementById('corner');
  let code = <?= json_encode(strtoupper($code)) ?> || (localStorage.getItem('sg_display_code') || '');
  let doc = null, hash = '', lastFull = 0, player2 = null;
  const showForm = (msg) => { stop(); document.body.className = 'auth'; player.style.display = 'none'; form.style.display = ''; err.style.display = msg ? '' : 'none'; err.textContent = msg || ''; document.getElementById('code').value = code; };
  const stop = () => { if (player2) { player2.destroy(); player2 = null; } };
  async function fetchContent(full) {
    const url = S.api + 'display/content.php?code=' + encodeURIComponent(code) + (hash && !full ? '&have=' + encodeURIComponent(hash) : '');
    const r = await fetch(url, { cache: 'no-store' });
    const j = await r.json().catch(() => null);
    if (!j || !j.status) throw Object.assign(new Error((j && j.message) || 'Server error'), { code: j && j.code, status: r.status });
    return j.data;
  }
  // Download every video of the published content into browser storage (once), so loops and
  // rotations play from the TV itself — no buffering, no repeated downloads.
  let prepToken = 0;
  async function prepareVideos(d) {
    const token = ++prepToken;
    const media = (d.media || []).filter((m) => m.file_type === 'video');
    if (R.VideoCache.mode() === 'stream') { R.VideoCache.prune([]); return; }   // this TV can only stream
    R.VideoCache.prune(media);
    for (let i = 0; i < media.length; i++) {
      if (token !== prepToken) return;
      const m = media[i];
      if (await R.VideoCache.localBlob(m, m.media_id)) continue;
      const off = R.VideoCache.onChange(() => { const st = R.VideoCache.status(); if (st.downloading) showMessage(`Saving video ${i + 1} of ${media.length} for smooth playback… ${Math.round(st.progress * 100)}%`); });
      await R.VideoCache.ensure(m, m.media_id, m.url);
      off();
    }
    if (token === prepToken && corner.textContent.startsWith('Saving video')) corner.style.display = 'none';
  }
  // Offline start-up: a service worker keeps the player page, scripts, fonts and every image of the
  // content on the TV, so the display opens and plays even when the TV is switched on without Wi-Fi.
  // (Browsers only allow service workers on HTTPS; on plain HTTP videos are still stored, but the
  // page itself needs the server to open.)
  let swReg = null, offline = { images: 0, saved: 0, page: null };
  const swSupported = 'serviceWorker' in navigator && window.isSecureContext;
  if (swSupported) navigator.serviceWorker.register(S.web + 'sw.js', { scope: S.web + 'display.php' }).then((r) => (swReg = r)).catch((e) => console.warn('offline start-up unavailable:', e && e.message));
  async function syncOffline(d) {
    if (!swSupported) return;
    const reg = swReg || (await navigator.serviceWorker.ready.catch(() => null));
    const sw = reg && (reg.active || reg.waiting || reg.installing);
    if (!sw) return;
    const images = [];
    (d.media || []).forEach((m) => {
      if (m.file_type === 'video') { if (m.poster_url || m.thumbnail_url) images.push(m.poster_url || m.thumbnail_url); }
      else if (m.url) images.push(m.url);
    });
    const ch = new MessageChannel();
    ch.port1.onmessage = (e) => { offline = e.data || offline; };
    const assets = [...document.querySelectorAll('script[src], link[rel=stylesheet]')].map((el) => el.src || el.href)
      .concat(performance.getEntriesByType ? performance.getEntriesByType('resource').map((e) => e.name) : []);
    sw.postMessage({ type: 'sync', images, assets: [...new Set(assets)], page: location.pathname + location.search }, [ch.port2]);
  }
  const docKey = () => 'sg_display_doc_' + code;
  function remember(d, h) { try { localStorage.setItem(docKey(), JSON.stringify({ doc: d, hash: h })); } catch (e) { /* storage full: still plays */ } }
  function remembered() { try { return JSON.parse(localStorage.getItem(docKey()) || 'null'); } catch (e) { return null; } }

  function play() {
    prepareVideos(doc).then(() => syncOffline(doc));
    if (player2) player2.update(doc);
    else player2 = window.SignPlayer.start(player, doc, { mode: 'live', onEmpty: (empty) => (empty ? showMessage('Nothing scheduled to play right now.') : (corner.style.display = 'none')) });
  }
  function showMessage(t) { corner.style.display = 'block'; corner.textContent = t; }
  function emergency(list) {
    const box = document.getElementById('emergency');
    if (!list || !list.length) { box.style.display = 'none'; box.innerHTML = ''; return; }
    const e = list[0], st = e.style_json || {};
    box.style.display = 'block';
    R.mount(box, { canvas: { width: 1920, height: 1080 }, background: { type: 'color', value: st.background || '#B00020' }, widgets: [{ widget_uid: 'em', widget_type: 'emergency', position_x: 0, position_y: 0, width: 1, height: 1, configuration: { title: e.title, message: e.body || '', background_color: st.background || '#B00020', color: st.color || '#FFFFFF', flash: st.flash !== false } }] }, { mode: 'live' });
  }
  async function start() {
    R.VideoCache.askPersist();   // ask the browser not to clear this TV's saved content under storage pressure
    form.style.display = 'none'; document.body.className = ''; player.style.display = '';
    try {
      const d = await fetchContent(true);
      doc = d.doc; hash = d.content_hash; lastFull = Date.now();
      remember(doc, hash);
      emergency(d.emergency); corner.style.display = 'none';
      play();
      setTimeout(poll, (d.refresh_seconds || 60) * 1000);
    } catch (e) {
      if (e.code === 'CODE_NOT_FOUND' || e.code === 'CODE_INACTIVE' || e.code === 'NOT_PUBLISHED' || e.code === 'VALIDATION') return showForm(e.message);
      const saved = remembered();
      if (saved && saved.doc) {   // no internet after a restart: play the last content from the TV's storage
        doc = saved.doc; hash = saved.hash || ''; play(); showMessage('Offline — playing saved content');
        setTimeout(poll, 60000);
        return;
      }
      showForm('Could not connect: ' + e.message);
    }
  }
  async function poll() {
    try {
      const full = Date.now() - lastFull > 6 * 3600 * 1000;   // refresh signed media links a few times a day
      const d = await fetchContent(full);
      emergency(d.emergency); corner.style.display = 'none';
      if (d.doc) { doc = d.doc; hash = d.content_hash; if (full) lastFull = Date.now(); remember(doc, hash); play(); }
    } catch (e) {
      if (e.code === 'CODE_NOT_FOUND' || e.code === 'CODE_INACTIVE') { localStorage.removeItem('sg_display_code'); return showForm(e.message); }
      showMessage('Offline — showing the last content');   // keep playing what we have
    }
    setTimeout(poll, 60000);
  }
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    code = document.getElementById('code').value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (!code) return;
    if (document.getElementById('remember').checked) localStorage.setItem('sg_display_code', code); else localStorage.removeItem('sg_display_code');
    document.documentElement.requestFullscreen && document.documentElement.requestFullscreen().catch(() => {});
    hash = ''; start();
  });
  // Esc / Back pressed 3 times within 2 s → change code
  let presses = [];
  document.addEventListener('keydown', (e) => {
    if (form.style.display !== 'none') return;
    if (e.key === 'Escape' || e.key === 'Backspace' || e.key === 'GoBack' || e.keyCode === 461 || e.keyCode === 10009) {
      presses = presses.filter((t) => Date.now() - t < 2000).concat(Date.now());
      if (presses.length >= 3) { presses = []; showForm(''); }
    }
  });
  // Smart-TV browsers (e.g. LG webOS) only allow full screen after a button press: use the first one.
  const goFull = () => {
    const d = document.documentElement;
    if (form.style.display === 'none' && !document.fullscreenElement && d.requestFullscreen) d.requestFullscreen().catch(() => {});
  };
  document.addEventListener('keydown', goFull, true);
  document.addEventListener('click', goFull, true);

  // Diagnostics overlay: add &debug=1 to the link, or press the BLUE button (or "D") on the remote/keyboard.
  const diag = document.getElementById('diag');
  let diagTimer = null;
  async function drawDiag() {
    const lines = [];
    const vids = [...document.querySelectorAll('#player video')];
    const ER = { 1: 'aborted', 2: 'network', 3: 'decode (TV cannot decode this file)', 4: 'format not supported' };
    lines.push('Signal Signage – display diagnostics   (BLUE / D to close)');
    lines.push('Browser: ' + navigator.userAgent);
    lines.push(`Screen: ${screen.width}x${screen.height}  window: ${innerWidth}x${innerHeight}  memory: ${navigator.deviceMemory || '?'} GB`);
    const v0 = document.createElement('video');
    lines.push(`Decoders: H.264 ${v0.canPlayType('video/mp4; codecs="avc1.640028"') || 'no'} · HEVC ${v0.canPlayType('video/mp4; codecs="hvc1.1.6.L120.90"') || 'no'}`);
    const si = await R.VideoCache.storageInfo().catch(() => null);
    const mb = (b) => (b / 1048576).toFixed(0);
    if (si) {
      lines.push(`Storage: ${{ indexeddb: 'IndexedDB', cache: 'Cache Storage', memory: 'none (memory only)' }[si.backend]}${si.quota ? ` · used ${mb(si.usage)} of ${mb(si.quota)} MB` : ' · quota unknown'} · videos saved: ${si.videos} (${mb(si.videoBytes)} MB) · protected from clean-up: ${si.persisted === null ? '?' : si.persisted ? 'yes' : 'no'}`);
    }
    const st = R.VideoCache.status();
    const modeText = { blob: 'local file (blob)', mse: 'local file via Media Source (LG-compatible)', stream: 'streaming from the server' }[R.VideoCache.mode()];
    lines.push(`Video playback: ${modeText} · largest video kept: ${si ? mb(si.maxBytes) : '?'} MB · downloads running: ${st.downloading}${st.downloading ? ' (' + Math.round(st.progress * 100) + '%)' : ''}`);
    const vmedia = ((doc && doc.media) || []).filter((x) => x.file_type === 'video');
    const VS = { saved: 'saved on this TV', downloading: 'downloading…', memory: 'in memory only (not saved yet)', nospace: 'NOT SAVED – TV storage full (plays from memory while online)', toolarge: 'too large for this TV – streams', stream: 'streams', missing: 'not downloaded yet' };
    const vstates = vmedia.map((x) => R.VideoCache.state(x));
    const ctrl = swSupported && navigator.serviceWorker.controller;
    const ready = vstates.every((x) => x === 'saved') && (!swSupported || (ctrl && offline.page !== false && offline.saved >= offline.images));
    lines.push(`Offline: ${ready ? 'READY – keeps playing with Wi-Fi off' + (swSupported ? ', also after a restart' : '') : 'not ready yet'}`
      + ` · videos ${vstates.filter((x) => x === 'saved').length}/${vmedia.length}`
      + (swSupported ? ` · images ${offline.saved}/${offline.images} · page ${ctrl ? (offline.page === false ? 'not saved' : 'saved') : 'reload once to save'}` : ' · restart without Wi-Fi needs an HTTPS link'));
    vmedia.forEach((x, i) => lines.push(`  video ${i + 1} (#${x.media_id}, ${((x.file_size || 0) / 1048576).toFixed(1)} MB): ${VS[vstates[i]] || vstates[i]}`));
    lines.push(`Code: ${code}   content: ${hash ? hash.slice(0, 10) : '-'}   online: ${navigator.onLine}`);
    if (!vids.length) lines.push('Videos on screen: none');
    vids.forEach((v, i) => {
      const kind = v._sg && v._sg.kind;
      const src = !v.getAttribute('src') ? 'NOT STARTED (downloading first)' : kind === 'mse' ? 'LOCAL copy on this TV (Media Source)' : kind === 'local' ? 'LOCAL copy on this TV' : 'STREAM from server';
      lines.push(`Video ${i + 1}: ${src}`);
      const mm = ((doc && doc.media) || []).find((x) => String(x.media_id) === String(v.dataset.mediaId));
      if (mm) lines.push(`  file: ${mm.variant === 'tv' ? 'TV version (optimised)' : 'ORIGINAL (not converted yet)'} · ${mm.mime_type || '?'} · ${((mm.file_size || 0) / 1048576).toFixed(1)} MB`);
      lines.push(`  ${v.paused ? 'PAUSED' : 'playing'} · ${v.currentTime.toFixed(1)} / ${isFinite(v.duration) ? v.duration.toFixed(1) : '?'} s · ${v.videoWidth}x${v.videoHeight} · ready ${v.readyState}/4 · network ${v.networkState} · muted ${v.muted}`);
      if (v.error) lines.push(`  ERROR ${v.error.code}: ${ER[v.error.code] || ''} ${v.error.message || ''}`);
    });
    diag.textContent = lines.join('\n');
  }
  function toggleDiag(on) {
    const show = on ?? diag.style.display === 'none';
    diag.style.display = show ? 'block' : 'none';
    clearInterval(diagTimer);
    if (show) { drawDiag(); diagTimer = setInterval(drawDiag, 1000); }
  }
  document.addEventListener('keydown', (e) => {
    if (form.style.display !== 'none') return;
    if (e.keyCode === 406 || e.key === 'ColorF3Blue' || e.key === 'd' || e.key === 'D') toggleDiag();
  });
  if (/[?&]debug=1/.test(location.search)) toggleDiag(true);

  document.addEventListener('mousemove', () => { document.body.style.cursor = ''; clearTimeout(window._cur); window._cur = setTimeout(() => (document.body.style.cursor = 'none'), 3000); });
  code ? start() : showForm('');
})();
</script>
</body></html>
