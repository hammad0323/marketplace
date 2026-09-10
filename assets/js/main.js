(function () {
    'use strict';

    // ---- Page loader ---------------------------------------------------------
    // This script tag sits at the end of <body>, so the DOM is already parsed
    // by the time it runs — no need to wait on window 'load' (which depends on
    // every subresource, including third-party fonts, finishing).
    (function () {
        var loader = document.getElementById('page-loader');
        if (loader) {
            setTimeout(function () { loader.classList.add('hidden'); }, 250);
        }
    })();

    // ---- Navbar scroll state --------------------------------------------------
    // The initial check runs on the next frame (not synchronously here) so
    // reading window.scrollY doesn't force a layout flush in the middle of
    // this script's own initial execution, right after other DOM writes above.
    var navbar = document.querySelector('.navbar');
    if (navbar) {
        var onScroll = function () {
            navbar.classList.toggle('scrolled', window.scrollY > 12);
        };
        requestAnimationFrame(onScroll);
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // ---- Mobile nav toggle -----------------------------------------------------
    document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelector('.nav-links').classList.toggle('mobile-open');
        });
    });

    // ---- Sidebar toggle (dashboards) -------------------------------------------
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelector('.dash-sidebar').classList.toggle('open');
        });
    });

    // ---- Dropdown menus ---------------------------------------------------------
    // aria-haspopup/aria-expanded are set here in JS (not the markup) so every
    // trigger site-wide — nav, notifications, user menu — gets correct,
    // consistent disclosure-widget semantics without touching each template.
    document.querySelectorAll('[data-dropdown-trigger]').forEach(function (trigger) {
        var menu = document.getElementById(trigger.getAttribute('data-dropdown-trigger'));
        if (!menu) return;
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
                if (m !== menu) {
                    m.classList.remove('open');
                    var t = document.querySelector('[data-dropdown-trigger="' + m.id + '"]');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });
            var willOpen = !menu.classList.contains('open');
            menu.classList.toggle('open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
            m.classList.remove('open');
            var t = document.querySelector('[data-dropdown-trigger="' + m.id + '"]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    });

    // ---- Tab groups (role="tablist") --------------------------------------------
    // One generic, ARIA-complete implementation shared by every tabbed panel
    // site-wide (doctor/pharmacy profile & storefront, admin settings, public
    // doctor profile) — driven purely by role/aria-controls markup, so the
    // panel container's own class name doesn't matter.
    document.querySelectorAll('[role="tablist"]').forEach(function (tablist) {
        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
        function activate(tab) {
            tabs.forEach(function (t) {
                var selected = t === tab;
                t.classList.toggle('active', selected);
                t.setAttribute('aria-selected', selected ? 'true' : 'false');
                t.setAttribute('tabindex', selected ? '0' : '-1');
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) panel.style.display = selected ? '' : 'none';
            });
        }
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { activate(tab); });
            tab.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                e.preventDefault();
                var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                next.focus();
                activate(next);
            });
        });
    });

    // ---- Scroll reveal animations ------------------------------------------------
    var revealEls = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && revealEls.length) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { observer.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('in-view'); });
    }

    // ---- Animated counters ---------------------------------------------------------
    var counters = document.querySelectorAll('[data-counter]');
    if ('IntersectionObserver' in window && counters.length) {
        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { counterObserver.observe(el); });
    }
    function animateCounter(el) {
        var target = parseFloat(el.getAttribute('data-counter'));
        var duration = 1400;
        var start = performance.now();
        var suffix = el.getAttribute('data-suffix') || '';
        function step(now) {
            var progress = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var value = target * eased;
            el.textContent = (target % 1 === 0 ? Math.round(value) : value.toFixed(1)) + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // ---- Button ripple effect -----------------------------------------------------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn');
        if (!btn) return;
        var rect = btn.getBoundingClientRect();
        var ripple = document.createElement('span');
        var size = Math.max(rect.width, rect.height);
        ripple.className = 'ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        btn.appendChild(ripple);
        setTimeout(function () { ripple.remove(); }, 650);
    });

    // ---- Card tilt effect ---------------------------------------------------------
    document.querySelectorAll('[data-tilt]').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
            var rect = card.getBoundingClientRect();
            var x = (e.clientX - rect.left) / rect.width - 0.5;
            var y = (e.clientY - rect.top) / rect.height - 0.5;
            card.style.transform = 'perspective(800px) rotateY(' + (x * 8) + 'deg) rotateX(' + (y * -8) + 'deg) translateY(-4px)';
        });
        card.addEventListener('mouseleave', function () {
            card.style.transform = '';
        });
    });

    // ---- Password show/hide toggle -------------------------------------------------
    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-pass'));
            if (!input) return;
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.querySelector('i').className = isPass ? 'ri-eye-off-line' : 'ri-eye-line';
        });
    });

    // ---- Password strength meter ----------------------------------------------------
    document.querySelectorAll('[data-pw-strength]').forEach(function (input) {
        var bar = document.querySelector(input.getAttribute('data-pw-strength'));
        if (!bar) return;
        input.addEventListener('input', function () {
            var val = input.value;
            var score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            var pct = (score / 4) * 100;
            var colors = ['#EF4444', '#EF4444', '#F59E0B', '#F59E0B', '#22C55E'];
            bar.style.width = pct + '%';
            bar.style.background = colors[score];
        });
    });

    // ---- Generic horizontal carousel nav (e.g. homepage city carousel) --------
    document.querySelectorAll('[data-carousel]').forEach(function (wrap) {
        var track = wrap.querySelector('[data-carousel-track]');
        if (!track) return;
        var scrollAmount = function () { return Math.min(track.clientWidth * 0.8, 480); };
        var prev = wrap.querySelector('[data-carousel-prev]');
        var next = wrap.querySelector('[data-carousel-next]');
        if (prev) prev.addEventListener('click', function () { track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' }); });
        if (next) next.addEventListener('click', function () { track.scrollBy({ left: scrollAmount(), behavior: 'smooth' }); });
    });
})();
