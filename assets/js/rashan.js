/* =====================================================================
   Fahim Store - Monthly Rashan builder
   Instant client-side totals + debounced server verification (AJAX).
   ===================================================================== */
(function ($) {
  'use strict';
  var R = window.RASHAN || { products: {} };
  var KEY = 'fs_rashan_draft';
  // state.items: { "pid-vid": qty }
  var state = { items: {}, budget: R.defaultBudget || 0, name: '', notes: '', listId: 0 };

  function load() {
    try { var s = JSON.parse(localStorage.getItem(KEY)); if (s && s.items) state = $.extend(state, s); } catch (e) {}
  }
  function persist() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }

  function opt(pid, vid) {
    var p = R.products[pid]; if (!p) return null;
    for (var i = 0; i < p.opts.length; i++) if (+p.opts[i].v === +vid) return p.opts[i];
    return null;
  }
  function itemsArray() {
    return Object.keys(state.items).map(function (k) { var a = k.split('-'); return { p: +a[0], v: +a[1], q: state.items[k] }; })
      .filter(function (x) { return x.q > 0; });
  }
  function selectedVid($row) {
    var $s = $row.find('.js-ri-var');
    return $s.length ? +$s.val() : +(R.products[$row.data('id')].opts[0].v);
  }
  function keyFor($row) { return $row.data('id') + '-' + selectedVid($row); }

  /* ---------- Rendering ---------- */
  function paintRow($row) {
    var pid = $row.data('id'), vid = selectedVid($row), o = opt(pid, vid), q = state.items[pid + '-' + vid] || 0;
    if (o) { $row.find('.js-unit-price').text(FS.money(o.price)); $row.find('.js-unit-name').text(o.name); }
    $row.toggleClass('selected', q > 0);
    $row.find('.js-ri-add').toggleClass('d-none', q > 0);
    $row.find('.js-ri-qty').toggleClass('d-none', q === 0);
    $row.find('.js-ri-input').val(q || 1).attr('max', o ? Math.min(999, o.stock) : 999);
  }
  function paintAll() { $('.rashan-item').each(function () { paintRow($(this)); }); summary(); }

  function summary() {
    var total = 0, units = 0, count = 0, html = '';
    itemsArray().forEach(function (it) {
      var p = R.products[it.p], o = opt(it.p, it.v); if (!p || !o) return;
      var line = o.price * it.q; total += line; units += it.q; count++;
      html += '<div class="sl-row"><span>' + FS.esc(p.name) + (p.opts.length > 1 ? ' <small class="text-muted">(' + FS.esc(o.name) + ')</small>' : '') + ' × ' + it.q + '</span><strong>' + FS.money(line) + '</strong></div>';
    });
    var budget = +state.budget || 0, remaining = budget - total, pct = budget > 0 ? Math.min(100, total / budget * 100) : 0;
    $('.js-sel-count').text(count);
    $('.js-qty-total').text(units);
    $('.js-total').text(FS.money(total));
    $('.js-budget').text(FS.money(budget));
    $('.js-remaining').text((remaining < 0 ? '- ' : '') + FS.money(Math.abs(remaining)));
    $('.js-remaining-label').text(remaining < 0 ? 'Over budget by' : 'Remaining');
    $('.budget-figures .remaining').toggleClass('over', remaining < 0).toggleClass('text-success', remaining >= 0);
    $('.js-remaining-mini').text(budget ? (remaining < 0 ? 'Over by ' : 'Left: ') + FS.money(Math.abs(remaining)) : '');
    $('.js-budget-bar').css('width', pct + '%').toggleClass('bg-danger', remaining < 0).toggleClass('bg-success', remaining >= 0 && pct < 90).toggleClass('bg-warning', remaining >= 0 && pct >= 90);
    $('.js-selected-list').html(html || '<p class="small text-muted mb-0">Your selected items will appear here.</p>');
    persist();
    verify();
  }

  /* ---------- Server verification (authoritative prices/stock) ---------- */
  var vTimer;
  function verify() {
    clearTimeout(vTimer);
    vTimer = setTimeout(function () {
      var items = itemsArray();
      $('.js-ri-problem').text('');
      if (!items.length) return;
      $.post(FS.url('api/rashan'), { action: 'calc', items: JSON.stringify(items), budget: state.budget }, null, 'json').done(function (d) {
        if (!d.ok) return;
        $('.js-total').text(d.total_fmt);
        d.lines.forEach(function (l) {
          var o = opt(l.p, l.v); if (o && Math.abs(o.price - l.unit_price) > 0.001) o.price = l.unit_price; // price changed on server
          if (l.problem) $('.rashan-item[data-id="' + l.p + '"] .js-ri-problem').text(l.problem);
        });
      });
    }, 400);
  }

  function setQty($row, q) {
    var o = opt($row.data('id'), selectedVid($row));
    q = Math.max(0, Math.min(o ? Math.min(999, o.stock) : 999, q | 0));
    if (o && q === o.stock && o.stock < 999 && q > 0) FS.toast('Only ' + o.stock + ' available', 'error');
    if (q > 0) state.items[keyFor($row)] = q; else delete state.items[keyFor($row)];
    paintRow($row); summary();
  }

  /* ---------- Events ---------- */
  $(document).on('click', '.js-ri-add', function () { setQty($(this).closest('.rashan-item'), 1); });
  $(document).on('click', '.js-ri-plus', function () { var $r = $(this).closest('.rashan-item'); setQty($r, (state.items[keyFor($r)] || 0) + 1); });
  $(document).on('click', '.js-ri-minus', function () { var $r = $(this).closest('.rashan-item'); setQty($r, (state.items[keyFor($r)] || 0) - 1); });
  $(document).on('change', '.js-ri-input', function () { setQty($(this).closest('.rashan-item'), parseInt(this.value, 10) || 0); });
  $(document).on('change', '.js-ri-var', function () { paintRow($(this).closest('.rashan-item')); });
  $('#rBudget').on('input', function () { state.budget = Math.max(0, parseFloat(this.value) || 0); summary(); });
  $('#rName').on('input', function () { state.name = this.value; persist(); });
  $('#rNotes').on('input', function () { state.notes = this.value; persist(); });

  // Search + category filter
  function filter() {
    var q = $.trim($('#rSearch').val()).toLowerCase(), cat = $('.rashan-cats a.active').data('cat');
    var any = false;
    $('.rashan-group').each(function () {
      var $g = $(this), groupHit = false;
      $g.find('.rashan-item').each(function () {
        var $r = $(this), sel = $r.hasClass('selected');
        var hit = (!q || $r.data('search').indexOf(q) > -1) && (cat !== 'selected' || sel);
        $r.toggle(hit); if (hit) groupHit = true;
      });
      $g.toggle(groupHit); if (groupHit) any = true;
    });
    $('.js-no-match').toggleClass('d-none', any);
  }
  $('#rSearch').on('input', filter);
  $('.rashan-cats a').on('click', function (e) {
    var $a = $(this), cat = $a.data('cat');
    $a.addClass('active').siblings().removeClass('active');
    if (cat === 'all' || cat === 'selected') { e.preventDefault(); filter(); return; }
    e.preventDefault();
    $('.rashan-cats a[data-cat=all]').addClass('active').siblings().not($a).removeClass('active'); $a.addClass('active');
    filter();
    var el = document.getElementById('rg' + cat); if (el) el.scrollIntoView({ behavior: 'smooth' });
  });

  /* ---------- Lists (logged in) ---------- */
  function loadLists() {
    if (!R.loggedIn) return;
    $.post(FS.url('api/rashan'), { action: 'lists' }, null, 'json').done(function (d) {
      var h = '';
      (d.lists || []).forEach(function (l) {
        h += '<li class="px-2 py-1 d-flex align-items-center gap-1"><a href="#" class="dropdown-item rounded js-list-open flex-grow-1" data-id="' + l.id + '"><strong>' + FS.esc(l.name) + '</strong><br><small class="text-muted">' + l.items + ' items' + (l.monthly_budget ? ' · budget ' + FS.money(l.monthly_budget) : '') + '</small></a>'
          + '<button class="btn btn-sm btn-light js-list-dup" data-id="' + l.id + '" title="Duplicate for this month"><i class="bi bi-files"></i></button>'
          + '<button class="btn btn-sm btn-light text-danger js-list-del" data-id="' + l.id + '" title="Delete"><i class="bi bi-trash"></i></button></li>';
      });
      h += '<li><hr class="dropdown-divider"></li><li><a href="#" class="dropdown-item js-list-new"><i class="bi bi-plus-circle me-1"></i>Start a new list</a></li>';
      $('.js-lists-menu').html(h);
    });
  }
  function applyList(l) {
    state = { items: {}, budget: l.budget != null ? l.budget : state.budget, name: l.name, notes: l.notes || '', listId: l.id };
    (l.items || []).forEach(function (it) { if (R.products[it.p]) state.items[it.p + '-' + it.v] = +it.q; });
    syncInputs();
    // select the right variation in each row for loaded items
    Object.keys(state.items).forEach(function (k) { var a = k.split('-'); $('.rashan-item[data-id="' + a[0] + '"] .js-ri-var').val(a[1]); });
    paintAll();
  }
  function syncInputs() { $('#rBudget').val(state.budget); $('#rName').val(state.name); $('#rNotes').val(state.notes); }

  $(document).on('click', '.js-list-open', function (e) {
    e.preventDefault();
    FS.api('rashan', { action: 'load', id: $(this).data('id') }).done(function (d) { applyList(d.list); FS.toast('Loaded "' + d.list.name + '"'); });
  });
  $(document).on('click', '.js-list-dup', function (e) {
    e.preventDefault(); e.stopPropagation();
    FS.api('rashan', { action: 'duplicate', id: $(this).data('id') }).done(function (d) { applyList(d.list); loadLists(); FS.toast(d.message); });
  });
  $(document).on('click', '.js-list-del', function (e) {
    e.preventDefault(); e.stopPropagation();
    var id = $(this).data('id');
    if (!confirm('Delete this saved list?')) return;
    FS.api('rashan', { action: 'delete', id: id }).done(function (d) { if (state.listId == id) state.listId = 0; persist(); loadLists(); FS.toast(d.message); });
  });
  $(document).on('click', '.js-list-new', function (e) {
    e.preventDefault();
    state = { items: {}, budget: state.budget, name: '', notes: '', listId: 0 }; syncInputs(); paintAll();
  });

  function save(asNew) {
    if (!R.loggedIn) { location.href = FS.url('login/') + '?next=' + encodeURIComponent(FS.url('monthly-rashan/')); return; }
    if (!itemsArray().length) { FS.toast('Add some items first.', 'error'); return; }
    FS.api('rashan', { action: 'save', id: asNew ? 0 : state.listId, name: state.name, budget: state.budget, notes: state.notes, items: JSON.stringify(itemsArray()) })
      .done(function (d) { state.listId = d.id; if (!state.name) { state.name = ''; } persist(); loadLists(); FS.toast(d.message); });
  }
  $('.js-r-save').on('click', function () { save(false); });
  $('.js-r-saveas').on('click', function () { save(true); });
  $('.js-r-clear').on('click', function () { if (confirm('Clear all items from this list?')) { state.items = {}; paintAll(); } });

  function toCart(order) {
    var items = itemsArray();
    if (!items.length) { FS.toast('Your rashan list is empty.', 'error'); return; }
    var $b = order ? $('.js-r-order') : $('.js-r-cart'), html = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border fs-spinner"></span>');
    FS.api('rashan', { action: 'to_cart', items: JSON.stringify(items), order: order ? 1 : 0, list_id: state.listId })
      .done(function (d) {
        FS.setCartCount(d.count);
        if (d.errors && d.errors.length) { FS.toast(d.errors[0], 'error'); if (!order) return; }
        FS.toast(d.message);
        if (order) location.href = d.redirect;
      })
      .always(function () { $b.prop('disabled', false).html(html); });
  }
  $('.js-r-order').on('click', function () { toCart(true); });
  $('.js-r-cart').on('click', function () { toCart(false); });

  /* ---------- Init ---------- */
  load();
  // drop items whose products are no longer offered
  Object.keys(state.items).forEach(function (k) { var a = k.split('-'); if (!opt(a[0], a[1])) delete state.items[k]; });
  Object.keys(state.items).forEach(function (k) { var a = k.split('-'); $('.rashan-item[data-id="' + a[0] + '"] .js-ri-var').val(a[1]); });
  syncInputs();
  paintAll();
  loadLists();
  if (R.openList && R.loggedIn) {
    FS.api('rashan', { action: 'load', id: R.openList }).done(function (d) { applyList(d.list); });
  }
})(jQuery);
