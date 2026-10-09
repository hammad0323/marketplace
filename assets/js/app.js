/* =====================================================================
   Beglet storefront behaviour (jQuery + vanilla where it is lighter)
   ===================================================================== */
(function ($) {
  'use strict';

  var B = window.BEGLET || { base: '', csrf: '' };
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var animate = B.animations !== false && !reduceMotion;
  document.documentElement.classList.remove('no-js');

  // ---------- Helpers ----------
  function api(path) { return B.base + '/api/' + path; }
  $.ajaxSetup({ headers: { 'X-CSRF-Token': B.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

  var Toast = window.Swal ? Swal.mixin({
    toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3200, timerProgressBar: true,
    showClass: { popup: 'swal2-noanimation' }, hideClass: { popup: '' }
  }) : null;
  function notify(type, message) {
    if (!message) return;
    if (Toast) { Toast.fire({ icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success')), title: message }); }
    else { window.alert(message); }
  }
  function errorMessage(xhr) {
    return (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.';
  }
  function setCartCount(n) {
    var $b = $('[data-cart-count]');
    $b.text(n).prop('hidden', !n).removeClass('is-bump');
    if (n) { void $b[0] && $b[0].offsetWidth; $b.addClass('is-bump'); }
  }
  function loading($btn, on) { $btn.toggleClass('is-loading', on).prop('disabled', on); }

  // Flash messages rendered by PHP
  $('.flash-data').each(function () { notify($(this).data('type'), $(this).data('message')); });

  // Confirm dialogs for destructive forms
  $(document).on('submit', 'form[data-confirm]', function (e) {
    var form = this;
    if (form.dataset.confirmed) return;
    e.preventDefault();
    Swal.fire({ title: form.dataset.confirm, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, continue' })
      .then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); } });
  });

  // ---------- Announcement rotator ----------
  (function () {
    var $bar = $('.announcement'), $items = $bar.find('.announcement__item');
    if ($items.length < 2) return;
    var i = 0, interval = parseInt($bar.data('interval'), 10) || 5000;
    setInterval(function () {
      var $cur = $items.eq(i).removeClass('is-active').addClass('is-leaving');
      setTimeout(function () { $cur.removeClass('is-leaving'); }, 700);
      i = (i + 1) % $items.length;
      $items.eq(i).addClass('is-active');
    }, interval);
  })();

  // ---------- Sticky header ----------
  (function () {
    var header = document.getElementById('siteHeader');
    if (!header) return;
    var lastY = window.scrollY, ticking = false;
    function update() {
      var y = window.scrollY;
      header.classList.toggle('is-scrolled', y > 40);
      // Hide when scrolling down fast deep in the page, reveal on scroll up.
      header.classList.toggle('is-hidden', y > 600 && y > lastY + 4 && !document.querySelector('.has-mega:hover'));
      if (y < lastY - 4 || y < 600) header.classList.remove('is-hidden');
      lastY = y; ticking = false;
    }
    window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(update); ticking = true; } }, { passive: true });
    update();
  })();

  // ---------- Reveal on scroll ----------
  (function () {
    var els = document.querySelectorAll('[data-reveal]');
    if (!animate || !('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('is-visible'); }); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    els.forEach(function (el) { io.observe(el); });
    window.BegletReveal = function (root) {
      (root || document).querySelectorAll('[data-reveal]:not(.is-visible)').forEach(function (el) { io.observe(el); });
    };
  })();

  // ---------- Parallax (transform-only, rAF, viewport-gated) ----------
  (function () {
    if (!animate) return;
    var items = [].slice.call(document.querySelectorAll('[data-parallax]'));
    if (!items.length) return;
    var visible = new Set();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { en.isIntersecting ? visible.add(en.target) : visible.delete(en.target); });
    });
    items.forEach(function (el) { io.observe(el.parentElement || el); el._box = el.parentElement || el; });
    var ticking = false;
    function frame() {
      var vh = window.innerHeight;
      items.forEach(function (el) {
        if (!visible.has(el._box)) return;
        var r = el._box.getBoundingClientRect();
        var progress = (r.top + r.height / 2 - vh / 2) / vh; // -1..1
        var speed = parseFloat(el.dataset.parallax) || 0.15;
        el.style.transform = 'translate3d(0,' + (progress * speed * -100).toFixed(2) + 'px,0)';
      });
      ticking = false;
    }
    window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(frame); ticking = true; } }, { passive: true });
    frame();
  })();

  // ---------- Hero carousel ----------
  $('[data-hero]').each(function () {
    var $hero = $(this), $slides = $hero.find('.hero__slide'), $dots = $hero.find('[data-hero-dot]');
    var n = $slides.length, i = 0, timer = null;
    var interval = parseInt($hero.data('interval'), 10) || 6500;
    var autoplay = $hero.data('autoplay') === 1 || $hero.data('autoplay') === '1';
    var isSlide = $hero.hasClass('hero--slide');
    this.style.setProperty('--hero-interval', interval + 'ms');
    if (n < 2) return;
    function go(to) {
      i = (to + n) % n;
      $slides.removeClass('is-active').eq(i).addClass('is-active');
      $dots.removeClass('is-active');
      if ($dots.length) { void $dots[0].offsetWidth; $dots.eq(i).addClass('is-active'); }
      if (isSlide) { $hero.find('.hero__slides').css('transform', 'translateX(' + (-100 * i) + '%)'); }
      $slides.each(function (k) { this.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
      restart();
    }
    function restart() { clearTimeout(timer); if (autoplay && !reduceMotion && !$hero.hasClass('is-paused')) { timer = setTimeout(function () { go(i + 1); }, interval); } }
    $hero.on('click', '[data-hero-next]', function () { go(i + 1); });
    $hero.on('click', '[data-hero-prev]', function () { go(i - 1); });
    $hero.on('click', '[data-hero-dot]', function () { go(parseInt($(this).data('hero-dot'), 10)); });
    $hero.on('mouseenter focusin', function () { $hero.addClass('is-paused'); clearTimeout(timer); });
    $hero.on('mouseleave focusout', function () { $hero.removeClass('is-paused'); restart(); });
    $hero.on('keydown', function (e) { if (e.key === 'ArrowRight') go(i + 1); if (e.key === 'ArrowLeft') go(i - 1); });
    var sx = null;
    this.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    this.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 45) go(i + (dx < 0 ? 1 : -1)); sx = null; });
    document.addEventListener('visibilitychange', function () { document.hidden ? clearTimeout(timer) : restart(); });
    go(0);
  });

  // ---------- Product carousels (CSS scroll-snap) ----------
  function initCarousels(root) {
    $(root || document).find('[data-carousel]').each(function () {
      if (this._init) return; this._init = true;
      var $c = $(this), track = $c.find('[data-carousel-track]')[0], $prev = $c.find('[data-carousel-prev]'), $next = $c.find('[data-carousel-next]'), bar = $c.find('[data-carousel-progress]')[0];
      function step() { var s = track.querySelector('.pcarousel__slide'); return s ? s.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 24) : 300; }
      function update() {
        var max = track.scrollWidth - track.clientWidth;
        $prev.prop('disabled', track.scrollLeft <= 2);
        $next.prop('disabled', track.scrollLeft >= max - 2);
        if (bar) { var w = Math.max(12, track.clientWidth / track.scrollWidth * 100); bar.style.width = w + '%'; bar.style.left = (max > 0 ? track.scrollLeft / max * (100 - w) : 0) + '%'; }
        $c.find('.pcarousel__nav').toggle(max > 2);
      }
      $prev.on('click', function () { track.scrollBy({ left: -step() * Math.max(1, Math.floor(track.clientWidth / step())), behavior: reduceMotion ? 'auto' : 'smooth' }); });
      $next.on('click', function () { track.scrollBy({ left: step() * Math.max(1, Math.floor(track.clientWidth / step())), behavior: reduceMotion ? 'auto' : 'smooth' }); });
      track.addEventListener('scroll', function () { requestAnimationFrame(update); }, { passive: true });
      window.addEventListener('resize', update);
      update();
    });
  }
  initCarousels();

  // ---------- Testimonials ----------
  $('[data-quotes]').each(function () {
    var $q = $(this), $items = $q.find('.quote'), $dots = $q.find('[data-quote-dot]'), i = 0, t;
    if ($items.length < 2) return;
    function go(n) { i = (n + $items.length) % $items.length; $items.removeClass('is-active').eq(i).addClass('is-active'); $dots.removeClass('is-active').eq(i).addClass('is-active'); clearTimeout(t); if (!reduceMotion) t = setTimeout(function () { go(i + 1); }, 7000); }
    $dots.on('click', function () { go(parseInt($(this).data('quote-dot'), 10)); });
    go(0);
  });

  // ---------- Search overlay & suggestions ----------
  (function () {
    var $ov = $('#searchOverlay'), $in = $ov.find('[data-search-input]'), $res = $ov.find('[data-search-results]'), timer, xhr, lastFocus;
    function open() { lastFocus = document.activeElement; $ov.addClass('is-open').attr('aria-hidden', 'false'); setTimeout(function () { $in.trigger('focus'); }, 80); $('body').css('overflow', 'hidden'); }
    function close() { $ov.removeClass('is-open').attr('aria-hidden', 'true'); $('body').css('overflow', ''); if (lastFocus) lastFocus.focus(); }
    $(document).on('click', '[data-search-open]', open);
    $ov.on('click', '[data-search-close]', close);
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $ov.hasClass('is-open')) close(); if (e.key === '/' && !$(e.target).is('input,textarea,select')) { e.preventDefault(); open(); } });
    $in.on('input', function () {
      var q = $.trim($in.val());
      clearTimeout(timer);
      if (q.length < 2) { $res.empty(); return; }
      timer = setTimeout(function () {
        if (xhr) xhr.abort();
        $res.html('<div class="skeleton-list"><div class="skeleton" style="height:64px"></div><div class="skeleton" style="height:64px"></div></div>');
        xhr = $.getJSON(api('search'), { q: q }).done(function (r) {
          var html = '';
          if (r.categories && r.categories.length) {
            html += '<div class="search-suggest__group"><h4>Categories</h4><div class="search-suggest__chips">' + r.categories.map(function (c) { return '<a href="' + c.url + '">' + $('<i>').text(c.name).html() + '</a>'; }).join('') + '</div></div>';
          }
          if (r.products && r.products.length) {
            html += '<div class="search-suggest__group"><h4>Products</h4>' + r.products.map(function (p) {
              return '<a class="search-suggest__item" href="' + p.url + '"><img src="' + p.image + '" alt="" loading="lazy"><strong>' + $('<i>').text(p.name).html() + '</strong><span>' + p.price + '</span></a>';
            }).join('') + '</div><p class="mt-3"><a class="link-arrow" href="' + r.search_url + '">See all ' + r.total + ' results <i class="bi bi-arrow-right"></i></a></p>';
          }
          $res.html(html || '<p class="text-muted mt-3">No matches for “' + $('<i>').text(q).html() + '”.</p>');
        });
      }, 220);
    });
  })();

  // ---------- Mini cart ----------
  var miniCartEl = document.getElementById('miniCart');
  var miniCart = miniCartEl && window.bootstrap ? bootstrap.Offcanvas.getOrCreateInstance(miniCartEl) : null;
  function loadMiniCart(html) {
    var $body = $('[data-minicart-body]');
    if (html !== undefined) { $body.html(html); return; }
    $.getJSON(api('cart'), { action: 'mini' }).done(function (r) { $body.html(r.html); setCartCount(r.count); });
  }
  $(document).on('click', '[data-minicart-open]', function (e) {
    if (!miniCart || window.innerWidth < 360 || $('body').hasClass('page-cart') || $('body').hasClass('page-checkout')) return;
    e.preventDefault(); loadMiniCart(); miniCart.show();
  });

  function cartRequest(data, $scope) {
    var isPage = $scope && $scope.closest('[data-cart-page]').length;
    data.view = isPage ? 'page' : 'mini';
    $scope && $scope.addClass('is-updating');
    return $.post(api('cart'), data).always(function (r) {
      var res = r.responseJSON || r;
      if (res && res.html !== undefined) {
        if (isPage) { $('[data-cart-page]').html(res.html); } else { loadMiniCart(res.html); }
      }
      if (res && res.count !== undefined) setCartCount(res.count);
      if (res && res.message && (data.action !== 'update' || !res.ok)) notify(res.ok ? 'success' : 'error', res.message);
      $scope && $scope.removeClass('is-updating');
    });
  }
  $(document).on('click', '[data-line-qty]', function () {
    var id = $(this).data('line-qty'), $in = $('[data-line-input="' + id + '"]'), q = (parseInt($in.val(), 10) || 1) + parseInt($(this).data('delta'), 10);
    cartRequest({ action: 'update', item_id: id, quantity: Math.max(0, q) }, $(this).closest('.cart-line'));
  });
  $(document).on('change', '[data-line-input]', function () {
    cartRequest({ action: 'update', item_id: $(this).data('line-input'), quantity: Math.max(0, parseInt(this.value, 10) || 0) }, $(this).closest('.cart-line'));
  });
  $(document).on('click', '[data-line-remove]', function () { cartRequest({ action: 'remove', item_id: $(this).data('line-remove') }, $(this).closest('.cart-line')); });
  $(document).on('change', '[data-gift-toggle]', function () { cartRequest({ action: 'gift', item_id: $(this).data('gift-toggle'), on: this.checked ? 1 : 0 }, $(this).closest('.cart-line')); });
  $(document).on('submit', '[data-coupon-form]', function (e) {
    e.preventDefault();
    var code = $.trim($(this).find('[name=code]').val());
    if (!code) return;
    cartRequest({ action: 'coupon', code: code }, $(this));
  });
  $(document).on('click', '[data-coupon-remove]', function () { cartRequest({ action: 'coupon_remove' }, $(this).closest('[data-coupon-form]')); });

  // Quick add from product card (simple products)
  $(document).on('click', '[data-add-to-cart]', function () {
    var $b = $(this);
    $b.addClass('is-loading');
    $.post(api('cart'), { action: 'add', product_id: $b.data('add-to-cart'), quantity: 1, view: 'mini' })
      .done(function (r) { setCartCount(r.count); loadMiniCart(r.html); if (miniCart) miniCart.show(); else notify('success', r.message); })
      .fail(function (x) { notify('error', errorMessage(x)); })
      .always(function () { $b.removeClass('is-loading'); });
  });

  // ---------- Wishlist ----------
  $(document).on('click', '[data-wishlist]', function () {
    var $b = $(this), id = $b.data('wishlist');
    $.post(api('wishlist'), { product_id: id }).done(function (r) {
      $('[data-wishlist="' + id + '"]').each(function () {
        $(this).toggleClass('is-active', r.added).attr('aria-pressed', r.added ? 'true' : 'false')
          .find('i').attr('class', 'bi ' + (r.added ? 'bi-heart-fill' : 'bi-heart'));
        $(this).removeClass('is-pop'); void this.offsetWidth; $(this).addClass('is-pop');
      });
      $('[data-wishlist-count]').text(r.count).prop('hidden', !r.count);
      notify('success', r.message);
    }).fail(function (x) { notify('error', errorMessage(x)); });
  });

  // ---------- Buy box (product page + quick view) ----------
  function initBuybox(root) {
    $(root || document).find('[data-buybox]').each(function () {
      if (this._init) return; this._init = true;
      var $f = $(this), variants = $f.data('variants') || [], $qty = $f.find('[name=quantity]');
      var $gallery = $f.closest('.quickview__grid, .pdp__top').find('[data-gallery]');
      function current() { var id = parseInt($f.find('[name=variant_id]:checked').val(), 10); return variants.filter(function (v) { return v.id === id; })[0]; }
      function render() {
        var v = current();
        if (!v) return;
        $f.find('[data-variant-label]').text(v.label);
        $f.find('[data-price]').text(v.price).toggleClass('price--sale', !!v.regular);
        $f.find('[data-regular]').text(v.regular || '').prop('hidden', !v.regular);
        $f.find('[data-off]').text('Save ' + v.off + '%').prop('hidden', !v.off);
        $f.find('[data-stock]').attr('class', 'stock stock--' + v.stock_class + ' buybox__stock').find('span').text(v.stock_label);
        $f.find('[data-add-btn], [data-buy-now]').prop('disabled', !v.available);
        $f.find('[data-add-text]').text(v.available ? (v.stock_class === 'preorder' ? 'Pre-order' : 'Add to bag') : 'Sold out');
        $qty.attr('max', Math.max(1, v.max));
        if (parseInt($qty.val(), 10) > v.max) $qty.val(Math.max(1, v.max));
        if (v.image !== null && $gallery.length) $gallery.trigger('gallery:go', [v.image]);
      }
      $f.on('change', '[name=variant_id]', render);
      $f.on('click', '[data-qty-minus]', function () { $qty.val(Math.max(1, (parseInt($qty.val(), 10) || 1) - 1)); });
      $f.on('click', '[data-qty-plus]', function () { var m = parseInt($qty.attr('max'), 10) || 20; $qty.val(Math.min(m, (parseInt($qty.val(), 10) || 1) + 1)); });
      function add(buyNow, $btn) {
        var data = { action: 'add', product_id: $f.find('[name=product_id]').val(), variant_id: $f.find('[name=variant_id]:checked').val() || 0, quantity: $qty.val(), gift_wrap: $f.find('[name=gift_wrap]').is(':checked') ? 1 : 0, view: 'mini' };
        loading($btn, true);
        $.post(api('cart'), data).done(function (r) {
          setCartCount(r.count);
          if (buyNow) { window.location.href = B.base + '/checkout'; return; }
          var qv = document.getElementById('quickView');
          if (qv && $f.closest('#quickView').length) bootstrap.Modal.getOrCreateInstance(qv).hide();
          loadMiniCart(r.html); if (miniCart) miniCart.show(); else notify('success', r.message);
        }).fail(function (x) { notify('error', errorMessage(x)); }).always(function () { loading($btn, false); });
      }
      $f.on('submit', function (e) { e.preventDefault(); add(false, $f.find('[data-add-btn]')); });
      $f.on('click', '[data-buy-now]', function () { add(true, $(this)); });
      render();
    });
  }
  initBuybox();

  // Sticky add-to-cart (mobile)
  (function () {
    var bar = document.querySelector('[data-sticky-atc]'), btn = document.querySelector('.pdp [data-add-btn]');
    if (!bar || !btn || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(function (en) { bar.classList.toggle('is-visible', !en[0].isIntersecting && en[0].boundingClientRect.top < 0); }).observe(btn);
    $(bar).on('click', '[data-sticky-atc-btn]', function () { $(btn).closest('form').trigger('submit'); });
  })();

  // ---------- Gallery, zoom, lightbox ----------
  function initGallery(root) {
    $(root || document).find('[data-gallery]').each(function () {
      if (this._init) return; this._init = true;
      var $g = $(this), $slides = $g.find('[data-gallery-slide]'), $thumbs = $g.find('[data-gallery-thumb]'), i = 0;
      function go(n) { i = (n + $slides.length) % $slides.length; $slides.removeClass('is-active').eq(i).addClass('is-active'); $thumbs.removeClass('is-active').eq(i).addClass('is-active'); }
      $g.on('gallery:go', function (e, n) { go(n); });
      $g.on('click', '[data-gallery-thumb]', function () { go(parseInt($(this).data('gallery-thumb'), 10)); });
      $g.on('click', '[data-gallery-next]', function () { go(i + 1); });
      $g.on('click', '[data-gallery-prev]', function () { go(i - 1); });
      var sx = null, main = $g.find('.gallery__main')[0];
      main.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
      main.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1)); sx = null; });
      // Hover zoom (pointer devices only)
      if (window.matchMedia('(hover: hover)').matches) {
        $g.on('mouseenter', '[data-zoom]', function () { this.style.backgroundImage = 'url("' + this.dataset.zoom + '")'; this.classList.add('is-zooming'); });
        $g.on('mousemove', '[data-zoom]', function (e) { var r = this.getBoundingClientRect(); this.style.backgroundPosition = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%'; });
        $g.on('mouseleave', '[data-zoom]', function () { this.classList.remove('is-zooming'); });
      }
      // Lightbox
      var $lb = $('[data-lightbox]');
      if (!$lb.length) return;
      var srcs = $slides.map(function () { return $(this).find('[data-zoom]').data('zoom'); }).get(), li = 0;
      function show(n) { li = (n + srcs.length) % srcs.length; $lb.find('[data-lightbox-img]').attr('src', srcs[li]); $lb.find('[data-lightbox-count]').text((li + 1) + ' / ' + srcs.length); }
      $g.on('click', '[data-lightbox-open], [data-zoom]', function () { show(i); $lb.prop('hidden', false); $('body').css('overflow', 'hidden'); $lb.find('[data-lightbox-close]').trigger('focus'); });
      $lb.on('click', '[data-lightbox-close]', function () { $lb.prop('hidden', true); $('body').css('overflow', ''); });
      $lb.on('click', '[data-lightbox-next]', function () { show(li + 1); });
      $lb.on('click', '[data-lightbox-prev]', function () { show(li - 1); });
      $lb.on('click', function (e) { if (e.target === this) { $lb.prop('hidden', true); $('body').css('overflow', ''); } });
      $(document).on('keydown', function (e) { if ($lb.prop('hidden')) return; if (e.key === 'Escape') $lb.find('[data-lightbox-close]').click(); if (e.key === 'ArrowRight') show(li + 1); if (e.key === 'ArrowLeft') show(li - 1); });
    });
  }
  initGallery();

  // ---------- Quick view ----------
  $(document).on('click', '[data-quickview]', function () {
    var slug = $(this).data('quickview'), el = document.getElementById('quickView');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el), $body = $(el).find('[data-quickview-body]');
    $body.html('<div class="quickview__grid"><div class="quickview__gallery"><div class="skeleton" style="aspect-ratio:4/5;height:auto"></div></div><div class="quickview__info"><div class="skeleton" style="height:40px;margin-bottom:16px"></div><div class="skeleton" style="height:24px;width:40%;margin-bottom:24px"></div><div class="skeleton" style="height:140px"></div></div></div>');
    modal.show();
    $.getJSON(api('quick-view'), { slug: slug }).done(function (r) { $body.html(r.html); initGallery($body); initBuybox($body); })
      .fail(function (x) { modal.hide(); notify('error', errorMessage(x)); });
  });

  // ---------- Newsletter ----------
  $(document).on('submit', '[data-newsletter-form]', function (e) {
    e.preventDefault();
    var $f = $(this), $btn = $f.find('button[type=submit]'), email = $.trim($f.find('[name=email]').val());
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { notify('error', 'Please enter a valid email address.'); return; }
    if (!$f.find('[name=consent]').is(':checked')) { notify('error', 'Please tick the consent box to subscribe.'); return; }
    loading($btn, true);
    $.post(api('newsletter'), $f.serialize()).done(function (r) {
      Swal.fire({ icon: 'success', title: 'Thank you', text: r.message, confirmButtonText: 'Continue' }); $f[0].reset();
    }).fail(function (x) { notify('error', errorMessage(x)); }).always(function () { loading($btn, false); });
  });

  // ---------- Reviews ----------
  $(document).on('submit', '[data-review-form]', function (e) {
    e.preventDefault();
    var $f = $(this), $btn = $f.find('button[type=submit]');
    if (!$f.find('[name=rating]:checked').length) { notify('error', 'Please choose a star rating.'); return; }
    loading($btn, true);
    $.post(api('review'), $f.serialize()).done(function (r) {
      Swal.fire({ icon: 'success', title: 'Review received', text: r.message }); $f[0].reset(); $f.collapse('hide');
    }).fail(function (x) { notify('error', errorMessage(x)); }).always(function () { loading($btn, false); });
  });

  // ---------- Filters: auto-submit swatches on desktop ----------
  $(document).on('change', '[data-filter-form] .color-opt input', function () { $(this).closest('.color-opt').toggleClass('is-on', this.checked); });

  // ---------- Checkout ----------
  (function () {
    var $form = $('[data-checkout-form]');
    if (!$form.length) return;
    var timer, xhr;
    function quote() {
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (xhr) xhr.abort();
        $('[data-checkout-summary]').addClass('is-updating');
        xhr = $.post(api('checkout-quote'), {
          city: $form.find('[name=city]').val(), region: $form.find('[name=region]').val(),
          shipping_method: $form.find('[name=shipping_method]:checked').val() || 'standard',
          payment_method: $form.find('[name=payment_method]:checked').val() || '', email: $form.find('[name=email]').val()
        }).done(function (r) {
          var t = r.totals, $s = $('[data-checkout-summary]');
          $s.find('[data-t=subtotal]').text(t.subtotal);
          $s.find('[data-t=gift]').text(t.gift); $s.find('[data-row=gift]').prop('hidden', !t.gift_raw);
          $s.find('[data-t=discount]').text(t.discount); $s.find('[data-row=discount]').prop('hidden', !t.discount_raw);
          $s.find('[data-t=shipping]').text(t.shipping);
          $s.find('[data-t=cod]').text(t.cod); $s.find('[data-row=cod]').prop('hidden', !t.cod_raw);
          $s.find('[data-t=total]').text(t.total);
          $s.find('[data-t=estimate]').html(t.estimate ? '<i class="bi bi-truck"></i> ' + $('<i>').text(t.estimate).html() : '');
          var html = r.options.map(function (o) {
            return '<label class="choice"><input type="radio" name="shipping_method" value="' + o.method + '"' + (o.method === r.selected ? ' checked' : '') + ' data-quote-trigger>' +
              '<span class="choice__body"><strong>' + $('<i>').text(o.name).html() + '</strong><small>' + $('<i>').text(o.estimate).html() + '</small></span><span class="choice__price">' + o.cost_label + '</span></label>';
          }).join('');
          $('[data-ship-options]').html(html || '<p class="text-muted mb-0">Delivery is not available to this location.</p>');
          var $cod = $('[data-pay-option=cod]');
          $cod.toggleClass('is-disabled', !r.cod_available).attr('title', r.cod_available ? '' : (r.cod_message || ''));
          if (!r.cod_available && $cod.find('input').is(':checked')) { $form.find('[name=payment_method]').not('[value=cod]').first().prop('checked', true); }
          $('[data-quote-errors]').html(r.errors.map(function (er) { return '<p>' + $('<i>').text(er).html() + '</p>'; }).join(''));
        }).always(function () { $('[data-checkout-summary]').removeClass('is-updating'); });
      }, 250);
    }
    $form.on('change', '[data-quote-trigger]', quote);
    $form.on('input', '[name=city]', quote);
    // Saved addresses
    $form.on('change', '[name=address_id]', function () {
      var a = $(this).data('address');
      if (a) { ['full_name', 'phone', 'address_line1', 'address_line2', 'city', 'region', 'postal_code'].forEach(function (k) { $form.find('[name=' + k + ']').val(a[k] || ''); }); }
      else if ($(this).is('[data-address-new]')) { ['full_name', 'address_line1', 'address_line2', 'city', 'postal_code'].forEach(function (k) { $form.find('[name=' + k + ']').val(''); }); $form.find('[name=region]').val(''); }
      quote();
    });
    $form.find('[name=address_id]:checked').trigger('change');
    $form.on('change', '[data-toggle-target]', function () { $($(this).data('toggle-target')).prop('hidden', !this.checked); });
    // Client-side validation & double-submit protection
    $form.on('submit', function (e) {
      var ok = true;
      $form.find('[required]').each(function () {
        var valid = this.type === 'checkbox' ? this.checked : $.trim(this.value) !== '';
        if (this.type === 'email' && valid) valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
        $(this).toggleClass('is-invalid', !valid);
        if (!valid && ok) { ok = false; this.focus(); }
      });
      if (!ok) { e.preventDefault(); notify('error', 'Please complete the highlighted fields.'); return; }
      if ($form.data('submitted')) { e.preventDefault(); return; }
      $form.data('submitted', true);
      $form.find('[data-place-order]').addClass('is-loading').prop('disabled', true);
    });
    window.addEventListener('pageshow', function (ev) { if (ev.persisted) { $form.data('submitted', false); $form.find('[data-place-order]').removeClass('is-loading').prop('disabled', false); } });
  })();

})(jQuery);
