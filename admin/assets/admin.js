/* Fahim Store admin JS */
(function ($) {
  'use strict';
  var A = window.FSA || {};
  $.ajaxSetup({ headers: { 'X-CSRF-Token': A.csrf, 'X-Requested-With': 'XMLHttpRequest' } });
  A.money = function (n) { n = Number(n) || 0; var d = Math.abs(n - Math.round(n)) < 0.005 ? 0 : 2; return A.currency + ' ' + n.toLocaleString('en-PK', { minimumFractionDigits: d, maximumFractionDigits: d }); };
  A.esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
  A.toast = function (msg, type) {
    var $t = $('<div class="toast align-items-center text-bg-' + (type === 'error' ? 'danger' : 'dark') + ' border-0" role="status"><div class="d-flex"><div class="toast-body">' + A.esc(msg) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
    $('#toastBox').append($t); new bootstrap.Toast($t[0], { delay: 3500 }).show(); $t.on('hidden.bs.toast', function () { $t.remove(); });
  };
  A.post = function (url, data) {
    return $.ajax({ url: A.admin + url, method: 'POST', data: data, dataType: 'json' }).fail(function (x) {
      A.toast((x.responseJSON && x.responseJSON.error) || 'Request failed', 'error');
    });
  };

  // Image preview before upload
  $(document).on('change', '.js-img-input', function () {
    var f = this.files && this.files[0], $p = $($(this).data('preview'));
    if (f && $p.length) { var r = new FileReader(); r.onload = function (e) { $p.attr('src', e.target.result); }; r.readAsDataURL(f); }
  });
  $(document).on('change', '.js-multi-img', function () {
    var $box = $($(this).data('preview')).empty();
    Array.prototype.forEach.call(this.files || [], function (f) {
      var r = new FileReader(); r.onload = function (e) { $box.append('<img src="' + e.target.result + '" class="a-img-preview me-2 mb-2" alt="">'); }; r.readAsDataURL(f);
    });
  });
  // Character counters + SERP preview
  $(document).on('input', '.js-count', function () {
    var max = +$(this).data('max'), n = this.value.length, $c = $(this).siblings('.js-counter');
    if (!$c.length) $c = $('<div class="form-text js-counter"></div>').insertAfter(this);
    $c.text(n + ' / ' + max + ' characters').toggleClass('text-danger', n > max);
    if (this.name === 'seo[seo_title]') $('.js-serp-title').text(this.value || 'Title');
    if (this.name === 'seo[meta_description]') $('.js-serp-desc').text(this.value || 'Meta description…');
  }).find('.js-count').trigger('input');
  $('.js-count').trigger('input');
  // Slug auto-fill
  $(document).on('input', '.js-slug-source', function () {
    var $t = $($(this).data('slug'));
    if ($t.length && !$t.data('touched')) $t.val(this.value.toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
  });
  $(document).on('input', '.js-slug', function () { $(this).data('touched', true); });
  // Confirm
  $(document).on('submit', 'form[data-confirm]', function (e) { if (!confirm($(this).data('confirm'))) e.preventDefault(); });
  $(document).on('click', '[data-confirm]:not(form)', function (e) { if (!confirm($(this).data('confirm'))) e.preventDefault(); });
  // Select all checkboxes
  $(document).on('change', '.js-check-all', function () { $($(this).data('target')).prop('checked', this.checked); });

  // Notifications (poll every 45s)
  var lastCount = null;
  function loadNotifs() {
    if (!$('#notifBtn').length) return;
    $.getJSON(A.admin + 'ajax/notifications.php').done(function (d) {
      if (!d.ok) return;
      $('.js-notif-count').text(d.unread).toggleClass('d-none', !d.unread);
      var h = '';
      d.items.forEach(function (n) {
        h += '<a class="notif-item' + (n.is_read ? '' : ' unread') + '" href="' + A.admin + 'notifications.php?open=' + n.id + '"><strong>' + A.esc(n.title) + '</strong><br><span class="text-muted">' + A.esc(n.message || '') + '</span><br><small class="text-muted">' + A.esc(n.ago) + '</small></a>';
      });
      $('.js-notif-list').html(h || '<div class="p-3 small text-muted">No notifications</div>');
      if (lastCount !== null && d.unread > lastCount) {
        A.toast('🔔 ' + (d.items[0] ? d.items[0].title : 'New notification'));
        try { new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=').play(); } catch (e) {}
      }
      lastCount = d.unread;
    });
  }
  loadNotifs();
  setInterval(loadNotifs, 45000);

  // Generic product picker (search box -> add to list)
  $(document).on('input', '.js-product-picker', function () {
    var $in = $(this), $res = $($in.data('results')), q = $.trim(this.value);
    clearTimeout($in.data('t'));
    if (q.length < 2) { $res.empty(); return; }
    $in.data('t', setTimeout(function () {
      $.getJSON(A.admin + 'ajax/products.php', { q: q, limit: 8 }).done(function (d) {
        var h = '';
        (d.items || []).forEach(function (p) { h += '<button type="button" class="list-group-item list-group-item-action js-pick" data-id="' + p.product_id + '" data-vid="' + p.variation_id + '" data-name="' + A.esc(p.label) + '" data-price="' + p.unit_price + '" data-cost="' + p.cost_price + '"><img src="' + p.thumb + '" class="a-thumb me-2" alt="">' + A.esc(p.label) + ' <small class="text-muted">' + A.esc(p.sku) + ' · stock ' + p.stock + '</small></button>'; });
        $res.html(h || '<div class="list-group-item small text-muted">No products found</div>');
      });
    }, 200));
  });

  window.FSA = A;
})(jQuery);
