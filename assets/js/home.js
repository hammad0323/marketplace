/**
 * Homepage-only motion (loaded from index.php). Everything degrades to a
 * static, fully readable page: no JS = no movement, and prefers-reduced-motion
 * skips the parallax/mouse effects entirely (home.css also freezes the CSS
 * animations in that case).
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- Scroll parallax: [data-parallax="speed"] drifts relative to how far
    // its centre is from the viewport centre. Writes a CSS var so the element
    // keeps any transform it already has in CSS.
    var parallaxEls = reduceMotion ? [] : Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));

    // ---- "How it works": a progress line fills and each step lights up as the
    // section scrolls past the reading line (60% down the viewport).
    var stepsSec = document.querySelector('[data-steps]');
    var steps = stepsSec ? Array.prototype.slice.call(stepsSec.querySelectorAll('.h-step')) : [];
    var stepsList = stepsSec ? stepsSec.querySelector('.h-steps-list') : null;

    var ticking = false;
    function update() {
        ticking = false;
        var vh = window.innerHeight;
        parallaxEls.forEach(function (el) {
            var rect = el.getBoundingClientRect();
            if (rect.bottom < -200 || rect.top > vh + 200) return;
            var speed = parseFloat(el.getAttribute('data-parallax')) || 0;
            var offset = (rect.top + rect.height / 2 - vh / 2) * speed;
            el.style.setProperty('--py', offset.toFixed(1) + 'px');
        });
        if (stepsList) {
            var readLine = vh * 0.6;
            var r = stepsList.getBoundingClientRect();
            var progress = Math.min(Math.max((readLine - r.top) / r.height, 0), 1);
            stepsSec.style.setProperty('--progress', progress.toFixed(3));
            steps.forEach(function (step) {
                step.classList.toggle('is-active', reduceMotion || step.getBoundingClientRect().top < readLine);
            });
        }
    }
    function requestUpdate() {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
    update();

    // ---- Hero depth: doctor tiles drift against the cursor, each by its own
    // data-depth (px), giving the collage a layered 3D feel. Desktop pointers only.
    var stage = document.querySelector('[data-hero-stage]');
    if (stage && !reduceMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        var tiles = Array.prototype.slice.call(stage.querySelectorAll('[data-depth]'));
        var hero = stage.closest('.h-hero') || stage;
        hero.addEventListener('mousemove', function (e) {
            var rect = hero.getBoundingClientRect();
            var x = (e.clientX - rect.left) / rect.width - 0.5;
            var y = (e.clientY - rect.top) / rect.height - 0.5;
            tiles.forEach(function (tile) {
                var depth = parseFloat(tile.getAttribute('data-depth')) || 0;
                // `translate` (not `transform`) so it composes with the entrance
                // animation, which holds its own final transform.
                tile.style.translate = (-x * depth).toFixed(1) + 'px ' + (-y * depth).toFixed(1) + 'px';
            });
        });
        hero.addEventListener('mouseleave', function () {
            tiles.forEach(function (tile) { tile.style.translate = ''; });
        });
    }

    // ---- Hero booking preview: the selected time slot steps along every few
    // seconds so the card reads as a live booking UI, not a static picture.
    var slotBox = document.querySelector('[data-slots]');
    if (slotBox && !reduceMotion) {
        var slotEls = Array.prototype.slice.call(slotBox.children);
        var slotIdx = Math.max(0, slotEls.findIndex(function (el) { return el.classList.contains('is-selected'); }));
        var slotPaused = false;
        slotBox.addEventListener('mouseenter', function () { slotPaused = true; });
        slotBox.addEventListener('mouseleave', function () { slotPaused = false; });
        if (slotEls.length > 1) {
            setInterval(function () {
                if (slotPaused || document.hidden) return;
                slotEls[slotIdx].classList.remove('is-selected');
                slotIdx = (slotIdx + 1) % slotEls.length;
                slotEls[slotIdx].classList.add('is-selected');
            }, 2400);
        }
    }

    if (window.setupNearMeButton) window.setupNearMeButton('#hero-near-me', '/doctors');

    // ---- Testimonials: crossfade, auto-advancing every 6s, paused on hover/focus.
    var quoteSec = document.querySelector('[data-quotes]');
    if (quoteSec) {
        var quotes = Array.prototype.slice.call(quoteSec.querySelectorAll('[data-quote]'));
        var dots = Array.prototype.slice.call(quoteSec.querySelectorAll('[data-quote-dot]'));
        var current = 0;
        var timer = null;
        var show = function (i) {
            current = (i + quotes.length) % quotes.length;
            quotes.forEach(function (q, idx) { q.classList.toggle('is-active', idx === current); });
            dots.forEach(function (d, idx) { d.classList.toggle('is-active', idx === current); });
        };
        var start = function () {
            if (reduceMotion || quotes.length < 2) return;
            stop();
            timer = setInterval(function () { show(current + 1); }, 6000);
        };
        var stop = function () { if (timer) clearInterval(timer); timer = null; };
        dots.forEach(function (d) {
            d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-quote-dot'), 10)); start(); });
        });
        quoteSec.addEventListener('mouseenter', stop);
        quoteSec.addEventListener('mouseleave', start);
        quoteSec.addEventListener('focusin', stop);
        quoteSec.addEventListener('focusout', start);
        start();
    }
})();
