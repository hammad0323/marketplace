/* Webanza Tech — interactions, parallax & animations (no dependencies; Lenis optional) */
(function () {
  'use strict';

  var doc = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Preloader ---------- */
  var preloader = $('#preloader');
  function hidePreloader() { if (preloader) preloader.classList.add('done'); }
  window.addEventListener('load', function () { setTimeout(hidePreloader, 250); });
  setTimeout(hidePreloader, 2500);

  /* ---------- Smooth scroll (Lenis, if the CDN loaded) ---------- */
  var lenis = null;
  if (!reduce && window.Lenis) {
    lenis = new window.Lenis({ duration: 1.15, smoothWheel: true });
    (function raf(t) { lenis.raf(t); requestAnimationFrame(raf); })(0);
  }
  function scrollToY(y) {
    if (lenis) lenis.scrollTo(y); else window.scrollTo({ top: y, behavior: 'smooth' });
  }
  $$('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href');
      if (id.length < 2) return;
      var t = $(id);
      if (!t) return;
      e.preventDefault();
      scrollToY(t.getBoundingClientRect().top + window.scrollY - 70);
    });
  });

  /* ---------- Header, progress, parallax ---------- */
  var header = $('#header');
  var topbar = $('#topbar');
  var progress = $('#scrollProgress');
  var toTop = $('#toTop');
  var ring = toTop ? $('circle', toTop) : null;
  var parallaxEls = reduce ? [] : $$('[data-parallax]');
  var lastY = window.scrollY;
  var ticking = false;

  function onScroll() {
    var y = window.scrollY;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    var pct = max > 0 ? y / max : 0;
    if (progress) progress.style.width = (pct * 100) + '%';
    if (ring) ring.style.strokeDashoffset = String(151 - 151 * pct);
    if (toTop) toTop.classList.toggle('show', y > 600);

    if (header) {
      var tb = topbar && topbar.offsetParent !== null ? topbar.offsetHeight : 0;
      header.style.top = Math.max(0, tb - y) + 'px';
      header.classList.toggle('scrolled', y > 40);
      var menuOpen = doc.classList.contains('menu-open');
      header.classList.toggle('hide', !menuOpen && y > 500 && y > lastY + 4);
      if (y < lastY - 4) header.classList.remove('hide');
    }

    var vh = window.innerHeight;
    for (var i = 0; i < parallaxEls.length; i++) {
      var el = parallaxEls[i];
      var speed = parseFloat(el.getAttribute('data-parallax')) || 0;
      var host = el.parentElement || el;
      var r = host.getBoundingClientRect();
      if (r.bottom < -200 || r.top > vh + 200) continue;
      var center = r.top + r.height / 2 - vh / 2;
      el.style.translate = '0 ' + (-center * speed).toFixed(1) + 'px';
    }
    lastY = y;
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { requestAnimationFrame(onScroll); ticking = true; }
  }, { passive: true });
  window.addEventListener('resize', onScroll);
  onScroll();

  if (toTop) toTop.addEventListener('click', function () { scrollToY(0); });

  /* ---------- Announcement bar ---------- */
  if (topbar) {
    try { if (sessionStorage.getItem('wt-topbar') === '0') topbar.style.display = 'none'; } catch (e) {}
    var close = $('.topbar-close', topbar);
    if (close) close.addEventListener('click', function () {
      topbar.style.display = 'none';
      try { sessionStorage.setItem('wt-topbar', '0'); } catch (e) {}
      onScroll();
    });
    onScroll();
  }

  /* ---------- Reveal on scroll ---------- */
  var revealTargets = $$('[data-reveal], [data-split], [data-reveal-self]');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealTargets.forEach(function (el) { io.observe(el); });
  } else {
    revealTargets.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- Counters ---------- */
  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduce) { el.textContent = target.toLocaleString(); return; }
    var start = null, dur = 2000;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 4);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var counters = $$('[data-count]');
  if ('IntersectionObserver' in window) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { animateCount(en.target); cio.unobserve(en.target); }
      });
    }, { threshold: 0.5 });
    counters.forEach(function (c) { cio.observe(c); });
  } else {
    counters.forEach(animateCount);
  }

  /* ---------- Hero rotating words ---------- */
  var rot = $('.rotator');
  if (rot) {
    var words = $$('span', rot);
    if (words.length > 1) {
      var idx = 0;
      setInterval(function () {
        var cur = words[idx];
        cur.classList.remove('on'); cur.classList.add('out');
        idx = (idx + 1) % words.length;
        var next = words[idx];
        next.classList.remove('out');
        void next.offsetWidth;
        next.classList.add('on');
        setTimeout(function () { cur.classList.remove('out'); }, 800);
      }, 2600);
    }
  }

  /* ---------- Hero mouse parallax ---------- */
  var hv = $('#heroVisual');
  if (hv && finePointer && !reduce) {
    var layers = $$('[data-depth]', hv);
    var hero = $('#hero');
    var mx = 0, my = 0, cx = 0, cy = 0;
    hero.addEventListener('mousemove', function (e) {
      mx = (e.clientX / window.innerWidth - 0.5) * 2;
      my = (e.clientY / window.innerHeight - 0.5) * 2;
    });
    hero.addEventListener('mouseleave', function () { mx = 0; my = 0; });
    (function loop() {
      cx += (mx - cx) * 0.06; cy += (my - cy) * 0.06;
      layers.forEach(function (l) {
        var d = parseFloat(l.getAttribute('data-depth')) || 0;
        l.style.translate = (cx * d * -28).toFixed(2) + 'px ' + (cy * d * -22).toFixed(2) + 'px';
      });
      requestAnimationFrame(loop);
    })();
  }

  /* ---------- Particles ---------- */
  var canvas = $('#particles');
  if (canvas && canvas.getContext && !reduce) {
    var ctx = canvas.getContext('2d');
    var pts = [], W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    function size() {
      W = canvas.offsetWidth; H = canvas.offsetHeight;
      canvas.width = W * dpr; canvas.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.min(70, Math.floor(W * H / 22000));
      pts = [];
      for (var i = 0; i < n; i++) pts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .3, vy: (Math.random() - .5) * .3, r: Math.random() * 1.6 + .4 });
    }
    size();
    window.addEventListener('resize', size);
    var visible = true;
    if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }).observe(canvas);
    (function draw() {
      if (visible) {
        ctx.clearRect(0, 0, W, H);
        for (var i = 0; i < pts.length; i++) {
          var p = pts[i];
          p.x += p.vx; p.y += p.vy;
          if (p.x < 0 || p.x > W) p.vx *= -1;
          if (p.y < 0 || p.y > H) p.vy *= -1;
          ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fillStyle = 'rgba(160,255,200,.6)'; ctx.fill();
          for (var j = i + 1; j < pts.length; j++) {
            var q = pts[j], dx = p.x - q.x, dy = p.y - q.y, dist = dx * dx + dy * dy;
            if (dist < 14000) {
              ctx.strokeStyle = 'rgba(62,224,137,' + (0.16 * (1 - dist / 14000)) + ')';
              ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
            }
          }
        }
      }
      requestAnimationFrame(draw);
    })();
  }

  /* ---------- Tilt + spotlight cards ---------- */
  if (finePointer && !reduce) {
    $$('.tilt').forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        var r = card.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
        card.style.setProperty('--mx', (x * 100) + '%');
        card.style.setProperty('--my', (y * 100) + '%');
        card.style.transform = 'perspective(900px) rotateX(' + ((0.5 - y) * 8).toFixed(2) + 'deg) rotateY(' + ((x - 0.5) * 8).toFixed(2) + 'deg) translateY(-10px)';
      });
      card.addEventListener('mouseleave', function () { card.style.transform = ''; });
    });

    /* magnetic buttons */
    $$('.magnetic').forEach(function (b) {
      b.addEventListener('mousemove', function (e) {
        var r = b.getBoundingClientRect();
        b.style.translate = ((e.clientX - r.left - r.width / 2) * 0.25).toFixed(1) + 'px ' + ((e.clientY - r.top - r.height / 2) * 0.35).toFixed(1) + 'px';
      });
      b.addEventListener('mouseleave', function () { b.style.translate = ''; });
    });

    /* custom cursor */
    var dot = $('#cursorDot'), cring = $('#cursorRing');
    if (dot && cring) {
      doc.classList.add('has-cursor');
      var tx = 0, ty = 0, rx = 0, ry = 0;
      window.addEventListener('mousemove', function (e) {
        tx = e.clientX; ty = e.clientY;
        if (!doc.classList.contains('cursor-on')) { rx = tx; ry = ty; doc.classList.add('cursor-on'); }
        dot.style.transform = 'translate(' + tx + 'px,' + ty + 'px)';
      });
      (function follow() {
        rx += (tx - rx) * 0.16; ry += (ty - ry) * 0.16;
        cring.style.transform = 'translate(' + rx + 'px,' + ry + 'px)';
        requestAnimationFrame(follow);
      })();
      document.addEventListener('mouseover', function (e) {
        cring.classList.toggle('hover', !!e.target.closest('a, button, .pkg-tab, input, textarea, select'));
      });
    }
  }

  /* ---------- Mobile menu ---------- */
  var burger = $('#burger');
  if (burger) {
    burger.addEventListener('click', function () {
      var open = doc.classList.toggle('menu-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      $$('.mobile-menu .mm-link').forEach(function (l, i) { l.style.transitionDelay = open ? (0.15 + i * 0.05) + 's' : '0s'; });
      if (lenis) { open ? lenis.stop() : lenis.start(); }
      document.body.style.overflow = open ? 'hidden' : '';
    });
    $$('.mobile-menu a').forEach(function (a) {
      a.addEventListener('click', function () { doc.classList.remove('menu-open'); document.body.style.overflow = ''; if (lenis) lenis.start(); });
    });
  }

  /* ---------- Package tabs ---------- */
  $$('.pkg-tabs').forEach(function (tabs) {
    var section = tabs.closest('section') || document;
    $$('.pkg-tab', tabs).forEach(function (tab) {
      tab.addEventListener('click', function () {
        var key = tab.getAttribute('data-tab');
        $$('.pkg-tab', tabs).forEach(function (t) { t.classList.toggle('active', t === tab); t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
        $$('.pkg-panel', section).forEach(function (p) {
          var on = p.getAttribute('data-panel') === key;
          p.classList.toggle('active', on);
          if (on) $$('[data-reveal]', p).forEach(function (el) { el.classList.add('in'); });
        });
        tab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      });
    });
  });
  $$('.pkg-more').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.pkg-card');
      var open = card.classList.toggle('expanded');
      b.textContent = open ? '− Show less' : b.getAttribute('data-more');
    });
  });

  /* ---------- Portfolio filter ---------- */
  $$('[data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var f = btn.getAttribute('data-filter');
      $$('[data-filter]').forEach(function (b) { b.classList.toggle('active', b === btn); });
      $$('.pf-item').forEach(function (it) {
        var show = f === '*' || it.getAttribute('data-cat') === f;
        it.classList.toggle('hide', !show);
        if (show) it.classList.add('in');
      });
      onScroll();
    });
  });

  /* ---------- Testimonials slider ---------- */
  var track = $('#testiTrack');
  if (track) {
    var move = function (dir) {
      var card = $('.testi', track);
      if (!card) return;
      var step = card.offsetWidth + 26;
      var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 10;
      if (dir > 0 && atEnd) track.scrollTo({ left: 0, behavior: 'smooth' });
      else track.scrollBy({ left: dir * step, behavior: 'smooth' });
    };
    $$('[data-slide]').forEach(function (b) {
      b.addEventListener('click', function () { move(parseInt(b.getAttribute('data-slide'), 10)); });
    });
    var auto = setInterval(function () { move(1); }, 5000);
    track.addEventListener('pointerenter', function () { clearInterval(auto); });
  }

  /* ---------- Toast (newsletter) ---------- */
  if (location.hash === '#subscribed') {
    var t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thanks for subscribing!';
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('show'); }, 600);
    setTimeout(function () { t.classList.remove('show'); }, 4600);
    try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
  }

  /* ---------- FAQ ---------- */
  $$('.faq-q').forEach(function (q) {
    q.addEventListener('click', function () {
      var item = q.closest('.faq-item');
      var open = !item.classList.contains('open');
      $$('.faq-item', item.parentElement).forEach(function (i) {
        i.classList.remove('open');
        $('.faq-q', i).setAttribute('aria-expanded', 'false');
      });
      if (open) { item.classList.add('open'); q.setAttribute('aria-expanded', 'true'); }
    });
  });
})();
