/* =========================================================
   4Brothers — interactions & animations
   ========================================================= */
(function () {
  "use strict";

  const $ = (s, ctx = document) => ctx.querySelector(s);
  const $$ = (s, ctx = document) => Array.from(ctx.querySelectorAll(s));
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  /* ---------- Preloader ---------- */
  const finishLoading = () => {
    const pre = $("#preloader");
    if (pre) pre.classList.add("done");
    document.body.classList.add("loaded");
  };
  window.addEventListener("load", () => setTimeout(finishLoading, 600));
  setTimeout(finishLoading, 4000); // safety net if a CDN asset stalls

  /* ---------- Year ---------- */
  const yearEl = $("#year");
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  /* ---------- Navbar, progress bar, back-to-top ---------- */
  const nav = $("#mainNav");
  const progress = $(".scroll-progress");
  const toTop = $(".to-top");

  /* ---------- Scroll parallax ---------- */
  const parallaxLayers = $$(".parallax-layer");

  let ticking = false;
  const onScroll = () => {
    const y = window.scrollY;
    const docH = document.documentElement.scrollHeight - window.innerHeight;
    nav.classList.toggle("scrolled", y > 40);
    toTop.classList.toggle("show", y > 600);
    progress.style.width = (docH > 0 ? (y / docH) * 100 : 0) + "%";

    if (!reduceMotion) {
      const vh = window.innerHeight;
      parallaxLayers.forEach((el) => {
        const parent = el.parentElement.getBoundingClientRect();
        if (parent.bottom < -200 || parent.top > vh + 200) return;
        const speed = parseFloat(el.dataset.speed) || 0.2;
        // offset relative to the centre of the viewport
        const offset = (parent.top + parent.height / 2 - vh / 2) * -speed;
        el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
      });
    }
    ticking = false;
  };
  window.addEventListener("scroll", () => {
    if (!ticking) { requestAnimationFrame(onScroll); ticking = true; }
  }, { passive: true });
  onScroll();

  /* ---------- Close mobile menu on link click ---------- */
  $$("#navMenu .nav-link, #navMenu .btn").forEach((link) => {
    link.addEventListener("click", () => {
      const menu = $("#navMenu");
      if (menu.classList.contains("show")) bootstrap.Collapse.getOrCreateInstance(menu).hide();
    });
  });

  /* ---------- Mouse parallax in hero ---------- */
  const hero = $(".hero");
  const mouseLayers = $$(".mouse-layer");
  if (hero && finePointer && !reduceMotion) {
    let mx = 0, my = 0, cx = 0, cy = 0;
    hero.addEventListener("mousemove", (e) => {
      const r = hero.getBoundingClientRect();
      mx = (e.clientX - r.left) / r.width - 0.5;
      my = (e.clientY - r.top) / r.height - 0.5;
    });
    hero.addEventListener("mouseleave", () => { mx = 0; my = 0; });
    const animateMouse = () => {
      cx += (mx - cx) * 0.08;
      cy += (my - cy) * 0.08;
      mouseLayers.forEach((el) => {
        const d = parseFloat(el.dataset.depth) || 0.5;
        el.style.transform = `translate3d(${(cx * d * 60).toFixed(2)}px, ${(cy * d * 60).toFixed(2)}px, 0) rotate(var(--r, 0deg))`;
      });
      requestAnimationFrame(animateMouse);
    };
    animateMouse();
  }

  /* ---------- Reveal on scroll ---------- */
  const revealEls = $$(".reveal, .reveal-left, .reveal-right, .reveal-zoom");
  const revealObs = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const delay = parseInt(el.dataset.delay || 0, 10);
      // hero items wait for the preloader to finish
      const extra = el.closest(".hero") && !document.body.classList.contains("loaded") ? 700 : 0;
      setTimeout(() => el.classList.add("in-view"), delay + extra);
      revealObs.unobserve(el);
    });
  }, { threshold: 0.15, rootMargin: "0px 0px -40px 0px" });
  revealEls.forEach((el) => revealObs.observe(el));

  /* ---------- Hero title words stagger ---------- */
  $$(".hero-title .word").forEach((w, i) => { w.style.transitionDelay = (0.15 + i * 0.09) + "s"; });

  /* ---------- Counters ---------- */
  const animateCounter = (el) => {
    const target = parseFloat(el.dataset.target);
    const decimals = parseInt(el.dataset.decimals || 0, 10);
    const suffix = el.dataset.suffix || "";
    const duration = 2000;
    const start = performance.now();
    const step = (now) => {
      const p = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 4);
      el.textContent = (target * eased).toFixed(decimals) + suffix;
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };
  const counterObs = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) { animateCounter(entry.target); counterObs.unobserve(entry.target); }
    });
  }, { threshold: 0.6 });
  $$(".counter").forEach((c) => counterObs.observe(c));

  /* ---------- Skill bars & process line ---------- */
  const fillObs = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      if (el.classList.contains("process-line")) el.classList.add("in");
      else el.style.width = el.dataset.width + "%";
      fillObs.unobserve(el);
    });
  }, { threshold: 0.5 });
  $$(".skill .bar span, .process-line").forEach((el) => fillObs.observe(el));

  /* ---------- Typed text ---------- */
  const typedEl = $(".typed");
  if (typedEl) {
    const words = JSON.parse(typedEl.dataset.words);
    let wi = 0, ci = 0, deleting = false;
    const type = () => {
      const word = words[wi];
      typedEl.textContent = word.slice(0, ci);
      if (!deleting && ci < word.length) { ci++; setTimeout(type, 85); }
      else if (!deleting) { deleting = true; setTimeout(type, 1600); }
      else if (ci > 0) { ci--; setTimeout(type, 40); }
      else { deleting = false; wi = (wi + 1) % words.length; setTimeout(type, 300); }
    };
    setTimeout(type, 1500);
  }

  /* ---------- 3D tilt + spotlight on service cards ---------- */
  if (finePointer && !reduceMotion) {
    $$(".tilt").forEach((card) => {
      card.addEventListener("mousemove", (e) => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width;
        const y = (e.clientY - r.top) / r.height;
        card.style.transform = `perspective(900px) rotateX(${(0.5 - y) * 12}deg) rotateY(${(x - 0.5) * 12}deg) translateY(-6px)`;
        card.style.setProperty("--mx", x * 100 + "%");
        card.style.setProperty("--my", y * 100 + "%");
      });
      card.addEventListener("mouseleave", () => { card.style.transform = ""; });
    });

    /* magnetic buttons */
    $$(".magnetic").forEach((btn) => {
      btn.addEventListener("mousemove", (e) => {
        const r = btn.getBoundingClientRect();
        const x = e.clientX - r.left - r.width / 2;
        const y = e.clientY - r.top - r.height / 2;
        btn.style.transform = `translate(${x * 0.25}px, ${y * 0.35}px)`;
      });
      btn.addEventListener("mouseleave", () => { btn.style.transform = ""; });
    });

    /* custom cursor */
    const dot = $(".cursor-dot");
    const ring = $(".cursor-ring");
    let rx = 0, ry = 0, tx = 0, ty = 0;
    window.addEventListener("mousemove", (e) => {
      tx = e.clientX; ty = e.clientY;
      dot.style.transform = `translate(${tx}px, ${ty}px) translate(-50%, -50%)`;
    });
    const followCursor = () => {
      rx += (tx - rx) * 0.18; ry += (ty - ry) * 0.18;
      ring.style.transform = `translate(${rx}px, ${ry}px) translate(-50%, -50%)`;
      requestAnimationFrame(followCursor);
    };
    followCursor();
    $$("a, button, .tilt, .p-card, input, select, textarea").forEach((el) => {
      el.addEventListener("mouseenter", () => ring.classList.add("hover"));
      el.addEventListener("mouseleave", () => ring.classList.remove("hover"));
    });
  }

  /* ---------- Portfolio filter ---------- */
  const filterBtns = $$(".filter-btns button");
  const items = $$(".p-item");
  filterBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      filterBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      const f = btn.dataset.filter;
      items.forEach((item) => {
        const show = f === "all" || item.dataset.cat === f;
        item.classList.toggle("hide", !show);
        if (show) $(".reveal-zoom", item)?.classList.add("in-view");
      });
    });
  });

  /* ---------- Quote form → WhatsApp ---------- */
  const form = $("#quoteForm");
  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      if (!form.checkValidity()) { form.classList.add("was-validated"); return; }
      const msg = [
        "Hello 4Brothers! I'd like a quote.",
        "",
        "Name: " + $("#qName").value.trim(),
        "Phone: " + $("#qPhone").value.trim(),
        "Service: " + $("#qService").value,
        "Quantity: " + ($("#qQty").value || "-"),
        "Details: " + ($("#qMsg").value.trim() || "-"),
      ].join("\n");
      window.open("https://wa.me/923041260552?text=" + encodeURIComponent(msg), "_blank", "noopener");
      form.reset();
      form.classList.remove("was-validated");
    });
  }
})();
