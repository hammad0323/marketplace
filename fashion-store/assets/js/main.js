document.addEventListener('DOMContentLoaded', function () {
  if (window.AOS) AOS.init({ duration: 700, once: true, offset: 60 });

  // Mobile nav
  var toggle = document.getElementById('mobileMenuToggle');
  var closeBtn = document.getElementById('mobileMenuClose');
  var nav = document.getElementById('mobileNav');
  if (toggle && nav) toggle.addEventListener('click', function () { nav.classList.add('open'); });
  if (closeBtn && nav) closeBtn.addEventListener('click', function () { nav.classList.remove('open'); });
  if (nav) nav.addEventListener('click', function (e) { if (e.target === nav) nav.classList.remove('open'); });

  // Search suggestions
  var searchInput = document.getElementById('searchInput');
  var suggestBox = document.getElementById('searchSuggest');
  var searchTimer;
  if (searchInput && suggestBox) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      var q = searchInput.value.trim();
      if (q.length < 2) { suggestBox.classList.remove('show'); return; }
      searchTimer = setTimeout(function () {
        fetch(BASE_URL + '/ajax/search_suggest.php?q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.length) { suggestBox.innerHTML = '<div class="p-3 small text-muted">No products found</div>'; }
            else {
              suggestBox.innerHTML = data.map(function (p) {
                return '<a href="' + BASE_URL + '/product.php?slug=' + p.slug + '"><img src="' + BASE_URL + '/' + p.image + '"><span>' + p.name + ' — ' + p.price + '</span></a>';
              }).join('');
            }
            suggestBox.classList.add('show');
          });
      }, 300);
    });
    document.addEventListener('click', function (e) {
      if (!suggestBox.contains(e.target) && e.target !== searchInput) suggestBox.classList.remove('show');
    });
  }

  // Add to cart (AJAX, works for buttons with data-product-id)
  document.querySelectorAll('.js-add-to-cart').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var form = btn.closest('form');
      var body = form ? new FormData(form) : new FormData();
      if (!form) body.append('product_id', btn.dataset.productId);
      fetch(BASE_URL + '/ajax/add_to_cart.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            document.querySelectorAll('.cart-count').forEach(function (el) { el.textContent = data.cart_count; });
            toast('Added to cart', 'success');
          } else {
            toast(data.message || 'Could not add to cart', 'error');
          }
        });
    });
  });

  // Wishlist toggle
  document.querySelectorAll('.js-wishlist-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var body = new FormData();
      body.append('product_id', btn.dataset.productId);
      fetch(BASE_URL + '/ajax/wishlist_toggle.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.login_required) { window.location.href = BASE_URL + '/login.php'; return; }
          if (data.success) {
            btn.classList.toggle('active', data.added);
            toast(data.added ? 'Added to wishlist' : 'Removed from wishlist', 'success');
          }
        });
    });
  });

  // Newsletter (footer form + any inline homepage section forms)
  document.querySelectorAll('#newsletterForm, .js-inline-newsletter').forEach(function (nlForm) {
    nlForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var body = new FormData(nlForm);
      fetch(BASE_URL + '/ajax/newsletter_subscribe.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var msg = document.getElementById('newsletterMsg');
          if (msg) { msg.textContent = data.message; msg.style.color = data.success ? '#8fd19e' : '#e88f8f'; }
          else toast(data.message, data.success ? 'success' : 'error');
          if (data.success) nlForm.reset();
        });
    });
  });

  // Quantity boxes
  document.querySelectorAll('.qty-box').forEach(function (box) {
    var input = box.querySelector('input');
    box.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = parseInt(input.value || '1', 10);
        val = btn.dataset.dir === 'inc' ? val + 1 : Math.max(1, val - 1);
        input.value = val;
        input.dispatchEvent(new Event('change'));
      });
    });
  });

  // Product gallery thumbnails
  document.querySelectorAll('.pd-thumbs img').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      var main = document.getElementById('pdMainImage');
      if (main) main.src = thumb.dataset.full || thumb.src;
      document.querySelectorAll('.pd-thumbs img').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });
});

function toast(message, icon) {
  if (window.Swal) {
    Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: message, showConfirmButton: false, timer: 2000 });
  }
}
