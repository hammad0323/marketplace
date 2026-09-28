/* Rabia Khan's Beauty Bar — interactions & animations (no dependencies) */
(function () {
    'use strict';
    var doc = document.documentElement;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

    /* ---- Preloader ---- */
    function hidePreloader() {
        var p = $('#preloader');
        if (p) { p.classList.add('done'); }
        var title = $('.split-text');
        if (title) { setTimeout(function () { title.classList.add('in'); }, 200); }
    }
    window.addEventListener('load', function () { setTimeout(hidePreloader, 400); });
    setTimeout(hidePreloader, 3000); // safety net if an asset hangs

    /* ---- Split hero title into animated words ---- */
    $$('.split-text').forEach(function (el) {
        var words = el.textContent.trim().split(/\s+/);
        el.innerHTML = words.map(function (w, i) {
            return '<span class="word"><span style="transition-delay:' + (i * 120) + 'ms">' + w.replace(/</g, '&lt;') + '</span></span>';
        }).join(' ');
    });

    /* ---- Mobile nav ---- */
    var toggle = $('#navToggle');
    if (toggle) {
        toggle.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
        $$('#mainNav a').forEach(function (a) {
            a.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
        });
    }

    /* ---- Scroll-driven effects: header, progress, back-to-top, parallax ---- */
    var header = $('#siteHeader'), progress = $('#scrollProgress'), toTop = $('#backToTop');
    var parallaxEls = $$('[data-parallax]');
    var ticking = false;

    function onScroll() {
        var y = window.pageYOffset, h = doc.scrollHeight - window.innerHeight;
        if (header) { header.classList.toggle('scrolled', y > 60); }
        if (progress) { progress.style.width = (h > 0 ? (y / h) * 100 : 0) + '%'; }
        if (toTop) { toTop.classList.toggle('show', y > 600); }

        if (!reduced) {
            var vh = window.innerHeight;
            parallaxEls.forEach(function (el) {
                var host = el.parentElement.getBoundingClientRect();
                if (host.bottom < -200 || host.top > vh + 200) { return; }
                var speed = parseFloat(el.getAttribute('data-parallax')) || 0;
                // offset relative to the element's host being centered in the viewport
                var offset = (host.top + host.height / 2 - vh / 2) * -speed;
                el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
            });
        }
        ticking = false;
    }
    window.addEventListener('scroll', function () {
        if (!ticking) { window.requestAnimationFrame(onScroll); ticking = true; }
    }, { passive: true });
    window.addEventListener('resize', onScroll);
    onScroll();

    if (toTop) { toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); }

    /* ---- Mouse-move depth parallax in the hero ---- */
    var hero = $('.hero');
    if (hero && !reduced && window.matchMedia('(pointer: fine)').matches) {
        var layers = $$('[data-depth]', hero);
        hero.addEventListener('mousemove', function (e) {
            var cx = e.clientX - window.innerWidth / 2, cy = e.clientY - window.innerHeight / 2;
            layers.forEach(function (l) {
                var d = parseFloat(l.getAttribute('data-depth'));
                l.style.transform = 'translate3d(' + (cx * d) + 'px,' + (cy * d) + 'px,0)';
            });
        });
        hero.addEventListener('mouseleave', function () {
            layers.forEach(function (l) { l.style.transform = ''; });
        });
    }

    /* ---- Reveal on scroll ---- */
    var revealEls = $$('.reveal, .reveal-left, .reveal-right, .reveal-zoom');
    if ('IntersectionObserver' in window && !reduced) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) { return; }
                var el = en.target, delay = parseInt(el.getAttribute('data-delay') || '0', 10);
                setTimeout(function () { el.classList.add('is-visible'); }, delay);
                io.unobserve(el);
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* ---- Animated counters ---- */
    function runCounter(el) {
        var target = parseFloat(el.getAttribute('data-target')) || 0;
        var dec = parseInt(el.getAttribute('data-decimals') || '0', 10);
        var start = null, dur = 2000;
        function step(ts) {
            if (!start) { start = ts; }
            var p = Math.min((ts - start) / dur, 1), eased = 1 - Math.pow(1 - p, 3);
            el.textContent = (target * eased).toLocaleString(undefined, { minimumFractionDigits: dec, maximumFractionDigits: dec }) + (p === 1 && !dec && target > 10 ? '+' : '');
            if (p < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
    }
    var counters = $$('.counter');
    if ('IntersectionObserver' in window) {
        var co = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { runCounter(en.target); co.unobserve(en.target); } });
        }, { threshold: 0.5 });
        counters.forEach(function (c) { co.observe(c); });
    } else { counters.forEach(runCounter); }

    /* ---- 3D tilt cards ---- */
    if (!reduced && window.matchMedia('(pointer: fine)').matches) {
        $$('.tilt').forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
                card.style.transform = 'perspective(800px) rotateY(' + (x * 10) + 'deg) rotateX(' + (-y * 10) + 'deg) translateY(-8px)';
            });
            card.addEventListener('mouseleave', function () { card.style.transform = ''; });
        });
    }

    /* ---- Tabs (price menu) ---- */
    $$('.tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            $$('.tab').forEach(function (t) { t.classList.remove('active'); });
            $$('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var panel = document.getElementById(tab.getAttribute('data-tab'));
            if (panel) { panel.classList.add('active'); }
        });
    });

    /* ---- Testimonial slider ---- */
    var slider = $('#reviewSlider');
    if (slider) {
        var slides = $$('.slide', slider), dotsWrap = $('.slider-dots', slider), idx = 0, timer;
        function go(i) {
            idx = (i + slides.length) % slides.length;
            slides.forEach(function (s, n) { s.classList.toggle('active', n === idx); });
            $$('button', dotsWrap).forEach(function (d, n) { d.classList.toggle('active', n === idx); });
        }
        slides.forEach(function (s, n) {
            var b = document.createElement('button');
            b.setAttribute('aria-label', 'Review ' + (n + 1));
            b.addEventListener('click', function () { go(n); restart(); });
            dotsWrap.appendChild(b);
        });
        function restart() { clearInterval(timer); timer = setInterval(function () { go(idx + 1); }, 5500); }
        if (slides.length) { go(0); restart(); }
        var sx = null;
        slider.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', function (e) {
            if (sx === null) { return; }
            var dx = e.changedTouches[0].clientX - sx;
            if (Math.abs(dx) > 40) { go(idx + (dx < 0 ? 1 : -1)); restart(); }
            sx = null;
        });
    }

    /* ---- Gallery lightbox ---- */
    var lb = $('#lightbox');
    if (lb) {
        var items = $$('.gallery-item'), cur = 0, lbImg = $('img', lb), lbCap = $('figcaption', lb);
        function show(i) {
            cur = (i + items.length) % items.length;
            lbImg.src = items[cur].getAttribute('href');
            lbImg.alt = lbCap.textContent = items[cur].getAttribute('data-caption') || '';
        }
        items.forEach(function (it, i) {
            it.addEventListener('click', function (e) { e.preventDefault(); show(i); lb.classList.add('open'); lb.setAttribute('aria-hidden', 'false'); });
        });
        function close() { lb.classList.remove('open'); lb.setAttribute('aria-hidden', 'true'); }
        $('.lb-close', lb).addEventListener('click', close);
        $('.lb-prev', lb).addEventListener('click', function () { show(cur - 1); });
        $('.lb-next', lb).addEventListener('click', function () { show(cur + 1); });
        lb.addEventListener('click', function (e) { if (e.target === lb) { close(); } });
        document.addEventListener('keydown', function (e) {
            if (!lb.classList.contains('open')) { return; }
            if (e.key === 'Escape') { close(); }
            if (e.key === 'ArrowLeft') { show(cur - 1); }
            if (e.key === 'ArrowRight') { show(cur + 1); }
        });
    }

    /* ---- Booking page: live time-slot loading ---- */
    var dateInput = $('#bookingDate'), slotBox = $('#slotBox');
    if (dateInput && slotBox) {
        var preselect = slotBox.getAttribute('data-selected') || '';
        function loadSlots() {
            if (!dateInput.value) { return; }
            slotBox.innerHTML = '<p class="slots-msg"><i class="fa-solid fa-spinner fa-spin"></i> Checking availability…</p>';
            fetch('slots.php?date=' + encodeURIComponent(dateInput.value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.slots || !data.slots.length) {
                        slotBox.innerHTML = '<p class="slots-msg">' + (data.message || 'No slots available on this date — please choose another day.') + '</p>';
                        return;
                    }
                    slotBox.innerHTML = data.slots.map(function (s) {
                        return '<label class="slot"><input type="radio" name="time" value="' + s.value + '"' + (s.value === preselect ? ' checked' : '') + ' required><span>' + s.label + '</span></label>';
                    }).join('');
                })
                .catch(function () { slotBox.innerHTML = '<p class="slots-msg">Could not load time slots. Please refresh and try again.</p>'; });
        }
        dateInput.addEventListener('change', function () { preselect = ''; loadSlots(); });
        loadSlots();
    }
})();
