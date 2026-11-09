/*
 * Playlist player for the browser: plays a publication document (items of complete screens)
 * with durations, transitions, shuffle, loop and validity windows.
 *   const p = SignPlayer.start(hostEl, doc, { mode: 'live' });  p.update(newDoc);  p.destroy();
 */
(function () {
  function mediaMap(doc) {
    const m = {};
    (doc.media || []).forEach((x) => (m[x.media_id] = { ...x, thumbnail_url: x.thumbnail_url || (x.file_type === 'image' ? x.url : null) }));
    (doc.items || []).forEach((it) => ((it.screen || {}).media || []).forEach((x) => { if (!m[x.media_id] && x.url) m[x.media_id] = { ...x, thumbnail_url: x.file_type === 'image' ? x.url : null }; }));
    return m;
  }
  const toTime = (s) => (s ? new Date(String(s).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(s) ? '' : 'Z')).getTime() : null);
  function start(host, doc, opts = {}) {
    let idx = -1, timer = null, current = null, media = mediaMap(doc), dead = false;
    if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
    const onEmpty = opts.onEmpty || (() => {});
    function playable() {
      const now = Date.now();
      return (doc.items || []).filter((it) => it.screen && (!it.valid_from || toTime(it.valid_from) <= now) && (!it.valid_until || toTime(it.valid_until) > now));
    }
    function next() {
      if (dead) return;
      const items = playable();
      if (!items.length) { onEmpty(true); timer = setTimeout(next, 30000); return; }
      onEmpty(false);
      if (doc.shuffle && items.length > 1) { let n; do { n = Math.floor(Math.random() * items.length); } while (n === idx); idx = n; }
      else idx = idx + 1;
      if (idx >= items.length) { if (doc.loop_enabled === false && opts.mode !== 'preview') { idx = items.length - 1; return; } idx = 0; }
      const it = items[idx];
      const tr = it.transition_type && it.transition_type !== 'inherit' ? it.transition_type : ((doc.transition || {}).type || 'fade');
      const ms = Math.max(0, Number((doc.transition || {}).duration_ms) || 800);
      const el = document.createElement('div');
      Object.assign(el.style, { position: 'absolute', inset: '0', transition: tr === 'none' || !current ? 'none' : `opacity ${ms}ms ease, transform ${ms}ms ease` });
      const from = { fade: ['0', 'none'], slide_left: ['1', 'translateX(100%)'], slide: ['1', 'translateX(100%)'], slide_right: ['1', 'translateX(-100%)'], slide_up: ['1', 'translateY(100%)'], slide_down: ['1', 'translateY(-100%)'], zoom: ['0', 'scale(1.15)'], flip: ['0', 'perspective(1200px) rotateY(80deg)'] }[tr] || ['1', 'none'];
      if (current) { el.style.opacity = from[0]; el.style.transform = from[1]; }
      host.append(el);
      const r = window.SignRender.mount(el, it.screen, { media, mode: opts.mode || 'live', timezone: opts.timezone });
      const prev = current; current = { el, r };
      requestAnimationFrame(() => requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'none'; }));
      if (prev) setTimeout(() => { prev.r.destroy(); prev.el.remove(); }, ms + 100);
      if (opts.onItem) opts.onItem(it, idx, items.length);
      if (items.length > 1 || doc.loop_enabled === false || opts.mode === 'preview') timer = setTimeout(next, Math.max(3, Number(it.duration) || Number(doc.default_duration) || 10) * 1000);
      else timer = setTimeout(next, 60000); // single item: re-check validity windows every minute
    }
    next();
    return {
      update(d) { doc = d; media = mediaMap(d); clearTimeout(timer); idx = -1; next(); },
      next() { clearTimeout(timer); next(); },
      destroy() { dead = true; clearTimeout(timer); if (current) { current.r.destroy(); current.el.remove(); current = null; } host.replaceChildren(); },
    };
  }
  window.SignPlayer = { start, mediaMap };
})();
