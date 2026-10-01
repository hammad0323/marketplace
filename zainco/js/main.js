/* =====================================================================
   ZAINCO PACKAGING — SITE SCRIPT
   Shared layout (header/footer), cart, animations and page renderers.
   ===================================================================== */
(function () {
  "use strict";

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const page = document.body.dataset.page || "";
  const fmt = n => SITE.currency + " " + Number(n).toLocaleString("en-PK", { maximumFractionDigits: 2 });
  const imgPath = name => "img/products/" + name + (name.includes(".") ? "" : ".svg");
  const catName = id => (CATEGORIES.find(c => c.id === id) || {}).name || "";
  const productById = id => PRODUCTS.find(p => p.id === id);
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* storage unavailable */ } }
  };

  /* ---------------- Icons ---------------- */
  const P = {
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    cart: '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
    menu: '<path d="M3 6h18M3 12h18M3 18h18"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    arrow: '<path d="M5 12h14M12 5l7 7-7 7"/>',
    up: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    checkCircle: '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14l-3-3"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    trash: '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
    truck: '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    award: '<circle cx="12" cy="8" r="7"/><path d="M8.2 13.9 7 23l5-3 5 3-1.2-9.1"/>',
    leaf: '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10z"/><path d="M2 21c0-3 1.9-5.4 5.1-6"/>',
    box: '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
    sack: '<path d="M8 3h8l-1.5 3h-5z"/><path d="M9.5 6C5 9 4 13 4 16c0 3.5 3 5 8 5s8-1.5 8-5c0-3-1-7-5.5-10"/>',
    layers: '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
    bag: '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
    carton: '<path d="M7 3h10v18H7z"/><path d="M7 7h10M10 3v4"/>',
    pouch: '<path d="M6 3h12v14a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4z"/><path d="M6 7h12"/>',
    jumbo: '<path d="M5 6h14l1 15H4z"/><path d="M5 6 7 2M19 6l-2-4"/>',
    wheat: '<path d="M2 22 16 8M3.5 12.5 5 11l1.5 1.5a3.5 3.5 0 0 1 0 5L5 19l-1.5-1.5a3.5 3.5 0 0 1 0-5zM7.5 8.5 9 7l1.5 1.5a3.5 3.5 0 0 1 0 5L9 15l-1.5-1.5a3.5 3.5 0 0 1 0-5zM11.5 4.5 13 3l1.5 1.5a3.5 3.5 0 0 1 0 5L13 11l-1.5-1.5a3.5 3.5 0 0 1 0-5zM20 2h2v2a4 4 0 0 1-4 4h-2V6a4 4 0 0 1 4-4z"/>',
    pill: '<path d="m10.5 20.5 10-10a5 5 0 1 0-7-7l-10 10a5 5 0 1 0 7 7zM8.5 8.5l7 7"/>',
    building: '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
    sprout: '<path d="M7 20h10M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8zM14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/>',
    flask: '<path d="M9 2h6M10 2v7L4 20a1 1 0 0 0 1 2h14a1 1 0 0 0 1-2L14 9V2M7 16h10"/>',
    shirt: '<path d="M20.4 3.5 16 2a4 4 0 0 1-8 0L3.6 3.5a2 2 0 0 0-1.3 2.2l.6 3.5a1 1 0 0 0 1 .8H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.2a1 1 0 0 0 1-.8l.6-3.5a2 2 0 0 0-1.4-2.2z"/>',
    sparkle: '<path d="M12 3 9.5 9.5 3 12l6.5 2.5L12 21l2.5-6.5L21 12l-6.5-2.5z"/>',
    pen: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    printer: '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    factory: '<path d="M2 20V8l6 4V8l6 4V4h8v16z"/><path d="M6 16h.01M10 16h.01M14 16h.01M18 16h.01"/>',
    chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    quote: '<path d="M3 21c3 0 7-1 7-8V5H3v8h4c0 3-1 5-4 5zM14 21c3 0 7-1 7-8V5h-7v8h4c0 3-1 5-4 5z"/>',
    zoom: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
    heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8z"/>',
    play: '<path d="M6 4l14 8-14 8z" fill="currentColor"/>',
    ruler: '<path d="M21.3 15.3 8.7 2.7a1 1 0 0 0-1.4 0L2.7 7.3a1 1 0 0 0 0 1.4l12.6 12.6a1 1 0 0 0 1.4 0l4.6-4.6a1 1 0 0 0 0-1.4zM7.5 10.5l2-2M10.5 13.5l2-2M13.5 16.5l2-2"/>',
    fb: '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
    ig: '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
    in: '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/>',
    wa: '<path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9z"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>'
  };
  const icon = (n, cls = "icon") => `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[n] || ""}</svg>`;
  window.ZIcon = icon;

  const brandMark = '<svg viewBox="0 0 32 32" fill="none" stroke="#fff" stroke-width="2.4" stroke-linejoin="round"><path d="M16 3 28 9.5v13L16 29 4 22.5v-13z"/><path d="M4 9.5 16 16l12-6.5M16 16v13"/><path d="m10 6.3 12 6.5" stroke-opacity=".6"/></svg>';
  const brand = `<a href="index.html" class="brand" aria-label="${SITE.name} home"><span class="brand-mark">${brandMark}</span><span class="brand-text"><b>ZAINCO</b><small>Packaging Industries</small></span></a>`;

  /* ---------------- Layout injection ---------------- */
  const NAV = [["index.html", "Home", "home"], ["about.html", "About", "about"], ["products.html", "Products", "products"], ["portfolio.html", "Portfolio", "portfolio"], ["contact.html", "Contact", "contact"]];
  const navLinks = NAV.map(([h, t, k]) => `<a href="${h}" class="${page === k || (k === "products" && page === "product") ? "active" : ""}">${t}</a>`).join("");

  function renderLayout() {
    const head = document.createElement("div");
    head.className = "site-head";
    head.innerHTML = `
      <div class="topbar"><div class="container">
        <div class="tb-left">
          <a href="tel:${SITE.phoneIntl}">${icon("phone")} ${SITE.phone}</a>
          <a href="mailto:${SITE.email}" class="hide-sm">${icon("mail")} ${SITE.email}</a>
          <span class="hide-sm">${icon("clock")} ${SITE.hours}</span>
        </div>
        <a href="https://wa.me/${SITE.whatsapp}" target="_blank" rel="noopener">${icon("chat")} WhatsApp for bulk quotes</a>
      </div></div>
      <header class="header"><div class="container">
        ${brand}
        <nav class="nav" aria-label="Main">${navLinks}</nav>
        <div class="header-actions">
          <a href="products.html" class="icon-btn hide-sm" aria-label="Search products">${icon("search")}</a>
          <button class="icon-btn" data-open="cart" aria-label="Open cart">${icon("cart")}<span class="cart-count">0</span></button>
          <a href="contact.html#quote" class="btn btn-primary btn-sm hide-sm">Get a Quote</a>
          <button class="icon-btn menu-toggle" data-open="menu" aria-label="Open menu">${icon("menu")}</button>
        </div>
      </div></header>`;
    document.body.prepend(head);
    if (document.body.dataset.solid !== undefined) head.classList.add("solid");

    const progress = document.createElement("div");
    progress.className = "progress";
    document.body.prepend(progress);

    document.body.insertAdjacentHTML("beforeend", `
      <div class="drawer" id="menu"><div class="scrim" data-close></div><aside class="panel">
        <div class="drawer-head">${brand.replace('class="brand"', 'class="brand" style="color:var(--navy)"')}<button class="icon-btn" data-close aria-label="Close">${icon("x")}</button></div>
        <nav class="drawer-nav">${navLinks}<a href="cart.html">Cart &amp; Checkout</a></nav>
        <div style="margin-top:auto;padding-top:24px;display:grid;gap:10px">
          <a class="btn btn-primary btn-block" href="contact.html#quote">Get a Quote</a>
          <a class="btn btn-wa btn-block" href="https://wa.me/${SITE.whatsapp}" target="_blank" rel="noopener">${icon("chat")} WhatsApp Us</a>
        </div>
      </aside></div>
      <div class="drawer" id="cart"><div class="scrim" data-close></div><aside class="panel">
        <div class="drawer-head"><h3 style="margin:0">Your Cart</h3><button class="icon-btn" data-close aria-label="Close">${icon("x")}</button></div>
        <div class="mini-cart-items"></div>
        <div class="mini-cart-foot"></div>
      </aside></div>
      <footer class="footer"><div class="container">
        <div class="footer-grid">
          <div>
            ${brand}
            <p style="margin-top:20px">Karachi-based manufacturer and supplier of complete packaging — PP woven &amp; BOPP bags, paper bags, corrugated boxes, folding cartons, flexible pouches and FIBC jumbo bags for businesses across Pakistan.</p>
            <div class="socials">
              <a href="#" aria-label="Facebook">${icon("fb")}</a><a href="#" aria-label="Instagram">${icon("ig")}</a>
              <a href="#" aria-label="LinkedIn">${icon("in")}</a><a href="https://wa.me/${SITE.whatsapp}" aria-label="WhatsApp">${icon("chat")}</a>
            </div>
          </div>
          <div><h4>Quick Links</h4><ul>
            <li><a href="index.html">Home</a></li><li><a href="about.html">About Us</a></li><li><a href="products.html">Shop Products</a></li>
            <li><a href="portfolio.html">Portfolio</a></li><li><a href="contact.html">Contact</a></li><li><a href="cart.html">Cart &amp; Checkout</a></li></ul></div>
          <div><h4>Products</h4><ul>${CATEGORIES.slice(0, 7).map(c => `<li><a href="products.html?cat=${c.id}">${c.name}</a></li>`).join("")}</ul></div>
          <div><h4>Get in Touch</h4>
            <div class="contact-line">${icon("pin")}<span>${SITE.address}</span></div>
            <div class="contact-line">${icon("phone")}<a href="tel:${SITE.phoneIntl}">${SITE.phone}</a></div>
            <div class="contact-line">${icon("mail")}<a href="mailto:${SITE.email}">${SITE.email}</a></div>
            <form class="newsletter" data-newsletter><input type="email" required placeholder="Your email for price lists" aria-label="Email"><button class="btn btn-primary btn-sm">Subscribe</button></form>
          </div>
        </div>
        <div class="footer-bottom"><span>© ${new Date().getFullYear()} ${SITE.name}. All rights reserved.</span><span>Karachi, Pakistan · Packaging that protects &amp; sells</span></div>
      </div></footer>
      <a class="wa-float" href="https://wa.me/${SITE.whatsapp}?text=${encodeURIComponent("Hello Zainco, I'd like a packaging quote.")}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
        <svg width="30" height="30" viewBox="0 0 32 32" fill="#fff"><path d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.7a10.7 10.7 0 0 1-5.5-1.5l-.4-.2-3.9 1 1-3.8-.3-.4A10.7 10.7 0 1 1 16 26.7zm5.9-8c-.3-.2-1.9-1-2.2-1-.3-.1-.5-.2-.7.2l-1 1.2c-.2.2-.4.2-.7.1a8.8 8.8 0 0 1-4.4-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.6l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.7 3.7 0 0 0-1.1 2.7 6.5 6.5 0 0 0 1.3 3.4 14.7 14.7 0 0 0 5.7 5c2.1.9 2.9 1 4 .8.6-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.5-.1-.2-.3-.3-.6-.4z"/></svg>
      </a>
      <button class="to-top" aria-label="Back to top">${icon("up")}<svg class="ring" viewBox="0 0 52 52"><circle cx="26" cy="26" r="24" fill="none" stroke="#f59e0b" stroke-width="3" stroke-dasharray="150.8" stroke-dashoffset="150.8"/></svg></button>
      <div class="toast" role="status" aria-live="polite">${icon("checkCircle")}<span></span></div>`);
  }

  /* ---------------- Toast ---------------- */
  let toastT;
  function toast(msg) {
    const t = $(".toast");
    $("span", t).textContent = msg;
    t.classList.add("show");
    clearTimeout(toastT);
    toastT = setTimeout(() => t.classList.remove("show"), 2800);
  }

  /* ---------------- Cart ---------------- */
  const Cart = {
    items() { return store.get("zainco_cart", []); },
    save(items) { store.set("zainco_cart", items); Cart.sync(); },
    add(id, qty, size, printing) {
      const p = productById(id); if (!p) return;
      qty = Math.max(p.moq, parseInt(qty, 10) || p.moq);
      size = size || p.sizes[0];
      printing = printing || "Plain / stock";
      const items = Cart.items();
      const key = [id, size, printing].join("|");
      const ex = items.find(i => i.key === key);
      if (ex) ex.qty += qty; else items.push({ key, id, qty, size, printing });
      Cart.save(items);
      toast(`${p.name} added to cart`);
      const c = $(".cart-count"); c.classList.remove("bump"); void c.offsetWidth; c.classList.add("bump");
    },
    setQty(key, qty) {
      const items = Cart.items(); const it = items.find(i => i.key === key); if (!it) return;
      const p = productById(it.id);
      it.qty = Math.max(p.moq, parseInt(qty, 10) || p.moq);
      Cart.save(items);
    },
    remove(key) { Cart.save(Cart.items().filter(i => i.key !== key)); },
    clear() { Cart.save([]); },
    unitPrice(it) { const p = productById(it.id); return p.price * (it.printing.startsWith("Custom") ? 1.15 : 1); },
    totals() {
      const items = Cart.items().filter(i => productById(i.id));
      const subtotal = items.reduce((s, i) => s + Cart.unitPrice(i) * i.qty, 0);
      const delivery = subtotal === 0 ? 0 : subtotal >= 50000 ? 0 : 1500;
      return { items, subtotal, delivery, total: subtotal + delivery };
    },
    sync() {
      const { items, subtotal } = Cart.totals();
      const count = items.length;
      $$(".cart-count").forEach(c => { c.textContent = count; c.classList.toggle("show", count > 0); });
      const list = $(".mini-cart-items"), foot = $(".mini-cart-foot");
      if (list) {
        list.innerHTML = count ? items.map(i => {
          const p = productById(i.id);
          return `<div class="mini-item"><img src="${imgPath(p.img)}" alt=""><div><b>${esc(p.name)}</b><small>${esc(i.size)} · ${i.qty.toLocaleString()} ${p.unit}s</small><br><small><b style="display:inline;color:var(--orange)">${fmt(Cart.unitPrice(i) * i.qty)}</b></small></div><button class="remove" data-remove="${esc(i.key)}" aria-label="Remove">${icon("trash")}</button></div>`;
        }).join("") : `<div class="empty-state">${icon("cart")}<p>Your cart is empty.</p><a href="products.html" class="btn btn-outline btn-sm">Browse products</a></div>`;
        foot.innerHTML = count ? `<div class="row"><span>Subtotal</span><span>${fmt(subtotal)}</span></div><a href="cart.html" class="btn btn-dark btn-block">View cart</a><a href="cart.html#checkout" class="btn btn-primary btn-block">Checkout</a>` : "";
      }
      if (page === "cart") renderCartPage();
    }
  };
  window.ZCart = Cart;

  /* ---------------- Product card ---------------- */
  function productCard(p, i = 0) {
    const badge = p.badge ? `<span class="badge ${p.badge === "Eco" || p.badge === "Food Grade" ? "eco" : ""}">${p.badge}</span>` : "";
    return `<article class="product-card reveal" style="--d:${(i % 4) * 0.08}s">
      <a class="media" href="product.html?id=${p.id}">${badge}<img src="${imgPath(p.img)}" alt="${esc(p.name)}" loading="lazy">
        <span class="quick"><span class="btn btn-dark btn-sm">View details</span></span></a>
      <div class="info">
        <span class="cat">${catName(p.cat)}</span>
        <h3><a href="product.html?id=${p.id}">${esc(p.name)}</a></h3>
        <div class="price-row"><span class="price">${fmt(p.price)} <small>/ ${p.unit}</small></span><span class="moq">MOQ ${p.moq.toLocaleString()}</span></div>
        <button class="btn btn-outline btn-sm btn-block" style="margin-top:14px" data-add="${p.id}">${icon("cart")} Add to cart</button>
      </div></article>`;
  }

  /* ---------------- Global events ---------------- */
  function bindGlobal() {
    document.addEventListener("click", e => {
      const open = e.target.closest("[data-open]");
      if (open) { $("#" + open.dataset.open).classList.add("open"); document.body.style.overflow = "hidden"; return; }
      if (e.target.closest("[data-close]")) { $$(".drawer.open").forEach(d => d.classList.remove("open")); document.body.style.overflow = ""; return; }
      const add = e.target.closest("[data-add]");
      if (add) { e.preventDefault(); Cart.add(add.dataset.add); return; }
      const rm = e.target.closest("[data-remove]");
      if (rm) { Cart.remove(rm.dataset.remove); return; }
      if (e.target.closest(".to-top")) window.scrollTo({ top: 0, behavior: "smooth" });
    });
    document.addEventListener("keydown", e => {
      if (e.key === "Escape") { $$(".drawer.open, .lightbox.open").forEach(d => d.classList.remove("open")); document.body.style.overflow = ""; }
    });
    $$("[data-newsletter]").forEach(f => f.addEventListener("submit", e => { e.preventDefault(); f.reset(); toast("Thanks! We'll send you our latest price list."); }));
  }

  /* ---------------- Scroll effects: header, progress, parallax ---------------- */
  function initScroll() {
    const head = $(".site-head"), prog = $(".progress"), top = $(".to-top"), ring = $(".to-top .ring circle");
    const layers = $$("[data-speed]");
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let ticking = false;
    function update() {
      const y = window.scrollY, h = document.documentElement.scrollHeight - innerHeight;
      const pct = h > 0 ? y / h : 0;
      head.classList.toggle("scrolled", y > 40);
      prog.style.width = pct * 100 + "%";
      top.classList.toggle("show", y > 600);
      ring.style.strokeDashoffset = 150.8 * (1 - pct);
      if (!reduce) {
        layers.forEach(el => {
          const r = el.parentElement.getBoundingClientRect();
          if (r.bottom < -200 || r.top > innerHeight + 200) return;
          const speed = parseFloat(el.dataset.speed);
          const offset = (r.top + r.height / 2 - innerHeight / 2) * speed;
          el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)` + (el.dataset.scale ? ` scale(${el.dataset.scale})` : "");
        });
      }
      ticking = false;
    }
    addEventListener("scroll", () => { if (!ticking) { requestAnimationFrame(update); ticking = true; } }, { passive: true });
    addEventListener("resize", update);
    update();
  }

  /* Mouse parallax on hero */
  function initMouseParallax() {
    const area = $("[data-mouse]");
    if (!area || matchMedia("(hover: none)").matches) return;
    const items = $$("[data-depth]", area);
    area.addEventListener("mousemove", e => {
      const r = area.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      items.forEach(it => { const d = parseFloat(it.dataset.depth); it.style.translate = `${(-x * d).toFixed(1)}px ${(-y * d).toFixed(1)}px`; });
    });
    area.addEventListener("mouseleave", () => items.forEach(it => it.style.translate = "0 0"));
  }

  /* 3D tilt on cards */
  function initTilt() {
    if (matchMedia("(hover: none)").matches) return;
    $$("[data-tilt]").forEach(el => {
      el.addEventListener("mousemove", e => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
        el.style.transform = `perspective(900px) rotateY(${x * 8}deg) rotateX(${-y * 8}deg) translateY(-6px)`;
      });
      el.addEventListener("mouseleave", () => el.style.transform = "");
    });
  }

  /* Reveal on scroll, counters and progress bars */
  function initReveal() {
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        const el = en.target;
        el.classList.add("in");
        if (el.dataset.count !== undefined) countUp(el);
        if (el.dataset.bar !== undefined) el.style.width = el.dataset.bar + "%";
        io.unobserve(el);
      });
    }, { threshold: .15, rootMargin: "0px 0px -40px 0px" });
    $$(".reveal, [data-count], [data-bar]").forEach(el => io.observe(el));
    window.ZObserve = el => $$(".reveal, [data-count], [data-bar]", el).forEach(n => io.observe(n));
  }
  function countUp(el) {
    const end = parseFloat(el.dataset.count), dur = 2000, t0 = performance.now();
    (function step(t) {
      const k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 4);
      el.textContent = Math.round(end * e).toLocaleString();
      if (k < 1) requestAnimationFrame(step);
    })(t0);
  }

  /* Split hero heading into animated words */
  function splitWords() {
    $$("[data-split]").forEach(h => {
      let i = 0;
      const walk = node => {
        [...node.childNodes].forEach(n => {
          if (n.nodeType === 3) {
            const frag = document.createDocumentFragment();
            n.textContent.split(/(\s+)/).forEach(w => {
              if (!w.trim()) { frag.appendChild(document.createTextNode(w)); return; }
              const o = document.createElement("span"); o.className = "word";
              const s = document.createElement("span"); s.textContent = w; s.style.animationDelay = (0.3 + i++ * 0.08) + "s";
              o.appendChild(s); frag.appendChild(o);
            });
            n.replaceWith(frag);
          } else if (n.nodeType === 1 && n.tagName !== "BR") walk(n);
        });
      };
      walk(h);
    });
  }

  /* Remote photo with local fallback */
  function initPhotos() {
    $$("[data-photo]").forEach(el => {
      const url = PHOTOS[el.dataset.photo]; if (!url) return;
      const fallback = el.dataset.fallback || "img/factory-scene.svg";
      if (el.tagName === "IMG") {
        el.onerror = () => { el.onerror = null; el.src = fallback; };
        el.src = url;
      } else {
        el.style.backgroundImage = `url("${fallback}")`;
        const im = new Image();
        im.onload = () => { el.style.backgroundImage = `url("${url}")`; };
        im.src = url;
      }
    });
  }

  function renderStats() {
    const st = $("#stats");
    if (st) st.innerHTML = SITE.stats.map((s, i) => `<div class="stat reveal" style="--d:${i * .1}s"><b><span data-count="${s.value}">0</span><span class="suf">${s.suffix}</span></b><span>${s.label}</span></div>`).join("");
  }

  /* ---------------- HOME ---------------- */
  function initHome() {
    // categories
    const cg = $("#catGrid");
    if (cg) cg.innerHTML = CATEGORIES.map((c, i) => `
      <a href="products.html?cat=${c.id}" class="cat-card reveal" style="--d:${(i % 3) * .1}s" data-tilt>
        <div class="media"><span class="num">0${i + 1}</span><img src="${imgPath(c.img)}" alt="${c.name}" loading="lazy"></div>
        <div class="body"><h3>${c.name}</h3><p>${c.desc}</p><span class="link-arrow">Explore range ${icon("arrow")}</span></div>
      </a>`).join("");
    // featured products with tabs
    const fg = $("#featuredGrid"), tabs = $("#featuredTabs");
    if (fg && tabs) {
      const groups = [["all", "All"], ["pp-woven", "Woven"], ["bopp", "BOPP"], ["corrugated", "Boxes"], ["flexible", "Pouches"], ["paper", "Paper"]];
      tabs.innerHTML = groups.map(([k, t], i) => `<button class="tab ${i ? "" : "active"}" data-k="${k}">${t}</button>`).join("");
      const draw = k => {
        const list = (k === "all" ? PRODUCTS.filter(p => p.badge) : PRODUCTS.filter(p => p.cat === k)).slice(0, 8);
        fg.innerHTML = list.map(productCard).join("");
        window.ZObserve(fg);
      };
      tabs.addEventListener("click", e => {
        const b = e.target.closest(".tab"); if (!b) return;
        $$(".tab", tabs).forEach(t => t.classList.toggle("active", t === b));
        draw(b.dataset.k);
      });
      draw("all");
    }
    // industries
    const ig = $("#indGrid");
    if (ig) ig.innerHTML = INDUSTRIES.map((d, i) => `<div class="ind-card reveal" style="--d:${(i % 4) * .08}s"><div class="ic">${icon(d.icon)}</div><h3>${d.name}</h3><p>${d.text}</p></div>`).join("");
    // marquee
    const mq = $("#marquee");
    if (mq) { const words = ["PP Woven Bags", "BOPP Laminated Bags", "Corrugated Boxes", "Paper Bags", "Folding Cartons", "Stand-up Pouches", "FIBC Jumbo Bags", "Courier Bags", "Stretch Film"]; const row = words.map(w => `<span>${w}</span>`).join(""); mq.innerHTML = row + row; }
    initTestimonials();
    initFaq();
  }

  function initTestimonials() {
    const track = $("#testiTrack"), dots = $("#testiDots");
    if (!track) return;
    track.innerHTML = TESTIMONIALS.map(t => `<div class="testi"><div class="quote-ic">${icon("quote")}</div><div class="stars">★★★★★</div><blockquote>“${esc(t.quote)}”</blockquote><div class="who"><b>${esc(t.who)}</b><span>${esc(t.org)}</span></div></div>`).join("");
    dots.innerHTML = TESTIMONIALS.map((_, i) => `<button aria-label="Slide ${i + 1}" class="${i ? "" : "active"}"></button>`).join("");
    let idx = 0, timer;
    const go = i => { idx = (i + TESTIMONIALS.length) % TESTIMONIALS.length; track.style.transform = `translateX(-${idx * 100}%)`; $$("button", dots).forEach((d, j) => d.classList.toggle("active", j === idx)); };
    const auto = () => { clearInterval(timer); timer = setInterval(() => go(idx + 1), 5500); };
    dots.addEventListener("click", e => { const b = e.target.closest("button"); if (b) { go([...dots.children].indexOf(b)); auto(); } });
    let sx = null;
    track.addEventListener("touchstart", e => sx = e.touches[0].clientX, { passive: true });
    track.addEventListener("touchend", e => { if (sx === null) return; const dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) { go(idx + (dx < 0 ? 1 : -1)); auto(); } sx = null; });
    auto();
  }

  function initFaq() {
    const list = $("#faqList");
    if (!list) return;
    list.innerHTML = FAQS.map((f, i) => `<div class="faq-item ${i ? "" : "open"}"><button class="faq-q" aria-expanded="${!i}">${esc(f.q)}<span class="pm">+</span></button><div class="faq-a"><p>${esc(f.a)}</p></div></div>`).join("");
    const setH = it => { const a = $(".faq-a", it); a.style.maxHeight = it.classList.contains("open") ? a.scrollHeight + "px" : 0; };
    $$(".faq-item", list).forEach(setH);
    list.addEventListener("click", e => {
      const q = e.target.closest(".faq-q"); if (!q) return;
      const it = q.parentElement, wasOpen = it.classList.contains("open");
      $$(".faq-item", list).forEach(x => { x.classList.remove("open"); $(".faq-q", x).setAttribute("aria-expanded", "false"); setH(x); });
      if (!wasOpen) { it.classList.add("open"); q.setAttribute("aria-expanded", "true"); setH(it); }
    });
  }

  /* ---------------- PRODUCTS (shop) ---------------- */
  function initShop() {
    const params = new URLSearchParams(location.search);
    const state = { cat: params.get("cat") || "all", q: params.get("q") || "", sort: "featured" };
    const filter = $("#catFilter"), grid = $("#shopGrid"), count = $("#resultCount"), search = $("#shopSearch"), sort = $("#shopSort");
    const counts = id => id === "all" ? PRODUCTS.length : PRODUCTS.filter(p => p.cat === id).length;
    filter.innerHTML = [{ id: "all", name: "All Products" }, ...CATEGORIES].map(c => `<li><button data-cat="${c.id}">${c.name}<small>${counts(c.id)}</small></button></li>`).join("");
    search.value = state.q;
    function draw() {
      $$("button", filter).forEach(b => b.classList.toggle("active", b.dataset.cat === state.cat));
      const q = state.q.trim().toLowerCase();
      let list = PRODUCTS.filter(p => (state.cat === "all" || p.cat === state.cat) && (!q || (p.name + " " + p.desc + " " + p.material + " " + catName(p.cat)).toLowerCase().includes(q)));
      if (state.sort === "low") list = [...list].sort((a, b) => a.price - b.price);
      if (state.sort === "high") list = [...list].sort((a, b) => b.price - a.price);
      if (state.sort === "name") list = [...list].sort((a, b) => a.name.localeCompare(b.name));
      if (state.sort === "featured") list = [...list].sort((a, b) => (b.badge ? 1 : 0) - (a.badge ? 1 : 0));
      grid.innerHTML = list.length ? list.map(productCard).join("") : `<div class="empty-state" style="grid-column:1/-1">${icon("search")}<p>No products match “${esc(state.q)}”. Try another search or <a href="contact.html#quote" style="color:var(--orange);font-weight:700">request a custom quote</a>.</p></div>`;
      count.textContent = `Showing ${list.length} of ${PRODUCTS.length} products`;
      const c = CATEGORIES.find(c => c.id === state.cat);
      $("#shopTitle").textContent = c ? c.name : "All Packaging Products";
      $("#shopDesc").textContent = c ? c.desc : "Browse our complete range of bags, boxes, cartons, pouches and films. Prices shown are indicative per unit at minimum order quantity.";
      window.ZObserve(grid);
      const url = new URL(location); state.cat === "all" ? url.searchParams.delete("cat") : url.searchParams.set("cat", state.cat); history.replaceState(null, "", url);
    }
    filter.addEventListener("click", e => { const b = e.target.closest("button"); if (b) { state.cat = b.dataset.cat; draw(); } });
    search.addEventListener("input", () => { state.q = search.value; if (state.q.trim()) state.cat = "all"; draw(); });
    sort.addEventListener("change", () => { state.sort = sort.value; draw(); });
    draw();
  }

  /* ---------------- PRODUCT detail ---------------- */
  function initProduct() {
    const id = new URLSearchParams(location.search).get("id");
    const p = productById(id) || PRODUCTS[0];
    document.title = `${p.name} | ${SITE.name}`;
    $("#crumbName").textContent = p.name;
    $("#crumbCat").textContent = catName(p.cat);
    $("#crumbCat").href = "products.html?cat=" + p.cat;
    const related = PRODUCTS.filter(x => x.cat === p.cat && x.id !== p.id);
    const gallery = [p, ...related].slice(0, 4);
    const opts = { size: p.sizes[0], printing: "Plain / stock", qty: p.moq };
    $("#pd").innerHTML = `
      <div class="pd-gallery reveal left">
        <div class="pd-main" id="pdMain">${p.badge ? `<span class="badge">${p.badge}</span>` : ""}<img src="${imgPath(p.img)}" alt="${esc(p.name)}"></div>
        <div class="pd-thumbs">${gallery.map((g, i) => `<button class="${i ? "" : "active"}" data-img="${imgPath(g.img)}" aria-label="${esc(g.name)}"><img src="${imgPath(g.img)}" alt=""></button>`).join("")}</div>
      </div>
      <div class="reveal right">
        <span class="eyebrow">${catName(p.cat)}</span>
        <h1>${esc(p.name)}</h1>
        <div class="price">${fmt(p.price)} <small>/ ${p.unit} · indicative</small></div>
        <div class="pd-meta"><span class="pill">${icon("box")} MOQ ${p.moq.toLocaleString()} ${p.unit}s</span><span class="pill">${icon("truck")} Nationwide delivery</span><span class="pill">${icon("printer")} Custom print available</span></div>
        <p>${esc(p.desc)}</p>
        <div class="opt-group"><label class="lbl">Size / capacity</label><div class="chips" id="optSize">${p.sizes.map((s, i) => `<button class="chip ${i ? "" : "active"}" data-v="${esc(s)}">${esc(s)}</button>`).join("")}</div></div>
        <div class="opt-group"><label class="lbl">Printing</label><div class="chips" id="optPrint">
          <button class="chip active" data-v="Plain / stock">Plain / stock</button><button class="chip" data-v="Custom branded print">Custom branded print (+15%)</button></div></div>
        <div class="opt-group"><label class="lbl">Quantity (${p.unit}s)</label><div class="qty"><button data-step="-1" aria-label="Decrease">−</button><input id="optQty" type="number" min="${p.moq}" step="${p.moq}" value="${p.moq}" aria-label="Quantity"><button data-step="1" aria-label="Increase">+</button></div></div>
        <div class="pd-total"><span>Estimated total</span><b id="pdTotal">${fmt(p.price * p.moq)}</b></div>
        <div class="pd-actions">
          <button class="btn btn-primary" id="pdAdd">${icon("cart")} Add to cart</button>
          <a class="btn btn-wa" id="pdWa" target="_blank" rel="noopener">${icon("chat")} Order on WhatsApp</a>
          <a class="btn btn-outline" href="contact.html?product=${p.id}#quote">Request quote</a>
        </div>
        <div class="trust-row"><div>${icon("shield")} Quality checked</div><div>${icon("award")} Factory-direct price</div><div>${icon("truck")} On-time dispatch</div></div>
      </div>`;
    $("#pdTabs").innerHTML = `
      <div class="tabs" id="pdTabBtns"><button class="tab active" data-t="0">Specifications</button><button class="tab" data-t="1">Features</button><button class="tab" data-t="2">Ordering &amp; Delivery</button></div>
      <div class="tab-panel active"><table class="spec-table">
        <tr><th>Product</th><td>${esc(p.name)}</td></tr><tr><th>Category</th><td>${catName(p.cat)}</td></tr>
        <tr><th>Material</th><td>${esc(p.material)}</td></tr><tr><th>Available sizes</th><td>${p.sizes.map(esc).join(", ")}</td></tr>
        <tr><th>Minimum order</th><td>${p.moq.toLocaleString()} ${p.unit}s</td></tr><tr><th>Printing</th><td>Plain, or custom branded print (flexo / offset / rotogravure)</td></tr>
        <tr><th>Lead time</th><td>Stock: 1–3 days · Custom: 10–21 working days</td></tr></table></div>
      <div class="tab-panel"><ul class="check-list">${p.features.map(f => `<li>${icon("check")} ${esc(f)}</li>`).join("")}</ul></div>
      <div class="tab-panel"><p>Prices are indicative and depend on size, GSM/thickness, print colours and quantity. After you place an order, our sales team confirms the final price, artwork and delivery date by phone or WhatsApp before production.</p>
        <p>We deliver across Karachi and nationwide by our own vehicles and trusted cargo partners. Free delivery in Karachi on orders above ${fmt(50000)}.</p></div>`;
    const recalc = () => {
      const unit = p.price * (opts.printing.startsWith("Custom") ? 1.15 : 1);
      $("#pdTotal").textContent = fmt(unit * opts.qty);
      const msg = `Hello Zainco, I want to order:\n${p.name}\nSize: ${opts.size}\nPrinting: ${opts.printing}\nQuantity: ${opts.qty} ${p.unit}s`;
      $("#pdWa").href = `https://wa.me/${SITE.whatsapp}?text=${encodeURIComponent(msg)}`;
    };
    const chipGroup = (sel, key) => $(sel).addEventListener("click", e => { const c = e.target.closest(".chip"); if (!c) return; $$(".chip", $(sel)).forEach(x => x.classList.toggle("active", x === c)); opts[key] = c.dataset.v; recalc(); });
    chipGroup("#optSize", "size"); chipGroup("#optPrint", "printing");
    const qi = $("#optQty");
    const setQ = v => { opts.qty = Math.max(p.moq, Math.round((parseInt(v, 10) || p.moq))); qi.value = opts.qty; recalc(); };
    $$(".qty [data-step]").forEach(b => b.addEventListener("click", () => setQ(opts.qty + p.moq * +b.dataset.step)));
    qi.addEventListener("change", () => setQ(qi.value));
    $("#pdAdd").addEventListener("click", () => Cart.add(p.id, opts.qty, opts.size, opts.printing));
    $$(".pd-thumbs button").forEach(b => b.addEventListener("click", () => { $$(".pd-thumbs button").forEach(x => x.classList.toggle("active", x === b)); $("#pdMain img").src = b.dataset.img; }));
    const main = $("#pdMain"), mimg = $("img", main);
    main.addEventListener("mousemove", e => { const r = main.getBoundingClientRect(); mimg.style.transformOrigin = `${(e.clientX - r.left) / r.width * 100}% ${(e.clientY - r.top) / r.height * 100}%`; mimg.style.transform = "scale(1.8)"; });
    main.addEventListener("mouseleave", () => mimg.style.transform = "");
    $("#pdTabBtns").addEventListener("click", e => { const b = e.target.closest(".tab"); if (!b) return; $$("#pdTabBtns .tab").forEach(x => x.classList.toggle("active", x === b)); $$("#pdTabs .tab-panel").forEach((pn, i) => pn.classList.toggle("active", i === +b.dataset.t)); });
    const rel = related.length ? related : PRODUCTS.filter(x => x.id !== p.id);
    $("#relatedGrid").innerHTML = rel.slice(0, 4).map(productCard).join("");
    recalc();
    window.ZObserve(document);
  }

  /* ---------------- CART & CHECKOUT ---------------- */
  function renderCartPage() {
    const wrap = $("#cartWrap"); if (!wrap || wrap.dataset.done) return;
    const { items, subtotal, delivery, total } = Cart.totals();
    if (!items.length) {
      wrap.innerHTML = `<div class="empty-state" style="padding:80px 0">${icon("cart")}<h2>Your cart is empty</h2><p>Add bags, boxes or pouches from our catalogue to get started.</p><a href="products.html" class="btn btn-primary">Shop products ${icon("arrow")}</a></div>`;
      return;
    }
    const prevForm = $("#checkoutForm") ? Object.fromEntries(new FormData($("#checkoutForm"))) : null;
    wrap.innerHTML = `<div class="cart-layout">
      <div>
        <table class="cart-table"><thead><tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Total</th><th></th></tr></thead><tbody>
        ${items.map(i => { const p = productById(i.id); return `<tr>
          <td><div class="cart-prod"><img src="${imgPath(p.img)}" alt=""><div><b>${esc(p.name)}</b><small>${esc(i.size)} · ${esc(i.printing)}</small></div></div></td>
          <td>${fmt(Cart.unitPrice(i))}</td>
          <td><div class="qty"><button data-q="${esc(i.key)}" data-d="-1" aria-label="Decrease">−</button><input value="${i.qty}" data-qi="${esc(i.key)}" type="number" min="${p.moq}" aria-label="Quantity"><button data-q="${esc(i.key)}" data-d="1" aria-label="Increase">+</button></div><small style="color:var(--muted)">MOQ ${p.moq.toLocaleString()}</small></td>
          <td><b style="color:var(--navy)">${fmt(Cart.unitPrice(i) * i.qty)}</b></td>
          <td><button class="remove" data-remove="${esc(i.key)}" aria-label="Remove">${icon("trash")}</button></td></tr>`; }).join("")}
        </tbody></table>
        <div style="display:flex;justify-content:space-between;gap:12px;margin:18px 0 50px;flex-wrap:wrap"><a href="products.html" class="btn btn-outline btn-sm">← Continue shopping</a><button class="btn btn-sm" style="color:#ef4444" id="clearCart">${icon("trash")} Clear cart</button></div>
        <div class="form-card" id="checkout">
          <h2 style="font-size:1.7rem">Checkout details</h2>
          <p style="color:var(--muted)">Our sales team will call to confirm pricing, artwork and delivery before production.</p>
          <form id="checkoutForm" class="form-grid">
            <div class="field"><label>Full name *</label><input class="input" name="name" required></div>
            <div class="field"><label>Company / business</label><input class="input" name="company"></div>
            <div class="field"><label>Phone / WhatsApp *</label><input class="input" name="phone" required pattern="[0-9+\\s-]{10,}" placeholder="03xx xxxxxxx"></div>
            <div class="field"><label>Email</label><input class="input" name="email" type="email"></div>
            <div class="field"><label>City *</label><select name="city" required><option>Karachi</option><option>Lahore</option><option>Islamabad / Rawalpindi</option><option>Faisalabad</option><option>Multan</option><option>Hyderabad</option><option>Peshawar</option><option>Quetta</option><option>Other</option></select></div>
            <div class="field"><label>Required by</label><input class="input" name="date" type="date"></div>
            <div class="field full"><label>Delivery address *</label><textarea name="address" rows="2" required></textarea></div>
            <div class="field full"><label>Payment method</label><div class="radio-cards">
              <label class="radio-card"><input type="radio" name="payment" value="Cash on delivery" checked><div><b>Cash on delivery</b><small>Karachi & major cities</small></div></label>
              <label class="radio-card"><input type="radio" name="payment" value="Bank transfer"><div><b>Bank transfer</b><small>Account details shared on confirmation</small></div></label>
              <label class="radio-card"><input type="radio" name="payment" value="Credit terms (corporate)"><div><b>Corporate credit terms</b><small>For registered business clients</small></div></label></div></div>
            <div class="field full"><label>Notes (artwork, colours, special requirements)</label><textarea name="notes" rows="3"></textarea></div>
          </form>
        </div>
      </div>
      <aside class="summary">
        <h3>Order summary</h3>
        <div class="row"><span>Items</span><span>${items.length}</span></div>
        <div class="row"><span>Subtotal</span><span>${fmt(subtotal)}</span></div>
        <div class="row"><span>Delivery</span><span>${delivery ? fmt(delivery) : "Free"}</span></div>
        <div class="row total"><span>Estimated total</span><span>${fmt(total)}</span></div>
        <p style="font-size:.85rem;color:var(--muted)">${delivery ? `Add ${fmt(50000 - subtotal)} more for free delivery in Karachi.` : "You qualify for free delivery in Karachi."} Final price confirmed by our team.</p>
        <button class="btn btn-primary btn-block" id="placeOrder">${icon("check")} Place order</button>
        <button class="btn btn-wa btn-block" id="waOrder" style="margin-top:10px">${icon("chat")} Send order via WhatsApp</button>
        <div class="trust-row" style="grid-template-columns:1fr;margin-top:18px"><div>${icon("shield")} No payment taken online — pay on confirmation</div></div>
      </aside></div>`;
    if (prevForm) { const f = $("#checkoutForm"); Object.entries(prevForm).forEach(([k, v]) => { if (f.elements[k]) f.elements[k].value = v; }); }
  }

  function initCart() {
    renderCartPage();
    const wrap = $("#cartWrap");
    wrap.addEventListener("click", e => {
      const b = e.target.closest("[data-q]");
      if (b) { const it = Cart.items().find(i => i.key === b.dataset.q); const p = productById(it.id); Cart.setQty(it.key, it.qty + p.moq * +b.dataset.d); return; }
      if (e.target.closest("#clearCart")) { if (confirm("Remove all items from the cart?")) Cart.clear(); return; }
      if (e.target.closest("#placeOrder")) submitOrder(false);
      if (e.target.closest("#waOrder")) submitOrder(true);
    });
    wrap.addEventListener("change", e => { const i = e.target.closest("[data-qi]"); if (i) Cart.setQty(i.dataset.qi, i.value); });
  }

  function submitOrder(viaWhatsApp) {
    const form = $("#checkoutForm");
    if (!form.reportValidity()) { form.scrollIntoView({ behavior: "smooth", block: "center" }); return; }
    const d = Object.fromEntries(new FormData(form));
    const { items, subtotal, delivery, total } = Cart.totals();
    const no = "ZP-" + Date.now().toString().slice(-7);
    const lines = items.map((i, n) => { const p = productById(i.id); return `${n + 1}. ${p.name} — ${i.size}, ${i.printing}, Qty ${i.qty} = ${fmt(Cart.unitPrice(i) * i.qty)}`; });
    const msg = [`*New Order ${no}*`, ``, ...lines, ``, `Subtotal: ${fmt(subtotal)}`, `Delivery: ${delivery ? fmt(delivery) : "Free"}`, `*Estimated total: ${fmt(total)}*`, ``,
      `Name: ${d.name}`, d.company ? `Company: ${d.company}` : "", `Phone: ${d.phone}`, d.email ? `Email: ${d.email}` : "", `City: ${d.city}`, `Address: ${d.address}`,
      d.date ? `Required by: ${d.date}` : "", `Payment: ${d.payment}`, d.notes ? `Notes: ${d.notes}` : ""].filter(Boolean).join("\n");
    const orders = store.get("zainco_orders", []);
    orders.push({ no, date: new Date().toISOString(), items, total, customer: d });
    store.set("zainco_orders", orders);
    const waUrl = `https://wa.me/${SITE.whatsapp}?text=${encodeURIComponent(msg)}`;
    const mailUrl = `mailto:${SITE.email}?subject=${encodeURIComponent("Order " + no)}&body=${encodeURIComponent(msg.replace(/\*/g, ""))}`;
    if (viaWhatsApp) window.open(waUrl, "_blank", "noopener");
    const wrap = $("#cartWrap");
    wrap.dataset.done = "1";
    Cart.clear();
    wrap.innerHTML = `<div class="order-done"><div class="tick">${icon("check", "icon")}</div>
      <h2>Thank you, ${esc(d.name)}!</h2><p>Your order <b>${no}</b> has been prepared (estimated total <b>${fmt(total)}</b>). To make sure it reaches our sales team, please send it using one of the buttons below — we'll call you on <b>${esc(d.phone)}</b> to confirm.</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin:26px 0"><a class="btn btn-wa" href="${waUrl}" target="_blank" rel="noopener">${icon("chat")} Send on WhatsApp</a><a class="btn btn-dark" href="${mailUrl}">${icon("mail")} Send by email</a></div>
      <a href="products.html" class="link-arrow">Continue shopping ${icon("arrow")}</a></div>`;
    wrap.scrollIntoView({ behavior: "smooth" });
  }

  /* ---------------- PORTFOLIO ---------------- */
  function initPortfolio() {
    const grid = $("#portGrid"), tabs = $("#portTabs");
    const cats = ["all", ...new Set(PROJECTS.map(p => p.cat))];
    tabs.innerHTML = cats.map((c, i) => `<button class="tab ${i ? "" : "active"}" data-k="${c}">${c === "all" ? "All Work" : catName(c)}</button>`).join("");
    grid.innerHTML = PROJECTS.map((p, i) => `<div class="port-item reveal zoom" style="--d:${(i % 3) * .1}s" data-cat="${p.cat}" data-i="${i}">
      <img src="${imgPath(p.img)}" alt="${esc(p.title)}" loading="lazy"><span class="zoom">${icon("zoom")}</span>
      <div class="over"><small>${catName(p.cat)}</small><h3>${esc(p.title)}</h3><p>${esc(p.text)}</p></div></div>`).join("");
    tabs.addEventListener("click", e => {
      const b = e.target.closest(".tab"); if (!b) return;
      $$(".tab", tabs).forEach(t => t.classList.toggle("active", t === b));
      $$(".port-item", grid).forEach(it => { const show = b.dataset.k === "all" || it.dataset.cat === b.dataset.k; it.classList.toggle("hide", !show); if (show) { it.classList.remove("in"); requestAnimationFrame(() => requestAnimationFrame(() => it.classList.add("in"))); } });
    });
    const lb = $("#lightbox");
    grid.addEventListener("click", e => {
      const it = e.target.closest(".port-item"); if (!it) return;
      const p = PROJECTS[+it.dataset.i];
      $(".lb-inner", lb).innerHTML = `<img src="${imgPath(p.img)}" alt="${esc(p.title)}" style="width:100%;height:100%;object-fit:cover"><div class="lb-text"><span class="eyebrow">${catName(p.cat)}</span><h2>${esc(p.title)}</h2><p style="color:var(--muted)">${esc(p.text)}</p><p><b style="color:var(--navy)">Client:</b> ${esc(p.client)}</p><a class="btn btn-primary" href="products.html?cat=${p.cat}">View similar products ${icon("arrow")}</a></div>`;
      lb.classList.add("open");
    });
    lb.addEventListener("click", e => { if (e.target === lb || e.target.closest(".close")) lb.classList.remove("open"); });
  }

  /* ---------------- CONTACT ---------------- */
  function initContact() {
    const sel = $("#quoteProduct");
    if (sel) {
      sel.innerHTML = `<option value="">Select a product category</option>` + CATEGORIES.map(c => `<optgroup label="${c.name}">${PRODUCTS.filter(p => p.cat === c.id).map(p => `<option value="${esc(p.name)}">${esc(p.name)}</option>`).join("")}</optgroup>`).join("") + `<option value="Other / custom packaging">Other / custom packaging</option>`;
      const pre = productById(new URLSearchParams(location.search).get("product"));
      if (pre) sel.value = pre.name;
    }
    const map = $("#map");
    if (map) map.innerHTML = `<iframe title="Zainco Packaging location" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=${encodeURIComponent(SITE.mapQuery)}&z=14&output=embed"></iframe>`;
    const form = $("#quoteForm");
    if (form) form.addEventListener("submit", e => {
      e.preventDefault();
      const d = Object.fromEntries(new FormData(form));
      const msg = [`*Quote request*`, `Name: ${d.name}`, d.company ? `Company: ${d.company}` : "", `Phone: ${d.phone}`, d.email ? `Email: ${d.email}` : "", `Product: ${d.product || "-"}`, d.qty ? `Quantity: ${d.qty}` : "", d.size ? `Size: ${d.size}` : "", d.message ? `Details: ${d.message}` : ""].filter(Boolean).join("\n");
      const waUrl = `https://wa.me/${SITE.whatsapp}?text=${encodeURIComponent(msg)}`;
      const mailUrl = `mailto:${SITE.email}?subject=${encodeURIComponent("Quote request — " + d.name)}&body=${encodeURIComponent(msg.replace(/\*/g, ""))}`;
      window.open(waUrl, "_blank", "noopener");
      form.innerHTML = `<div class="order-done"><div class="tick">${icon("check")}</div><h3>Your quote request is ready</h3><p style="color:var(--muted)">We opened WhatsApp with your request — just press send. Prefer email?</p><a class="btn btn-dark" href="${mailUrl}">${icon("mail")} Send by email instead</a></div>`;
    });
  }

  /* ---------------- Boot ---------------- */
  window.ZObserve = () => {}; // replaced by initReveal(); page renderers may call it earlier
  renderLayout();
  splitWords();
  bindGlobal();
  initPhotos();
  renderStats();
  if (page === "home") initHome();
  if (page === "about") { initFaq(); }
  if (page === "products") initShop();
  if (page === "product") initProduct();
  if (page === "cart") initCart();
  if (page === "portfolio") initPortfolio();
  if (page === "contact") initContact();
  $$("[data-icon]").forEach(el => el.insertAdjacentHTML("afterbegin", icon(el.dataset.icon)));
  Cart.sync();
  initReveal();
  initScroll();
  initMouseParallax();
  initTilt();
  if (page === "cart" && location.hash === "#checkout") setTimeout(() => { const c = $("#checkout"); if (c) c.scrollIntoView({ behavior: "smooth" }); }, 300);

  const pre = $(".preloader");
  const hidePre = () => pre && pre.classList.add("done");
  if (document.readyState === "complete") setTimeout(hidePre, 300); else addEventListener("load", () => setTimeout(hidePre, 300));
  setTimeout(hidePre, 2500); // never block the page on slow remote photos
})();
