document.documentElement.classList.add('js');

function showToast(message, type) {
  const container = document.getElementById('toast-container');
  if (!container) { alert(message); return; }
  const toast = document.createElement('div');
  toast.className = 'toast toast-' + (type || 'success');
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

function ajaxPost(url, data) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(data).toString(),
  }).then(r => r.json());
}

document.addEventListener('DOMContentLoaded', function () {
  const base = window.BEGLET_BASE_URL || '/';

  // Mobile menu toggle
  const menuToggle = document.getElementById('mobile-menu-toggle');
  const categoryNav = document.getElementById('category-nav');
  if (menuToggle && categoryNav) {
    menuToggle.addEventListener('click', () => categoryNav.querySelector('.category-nav-inner').classList.toggle('open'));
  }

  // Hero slider
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.hero-dot');
  if (slides.length > 1) {
    let current = 0;
    setInterval(() => {
      slides[current].classList.remove('active');
      dots[current] && dots[current].classList.remove('active');
      current = (current + 1) % slides.length;
      slides[current].classList.add('active');
      dots[current] && dots[current].classList.add('active');
    }, 5000);
    dots.forEach((dot, i) => dot.addEventListener('click', () => {
      slides[current].classList.remove('active'); dots[current].classList.remove('active');
      current = i;
      slides[current].classList.add('active'); dots[current].classList.add('active');
    }));
  }

  // Add to cart
  document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const productId = this.dataset.productId;
      const qtyInputId = this.dataset.qtyInput;
      const qty = qtyInputId ? (document.getElementById(qtyInputId).value || 1) : 1;
      const variationSelect = document.getElementById('variation-select');
      const variationId = variationSelect ? variationSelect.value : '';
      ajaxPost(base + 'actions/cart.php', { do: 'add', product_id: productId, quantity: qty, variation_id: variationId })
        .then(res => {
          if (res.login_required) { window.location.href = base + 'login.php'; return; }
          showToast(res.message, res.success ? 'success' : 'error');
          if (res.success) {
            const badge = document.getElementById('cart-badge');
            if (badge) badge.textContent = res.cart_count;
          }
        });
    });
  });

  // Wishlist toggle
  document.querySelectorAll('.wishlist-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const productId = this.dataset.productId;
      ajaxPost(base + 'actions/wishlist.php', { do: 'toggle', product_id: productId })
        .then(res => {
          if (res.login_required) { window.location.href = base + 'login.php'; return; }
          showToast(res.message, res.success ? 'success' : 'error');
          if (res.success) {
            this.classList.toggle('active', res.action === 'added');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-solid', res.action === 'added');
            icon.classList.toggle('fa-regular', res.action !== 'added');
          }
        });
    });
  });

  // Cart page: qty +/- and remove
  document.querySelectorAll('.cart-qty-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const row = this.closest('.cart-item');
      const input = row.querySelector('.cart-qty-value');
      let val = parseInt(input.value, 10) || 1;
      val = this.dataset.action === 'inc' ? val + 1 : Math.max(1, val - 1);
      input.value = val;
      updateCartItem(input.dataset.itemId, val);
    });
  });
  document.querySelectorAll('.cart-qty-value').forEach(input => {
    input.addEventListener('change', function () { updateCartItem(this.dataset.itemId, this.value); });
  });
  function updateCartItem(itemId, qty) {
    ajaxPost(base + 'actions/cart.php', { do: 'update', item_id: itemId, quantity: qty }).then(res => {
      if (res.success) {
        const subtotalEl = document.getElementById('cart-subtotal');
        if (subtotalEl) subtotalEl.textContent = res.subtotal;
        const badge = document.getElementById('cart-badge');
        if (badge) badge.textContent = res.cart_count;
      }
    });
  }
  document.querySelectorAll('.cart-remove-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const itemId = this.dataset.itemId;
      ajaxPost(base + 'actions/cart.php', { do: 'remove', item_id: itemId }).then(res => {
        showToast(res.message, 'success');
        if (res.success) {
          document.querySelector(`.cart-item[data-item-id="${itemId}"]`)?.remove();
          const badge = document.getElementById('cart-badge');
          if (badge) badge.textContent = res.cart_count;
          if (res.cart_count == 0) location.reload();
        }
      });
    });
  });

  // Review submission
  const reviewForm = document.getElementById('review-form');
  if (reviewForm) {
    reviewForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this).entries());
      data.product_id = this.dataset.productId;
      ajaxPost(base + 'actions/review.php', data).then(res => {
        showToast(res.message, res.success ? 'success' : 'error');
        if (res.success) setTimeout(() => location.reload(), 1000);
      });
    });
  }

  // Cancel order (customer)
  document.querySelectorAll('.cancel-order-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      if (!confirm('Cancel this order?')) return;
      ajaxPost(base + 'actions/order.php', { do: 'cancel_shop_order', shop_order_id: this.dataset.shopOrderId }).then(res => {
        showToast(res.message, res.success ? 'success' : 'error');
        if (res.success) setTimeout(() => location.reload(), 1000);
      });
    });
  });

  // Navbar search autocomplete
  const searchInput = document.querySelector('.search-bar input[name="q"]');
  if (searchInput) {
    let box;
    searchInput.addEventListener('input', function () {
      const q = this.value.trim();
      if (box) box.remove();
      if (q.length < 2) return;
      fetch(base + 'actions/product.php?do=suggest&q=' + encodeURIComponent(q))
        .then(r => r.json()).then(items => {
          if (box) box.remove();
          if (!items.length) return;
          box = document.createElement('div');
          box.style.cssText = 'position:absolute;top:100%;left:0;right:0;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.12);border-radius:8px;z-index:200;max-height:320px;overflow-y:auto;';
          items.forEach(item => {
            const a = document.createElement('a');
            a.href = item.url;
            a.style.cssText = 'display:flex;gap:10px;align-items:center;padding:8px 12px;border-bottom:1px solid #f0f0f0;';
            a.innerHTML = `<img src="${item.image}" style="width:36px;height:36px;object-fit:cover;border-radius:4px"><span>${item.name}<br><small style="color:#888">${item.price_formatted}</small></span>`;
            box.appendChild(a);
          });
          this.closest('.search-bar').style.position = 'relative';
          this.closest('.search-bar').appendChild(box);
        });
    });
    document.addEventListener('click', e => { if (box && !searchInput.contains(e.target)) box.remove(); });
  }
});
