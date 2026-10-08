/* ==========================================================
   Eashal Mirha — storefront interactions
   ========================================================== */
(function () {
  'use strict';
  const body = document.body;
  const BASE = body.dataset.base || '';
  const CSRF = body.dataset.csrf || '';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Preloader ---------- */
  const pre = $('#preloader');
  const hidePre = () => pre && pre.classList.add('done');
  window.addEventListener('load', () => setTimeout(hidePre, 350));
  setTimeout(hidePre, 3500);

  /* ---------- Helpers ---------- */
  function toast(msg, type) {
    const box = $('#toasts');
    if (!box || !msg) return;
    const t = document.createElement('div');
    t.className = 'toast' + (type === 'error' ? ' error' : '');
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 3200);
  }
  async function api(data, method = 'POST') {
    const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': CSRF } };
    let url = BASE + '/cart-action';
    if (method === 'GET') url += '?' + new URLSearchParams(data);
    else { const fd = data instanceof FormData ? data : new FormData(); if (!(data instanceof FormData)) Object.entries(data).forEach(([k, v]) => fd.append(k, v)); fd.append('csrf', CSRF); opts.body = fd; }
    const r = await fetch(url, opts);
    return r.json();
  }
  function setCount(id, n) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = n;
    el.hidden = !n;
    el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
  }

  /* ---------- Panels: drawer / search / cart / modal ---------- */
  const backdrop = $('#backdrop');
  let openPanel = null;
  function open(id) {
    const el = document.getElementById(id);
    if (!el) return;
    close();
    el.classList.add('open');
    el.setAttribute('aria-hidden', 'false');
    if (el.classList.contains('drawer')) backdrop.classList.add('show');
    body.style.overflow = 'hidden';
    openPanel = el;
    if (id === 'search') setTimeout(() => $('#q').focus(), 200);
    if (id === 'cart') loadMini();
  }
  function close() {
    $$('.drawer.open, .overlay-search.open, .modal.open, .filters.open').forEach(el => { el.classList.remove('open'); el.setAttribute('aria-hidden', 'true'); });
    backdrop && backdrop.classList.remove('show');
    body.style.overflow = '';
    openPanel = null;
  }
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); open(o.dataset.open); return; }
    if (e.target.closest('[data-close]') || e.target === backdrop || e.target.classList.contains('modal')) close();
    if (e.target.closest('[data-toggle-filters]')) {
      const f = $('#filters'); const on = !f.classList.contains('open');
      close(); if (on) { f.classList.add('open'); backdrop.classList.add('show'); body.style.overflow = 'hidden'; }
    }
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  /* ---------- Header behaviour ---------- */
  const header = $('#siteHeader');
  const toTop = $('#toTop');
  let lastY = 0;
  function onScroll() {
    const y = window.scrollY;
    header && header.classList.toggle('scrolled', y > 40);
    if (header && y > 400 && y > lastY + 4 && !openPanel) header.classList.add('hide');
    else if (header && y < lastY - 4) header.classList.remove('hide');
    lastY = y;
    toTop && toTop.classList.toggle('show', y > 700);
  }
  toTop && toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  /* ---------- Parallax ---------- */
  const parallax = $$('[data-parallax]');
  function runParallax() {
    if (reduceMotion) return;
    const vh = window.innerHeight;
    parallax.forEach(el => {
      const r = el.parentElement.getBoundingClientRect();
      if (r.bottom < 0 || r.top > vh) return;
      const speed = parseFloat(el.dataset.parallax) || 0.3;
      const room = Math.max(0, (el.offsetHeight - r.height) / 2);
      const offset = Math.max(-room, Math.min(room, (r.top + r.height / 2 - vh / 2) * -speed));
      el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
    });
  }
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) { requestAnimationFrame(() => { onScroll(); runParallax(); ticking = false; }); ticking = true; }
  }, { passive: true });
  window.addEventListener('resize', runParallax);
  onScroll(); runParallax();

  /* ---------- Reveal on scroll ---------- */
  const reveal = el => el.classList.add('in');
  if ('IntersectionObserver' in window && !reduceMotion) {
    const io = new IntersectionObserver(entries => entries.forEach(en => { if (en.isIntersecting) { reveal(en.target); io.unobserve(en.target); } }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    $$('[data-reveal]').forEach(el => io.observe(el));
  } else {
    $$('[data-reveal]').forEach(reveal);
  }

  /* ---------- Swiper carousels ---------- */
  function initSwipers() {
    if (typeof Swiper === 'undefined') return;
    $$('.hero-swiper').forEach(el => {
      const delay = parseInt(el.dataset.autoplay, 10) || 6000;
      el.style.setProperty('--autoplay', delay + 'ms');
      const slides = el.querySelectorAll('.swiper-slide').length;
      new Swiper(el, {
        effect: el.dataset.effect === 'slide' ? 'slide' : 'fade',
        fadeEffect: { crossFade: true },
        speed: 1200, loop: slides > 1,
        autoplay: slides > 1 ? { delay, disableOnInteraction: false } : false,
        pagination: { el: el.querySelector('.hero-pagination'), clickable: true },
        navigation: { nextEl: el.querySelector('.hero-next'), prevEl: el.querySelector('.hero-prev') },
      });
    });
    $$('.product-swiper').forEach(el => {
      const wrap = el.closest('.carousel');
      const opts = {
        slidesPerView: 2, spaceBetween: 14, speed: 700, grabCursor: true,
        navigation: { nextEl: wrap.querySelector('.car-next'), prevEl: wrap.querySelector('.car-prev') },
        breakpoints: { 768: { slidesPerView: 3, spaceBetween: 20 }, 1100: { slidesPerView: 4, spaceBetween: 24 } },
      };
      const prog = wrap.querySelector('.car-progress');
      if (prog) opts.pagination = { el: prog, type: 'progressbar' };
      new Swiper(el, opts);
    });
    $$('.testi-swiper').forEach(el => new Swiper(el, {
      slidesPerView: 1, spaceBetween: 24, loop: el.querySelectorAll('.swiper-slide').length > 3, speed: 800,
      autoplay: { delay: 5000, disableOnInteraction: false },
      pagination: { el: el.querySelector('.testi-pagination'), clickable: true },
      breakpoints: { 768: { slidesPerView: 2 }, 1100: { slidesPerView: 3 } },
    }));
    const thumbsEl = $('.gallery-thumbs');
    const mainEl = $('.gallery-main');
    if (mainEl) {
      const thumbs = thumbsEl ? new Swiper(thumbsEl, { direction: 'vertical', slidesPerView: 5, spaceBetween: 12, watchSlidesProgress: true }) : null;
      const gOpts = { speed: 600, pagination: { el: '.gallery-pagination', clickable: true } };
      if (thumbs) gOpts.thumbs = { swiper: thumbs };
      new Swiper(mainEl, gOpts);
    }
  }
  initSwipers();

  /* ---------- Image zoom on product page ---------- */
  $$('[data-zoom]').forEach(z => {
    const img = z.querySelector('img');
    z.addEventListener('mousemove', e => {
      if (window.innerWidth < 960) return;
      const r = z.getBoundingClientRect();
      img.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
      z.classList.add('zooming');
    });
    z.addEventListener('mouseleave', () => z.classList.remove('zooming'));
  });

  /* ---------- Tabs ---------- */
  $$('[data-tabs]').forEach(t => {
    t.addEventListener('click', e => {
      const b = e.target.closest('[data-tab]');
      if (!b) return;
      $$('[data-tab]', t).forEach(x => x.classList.toggle('active', x === b));
      $$('.tabs__panel', t).forEach(p => p.classList.toggle('active', p.id === b.dataset.tab));
    });
  });

  /* ---------- Quantity steppers ---------- */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-qty]');
    if (!b) return;
    const input = b.parentElement.querySelector('input');
    const min = parseInt(input.min, 10) || 0;
    const max = parseInt(input.max, 10) || 999;
    input.value = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + parseInt(b.dataset.qty, 10)));
  });

  /* ---------- Product options label ---------- */
  $$('.opt-group').forEach(g => g.addEventListener('change', e => {
    const v = g.querySelector('[data-opt-value]');
    if (v && e.target.checked) v.textContent = e.target.value;
  }));

  /* ---------- Mini cart ---------- */
  async function loadMini() {
    try {
      const r = await api({ action: 'mini' }, 'GET');
      $('#miniCart').innerHTML = r.html;
      setCount('cartCount', r.count);
    } catch (err) { $('#miniCart').innerHTML = '<div class="mini-empty">Could not load your bag.</div>'; }
  }
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-cart-qty]');
    if (!b) return;
    const r = await api({ action: 'update', key: b.dataset.cartQty, qty: b.dataset.v });
    if (r.html) $('#miniCart').innerHTML = r.html;
    setCount('cartCount', r.count);
  });

  /* ---------- Add to bag (product form) ---------- */
  $$('[data-add-form]').forEach(form => form.addEventListener('submit', async e => {
    e.preventDefault();
    const sizeGroup = form.querySelector('input[name=size]');
    if (sizeGroup && !form.querySelector('input[name=size]:checked')) {
      const g = sizeGroup.closest('.opt-group');
      g.classList.remove('shake'); void g.offsetWidth; g.classList.add('shake');
      toast('Please select a size.', 'error');
      return;
    }
    const fd = new FormData(form);
    if (e.submitter && e.submitter.name === 'buy_now') fd.append('buy_now', '1');
    fd.delete('csrf');
    const btn = e.submitter; if (btn) btn.disabled = true;
    try {
      const r = await api(fd);
      if (r.redirect) { location.href = r.redirect; return; }
      toast(r.message, r.ok ? '' : 'error');
      if (r.ok) { setCount('cartCount', r.count); open('cart'); }
    } finally { if (btn) btn.disabled = false; }
  }));

  /* ---------- Quick add from product cards ---------- */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-quick-add]');
    if (!b) return;
    e.preventDefault();
    const r = await api({ action: 'add', id: b.dataset.quickAdd, size: b.dataset.size || '', qty: 1 });
    toast(r.message, r.ok ? '' : 'error');
    if (r.ok) { setCount('cartCount', r.count); open('cart'); }
  });

  /* ---------- Wishlist ---------- */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-wish]');
    if (!b) return;
    e.preventDefault();
    const r = await api({ action: 'wishlist', id: b.dataset.wish });
    if (!r.ok) return toast(r.message, 'error');
    $$('[data-wish="' + b.dataset.wish + '"]').forEach(x => x.classList.toggle('active', r.added));
    setCount('wishCount', r.wish);
    toast(r.message);
  });

  /* ---------- Live search ---------- */
  const q = $('#q');
  const sug = $('#searchSuggest');
  let st;
  q && q.addEventListener('input', () => {
    clearTimeout(st);
    st = setTimeout(async () => {
      if (q.value.trim().length < 2) { sug.innerHTML = ''; return; }
      const r = await api({ action: 'search', q: q.value.trim() }, 'GET');
      sug.innerHTML = r.items.map(i => '<a href="' + i.url + '"><img src="' + i.img + '" alt=""><span>' + i.name.replace(/</g, '&lt;') + '<br><small>' + i.price + '</small></span></a>').join('') || '<p class="muted">No matches — press Enter to search all.</p>';
    }, 250);
  });

  /* ---------- AJAX forms (newsletter) ---------- */
  $$('[data-ajax-form]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(x => x.json());
    toast(r.message, r.ok ? '' : 'error');
    if (r.ok) f.reset();
  }));

  /* ---------- Checkout ---------- */
  const co = $('#checkoutForm');
  if (co) {
    const city = co.querySelector('[data-city]');
    const syncManual = () => {
      $$('.pay-option', co).forEach(opt => {
        const on = opt.querySelector('input[name=payment]').checked;
        $$('[data-txn], [data-proof]', opt).forEach(i => { i.disabled = !on; i.required = on && i.hasAttribute('data-txn'); });
      });
    };
    const refresh = async () => {
      const pay = co.querySelector('input[name=payment]:checked');
      const r = await api({ action: 'shipping', city: city.value, payment: pay ? pay.value : 'cod' }, 'GET');
      $$('[data-shipping]', co).forEach(x => x.textContent = r.shipping);
      $$('[data-total]', co).forEach(x => x.textContent = r.total);
      $$('[data-fee]', co).forEach(x => x.textContent = r.fee);
      $$('[data-fee-row]', co).forEach(x => x.classList.toggle('hidden', !r.has_fee));
    };
    city && city.addEventListener('change', refresh);
    co.addEventListener('change', e => { if (e.target.name === 'payment') { syncManual(); refresh(); } });
    syncManual();
    co.addEventListener('submit', () => { const b = co.querySelector('button[type=submit]'); setTimeout(() => { b.disabled = true; b.textContent = 'Placing order…'; }, 10); });
  }
  $$('[data-toggle]').forEach(c => c.addEventListener('change', () => { const t = $(c.dataset.toggle); t && t.classList.toggle('hidden', !c.checked); }));
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    e.preventDefault();
    navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(() => toast('Copied: ' + b.dataset.copy));
  });

  /* ---------- Initial cart count sync ---------- */
  if (location.hash === '#cart') open('cart');
})();
