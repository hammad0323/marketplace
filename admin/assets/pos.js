/* Fahim Store POS */
$(function () {
  'use strict';
  var A = window.FSA;
  var cart = [];          // {p, v, q, name, price, stock, sku}
  var customer = null;    // {id, name, phone, addresses}
  var draftId = 0;
  var KEY = 'fs_pos_cart';

  function save() { try { localStorage.setItem(KEY, JSON.stringify({ cart: cart, customer: customer, draftId: draftId })); } catch (e) {} }
  function restore() { try { var s = JSON.parse(localStorage.getItem(KEY)); if (s) { cart = s.cart || []; customer = s.customer || null; draftId = s.draftId || 0; } } catch (e) {} }

  /* ---------- Products ---------- */
  var sTimer, sXhr;
  function search(q, scan) {
    if (sXhr) sXhr.abort();
    sXhr = $.getJSON(A.admin + 'ajax/products.php', { q: q, limit: 40 }).done(function (d) {
      if (scan && d.exact && d.items.length === 1) { add(d.items[0]); $('#posSearch').val('').focus(); return; }
      var h = '';
      d.items.forEach(function (p, i) {
        var out = p.stock_num <= 0;
        h += '<button type="button" class="pos-prod' + (out ? ' out' : '') + '" data-i="' + i + '"><img src="' + p.thumb + '" alt="" loading="lazy"><span class="n">' + A.esc(p.label) + '</span>'
          + '<span class="pr">' + A.money(p.unit_price) + '</span><span class="st ' + (out ? 'text-danger' : 'text-muted') + '">Stock: ' + p.stock + ' · ' + A.esc(p.sku) + '</span></button>';
      });
      $('#posResults').html(h || '<div class="text-muted p-3">No products found.</div>').data('items', d.items);
      if (scan && !d.items.length) A.toast('No product with code "' + q + '"', 'error');
    });
  }
  $('#posSearch').on('input', function () { var q = $.trim(this.value); clearTimeout(sTimer); sTimer = setTimeout(function () { search(q, false); }, 220); })
    .on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(sTimer); search($.trim(this.value), true); } });
  $(document).on('click', '.pos-prod', function () { add($('#posResults').data('items')[$(this).data('i')]); });

  function add(p) {
    if (p.stock_num <= 0) { A.toast('Out of stock: ' + p.label, 'error'); return; }
    var line = cart.find(function (l) { return l.p === p.product_id && l.v === p.variation_id; });
    if (line) {
      if (line.q + 1 > line.stock) { A.toast('Only ' + line.stock + ' in stock', 'error'); return; }
      line.q++;
    } else cart.push({ p: p.product_id, v: p.variation_id, q: 1, name: p.label, price: +p.unit_price, stock: +p.stock_num, sku: p.sku });
    render();
  }

  /* ---------- Cart ---------- */
  function totals() {
    var sub = 0, items = 0;
    cart.forEach(function (l) { sub += l.price * l.q; items += l.q; });
    var dv = +$('#discVal').val() || 0, disc = $('#discType').val() === 'percent' ? sub * Math.min(100, dv) / 100 : dv;
    disc = Math.min(disc, sub);
    var fee = ful() === 'delivery' ? (+$('#delFee').val() || 0) : 0;
    var grand = Math.max(0, sub - disc + fee);
    return { sub: sub, items: items, disc: disc, fee: fee, grand: Math.round(grand * 100) / 100 };
  }
  function render() {
    var h = '';
    cart.forEach(function (l, i) {
      h += '<div class="pos-line"><div><div class="fw-semibold">' + A.esc(l.name) + '</div><small class="text-muted">' + A.money(l.price) + ' · stock ' + (l.stock >= 99999 ? '∞' : l.stock) + '</small></div>'
        + '<div class="input-group input-group-sm" style="width:110px"><button class="btn btn-outline-secondary js-dec" data-i="' + i + '" type="button">−</button><input type="number" class="form-control text-center js-q" data-i="' + i + '" value="' + l.q + '" min="0" aria-label="Quantity"><button class="btn btn-outline-secondary js-inc" data-i="' + i + '" type="button">+</button></div>'
        + '<strong class="text-end" style="min-width:80px">' + A.money(l.price * l.q) + '</strong><button class="btn btn-sm btn-link text-danger js-del" data-i="' + i + '" aria-label="Remove"><i class="bi bi-trash"></i></button></div>';
    });
    $('#posCart').html(h || '<div class="text-center text-muted p-4 small"><i class="bi bi-cart3 fs-2 d-block"></i>Scan or click products to add</div>');
    var t = totals();
    $('#tItems').text(t.items); $('#tSub').text(A.money(t.sub)); $('#tDisc').text('- ' + A.money(t.disc)); $('#tGrand').text(A.money(t.grand));
    var tendered = +$('#tendered').val() || 0;
    $('#tChange').text(A.money(Math.max(0, tendered - t.grand)));
    renderCustomer();
    save();
  }
  function setQty(i, q) {
    var l = cart[i]; if (!l) return;
    if (q > l.stock) { A.toast('Only ' + l.stock + ' in stock', 'error'); q = l.stock; }
    if (q <= 0) cart.splice(i, 1); else l.q = q;
    render();
  }
  $(document).on('click', '.js-inc', function () { var i = +$(this).data('i'); setQty(i, cart[i].q + 1); });
  $(document).on('click', '.js-dec', function () { var i = +$(this).data('i'); setQty(i, cart[i].q - 1); });
  $(document).on('change', '.js-q', function () { setQty(+$(this).data('i'), parseInt(this.value, 10) || 0); });
  $(document).on('click', '.js-del', function () { cart.splice(+$(this).data('i'), 1); render(); });
  $('#discVal, #discType, #delFee, #tendered').on('input change', render);

  /* ---------- Fulfillment / payment ---------- */
  function ful() { return $('input[name=ful]:checked').val(); }
  $('input[name=ful]').on('change', function () {
    $('#pickupBox').toggleClass('d-none', ful() !== 'pickup');
    $('#deliveryBox').toggleClass('d-none', ful() !== 'delivery');
    if (ful() === 'pickup' && !$('#pickupAt').val()) { var d = new Date(Date.now() + 2 * 3600e3); d.setMinutes(0); $('#pickupAt').val(new Date(d - d.getTimezoneOffset() * 60e3).toISOString().slice(0, 16)); }
    render();
  });
  $('#zoneSel').on('change', function () {
    var o = $(this).find(':selected'), fee = +o.data('fee') || 0, free = +o.data('free') || 0;
    if (free && totals().sub >= free) fee = 0;
    $('#delFee').val(fee); render();
  });
  $('#payMethod').on('change', function () {
    var m = $(this).find(':selected');
    $('#payRef').toggleClass('d-none', !m.data('ref'));
    $('#cashBox').toggleClass('d-none', this.value !== 'cash');
  }).trigger('change');

  /* ---------- Customer ---------- */
  function renderCustomer() {
    if (!customer) { $('#custBox').html('<span class="text-muted">Walk-in customer</span>'); return; }
    $('#custBox').html('<i class="bi bi-person-check text-success"></i> <strong>' + A.esc(customer.name) + '</strong> · ' + A.esc(customer.phone) + ' <a href="#" class="text-danger ms-2 js-cust-clear">remove</a>');
  }
  var cTimer;
  $('#custSearch').on('input', function () {
    var q = $.trim(this.value); clearTimeout(cTimer);
    if (q.length < 2) { $('#custResults').empty(); return; }
    cTimer = setTimeout(function () {
      A.post('ajax/pos.php', { action: 'customers', q: q }).done(function (d) {
        var h = '';
        d.customers.forEach(function (c, i) { h += '<button type="button" class="list-group-item list-group-item-action small js-cust" data-i="' + i + '"><strong>' + A.esc(c.name) + '</strong> · ' + A.esc(c.phone) + (c.last_order ? ' <span class="text-muted">· last ' + A.esc(c.last_order) + '</span>' : '') + '</button>'; });
        h += '<button type="button" class="list-group-item list-group-item-action small text-brand" data-bs-toggle="modal" data-bs-target="#custModal"><i class="bi bi-person-plus"></i> Add new customer</button>';
        $('#custResults').html(h).data('list', d.customers);
      });
    }, 250);
  });
  function selectCustomer(c) {
    customer = c; $('#custResults').empty(); $('#custSearch').val('');
    if (c.addresses && c.addresses.length) {
      var a = c.addresses[0];
      $('#addrLine').val(a.address_line); if (a.delivery_zone_id) $('#zoneSel').val(a.delivery_zone_id).trigger('change');
    }
    render();
  }
  $(document).on('click', '.js-cust', function () { selectCustomer($('#custResults').data('list')[$(this).data('i')]); });
  $(document).on('click', '.js-cust-clear', function (e) { e.preventDefault(); customer = null; render(); });
  $('#custForm').on('submit', function (e) {
    e.preventDefault();
    A.post('ajax/pos.php', $(this).serialize() + '&action=customer_create').done(function (d) {
      bootstrap.Modal.getInstance('#custModal').hide(); selectCustomer(d.customer); A.toast(d.message); $('#custForm')[0].reset();
    });
  });
  $('#custModal').on('show.bs.modal', function () { var q = $.trim($('#custSearch').val()); if (/^\d+$/.test(q)) this.querySelector('[name=phone]').value = q; else if (q) this.querySelector('[name=name]').value = q; });

  /* ---------- Checkout ---------- */
  var busy = false;
  function submit(mode) {
    if (busy) return;
    if (!cart.length) { A.toast('Add products first.', 'error'); return; }
    if (mode !== 'complete' && mode !== 'draft' && !customer) { A.toast('Select or add a customer for pending orders.', 'error'); $('#custSearch').focus(); return; }
    if (ful() !== 'walkin' && !customer) { A.toast('Select a customer for pickup/delivery.', 'error'); return; }
    var t = totals();
    if (mode === 'complete' && $('#payMethod').val() === 'cash' && $('#tendered').val() !== '' && (+$('#tendered').val()) < t.grand) { A.toast('Cash received is less than the total.', 'error'); return; }
    busy = true;
    var data = {
      action: 'checkout', mode: mode, items: JSON.stringify(cart.map(function (l) { return { p: l.p, v: l.v, q: l.q }; })),
      customer_id: customer ? customer.id : 0, name: customer ? customer.name : '', phone: customer ? customer.phone : '', email: customer ? (customer.email || '') : '',
      fulfillment: ful(), zone_id: $('#zoneSel').val(), address_line: $('#addrLine').val(), delivery_fee: $('#delFee').val(), pickup_at: $('#pickupAt').val(),
      payment_method: $('#payMethod').val(), payment_reference: $('#payRef').val(), tendered: $('#tendered').val(),
      discount_type: $('#discType').val(), discount_value: $('#discVal').val(), notes: $('#posNotes').val(),
      order_type: $('#isRashan').is(':checked') ? 'rashan' : 'regular', draft_id: draftId
    };
    A.post('ajax/pos.php', data).done(function (d) {
      $('#doneTitle').text(d.message);
      $('.js-done-change').text(d.change ? 'Change: ' + A.money(d.change) : '');
      $('.js-print-receipt').attr('href', A.admin + 'print-receipt.php?auto=1&id=' + d.order_id).toggleClass('d-none', mode === 'draft');
      $('.js-print-pick').attr('href', A.admin + 'print-picking.php?auto=1&id=' + d.order_id);
      $('.js-view-order').attr('href', A.admin + 'order-view.php?id=' + d.order_id);
      new bootstrap.Modal('#doneModal').show();
      reset();
    }).always(function () { busy = false; });
  }
  function reset() {
    cart = []; customer = null; draftId = 0;
    $('#discVal').val(0); $('#tendered').val(''); $('#posNotes').val(''); $('#payRef').val(''); $('#addrLine').val(''); $('#zoneSel').val(''); $('#delFee').val(0); $('#isRashan').prop('checked', false);
    $('#fulW').prop('checked', true).trigger('change');
    render();
  }
  $('#btnComplete').on('click', function () { submit('complete'); });
  $('#btnPending').on('click', function () { submit('pending'); });
  $('#btnDraft').on('click', function () { submit('draft'); });
  $('#btnClear').on('click', function () { if (!cart.length || confirm('Clear this sale?')) reset(); });
  $('#doneModal').on('hidden.bs.modal', function () { $('#posSearch').focus(); });

  /* ---------- Drafts ---------- */
  function loadDraft(id) {
    A.post('ajax/pos.php', { action: 'load_draft', id: id }).done(function (d) {
      var o = d.order;
      cart = o.items; draftId = o.id;
      customer = o.customer_id && o.phone !== '00000000000' ? { id: o.customer_id, name: o.name, phone: o.phone } : null;
      $('input[name=ful][value=' + o.fulfillment + ']').prop('checked', true).trigger('change');
      $('#zoneSel').val(o.zone_id || ''); $('#addrLine').val(o.address_line || ''); $('#delFee').val(o.delivery_fee || 0); $('#pickupAt').val(o.pickup_at || '');
      $('#posNotes').val(o.notes || ''); $('#isRashan').prop('checked', o.order_type === 'rashan'); $('#discType').val('amount'); $('#discVal').val(o.discount || 0);
      render(); A.toast('Draft loaded');
      var m = bootstrap.Modal.getInstance('#draftsModal'); if (m) m.hide();
    });
  }
  $('#draftsBtn').on('click', function () {
    A.post('ajax/pos.php', { action: 'drafts' }).done(function (d) {
      var h = '';
      d.drafts.forEach(function (r) {
        h += '<div class="list-group-item d-flex gap-2 align-items-center"><div class="flex-grow-1"><strong>' + A.esc(r.order_number) + '</strong> ' + (r.order_type === 'rashan' ? '<span class="badge bg-success">Rashan</span>' : '') + '<br><small>' + A.esc(r.customer_name) + ' · ' + r.items + ' items · ' + A.esc(r.total_fmt) + ' · ' + A.esc(r.ago) + '</small></div>'
          + '<button class="btn btn-sm btn-brand js-load-draft" data-id="' + r.id + '">Load</button><button class="btn btn-sm btn-outline-danger js-del-draft" data-id="' + r.id + '"><i class="bi bi-trash"></i></button></div>';
      });
      $('#draftList').html(h || '<div class="p-4 text-muted text-center">No drafts saved.</div>');
      bootstrap.Modal.getOrCreateInstance('#draftsModal').show();
    });
  });
  $(document).on('click', '.js-load-draft', function () { if (!cart.length || confirm('Replace the current sale with this draft?')) loadDraft($(this).data('id')); });
  $(document).on('click', '.js-del-draft', function () { var $r = $(this).closest('.list-group-item'); if (confirm('Delete draft?')) A.post('ajax/pos.php', { action: 'delete_draft', id: $(this).data('id') }).done(function () { $r.remove(); }); });

  /* ---------- Register ---------- */
  $('#closeReg').on('click', function (e) {
    e.preventDefault();
    A.post('ajax/pos.php', { action: 'session_summary' }).done(function (d) {
      var s = d.summary;
      $('.js-sess-summary').html('Opening cash: <strong>' + A.money(d.session.opening_cash) + '</strong><br>Sales: <strong>' + s.sales + '</strong> totalling <strong>' + A.money(s.total) + '</strong><br>Cash sales: <strong>' + A.money(s.cash) + '</strong><br>Expected in drawer: <strong>' + A.money(s.expected_cash) + '</strong>');
      new bootstrap.Modal('#closeModal').show();
    });
  });
  $('#closeForm').on('submit', function (e) {
    e.preventDefault();
    A.post('ajax/pos.php', $(this).serialize() + '&action=close_session').done(function (d) {
      alert('Register closed.\nExpected cash: ' + A.money(d.summary.expected_cash) + '\nDifference: ' + A.money(d.difference));
      location.reload();
    });
  });

  /* ---------- Keyboard ---------- */
  $(document).on('keydown', function (e) {
    if (e.key === 'F2') { e.preventDefault(); $('#posSearch').focus().select(); }
    if (e.key === 'F9') { e.preventDefault(); if (!$('#btnComplete').prop('disabled')) submit('complete'); }
    if (e.key === 'F8') { e.preventDefault(); submit('pending'); }
  });

  restore();
  render();
  search('', false);
  if (window.POS_LOAD) loadDraft(window.POS_LOAD);
});
