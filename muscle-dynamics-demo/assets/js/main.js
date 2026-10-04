/* Muscle Dynamics — site interactions (no dependencies) */
(function () {
  "use strict";

  var WA_NUMBER = "923155065255";
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  document.documentElement.classList.add("js");

  /* ---------- Header + mobile nav ---------- */
  var header = document.querySelector(".site-header");
  var toggle = document.querySelector(".menu-toggle");
  function onScrollHeader() {
    if (header) header.classList.toggle("is-scrolled", window.scrollY > 30);
  }
  if (toggle) {
    toggle.addEventListener("click", function () {
      var open = document.body.classList.toggle("nav-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.querySelectorAll(".mobile-nav a").forEach(function (a) {
      a.addEventListener("click", function () {
        document.body.classList.remove("nav-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Parallax ---------- */
  var layers = Array.prototype.slice.call(document.querySelectorAll("[data-parallax]"));
  var ticking = false;
  function updateParallax() {
    var vh = window.innerHeight;
    layers.forEach(function (el) {
      var host = el.parentElement;
      var rect = host.getBoundingClientRect();
      if (rect.bottom < -100 || rect.top > vh + 100) return;
      var speed = parseFloat(el.getAttribute("data-parallax")) || 0.2;
      var offset = (rect.top + rect.height / 2 - vh / 2) * -speed;
      el.style.transform = "translate3d(0," + offset.toFixed(1) + "px,0)";
    });
    ticking = false;
  }
  function onScroll() {
    onScrollHeader();
    if (!reduceMotion && layers.length && !ticking) {
      ticking = true;
      window.requestAnimationFrame(updateParallax);
    }
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("resize", onScroll);
  onScroll();

  /* ---------- Reveal on scroll ---------- */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !reduceMotion) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("in"); });
  }

  /* ---------- Count-up (final value is already in the HTML for crawlers / no-JS) ---------- */
  var counters = document.querySelectorAll("[data-count]");
  if ("IntersectionObserver" in window && !reduceMotion) {
    var co = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        co.unobserve(e.target);
        var el = e.target;
        var target = parseFloat(el.getAttribute("data-count"));
        var decimals = (el.getAttribute("data-count").split(".")[1] || "").length;
        var suffix = el.getAttribute("data-suffix") || "";
        var start = null, dur = 1400;
        function step(ts) {
          if (!start) start = ts;
          var p = Math.min((ts - start) / dur, 1);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = (target * eased).toFixed(decimals) + suffix;
          if (p < 1) requestAnimationFrame(step);
          else el.textContent = target.toFixed(decimals) + suffix;
        }
        requestAnimationFrame(step);
      });
    }, { threshold: 0.6 });
    counters.forEach(function (el) { co.observe(el); });
  }

  /* ---------- Live open / closed status (Asia/Karachi) ----------
     Mon–Sat: Gents 06:00–12:00, Ladies 12:00–16:00, Gents 16:00–24:00. Sunday closed. */
  function karachiNow() {
    try {
      var parts = new Intl.DateTimeFormat("en-GB", {
        timeZone: "Asia/Karachi", weekday: "short", hour: "2-digit", minute: "2-digit", hour12: false
      }).formatToParts(new Date());
      var map = {};
      parts.forEach(function (p) { map[p.type] = p.value; });
      var days = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
      return { day: days[map.weekday], mins: (parseInt(map.hour, 10) % 24) * 60 + parseInt(map.minute, 10) };
    } catch (err) {
      var d = new Date();
      return { day: d.getDay(), mins: d.getHours() * 60 + d.getMinutes() };
    }
  }
  function computeStatus() {
    var now = karachiNow();
    var sessions = [
      { from: 360, to: 720, who: "gents", label: "Gents session", until: "12:00 PM" },
      { from: 720, to: 960, who: "ladies", label: "Ladies session", until: "4:00 PM" },
      { from: 960, to: 1440, who: "gents", label: "Gents session", until: "12:00 AM" }
    ];
    if (now.day !== 0) {
      for (var i = 0; i < sessions.length; i++) {
        var s = sessions[i];
        if (now.mins >= s.from && now.mins < s.to) {
          return { open: true, who: s.who, text: "Open now · " + s.label + " until " + s.until };
        }
      }
      return { open: false, text: "Closed now · Opens today at 6:00 AM" };
    }
    return { open: false, text: "Closed today (Sunday) · Opens Monday at 6:00 AM" };
  }
  var statusEls = document.querySelectorAll("[data-live-status]");
  if (statusEls.length) {
    var st = computeStatus();
    statusEls.forEach(function (el) {
      el.classList.add(st.open ? "is-open" : "is-closed");
      var label = el.querySelector(".status-text");
      if (label) label.textContent = st.text;
    });
    if (st.open) {
      document.querySelectorAll(".timing-card." + st.who).forEach(function (c) { c.classList.add("is-now"); });
    } else {
      document.querySelectorAll(".timing-card.closed").forEach(function (c) {
        if (karachiNow().day === 0) c.classList.add("is-now");
      });
    }
  }

  /* ---------- Contact form → WhatsApp ---------- */
  document.querySelectorAll("form[data-wa-form]").forEach(function (form) {
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var f = form.elements;
      var name = (f.name && f.name.value || "").trim();
      var phone = (f.phone && f.phone.value || "").trim();
      if (!name || !phone) {
        (name ? f.phone : f.name).focus();
        return;
      }
      var lines = [
        "Hi Muscle Dynamics! 👋",
        "Name: " + name,
        "Phone: " + phone
      ];
      if (f.email && f.email.value.trim()) lines.push("Email: " + f.email.value.trim());
      if (f.interest && f.interest.value) lines.push("Interested in: " + f.interest.value);
      if (f.message && f.message.value.trim()) lines.push("Message: " + f.message.value.trim());
      var url = "https://wa.me/" + WA_NUMBER + "?text=" + encodeURIComponent(lines.join("\n"));
      window.open(url, "_blank", "noopener");
    });
  });

  /* ---------- Gallery lightbox ---------- */
  var lb = document.querySelector(".lightbox");
  if (lb) {
    var lbImg = lb.querySelector("img"), lbCap = lb.querySelector("p");
    document.querySelectorAll(".g-item[data-full]").forEach(function (item) {
      item.addEventListener("click", function () {
        lbImg.src = item.getAttribute("data-full");
        lbImg.alt = item.getAttribute("data-alt") || "";
        lbCap.textContent = item.getAttribute("data-alt") || "";
        lb.classList.add("open");
        lb.querySelector("button").focus();
      });
    });
    function closeLb() { lb.classList.remove("open"); }
    lb.addEventListener("click", function (e) { if (e.target === lb || e.target.closest("button")) closeLb(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeLb(); });
  }

  /* ---------- Footer year ---------- */
  document.querySelectorAll("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });
})();
