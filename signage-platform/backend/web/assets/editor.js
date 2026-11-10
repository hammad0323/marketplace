/*
 * Screen / template editor for the web app.
 * window.EDITOR = { mode: 'screen' | 'template', screen_id?, template_id?, orientation?, is_admin }
 */
(function () {
  const { api, el, icon, toast, modal, confirmBox, pickMedia, S } = window.App;
  const E = window.EDITOR || {};
  const TEMPLATE = E.mode === 'template';
  const CANVAS = { landscape: [1920, 1080], portrait: [1080, 1920], square: [1080, 1080] };
  const FONTS = ['Poppins', 'Inter', 'Montserrat', 'Roboto', 'Open Sans', 'Lato', 'Playfair Display', 'Oswald', 'Raleway', 'Merriweather', 'Bebas Neue', 'Nunito', 'Cairo', 'Noto Sans',
    'Amatic SC', 'Caveat', 'Pacifico', 'Lobster', 'Dancing Script', 'Anton', 'Archivo Black', 'Permanent Marker'];
  const ENUMS = {
    fit: ['cover', 'contain', 'fill'], align: ['left', 'center', 'right', 'justify'], vertical_align: ['top', 'center', 'bottom'], alignment: ['left', 'center', 'right'],
    text_effect: ['none', 'shadow', 'glow', 'outline'], style: ['digital', 'analog'], design: ['minimal', 'bold'],
    format: ['24h', '12h'], direction: ['left', 'right', 'up', 'down'], transition: ['fade', 'slide', 'zoom', 'none'], shape: ['rectangle', 'circle', 'line'], units: ['metric', 'imperial'],
    text_direction: ['ltr', 'rtl'], mode: ['rotate', 'daily'], unit: ['days', 'hours', 'minutes', 'hms'], error_correction: ['L', 'M', 'Q', 'H'], view: ['month'], layout: ['card'],
    provider: ['open_meteo'], font_weight: ['100', '200', '300', '400', '500', '600', '700', '800', '900'], font_family: FONTS, type: ['linear', 'radial'],
  };
  const RANGES = {
    font_size: [8, 400, 1], opacity: [0, 1, 0.05], border_radius: [0, 400, 1], border_width: [0, 40, 1], letter_spacing: [-5, 40, 0.5], line_height: [0.8, 3, 0.05], speed: [10, 600, 5],
    gap: [0, 400, 5], padding: [0, 200, 1], rotate_seconds: [2, 120, 1], duration: [1, 600, 1], transition_ms: [0, 3000, 100], overlay_opacity: [0, 1, 0.05], blur: [0, 40, 1],
    forecast_days: [1, 7, 1], refresh_minutes: [0, 1440, 1], columns: [1, 3, 1], default_duration: [1, 120, 1], pause_ms: [0, 10000, 500], first_day_of_week: [1, 7, 1],
    animation_duration_ms: [100, 3000, 100], max_items: [1, 30, 1], refresh_seconds: [10, 3600, 10], shadow_blur: [0, 120, 1], shadow_offset_x: [-80, 80, 1], shadow_offset_y: [-80, 80, 1],
    effect_blur: [0, 60, 1], effect_offset_y: [-30, 30, 1], start_sec: [0, 3600, 1], angle: [0, 360, 5], zoom: [0.25, 3, 0.05],
  };
  const MULTILINE = new Set(['text', 'body', 'message', 'html', 'css', 'description', 'address', 'directions']);
  const CONTENT = ['text', 'spans', 'title', 'message', 'messages', 'media_id', 'items', 'sections', 'quotes', 'zones', 'days', 'events', 'data', 'label', 'url', 'phone', 'email', 'website',
    'address', 'directions', 'target', 'start', 'currency', 'location_name', 'latitude', 'longitude', 'html', 'css', 'prefix', 'suffix', 'json_path', 'finished_text', 'closed_text'];
  const HIDDEN = new Set(['placeholder', 'animation', 'animation_duration_ms', 'animation_delay_ms', 'animation_replay_seconds', 'loop_animation', 'loop_duration_ms', 'loop_intensity']);
  const isColorKey = (k) => k.includes('color') || k === 'foreground' || k === 'background';
  const label = (k) => { const s = k.replace(/_json$/, '').replace(/_/g, ' '); return s.charAt(0).toUpperCase() + s.slice(1); };
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'w' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
  const clone = (o) => JSON.parse(JSON.stringify(o));
  const round = (v, d = 6) => Math.round(v * 10 ** d) / 10 ** d;

  // ------------------------------------------------------------------ state
  const st = {
    doc: null, media: {}, types: [], typeMap: {}, sel: [], history: [], future: [], dirty: false, saving: false, zoom: 1, fitScale: 1,
    screen: null, template: null, categories: [], built: null, clip: null, lastSaved: null,
  };
  const W = () => st.doc.canvas.width, H = () => st.doc.canvas.height;
  const widget = (uid) => st.doc.widgets.find((w) => w.widget_uid === uid);
  const selected = () => st.sel.map(widget).filter(Boolean);
  const canMove = (w) => TEMPLATE || !w.is_locked;

  // ------------------------------------------------------------------ DOM
  const root = document.getElementById('editor');
  const titleIn = el('input', { class: 'title', value: '', 'aria-label': 'Name', oninput: () => { markDirty(false); } });
  const stateLbl = el('span', { class: 'state muted' });
  const undoBtn = el('button', { class: 'btn icon sm hide-sm', title: 'Undo (Ctrl+Z)', onclick: () => undo() }, icon('undo'));
  const redoBtn = el('button', { class: 'btn icon sm hide-sm', title: 'Redo (Ctrl+Y)', onclick: () => redo() }, icon('redo'));
  const saveBtn = el('button', { class: 'btn sm', onclick: () => save(false) }, icon('save'), el('span', { class: 'hide-sm' }, 'Save'));
  const publishBtn = TEMPLATE ? null : el('button', { class: 'btn sm primary', onclick: () => save(true) }, icon('publish'), el('span', { class: 'hide-sm' }, 'Publish'));
  const top = el('div', { class: 'ed-top' },
    el('a', { class: 'btn icon sm', href: S.web + (TEMPLATE ? 'admin/templates.php' : 'screens.php'), title: 'Back' }, icon('arrow_back')),
    titleIn, stateLbl, el('div', { class: 'grow' }),
    undoBtn, redoBtn,
    !TEMPLATE ? el('button', { class: 'btn sm hide-sm', onclick: () => openTemplates() }, icon('auto_awesome_mosaic'), 'Templates') : null,
    el('button', { class: 'btn sm', onclick: () => preview() }, icon('play_arrow'), el('span', { class: 'hide-sm' }, 'Preview')),
    saveBtn, publishBtn);

  const left = el('aside', { class: 'ed-left' });
  const right = el('aside', { class: 'ed-right' });
  const viewport = el('div', { class: 'ed-viewport' });
  const stageWrap = el('div', { style: { position: 'relative', margin: 'auto', flex: 'none' } });
  viewport.append(stageWrap);
  const zoomBox = el('div', { class: 'ed-zoom' },
    el('button', { class: 'btn icon sm', title: 'Zoom in', onclick: () => setZoom(st.zoom * 1.2) }, icon('add')),
    el('button', { class: 'btn icon sm', title: 'Fit', onclick: () => setZoom(1) }, icon('fit_screen')),
    (st.playBtn = el('button', { class: 'btn icon sm', title: 'Play animations on the canvas', onclick: () => togglePlay() }, icon('animation'))),
    el('button', { class: 'btn icon sm', title: 'Zoom out', onclick: () => setZoom(st.zoom / 1.2) }, icon('remove')));
  const canvasArea = el('section', { class: 'ed-canvas' }, viewport, zoomBox);
  const bottom = el('nav', { class: 'ed-bottom' });
  root.append(el('div', { class: 'ed' }, top, left, canvasArea, right, bottom));

  // Left tabs
  let leftTab = 'add';
  const tabs = [['add', 'add_box', 'Add'], ['layers', 'layers', 'Layers'], ['bg', 'wallpaper', 'Background']];
  if (TEMPLATE) tabs.push(['tpl', 'tune', 'Template']);
  const tabBar = el('div', { class: 'ed-tabs' });
  const leftPane = el('div', { class: 'ed-pane' });
  left.append(tabBar, leftPane);
  function drawTabs() {
    tabBar.replaceChildren(...tabs.map(([k, i, l]) => el('button', { class: leftTab === k ? 'on' : '', onclick: () => { leftTab = k; drawTabs(); drawLeft(); } }, icon(i), l)));
  }

  // Mobile bottom bar
  function drawBottom() {
    const b = (i, l, fn, cls = '') => el('button', { class: cls, onclick: fn }, icon(i), l);
    const open = (which, tab) => { if (tab) { leftTab = tab; drawTabs(); drawLeft(); } left.classList.toggle('open', which === 'left' && !(left.classList.contains('open') && (!tab || leftTab === tab) && lastOpen === tab)); right.classList.toggle('open', which === 'right' && !right.classList.contains('open')); if (which === 'right') left.classList.remove('open'); if (which === 'left') right.classList.remove('open'); lastOpen = tab; };
    let lastOpen = null;
    bottom.replaceChildren(
      b('add_box', 'Add', () => open('left', 'add'), 'primary'),
      b('layers', 'Layers', () => open('left', 'layers')),
      b('wallpaper', 'Background', () => open('left', 'bg')),
      TEMPLATE ? b('tune', 'Template', () => open('left', 'tpl')) : b('auto_awesome_mosaic', 'Templates', () => openTemplates()),
      b('edit', 'Edit', () => open('right')));
  }
  const closeDrawers = () => { left.classList.remove('open'); right.classList.remove('open'); };

  // ------------------------------------------------------------------ history
  function snapshot() { return JSON.stringify(st.doc); }
  function commit(redraw = true) {
    st.history.push(st._before || snapshot());
    if (st.history.length > 80) st.history.shift();
    st.future = []; st._before = null;
    markDirty(true);
    if (redraw) render();
  }
  function begin() { st._before = snapshot(); }
  function undo() { if (!st.history.length) return; st.future.push(snapshot()); st.doc = JSON.parse(st.history.pop()); st.sel = st.sel.filter(widget); markDirty(true); render(); }
  function redo() { if (!st.future.length) return; st.history.push(snapshot()); st.doc = JSON.parse(st.future.pop()); st.sel = st.sel.filter(widget); markDirty(true); render(); }
  function markDirty(structural) {
    st.dirty = true;
    stateLbl.textContent = 'Unsaved changes'; stateLbl.style.color = '#E8A317';
    undoBtn.disabled = !st.history.length; redoBtn.disabled = !st.future.length;
    if (!TEMPLATE) { clearTimeout(st.autoT); st.autoT = setTimeout(() => st.dirty && !st.saving && save(false, true), 20000); }
  }
  function markSaved(text) {
    st.dirty = false; stateLbl.textContent = text; stateLbl.style.color = '';
    undoBtn.disabled = !st.history.length; redoBtn.disabled = !st.future.length;
  }
  window.addEventListener('beforeunload', (e) => { if (st.dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ------------------------------------------------------------------ canvas
  function computeFit() {
    const vw = viewport.clientWidth - 60, vh = viewport.clientHeight - 60;
    st.fitScale = Math.max(0.05, Math.min(vw / W(), vh / H()));
  }
  function scale() { return st.fitScale * st.zoom; }
  function setZoom(z) { st.zoom = Math.min(6, Math.max(0.2, z)); layoutStage(); }
  function layoutStage() {
    const s = scale();
    stageWrap.style.width = W() * s + 'px'; stageWrap.style.height = H() * s + 'px';
    if (st.built) st.built.stage.style.transform = `scale(${s})`;
    root.style.setProperty('--inv', String(1 / s));
    drawOverlay();
  }
  new ResizeObserver(() => { if (!st.doc) return; computeFit(); layoutStage(); }).observe(viewport);

  function render() {
    if (st.built) st.built.destroy();
    st.built = SignRender.buildStage(st.doc, { media: st.media, mode: st.playing ? 'preview' : 'editor' });
    const stage = st.built.stage;
    stage.style.boxShadow = '0 10px 40px rgba(0,0,0,.25)';
    stage.style.touchAction = 'none';
    // Hidden widgets are still selectable from Layers, but not drawn.
    stageWrap.replaceChildren(stage);
    stage.addEventListener('pointerdown', onStageDown);
    stage.addEventListener('dblclick', onDbl);
    layoutStage();
    SignRender.refit(stage);
    drawLeft(); drawRight();
  }
  function togglePlay(force) {
    st.playing = force === undefined ? !st.playing : force;
    st.playBtn.replaceChildren(icon(st.playing ? 'pause' : 'animation'));
    st.playBtn.classList.toggle('primary', st.playing);
    st.playBtn.title = st.playing ? 'Stop animations' : 'Play animations on the canvas';
    render();
  }
  /** Replay one element's animation on the canvas (even when animations are paused). */
  function playWidget(w) {
    if (!st.built) return;
    const old = st.built.stage.querySelector(`.sg-w[data-uid="${CSS.escape(w.widget_uid)}"]`); if (!old) return;
    const ctx = { ...st.built.ctx, mode: 'preview', timers: [] };
    const nw = SignRender.widgetBox(w, ctx);
    old.replaceWith(nw); SignRender.refit(nw);
    if (!st.playing) setTimeout(() => { ctx.timers.forEach((f) => f()); if (nw.isConnected) rerenderWidget(w); }, Math.min(30000, (Number(w.configuration.animation_delay_ms) || 0) + (Number(w.configuration.animation_duration_ms) || 800) + 4000));
  }
  /** Re-render one widget only (fast path while typing in the properties panel). */
  function rerenderWidget(w) {
    if (!st.built) return render();
    const old = st.built.stage.querySelector(`.sg-w[data-uid="${CSS.escape(w.widget_uid)}"]`);
    if (!old || w.is_visible === false) return render();
    const nw = SignRender.widgetBox(w, st.built.ctx);
    old.replaceWith(nw);
    SignRender.refit(nw);
    drawOverlay();
  }
  function rerenderBackground() {
    if (!st.built) return render();
    const old = st.built.stage.querySelector('[data-role="bg"]');
    const nb = SignRender.backgroundEl(st.doc.background, st.built.ctx); nb.dataset.role = 'bg';
    old ? old.replaceWith(nb) : st.built.stage.prepend(nb);
  }

  // Selection overlay (in design px inside the stage, handles counter-scaled).
  let overlay = null;
  function drawOverlay() {
    if (!st.built) return;
    overlay && overlay.remove();
    const s = scale(), inv = 1 / s;
    // Lives next to the (clipped) stage so handles stay visible at the canvas edges.
    overlay = el('div', { class: 'ed-overlay', style: { position: 'absolute', left: '0', top: '0', width: W() + 'px', height: H() + 'px', transform: `scale(${s})`, transformOrigin: '0 0', pointerEvents: 'none', zIndex: '5' } });
    for (const w of selected()) {
      const box = el('div', { class: 'sg-sel' + (canMove(w) ? '' : ' locked') });
      Object.assign(box.style, { left: w.position_x * W() + 'px', top: w.position_y * H() + 'px', width: w.width * W() + 'px', height: w.height * H() + 'px', transform: w.rotation ? `rotate(${w.rotation}deg)` : '', borderWidth: 2 * inv + 'px' });
      if (st.sel.length === 1 && canMove(w)) {
        for (const [hx, hy, cur] of [[0, 0, 'nwse'], [0.5, 0, 'ns'], [1, 0, 'nesw'], [1, 0.5, 'ew'], [1, 1, 'nwse'], [0.5, 1, 'ns'], [0, 1, 'nesw'], [0, 0.5, 'ew']]) {
          const hd = el('div', { class: 'sg-h' });
          Object.assign(hd.style, { left: hx * 100 + '%', top: hy * 100 + '%', cursor: cur + '-resize', transform: `scale(${inv})` });
          hd.addEventListener('pointerdown', (e) => startResize(e, w, hx, hy));
          box.append(hd);
        }
        const rot = el('div', { class: 'sg-rot', title: 'Rotate' }, el('span', { class: 'ms', style: { fontSize: '16px' } }, 'rotate_right'));
        Object.assign(rot.style, { transform: `scale(${inv})`, transformOrigin: '50% 100%', top: -46 * inv + 'px' });
        rot.addEventListener('pointerdown', (e) => startRotate(e, w));
        box.append(rot);
      }
      overlay.append(box);
    }
    stageWrap.append(overlay);
  }

  function stagePoint(e) {
    const r = st.built.stage.getBoundingClientRect(), s = scale();
    return { x: (e.clientX - r.left) / s, y: (e.clientY - r.top) / s };
  }
  function onStageDown(e) {
    if (e.button > 0) return;
    const box = e.target.closest('.sg-w');
    if (!box) { st.sel = []; drawOverlay(); drawRight(); drawLeft(); return; }
    const uid = box.dataset.uid;
    if (e.shiftKey || e.ctrlKey || e.metaKey) st.sel = st.sel.includes(uid) ? st.sel.filter((x) => x !== uid) : [...st.sel, uid];
    else if (!st.sel.includes(uid)) st.sel = [uid];
    drawOverlay(); drawRight(); if (leftTab === 'layers') drawLeft();
    const movers = selected().filter(canMove);
    if (!movers.length) { if (selected().some((w) => w.is_locked)) toast('This element is locked — you can still change its content on the right.'); return; }
    e.preventDefault();
    const p0 = stagePoint(e);
    const starts = movers.map((w) => ({ w, x: w.position_x, y: w.position_y }));
    let moved = false;
    begin();
    const move = (ev) => {
      const p = stagePoint(ev);
      let dx = (p.x - p0.x) / W(), dy = (p.y - p0.y) / H();
      if (!moved && Math.abs(p.x - p0.x) + Math.abs(p.y - p0.y) < 3) return;
      moved = true;
      // Snap the primary element's edges/centre to the canvas and other widgets.
      const prim = starts[0];
      const sn = snap(prim.x + dx, prim.y + dy, prim.w.width, prim.w.height, movers.map((m) => m.widget_uid), ev.altKey);
      dx = sn.x - prim.x; dy = sn.y - prim.y;
      for (const s of starts) { s.w.position_x = round(s.x + dx); s.w.position_y = round(s.y + dy); placeBox(s.w); }
      drawOverlay(); showGuides(sn.guides);
    };
    const up = () => {
      window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); showGuides([]);
      if (moved) { commit(false); drawRight(); } else st._before = null;
    };
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
  }
  function onDbl(e) {
    const box = e.target.closest('.sg-w'); if (!box) return;
    const w = widget(box.dataset.uid); if (!w) return;
    st.sel = [w.widget_uid]; drawOverlay(); drawRight();
    right.classList.add('open');
    const f = right.querySelector('[data-key="text"], [data-key="title"], [data-key="message"]');
    if (f) { f.focus(); f.select && f.select(); }
    if (['image', 'logo', 'background_image'].includes(w.widget_type)) pickFor(w, 'media_id', 'image');
  }
  function placeBox(w) {
    const b = st.built.stage.querySelector(`.sg-w[data-uid="${CSS.escape(w.widget_uid)}"]`); if (!b) return;
    Object.assign(b.style, { left: w.position_x * W() + 'px', top: w.position_y * H() + 'px', width: Math.max(1, w.width * W()) + 'px', height: Math.max(1, w.height * H()) + 'px', transform: w.rotation ? `rotate(${w.rotation}deg)` : '' });
  }
  function snap(x, y, w, h, exclude, off) {
    const guides = [];
    if (off) return { x, y, guides };
    const th = 8 / scale();
    const xs = [0, 0.5, 1], ys = [0, 0.5, 1];
    for (const o of st.doc.widgets) if (!exclude.includes(o.widget_uid) && o.is_visible !== false) { xs.push(o.position_x, o.position_x + o.width / 2, o.position_x + o.width); ys.push(o.position_y, o.position_y + o.height / 2, o.position_y + o.height); }
    let bx = null, by = null;
    for (const [edge, off2] of [[x, 0], [x + w / 2, w / 2], [x + w, w]]) for (const t of xs) { const d = Math.abs(edge - t) * W(); if (d < th && (!bx || d < bx.d)) bx = { d, v: t - off2, t }; }
    for (const [edge, off2] of [[y, 0], [y + h / 2, h / 2], [y + h, h]]) for (const t of ys) { const d = Math.abs(edge - t) * H(); if (d < th && (!by || d < by.d)) by = { d, v: t - off2, t }; }
    if (bx) { x = bx.v; guides.push({ v: bx.t }); }
    if (by) { y = by.v; guides.push({ h: by.t }); }
    return { x, y, guides };
  }
  function showGuides(gs) {
    if (!overlay) return;
    overlay.querySelectorAll('.sg-guide').forEach((g) => g.remove());
    const inv = 1 / scale();
    for (const g of gs) {
      const d = el('div', { class: 'sg-guide' });
      if (g.v !== undefined) Object.assign(d.style, { left: g.v * W() + 'px', top: '0', bottom: '0', width: inv + 'px' });
      else Object.assign(d.style, { top: g.h * H() + 'px', left: '0', right: '0', height: inv + 'px' });
      overlay.append(d);
    }
  }
  function startResize(e, w, hx, hy) {
    e.stopPropagation(); e.preventDefault();
    begin();
    const p0 = stagePoint(e), s0 = { x: w.position_x, y: w.position_y, w: w.width, h: w.height };
    const ratio = s0.w * W() / (s0.h * H());
    const rad = (w.rotation || 0) * Math.PI / 180;
    const move = (ev) => {
      const p = stagePoint(ev);
      // Movement in the widget's rotated frame.
      let ddx = p.x - p0.x, ddy = p.y - p0.y;
      const rx = ddx * Math.cos(-rad) - ddy * Math.sin(-rad), ry = ddx * Math.sin(-rad) + ddy * Math.cos(-rad);
      let nw = s0.w * W(), nh = s0.h * H();
      if (hx === 1) nw += rx; if (hx === 0) nw -= rx;
      if (hy === 1) nh += ry; if (hy === 0) nh -= ry;
      const corner = hx !== 0.5 && hy !== 0.5;
      if (corner && (ev.shiftKey || ['image', 'logo', 'qr_code', 'clock'].includes(w.widget_type) && !ev.altKey)) {
        if (Math.abs(rx) > Math.abs(ry)) nh = nw / ratio; else nw = nh * ratio;
      }
      nw = Math.max(10, nw); nh = Math.max(10, nh);
      let nx = s0.x * W(), ny = s0.y * H();
      if (hx === 0) nx = s0.x * W() + (s0.w * W() - nw);
      if (hy === 0) ny = s0.y * H() + (s0.h * H() - nh);
      if (!rad) { // edge snapping for unrotated boxes
        const sn = snap(nx / W(), ny / H(), nw / W(), nh / H(), [w.widget_uid], ev.altKey);
        if (hx === 1 && sn.guides.length) { /* keep simple: only snap right/bottom edges through xs */ }
      }
      w.position_x = round(nx / W()); w.position_y = round(ny / H()); w.width = round(nw / W()); w.height = round(nh / H());
      placeBox(w); drawOverlay();
    };
    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); commit(false); rerenderWidget(w); drawRight(); };
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
  }
  function startRotate(e, w) {
    e.stopPropagation(); e.preventDefault();
    begin();
    const cx = (w.position_x + w.width / 2) * W(), cy = (w.position_y + w.height / 2) * H();
    const move = (ev) => {
      const p = stagePoint(ev);
      let a = Math.atan2(p.y - cy, p.x - cx) * 180 / Math.PI + 90;
      if (!ev.altKey) { const r = Math.round(a / 15) * 15; if (Math.abs(r - a) < 4) a = r; }
      if (a > 180) a -= 360;
      w.rotation = Math.round(a * 10) / 10; placeBox(w); drawOverlay();
    };
    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); commit(false); drawRight(); };
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
  }
  canvasArea.addEventListener('wheel', (e) => { if (e.ctrlKey || e.metaKey) { e.preventDefault(); setZoom(st.zoom * (e.deltaY < 0 ? 1.1 : 0.9)); } }, { passive: false });

  // ------------------------------------------------------------------ actions
  function addWidget(t) {
    if (!t.is_allowed && !TEMPLATE) { toast('“' + t.widget_name + '” is not included in your plan.', true); return; }
    begin();
    const ww = Math.min(0.9, Number(t.default_width) || 0.4), hh = Math.min(0.9, Number(t.default_height) || 0.3);
    const z = st.doc.widgets.reduce((m, w) => Math.max(m, Number(w.z_index) || 0), 0) + 1;
    const w = {
      widget_uid: uuid(), widget_type: t.widget_key, widget_name: t.widget_name, position_x: round((1 - ww) / 2), position_y: round((1 - hh) / 2), width: ww, height: hh,
      rotation: 0, z_index: z, is_locked: false, is_visible: true, configuration: clone(t.default_config_json || {}),
    };
    st.doc.widgets.push(w); st.sel = [w.widget_uid];
    commit(); closeDrawers();
    if (['image', 'logo', 'background_image'].includes(t.widget_key)) pickFor(w, 'media_id', 'image');
    if (t.widget_key === 'video') pickFor(w, 'items', 'video');
  }
  async function pickFor(w, key, type) {
    const m = await pickMedia(type, { global: TEMPLATE });
    if (!m) return;
    st.media[m.media_id] = m;
    begin();
    if (key === 'items') w.configuration.items = [...(w.configuration.items || []), { media_id: m.media_id, duration: 10 }];
    else w.configuration[key] = m.media_id;
    commit();
  }
  function removeSelected() {
    const del = selected().filter((w) => TEMPLATE || !w.is_locked);
    if (!del.length) { toast('Locked elements cannot be deleted.', true); return; }
    begin();
    st.doc.widgets = st.doc.widgets.filter((w) => !del.includes(w));
    st.sel = []; commit();
  }
  function duplicateSelected() {
    const src = selected(); if (!src.length) return;
    begin();
    const z = st.doc.widgets.reduce((m, w) => Math.max(m, Number(w.z_index) || 0), 0);
    st.sel = src.map((w, i) => { const c = clone(w); c.widget_uid = uuid(); c.position_x = round(w.position_x + 0.02); c.position_y = round(w.position_y + 0.02); c.z_index = z + 1 + i; c.is_locked = false; st.doc.widgets.push(c); return c.widget_uid; });
    commit();
  }
  function copySel() { const s = selected(); if (!s.length) return; st.clip = clone(s); try { localStorage.setItem('sg_clip', JSON.stringify({ w: st.clip, media: st.media })); } catch (e) {} toast(s.length + ' copied'); }
  function paste() {
    let clip = st.clip;
    try { const c = JSON.parse(localStorage.getItem('sg_clip') || 'null'); if (c) { clip = c.w; Object.assign(st.media, c.media || {}); } } catch (e) {}
    if (!clip || !clip.length) return;
    begin();
    const z = st.doc.widgets.reduce((m, w) => Math.max(m, Number(w.z_index) || 0), 0);
    st.sel = clip.map((w, i) => { const c = clone(w); c.widget_uid = uuid(); c.position_x = round(c.position_x + 0.02); c.position_y = round(c.position_y + 0.02); c.z_index = z + 1 + i; st.doc.widgets.push(c); return c.widget_uid; });
    commit();
  }
  function nudge(dx, dy) {
    const m = selected().filter(canMove); if (!m.length) return;
    begin();
    m.forEach((w) => { w.position_x = round(w.position_x + dx / W()); w.position_y = round(w.position_y + dy / H()); placeBox(w); });
    commit(false); drawOverlay(); drawRight();
  }
  function order(w, how) {
    begin();
    const list = [...st.doc.widgets].sort((a, b) => a.z_index - b.z_index);
    const i = list.indexOf(w); list.splice(i, 1);
    if (how === 'front') list.push(w); else if (how === 'back') list.unshift(w); else if (how === 'up') list.splice(Math.min(list.length, i + 1), 0, w); else list.splice(Math.max(0, i - 1), 0, w);
    list.forEach((x, k) => (x.z_index = k + 1));
    commit();
  }
  function align(how) {
    const ws = selected().filter(canMove); if (!ws.length) return;
    begin();
    const single = ws.length === 1;
    const minX = single ? 0 : Math.min(...ws.map((w) => w.position_x)), maxX = single ? 1 : Math.max(...ws.map((w) => w.position_x + w.width));
    const minY = single ? 0 : Math.min(...ws.map((w) => w.position_y)), maxY = single ? 1 : Math.max(...ws.map((w) => w.position_y + w.height));
    for (const w of ws) {
      if (how === 'left') w.position_x = minX; if (how === 'right') w.position_x = maxX - w.width; if (how === 'hcenter') w.position_x = (minX + maxX) / 2 - w.width / 2;
      if (how === 'top') w.position_y = minY; if (how === 'bottom') w.position_y = maxY - w.height; if (how === 'vcenter') w.position_y = (minY + maxY) / 2 - w.height / 2;
      w.position_x = round(w.position_x); w.position_y = round(w.position_y);
    }
    if ((how === 'hdist' || how === 'vdist') && ws.length > 2) {
      const k = how === 'hdist' ? ['position_x', 'width'] : ['position_y', 'height'];
      const sorted = [...ws].sort((a, b) => a[k[0]] - b[k[0]]);
      const total = sorted.reduce((s, w) => s + w[k[1]], 0), span = sorted[sorted.length - 1][k[0]] + sorted[sorted.length - 1][k[1]] - sorted[0][k[0]];
      const gap = (span - total) / (sorted.length - 1); let pos = sorted[0][k[0]];
      for (const w of sorted) { w[k[0]] = round(pos); pos += w[k[1]] + gap; }
    }
    commit();
  }

  document.addEventListener('keydown', (e) => {
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable || document.querySelector('.modal-back')) return;
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
    else if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); }
    else if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(false); }
    else if (mod && e.key.toLowerCase() === 'c') copySel();
    else if (mod && e.key.toLowerCase() === 'v') paste();
    else if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicateSelected(); }
    else if (mod && e.key.toLowerCase() === 'a') { e.preventDefault(); st.sel = st.doc.widgets.map((w) => w.widget_uid); drawOverlay(); drawRight(); }
    else if (e.key === 'Delete' || e.key === 'Backspace') { if (st.sel.length) { e.preventDefault(); removeSelected(); } }
    else if (e.key === 'Escape') { st.sel = []; drawOverlay(); drawRight(); closeDrawers(); }
    else if (e.key.startsWith('Arrow') && st.sel.length) {
      e.preventDefault(); const d = e.shiftKey ? 10 : 1;
      nudge(e.key === 'ArrowLeft' ? -d : e.key === 'ArrowRight' ? d : 0, e.key === 'ArrowUp' ? -d : e.key === 'ArrowDown' ? d : 0);
    }
  });

  // ------------------------------------------------------------------ left panels
  function drawLeft() {
    if (leftTab === 'add') return drawAdd();
    if (leftTab === 'layers') return drawLayers();
    if (leftTab === 'bg') return drawBackground();
    if (leftTab === 'tpl') return drawTemplateSettings();
  }
  function drawAdd() {
    const groups = {};
    const names = { text: 'Text', media: 'Images & video', layout: 'Shapes & panels', business: 'Business & menus', time: 'Time & date', data: 'Live data', embed: 'Web', system: 'Alerts' };
    for (const t of st.types.filter((x) => x.availability !== 'planned')) (groups[t.category] = groups[t.category] || []).push(t);
    const orderKeys = ['text', 'media', 'layout', 'business', 'time', 'data', 'embed', 'system'];
    leftPane.replaceChildren(
      el('p', { class: 'muted small', style: { marginTop: 0 } }, 'Tap an element to add it to the canvas. Drag to move, use the handles to resize or rotate.'),
      ...orderKeys.filter((k) => groups[k]).map((k) => el('div', {}, el('div', { class: 'wgroup' }, names[k] || k),
        el('div', { class: 'wtypes' }, ...groups[k].map((t) => el('button', { class: 'wtype' + (t.is_allowed || TEMPLATE ? '' : ' locked'), title: t.description || '', onclick: () => addWidget(t) },
          icon(t.icon || 'widgets'), t.widget_name, t.availability === 'beta' ? el('span', { class: 'small muted' }, 'beta') : null))))));
  }
  function drawLayers() {
    const list = [...st.doc.widgets].sort((a, b) => b.z_index - a.z_index);
    if (!list.length) { leftPane.replaceChildren(el('div', { class: 'empty' }, 'No elements yet.')); return; }
    leftPane.replaceChildren(
      el('p', { class: 'muted small', style: { marginTop: 0 } }, 'Top of the list is in front.'),
      ...list.map((w) => {
        const t = st.typeMap[w.widget_type] || {};
        const row = el('div', { class: 'layer' + (st.sel.includes(w.widget_uid) ? ' on' : ''), onclick: (e) => { if (e.target.closest('button')) return; st.sel = e.shiftKey ? [...new Set([...st.sel, w.widget_uid])] : [w.widget_uid]; drawOverlay(); drawRight(); drawLayers(); } },
          icon(t.icon || 'widgets'), el('span', { class: 'nm' }, w.widget_name || t.widget_name || w.widget_type),
          (TEMPLATE || !w.is_locked) ? el('button', { title: 'Bring forward', onclick: () => order(w, 'up') }, icon('arrow_upward')) : null,
          el('button', { title: w.is_visible === false ? 'Show' : 'Hide', onclick: () => { if (!TEMPLATE && w.is_locked) return toast('Locked by the template', true); begin(); w.is_visible = w.is_visible === false; commit(); } }, icon(w.is_visible === false ? 'visibility_off' : 'visibility')),
          TEMPLATE ? el('button', { title: w.is_locked ? 'Unlock' : 'Lock for users', onclick: () => { begin(); w.is_locked = !w.is_locked; commit(); } }, icon(w.is_locked ? 'lock' : 'lock_open'))
            : (w.is_locked ? el('span', { class: 'ms', title: 'Locked by the template', style: { fontSize: '18px', color: 'var(--muted)' } }, 'lock') : null));
        return row;
      }));
  }

  function colorRow(value, onChange, allowEmpty = true) {
    const hex = (v) => (/^#[0-9a-fA-F]{6}/.test(v || '') ? v.slice(0, 7) : '#000000');
    const txt = el('input', { type: 'text', value: value || '', placeholder: allowEmpty ? 'none' : '#RRGGBB' });
    const pick = el('input', { type: 'color', value: hex(value) });
    pick.addEventListener('input', () => { const a = (txt.value || '').length === 9 ? txt.value.slice(7) : ''; txt.value = pick.value.toUpperCase() + a; onChange(txt.value); });
    txt.addEventListener('change', () => { const v = txt.value.trim(); if (!v && allowEmpty) return onChange(null); if (/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(v)) { pick.value = hex(v); onChange(v.toUpperCase()); } else toast('Use #RRGGBB or #RRGGBBAA', true); });
    const swatches = ['#FFFFFF', '#202124', '#008C86', '#F57C00', '#2E7D32', '#C62828', '#FBC02D', '#00000066'];
    return el('div', {}, el('div', { class: 'colorrow' }, pick, txt, allowEmpty ? el('button', { class: 'btn icon sm', title: 'None', onclick: () => { txt.value = ''; onChange(null); } }, icon('format_color_reset')) : null),
      el('div', { class: 'row', style: { gap: '4px', marginTop: '5px' } }, ...swatches.map((c) => el('button', { title: c, style: { width: '22px', height: '22px', borderRadius: '6px', border: '1px solid var(--border)', background: c, cursor: 'pointer', padding: 0 }, onclick: () => { txt.value = c; pick.value = hex(c); onChange(c); } }))));
  }
  function rangeRow(value, [min, max, step], onChange) {
    const r = el('input', { type: 'range', min, max, step, value: value ?? min });
    const n = el('input', { type: 'number', min, max, step, value: value ?? '' });
    const fire = (v) => { const x = parseFloat(v); if (Number.isFinite(x)) onChange(step < 1 ? Math.round(x * 100) / 100 : Math.round(x)); };
    r.addEventListener('input', () => { n.value = r.value; fire(r.value); });
    n.addEventListener('change', () => { r.value = n.value; fire(n.value); });
    return el('div', { class: 'rng' }, r, n);
  }
  function prop(lbl, input) { return el('div', { class: 'prop' }, el('span', {}, lbl), input); }

  function drawBackground() {
    const bg = st.doc.background = st.doc.background || { type: 'color', value: '#17191C', config: {} };
    bg.config = bg.config || {};
    const c = bg.config;
    const change = (fn, full = false) => { begin(); fn(); commit(false); rerenderBackground(); if (full) drawBackground(); };
    const typeSel = el('div', { class: 'row', style: { gap: '6px' } }, ...[['color', 'Colour'], ['gradient', 'Gradient'], ['preset', 'Preset'], ['image', 'Image'], ['video', 'Video']].map(([k, l]) =>
      el('button', { class: 'chip' + (bg.type === k ? ' on' : ''), onclick: async () => {
        if (k === 'image' || k === 'video') { const m = await pickMedia(k, { global: TEMPLATE }); if (!m) return; st.media[m.media_id] = m; change(() => { bg.type = k; bg.media_id = m.media_id; bg.value = String(m.media_id); }, true); return; }
        change(() => {
          bg.type = k; bg.media_id = null;
          if (k === 'color') bg.value = /^#/.test(bg.value || '') ? bg.value : '#17191C';
          if (k === 'gradient') c.gradient = c.gradient || { type: 'linear', angle: 135, colors: ['#008C86', '#17191C'], stops: [0, 1] };
          if (k === 'preset') bg.value = SignRender.PRESETS[bg.value] ? bg.value : 'teal_night';
        }, true);
      } }, l)));
    const parts = [prop('Type', typeSel)];
    if (bg.type === 'color') parts.push(prop('Colour', colorRow(bg.value, (v) => change(() => (bg.value = v || '#17191C')), false)));
    if (bg.type === 'gradient') {
      const g = c.gradient = c.gradient || { type: 'linear', angle: 135, colors: ['#008C86', '#17191C'] };
      g.colors = g.colors || ['#008C86', '#17191C'];
      parts.push(prop('Style', el('select', { onchange: (e) => change(() => (g.type = e.target.value)) }, ...['linear', 'radial'].map((o) => el('option', { value: o, selected: g.type === o }, label(o))))));
      g.colors.forEach((col, i) => parts.push(prop('Colour ' + (i + 1), colorRow(col, (v) => change(() => (g.colors[i] = v || '#000000')), false))));
      parts.push(el('div', { class: 'row' }, g.colors.length < 4 ? el('button', { class: 'btn sm', onclick: () => change(() => { g.colors.push('#FFFFFF'); delete g.stops; }, true) }, icon('add'), 'Colour') : null,
        g.colors.length > 2 ? el('button', { class: 'btn sm', onclick: () => change(() => { g.colors.pop(); delete g.stops; }, true) }, icon('remove'), 'Colour') : null));
      if (g.type !== 'radial') parts.push(prop('Angle', rangeRow(g.angle ?? 135, RANGES.angle, (v) => change(() => (g.angle = v)))));
    }
    if (bg.type === 'preset') parts.push(el('div', { class: 'grid', style: { gridTemplateColumns: 'repeat(4, 1fr)', gap: '8px' } }, ...Object.entries(SignRender.PRESETS).map(([k, cols]) =>
      el('button', { title: k, style: { aspectRatio: '1', borderRadius: '12px', cursor: 'pointer', border: bg.value === k ? '3px solid var(--teal)' : '1px solid var(--border)', background: `linear-gradient(135deg, ${cols[0]}, ${cols[1]})` }, onclick: () => change(() => (bg.value = k), true) }))));
    if (bg.type === 'image' || bg.type === 'video') {
      const m = st.media[bg.media_id || bg.value];
      parts.push(el('div', { class: 'thumb', style: { marginBottom: '10px' } }, m && m.thumbnail_url ? el('img', { src: m.thumbnail_url, style: { width: '100%', height: '100%', objectFit: 'cover' } }) : null));
      parts.push(el('button', { class: 'btn sm block', style: { marginBottom: '10px' }, onclick: async () => { const nm = await pickMedia(bg.type, { global: TEMPLATE }); if (nm) { st.media[nm.media_id] = nm; change(() => { bg.media_id = nm.media_id; bg.value = String(nm.media_id); }, true); } } }, icon('swap_horiz'), 'Change ' + bg.type));
      parts.push(prop('Fit', el('select', { onchange: (e) => change(() => (c.fit = e.target.value)) }, ...ENUMS.fit.map((o) => el('option', { value: o, selected: (c.fit || 'cover') === o }, label(o))))));
      parts.push(prop('Position', el('select', { onchange: (e) => change(() => (c.position = e.target.value)) }, ...['center', 'top', 'bottom', 'left', 'right'].map((o) => el('option', { value: o, selected: (c.position || 'center') === o }, label(o))))));
    }
    parts.push(el('div', { class: 'wgroup' }, 'Effects'));
    parts.push(prop('Opacity', rangeRow(c.opacity ?? 1, RANGES.opacity, (v) => change(() => (c.opacity = v)))));
    parts.push(prop('Blur', rangeRow(c.blur ?? 0, RANGES.blur, (v) => change(() => (c.blur = v)))));
    parts.push(prop('Overlay colour', colorRow(c.overlay_color || '#000000', (v) => change(() => (c.overlay_color = v || '#000000')), false)));
    parts.push(prop('Overlay opacity', rangeRow(c.overlay_opacity ?? 0, RANGES.overlay_opacity, (v) => change(() => (c.overlay_opacity = v)))));
    leftPane.replaceChildren(...parts);
  }

  function drawTemplateSettings() {
    const t = st.template;
    const field = (k, lbl, input) => { input.addEventListener('change', () => { t[k] = input.type === 'checkbox' ? input.checked : input.value; markDirty(false); }); return el('label', {}, lbl, input); };
    const allLocked = st.doc.widgets.length && st.doc.widgets.every((w) => w.is_locked);
    leftPane.replaceChildren(
      field('category_id', 'Category', el('select', {}, ...st.categories.map((c) => el('option', { value: c.category_id, selected: String(c.category_id) === String(t.category_id) }, c.category_name)))),
      field('description', 'Description', el('textarea', { rows: 2 }, t.description || '')),
      field('tags', 'Tags (comma separated)', el('input', { value: t.tags || '' })),
      field('status', 'Status', el('select', {}, el('option', { value: 'active', selected: t.status !== 'inactive' }, 'Active — visible to users'), el('option', { value: 'inactive', selected: t.status === 'inactive' }, 'Inactive — hidden'))),
      el('label', { style: { flexDirection: 'row', alignItems: 'center', gap: '8px', color: 'var(--text)' } }, (() => { const c = el('input', { type: 'checkbox', checked: !!Number(t.is_premium) || t.is_premium === true }); c.addEventListener('change', () => { t.is_premium = c.checked; markDirty(false); }); return c; })(), 'Premium template (needs a plan with premium templates)'),
      el('div', { class: 'wgroup' }, 'What users can change'),
      el('p', { class: 'muted small' }, 'Locked elements cannot be moved, resized or deleted by users — they can still change the text, prices and photos. Lock individual elements in Layers, or all at once:'),
      el('button', { class: 'btn block', onclick: () => { begin(); st.doc.widgets.forEach((w) => (w.is_locked = !allLocked)); commit(); } }, icon(allLocked ? 'lock_open' : 'lock'), allLocked ? 'Unlock all elements' : 'Lock design for users'),
      el('div', { class: 'wgroup' }, 'Canvas'),
      el('p', { class: 'muted small' }, `${st.doc.canvas.orientation} · ${W()} × ${H()} px`));
  }

  // ------------------------------------------------------------------ properties (right)
  function drawRight() {
    const ws = selected();
    if (!ws.length) {
      right.replaceChildren(el('div', { class: 'ed-pane' },
        el('h3', {}, TEMPLATE ? 'Template' : 'Screen'),
        el('p', { class: 'muted small' }, `${st.doc.canvas.orientation} · ${W()} × ${H()} px · ${st.doc.widgets.length} elements`),
        el('p', { class: 'muted small' }, 'Select an element on the canvas to edit it. Double-click text to edit it, or an image to replace it.'),
        screenAnimationPanel(),
        el('div', { class: 'wgroup' }, 'Shortcuts'),
        el('p', { class: 'small muted', html: 'Ctrl+Z / Ctrl+Y undo, redo · Ctrl+C / Ctrl+V copy, paste · Ctrl+D duplicate · Del delete · Arrows nudge (Shift ×10) · Shift-click multi-select · Alt disables snapping · Ctrl+wheel zoom' })));
      return;
    }
    if (ws.length > 1) {
      const ab = (i, how, t) => el('button', { class: 'btn icon sm', title: t, onclick: () => align(how) }, icon(i));
      right.replaceChildren(el('div', { class: 'ed-pane' }, el('h3', {}, ws.length + ' elements selected'),
        el('div', { class: 'wgroup' }, 'Align'),
        el('div', { class: 'row', style: { gap: '4px' } }, ab('align_horizontal_left', 'left', 'Left'), ab('align_horizontal_center', 'hcenter', 'Centre'), ab('align_horizontal_right', 'right', 'Right'),
          ab('align_vertical_top', 'top', 'Top'), ab('align_vertical_center', 'vcenter', 'Middle'), ab('align_vertical_bottom', 'bottom', 'Bottom'),
          ab('horizontal_distribute', 'hdist', 'Distribute horizontally'), ab('vertical_distribute', 'vdist', 'Distribute vertically')),
        el('div', { class: 'row', style: { marginTop: '14px' } }, el('button', { class: 'btn sm', onclick: duplicateSelected }, icon('content_copy'), 'Duplicate'), el('button', { class: 'btn sm danger', onclick: removeSelected }, icon('delete'), 'Delete'))));
      return;
    }
    const w = ws[0];
    const t = st.typeMap[w.widget_type] || {};
    const locked = !TEMPLATE && w.is_locked;
    const pane = el('div', { class: 'ed-pane' });
    const nameIn = el('input', { value: w.widget_name || '', disabled: locked });
    nameIn.addEventListener('change', () => { begin(); w.widget_name = nameIn.value; commit(false); if (leftTab === 'layers') drawLayers(); });
    pane.append(el('div', { class: 'row', style: { marginBottom: '10px' } }, icon(t.icon || 'widgets'), el('h3', { class: 'grow' }, t.widget_name || w.widget_type),
      el('button', { class: 'btn icon sm', title: 'Close', onclick: () => { right.classList.remove('open'); } }, icon('close'))));
    if (locked) pane.append(el('div', { class: 'alert' }, icon('lock'), ' Position is locked by the template. You can change the content below.'));
    pane.append(prop('Name', nameIn));

    // Content first
    const cfg = w.configuration = w.configuration || {};
    const defaults = t.default_config_json || {};
    const keys = [...new Set([...Object.keys(defaults), ...Object.keys(cfg)])].filter((k) => !HIDDEN.has(k));
    for (const k of keys) if (!(k in cfg)) cfg[k] = clone(defaults[k]);
    const contentKeys = keys.filter((k) => CONTENT.includes(k)).sort((a, b) => CONTENT.indexOf(a) - CONTENT.indexOf(b));
    const styleKeys = keys.filter((k) => !CONTENT.includes(k));
    // Typing is grouped into one undo step (a pause of 0.8 s starts a new one).
    const onEdit = (structural) => {
      if (structural) { st._editBefore = null; commit(); return; }
      if (!st._typing && st._editBefore) { st.history.push(st._editBefore); st.future = []; }
      st._editBefore = null;
      clearTimeout(st._typing); st._typing = setTimeout(() => (st._typing = null), 800);
      markDirty(); rerenderWidget(w);
    };
    const ed = (k) => field(cfg, k, w, onEdit);
    if (w.widget_type === 'weather') pane.append(locationSearch(w));
    if (contentKeys.length) pane.append(el('div', { class: 'wgroup' }, 'Content'), ...contentKeys.map(ed));

    // Geometry
    if (!locked) {
      const px = (v, total) => Math.round(v * total);
      const num = (lbl, val, set) => { const i = el('input', { type: 'number', value: val }); i.addEventListener('change', () => { begin(); set(parseFloat(i.value) || 0); commit(); }); return el('label', { style: { marginBottom: '0' } }, lbl, i); };
      pane.append(el('details', { class: 'sect' }, el('summary', {}, 'Position & size'), el('div', {},
        el('div', { class: 'geom' },
          num('X (px)', px(w.position_x, W()), (v) => (w.position_x = round(v / W()))), num('Y (px)', px(w.position_y, H()), (v) => (w.position_y = round(v / H()))),
          num('Width', px(w.width, W()), (v) => (w.width = round(Math.max(1, v) / W()))), num('Height', px(w.height, H()), (v) => (w.height = round(Math.max(1, v) / H()))),
          num('Rotation °', w.rotation || 0, (v) => (w.rotation = Math.max(-360, Math.min(360, v)))), num('Layer', w.z_index, (v) => (w.z_index = Math.round(v)))),
        el('div', { class: 'row', style: { gap: '4px', marginTop: '10px' } },
          el('button', { class: 'btn icon sm', title: 'Bring to front', onclick: () => order(w, 'front') }, icon('flip_to_front')), el('button', { class: 'btn icon sm', title: 'Send to back', onclick: () => order(w, 'back') }, icon('flip_to_back')),
          el('button', { class: 'btn icon sm', title: 'Centre horizontally', onclick: () => align('hcenter') }, icon('align_horizontal_center')), el('button', { class: 'btn icon sm', title: 'Centre vertically', onclick: () => align('vcenter') }, icon('align_vertical_center')),
          el('button', { class: 'btn icon sm', title: 'Fill canvas', onclick: () => { begin(); Object.assign(w, { position_x: 0, position_y: 0, width: 1, height: 1, rotation: 0 }); commit(); } }, icon('fullscreen'))))));
    }
    if (styleKeys.length) pane.append(el('details', { class: 'sect', open: TEMPLATE || !contentKeys.length }, el('summary', {}, 'Style & effects'), el('div', {}, ...styleKeys.map(ed))));
    pane.append(animationSection(w));
    pane.append(el('div', { class: 'row', style: { marginTop: '14px' } },
      el('button', { class: 'btn sm', onclick: duplicateSelected }, icon('content_copy'), 'Duplicate'),
      TEMPLATE ? el('button', { class: 'btn sm', onclick: () => { begin(); w.is_locked = !w.is_locked; commit(); } }, icon(w.is_locked ? 'lock_open' : 'lock'), w.is_locked ? 'Unlock' : 'Lock') : null,
      !locked ? el('button', { class: 'btn sm danger', onclick: removeSelected }, icon('delete'), 'Delete') : null));
    right.replaceChildren(pane);
  }

  /** Animation settings — available on every element type. */
  function animationSection(w) {
    const c = w.configuration;
    const R = SignRender;
    const change = (k, v, full = false) => { begin(); c[k] = v; commit(false); if (st.playing) rerenderWidget(w); if (full) drawRight(); };
    const sel = (k, opts, cur) => { const s2 = el('select', { 'data-key': k }, ...Object.entries(opts).map(([v, l]) => el('option', { value: v, selected: v === cur }, l))); s2.onchange = () => { change(k, s2.value, true); playWidget(w); }; return s2; };
    const secs = (ms) => (ms / 1000).toFixed(1) + ' s';
    const ent = R.ENTRANCES[c.animation] ? c.animation : 'none', loop = R.LOOPS[c.loop_animation] ? c.loop_animation : 'none';
    const has = ent !== 'none' || loop !== 'none';
    const box = el('div', {},
      el('p', { class: 'muted small', style: { marginTop: 0 } }, 'Plays on the TV, in Preview and when ▶ animations are on. Use “Replay every” so the screen keeps playing like a video.'),
      prop('Entrance', sel('animation', R.ENTRANCES, ent)));
    if (ent !== 'none') {
      box.append(prop('Duration (ms)', rangeRow(Number(c.animation_duration_ms) || 800, [200, 5000, 50], (v) => change('animation_duration_ms', v))),
        prop('Delay (ms) — start after other elements', rangeRow(Number(c.animation_delay_ms) || 0, [0, 20000, 50], (v) => change('animation_delay_ms', v))),
        prop('Replay every (s, 0 = once)', rangeRow(Number(c.animation_replay_seconds) || 0, [0, 120, 1], (v) => change('animation_replay_seconds', v))));
    }
    box.append(prop('Loop effect (continuous)', sel('loop_animation', R.LOOPS, loop)));
    if (loop !== 'none') {
      const def = R.LOOP_MS[loop] || 2000;
      box.append(prop('One cycle (ms, 0 = default ' + secs(def) + ')', rangeRow(Number(c.loop_duration_ms) || 0, [0, 30000, 100], (v) => change('loop_duration_ms', v))),
        prop('Intensity', rangeRow(c.loop_intensity ?? 1, [0.2, 3, 0.05], (v) => change('loop_intensity', v))));
    }
    box.append(el('div', { class: 'row', style: { marginTop: '6px' } },
      el('button', { class: 'btn sm', disabled: !has, onclick: () => playWidget(w) }, icon('play_arrow'), 'Play'),
      has ? el('button', { class: 'btn sm', onclick: () => { begin(); R.ANIM_KEYS.forEach((k) => delete c[k]); commit(); } }, icon('block'), 'Remove') : null));
    return el('details', { class: 'sect', open: has }, el('summary', {}, '✨ Animation' + (has ? ' — ' + [ent !== 'none' ? R.ENTRANCES[ent] : '', loop !== 'none' ? R.LOOPS[loop] : ''].filter(Boolean).join(' + ') : '')), box);
  }
  function screenAnimationPanel() {
    if (!st.anim) st.anim = { style: 'smooth', replay: 12 };
    const A = st.anim;
    const R = SignRender;
    const chips = el('div', { class: 'row', style: { gap: '6px' } });
    const drawChips = () => chips.replaceChildren(...Object.entries(R.SCREEN_STYLES).map(([k, l]) => el('button', { class: 'chip' + (A.style === k ? ' on' : ''), onclick: () => { A.style = k; drawChips(); } }, l)));
    drawChips();
    return el('div', { class: 'card', style: { padding: '12px', margin: '12px 0', background: 'var(--lteal)', border: '0' } },
      el('h3', {}, '✨ Animate whole screen'),
      el('p', { class: 'small muted' }, 'Elements appear one after another from top to bottom, badges pulse, photos float and decorations sway — like a short looping video.'),
      chips,
      prop('Replay scene every (s, 0 = once)', rangeRow(A.replay, [0, 60, 1], (v) => (A.replay = v))),
      el('button', { class: 'btn primary block', onclick: () => {
        begin(); R.applyScreenAnimation(st.doc.widgets, A.style, A.replay); commit();
        if (A.style !== 'none') { togglePlay(true); toast('Animations applied — playing on the canvas. Fine-tune any element in its ✨ Animation section.'); } else toast('Animations removed');
      } }, icon('auto_awesome'), 'Apply'));
  }

  /** One configuration field (recursive for lists / maps). */
  function field(map, k, w, onEdit, depth = 0) {
    const v = map[k];
    const lbl = label(k);
    const set = (x, structural = false) => { if (structural) begin(); else if (!st._typing) st._editBefore = snapshot(); map[k] = x; onEdit(structural); };
    const typeMedia = w.widget_type === 'video' ? 'video' : 'image';

    if (k === 'media_id') {
      const m = st.media[v];
      return prop(lbl === 'Media id' ? 'Image' : lbl, el('div', { class: 'row', style: { flexWrap: 'nowrap' } },
        el('div', { class: 'thumb', style: { width: '72px', aspectRatio: '1', flex: 'none' } }, m && (m.thumbnail_url || m.url) ? el('img', { src: m.thumbnail_url || m.url, style: { width: '100%', height: '100%', objectFit: 'contain' } }) : null),
        el('div', { class: 'grow' }, el('button', { class: 'btn sm block', onclick: async () => { const nm = await pickMedia(typeMedia, { global: TEMPLATE }); if (nm) { st.media[nm.media_id] = nm; set(nm.media_id, true); } } }, icon('image'), v ? 'Replace' : 'Choose'),
          v ? el('button', { class: 'btn sm block danger', style: { marginTop: '6px' }, onclick: () => set(null, true) }, 'Remove') : null)));
    }
    if (k === 'spans') {
      if (!Array.isArray(v) || !v.length) return el('div', { class: 'prop' }, el('button', { class: 'btn sm', onclick: () => set([{ text: map.text || 'Text', bold: false, italic: false, underline: false, color: null }], true) }, icon('format_paint'), 'Mix styles (rich text)'));
    }
    if (ENUMS[k] && (typeof v === 'string' || typeof v === 'number' || v === null)) {
      const opts = ENUMS[k];
      const s = el('select', { 'data-key': k, onchange: (e) => set(k === 'font_weight' ? parseInt(e.target.value, 10) : e.target.value) }, ...(opts.includes(String(v)) ? [] : [el('option', { value: '' }, v === null ? '—' : String(v))]),
        ...opts.map((o) => el('option', { value: o, selected: String(v) === o, style: k === 'font_family' ? { fontFamily: `'${o}'` } : null }, k === 'font_family' || k === 'font_weight' ? o : label(o))));
      return prop(lbl, s);
    }
    if (isColorKey(k) && (v === null || typeof v === 'string')) return prop(lbl, colorRow(v, (x) => set(x)));
    if (typeof v === 'boolean') { const c = el('input', { type: 'checkbox', checked: v }); c.addEventListener('change', () => set(c.checked)); return el('label', { style: { flexDirection: 'row', alignItems: 'center', gap: '8px', color: 'var(--text)', fontWeight: '500' } }, c, lbl); }
    if (typeof v === 'number' && RANGES[k]) return prop(lbl, rangeRow(v, RANGES[k], (x) => set(x)));
    if (typeof v === 'number' || (v === null && (k.endsWith('_sec') || k.endsWith('_id') || k === 'latitude' || k === 'longitude'))) {
      const i = el('input', { type: 'number', step: 'any', value: v ?? '', 'data-key': k }); i.addEventListener('change', () => set(i.value === '' ? null : parseFloat(i.value))); return prop(lbl, i);
    }
    if (Array.isArray(v) && (v.length === 0 && ['messages', 'tags'].includes(k) || typeof v[0] === 'string' || typeof v[0] === 'number')) {
      if (k === 'days_of_week') return el('span');
      const ta = el('textarea', { rows: Math.min(8, Math.max(2, v.length + 1)), 'data-key': k }, v.join('\n'));
      ta.addEventListener('input', () => set(ta.value.split('\n').map((s) => s.trim()).filter(Boolean)));
      return prop(lbl + ' (one per line)', ta);
    }
    if (Array.isArray(v)) return listEditor(map, k, w, onEdit, depth);
    if (v && typeof v === 'object') {
      return el('details', { class: 'sect' }, el('summary', {}, lbl), el('div', {}, ...Object.keys(v).map((kk) => field(v, kk, w, onEdit, depth + 1))));
    }
    if (k === 'gradient' && v === null) {
      return el('div', { class: 'prop' }, el('button', { class: 'btn sm', onclick: () => set({ type: 'linear', angle: 135, colors: ['#008C86', '#006B66'] }, true) }, icon('gradient'), 'Use gradient fill'));
    }
    if (k === 'target' || k === 'start' || k.endsWith('_at') || k === 'date') {
      const i = el('input', { type: 'datetime-local', value: String(v || '').slice(0, 16) }); i.addEventListener('change', () => set(i.value ? i.value + ':00' : '')); return prop(lbl, i);
    }
    const multi = MULTILINE.has(k);
    const i = multi ? el('textarea', { rows: k === 'html' || k === 'css' ? 6 : 3, 'data-key': k }, v ?? '') : el('input', { value: v ?? '', 'data-key': k });
    i.addEventListener('input', () => set(i.value));
    return prop(lbl, i);
  }

  function blankLike(sample) {
    if (!sample || typeof sample !== 'object') return { title: '', body: '' };
    const out = {};
    for (const [k, v] of Object.entries(sample)) out[k] = typeof v === 'string' ? (k === 'start' ? '09:00' : k === 'end' ? '10:00' : k === 'price' ? '0.00' : '') : typeof v === 'boolean' ? v : typeof v === 'number' ? v : Array.isArray(v) ? [] : v && typeof v === 'object' ? blankLike(v) : null;
    return out;
  }
  function listEditor(map, k, w, onEdit, depth) {
    const list = map[k];
    const mediaList = (k === 'items' && ['image_carousel', 'video'].includes(w.widget_type));
    const wrap = el('details', { class: 'sect', open: depth === 0 }, el('summary', {}, `${label(k)} (${list.length})`));
    const body = el('div');
    const structural = (fn) => { begin(); fn(); onEdit(true); };
    list.forEach((item, i) => {
      const card = el('div', { class: 'item-card' });
      const title = item && typeof item === 'object' ? (item.name || item.title || item.label || item.text || (item.media_id ? (st.media[item.media_id] || {}).original_name || 'Media #' + item.media_id : '')) : '';
      card.append(el('div', { class: 'bar' }, el('span', { class: 'grow' }, `${i + 1}. ${String(title).slice(0, 26)}`),
        el('button', { class: 'btn icon sm', title: 'Move up', disabled: i === 0, onclick: () => structural(() => { list.splice(i - 1, 0, list.splice(i, 1)[0]); }) }, icon('arrow_upward')),
        el('button', { class: 'btn icon sm', title: 'Duplicate', onclick: () => structural(() => list.splice(i + 1, 0, clone(item))) }, icon('content_copy')),
        el('button', { class: 'btn icon sm danger', title: 'Remove', onclick: () => structural(() => list.splice(i, 1)) }, icon('delete'))));
      if (item && typeof item === 'object') for (const kk of Object.keys(item)) card.append(field(item, kk, w, onEdit, depth + 1));
      body.append(card);
    });
    body.append(el('button', { class: 'btn sm block', onclick: async () => {
      if (mediaList) { const m = await pickMedia(w.widget_type === 'video' ? 'video' : 'image', { global: TEMPLATE }); if (!m) return; st.media[m.media_id] = m; structural(() => list.push({ media_id: m.media_id, duration: w.widget_type === 'video' ? null : 5 })); return; }
      structural(() => list.push(blankLike(list[0] || defaultItem(k))));
    } }, icon('add'), 'Add ' + (mediaList ? (w.widget_type === 'video' ? 'video' : 'image') : label(k).replace(/s$/, '').toLowerCase())));
    wrap.append(body);
    return wrap;
  }
  function defaultItem(k) {
    return { sections: { name: 'Section', items: [{ name: 'Dish', description: '', price: '9.90', tags: [] }] }, items: { name: 'Item', price: '0.00' }, quotes: { text: '', author: '' }, zones: { label: 'City', timezone: 'Europe/London' },
      events: { date: new Date().toISOString().slice(0, 10), title: 'Event' }, days: { day: 1, open: '09:00', close: '18:00', closed: false } }[k] || { title: '', body: '' };
  }
  function locationSearch(w) {
    const q = el('input', { placeholder: 'Search a city…' });
    const res = el('div');
    const go = async () => {
      if (!q.value.trim()) return;
      try {
        const r = await fetch('https://geocoding-api.open-meteo.com/v1/search?count=5&name=' + encodeURIComponent(q.value.trim())).then((x) => x.json());
        res.replaceChildren(...(r.results || []).map((p) => el('button', { class: 'btn sm block', style: { justifyContent: 'flex-start', marginTop: '4px' }, onclick: () => {
          begin(); Object.assign(w.configuration, { location_name: p.name, latitude: p.latitude, longitude: p.longitude }); commit();
        } }, `${p.name}, ${p.admin1 || ''} ${p.country || ''}`)));
        if (!(r.results || []).length) res.textContent = 'No places found';
      } catch (e) { toast('Location search is unavailable — enter latitude and longitude instead.', true); }
    };
    q.addEventListener('keydown', (e) => e.key === 'Enter' && go());
    return prop('Find location', el('div', {}, el('div', { class: 'row', style: { flexWrap: 'nowrap' } }, q, el('button', { class: 'btn icon sm', onclick: go }, icon('search'))), res));
  }

  // ------------------------------------------------------------------ templates / preview / save
  async function openTemplates() {
    const grid = el('div', { class: 'grid' }, el('div', { class: 'muted' }, 'Loading…'));
    const m = modal('Apply a template', el('div', {}, el('p', { class: 'muted small' }, 'Replaces the current design (you can undo). Only templates enabled for your account are shown.'), grid), { wide: true });
    try {
      const list = await api('templates/list.php');
      grid.replaceChildren();
      if (!list.length) grid.append(el('div', { class: 'empty' }, 'No templates available for your account.'));
      for (const t of list) {
        const th = el('div', { class: 'thumb' });
        const card = el('div', { class: 'card hover tile', style: { cursor: t.is_locked ? 'not-allowed' : 'pointer' } }, th, t.is_premium ? el('span', { class: 'pro' }, 'PRO') : null, el('div', { class: 'name' }, t.template_name), el('div', { class: 'small muted' }, t.category_name + ' · ' + t.orientation));
        card.addEventListener('click', async () => {
          if (t.is_locked) return toast('Premium template — upgrade your plan to use it.', true);
          if (t.orientation !== st.doc.canvas.orientation && !(await confirmBox('Different orientation', `This template is ${t.orientation}; the screen is ${st.doc.canvas.orientation}. Use it anyway? The canvas will change to ${t.orientation}.`, 'Use template'))) return;
          begin();
          const tj = clone(t.template_json);
          tj.widgets = (tj.widgets || []).map((x) => ({ ...x, widget_uid: uuid() }));
          st.doc = { schema_version: 1, canvas: tj.canvas || st.doc.canvas, background: tj.background || st.doc.background, widgets: tj.widgets };
          Object.assign(st.media, t.media || {});
          st.sel = []; m.close(); commit(); computeFit(); layoutStage();
          toast('Template applied — change the text and photos to make it yours.');
        });
        grid.append(card);
        requestAnimationFrame(() => SignRender.thumb(th, t.template_json, t.media));
      }
    } catch (e) { grid.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
  }
  function preview() {
    const ov = el('div', { class: 'preview-overlay' });
    const host = el('div', { style: { position: 'absolute', inset: '0' } });
    let r = null;
    const close = () => { r && r.destroy(); ov.remove(); document.removeEventListener('keydown', esc); };
    const esc = (e) => e.key === 'Escape' && close();
    ov.append(host, el('button', { class: 'close', onclick: close, title: 'Close (Esc)' }, icon('close')));
    document.body.append(ov); document.addEventListener('keydown', esc);
    r = SignRender.mount(host, st.doc, { media: st.media, mode: 'preview' });
    ov.requestFullscreen && ov.requestFullscreen().catch(() => {});
    document.addEventListener('fullscreenchange', function f() { if (!document.fullscreenElement) { document.removeEventListener('fullscreenchange', f); if (ov.isConnected) close(); } });
  }

  async function save(publish, auto = false, force = false) {
    if (st.saving) return;
    st.saving = true; stateLbl.textContent = publish ? 'Publishing…' : 'Saving…';
    try {
      if (TEMPLATE) {
        const t = st.template;
        if (!titleIn.value.trim()) { toast('Give the template a name', true); titleIn.focus(); return; }
        const res = await api('admin/templates.php', {
          action: 'save', template_id: t.template_id || undefined, category_id: Number(t.category_id), template_name: titleIn.value.trim(), description: t.description || '',
          orientation: st.doc.canvas.orientation, template_json: st.doc, tags: t.tags || '', is_premium: !!(t.is_premium === true || Number(t.is_premium)), status: t.status || 'active',
        });
        if (!t.template_id) { t.template_id = res.template_id; history.replaceState(null, '', S.web + 'editor.php?template_id=' + res.template_id); }
        markSaved('Saved'); toast('Template saved — users with access can use it now.');
      } else {
        const res = await api('screens/save-layout.php', { screen_id: st.screen.screen_id, layout_version: st.screen.layout_version, screen_name: titleIn.value.trim() || st.screen.screen_name, layout: st.doc, publish, force });
        st.screen = res; Object.assign(st.media, res.media || {});
        markSaved(publish ? 'Published v' + res.published_version : (auto ? 'Auto-saved' : 'Saved'));
        if (!auto) toast(publish ? 'Published — displays update within a minute.' : 'Saved as draft');
        if (publish && (res.republished_playlists || []).length) toast('Also updated ' + res.republished_playlists.length + ' live playlist(s).');
      }
    } catch (e) {
      if (e.code === 'CONFLICT') {
        st.saving = false;
        if (await confirmBox('Changed somewhere else', 'This screen was saved on another device after you opened it. Overwrite it with your version?', 'Overwrite', true)) return save(publish, false, true);
        stateLbl.textContent = 'Not saved (conflict)';
      } else { stateLbl.textContent = 'Not saved'; toast(e.message, true); }
    } finally { st.saving = false; }
  }

  // ------------------------------------------------------------------ boot
  async function boot() {
    try {
      if (!window.SignRender || !SignRender.SCREEN_STYLES || !SignRender.ENTRANCES || !App.get) {
        throw new Error('Your browser loaded an outdated copy of the app after an update. Press Ctrl+F5 (Cmd+Shift+R on Mac) to reload.');
      }
      const types = await api('widgets/types.php');
      st.types = types.types; st.types.forEach((t) => (st.typeMap[t.widget_key] = t));
      if (TEMPLATE) {
        const cats = await App.get('templates/categories.php', { all: 1 }).catch(() => []);
        st.categories = cats;
        if (E.template_id) {
          const t = await App.get('admin/templates.php', { template_id: E.template_id });
          st.template = t; st.media = t.media || {};
          st.doc = normalize(t.template_json, t.orientation);
          titleIn.value = t.template_name;
        } else {
          const o = CANVAS[E.orientation] ? E.orientation : 'landscape';
          st.template = { template_id: null, category_id: (st.categories[0] || {}).category_id, status: 'active', is_premium: false, tags: '', description: '' };
          st.doc = { schema_version: 1, canvas: { orientation: o, width: CANVAS[o][0], height: CANVAS[o][1] }, background: { type: 'color', value: '#17191C', media_id: null, config: { opacity: 1 } }, widgets: [] };
          titleIn.value = 'New template';
          leftTab = 'add';
        }
        markSaved(E.template_id ? 'Saved' : 'New');
        if (!E.template_id) markDirty(false);
      } else {
        const s = await App.get('screens/details.php', { screen_id: E.screen_id });
        st.screen = s; st.media = s.media || {};
        st.doc = normalize(s.layout, s.orientation);
        titleIn.value = s.screen_name;
        markSaved(s.has_unpublished_changes ? 'Draft — not published yet' : (s.published_version ? 'Published v' + s.published_version : 'Draft'));
      }
      drawTabs(); drawBottom(); computeFit(); render();
    } catch (e) {
      root.replaceChildren(el('div', { class: 'empty' }, el('h2', {}, 'Could not open the editor'), el('p', {}, e.message), el('a', { class: 'btn', href: S.web + (TEMPLATE ? 'admin/templates.php' : 'screens.php') }, 'Back')));
    }
  }
  function normalize(doc, orientation) {
    doc = doc && typeof doc === 'object' ? clone(doc) : {};
    const o = (doc.canvas && doc.canvas.orientation) || orientation || 'landscape';
    const def = CANVAS[o] || CANVAS.landscape;
    doc.canvas = { orientation: o, width: Number((doc.canvas || {}).width) || def[0], height: Number((doc.canvas || {}).height) || def[1] };
    doc.background = doc.background || { type: 'color', value: '#17191C', config: {} };
    doc.background.config = doc.background.config || {};
    doc.widgets = (doc.widgets || []).map((w, i) => ({ rotation: 0, z_index: i + 1, is_locked: false, is_visible: true, configuration: {}, ...w, widget_uid: w.widget_uid || uuid(),
      position_x: Number(w.position_x) || 0, position_y: Number(w.position_y) || 0, width: Number(w.width) || 0.2, height: Number(w.height) || 0.2, rotation: Number(w.rotation) || 0, z_index: Number(w.z_index) || i + 1,
      is_locked: !!(w.is_locked === true || Number(w.is_locked)), is_visible: !(w.is_visible === false || w.is_visible === 0 || w.is_visible === '0') }));
    doc.schema_version = 1;
    return doc;
  }
  boot();
})();
