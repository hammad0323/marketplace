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

    // ---- Theme toggle --------------------------------------------------------
    var root = document.documentElement;
    var savedTheme = localStorage.getItem('mc-theme');
    if (savedTheme) {
        root.setAttribute('data-theme', savedTheme);
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        updateThemeIcon(btn);
        btn.addEventListener('click', function () {
            var current = root.getAttribute('data-theme') === 'dark' ? 'dark' :
                (root.getAttribute('data-theme') === 'light' ? 'light' :
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
            var next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('mc-theme', next);
            document.querySelectorAll('[data-theme-toggle]').forEach(updateThemeIcon);
        });
    });
    function updateThemeIcon(btn) {
        var isDark = root.getAttribute('data-theme') === 'dark' ||
            (root.getAttribute('data-theme') !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var icon = btn.querySelector('i');
        if (icon) { icon.className = isDark ? 'ri-sun-line' : 'ri-moon-line'; }
    }

    // ---- Navbar scroll state --------------------------------------------------
    var navbar = document.querySelector('.navbar');
    if (navbar) {
        var onScroll = function () {
            navbar.classList.toggle('scrolled', window.scrollY > 12);
        };
        onScroll();
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
    document.querySelectorAll('[data-dropdown-trigger]').forEach(function (trigger) {
        var menu = document.getElementById(trigger.getAttribute('data-dropdown-trigger'));
        if (!menu) return;
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
                if (m !== menu) m.classList.remove('open');
            });
            menu.classList.toggle('open');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
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
})();
