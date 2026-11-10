/* =====================================================================
   Fahim Store - storefront JavaScript (jQuery + Bootstrap 5)
   ===================================================================== */
(function ($) {
  'use strict';
  var FS = window.FS || {};
  FS.url = function (p) { return (FS.base || '') + '/' + String(p || '').replace(/^\//, ''); };
  FS.money = function (n) {
    n = Number(n) || 0;
    var dec = Math.abs(n - Math.round(n)) < 0.005 ? 0 : 2;
    return FS.currency + ' ' + n.toLocaleString('en-PK', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  };
  FS.esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };

  // All AJAX POSTs carry the CSRF token
  $.ajaxSetup({ headers: { 'X-CSRF-Token': FS.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

  FS.toast = function (msg, type) {
    var id = 't' + Date.now();
    var icon = type === 'error' ? 'bi-exclamation-circle text-danger' : 'bi-check-circle-fill text-success';
    var $t = $('<div class="toast align-items-center border-0 shadow" role="status" aria-live="polite" id="' + id + '"><div class="d-flex"><div class="toast-body"><i class="bi ' + icon + ' me-2"></i>' + FS.esc(msg) + '</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>');
    $('#toastBox').append($t);
    var t = new bootstrap.Toast($t[0], { delay: 3200 });
    t.show();
    $t.on('hidden.bs.toast', function () { $t.remove(); });
  };

  FS.api = function (endpoint, data, method) {
    return $.ajax({ url: FS.url('api/' + endpoint), method: method || 'POST', data: data || {}, dataType: 'json' })
      .fail(function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Something went wrong. Please try again.';
        FS.toast(msg, 'error');
      });
  };

  FS.setCartCount = function (n) {
    $('.js-cart-count').text(n).toggleClass('d-none', !n);
  };

  /* ---------- Sticky header shadow + back to top ---------- */
  var $header = $('#siteHeader'), $btt = $('.back-to-top');
  $(window).on('scroll', function () {
    var y = window.scrollY;
    $header.toggleClass('scrolled', y > 10);
    $btt.toggleClass('show', y > 600);
  });
  $btt.on('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  document.documentElement.style.setProperty('--header-h', ($header.outerHeight() || 64) + 'px');

  /* ---------- Mega menu ---------- */
  $('.mega-root').on('mouseenter focusin', function () {
    var $r = $(this);
    $r.addClass('active').siblings().removeClass('active');
    $('#' + $r.data('target')).addClass('active').siblings().removeClass('active');
  });
  $('.mega-toggle').on('click', function () { $(this).closest('.mega-wrap').toggleClass('open'); });
  $(document).on('click', function (e) { if (!$(e.target).closest('.mega-wrap').length) $('.mega-wrap').removeClass('open'); });

  /* ---------- Delivery area ---------- */
  $('#zoneFilter').on('input', function () {
    var q = this.value.toLowerCase();
    $('.js-zone').each(function () { $(this).toggle($(this).data('label').toLowerCase().indexOf(q) > -1); });
  });
  $(document).on('click', '.js-zone', function () {
    var $z = $(this);
    document.cookie = 'fs_zone=' + $z.data('id') + ';path=' + (FS.base || '/') + ';max-age=' + (86400 * 180) + ';SameSite=Lax';
    $('#zoneLabel, .js-zone-text').text($z.data('label'));
    $('.js-zone').removeClass('active'); $z.addClass('active');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('zoneModal')).hide();
    FS.toast('Delivery area set to ' + $z.data('label'));
    $(document).trigger('fs:zone', [$z.data('id')]);
  });

  /* ---------- Smart search ---------- */
  var RECENT_KEY = 'fs_recent_searches';
  function recentSearches() { try { return JSON.parse(localStorage.getItem(RECENT_KEY)) || []; } catch (e) { return []; } }
  function saveRecent(q) {
    q = $.trim(q); if (q.length < 2) return;
    var r = recentSearches().filter(function (x) { return x.toLowerCase() !== q.toLowerCase(); });
    r.unshift(q);
    try { localStorage.setItem(RECENT_KEY, JSON.stringify(r.slice(0, 8))); } catch (e) {}
  }
  var popularCache = null;
  function renderIdle($box) {
    var recent = recentSearches();
    var draw = function (popular) {
      var h = '';
      if (recent.length) {
        h += '<div class="ss-group"><div class="ss-title d-flex justify-content-between">Recent searches <a href="#" class="js-clear-recent text-muted fw-normal text-none">Clear</a></div><div class="ss-chips">';
        recent.forEach(function (r) { h += '<a class="ss-chip" href="' + FS.url('search/') + '?q=' + encodeURIComponent(r) + '"><i class="bi bi-clock-history me-1"></i>' + FS.esc(r) + '</a>'; });
        h += '</div></div>';
      }
      if (popular && popular.length) {
        h += '<div class="ss-group"><div class="ss-title">Popular searches</div><div class="ss-chips">';
        popular.forEach(function (r) { h += '<a class="ss-chip" href="' + FS.url('search/') + '?q=' + encodeURIComponent(r) + '"><i class="bi bi-graph-up-arrow me-1"></i>' + FS.esc(r) + '</a>'; });
        h += '</div></div>';
      }
      $box.html(h).toggleClass('open', !!h);
    };
    if (popularCache) return draw(popularCache);
    $.getJSON(FS.url('api/search'), { popular: 1 }).done(function (d) { popularCache = d.popular || []; draw(popularCache); });
  }
  var searchTimer, searchXhr;
  $(document).on('focus input', '.js-search-input', function (e) {
    var $in = $(this), $form = $in.closest('form'), $box = $form.find('.search-suggest');
    var q = $.trim($in.val());
    clearTimeout(searchTimer);
    if (q.length < 2) { renderIdle($box); return; }
    if (e.type === 'focusin' && $box.children().length) { $box.addClass('open'); return; }
    searchTimer = setTimeout(function () {
      if (searchXhr) searchXhr.abort();
      searchXhr = $.getJSON(FS.url('api/search'), { q: q, suggest: 1 }).done(function (d) {
        var h = '';
        if (d.categories && d.categories.length) {
          h += '<div class="ss-group"><div class="ss-title">Categories</div>';
          d.categories.forEach(function (c) { h += '<a class="ss-item" href="' + c.url + '"><i class="bi bi-folder2-open text-brand"></i><span>' + c.label_html + '</span></a>'; });
          h += '</div>';
        }
        if (d.products && d.products.length) {
          h += '<div class="ss-group"><div class="ss-title">Products</div>';
          d.products.forEach(function (p) {
            h += '<a class="ss-item" href="' + p.url + '"><img src="' + p.image + '" alt="" loading="lazy"><span><span class="d-block">' + p.name_html + '</span><small class="text-muted">' + FS.esc(p.meta) + '</small></span><span class="ss-price">' + FS.esc(p.price) + '</span></a>';
          });
          h += '</div>';
        }
        if (!h) h = '<div class="p-3 text-muted">No matches for "<strong>' + FS.esc(q) + '</strong>". Try a different word.</div>';
        else if (d.total > (d.products || []).length) h += '<a class="ss-all" href="' + FS.url('search/') + '?q=' + encodeURIComponent(q) + '">See all ' + d.total + ' results <i class="bi bi-arrow-right"></i></a>';
        $box.html(h).addClass('open');
      });
    }, 180);
  });
  $(document).on('keydown', '.js-search-input', function (e) {
    var $box = $(this).closest('form').find('.search-suggest'), $items = $box.find('.ss-item'), $act = $items.filter('.active'), i = $items.index($act);
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      i = e.key === 'ArrowDown' ? Math.min(i + 1, $items.length - 1) : Math.max(i - 1, 0);
      $items.removeClass('active').eq(i).addClass('active');
    } else if (e.key === 'Enter' && $act.length) {
      e.preventDefault(); window.location = $act.attr('href');
    } else if (e.key === 'Escape') { $box.removeClass('open'); }
  });
  $(document).on('submit', '.search-box', function () { saveRecent($(this).find('.js-search-input').val()); });
  $(document).on('click', '.js-clear-recent', function (e) { e.preventDefault(); try { localStorage.removeItem(RECENT_KEY); } catch (x) {} $('.search-suggest').removeClass('open').empty(); });
  $(document).on('click', function (e) { if (!$(e.target).closest('.search-box').length) $('.search-suggest:not(.static)').removeClass('open'); });
  $('#searchOverlay').on('shown.bs.modal', function () { $(this).find('.js-search-input').trigger('focus'); });
  if (/[?&]q=/.test(location.search) && $('.js-search-input').val()) saveRecent($('.js-search-input').val());

  /* ---------- Quantity boxes ---------- */
  $(document).on('click', '.qty-box [data-step]', function () {
    var $in = $(this).closest('.qty-box').find('input');
    var min = parseInt($in.attr('min') || '1', 10), max = parseInt($in.attr('max') || '9999', 10);
    var v = (parseInt($in.val(), 10) || 0) + parseInt($(this).data('step'), 10);
    $in.val(Math.max(min, Math.min(max, v))).trigger('change');
  });

  /* ---------- Add to cart ---------- */
  $(document).on('click', '.js-add-cart', function () {
    var $b = $(this);
    var qty = parseInt($b.data('qty-input') ? $($b.data('qty-input')).val() : ($b.data('qty') || 1), 10) || 1;
    var variation = $b.data('variation-input') ? $($b.data('variation-input') + ':checked').val() : ($b.data('variation') || 0);
    if ($b.data('variation-input') && !variation) { FS.toast('Please select an option first.', 'error'); return; }
    var html = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border fs-spinner"></span>');
    FS.api('cart', { action: 'add', product_id: $b.data('id'), variation_id: variation || 0, quantity: qty })
      .done(function (d) {
        if (d.ok) { FS.setCartCount(d.count); FS.toast(d.message || 'Added to cart'); if ($b.data('buy-now')) location.href = FS.url('checkout/'); }
      })
      .always(function () { $b.prop('disabled', false).html(html); });
  });

  /* ---------- Wishlist ---------- */
  $(document).on('click', '.js-wishlist', function () {
    var $b = $(this);
    if (!FS.loggedIn) { location.href = FS.url('login/') + '?next=' + encodeURIComponent(location.pathname); return; }
    FS.api('wishlist', { action: 'toggle', product_id: $b.data('id') }).done(function (d) {
      if (!d.ok) return;
      $('.js-wishlist[data-id="' + $b.data('id') + '"]').toggleClass('active', d.added).attr('aria-pressed', d.added)
        .find('i').attr('class', d.added ? 'bi bi-heart-fill' : 'bi bi-heart');
      $('.js-wish-count').text(d.count).toggleClass('d-none', !d.count);
      FS.toast(d.added ? 'Added to wishlist' : 'Removed from wishlist');
      if (!d.added && $b.closest('.js-wish-page').length) $b.closest('.col').fadeOut(200);
    });
  });

  /* ---------- Newsletter ---------- */
  $(document).on('submit', '.js-newsletter', function (e) {
    e.preventDefault();
    var $f = $(this), $msg = $f.parent().find('.js-newsletter-msg').first();
    FS.api('newsletter', { email: $f.find('[name=email]').val() }).done(function (d) {
      $msg.text(d.message || '').removeClass('text-danger');
      if (d.ok) $f[0].reset();
    }).fail(function (x) { $msg.text((x.responseJSON && x.responseJSON.error) || '').addClass('text-danger'); });
  });

  /* ---------- Product gallery ---------- */
  $(document).on('click', '.pd-thumbs button', function () {
    $(this).addClass('active').siblings().removeClass('active');
    $('#pdMainImg').attr('src', $(this).data('src'));
  });

  window.FS = FS;
})(jQuery);
