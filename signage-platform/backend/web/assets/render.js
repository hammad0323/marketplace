/*
 * Signal Signage browser renderer.
 * Draws the same layout JSON the Flutter player uses: a design-resolution stage (e.g. 1920x1080)
 * with normalized widget boxes, scaled to fit its host. Font sizes are design pixels.
 *
 *   const r = SignRender.mount(hostEl, layout, { media, mode: 'preview' });   // scaled, letter-boxed
 *   r.destroy();
 *   const stage = SignRender.buildStage(layout, ctx);   // unscaled stage (used by the editor)
 *
 * mode: 'editor' (static, placeholders), 'preview' / 'live' (animated, timers, video), 'thumb' (static, thumbnails, no video/iframes)
 */
(function () {
  const TEAL = '#008C86';
  const PRESETS = {
    teal_night: ['#006B66', '#17191C'], ocean: ['#008C86', '#1E88E5'], sunset: ['#FB8C00', '#D81B60'], charcoal: ['#2A2D31', '#17191C'],
    mint: ['#E5F5F3', '#FFFFFF'], forest: ['#1B5E20', '#004D40'], royal: ['#311B92', '#006B66'], gold: ['#3E2723', '#C49A2C'],
  };
  const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  // ---------------------------------------------------------------- helpers
  const num = (v, d = 0) => { const n = parseFloat(v); return Number.isFinite(n) ? n : d; };
  const str = (v, d = '') => (v === null || v === undefined ? d : String(v));
  const bool = (v, d = false) => (v === null || v === undefined ? d : v === true || v === 1 || v === '1' || v === 'true');
  const arr = (v) => (Array.isArray(v) ? v : []);
  const obj = (v) => (v && typeof v === 'object' && !Array.isArray(v) ? v : {});
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  function color(v, fallback = null) {
    if (typeof v !== 'string') return fallback;
    let s = v.trim().replace('#', '');
    if (s.length === 3) s = s.split('').map((c) => c + c).join('');
    if (s.length === 6 || s.length === 8) return '#' + s; // CSS understands #RRGGBBAA (same order as the app)
    return fallback;
  }
  function withAlpha(c, a) {
    c = color(c, '#FFFFFF').slice(1, 7);
    return `rgba(${parseInt(c.slice(0, 2), 16)},${parseInt(c.slice(2, 4), 16)},${parseInt(c.slice(4, 6), 16)},${a})`;
  }
  function h(tag, style, ...kids) {
    const n = document.createElement(tag);
    if (style) for (const k in style) if (style[k] !== null && style[k] !== undefined && style[k] !== '') n.style[k] = style[k];
    for (const k of kids.flat()) if (k !== null && k !== undefined && k !== false) n.append(k instanceof Node ? k : document.createTextNode(String(k)));
    return n;
  }
  const ico = (name, size, col) => { const s = h('span', { fontSize: size + 'px', color: col, lineHeight: 1, flex: 'none' }, name); s.className = 'ms'; return s; };
  const fam = (f) => (f ? `'${String(f).replace(/'/g, '')}', 'Inter', sans-serif` : `'Inter', sans-serif`);
  const fitOf = (v) => ({ contain: 'contain', fill: 'fill', fit_width: 'cover', fit_height: 'cover', none: 'none' }[v] || 'cover');
  const posOf = (h2, v2) => `${h2 === 'left' ? 'left' : h2 === 'right' ? 'right' : 'center'} ${v2 === 'top' ? 'top' : v2 === 'bottom' ? 'bottom' : 'center'}`;

  /** CSS text style from a widget config (mirrors the app's textStyleFrom). */
  function textCss(c, size = 40, weight = 400, col = '#FFFFFF') {
    const eff = c.text_effect, ec = color(c.effect_color, '#00000066');
    let shadow = null;
    if (eff === 'shadow') shadow = `0 ${num(c.effect_offset_y, 4)}px ${num(c.effect_blur, 12)}px ${ec}`;
    else if (eff === 'glow') shadow = `0 0 24px ${ec}, 0 0 8px ${ec}`;
    else if (eff === 'outline') shadow = [[-2, -2], [2, -2], [-2, 2], [2, 2]].map(([x, y]) => `${x}px ${y}px 0 ${ec}`).join(',');
    return {
      fontFamily: fam(c.font_family), fontSize: num(c.font_size, size) + 'px', fontWeight: c.font_weight !== undefined && c.font_weight !== null ? String(clamp(num(c.font_weight, 400), 100, 900)) : String(weight),
      fontStyle: bool(c.italic) ? 'italic' : 'normal', textDecoration: bool(c.underline) ? 'underline' : 'none', color: color(c.color, col),
      letterSpacing: num(c.letter_spacing) ? num(c.letter_spacing) + 'px' : null, lineHeight: c.line_height === null || c.line_height === undefined ? '1.2' : String(num(c.line_height, 1.2)),
      textShadow: shadow,
    };
  }
  function panel(c, pad, extra = {}) {
    return h('div', { position: 'absolute', inset: '0', background: color(c.background_color), borderRadius: num(c.border_radius) + 'px', padding: pad + 'px', boxSizing: 'border-box', overflow: 'hidden', ...extra });
  }

  /**
   * Scale-down box (like Flutter's FittedBox(scaleDown)). `inner` is laid out at its natural size
   * (fixed width or max-content) and shrunk to fit; ax/ay align it (0 start, .5 center, 1 end).
   */
  function fit(inner, { width = null, ax = 0.5, ay = 0.5, grow = false } = {}) {
    const wrap = h('div', { position: 'relative', width: '100%', height: '100%', overflow: 'hidden' });
    wrap.className = 'sg-fitw';
    Object.assign(inner.style, { position: 'absolute', left: '0', top: '0', width: width ? width + 'px' : 'max-content', transformOrigin: '0 0' });
    wrap.dataset.ax = ax; wrap.dataset.ay = ay; if (grow) wrap.dataset.grow = '1';
    wrap.append(inner);
    return wrap;
  }
  function refit(root) {
    (root || document).querySelectorAll('.sg-fitw').forEach((w) => {
      const inner = w.firstElementChild; if (!inner) return;
      const W = w.clientWidth, H = w.clientHeight, iw = inner.offsetWidth, ih = inner.offsetHeight;
      if (!W || !H || !iw || !ih) return;
      let s = Math.min(W / iw, H / ih); if (!w.dataset.grow) s = Math.min(1, s);
      const tx = (W - iw * s) * num(w.dataset.ax, 0.5), ty = (H - ih * s) * num(w.dataset.ay, 0.5);
      inner.style.transform = `translate(${tx}px, ${ty}px) scale(${s})`;
    });
  }
  let refitTimer = null;
  const refitSoon = () => { clearTimeout(refitTimer); refitTimer = setTimeout(() => refit(document), 60); };
  if (document.fonts) { document.fonts.addEventListener && document.fonts.addEventListener('loadingdone', refitSoon); document.fonts.ready.then(refitSoon); }

  function placeholder(ctx, icon, label) {
    if (ctx.mode === 'live') return h('div');
    const p = h('div', null, ico(icon, 80), h('div', { padding: '0 10px' }, label));
    p.className = 'sg-ph';
    return p;
  }
  function mediaUrl(ctx, id, thumb = false) {
    if (id === null || id === undefined || id === '') return null;
    const m = (ctx.media || {})[id] || (ctx.media || {})[String(id)];
    if (!m) return null;
    return thumb && m.thumbnail_url ? m.thumbnail_url : m.url;
  }
  const isThumb = (ctx) => ctx.mode === 'thumb';
  const isLive = (ctx) => ctx.mode === 'live' || ctx.mode === 'preview';
  function every(ctx, el, ms, fn) {
    fn();
    if (!isLive(ctx) && ctx.mode !== 'editor') return;
    const t = setInterval(() => { if (!el.isConnected) { clearInterval(t); return; } fn(); }, ms);
    ctx.timers.push(() => clearInterval(t));
  }

  // ---------------------------------------------------------------- time
  const WD = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
  function zoned(tz) {
    const d = new Date();
    if (tz && tz !== 'device') {
      try {
        const p = {};
        new Intl.DateTimeFormat('en-US', { timeZone: tz, year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hourCycle: 'h23', weekday: 'short' })
          .formatToParts(d).forEach((x) => (p[x.type] = x.value));
        return { Y: +p.year, M: +p.month, D: +p.day, h: +p.hour % 24, m: +p.minute, s: +p.second, wd: WD[p.weekday] ?? d.getDay() };
      } catch (e) { /* unknown zone → device time */ }
    }
    return { Y: d.getFullYear(), M: d.getMonth() + 1, D: d.getDate(), h: d.getHours(), m: d.getMinutes(), s: d.getSeconds(), wd: d.getDay() };
  }
  const pad2 = (n) => String(n).padStart(2, '0');
  /** ICU-style date pattern (EEEE, d MMMM yyyy · HH:mm · h:mm a). Quoted text is literal. */
  function fmt(z, pattern) {
    const dayName = DAYS[(z.wd + 6) % 7];
    return String(pattern).split(/('[^']*')/).map((seg) => (seg.startsWith("'") ? seg.slice(1, -1) : seg.replace(/EEEE|EEE|MMMM|MMM|MM|M|yyyy|yy|dd|d|HH|H|hh|h|mm|ss|a/g, (t) => ({
      EEEE: dayName, EEE: dayName.slice(0, 3), MMMM: MONTHS[z.M - 1], MMM: MONTHS[z.M - 1].slice(0, 3), MM: pad2(z.M), M: z.M, yyyy: z.Y, yy: String(z.Y).slice(2),
      dd: pad2(z.D), d: z.D, HH: pad2(z.h), H: z.h, hh: pad2(z.h % 12 || 12), h: z.h % 12 || 12, mm: pad2(z.m), ss: pad2(z.s), a: z.h < 12 ? 'AM' : 'PM',
    }[t])))).join('');
  }
  const timeFmt = (c, sec = false) => { const s = sec || bool(c.show_seconds); return c.format === '12h' ? (s ? 'h:mm:ss a' : 'h:mm a') : (s ? 'HH:mm:ss' : 'HH:mm'); };

  // ---------------------------------------------------------------- widgets
  const R = {};

  R.image = (c, ctx, w) => {
    const logo = w.widget_type === 'logo';
    const url = mediaUrl(ctx, c.media_id, isThumb(ctx));
    if (!url) return placeholder(ctx, logo ? 'verified' : 'image', str(c.placeholder, logo ? 'Logo' : 'Image'));
    const radius = num(c.border_radius), bw = num(c.border_width);
    const img = h('img', { width: '100%', height: '100%', objectFit: fitOf(c.fit || (logo ? 'contain' : 'cover')), objectPosition: posOf(c.alignment), display: 'block', borderRadius: radius + 'px' });
    img.src = url; img.draggable = false; img.alt = '';
    const box = h('div', {
      position: 'absolute', inset: '0', background: color(c.background_color), borderRadius: radius + 'px', border: bw > 0 ? `${bw}px solid ${color(c.border_color, '#FFFFFF')}` : null,
      overflow: radius > 0 ? 'hidden' : 'visible', opacity: String(clamp(num(c.opacity, 1), 0, 1)), boxSizing: 'border-box',
    }, img);
    const sc = color(c.shadow_color);
    // Drop shadow follows the image's transparency (cut-out dish photos get a dish-shaped shadow).
    if (sc && !isThumb(ctx)) img.style.filter = `drop-shadow(${num(c.shadow_offset_x)}px ${num(c.shadow_offset_y, 18)}px ${num(c.shadow_blur, 30) / 2}px ${sc})`;
    return box;
  };
  R.logo = R.image;

  R.background_image = (c, ctx) => {
    const url = mediaUrl(ctx, c.media_id, isThumb(ctx));
    const box = h('div', { position: 'absolute', inset: '0', overflow: 'hidden' });
    if (!url) box.append(placeholder(ctx, 'wallpaper', 'Background image'));
    else { const i = h('img', { width: '100%', height: '100%', objectFit: fitOf(c.fit), filter: num(c.blur) ? `blur(${num(c.blur)}px)` : null, display: 'block' }); i.src = url; i.draggable = false; box.append(i); }
    box.append(h('div', { position: 'absolute', inset: '0', background: withAlpha(c.overlay_color || '#000000', clamp(num(c.overlay_opacity), 0, 1)) }));
    return box;
  };

  R.image_carousel = (c, ctx) => {
    const items = arr(c.items).filter((i) => i && i.media_id);
    if (!items.length) return placeholder(ctx, 'view_carousel', 'Add images');
    const box = h('div', { position: 'absolute', inset: '0', overflow: 'hidden', borderRadius: num(c.border_radius) + 'px', background: '#000' });
    const ms = clamp(num(c.transition_ms, 800), 0, 5000);
    let idx = 0;
    const show = () => {
      const it = items[idx % items.length];
      const img = h('img', { position: 'absolute', inset: '0', width: '100%', height: '100%', objectFit: fitOf(it.fit || c.fit), opacity: '0', transition: `opacity ${ms}ms ease, transform ${ms}ms ease` });
      img.src = mediaUrl(ctx, it.media_id, isThumb(ctx)) || ''; img.draggable = false;
      const tr = c.transition || 'fade';
      if (isLive(ctx) && box.childElementCount) img.style.transform = tr.startsWith('slide') ? 'translateX(30%)' : tr === 'zoom' ? 'scale(1.15)' : 'none';
      box.append(img);
      requestAnimationFrame(() => requestAnimationFrame(() => { img.style.opacity = '1'; img.style.transform = 'none'; }));
      while (box.childElementCount > 2) box.firstElementChild.remove();
      if (!isLive(ctx)) { img.style.transition = 'none'; img.style.opacity = '1'; }
    };
    show();
    if (isLive(ctx) && items.length > 1) {
      const next = () => {
        const d = clamp(num(items[idx % items.length].duration, num(c.default_duration, 5)), 1, 3600) * 1000 + num(c.pause_ms);
        const t = setTimeout(() => {
          if (!box.isConnected) return;
          if (!bool(c.loop, true) && idx >= items.length - 1) return;
          idx = (idx + 1) % items.length; show(); next();
        }, d);
        ctx.timers.push(() => clearTimeout(t));
      };
      next();
    }
    return box;
  };

  R.video = (c, ctx) => {
    const ids = arr(c.items).map((i) => i && i.media_id).filter(Boolean);
    if (c.media_id && !ids.includes(c.media_id)) ids.unshift(c.media_id);
    if (!ids.length) return placeholder(ctx, 'smart_display', 'Add a video');
    const box = h('div', { position: 'absolute', inset: '0', overflow: 'hidden', borderRadius: num(c.border_radius) + 'px', background: '#000' });
    const m0 = (ctx.media || {})[ids[0]] || {};
    if (!isLive(ctx)) {
      if (m0.thumbnail_url) { const i = h('img', { width: '100%', height: '100%', objectFit: fitOf(c.fit), display: 'block' }); i.src = m0.thumbnail_url; box.append(i); }
      box.append(h('div', { position: 'absolute', inset: '0', display: 'flex', alignItems: 'center', justifyContent: 'center' }, ico('play_circle', 140, 'rgba(255,255,255,.85)')));
      return box;
    }
    let i = 0;
    const v = h('video', { width: '100%', height: '100%', objectFit: fitOf(c.fit), display: 'block' });
    v.muted = bool(c.muted, true); v.playsInline = true; v.autoplay = bool(c.autoplay, true);
    const play = () => {
      v.src = mediaUrl(ctx, ids[i % ids.length]) || '';
      v.loop = ids.length === 1 && bool(c.loop, true);
      if (num(c.start_sec)) v.currentTime = num(c.start_sec);
      v.play().catch(() => {});
    };
    v.addEventListener('ended', () => { i++; if (i < ids.length || bool(c.loop, true)) play(); });
    v.addEventListener('timeupdate', () => { if (c.end_sec && v.currentTime >= num(c.end_sec)) { v.pause(); v.dispatchEvent(new Event('ended')); } });
    play();
    box.append(v);
    ctx.timers.push(() => { v.pause(); v.removeAttribute('src'); v.load(); });
    return box;
  };

  function textWidget(c, ctx, heading) {
    const css = textCss(c, heading ? 96 : 40, heading ? 700 : 400);
    const align = c.align || (heading ? 'center' : 'left');
    const ta = { left: 'left', start: 'left', right: 'right', end: 'right', justify: 'justify' }[align] || 'center';
    const p = h('div', { ...css, whiteSpace: 'pre', textAlign: ta });
    if (c.text_direction === 'rtl') p.dir = 'rtl';
    const spans = arr(c.spans);
    if (spans.length) {
      for (const s of spans) p.append(h('span', { fontWeight: bool(s.bold) ? '700' : null, fontStyle: bool(s.italic) ? 'italic' : null, textDecoration: bool(s.underline) ? 'underline' : null, color: color(s.color) }, str(s.text)));
    } else p.textContent = str(c.text);
    const va = c.vertical_align || (heading ? 'center' : 'top');
    const box = panel(c, num(c.padding), { opacity: String(clamp(num(c.opacity, 1), 0, 1)), overflow: 'visible' });
    box.append(fit(p, { ax: ta === 'left' ? 0 : ta === 'right' ? 1 : 0.5, ay: va === 'top' ? 0 : va === 'bottom' ? 1 : 0.5 }));
    box.firstChild.style.overflow = 'visible';
    return box;
  }
  R.heading = (c, ctx) => textWidget(c, ctx, true);
  R.text = (c, ctx) => textWidget(c, ctx, false);

  R.ticker = (c, ctx) => {
    const css = textCss(c, 42, 600);
    const msgs = arr(c.messages).map(String).filter(Boolean);
    const sep = str(c.separator, '•');
    const text = msgs.join(`     ${sep}     `) || ' ';
    const box = h('div', { position: 'absolute', inset: '0', background: color(c.background_color), overflow: 'hidden', display: 'flex', alignItems: 'center' });
    const dir = c.direction || 'left';
    const speed = clamp(num(c.speed, 120), 5, 2000), gap = num(c.gap, 80);
    if (dir === 'up' || dir === 'down') {
      const col = h('div', { position: 'absolute', left: '0', right: '0', top: '0' });
      const list = msgs.length ? msgs : [' '];
      [...list, ...list].forEach((m) => col.append(h('div', { ...css, height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', whiteSpace: 'nowrap', overflow: 'hidden' }, m)));
      box.append(col);
      requestAnimationFrame(() => {
        const H = box.clientHeight || 100;
        [...col.children].forEach((d) => (d.style.height = H + 'px'));
        if (isLive(ctx)) col.animate([{ transform: `translateY(${dir === 'down' ? -H * list.length : 0}px)` }, { transform: `translateY(${dir === 'down' ? 0 : -H * list.length}px)` }], { duration: (H * list.length / speed) * 1000, iterations: Infinity });
      });
      return box;
    }
    const row = h('div', { display: 'flex', whiteSpace: 'nowrap', willChange: 'transform' });
    const copy = () => h('span', { ...css, paddingRight: gap + 'px', flex: 'none' }, text);
    row.append(copy(), copy());
    box.append(row);
    if (isLive(ctx)) {
      requestAnimationFrame(() => {
        const one = row.firstElementChild.offsetWidth || 1000;
        const copies = Math.max(2, Math.ceil((box.clientWidth || 1920) / one) + 1);
        while (row.childElementCount < copies) row.append(copy());
        const kf = dir === 'right' ? [{ transform: `translateX(${-one}px)` }, { transform: 'translateX(0)' }] : [{ transform: 'translateX(0)' }, { transform: `translateX(${-one}px)` }];
        const anim = row.animate(kf, { duration: clamp((one / speed) * 1000, 500, 3600000), iterations: Infinity });
        ctx.timers.push(() => anim.cancel());
      });
    }
    return box;
  };

  R.announcement = (c) => {
    const accent = color(c.accent_color, TEAL), title = color(c.title_color, '#FFFFFF'), body = color(c.color, '#E5F5F3');
    const inner = h('div', { fontFamily: fam(c.font_family) },
      h('div', { display: 'flex', alignItems: 'center', gap: '16px', marginBottom: '24px' }, ico('campaign', 60, accent), h('div', { color: title, fontSize: '56px', fontWeight: '700' }, str(c.title, 'Announcements'))),
      arr(c.items).map((it) => h('div', { display: 'flex', gap: '20px', marginBottom: '22px' },
        h('div', { width: '8px', minHeight: '70px', background: accent, borderRadius: '4px', flex: 'none', marginTop: '4px' }),
        h('div', null, h('div', { color: title, fontSize: '40px', fontWeight: '700' }, str(obj(it).title)), obj(it).body ? h('div', { color: body, fontSize: '34px', lineHeight: '1.35' }, str(it.body)) : null))));
    const p = panel(c, 36); p.append(fit(inner, { width: 900, ax: 0, ay: 0 })); return p;
  };

  R.quote = (c, ctx) => {
    const quotes = arr(c.quotes).map(obj);
    if (!quotes.length) return placeholder(ctx, 'format_quote', 'Add quotes');
    const box = h('div', { position: 'absolute', inset: '0', padding: '24px', boxSizing: 'border-box' });
    const rotate = clamp(num(c.rotate_seconds, 15), 2, 86400);
    const css = textCss(c, 56, 500);
    every(ctx, box, rotate * 1000, () => {
      const day = Math.floor((Date.now() - new Date(new Date().getFullYear(), 0, 1)) / 864e5);
      const q = quotes[c.mode === 'daily' ? day % quotes.length : Math.floor(Date.now() / (rotate * 1000)) % quotes.length];
      const inner = h('div', { textAlign: 'center' }, ico('format_quote', 90, color(c.accent_color, TEAL)),
        h('div', { ...css, fontStyle: 'italic', textAlign: c.align || 'center' }, `“${str(q.text)}”`),
        q.author ? h('div', { ...css, fontSize: num(c.font_size, 56) * 0.55 + 'px', marginTop: '18px', opacity: '.75' }, '— ' + q.author) : null);
      box.replaceChildren(fit(inner, { width: 1400 })); refit(box);
    });
    return box;
  };

  R.daily_message = (c, ctx) => {
    const msgs = arr(c.messages).map(String);
    if (!msgs.length) return placeholder(ctx, 'self_improvement', 'Add messages');
    const day = Math.floor((Date.now() - new Date(2020, 0, 1)) / 864e5);
    const box = h('div', { position: 'absolute', inset: '0', padding: '16px', boxSizing: 'border-box' });
    box.append(fit(h('div', { ...textCss(c, 48, 600), textAlign: c.align || 'center' }, msgs[day % msgs.length]), { width: 1200 }));
    return box;
  };

  R.emergency = (c, ctx) => {
    const col = color(c.color, '#FFFFFF');
    const box = h('div', { position: 'absolute', inset: '0', background: color(c.background_color, '#B00020'), padding: '48px', boxSizing: 'border-box', animation: bool(c.flash, true) && isLive(ctx) ? 'sgFlash 1.8s ease-in-out infinite' : null });
    box.append(fit(h('div', { textAlign: 'center', color: col, fontFamily: fam(c.font_family) }, ico('warning', 160, col),
      h('div', { fontSize: '120px', fontWeight: '800', marginTop: '16px' }, str(c.title, 'Attention')),
      c.message ? h('div', { fontSize: '56px', lineHeight: '1.3', marginTop: '24px' }, str(c.message)) : null), { width: 1600 }));
    return box;
  };

  R.clock = (c, ctx) => {
    const box = h('div', { position: 'absolute', inset: '0' });
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    const tz = !c.timezone || c.timezone === 'device' ? ctx.timezone : c.timezone;
    if (c.style === 'analog') {
      const NS = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(NS, 'svg'); svg.setAttribute('viewBox', '-100 -100 200 200');
      Object.assign(svg.style, { position: 'absolute', inset: '0', width: '100%', height: '100%' });
      let ticks = `<circle r="97" fill="${withAlpha(col, 0.08)}" stroke="${withAlpha(col, 0.6)}" stroke-width="2.5"/>`;
      for (let i = 0; i < 60; i++) { const a = i * Math.PI / 30, big = i % 5 === 0, r1 = big ? 80 : 86; ticks += `<line x1="${Math.sin(a) * r1}" y1="${-Math.cos(a) * r1}" x2="${Math.sin(a) * 91}" y2="${-Math.cos(a) * 91}" stroke="${withAlpha(col, big ? 0.9 : 0.4)}" stroke-width="${big ? 2.5 : 1}" stroke-linecap="round"/>`; }
      const hand = (a, len, w, cc) => `<line x1="0" y1="0" x2="${Math.sin(a) * len}" y2="${-Math.cos(a) * len}" stroke="${cc}" stroke-width="${w}" stroke-linecap="round"/>`;
      box.append(svg);
      every(ctx, box, 1000, () => {
        const z = zoned(tz);
        svg.innerHTML = ticks + hand((z.h % 12 + z.m / 60) * Math.PI / 6, 50, 5, col) + hand((z.m + z.s / 60) * Math.PI / 30, 75, 3.5, col) +
          (bool(c.show_seconds, true) ? hand(z.s * Math.PI / 30, 82, 1.2, accent) : '') + `<circle r="4" fill="${accent}"/>`;
      });
      return box;
    }
    const css = textCss(c, 120, 600);
    const align = c.align || 'center';
    every(ctx, box, 1000, () => {
      const z = zoned(tz);
      const inner = h('div', { textAlign: align, whiteSpace: 'nowrap' }, h('div', css, fmt(z, timeFmt(c))),
        bool(c.show_date, true) ? h('div', { ...css, fontSize: num(c.font_size, 120) * 0.32 + 'px', fontWeight: '500', color: withAlpha(col, 0.8), textShadow: css.textShadow }, fmt(z, str(c.date_format, 'EEEE, d MMMM yyyy'))) : null);
      box.replaceChildren(fit(inner, { ax: align === 'left' ? 0 : align === 'right' ? 1 : 0.5 })); refit(box);
    });
    return box;
  };

  R.world_clock = (c, ctx) => {
    const box = h('div', { position: 'absolute', inset: '0' });
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    every(ctx, box, 15000, () => {
      const inner = h('div', { display: 'flex', fontFamily: fam(c.font_family) }, arr(c.zones).map(obj).map((z) => h('div', { padding: '0 40px', textAlign: 'center' },
        h('div', { color: accent, fontSize: '30px', fontWeight: '700', letterSpacing: '3px' }, str(z.label).toUpperCase()),
        h('div', { color: col, fontSize: '72px', fontWeight: '600' }, fmt(zoned(z.timezone), timeFmt(c))))));
      box.replaceChildren(fit(inner)); refit(box);
    });
    return box;
  };

  R.countdown = (c, ctx) => {
    const box = h('div', { position: 'absolute', inset: '0' });
    const target = new Date(str(c.target));
    if (isNaN(target)) return placeholder(ctx, 'hourglass_bottom', 'Set a target date');
    const css = textCss(c, 96, 700), accent = color(c.accent_color, TEAL), fs = num(c.font_size, 96);
    every(ctx, box, 1000, () => {
      const diff = Math.floor((target - Date.now()) / 1000);
      let inner;
      if (diff < 0) inner = h('div', css, str(c.finished_text, "It's time!"));
      else {
        const days = bool(c.show_days, true);
        const parts = [days ? ['DAYS', Math.floor(diff / 86400)] : null, ['HOURS', days ? Math.floor(diff / 3600) % 24 : Math.floor(diff / 3600)], ['MIN', Math.floor(diff / 60) % 60], bool(c.show_seconds, true) ? ['SEC', diff % 60] : null].filter(Boolean);
        inner = h('div', { textAlign: 'center' }, c.label ? h('div', { ...css, fontSize: fs * 0.4 + 'px', fontWeight: '500' }, str(c.label)) : null,
          h('div', { display: 'flex' }, parts.map(([l, v]) => h('div', { margin: '0 14px', padding: '16px 28px', background: withAlpha(accent, 0.18), borderRadius: '24px', textAlign: 'center' },
            h('div', css, pad2(v)), h('div', { ...css, fontSize: fs * 0.25 + 'px', letterSpacing: '4px', color: accent }, l)))));
      }
      box.replaceChildren(fit(inner)); refit(box);
    });
    return box;
  };

  R.countup = (c, ctx) => {
    const start = new Date(str(c.start));
    if (isNaN(start)) return placeholder(ctx, 'timer', 'Set a start date');
    const box = h('div', { position: 'absolute', inset: '0' });
    const css = textCss(c, 96, 700);
    every(ctx, box, 1000, () => {
      const s = Math.max(0, Math.floor((Date.now() - start) / 1000));
      const v = { hours: Math.floor(s / 3600), minutes: Math.floor(s / 60), hms: `${Math.floor(s / 3600)}:${pad2(Math.floor(s / 60) % 60)}:${pad2(s % 60)}` }[c.unit] ?? Math.floor(s / 86400);
      box.replaceChildren(fit(h('div', { textAlign: 'center' }, h('div', css, v), c.label ? h('div', { ...css, fontSize: num(c.font_size, 96) * 0.35 + 'px', fontWeight: '500' }, str(c.label)) : null))); refit(box);
    });
    return box;
  };

  R.calendar = (c) => {
    const now = new Date(), y = now.getFullYear(), m = now.getMonth();
    const startDow = clamp(num(c.first_day_of_week, 1), 1, 7);
    const firstWd = ((new Date(y, m, 1).getDay() + 6) % 7) + 1;
    const offset = (firstWd - startDow + 7) % 7, days = new Date(y, m + 1, 0).getDate();
    const col = color(c.color, '#FFFFFF'), hi = color(c.highlight_color, TEAL);
    const evDays = new Set(arr(c.events).map((e) => new Date(str(obj(e).date))).filter((d) => !isNaN(d) && d.getMonth() === m && d.getFullYear() === y).map((d) => d.getDate()));
    const grid = h('div', { display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)' });
    for (let i = 0; i < 7; i++) grid.append(h('div', { textAlign: 'center', color: withAlpha(col, 0.6), fontSize: '26px', paddingBottom: '8px' }, DAYS[(startDow - 1 + i) % 7].slice(0, 2)));
    for (let i = 0; i < offset; i++) grid.append(h('div'));
    for (let d = 1; d <= days; d++) {
      const today = d === now.getDate();
      grid.append(h('div', { height: '80px', margin: '3px', borderRadius: '16px', background: today ? hi : null, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontSize: '30px', fontWeight: today ? '700' : '400' },
        String(d), evDays.has(d) ? h('div', { width: '8px', height: '8px', borderRadius: '50%', background: today ? '#fff' : hi }) : null));
    }
    const inner = h('div', { color: col, fontFamily: fam(c.font_family) }, h('div', { textAlign: 'center', fontSize: '44px', fontWeight: '700', marginBottom: '18px' }, `${MONTHS[m]} ${y}`), grid);
    const p = panel(c, 28); p.append(fit(inner, { width: 700 })); return p;
  };

  R.daily_schedule = (c, ctx) => {
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    const p = panel(c, 32);
    every(ctx, p, 30000, () => {
      const z = zoned(ctx.timezone), hm = `${pad2(z.h)}:${pad2(z.m)}`;
      const inner = h('div', { color: col, fontFamily: fam(c.font_family), fontSize: '34px' },
        c.title ? h('div', { fontSize: '50px', fontWeight: '700', marginBottom: '22px' }, str(c.title)) : null,
        arr(c.items).map(obj).map((it) => {
          const cur = bool(c.highlight_current, true) && hm >= str(it.start) && hm < str(it.end, '99:99');
          return h('div', { display: 'flex', alignItems: 'center', marginBottom: '14px', padding: '18px 24px', borderRadius: '20px', background: cur ? accent : withAlpha(col, 0.06) },
            h('div', { width: '230px', flex: 'none', fontWeight: '700', color: cur ? '#fff' : accent }, str(it.start) + (it.end ? ' – ' + it.end : '')),
            h('div', { flex: '1' }, h('div', { fontWeight: '600', color: cur ? '#fff' : col }, str(it.title)), it.subtitle ? h('div', { fontSize: '26px', opacity: '.75', color: cur ? '#fff' : col }, str(it.subtitle)) : null),
            cur ? h('div', { fontSize: '24px', fontWeight: '800', color: '#fff', letterSpacing: '2px' }, 'NOW') : null);
        }));
      p.replaceChildren(fit(inner, { width: 900, ay: 0 })); refit(p);
    });
    return p;
  };

  // ---------- network widgets
  const cache = {};
  async function cachedFetch(url, maxAgeMs, text = false) {
    const k = url + text; const hit = cache[k];
    if (hit && Date.now() - hit.t < maxAgeMs) return hit.v;
    const r = await fetch(url); if (!r.ok) throw new Error('HTTP ' + r.status);
    const v = text ? await r.text() : await r.json(); cache[k] = { t: Date.now(), v }; return v;
  }
  const W_ICON = (code) => (code === 0 ? 'sunny' : code <= 2 ? 'partly_cloudy_day' : code === 3 ? 'cloud' : code <= 48 ? 'foggy' : code <= 67 ? 'rainy' : code <= 77 ? 'weather_snowy' : code <= 82 ? 'rainy' : 'thunderstorm');
  const W_LABEL = (code) => (code === 0 ? 'Clear sky' : code <= 2 ? 'Partly cloudy' : code === 3 ? 'Overcast' : code <= 48 ? 'Fog' : code <= 57 ? 'Drizzle' : code <= 67 ? 'Rain' : code <= 77 ? 'Snow' : code <= 82 ? 'Showers' : 'Thunderstorm');
  function netBox(c, ctx, pad, icon, refreshMs, load, draw) {
    const p = panel(c, pad);
    const msg = (t) => { p.replaceChildren(fit(h('div', { color: color(c.color, '#FFFFFF'), fontSize: '34px', textAlign: 'center', fontFamily: fam(c.font_family) }, ico(icon, 70, color(c.accent_color, TEAL)), h('div', null, t)))); refit(p); };
    if (isThumb(ctx)) { msg(''); return p; }
    msg('Loading…');
    const run = () => load().then((d) => { if (p.isConnected || !ctx.attached) { p.replaceChildren(draw(d)); refit(p); } }).catch((e) => msg(ctx.mode === 'live' ? '' : 'Could not load: ' + e.message));
    setTimeout(run, 0);
    if (isLive(ctx)) { const t = setInterval(() => (p.isConnected ? run() : clearInterval(t)), refreshMs); ctx.timers.push(() => clearInterval(t)); }
    return p;
  }

  R.weather = (c, ctx) => {
    const imperial = c.units === 'imperial', days = clamp(num(c.forecast_days, 3), 1, 7), unit = imperial ? '°F' : '°C';
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${num(c.latitude)}&longitude=${num(c.longitude)}&current=temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=${days + 1}${imperial ? '&temperature_unit=fahrenheit&wind_speed_unit=mph' : ''}`;
    const refresh = clamp(num(c.refresh_minutes, 30), 5, 1440) * 60000;
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    return netBox(c, ctx, 28, 'partly_cloudy_day', refresh, () => cachedFetch(url, refresh), (d) => {
      const cur = obj(d.current), daily = obj(d.daily);
      const fc = [];
      for (let i = 1; i <= days && i < arr(daily.time).length; i++) {
        const dt = new Date(daily.time[i] + 'T12:00');
        fc.push(h('div', { textAlign: 'center', padding: '0 18px' }, h('div', { fontSize: '28px', opacity: '.8' }, DAYS[(dt.getDay() + 6) % 7].slice(0, 3)), ico(W_ICON(daily.weather_code[i]), 56, accent),
          h('div', { fontSize: '28px', fontWeight: '600' }, `${Math.round(daily.temperature_2m_max[i])}° / ${Math.round(daily.temperature_2m_min[i])}°`)));
      }
      const inner = h('div', { color: col, fontFamily: fam(c.font_family), fontSize: '34px' },
        h('div', { fontSize: '36px', fontWeight: '600', opacity: '.9' }, str(c.location_name)),
        h('div', { display: 'flex', alignItems: 'center', gap: '20px' }, ico(W_ICON(cur.weather_code), 140, accent), h('div', null, h('div', { fontSize: '110px', fontWeight: '700', lineHeight: '1' }, Math.round(num(cur.temperature_2m)) + unit), h('div', null, W_LABEL(num(cur.weather_code))))),
        h('div', { display: 'flex', gap: '30px', fontSize: '28px', opacity: '.85', margin: '10px 0' }, bool(c.show_humidity, true) ? h('span', null, ico('humidity_percentage', 30), ' ', Math.round(num(cur.relative_humidity_2m)) + '%') : null,
          bool(c.show_wind, true) ? h('span', null, ico('air', 30), ' ', Math.round(num(cur.wind_speed_10m)) + (imperial ? ' mph' : ' km/h')) : null),
        bool(c.show_forecast, true) ? h('div', { display: 'flex', marginTop: '10px' }, fc) : null);
      return fit(inner);
    });
  };

  function parseFeed(xml) {
    const doc = new DOMParser().parseFromString(xml, 'text/xml');
    const items = [...doc.querySelectorAll('item, entry')];
    const src = doc.querySelector('channel > title, feed > title');
    return items.map((n) => {
      const media = n.querySelector('enclosure[url], content[url], thumbnail[url]');
      return { title: n.querySelector('title')?.textContent?.trim() || '', image: media?.getAttribute('url') || '', source: src?.textContent?.trim() || '' };
    }).filter((i) => i.title);
  }
  R.rss_feed = (c, ctx) => {
    if (!c.url) return placeholder(ctx, 'rss_feed', 'Enter a feed URL');
    const refresh = clamp(num(c.refresh_minutes, 15), 1, 1440) * 60000, rotate = clamp(num(c.rotate_seconds, 8), 2, 3600) * 1000;
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    return netBox(c, ctx, 30, 'rss_feed', refresh, () => cachedFetch(c.url, refresh, true).then(parseFeed), (items) => {
      items = items.slice(0, clamp(num(c.max_items, 10), 1, 50));
      const box = h('div', { position: 'relative', width: '100%', height: '100%' });
      every(ctx, box, rotate, () => {
        const it = items[Math.floor(Date.now() / rotate) % Math.max(1, items.length)] || { title: 'No headlines' };
        box.replaceChildren(h('div', { display: 'flex', gap: '28px', height: '100%', alignItems: 'center', color: col, fontFamily: fam(c.font_family) },
          bool(c.show_image, true) && it.image ? h('img', { height: '100%', maxWidth: '40%', objectFit: 'cover', borderRadius: '16px' }) : null,
          h('div', { flex: '1', minWidth: '0' }, bool(c.show_source, true) && it.source ? h('div', { color: accent, fontWeight: '700', fontSize: '26px', letterSpacing: '2px' }, it.source.toUpperCase()) : null,
            h('div', { fontSize: '44px', fontWeight: '700', lineHeight: '1.25' }, it.title))));
        const img = box.querySelector('img'); if (img) img.src = it.image;
      });
      return box;
    });
  };

  function jsonPath(data, path) {
    let cur = data;
    for (const part of String(path || '$').replace(/^\$\.?/, '').split('.').filter(Boolean)) {
      const m = part.match(/^([^[]*)((\[\d+\])*)$/); if (!m) return null;
      if (m[1]) cur = cur?.[m[1]];
      for (const ix of (m[2].match(/\d+/g) || [])) cur = cur?.[+ix];
    }
    return cur;
  }
  R.live_data = (c, ctx) => {
    if (!c.url) return placeholder(ctx, 'monitoring', 'Enter a data URL');
    const every2 = clamp(num(c.refresh_seconds, 60), 5, 86400) * 1000;
    const css = textCss(c, 96, 700);
    return netBox(c, ctx, 0, 'monitoring', every2, () => cachedFetch(c.url, every2).then((d) => jsonPath(d, c.json_path || '$.value')), (v) => fit(h('div', { textAlign: 'center' },
      h('div', css, str(c.prefix) + str(v ?? '—') + str(c.suffix)), c.label ? h('div', { ...css, fontSize: num(c.font_size, 96) * 0.35 + 'px', fontWeight: '500' }, str(c.label)) : null)));
  };

  R.qr_code = (c) => {
    const data = str(c.data), label = str(c.label);
    const box = h('div', { position: 'absolute', inset: '0', display: 'flex', flexDirection: 'column' });
    const area = h('div', { flex: '1', minHeight: '0', display: 'flex', alignItems: 'center', justifyContent: 'center' });
    const sq = h('div', { height: '100%', aspectRatio: '1', maxWidth: '100%', background: color(c.background, '#FFFFFF'), borderRadius: num(c.border_radius, 16) + 'px', padding: num(c.padding, 16) + 'px', boxSizing: 'border-box' });
    const fg = color(c.foreground, '#202124');
    if (data && window.qrcode) {
      try {
        const q = window.qrcode(0, ['L', 'M', 'Q', 'H'].includes(c.error_correction) ? c.error_correction : 'M'); q.addData(data); q.make();
        const n = q.getModuleCount(); let path = '';
        for (let r = 0; r < n; r++) for (let col = 0; col < n; col++) if (q.isDark(r, col)) path += `M${col} ${r}h1v1h-1z`;
        sq.innerHTML = `<svg viewBox="0 0 ${n} ${n}" width="100%" height="100%" shape-rendering="crispEdges"><path d="${path}" fill="${fg}"/></svg>`;
      } catch (e) { sq.append(ico('qr_code_2', 100, fg)); }
    } else sq.append(h('div', { width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }, ico('qr_code_2', 100, fg)));
    area.append(sq); box.append(area);
    if (label) { const l = h('div', { height: '50px', flex: 'none', marginTop: '10px' }); l.append(fit(h('div', { color: color(c.label_color, '#FFFFFF'), fontSize: '30px', fontWeight: '600' }, label), { grow: false })); box.append(l); }
    return box;
  };

  R.menu_board = (c) => {
    const cols = clamp(num(c.columns, 1), 1, 3), cur = str(c.currency, '$'), f = fam(c.font_family);
    const secC = color(c.section_color, TEAL), itemC = color(c.item_color, '#FFFFFF'), descC = color(c.description_color, '#B8BEC6'), priceC = color(c.price_color, '#FFFFFF');
    const sections = arr(c.sections).map(obj), per = Math.ceil(sections.length / cols) || 1;
    const sec = (s) => h('div', null, s.name ? h('div', { color: secC, fontSize: '40px', fontWeight: '700', letterSpacing: '2px', margin: '8px 0 14px' }, str(s.name).toUpperCase()) : null,
      arr(s.items).map(obj).map((it) => h('div', { display: 'flex', alignItems: 'flex-start', gap: '20px', marginBottom: '18px' },
        h('div', { flex: '1', minWidth: '0' }, h('div', { display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '10px' }, h('span', { color: itemC, fontSize: '36px', fontWeight: '600' }, str(it.name)),
          arr(it.tags).map((t) => h('span', { border: `1px solid ${secC}`, color: secC, borderRadius: '8px', padding: '2px 10px', fontSize: '20px', fontWeight: '700' }, String(t)))),
          it.description ? h('div', { color: descC, fontSize: '26px' }, str(it.description)) : null),
        h('div', { color: priceC, fontSize: '36px', fontWeight: '700', whiteSpace: 'nowrap' }, it.price === '' || it.price === null || it.price === undefined ? '' : cur + it.price))));
    const colsEl = h('div', { display: 'flex', gap: '40px', alignItems: 'flex-start' });
    for (let i = 0; i < cols; i++) colsEl.append(h('div', { flex: '1', minWidth: '0' }, sections.slice(i * per, i * per + per).map(sec)));
    const inner = h('div', { fontFamily: f }, c.title ? h('div', { color: color(c.title_color, '#FFFFFF'), fontSize: '64px', fontWeight: '800', marginBottom: '18px' }, str(c.title)) : null, colsEl);
    const p = panel(c, 28); p.append(fit(inner, { width: 760 * cols, ax: 0, ay: 0 })); return p;
  };

  R.price_list = (c) => {
    const cur = str(c.currency, '$'), itemC = color(c.item_color, '#FFFFFF'), priceC = color(c.price_color, TEAL);
    const inner = h('div', { fontFamily: fam(c.font_family) },
      c.title ? h('div', { color: color(c.title_color, '#FFFFFF'), fontSize: '60px', fontWeight: '800', marginBottom: '22px' }, str(c.title)) : null,
      arr(c.items).map(obj).map((it) => h('div', { display: 'flex', alignItems: 'flex-end', padding: '20px 0', borderBottom: `2px solid ${withAlpha(itemC, 0.15)}` },
        h('div', { flex: '1' }, h('div', { color: itemC, fontSize: '40px', fontWeight: '600' }, str(it.name)), it.note ? h('div', { color: withAlpha(itemC, 0.65), fontSize: '26px' }, str(it.note)) : null),
        h('div', { color: priceC, fontSize: '44px', fontWeight: '800' }, cur + str(it.price)))));
    const p = panel(c, 36); p.append(fit(inner, { width: 900, ay: 0 })); return p;
  };

  R.product_showcase = (c, ctx) => {
    const items = arr(c.items).map(obj);
    if (!items.length) return placeholder(ctx, 'shopping_bag', 'Add products');
    const rotate = clamp(num(c.rotate_seconds, 8), 2, 3600) * 1000;
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL), f = fam(c.font_family);
    const p = panel(c, 0, { display: 'flex', flexDirection: 'column' });
    every(ctx, p, isLive(ctx) ? rotate : 3600000, () => {
      const it = items[isLive(ctx) ? Math.floor(Date.now() / rotate) % items.length : 0];
      const url = mediaUrl(ctx, it.media_id, isThumb(ctx));
      const top = h('div', { flex: '3', position: 'relative', minHeight: '0' });
      if (url) { const i = h('img', { position: 'absolute', inset: '0', width: '100%', height: '100%', objectFit: 'cover' }); i.src = url; top.append(i); } else top.append(placeholder(ctx, 'image', 'Product photo'));
      if (it.badge) top.append(h('div', { position: 'absolute', top: '24px', left: '24px', background: accent, color: '#fff', borderRadius: '12px', padding: '8px 18px', fontFamily: f, fontSize: '28px', fontWeight: '800' }, str(it.badge)));
      const bottom = h('div', { flex: '2', minHeight: '0', padding: '32px', boxSizing: 'border-box' });
      bottom.append(fit(h('div', { color: col, fontFamily: f, fontSize: '40px' }, h('div', { fontSize: '60px', fontWeight: '800' }, str(it.name)), it.description ? h('div', { opacity: '.75' }, str(it.description)) : null,
        h('div', { color: accent, fontSize: '72px', fontWeight: '800', marginTop: '12px' }, str(c.currency, '$') + str(it.price))), { width: 800, ax: 0, ay: 0 }));
      p.replaceChildren(top, bottom);
      if (isLive(ctx)) p.animate([{ opacity: 0 }, { opacity: 1 }], 700);
      requestAnimationFrame(() => refit(p));
    });
    return p;
  };

  R.contact_info = (c) => {
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    const rows = [['call', c.phone], ['mail', c.email], ['language', c.website], ['location_on', c.address]].filter((r) => r[1]);
    const inner = h('div', { color: col, fontFamily: fam(c.font_family), fontSize: '38px' },
      c.title ? h('div', { fontSize: '50px', fontWeight: '700', marginBottom: '18px' }, str(c.title)) : null,
      rows.map(([i, t]) => h('div', { display: 'flex', alignItems: 'center', gap: '18px', marginBottom: '16px' },
        bool(c.show_icons, true) ? h('div', { background: withAlpha(accent, 0.2), borderRadius: '50%', padding: '12px', display: 'flex' }, ico(i, 36, accent)) : null, h('span', { whiteSpace: 'nowrap' }, str(t)))));
    const p = panel(c, 24); p.append(fit(inner, { ax: 0, ay: 0 })); return p;
  };

  R.opening_hours = (c) => {
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL), today = ((new Date().getDay() + 6) % 7) + 1;
    const inner = h('div', { color: col, fontFamily: fam(c.font_family), fontSize: '36px' },
      h('div', { fontSize: '48px', fontWeight: '700', marginBottom: '18px' }, str(c.title, 'Opening Hours')),
      arr(c.days).map(obj).map((d) => {
        const day = clamp(num(d.day, 1), 1, 7), isToday = bool(c.highlight_today, true) && day === today, closed = bool(d.closed);
        return h('div', { display: 'flex', padding: '10px 18px', marginBottom: '4px', borderRadius: '14px', background: isToday ? accent : null },
          h('div', { flex: '1', fontWeight: isToday ? '700' : '400' }, DAYS[day - 1]),
          h('div', { fontWeight: '600', opacity: closed && !isToday ? '.5' : '1' }, closed ? str(c.closed_text, 'Closed') : `${str(d.open)} – ${str(d.close)}`));
      }));
    const p = panel(c, 30); p.append(fit(inner, { width: 640, ay: 0 })); return p;
  };

  R.location_info = (c) => {
    const col = color(c.color, '#FFFFFF'), accent = color(c.accent_color, TEAL);
    const box = h('div', { position: 'absolute', inset: '0' });
    box.append(fit(h('div', { display: 'flex', alignItems: 'center', gap: '18px', color: col, fontFamily: fam(c.font_family), fontSize: '38px' }, ico('location_on', 90, accent),
      h('div', null, c.title ? h('div', { fontSize: '46px', fontWeight: '700' }, str(c.title)) : null, h('div', null, str(c.address)), c.directions ? h('div', { fontSize: '28px', opacity: '.7' }, str(c.directions)) : null)), { ax: 0 }));
    return box;
  };

  R.shape = (c) => {
    const shape = c.shape || 'rectangle', bw = num(c.border_width);
    if (shape === 'line') return h('div', { position: 'absolute', left: '0', right: '0', top: '50%', height: Math.max(2, bw) + 'px', transform: 'translateY(-50%)', background: color(c.fill_color, '#FFFFFF') });
    const g = obj(c.gradient), cols = arr(g.colors).map((x) => color(x)).filter(Boolean);
    return h('div', {
      position: 'absolute', inset: '0', boxSizing: 'border-box', opacity: String(clamp(num(c.opacity, 1), 0, 1)),
      background: cols.length >= 2 ? `linear-gradient(${num(g.angle, 135) + 90}deg, ${cols.join(',')})` : color(c.fill_color),
      borderRadius: shape === 'circle' ? '50%' : num(c.border_radius) + 'px', border: bw > 0 ? `${bw}px solid ${color(c.border_color, '#FFFFFF')}` : null,
      boxShadow: bool(c.shadow) ? `${num(c.shadow_offset_x)}px ${num(c.shadow_offset_y, 12)}px ${num(c.shadow_blur, 30)}px ${color(c.shadow_color, '#00000055')}` : null,
    });
  };

  R.webpage = (c, ctx) => {
    if (!c.url) return placeholder(ctx, 'language', 'Enter a URL');
    if (!isLive(ctx)) return placeholder(ctx, 'language', str(c.url));
    const f = h('iframe', { position: 'absolute', inset: '0', width: '100%', height: '100%', border: '0', background: '#fff', pointerEvents: bool(c.allow_interaction) ? 'auto' : 'none' });
    f.src = c.url; f.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms'); f.referrerPolicy = 'no-referrer';
    const r = num(c.refresh_minutes); if (r > 0) { const t = setInterval(() => (f.isConnected ? (f.src = c.url) : clearInterval(t)), r * 60000); ctx.timers.push(() => clearInterval(t)); }
    return f;
  };
  R.custom_html = (c, ctx) => {
    if (isThumb(ctx)) return placeholder(ctx, 'code', 'Custom HTML');
    const f = h('iframe', { position: 'absolute', inset: '0', width: '100%', height: '100%', border: '0', background: 'transparent', pointerEvents: 'none' });
    f.setAttribute('sandbox', bool(c.allow_scripts) && isLive(ctx) ? 'allow-scripts' : '');
    f.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;background:transparent;overflow:hidden}${str(c.css)}</style></head><body>${str(c.html)}</body></html>`;
    return f;
  };

  // ---------------------------------------------------------------- composition
  function widgetContent(w, ctx) {
    const fn = R[w.widget_type];
    const c = obj(w.configuration);
    let el;
    try { el = fn ? fn(c, ctx, w) : placeholder(ctx, 'extension', `${w.widget_type} (not supported here)`); }
    catch (e) { console.warn('widget', w.widget_type, e); el = placeholder(ctx, 'error', 'Widget error'); }
    const anim = c.animation;
    if (isLive(ctx) && anim && anim !== 'none') {
      const name = { fade_in: 'sgFade', typewriter: 'sgFade', slide_up: 'sgUp', slide_left: 'sgLeft', zoom_in: 'sgZoom' }[anim];
      if (name) el.style.animation = `${name} ${clamp(num(c.animation_duration_ms, 700), 100, 5000)}ms cubic-bezier(.2,.7,.2,1) both`;
    }
    return el;
  }

  /** Absolutely positioned widget box in design px. */
  function widgetBox(w, ctx) {
    const cw = ctx.width, ch = ctx.height;
    const box = document.createElement('div');
    box.className = 'sg-w';
    box.dataset.uid = w.widget_uid;
    Object.assign(box.style, {
      left: num(w.position_x) * cw + 'px', top: num(w.position_y) * ch + 'px', width: Math.max(1, num(w.width) * cw) + 'px', height: Math.max(1, num(w.height) * ch) + 'px',
      transform: num(w.rotation) ? `rotate(${num(w.rotation)}deg)` : '', zIndex: String(num(w.z_index)),
    });
    box.append(widgetContent(w, ctx));
    return box;
  }

  function backgroundEl(bg, ctx) {
    bg = obj(bg); const c = obj(bg.config);
    const outer = h('div', { position: 'absolute', inset: '0', background: '#000', overflow: 'hidden' });
    const base = h('div', { position: 'absolute', inset: '0', opacity: String(clamp(num(c.opacity, 1), 0, 1)), filter: num(c.blur) > 0 ? `blur(${num(c.blur)}px)` : null });
    const type = bg.type || 'color';
    if (type === 'gradient' || type === 'preset') {
      const g = obj(c.gradient);
      let cols = arr(g.colors).map((x) => color(x)).filter(Boolean);
      if (type === 'preset') cols = PRESETS[bg.value] || PRESETS.teal_night;
      if (cols.length < 2) cols = [...cols, '#006B66', '#17191C'].slice(0, 2);
      const stops = arr(g.stops).map((s) => num(s));
      const list = cols.map((x, i) => (stops.length === cols.length ? `${x} ${stops[i] * 100}%` : x)).join(',');
      base.style.background = g.type === 'radial' ? `radial-gradient(circle ${Math.min(ctx.width, ctx.height)}px at 50% 50%, ${list})` : `linear-gradient(${num(g.angle, 135) + 90}deg, ${list})`;
    } else if (type === 'image') {
      const url = mediaUrl(ctx, bg.media_id ?? bg.value, isThumb(ctx));
      base.style.background = '#17191C';
      if (url) { const i = h('img', { width: '100%', height: '100%', objectFit: fitOf(c.fit), objectPosition: { top: 'center top', bottom: 'center bottom', left: 'left center', right: 'right center' }[c.position] || 'center', display: 'block' }); i.src = url; i.draggable = false; base.append(i); }
    } else if (type === 'video') {
      const id = bg.media_id ?? bg.value;
      if (isLive(ctx) && mediaUrl(ctx, id)) {
        const v = h('video', { width: '100%', height: '100%', objectFit: fitOf(c.fit), display: 'block' });
        v.muted = true; v.loop = true; v.autoplay = true; v.playsInline = true; v.src = mediaUrl(ctx, id); v.play().catch(() => {});
        base.append(v); ctx.timers.push(() => v.pause());
      } else { const m = (ctx.media || {})[id]; if (m && m.thumbnail_url) { const i = h('img', { width: '100%', height: '100%', objectFit: 'cover' }); i.src = m.thumbnail_url; base.append(i); } else base.append(h('div', { position: 'absolute', inset: '0', display: 'flex', alignItems: 'center', justifyContent: 'center' }, ico('movie', 160, 'rgba(255,255,255,.3)'))); }
    } else base.style.background = color(bg.value, '#17191C');
    outer.append(base);
    const oo = clamp(num(c.overlay_opacity), 0, 1);
    if (oo > 0) outer.append(h('div', { position: 'absolute', inset: '0', background: withAlpha(c.overlay_color || '#000000', oo) }));
    return outer;
  }

  function newCtx(layout, opts) {
    const canvas = obj(layout.canvas);
    return { media: opts.media || {}, mode: opts.mode || 'preview', timezone: opts.timezone || null, width: num(canvas.width, 1920), height: num(canvas.height, 1080), timers: [], attached: false };
  }

  /** Unscaled stage element. Returns {stage, ctx, destroy}. */
  function buildStage(layout, opts = {}) {
    const ctx = newCtx(layout, opts);
    const stage = document.createElement('div');
    stage.className = 'sg-stage';
    Object.assign(stage.style, { width: ctx.width + 'px', height: ctx.height + 'px' });
    const bg = backgroundEl(layout.background, ctx); bg.dataset.role = 'bg';
    stage.append(bg);
    arr(layout.widgets).filter((w) => w && w.is_visible !== false && w.is_visible !== 0).sort((a, b) => num(a.z_index) - num(b.z_index)).forEach((w) => stage.append(widgetBox(w, ctx)));
    const destroy = () => { ctx.timers.splice(0).forEach((f) => { try { f(); } catch (e) {} }); };
    return { stage, ctx, destroy };
  }

  /** Render a layout scaled to fit (letter-boxed) inside host. */
  function mount(host, layout, opts = {}) {
    host.classList.add('sg-host');
    if (opts.letterbox) host.style.background = opts.letterbox;
    let built = buildStage(layout, opts);
    host.replaceChildren(built.stage);
    built.ctx.attached = true;
    const scale = () => {
      const W = host.clientWidth, H = host.clientHeight; if (!W || !H) return;
      const s = Math.min(W / built.ctx.width, H / built.ctx.height);
      built.stage.style.transform = `translate(${(W - built.ctx.width * s) / 2}px, ${(H - built.ctx.height * s) / 2}px) scale(${s})`;
    };
    scale(); refit(built.stage); requestAnimationFrame(() => { scale(); refit(built.stage); });
    const ro = window.ResizeObserver ? new ResizeObserver(scale) : null; ro && ro.observe(host);
    return {
      get stage() { return built.stage; },
      update(newLayout, newOpts = {}) { built.destroy(); built = buildStage(newLayout, { ...opts, ...newOpts }); host.replaceChildren(built.stage); built.ctx.attached = true; scale(); refit(built.stage); },
      destroy() { built.destroy(); ro && ro.disconnect(); host.replaceChildren(); },
    };
  }

  /** Lightweight thumbnail (used in grids). */
  function thumb(host, layout, media) {
    if (!layout || !layout.canvas) { host.innerHTML = ''; return null; }
    return mount(host, layout, { media, mode: 'thumb', letterbox: '#101214' });
  }

  window.SignRender = { mount, thumb, buildStage, widgetBox, widgetContent, backgroundEl, refit, color, fmt, zoned, PRESETS };
})();
