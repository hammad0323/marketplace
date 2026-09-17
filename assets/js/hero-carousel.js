/**
 * Autoplay hero banner carousel. Usage:
 *   WH.initHeroCarousel(document.querySelector('.hero-carousel'));
 * Expects markup: root > .hero-slide[.active on first] (any count),
 * plus .hero-arrow.prev / .hero-arrow.next and a .hero-dots container
 * already in the DOM (dots are built automatically if empty).
 */
window.WH = window.WH || {};

WH.initHeroCarousel = function (root, opts) {
  if (!root) return;
  opts = opts || {};
  var interval = opts.interval || 6000;
  var slides = Array.prototype.slice.call(root.querySelectorAll('.hero-slide'));
  if (slides.length <= 1) return; // nothing to rotate

  var dotsWrap = root.querySelector('.hero-dots');
  var current = slides.findIndex(function (s) { return s.classList.contains('active'); });
  if (current < 0) current = 0;
  var timer = null;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (dotsWrap && !dotsWrap.children.length) {
    slides.forEach(function (_, i) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'hero-dot' + (i === current ? ' active' : '');
      dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
      dot.addEventListener('click', function () { goTo(i); restart(); });
      dotsWrap.appendChild(dot);
    });
  }

  function render() {
    slides.forEach(function (s, i) { s.classList.toggle('active', i === current); });
    if (dotsWrap) {
      Array.prototype.forEach.call(dotsWrap.children, function (dot, i) {
        dot.classList.toggle('active', i === current);
      });
    }
  }

  function goTo(i) {
    current = (i + slides.length) % slides.length;
    render();
  }

  function next() { goTo(current + 1); }
  function prev() { goTo(current - 1); }

  function start() {
    if (reduceMotion) return;
    stop();
    timer = setInterval(next, interval);
  }
  function stop() { if (timer) { clearInterval(timer); timer = null; } }
  function restart() { start(); }

  var nextBtn = root.querySelector('.hero-arrow.next');
  var prevBtn = root.querySelector('.hero-arrow.prev');
  if (nextBtn) nextBtn.addEventListener('click', function () { next(); restart(); });
  if (prevBtn) prevBtn.addEventListener('click', function () { prev(); restart(); });

  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);

  render();
  start();
};
