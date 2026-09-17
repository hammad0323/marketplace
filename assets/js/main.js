(function () {
  'use strict';

  // Mobile nav toggle
  var toggle = document.querySelector('.nav-toggle');
  var links = document.querySelector('.nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      links.classList.toggle('open');
      var expanded = links.classList.contains('open');
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      toggle.innerHTML = expanded ? '&#10005;' : '&#9776;';
    });
    links.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { links.classList.remove('open'); });
    });
  }

  // Scroll-reveal via IntersectionObserver (progressive enhancement —
  // .reveal is only ever hidden by CSS once html.js is set, see header.php)
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('reveal-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('reveal-visible'); });
  }

  // Parallax hero backgrounds
  var heroLayers = document.querySelectorAll('.parallax-hero-bg');
  if (heroLayers.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var ticking = false;
    function updateParallax() {
      heroLayers.forEach(function (layer) {
        var rect = layer.parentElement.getBoundingClientRect();
        var offset = rect.top * -0.28;
        layer.style.transform = 'translate3d(0,' + offset + 'px,0) scale(1.08)';
      });
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { window.requestAnimationFrame(updateParallax); ticking = true; }
    }, { passive: true });
    updateParallax();
  }

  // Simple lightbox for gallery grids
  var galleryItems = document.querySelectorAll('[data-lightbox]');
  if (galleryItems.length) {
    var overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';
    overlay.innerHTML = '<img alt=""><button type="button" class="lightbox-close" aria-label="Close">&#10005;</button>';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(10,6,9,.92);display:none;align-items:center;justify-content:center;z-index:999;padding:24px;';
    var img = overlay.querySelector('img');
    img.style.cssText = 'max-width:92vw;max-height:88vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);';
    var closeBtn = overlay.querySelector('.lightbox-close');
    closeBtn.style.cssText = 'position:absolute;top:24px;right:28px;background:rgba(255,255,255,.12);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.1rem;cursor:pointer;';
    document.body.appendChild(overlay);
    function close() { overlay.style.display = 'none'; }
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    galleryItems.forEach(function (item) {
      item.addEventListener('click', function () {
        img.src = item.getAttribute('data-lightbox');
        overlay.style.display = 'flex';
      });
    });
  }
})();
