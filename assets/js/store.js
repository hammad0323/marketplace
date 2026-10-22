/* =====================================================================
   Urban Collections — storefront interactions (jQuery)
   ===================================================================== */
(function ($) {
    'use strict';

    var UC = window.UC || {};
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var animOn = UC.animations && !reduceMotion;
    var $body = $(document.body);

    $.ajaxSetup({ headers: { 'X-CSRF-Token': UC.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

    function endpoint(path) { return UC.base + path; }

    // ------------------------------------------------------------ toasts
    var Toast = window.Swal ? Swal.mixin({
        toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3200, timerProgressBar: true,
        showClass: { popup: 'animate__fadeInUp' }
    }) : null;
    function toast(message, icon, html) {
        if (Toast) { Toast.fire({ icon: icon || 'success', title: message, html: html || undefined }); }
        else { window.alert(message); }
    }
    function errorMessage(xhr, fallback) {
        return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback || 'Something went wrong. Please try again.';
    }

    // ------------------------------------------------------------ broken images → branded placeholder
    document.addEventListener('error', function (e) {
        var t = e.target;
        if (t && t.tagName === 'IMG' && !t.dataset.fallback) {
            t.dataset.fallback = '1';
            if (t.parentNode && t.parentNode.tagName === 'PICTURE') {
                $(t.parentNode).find('source').remove();
            }
            t.src = UC.placeholder;
        }
    }, true);

    // ------------------------------------------------------------ header
    var $header = $('#ucHeader');
    var $announce = $('.uc-announce');
    var lastY = 0;
    function setOffsets() {
        var ah = $announce.length ? $announce.outerHeight() : 0;
        document.documentElement.style.setProperty('--uc-announce-h', ah + 'px');
        document.documentElement.style.setProperty('--uc-sticky-offset', ($body.hasClass('header-sticky') ? $header.outerHeight() : 0) + 'px');
    }
    function onScroll() {
        var y = window.scrollY;
        $body.toggleClass('is-scrolled', y > 40);
        if ($body.hasClass('header-sticky') && !$body.hasClass('no-scroll')) {
            $body.toggleClass('header-hidden', y > 400 && y > lastY + 4 && !$header.is(':hover'));
            if (y < lastY - 4 || y < 400) { $body.removeClass('header-hidden'); }
        }
        lastY = y;
    }
    setOffsets();
    onScroll();
    $(window).on('resize', setOffsets).on('scroll', function () { window.requestAnimationFrame(onScroll); });

    // announcement rotator
    $('[data-rotator]').each(function () {
        var $items = $(this).children();
        if ($items.length < 2) { return; }
        var i = 0;
        setInterval(function () {
            var $cur = $items.eq(i);
            i = (i + 1) % $items.length;
            $cur.removeClass('is-active').addClass('is-leaving');
            setTimeout(function () { $cur.removeClass('is-leaving'); }, 650);
            $items.eq(i).addClass('is-active');
        }, 4500);
    });

    // ------------------------------------------------------------ drawers
    var $backdrop = $('[data-backdrop]');
    function openDrawer(id) {
        var $d = $('#' + id);
        if (!$d.length) { return; }
        closeDrawers();
        $d.addClass('is-open').attr('aria-hidden', 'false');
        $backdrop.addClass('is-open');
        $body.addClass('no-scroll');
        setTimeout(function () { $d.find('button, a, input').filter(':visible').first().trigger('focus'); }, 350);
    }
    function closeDrawers() {
        $('.uc-drawer.is-open').removeClass('is-open').attr('aria-hidden', 'true');
        $('#ucSearch').removeClass('is-open').attr('aria-hidden', 'true');
        $backdrop.removeClass('is-open');
        $body.removeClass('no-scroll');
    }
    $(document).on('click', '[data-drawer-open]', function (e) { e.preventDefault(); openDrawer($(this).data('drawer-open')); });
    $(document).on('click', '[data-drawer-close], [data-backdrop]', closeDrawers);
    $(document).on('keydown', function (e) { if (e.key === 'Escape') { closeDrawers(); closeLightbox(); } });

    // accordions (mobile nav, customisation panel)
    $(document).on('click', '[data-accordion]', function () {
        var open = $(this).attr('aria-expanded') === 'true';
        $(this).attr('aria-expanded', open ? 'false' : 'true');
    });
    $(document).on('click', '[data-footer-toggle]', function () {
        if (window.innerWidth >= 768) { return; }
        var $col = $(this).closest('.uc-footer-col').toggleClass('is-open');
        $(this).attr('aria-expanded', $col.hasClass('is-open'));
    });

    // ------------------------------------------------------------ search
    var searchTimer, searchXhr;
    $(document).on('click', '[data-search-open]', function () {
        closeDrawers();
        $('#ucSearch').addClass('is-open').attr('aria-hidden', 'false');
        $backdrop.addClass('is-open');
        setTimeout(function () { $('[data-search-input]').trigger('focus'); }, 300);
    });
    $(document).on('click', '[data-search-close]', closeDrawers);
    $(document).on('input', '[data-search-input]', function () {
        var q = $.trim($(this).val());
        var $out = $('[data-search-results]');
        clearTimeout(searchTimer);
        if (q.length < 2) { $out.empty(); return; }
        searchTimer = setTimeout(function () {
            if (searchXhr) { searchXhr.abort(); }
            $out.html('<div class="uc-sr-grid">' + new Array(5).join('<div><span class="uc-skel" style="height:200px"></span></div>') + '</div>');
            searchXhr = $.getJSON(endpoint('ajax/search.php'), { q: q }).done(function (r) {
                var html = '';
                if (r.categories && r.categories.length) {
                    html += '<div class="uc-sr-cats">' + r.categories.map(function (c) { return '<a href="' + c.url + '">' + $('<i>').text(c.name).html() + '</a>'; }).join('') + '</div>';
                }
                if (r.products && r.products.length) {
                    html += '<div class="uc-sr-grid">' + r.products.map(function (p) {
                        return '<a class="uc-sr-item" href="' + p.url + '"><img src="' + p.image + '" alt="" loading="lazy"><span>' + $('<i>').text(p.name).html() + '</span><strong>' + $('<i>').text(p.price).html() + '</strong></a>';
                    }).join('') + '</div><p class="mt-3"><a class="uc-link-arrow" href="' + r.all_url + '">View all ' + r.total + ' results <i class="bi bi-arrow-right"></i></a></p>';
                } else {
                    html += '<p class="text-muted">No products match “' + $('<i>').text(q).html() + '”.</p>';
                }
                $out.html(html);
            });
        }, 250);
    });

    // ------------------------------------------------------------ scroll reveal
    if (animOn && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('is-revealed'); io.unobserve(en.target); }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        $('[data-reveal]').each(function () { io.observe(this); });
        window.ucObserveReveal = function (ctx) { $(ctx).find('[data-reveal]').each(function () { io.observe(this); }); };
    } else {
        $('[data-reveal]').addClass('is-revealed');
        window.ucObserveReveal = function (ctx) { $(ctx).find('[data-reveal]').addClass('is-revealed'); };
    }

    // ------------------------------------------------------------ parallax (transform only, rAF throttled)
    if (animOn && window.innerWidth > 767) {
        var $par = $('[data-parallax]');
        var $heroPar = $('[data-parallax-hero] .uc-hero-slide.is-active [data-parallax-layer]');
        var ticking = false;
        var runParallax = function () {
            var vh = window.innerHeight;
            $par.each(function () {
                var r = this.getBoundingClientRect();
                if (r.bottom < -100 || r.top > vh + 100) { return; }
                var speed = parseFloat(this.getAttribute('data-parallax')) || 0.15;
                var offset = (r.top + r.height / 2 - vh / 2) * -speed;
                this.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
            });
            if ($('[data-parallax-hero]').length && window.scrollY < vh * 1.2) {
                $('[data-parallax-hero] [data-parallax-layer]').css('transform', 'translate3d(0,' + (window.scrollY * 0.3).toFixed(1) + 'px,0)');
            }
            ticking = false;
        };
        $(window).on('scroll resize', function () { if (!ticking) { ticking = true; window.requestAnimationFrame(runParallax); } });
        runParallax();
    }

    // ------------------------------------------------------------ hero carousel
    $('[data-hero]').each(function () {
        var $hero = $(this), $slides = $hero.find('[data-hero-slide]'), $dots = $hero.find('[data-hero-dot]');
        var n = $slides.length, idx = 0, timer = null, delay = parseInt($hero.data('autoplay'), 10) || 0;
        if (n < 2) { return; }
        $hero[0].style.setProperty('--hero-interval', delay + 'ms');
        function go(i) {
            idx = (i + n) % n;
            $slides.removeClass('is-active').eq(idx).addClass('is-active');
            $dots.removeClass('is-active').eq(idx).addClass('is-active');
            restart();
        }
        function restart() {
            clearTimeout(timer);
            if (delay && !reduceMotion) { timer = setTimeout(function () { go(idx + 1); }, delay); }
        }
        $hero.on('click', '[data-hero-next]', function () { go(idx + 1); });
        $hero.on('click', '[data-hero-prev]', function () { go(idx - 1); });
        $hero.on('click', '[data-hero-dot]', function () { go(parseInt($(this).data('hero-dot'), 10)); });
        $hero.on('mouseenter', function () { clearTimeout(timer); }).on('mouseleave', restart);
        var sx = null;
        $hero.on('touchstart', function (e) { sx = e.originalEvent.touches[0].clientX; });
        $hero.on('touchend', function (e) {
            if (sx === null) { return; }
            var dx = e.originalEvent.changedTouches[0].clientX - sx;
            if (Math.abs(dx) > 50) { go(idx + (dx < 0 ? 1 : -1)); }
            sx = null;
        });
        restart();
    });

    // ------------------------------------------------------------ scroll-snap carousels
    function initCarousels(ctx) {
        $(ctx || document).find('[data-carousel]').each(function () {
            var $c = $(this);
            if ($c.data('ready')) { return; }
            $c.data('ready', true);
            var track = $c.find('[data-carousel-track]')[0];
            if (!track) { return; }
            var $prev = $c.find('[data-carousel-prev]'), $next = $c.find('[data-carousel-next]'), $bar = $c.find('[data-carousel-progress]');
            var step = function () { var first = track.children[0]; return first ? first.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 20) : 300; };
            var update = function () {
                var max = track.scrollWidth - track.clientWidth;
                $prev.prop('disabled', track.scrollLeft <= 4);
                $next.prop('disabled', track.scrollLeft >= max - 4);
                if ($bar.length) {
                    var w = Math.max(10, track.clientWidth / track.scrollWidth * 100);
                    var left = max > 0 ? (track.scrollLeft / max) * (100 - w) : 0;
                    $bar.css({ width: w + '%', transform: 'translateX(' + (left / w * 100) + '%)' });
                    $bar.parent().toggle(max > 4);
                }
            };
            $prev.on('click', function () { track.scrollBy({ left: -step() * Math.max(1, Math.floor(track.clientWidth / step())), behavior: 'smooth' }); });
            $next.on('click', function () { track.scrollBy({ left: step() * Math.max(1, Math.floor(track.clientWidth / step())), behavior: 'smooth' }); });
            $(track).on('scroll', function () { window.requestAnimationFrame(update); });
            $(window).on('resize', update);
            update();
            var auto = parseInt($c.data('autoplay'), 10);
            if (auto && !reduceMotion) {
                var t = setInterval(function () {
                    if ($c.is(':hover')) { return; }
                    var max = track.scrollWidth - track.clientWidth;
                    track.scrollTo({ left: track.scrollLeft >= max - 4 ? 0 : track.scrollLeft + step(), behavior: 'smooth' });
                }, auto);
                $c.on('touchstart', function () { clearInterval(t); });
            }
        });
    }
    initCarousels();

    // ------------------------------------------------------------ wishlist
    $(document).on('click', '[data-wishlist]', function (e) {
        e.preventDefault();
        var $btn = $(this), id = $btn.data('wishlist');
        $btn.prop('disabled', true);
        $.post(endpoint('ajax/wishlist.php'), { product_id: id }).done(function (r) {
            $('[data-wishlist="' + id + '"]').toggleClass('is-active', r.added).attr('aria-pressed', r.added ? 'true' : 'false');
            var $cnt = $('[data-wish-count]').text(r.count).prop('hidden', !r.count).removeClass('is-bump');
            void $cnt[0] && $cnt[0].offsetWidth;
            $cnt.addClass('is-bump');
            toast(r.message, r.added ? 'success' : 'info', r.added ? r.message + ' <a class="uc-toast-link" href="' + endpoint('wishlist') + '">View</a>' : undefined);
            if (!r.added && $btn.closest('[data-wishlist-page]').length) { $btn.closest('.uc-card').fadeOut(300, function () { $(this).remove(); }); }
        }).fail(function (x) { toast(errorMessage(x), 'error'); }).always(function () { $btn.prop('disabled', false); });
    });

    // ------------------------------------------------------------ cart
    function setCartCount(n) {
        var $c = $('[data-cart-count]').text(n).prop('hidden', !n).removeClass('is-bump');
        if ($c[0]) { void $c[0].offsetWidth; }
        $c.addClass('is-bump');
    }
    function renderCart(r) {
        if (typeof r.count !== 'undefined') { setCartCount(r.count); }
        if (r.html) {
            $('[data-cart-drawer-body]').html(r.html);
        }
        if ($('[data-cart-page]').length) {
            $.getJSON(endpoint('ajax/cart.php'), { action: 'page', view: 'page' }).done(function (p) { $('[data-cart-page]').html(p.html); });
        }
        if ($('[data-checkout-form]').length) { refreshQuote(); }
    }
    function openCart() {
        openDrawer('ucCartDrawer');
        $.getJSON(endpoint('ajax/cart.php'), { action: 'drawer' }).done(renderCart);
    }
    $(document).on('click', '[data-cart-open]', function (e) {
        if ($('[data-cart-page]').length || $('[data-checkout-form]').length) { return; }
        e.preventDefault();
        openCart();
    });
    function cartAction(data, $btn) {
        if ($btn) { $btn.prop('disabled', true); }
        data.view = $('[data-cart-page]').length ? 'page' : 'drawer';
        return $.post(endpoint('ajax/cart.php'), data).done(function (r) {
            renderCart(r);
            if (!r.ok && r.message) { toast(r.message, 'warning'); }
        }).fail(function (x) { toast(errorMessage(x), 'error'); }).always(function () { if ($btn) { $btn.prop('disabled', false); } });
    }
    var qtyTimers = {};
    $(document).on('click', '[data-cart-qty]', function () {
        var id = $(this).data('cart-qty'), $in = $('[data-cart-qty-input="' + id + '"]').first();
        var v = Math.max(0, (parseInt($in.val(), 10) || 0) + parseInt($(this).data('delta'), 10));
        $('[data-cart-qty-input="' + id + '"]').val(v);
        clearTimeout(qtyTimers[id]);
        qtyTimers[id] = setTimeout(function () { cartAction({ action: 'update', item_id: id, qty: v }); }, 350);
    });
    $(document).on('change', '[data-cart-qty-input]', function () {
        cartAction({ action: 'update', item_id: $(this).data('cart-qty-input'), qty: parseInt($(this).val(), 10) || 0 });
    });
    $(document).on('click', '[data-cart-remove]', function () {
        var id = $(this).data('cart-remove');
        $(this).closest('.uc-cart-line').addClass('is-removing');
        setTimeout(function () { cartAction({ action: 'remove', item_id: id }); }, 250);
    });
    $(document).on('submit', '[data-cart-page] [data-coupon-form], #ucCartDrawer [data-coupon-form]', function (e) {
        e.preventDefault();
        var $f = $(this);
        cartAction({ action: 'coupon', code: $f.find('[name=code]').val() }, $f.find('button')).done(function (r) { toast(r.message, r.ok ? 'success' : 'error'); });
    });
    $(document).on('click', '[data-coupon-remove]', function () {
        cartAction({ action: 'coupon', code: '' }).done(function (r) { toast(r.message, 'info'); });
    });

    // quick add (simple products from cards)
    $(document).on('click', '[data-add-to-cart]', function () {
        var $b = $(this);
        $b.addClass('is-loading');
        cartAction({ action: 'add', product_id: $b.data('add-to-cart'), qty: 1 }, $b).done(function (r) {
            if (r.ok) { openDrawer('ucCartDrawer'); toast(r.message); }
        }).always(function () { $b.removeClass('is-loading'); });
    });

    // ------------------------------------------------------------ product buy form (variants)
    function initBuyForm($form) {
        if ($form.data('ready')) { return; }
        $form.data('ready', true);
        var variants = $form.data('variants') || [];
        var colorImages = $form.data('color-images') || {};
        var $groups = $form.find('[data-option-group]');
        var $btn = $form.find('[data-add-btn]');
        var hasVariants = $groups.length > 0;

        function selected() {
            var sel = {};
            $groups.each(function () {
                var name = $(this).data('option-group');
                var v = $(this).find('input:checked').val();
                if (v !== undefined) { sel[name] = v; }
            });
            return sel;
        }
        function matches(v, sel) {
            for (var k in sel) { if (Object.prototype.hasOwnProperty.call(sel, k) && v.options[k] !== sel[k]) { return false; } }
            return true;
        }
        function refresh() {
            var sel = selected();
            // availability of each option given the other selections
            $groups.each(function () {
                var name = $(this).data('option-group');
                $(this).find('[data-option-selected]').text(sel[name] || '');
                $(this).find('input').each(function () {
                    var test = $.extend({}, sel);
                    test[name] = this.value;
                    var ok = variants.some(function (v) { return matches(v, test) && v.available > 0; });
                    var exists = variants.some(function (v) { return matches(v, test); });
                    $(this).next('label').toggleClass('is-unavailable', !ok).attr('title', exists ? (ok ? this.value : this.value + ' — sold out') : this.value + ' — not available');
                });
            });
            if (!hasVariants) { return; }
            var complete = Object.keys(sel).length === $groups.length;
            var match = complete ? variants.filter(function (v) { return matches(v, sel); })[0] : null;
            var $stock = $form.find('[data-stock-msg]');
            $form.find('[data-variant-input]').val(match ? match.id : '');
            if (sel.Color && colorImages[sel.Color]) { swapMainImage(colorImages[sel.Color]); }
            if (!match) {
                if (complete) { $stock.html('<span class="is-out"><i class="bi bi-x-circle"></i> This combination is not available</span>'); }
                $btn.prop('disabled', complete).find('span').text('Add to Bag');
                return;
            }
            $form.find('[data-price]').text(match.price);
            var $reg = $form.find('[data-regular]');
            if (match.regular) { $reg.text(match.regular).prop('hidden', false); } else { $reg.prop('hidden', true); }
            $('[data-sku]').text(match.sku);
            if (match.available <= 0) {
                $stock.html('<span class="is-out"><i class="bi bi-x-circle"></i> Sold out in this option</span>');
                $btn.prop('disabled', true).find('span').text('Sold Out');
            } else {
                $stock.html(match.low ? '<span class="is-low"><i class="bi bi-exclamation-circle"></i> Only ' + match.available + ' left</span>'
                    : '<span class="is-in"><i class="bi bi-check2-circle"></i> In stock — ready to ship</span>');
                $btn.prop('disabled', false).find('span').text('Add to Bag');
                $form.find('[name=qty]').attr('max', Math.min(20, match.available));
            }
        }
        $form.on('change', 'input[data-option]', function () { $(this).closest('[data-option-group]').removeClass('is-missing'); refresh(); });
        refresh();

        function validate() {
            var ok = true;
            $groups.each(function () {
                if (!$(this).find('input:checked').length) { $(this).addClass('is-missing'); ok = false; }
            });
            if (!ok) {
                toast('Please select ' + $groups.filter('.is-missing').map(function () { return $(this).data('option-group') === 'Color' ? 'a colour' : 'your ' + String($(this).data('option-group')).toLowerCase(); }).get().join(' and ') + '.', 'warning');
                return false;
            }
            if (hasVariants && !$form.find('[data-variant-input]').val()) { toast('This combination is not available.', 'warning'); return false; }
            return true;
        }
        function add(buyNow) {
            if (!validate()) { return; }
            var data = $form.serializeArray().filter(function (f) { return f.name.indexOf('opt[') !== 0; });
            data.push({ name: 'action', value: 'add' });
            var $b = buyNow ? $form.find('[data-buy-now]') : $btn;
            $b.addClass('is-loading');
            $.post(endpoint('ajax/cart.php'), $.param(data)).done(function (r) {
                if (!r.ok) { toast(r.message, 'warning'); return; }
                setCartCount(r.count);
                if (buyNow) { window.location.href = endpoint('checkout'); return; }
                $('#ucQuickView').modal && bootstrap.Modal.getInstance(document.getElementById('ucQuickView')) && bootstrap.Modal.getInstance(document.getElementById('ucQuickView')).hide();
                $('[data-cart-drawer-body]').html(r.html);
                openDrawer('ucCartDrawer');
            }).fail(function (x) { toast(errorMessage(x), 'error'); }).always(function () { $b.removeClass('is-loading'); });
        }
        $form.on('submit', function (e) { e.preventDefault(); add(false); });
        $form.on('click', '[data-buy-now]', function () { add(true); });
    }
    function swapMainImage(src) {
        var $first = $('[data-gallery-track] img').first();
        if ($first.length && $first.attr('src') !== src) {
            $first.css('opacity', 0);
            setTimeout(function () { $first.attr('src', src).css('opacity', 1); }, 200);
            var track = $('[data-gallery-track]')[0];
            if (track) { track.scrollTo({ left: 0, behavior: 'smooth' }); }
        }
    }
    $('[data-buy-form]').each(function () { initBuyForm($(this)); });

    // quantity steppers (product form)
    $(document).on('click', '[data-qty-minus], [data-qty-plus]', function () {
        var $in = $(this).closest('[data-qty]').find('input');
        var max = parseInt($in.attr('max'), 10) || 20;
        var v = (parseInt($in.val(), 10) || 1) + ($(this).is('[data-qty-plus]') ? 1 : -1);
        $in.val(Math.max(1, Math.min(max, v)));
    });

    // ------------------------------------------------------------ quick view
    $(document).on('click', '[data-quickview]', function () {
        var id = $(this).data('quickview');
        var el = document.getElementById('ucQuickView');
        var $body2 = $(el).find('[data-quickview-body]');
        $body2.html('<div class="uc-qv-loading"><div class="uc-skel" style="height:100%"></div><div><span class="uc-skel" style="height:36px;width:70%"></span><span class="uc-skel" style="width:40%"></span><span class="uc-skel" style="height:100px"></span><span class="uc-skel" style="height:52px"></span></div></div>');
        bootstrap.Modal.getOrCreateInstance(el).show();
        $.getJSON(endpoint('ajax/quickview.php'), { id: id }).done(function (r) {
            $body2.html(r.html);
            initBuyForm($body2.find('[data-buy-form]'));
        }).fail(function (x) { $body2.html('<p class="p-5">' + errorMessage(x) + '</p>'); });
    });

    // ------------------------------------------------------------ product gallery, zoom, lightbox
    var $track = $('[data-gallery-track]');
    if ($track.length) {
        var trackEl = $track[0];
        $(document).on('click', '[data-gallery-thumb]', function () {
            var i = parseInt($(this).data('gallery-thumb'), 10);
            trackEl.scrollTo({ left: trackEl.clientWidth * i, behavior: 'smooth' });
        });
        $track.on('scroll', function () {
            var i = Math.round(trackEl.scrollLeft / trackEl.clientWidth);
            $('[data-gallery-thumb]').removeClass('is-active').eq(i).addClass('is-active');
            $('.uc-gallery-dots span').removeClass('is-active').eq(i).addClass('is-active');
        });
        if (window.matchMedia('(hover: hover) and (min-width: 992px)').matches) {
            $track.on('mousemove', '[data-zoom]', function (e) {
                var r = this.getBoundingClientRect();
                this.style.setProperty('--zx', ((e.clientX - r.left) / r.width * 100) + '%');
                this.style.setProperty('--zy', ((e.clientY - r.top) / r.height * 100) + '%');
                $(this).addClass('is-zooming');
            }).on('mouseleave', '[data-zoom]', function () { $(this).removeClass('is-zooming'); });
        }
    }
    var $lb = $('[data-lightbox]'), lbIdx = 0, lbImgs = [];
    function openLightbox(i) {
        lbImgs = $('[data-gallery-track] img').map(function () { return $(this).data('full') || this.src; }).get();
        lbIdx = i || 0;
        $lb.removeClass('is-video').addClass('is-open').attr('aria-hidden', 'false').find('[data-lightbox-img]').attr('src', lbImgs[lbIdx]);
        $body.addClass('no-scroll');
    }
    function closeLightbox() {
        if (!$lb.hasClass('is-open')) { return; }
        $lb.removeClass('is-open is-video').attr('aria-hidden', 'true').find('[data-lightbox-video]').empty();
        $body.removeClass('no-scroll');
    }
    $(document).on('click', '[data-lightbox]:not(.uc-lightbox)', function () { openLightbox(parseInt($(this).data('lightbox'), 10)); });
    $(document).on('click', '[data-lightbox-open]', function () {
        openLightbox(Math.round($track[0].scrollLeft / $track[0].clientWidth));
    });
    $(document).on('click', '[data-lightbox-close]', closeLightbox);
    $(document).on('click', '[data-lightbox-next], [data-lightbox-prev]', function () {
        lbIdx = (lbIdx + ($(this).is('[data-lightbox-next]') ? 1 : -1) + lbImgs.length) % lbImgs.length;
        $lb.find('[data-lightbox-img]').attr('src', lbImgs[lbIdx]);
    });
    $(document).on('click', '[data-gallery-video]', function () {
        var $v = $lb.find('[data-lightbox-video]'), src = String($v.data('src') || ''), m;
        var html;
        if ((m = src.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{6,15})/))) {
            html = '<iframe src="https://www.youtube-nocookie.com/embed/' + m[1] + '?autoplay=1&rel=0" allow="autoplay; fullscreen" allowfullscreen title="Product video"></iframe>';
        } else if ((m = src.match(/vimeo\.com\/(\d+)/))) {
            html = '<iframe src="https://player.vimeo.com/video/' + m[1] + '?autoplay=1" allow="autoplay; fullscreen" allowfullscreen title="Product video"></iframe>';
        } else if (/^https?:\/\//.test(src)) {
            html = $('<video controls autoplay playsinline>').attr('src', src)[0].outerHTML;
        }
        if (!html) { return; }
        $v.html(html);
        $lb.addClass('is-open is-video').attr('aria-hidden', 'false');
        $body.addClass('no-scroll');
    });

    // sticky mobile buy bar
    var $sticky = $('[data-sticky-buy]');
    if ($sticky.length && 'IntersectionObserver' in window) {
        var buyEl = document.querySelector('.uc-pdp [data-buy-form]');
        new IntersectionObserver(function (en) { $sticky.toggleClass('is-visible', !en[0].isIntersecting && en[0].boundingClientRect.top < 0); }).observe(buyEl);
        $sticky.on('click', '[data-scroll-to-buy]', function () { buyEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
    }

    // delivery check
    $(document).on('submit', '[data-delivery-check]', function (e) {
        e.preventDefault();
        var $f = $(this);
        $.getJSON(endpoint('ajax/delivery.php'), $f.serialize()).done(function (r) { $f.siblings('[data-delivery-result]').text(r.message); });
    });

    // copy link
    $(document).on('click', '[data-copy-link]', function () {
        var link = $(this).data('copy-link');
        if (navigator.clipboard) { navigator.clipboard.writeText(link).then(function () { toast('Link copied.'); }); }
    });

    // reviews
    $(document).on('submit', '[data-review-form]', function (e) {
        e.preventDefault();
        var $f = $(this), $b = $f.find('button[type=submit]').addClass('is-loading');
        $.post(endpoint('ajax/review.php'), $f.serialize()).done(function (r) {
            $f.replaceWith('<div class="uc-alert uc-alert--success">' + $('<i>').text(r.message).html() + '</div>');
        }).fail(function (x) { toast(errorMessage(x), 'error'); }).always(function () { $b.removeClass('is-loading'); });
    });

    // newsletter
    $(document).on('submit', '[data-newsletter-form]', function (e) {
        e.preventDefault();
        var $f = $(this), email = $.trim($f.find('[name=email]').val());
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('Please enter a valid email address.', 'warning'); return; }
        if (!$f.find('[name=consent]').is(':checked')) { toast('Please tick the consent box to subscribe.', 'warning'); return; }
        var $b = $f.find('button').prop('disabled', true);
        $.post(endpoint('ajax/newsletter.php'), $f.serialize()).done(function (r) {
            toast(r.message);
            $f[0].reset();
        }).fail(function (x) { toast(errorMessage(x), 'error'); }).always(function () { $b.prop('disabled', false); });
    });

    // ------------------------------------------------------------ listing: AJAX filters + sort
    var $listing = $('[data-listing]');
    if ($listing.length) {
        var loadListing = function (url, push) {
            var $res = $('[data-listing-results]').addClass('is-loading');
            $.get(url, function (html) {
                var $doc = $('<div>').append($.parseHTML(html));
                $('[data-listing]').replaceWith($doc.find('[data-listing]'));
                if (push) { history.pushState({ listing: true }, '', url); }
                $('[data-listing] [data-reveal]').addClass('is-revealed');
                initCarousels(document.querySelector('[data-listing]'));
            }).fail(function () { window.location.href = url; }).always(function () { $res.removeClass('is-loading'); });
        };
        var filterUrl = function ($f) {
            var qs = $f.serializeArray().filter(function (x) { return x.value !== ''; });
            return $f.attr('action') + (qs.length ? '?' + $.param(qs) : '');
        };
        $(document).on('change', '[data-filter-form] input', function () {
            if (window.innerWidth < 992) { return; } // mobile: apply with "Show results"
            clearTimeout(window.ucFilterT);
            var $f = $(this).closest('form');
            window.ucFilterT = setTimeout(function () { loadListing(filterUrl($f), true); }, $(this).is('[type=number]') ? 600 : 50);
        });
        $(document).on('submit', '[data-filter-form]', function (e) { e.preventDefault(); closeDrawers(); loadListing(filterUrl($(this)), true); });
        $(document).on('change', '[data-sort-form] [data-auto-submit]', function () { loadListing(filterUrl($(this).closest('form')), true); });
        $(document).on('click', '[data-listing] .uc-chip, [data-listing] .uc-pagination a', function (e) {
            e.preventDefault();
            loadListing(this.href, true);
            if ($(this).closest('.uc-pagination').length) { $('html, body').animate({ scrollTop: $('[data-listing]').offset().top - 80 }, 400); }
        });
        window.addEventListener('popstate', function (e) { if (e.state && e.state.listing) { loadListing(location.href, false); } });
        history.replaceState({ listing: true }, '', location.href);
    }
    $(document).on('change', '[data-auto-submit]', function () { if (!$listing.length) { this.form.submit(); } });

    // ------------------------------------------------------------ checkout
    var $co = $('[data-checkout-form]');
    var quoteTimer, quoteXhr;
    function refreshQuote() {
        if (!$co.length) { return; }
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(function () {
            if (quoteXhr) { quoteXhr.abort(); }
            quoteXhr = $.post(endpoint('ajax/checkout-quote.php'), {
                city: $co.find('[name=city]').val(), region: $co.find('[name=region]').val(),
                shipping_method: $co.find('[name=shipping_method]:checked').val() || 'standard',
                payment_method: $co.find('[name=payment_method]:checked').val() || '', email: $co.find('[name=email]').val()
            }).done(function (r) {
                $('[data-checkout-totals]').html(r.totals_html);
                $('[data-shipping-methods]').html(r.methods_html);
                var $cod = $co.find('[data-pay-opt="cod"]');
                var $codIn = $cod.find('input');
                if (!r.cod_available) {
                    $cod.addClass('is-disabled'); $codIn.prop('disabled', true).prop('checked', false);
                    $cod.find('[data-cod-note]').text('Not available for this delivery area').prop('hidden', false);
                } else if ($codIn.data('was-disabled') !== true) {
                    $cod.removeClass('is-disabled'); $codIn.prop('disabled', false);
                    $cod.find('[data-cod-note]').prop('hidden', true);
                }
                $co.find('[data-place-order]').prop('disabled', !r.can_order);
            });
        }, 250);
    }
    if ($co.length) {
        $co.find('[data-pay-opt="cod"] input').each(function () { $(this).data('was-disabled', this.disabled); });
        refreshQuote();
        $co.on('change', '[data-quote-trigger], [name=shipping_method], [name=payment_method]', refreshQuote);
        $co.on('input', '[name=city]', refreshQuote);
        $co.on('change', '[data-saved-address]', function () {
            if (!this.value) { return; }
            var a = JSON.parse(this.value);
            $.each(a, function (k, v) { $co.find('[name=' + k + ']').val(v || ''); });
            refreshQuote();
        });
        $co.on('change', '[data-toggle-target]', function () { $($(this).data('toggle-target')).prop('hidden', !this.checked); });
        $co.on('click', '[data-coupon-apply]', function () {
            var $b = $(this);
            $.post(endpoint('ajax/cart.php'), { action: 'coupon', code: $co.find('[data-coupon-input]').val(), email: $co.find('[name=email]').val() })
                .done(function (r) { toast(r.message, r.ok ? 'success' : 'error'); refreshQuote(); })
                .fail(function (x) { toast(errorMessage(x), 'error'); });
        });
        $co.on('submit', function (e) {
            var missing = [];
            $co.find('.uc-field').removeClass('is-invalid');
            $co.find('[required]').each(function () {
                if (this.type === 'radio') { if (!$co.find('[name="' + this.name + '"]:checked').length) { missing.push(this.name); } return; }
                if (!$.trim(this.value)) { $(this).closest('.uc-field').addClass('is-invalid'); missing.push(this.name); }
            });
            if (missing.length) {
                e.preventDefault();
                toast(missing.indexOf('payment_method') > -1 && missing.length === 1 ? 'Please choose a payment method.' : 'Please complete the highlighted fields.', 'warning');
                var $first = $co.find('.is-invalid').first();
                if ($first.length) { $('html, body').animate({ scrollTop: $first.offset().top - 120 }, 300); }
                return;
            }
            // prevent double submission
            $co.find('[data-place-order]').addClass('is-loading').prop('disabled', true);
            $co.off('submit').on('submit', function (ev) { ev.preventDefault(); });
        });
    }

    // payment redirect forms
    $('[data-auto-submit-form]').each(function () { var f = this; setTimeout(function () { f.submit(); }, 600); });

    // confirm dialogs
    $(document).on('submit', 'form[data-confirm]', function (e) {
        var form = this;
        if (form.dataset.confirmed) { return; }
        e.preventDefault();
        var go = function () { form.dataset.confirmed = '1'; form.submit(); };
        if (window.Swal) {
            Swal.fire({ text: $(form).data('confirm'), icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, continue' }).then(function (r) { if (r.isConfirmed) { go(); } });
        } else if (window.confirm($(form).data('confirm'))) { go(); }
    });
})(jQuery);
