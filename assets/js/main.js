/**
 * Small vanilla-JS enhancement layer — no dependencies, no build step.
 * Parallax depth layers, scroll-reveal, tilt-on-hover cards, magnetic
 * buttons, animated stat counters, a scroll progress bar, and a
 * cursor glow. Every effect degrades to a plain static page if JS is
 * disabled, and everything motion-related is skipped for
 * prefers-reduced-motion.
 */
(function () {
    'use strict';

    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isFinePointer = window.matchMedia('(pointer: fine)').matches;

    // ---- Multi-layer parallax --------------------------------------------
    // Each .parallax-hero can contain a .parallax-hero-bg (the base
    // backdrop, speed .35) plus any number of .parallax-layer elements
    // (decorative floating shapes) with their own data-speed, so the
    // hero reads as several depths moving at different rates rather
    // than one flat image sliding underneath. Transform-only, so it
    // stays GPU-accelerated and works identically on mobile.
    var parallaxHeroes = document.querySelectorAll('.parallax-hero');

    function updateParallax() {
        if (prefersReducedMotion) {
            return;
        }
        for (var i = 0; i < parallaxHeroes.length; i++) {
            var hero = parallaxHeroes[i];
            var rect = hero.getBoundingClientRect();
            if (rect.bottom < -200 || rect.top > window.innerHeight + 200) {
                continue;
            }

            var bg = hero.querySelector('.parallax-hero-bg');
            if (bg) {
                var bgOffset = rect.top * 0.35;
                bg.style.transform = 'translate3d(0, ' + bgOffset + 'px, 0) scale(1.15)';
            }

            var layers = hero.querySelectorAll('.parallax-layer');
            for (var j = 0; j < layers.length; j++) {
                var layer = layers[j];
                var speed = parseFloat(layer.getAttribute('data-speed')) || 0.2;
                var offset = rect.top * speed;
                layer.style.transform = 'translate3d(0, ' + offset + 'px, 0)';
            }
        }
    }

    var ticking = false;
    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                updateParallax();
                updateScrollProgress();
                ticking = false;
            });
            ticking = true;
        }
    }

    // ---- Scroll progress bar ----------------------------------------------
    // A thin gradient bar under the nav (see .scroll-progress in
    // global.css) tracking how far down the current page the reader is.
    var progressBar = document.querySelector('.scroll-progress');
    function updateScrollProgress() {
        if (!progressBar) {
            return;
        }
        var scrollable = document.documentElement.scrollHeight - window.innerHeight;
        var pct = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 0;
        progressBar.style.width = pct + '%';
    }

    if (parallaxHeroes.length || progressBar) {
        updateParallax();
        updateScrollProgress();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
    }

    // ---- Scroll-reveal ---------------------------------------------------
    // Any element with class "reveal" fades/slides into place the first
    // time it enters the viewport. Add reveal-scale / reveal-blur /
    // reveal-left / reveal-right alongside "reveal" for a different
    // entrance (see global.css) — the observer logic is the same either
    // way, only the CSS transition differs. Falls back to fully visible
    // if IntersectionObserver isn't available.
    var revealEls = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window && !prefersReducedMotion && revealEls.length) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach(function (el) {
            observer.observe(el);
        });
    } else {
        revealEls.forEach(function (el) {
            el.classList.add('reveal-visible');
        });
    }

    // ---- Animated stat counters -------------------------------------------
    // Elements like <strong class="stat-counter" data-target="1284">0</strong>
    // count up from 0 the first time they scroll into view.
    var counters = document.querySelectorAll('.stat-counter');
    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        if (prefersReducedMotion || target === 0) {
            el.textContent = target.toLocaleString();
            return;
        }
        var duration = 1100;
        var start = null;
        function step(timestamp) {
            if (start === null) {
                start = timestamp;
            }
            var progress = Math.min((timestamp - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            el.textContent = Math.floor(eased * target).toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString();
            }
        }
        window.requestAnimationFrame(step);
    }
    if ('IntersectionObserver' in window && counters.length) {
        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.6 });
        counters.forEach(function (el) { counterObserver.observe(el); });
    } else {
        counters.forEach(function (el) {
            el.textContent = (parseInt(el.getAttribute('data-target'), 10) || 0).toLocaleString();
        });
    }

    // ---- Tilt-on-hover cards ------------------------------------------------
    // .product-card / .vendor-card tilt in 3D toward the cursor while
    // hovered — a light-touch effect, capped to a small angle so it
    // reads as "responsive" rather than gimmicky. Skipped on touch
    // devices (no meaningful hover position) and reduced-motion.
    if (isFinePointer && !prefersReducedMotion) {
        var tiltCards = document.querySelectorAll('.product-card, .vendor-card');
        tiltCards.forEach(function (card) {
            var raf = null;
            card.addEventListener('mousemove', function (e) {
                if (raf) {
                    return;
                }
                raf = window.requestAnimationFrame(function () {
                    var rect = card.getBoundingClientRect();
                    var px = (e.clientX - rect.left) / rect.width - 0.5;
                    var py = (e.clientY - rect.top) / rect.height - 0.5;
                    var maxTilt = 7;
                    card.style.transform =
                        'perspective(900px) rotateX(' + (-py * maxTilt) + 'deg) rotateY(' + (px * maxTilt) + 'deg) translateY(-6px) scale(1.015)';
                    raf = null;
                });
            });
            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
            });
        });
    }

    // ---- Magnetic buttons ---------------------------------------------------
    // .btn-magnetic elements drift a few px toward the cursor within a
    // padded hit area, snapping back on leave — used sparingly, on hero
    // CTAs only.
    if (isFinePointer && !prefersReducedMotion) {
        var magneticEls = document.querySelectorAll('.btn-magnetic');
        magneticEls.forEach(function (el) {
            var strength = 18;
            el.addEventListener('mousemove', function (e) {
                var rect = el.getBoundingClientRect();
                var px = (e.clientX - rect.left) / rect.width - 0.5;
                var py = (e.clientY - rect.top) / rect.height - 0.5;
                el.style.transform = 'translate(' + (px * strength) + 'px, ' + (py * strength) + 'px)';
            });
            el.addEventListener('mouseleave', function () {
                el.style.transform = '';
            });
        });
    }

    // ---- Cursor glow ---------------------------------------------------------
    // A soft, blurred glow that trails the cursor over hero sections
    // only (see .cursor-glow) — a subtle "alive" touch, not a full-page
    // gimmick. Created lazily on first pointer move so it never affects
    // initial paint, and only on fine-pointer, motion-OK devices.
    if (isFinePointer && !prefersReducedMotion) {
        var glow = null;
        var glowRaf = null;
        var lastX = 0;
        var lastY = 0;

        function ensureGlow() {
            if (glow) {
                return glow;
            }
            glow = document.createElement('div');
            glow.className = 'cursor-glow';
            document.body.appendChild(glow);
            return glow;
        }

        document.addEventListener('mousemove', function (e) {
            var overHero = e.target.closest && e.target.closest('.parallax-hero');
            if (!overHero) {
                if (glow) {
                    glow.style.opacity = '0';
                }
                return;
            }
            lastX = e.clientX;
            lastY = e.clientY;
            var el = ensureGlow();
            el.style.opacity = '1';
            if (!glowRaf) {
                glowRaf = window.requestAnimationFrame(function () {
                    el.style.transform = 'translate3d(' + lastX + 'px, ' + lastY + 'px, 0)';
                    glowRaf = null;
                });
            }
        }, { passive: true });
    }

    // ---- Sticky nav shadow on scroll -------------------------------------
    var nav = document.querySelector('.site-nav');
    if (nav) {
        var applyNavShadow = function () {
            nav.classList.toggle('site-nav-scrolled', window.scrollY > 8);
        };
        applyNavShadow();
        window.addEventListener('scroll', applyNavShadow, { passive: true });
    }
})();
