/* Shared helpers for the Signal Signage web app. */
(function () {
  const S = window.SIGNAGE || {};
  // Theme (remembered per browser)
  try { const t = localStorage.getItem('sg_theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-theme-toggle]');
    if (!t) return;
    e.preventDefault();
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('sg_theme', next); } catch (err) {}
  });

  /** Call the REST API with the session's bearer token. Throws Error(message) with .code/.status/.errors. */
  async function api(path, body, opts = {}) {
    const isForm = body instanceof FormData;
    const method = opts.method || (body !== undefined ? 'POST' : 'GET');
    let url = S.api + path;
    if (method === 'GET' && body && !isForm) { url += (url.includes('?') ? '&' : '?') + new URLSearchParams(clean(body)); body = undefined; }
    let r;
    try {
      r = await fetch(url, {
        method, body: body === undefined ? undefined : (isForm ? body : JSON.stringify(body)),
        headers: { Authorization: 'Bearer ' + S.token, ...(isForm || body === undefined ? {} : { 'Content-Type': 'application/json' }) },
      });
    } catch (e) { throw Object.assign(new Error('Cannot reach the server. Check your connection.'), { network: true }); }
    let j = null;
    try { j = await r.json(); } catch (e) {}
    if (r.status === 401) { location.href = S.web + 'login.php?expired=1'; throw new Error('Session expired'); }
    if (!j) throw Object.assign(new Error('Unexpected server response (' + r.status + ')'), { status: r.status });
    if (!j.status) throw Object.assign(new Error(j.message || 'Request failed'), { code: j.code, status: r.status, errors: j.errors });
    return opts.full ? j : j.data;
  }
  /** GET with query parameters. */
  function get(path, params, opts = {}) { return api(path, params || {}, { ...opts, method: 'GET' }); }
  function clean(o) { const out = {}; for (const k in o) if (o[k] !== null && o[k] !== undefined && o[k] !== '') out[k] = typeof o[k] === 'boolean' ? (o[k] ? 1 : 0) : o[k]; return out; }

  /** Upload with progress → resolves media row. */
  function upload(file, fields = {}, onProgress) {
    return new Promise((resolve, reject) => {
      const fd = new FormData();
      Object.entries(fields).forEach(([k, v]) => v !== null && v !== undefined && fd.append(k, v));
      fd.append('file', file);
      const x = new XMLHttpRequest();
      x.open('POST', S.api + 'media/upload.php');
      x.setRequestHeader('Authorization', 'Bearer ' + S.token);
      x.upload.onprogress = (e) => e.lengthComputable && onProgress && onProgress(e.loaded / e.total);
      x.onload = () => { try { const j = JSON.parse(x.responseText); j.status ? resolve(j.data) : reject(new Error(j.message)); } catch (e) { reject(new Error('Upload failed')); } };
      x.onerror = () => reject(new Error('Upload failed — check your connection'));
      x.send(fd);
    });
  }

  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
  function el(tag, attrs = {}, ...kids) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') n.className = v; else if (k === 'style' && typeof v === 'object') Object.assign(n.style, v);
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v); else if (k === 'html') n.innerHTML = v; else n.setAttribute(k, v === true ? '' : v);
    }
    for (const k of kids.flat()) if (k !== null && k !== undefined && k !== false) n.append(k instanceof Node ? k : document.createTextNode(String(k)));
    return n;
  }
  const icon = (name) => el('span', { class: 'ms' }, name);

  function toast(msg, error = false) {
    const box = document.getElementById('toast') || document.body.appendChild(el('div', { id: 'toast' }));
    const t = el('div', { class: error ? 'error' : '' }, msg);
    box.append(t);
    setTimeout(() => t.remove(), error ? 5000 : 2800);
  }

  /** Modal dialog. content: Node. Returns {close, body, root}. */
  function modal(title, content, { wide = false, actions = [], onClose } = {}) {
    const back = el('div', { class: 'modal-back' });
    const close = () => { back.remove(); onClose && onClose(); };
    const box = el('div', { class: 'modal' + (wide ? ' wide' : '') },
      el('div', { class: 'modal-head' }, el('h2', {}, title), el('button', { class: 'btn icon sm', onclick: close, 'aria-label': 'Close' }, icon('close'))),
      content,
      actions.length ? el('div', { class: 'modal-foot' }, ...actions.map((a) => el('button', { class: 'btn ' + (a.cls || ''), onclick: () => a.onClick(close) }, a.label))) : null);
    back.addEventListener('mousedown', (e) => e.target === back && close());
    back.append(box);
    document.body.append(back);
    return { close, root: box };
  }
  function confirmBox(title, message, okLabel = 'Confirm', danger = false) {
    return new Promise((res) => {
      const m = modal(title, el('p', { class: 'muted' }, message || ''), {
        onClose: () => res(false),
        actions: [{ label: 'Cancel', onClick: (c) => { res(false); c(); } }, { label: okLabel, cls: danger ? 'danger solid' : 'primary', onClick: (c) => { res(true); c(); } }],
      });
      m.root.querySelector('.modal-foot .btn:last-child').focus();
    });
  }
  function promptBox(title, value = '', label = 'Name', okLabel = 'Save') {
    return new Promise((res) => {
      const inp = el('input', { value });
      let done = false;
      const finish = (v, c) => { done = true; res(v); c(); };
      const m = modal(title, el('label', {}, label, inp), {
        onClose: () => !done && res(null),
        actions: [{ label: 'Cancel', onClick: (c) => finish(null, c) }, { label: okLabel, cls: 'primary', onClick: (c) => finish(inp.value.trim(), c) }],
      });
      inp.addEventListener('keydown', (e) => e.key === 'Enter' && finish(inp.value.trim(), m.close));
      setTimeout(() => inp.focus(), 30);
    });
  }
  async function busy(fn, success) {
    try { const r = await fn(); if (success) toast(success); return r; } catch (e) { toast(e.message, true); return null; }
  }
  function timeAgo(s) {
    if (!s) return 'never';
    const d = new Date(String(s).replace(' ', 'T') + (String(s).includes('T') ? '' : 'Z'));
    const sec = (Date.now() - d) / 1000;
    if (sec < 60) return 'just now'; if (sec < 3600) return Math.floor(sec / 60) + ' min ago'; if (sec < 86400) return Math.floor(sec / 3600) + ' h ago';
    if (sec < 604800) return Math.floor(sec / 86400) + ' d ago'; return d.toLocaleDateString();
  }
  function bytes(n) { n = Number(n) || 0; if (n < 1024) return n + ' B'; const u = ['KB', 'MB', 'GB']; let i = -1; do { n /= 1024; i++; } while (n >= 1024 && i < 2); return n.toFixed(n < 10 ? 1 : 0) + ' ' + u[i]; }
  const mb = (m) => (m < 0 ? 'Unlimited' : m >= 1024 ? (m / 1024).toFixed(m % 1024 ? 1 : 0) + ' GB' : m + ' MB');
  const lim = (v) => (v < 0 ? '∞' : v);

  /** Media picker modal → resolves {media_id, url, ...}. type: image|video|document|null. */
  function pickMedia(type, { global = false } = {}) {
    return new Promise((resolve) => {
      let chosen = null;
      const grid = el('div', { class: 'grid', style: { gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))' } });
      const file = el('input', { type: 'file', accept: type === 'video' ? 'video/mp4' : type === 'document' ? 'application/pdf' : 'image/*', style: { display: 'none' } });
      const status = el('span', { class: 'muted small' });
      const load = async () => {
        grid.innerHTML = '';
        const list = await get('media/list.php', { file_type: type, per_page: 100, include_global: global ? 1 : 0 });
        if (!list.length) grid.append(el('div', { class: 'empty', style: { gridColumn: '1/-1' } }, 'No files yet — upload one.'));
        for (const m of list) {
          grid.append(el('div', { class: 'card hover', style: { padding: '6px', cursor: 'pointer' }, onclick: () => { chosen = m; resolve(m); md.close(); } },
            el('div', { class: 'thumb', style: { aspectRatio: '1' } }, m.thumbnail_url && m.file_type !== 'video' ? el('img', { src: m.thumbnail_url, style: { width: '100%', height: '100%', objectFit: 'cover' } }) : el('div', { class: 'sg-ph', style: { fontSize: '14px' } }, icon(m.file_type === 'video' ? 'smart_display' : 'description'))),
            el('div', { class: 'small', style: { marginTop: '4px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, (m.is_global ? '🌐 ' : '') + m.original_name)));
        }
      };
      file.onchange = async () => {
        if (!file.files[0]) return;
        try {
          const m = await upload(file.files[0], global ? { global: 1 } : {}, (p) => (status.textContent = 'Uploading ' + Math.round(p * 100) + '%'));
          chosen = m; resolve(m); md.close();
        } catch (e) { toast(e.message, true); status.textContent = ''; }
      };
      const md = modal('Choose ' + (type || 'media'), el('div', {},
        el('div', { class: 'row', style: { marginBottom: '12px' } }, el('button', { class: 'btn primary', onclick: () => file.click() }, icon('upload'), 'Upload ' + (global ? 'shared ' : '') + (type || 'file')), status, file),
        global ? el('p', { class: 'muted small' }, 'Uploads here are shared with all users so templates can use them.') : null,
        grid), { wide: true, onClose: () => !chosen && resolve(null) });
      load().catch((e) => toast(e.message, true));
    });
  }

  /** Display code dialog for a screen or playlist: view, copy, choose your own, regenerate. */
  async function displayCode(kind, id, name) {
    const key = kind === 'screen' ? 'screen_id' : 'playlist_id';
    const body = el('div', {}, el('p', { class: 'muted' }, 'Loading…'));
    const m = modal('Display code', body);
    const draw = (c) => {
      const playerUrl = location.origin + S.web + 'display.php?code=' + encodeURIComponent(c.code);
      const qrBox = el('div', { style: { width: '150px', height: '150px', background: '#fff', borderRadius: '14px', padding: '10px', flex: 'none' } });
      if (window.qrcode) { const q = window.qrcode(0, 'M'); q.addData(playerUrl); q.make(); qrBox.innerHTML = q.createSvgTag({ scalable: true, margin: 0 }); }
      const own = el('input', { value: c.code, maxlength: 12, style: { textTransform: 'uppercase' } });
      body.replaceChildren(...[
        el('p', { class: 'muted', style: { marginTop: 0 } }, 'Show “' + name + '” on any TV: open the app, tap ', el('b', {}, 'Show Display'), ' and enter this code. TVs with a web browser can open the link or scan the QR code instead.'),
        !c.is_published ? el('div', { class: 'alert warn' }, 'Not published yet — publish it so the TV has something to show.') : null,
        el('div', { class: 'row', style: { alignItems: 'center', gap: '18px', flexWrap: 'wrap' } },
          el('div', { class: 'grow' },
            el('div', { style: { fontSize: '44px', fontWeight: '800', letterSpacing: '8px', color: 'var(--teal)', fontFamily: 'monospace' } }, c.code),
            el('div', { class: 'small muted' }, 'Used ' + (c.view_count || 0) + ' times · last ' + timeAgo(c.last_used_at)),
            el('div', { class: 'row', style: { marginTop: '10px' } },
              el('button', { class: 'btn sm', onclick: () => { navigator.clipboard && navigator.clipboard.writeText(c.code); toast('Code copied'); } }, icon('content_copy'), 'Copy code'),
              el('a', { class: 'btn sm', href: playerUrl, target: '_blank', rel: 'noopener' }, icon('open_in_new'), 'Open web player'))),
          qrBox),
        el('div', { class: 'wgroup', style: { marginTop: '18px' } }, 'Choose your own code'),
        el('div', { class: 'row', style: { flexWrap: 'nowrap' } }, own, el('button', { class: 'btn', onclick: async () => {
          const v = own.value.trim().toUpperCase();
          const r = await busy(() => api('display-codes/set.php', { [key]: id, code: v }), 'Code updated'); if (r) draw(r);
        } }, 'Save')),
        el('p', { class: 'small muted' }, '4–12 letters or numbers, e.g. LOBBY1.'),
        el('button', { class: 'btn sm danger', onclick: async () => {
          if (!(await confirmBox('New code?', 'The current code stops working immediately on every TV that uses it.', 'Generate new code', true))) return;
          const r = await busy(() => api('display-codes/regenerate.php', { [key]: id }), 'New code generated'); if (r) draw(r);
        } }, icon('autorenew'), 'Generate a new code')].filter(Boolean));
    };
    try { draw(await get('display-codes/get.php', { [key]: id })); } catch (e) { body.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
    return m;
  }

  /** Paint a layout thumbnail into every [data-thumb] element once visible. */
  const thumbs = new Map();
  const io = window.IntersectionObserver ? new IntersectionObserver((entries) => entries.forEach((en) => {
    if (!en.isIntersecting) return; io.unobserve(en.target);
    const d = thumbs.get(en.target); if (d) window.SignRender.thumb(en.target, d.layout, d.media);
  }), { rootMargin: '200px' }) : null;
  function thumb(host, layout, media) { if (!io) return window.SignRender.thumb(host, layout, media); thumbs.set(host, { layout, media }); io.observe(host); }

  window.App = { api, get, upload, esc, el, icon, toast, modal, confirmBox, promptBox, busy, timeAgo, bytes, mb, lim, pickMedia, displayCode, thumb, S };
})();
