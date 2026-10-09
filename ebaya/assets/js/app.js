/* Ebaya storefront interactions (jQuery + Swiper + SweetAlert2). */
(function ($) {
  'use strict';
  var EB = window.EB || {};
  var reduced = EB.reducedMotion;
  var animOff = document.body.getAttribute('data-anim') === 'none' || reduced;
  var api = function (path) { return EB.base + '/ajax/' + path; };

  $.ajaxSetup({ headers: { 'X-CSRF-Token': EB.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

  var toast = function (icon, title) {
    if (!window.Swal) return alert(title);
    Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: 2600, timerProgressBar: true });
  };
  var failMsg = function (xhr) {
    return (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.';
  };

  /* ---------------------------------------------------------- Header */
  var $header = $('#siteHeader');
  var lastY = 0;
  var onScroll = function () {
    var y = window.scrollY;
    $header.toggleClass('is-scrolled', y > 40);
    if (document.body.classList.contains('header-sticky')) {
      $header.toggleClass('is-hidden', y > 400 && y > lastY + 4 && !$('.offcanvas.show').length);
      if (y < lastY - 4) $header.removeClass('is-hidden');
    }
    lastY = y;
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  var setHeaderH = function () { document.documentElement.style.setProperty('--header-h', ($header.outerHeight() || 90) + 'px'); };
  setHeaderH(); $(window).on('resize', setHeaderH);

  // Announcement rotator
  $('[data-rotate]').each(function () {
    var $items = $(this).children();
    if ($items.length < 2) return;
    var i = 0;
    setInterval(function () {
      var $cur = $items.eq(i).removeClass('is-active').addClass('is-leaving');
      setTimeout(function () { $cur.removeClass('is-leaving'); }, 650);
      i = (i + 1) % $items.length;
      $items.eq(i).addClass('is-active');
    }, 4500);
  });

  /* ---------------------------------------------------------- Search */
  var $overlay = $('#searchOverlay'), searchTimer, searchXhr;
  $(document).on('click', '[data-search-open]', function () {
    $overlay.addClass('is-open').attr('aria-hidden', 'false');
    setTimeout(function () { $('#searchInput').trigger('focus'); }, 150);
  });
  $(document).on('click', '[data-search-close]', function () { $overlay.removeClass('is-open').attr('aria-hidden', 'true'); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') $overlay.removeClass('is-open'); });
  $('#searchInput').on('input', function () {
    var q = this.value.trim();
    clearTimeout(searchTimer);
    if (q.length < 2) { $('#searchResults').empty(); return; }
    searchTimer = setTimeout(function () {
      if (searchXhr) searchXhr.abort();
      $('#searchResults').html('<div class="skeleton-line w-50"></div><div class="skeleton-line w-75"></div>');
      searchXhr = $.getJSON(api('search'), { q: q }).done(function (r) { $('#searchResults').html(r.html); });
    }, 260);
  });

  /* ---------------------------------------------------------- Reveal on scroll */
  var revealAll = function (root) {
    var els = (root || document).querySelectorAll('[data-reveal]:not(.is-visible)');
    if (animOff || !('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('is-visible'); }); return; }
    els.forEach(function (el) { io.observe(el); });
  };
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 }) : null;
  revealAll();

  /* ---------------------------------------------------------- Parallax (rAF, transform only) */
  if (!reduced && document.body.getAttribute('data-parallax') === '1') {
    var pEls = [].slice.call(document.querySelectorAll('.is-parallax img, .parallax-img, .parallax-bg'));
    var ticking = false;
    var update = function () {
      var vh = window.innerHeight;
      pEls.forEach(function (el) {
        var r = el.parentElement.getBoundingClientRect();
        if (r.bottom < 0 || r.top > vh) return;
        var progress = (r.top + r.height / 2 - vh / 2) / vh;
        el.style.transform = 'translate3d(0,' + (progress * -60).toFixed(1) + 'px,0)' + (el.classList.contains('parallax-img') ? ' scale(1.08)' : '');
      });
      ticking = false;
    };
    if (pEls.length && window.innerWidth > 767) {
      window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(update); ticking = true; } }, { passive: true });
      update();
    }
  }

  /* ---------------------------------------------------------- Carousels */
  var initCarousels = function (root) {
    if (!window.Swiper) return;
    $(root || document).find('.hero-swiper').each(function () {
      var delay = parseInt(this.getAttribute('data-autoplay'), 10);
      var effect = this.getAttribute('data-effect');
      var count = this.querySelectorAll('.swiper-slide').length;
      new Swiper(this, {
        loop: count > 1, speed: reduced ? 0 : 1200,
        effect: effect === 'slide' ? 'slide' : 'fade', fadeEffect: { crossFade: true },
        autoplay: delay && count > 1 && !reduced ? { delay: delay, disableOnInteraction: false, pauseOnMouseEnter: true } : false,
        pagination: { el: this.querySelector('.hero-dots'), clickable: true },
        navigation: { prevEl: this.querySelector('.hero-prev'), nextEl: this.querySelector('.hero-next') },
        keyboard: { enabled: true }, a11y: { enabled: true }
      });
    });
    $(root || document).find('.product-carousel').each(function () {
      var $sec = $(this).closest('section, .pdp-strip');
      new Swiper(this, {
        slidesPerView: 'auto', spaceBetween: 24, speed: reduced ? 0 : 700, grabCursor: true,
        navigation: { prevEl: $sec.find('.car-prev')[0], nextEl: $sec.find('.car-next')[0] },
        scrollbar: { el: this.querySelector('.swiper-scrollbar'), draggable: true },
        breakpoints: { 0: { spaceBetween: 12 }, 768: { spaceBetween: 24 } }, a11y: { enabled: true }
      });
    });
    $(root || document).find('.eb-carousel:not(.product-carousel)').each(function () {
      var per = parseInt(this.getAttribute('data-per-view'), 10) || 3;
      new Swiper(this, { slidesPerView: 1.25, spaceBetween: 16, speed: reduced ? 0 : 700, grabCursor: true, breakpoints: { 768: { slidesPerView: 2.2, spaceBetween: 20 }, 1200: { slidesPerView: per, spaceBetween: 24 } } });
    });
    $(root || document).find('.testimonial-swiper').each(function () {
      new Swiper(this, { loop: this.querySelectorAll('.swiper-slide').length > 1, speed: reduced ? 0 : 900, autoplay: reduced ? false : { delay: 6500 }, pagination: { el: this.querySelector('.swiper-pagination'), clickable: true }, autoHeight: true });
    });
  };
  initCarousels();

  /* ---------------------------------------------------------- Product gallery, zoom, lightbox */
  if (window.Swiper && document.querySelector('.pdp-main')) {
    var thumbs = document.querySelector('.pdp-thumbs') ? new Swiper('.pdp-thumbs', { slidesPerView: 'auto', spaceBetween: 10, watchSlidesProgress: true }) : null;
    var main = new Swiper('.pdp-main', { speed: reduced ? 0 : 600, spaceBetween: 0, pagination: { el: '.pdp-main .swiper-pagination', clickable: true }, thumbs: thumbs ? { swiper: thumbs } : undefined, keyboard: { enabled: true } });
    var lb = null;
    var openLightbox = function (i) {
      var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('lightbox'));
      modal.show();
      $('#lightbox').one('shown.bs.modal', function () {
        if (!lb) lb = new Swiper('.lb-swiper', { zoom: true, navigation: { nextEl: '.lb-swiper .swiper-button-next', prevEl: '.lb-swiper .swiper-button-prev' }, pagination: { el: '.lb-swiper .swiper-pagination', type: 'fraction' }, keyboard: { enabled: true } });
        lb.slideTo(i, 0);
      });
    };
    $(document).on('click', '[data-lightbox-open]', function () { openLightbox(main.activeIndex); });
    $(document).on('click', '[data-zoom]', function () {
      if (window.matchMedia('(hover: none)').matches) openLightbox(parseInt($(this).data('lightbox-index'), 10) || 0);
    });
    if (!reduced) {
      $(document).on('mousemove', '[data-zoom]', function (e) {
        var r = this.getBoundingClientRect();
        this.style.setProperty('--zx', ((e.clientX - r.left) / r.width * 100) + '%');
        this.style.setProperty('--zy', ((e.clientY - r.top) / r.height * 100) + '%');
        this.classList.add('is-zooming');
      }).on('mouseleave', '[data-zoom]', function () { this.classList.remove('is-zooming'); });
    }
    $(document).on('dblclick', '[data-zoom]', function () { openLightbox(parseInt($(this).data('lightbox-index'), 10) || 0); });
  }

  /* ---------------------------------------------------------- Buy box: variant selection */
  var money = function (n) { return EB.currency + ' ' + Math.round(n).toLocaleString('en-US'); };
  var initBuybox = function ($form) {
    var matrix = $form.data('matrix') || { options: {}, variants: [] };
    var codes = Object.keys(matrix.options || {});
    var track = String($form.data('track')) === '1';
    var sel = function () {
      var s = {};
      codes.forEach(function (c) { var v = $form.find('input[name="opt_' + c + '"]:checked').val(); if (v) s[c] = parseInt(v, 10); });
      return s;
    };
    var matches = function (v, s) { return Object.keys(s).every(function (c) { return v.values[c] === s[c]; }); };
    var refresh = function () {
      var s = sel();
      // Mark option values that cannot be combined with the other current selections.
      codes.forEach(function (c) {
        $form.find('input[name="opt_' + c + '"]').each(function () {
          var trial = $.extend({}, s); trial[c] = parseInt(this.value, 10);
          var ok = matrix.variants.some(function (v) { return matches(v, trial) && v.available; });
          $(this).closest('label').toggleClass('is-unavailable', !ok);
        });
        var $chk = $form.find('input[name="opt_' + c + '"]:checked');
        $form.find('[data-option="' + c + '"] [data-option-label]').text($chk.length ? $chk.data('label') : 'Select');
      });
      var complete = Object.keys(s).length === codes.length;
      var v = complete ? matrix.variants.filter(function (x) { return matches(x, s); })[0] : null;
      $form.find('[name=variant_id]').val(v ? v.id : (codes.length ? '' : $form.find('[name=variant_id]').val()));
      var $stock = $form.find('[data-stock]');
      if (v) {
        $form.find('[data-price]').html(v.price < v.regular ? '<span class="price-sale">' + money(v.price) + '</span> <del class="price-old">' + money(v.regular) + '</del>' : '<span>' + money(v.price) + '</span>');
        if (!v.available) $stock.html('<span class="text-muted"><i class="bi bi-x-circle"></i> This combination is sold out</span>');
        else if (track && v.stock !== null && v.stock <= 3) $stock.html('<span class="low"><i class="bi bi-hourglass-split"></i> Only ' + v.stock + ' left</span>');
        else if (track) $stock.html('<span><i class="bi bi-check2-circle"></i> In stock, ready to ship</span>');
        $form.find('[data-add-btn],[data-buy-now]').prop('disabled', !v.available);
        $form.find('[name=qty]').attr('max', track && v.stock ? Math.min(10, v.stock) : 10);
      } else if (codes.length) {
        $form.find('[data-add-btn],[data-buy-now]').prop('disabled', false);
      }
    };
    $form.on('change', 'input[name^="opt_"]', refresh);
    refresh();
  };
  $('[data-buybox]').each(function () { initBuybox($(this)); });

  $(document).on('click', '[data-qty]', function () {
    var $i = $(this).siblings('input');
    var v = (parseInt($i.val(), 10) || 1) + parseInt($(this).data('qty'), 10);
    var max = parseInt($i.attr('max'), 10) || 10;
    $i.val(Math.max(1, Math.min(max, v)));
  });

  var updateCount = function (n) {
    $('[data-cart-count]').text(n).prop('hidden', !n).removeClass('bump');
    setTimeout(function () { $('[data-cart-count]').addClass('bump'); }, 10);
  };
  var renderCart = function (r) {
    if (r.mini) $('#miniCartBody').html(r.mini);
    if (r.page && $('#cartPage').length) $('#cartPage').html(r.page);
    if (typeof r.count !== 'undefined') updateCount(r.count);
  };

  var addToCart = function ($form, buyNow) {
    var missing = [];
    $form.find('[data-option]').each(function () {
      if (!$(this).find('input:checked').length) missing.push($(this).find('legend span').first().text().split(':')[0]);
    });
    if (missing.length) {
      $form.find('[data-option]').each(function () { if (!$(this).find('input:checked').length) $(this).addClass('shake'); });
      setTimeout(function () { $form.find('.shake').removeClass('shake'); }, 600);
      return toast('warning', 'Please select ' + missing.join(' and ').toLowerCase());
    }
    var $btn = buyNow ? $form.find('[data-buy-now]') : $form.find('[data-add-btn]');
    $btn.addClass('is-loading');
    var data = $form.serializeArray().filter(function (x) { return x.name.indexOf('opt_') !== 0; });
    data.push({ name: 'action', value: 'add' });
    $.post(api('cart'), $.param(data)).done(function (r) {
      if (!r.ok) return toast('error', r.message);
      renderCart(r);
      if (buyNow) { window.location.href = EB.base + '/checkout'; return; }
      bootstrap.Modal.getInstance(document.getElementById('quickView'))?.hide();
      bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('miniCart')).show();
    }).fail(function (x) { toast('error', failMsg(x)); }).always(function () { $btn.removeClass('is-loading'); });
  };
  $(document).on('submit', '[data-buybox]', function (e) { e.preventDefault(); addToCart($(this), false); });
  $(document).on('click', '[data-buy-now]', function () { addToCart($(this).closest('form'), true); });

  // Simple product "Add to bag" from a card
  $(document).on('click', '[data-add-simple]', function () {
    var $b = $(this).addClass('is-loading');
    $.post(api('cart'), { action: 'add', product_id: $b.data('add-simple'), variant_id: $b.data('variant'), qty: 1 }).done(function (r) {
      if (!r.ok) return toast('error', r.message);
      renderCart(r);
      bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('miniCart')).show();
    }).fail(function (x) { toast('error', failMsg(x)); }).always(function () { $b.removeClass('is-loading'); });
  });

  /* ---------------------------------------------------------- Mini cart / cart page */
  $('#miniCart').on('show.bs.offcanvas', function () {
    $.getJSON(api('cart'), { action: 'render' }).done(renderCart);
  });
  var cartTimer;
  var cartUpdate = function ($line, qty) {
    $line.addClass('is-busy');
    $.post(api('cart'), { action: qty > 0 ? 'update' : 'remove', item_id: $line.data('item'), qty: qty }).done(function (r) {
      if (!r.ok) toast('error', r.message);
      if (r.ok) renderCart(r); else $.getJSON(api('cart'), { action: 'render' }).done(renderCart);
    }).fail(function (x) { toast('error', failMsg(x)); $line.removeClass('is-busy'); });
  };
  $(document).on('click', '[data-cart-qty]', function () {
    var $line = $(this).closest('[data-item]'), $i = $line.find('[data-cart-input]');
    var v = Math.max(1, Math.min(parseInt($i.attr('max'), 10) || 10, (parseInt($i.val(), 10) || 1) + parseInt($(this).data('cart-qty'), 10)));
    $i.val(v);
    clearTimeout(cartTimer);
    cartTimer = setTimeout(function () { cartUpdate($line, v); }, 350);
  });
  $(document).on('change', '[data-cart-input]', function () { cartUpdate($(this).closest('[data-item]'), parseInt(this.value, 10) || 1); });
  $(document).on('click', '[data-cart-remove]', function () { cartUpdate($(this).closest('[data-item]'), 0); });
  $(document).on('submit', '[data-coupon-form]', function (e) {
    e.preventDefault();
    var $f = $(this);
    $.post(api('cart'), { action: 'coupon', code: $f.find('[name=code]').val() }).done(function (r) {
      if (!r.ok) return toast('error', r.message);
      renderCart(r); toast('success', r.message);
    }).fail(function (x) { toast('error', failMsg(x)); });
  });
  $(document).on('click', '[data-coupon-remove]', function () {
    $.post(api('cart'), { action: 'remove_coupon' }).done(renderCart);
  });

  /* ---------------------------------------------------------- Wishlist */
  $(document).on('click', '[data-wishlist]', function (e) {
    e.preventDefault();
    var id = $(this).data('wishlist');
    $.post(api('wishlist'), { product_id: id }).done(function (r) {
      if (!r.ok) return toast('error', r.message);
      var $all = $('[data-wishlist="' + id + '"]');
      $all.toggleClass('is-active', r.added).attr('aria-pressed', r.added ? 'true' : 'false').removeClass('pop');
      $all.find('i').attr('class', 'bi bi-heart' + (r.added ? '-fill' : ''));
      setTimeout(function () { $all.addClass('pop'); }, 10);
      $('[data-wish-count]').text(r.count).prop('hidden', !r.count);
      toast('success', r.message);
    }).fail(function (x) { toast('error', failMsg(x)); });
  });

  /* ---------------------------------------------------------- Quick view */
  $(document).on('click', '[data-quickview]', function () {
    var slug = $(this).data('quickview');
    var $body = $('#quickViewBody').html('<div class="p-5"><div class="skeleton-line"></div><div class="skeleton-line w-75"></div><div class="skeleton-line w-50"></div></div>');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('quickView')).show();
    $.getJSON(api('quickview'), { slug: slug }).done(function (r) {
      $body.html(r.html);
      $body.find('[data-buybox]').each(function () { initBuybox($(this)); });
      if (window.Swiper) new Swiper($body.find('.qv-swiper')[0], { pagination: { el: $body.find('.swiper-pagination')[0], clickable: true } });
    }).fail(function (x) { $body.html('<p class="p-5">' + failMsg(x) + '</p>'); });
  });

  /* ---------------------------------------------------------- Newsletter */
  $(document).on('submit', '[data-newsletter]', function (e) {
    e.preventDefault();
    var $f = $(this), email = $f.find('[name=email]').val().trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return toast('warning', 'Please enter a valid email address.');
    if (!$f.find('[name=consent]').is(':checked')) return toast('warning', 'Please tick the consent box to subscribe.');
    var $b = $f.find('button').addClass('is-loading');
    $.post(api('newsletter'), $f.serialize()).done(function (r) {
      if (!r.ok) return toast('error', r.message);
      $f[0].reset();
      Swal.fire({ icon: 'success', title: 'Thank you', text: r.message, confirmButtonText: 'Continue' });
    }).fail(function (x) { toast('error', failMsg(x)); }).always(function () { $b.removeClass('is-loading'); });
  });

  /* ---------------------------------------------------------- Reviews */
  $(document).on('submit', '[data-review-form]', function (e) {
    e.preventDefault();
    var $f = $(this);
    $.post(api('review'), $f.serialize()).done(function (r) {
      if (!r.ok) return toast('error', r.message);
      $f.replaceWith('<p class="alert alert-success">' + r.message + '</p>');
    }).fail(function (x) { toast('error', failMsg(x)); });
  });

  /* ---------------------------------------------------------- Checkout */
  var $co = $('[data-checkout]');
  if ($co.length) {
    var quoteTimer, quoteXhr;
    var quote = function () {
      if (quoteXhr) quoteXhr.abort();
      $co.addClass('checkout-loading');
      quoteXhr = $.post(api('checkout-quote'), {
        city: $co.find('[name=city]').val(), province: $co.find('[name=province]').val(),
        method: $co.find('[name=method]:checked').val() || 'standard', payment: $co.find('[name=payment]:checked').val() || '',
        email: $co.find('[name=email]').val()
      }).done(function (r) {
        if (!r.ok) return;
        $('#shippingOptions').html(r.shipping); $('#paymentOptions').html(r.payment); $('#checkoutTotals').html(r.totals);
      }).always(function () { $co.removeClass('checkout-loading'); });
    };
    $co.on('input change', '[data-shipping-input]', function () { clearTimeout(quoteTimer); quoteTimer = setTimeout(quote, this.type === 'radio' ? 0 : 450); });
    quote();
    $co.on('click', '[data-fill-address]', function () {
      var a = $(this).data('fill-address');
      Object.keys(a).forEach(function (k) { $co.find('[name=' + k + ']').val(a[k] || ''); });
      quote();
    });
    $co.on('change', '[data-toggle-target]', function () { $($(this).data('toggle-target')).prop('hidden', !this.checked); });
    $co.on('submit', function (e) {
      var ok = true;
      $co.find('[required]').each(function () {
        var bad = this.type === 'checkbox' ? !this.checked : (this.type === 'radio' ? !$co.find('[name="' + this.name + '"]:checked').length : !this.value.trim());
        $(this).toggleClass('is-invalid', bad);
        if (bad) ok = false;
      });
      if (!ok) {
        e.preventDefault();
        toast('warning', 'Please complete the highlighted fields.');
        var $first = $co.find('.is-invalid').first();
        if ($first.length) $('html,body').animate({ scrollTop: $first.offset().top - 140 }, reduced ? 0 : 400);
        return;
      }
      // Prevent double submission; the server also rejects duplicates via the checkout token.
      $co.find('[data-place-order]').addClass('is-loading').prop('disabled', true);
    });
  }

  /* ---------------------------------------------------------- Confirmations */
  $(document).on('submit', 'form[data-confirm]', function (e) {
    var f = this;
    if (f.dataset.confirmed) return;
    e.preventDefault();
    Swal.fire({ title: f.dataset.confirm, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, continue' }).then(function (res) {
      if (res.isConfirmed) { f.dataset.confirmed = '1'; f.submit(); }
    });
  });
})(jQuery);
