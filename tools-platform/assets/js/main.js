/**
 * main.js — theme toggle, scroll-reveal, lightweight parallax, and the
 * homepage/global search overlay. No framework; progressive enhancement
 * only (see theme.css: .reveal is only ever hidden once html.js is set,
 * which this file does at the very top — before first paint, an inline
 * copy of this line also lives in header.php as a fallback).
 */
document.documentElement.classList.add('js');

(function themeToggle() {
  const root = document.documentElement;
  const stored = localStorage.getItem('tp_theme');
  const applied = stored || root.getAttribute('data-theme-default') || 'system';

  function applyTheme(mode) {
    if (mode === 'system') {
      root.removeAttribute('data-theme');
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.classList.toggle('system-dark', isDark);
    } else {
      root.setAttribute('data-theme', mode);
      root.classList.remove('system-dark');
    }
    document.querySelectorAll('[data-theme-icon]').forEach((el) => {
      el.textContent = mode === 'dark' ? '🌙' : mode === 'light' ? '☀️' : '🖥️';
    });
  }

  applyTheme(applied);

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    const order = ['light', 'dark', 'system'];
    const current = localStorage.getItem('tp_theme') || 'system';
    const next = order[(order.indexOf(current) + 1) % order.length];
    localStorage.setItem('tp_theme', next);
    applyTheme(next);
  });
})();

(function currencySelector() {
  const menu = document.querySelector('[data-currency-menu]');
  const label = document.querySelector('[data-currency-label]');
  if (!menu || typeof TP_CURRENCIES === 'undefined') return;

  menu.innerHTML = Object.entries(TP_CURRENCIES).map(([code, c]) => `
    <li><button type="button" class="dropdown-item d-flex justify-content-between gap-3" data-currency-option="${code}">
      <span>${c.name}</span><span class="text-muted">${c.symbol} ${code}</span>
    </button></li>
  `).join('');

  function updateLabel(code) {
    if (label) label.textContent = `${TP_CURRENCIES[code].symbol} ${code}`;
  }
  updateLabel(tpGetCurrency());

  document.addEventListener('click', (e) => {
    const opt = e.target.closest('[data-currency-option]');
    if (!opt) return;
    tpSetCurrency(opt.dataset.currencyOption);
    updateLabel(opt.dataset.currencyOption);
  });
})();

(function scrollReveal() {
  const items = document.querySelectorAll('.reveal');
  if (!items.length) return;

  if (!('IntersectionObserver' in window)) {
    items.forEach((el) => el.classList.add('reveal-visible'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('reveal-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );
  items.forEach((el) => observer.observe(el));
})();

(function parallax() {
  const layers = document.querySelectorAll('.parallax-hero-bg');
  if (!layers.length) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  let ticking = false;
  function update() {
    const y = window.scrollY;
    layers.forEach((layer) => {
      layer.style.transform = `translate3d(0, ${y * 0.15}px, 0)`;
    });
    ticking = false;
  }
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(update);
      ticking = true;
    }
  }, { passive: true });
})();

(function animatedCounters() {
  const counters = document.querySelectorAll('[data-counter]');
  if (!counters.length || !('IntersectionObserver' in window)) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.counter, 10) || 0;
      const duration = 1200;
      const start = performance.now();
      function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        el.textContent = Math.floor(progress * target).toLocaleString();
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = target.toLocaleString();
      }
      requestAnimationFrame(tick);
      observer.unobserve(el);
    });
  });
  counters.forEach((el) => observer.observe(el));
})();

/* ---------------- Global search overlay (AJAX) ---------------- */
(function globalSearch() {
  const overlay = document.getElementById('tpSearchOverlay');
  if (!overlay) return;
  const input = overlay.querySelector('[data-search-input]');
  const results = overlay.querySelector('[data-search-results]');
  const baseUrl = document.body.dataset.baseUrl || '';
  let debounceTimer = null;

  function openOverlay() {
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
    setTimeout(() => input && input.focus(), 50);
  }
  function closeOverlay() {
    overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-search-open]')) { openOverlay(); }
    if (e.target.closest('[data-search-close]') || e.target === overlay) { closeOverlay(); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeOverlay();
    if ((e.ctrlKey || e.metaKey) && e.key === '/') { e.preventDefault(); openOverlay(); }
  });

  function renderResults(items) {
    if (!items.length) {
      results.innerHTML = '<p class="text-muted px-2">No tools matched your search.</p>';
      return;
    }
    results.innerHTML = items.map((t) => `
      <a href="${baseUrl}/${t.slug}" class="search-result-item">
        <span class="tp-icon"><i class="bi ${t.icon || 'bi-calculator'}"></i></span>
        <span>
          <strong>${t.name}</strong>
          <small class="d-block text-muted">${t.short_description || ''}</small>
        </span>
      </a>
    `).join('');
  }

  if (input) {
    input.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      const q = input.value.trim();
      if (q.length < 2) { results.innerHTML = ''; return; }
      debounceTimer = setTimeout(() => {
        fetch(`${baseUrl}/api/v1/search.php?q=${encodeURIComponent(q)}`)
          .then((r) => r.json())
          .then((data) => renderResults(data.results || []))
          .catch(() => { results.innerHTML = '<p class="text-danger px-2">Search failed. Please try again.</p>'; });
      }, 250);
    });
  }
})();
