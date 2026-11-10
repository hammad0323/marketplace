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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Amatic+SC:wght@700&family=Bebas+Neue&family=Oswald:wght@400;600;700&family=Caveat:wght@500;700&family=Pacifico&family=Playfair+Display:wght@400;700&family=Montserrat:wght@400;600;800&family=Lobster&family=Dancing+Script:wght@600&family=Anton&family=Archivo+Black&family=Permanent+Marker&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Lato:wght@400;700&family=Raleway:wght@400;700&family=Merriweather:wght@400;700&family=Nunito:wght@400;700&family=Cairo:wght@400;700&family=Noto+Sans:wght@400;700&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
<link rel="stylesheet" href="<?= h(web_asset('web.css')) ?>">
<style>
  html, body { background: #000; height: 100%; overflow: hidden; }
  #player { position: fixed; inset: 0; }
  #emergency { position: fixed; inset: 0; z-index: 50; display: none; }
  #corner { position: fixed; right: 14px; bottom: 14px; z-index: 60; color: #fff; background: rgba(0,0,0,.55); padding: 8px 12px; border-radius: 10px; font-size: 13px; display: none; }
  body.auth { background: #17191C; }
</style>
<script src="<?= h(web_asset('vendor/qrcode.js')) ?>"></script>
<script>window.SIGNAGE = <?= json_encode(['api' => $base . '/api/', 'web' => $base . '/web/', 'token' => '']) ?>;</script>
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
  function play() {
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
    form.style.display = 'none'; document.body.className = ''; player.style.display = '';
    try {
      const d = await fetchContent(true);
      doc = d.doc; hash = d.content_hash; lastFull = Date.now();
      emergency(d.emergency); corner.style.display = 'none';
      play();
      setTimeout(poll, (d.refresh_seconds || 60) * 1000);
    } catch (e) {
      if (e.code === 'CODE_NOT_FOUND' || e.code === 'CODE_INACTIVE' || e.code === 'NOT_PUBLISHED' || e.code === 'VALIDATION') return showForm(e.message);
      showForm('Could not connect: ' + e.message);
    }
  }
  async function poll() {
    try {
      const full = Date.now() - lastFull > 6 * 3600 * 1000;   // refresh signed media links a few times a day
      const d = await fetchContent(full);
      emergency(d.emergency); corner.style.display = 'none';
      if (d.doc) { doc = d.doc; hash = d.content_hash; if (full) lastFull = Date.now(); play(); }
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
  document.addEventListener('mousemove', () => { document.body.style.cursor = ''; clearTimeout(window._cur); window._cur = setTimeout(() => (document.body.style.cursor = 'none'), 3000); });
  code ? start() : showForm('');
})();
</script>
</body></html>
