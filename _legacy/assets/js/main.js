/**
 * Small vanilla-JS enhancement layer — no dependencies, no build step.
 * Two things: parallax hero backgrounds, and scroll-reveal animations.
 * Both degrade gracefully (plain static page) if JS is disabled.
 */
(function () {
    'use strict';

    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- Parallax hero backgrounds -------------------------------------
    // Each .parallax-hero has a .parallax-hero-bg layer that moves at a
    // slower rate than the page scroll, giving a depth effect. Uses
    // transform (not background-position) so it stays GPU-accelerated
    // and works identically on mobile, unlike background-attachment:fixed.
    var parallaxLayers = document.querySelectorAll('.parallax-hero-bg');

    function updateParallax() {
        if (prefersReducedMotion) {
            return;
        }
        for (var i = 0; i < parallaxLayers.length; i++) {
            var layer = parallaxLayers[i];
            var hero = layer.closest('.parallax-hero');
            var rect = hero.getBoundingClientRect();
            // Only compute while the hero is anywhere near the viewport.
            if (rect.bottom < -200 || rect.top > window.innerHeight + 200) {
                continue;
            }
            var offset = rect.top * 0.35;
            layer.style.transform = 'translate3d(0, ' + offset + 'px, 0) scale(1.15)';
        }
    }

    var ticking = false;
    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                updateParallax();
                ticking = false;
            });
            ticking = true;
        }
    }

    if (parallaxLayers.length) {
        updateParallax();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
    }

    // ---- Scroll-reveal ---------------------------------------------------
    // Any element with class "reveal" fades/slides into place the first
    // time it enters the viewport. Falls back to fully visible if
    // IntersectionObserver isn't available.
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
