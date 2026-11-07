// October, second design: mobile menu, menu tabs, fade-in on scroll.
// The cookie notice and the gallery lightbox come from /js/main.js.

// Mobile menu
(function () {
  var header = document.querySelector('[data-oc-header]');
  if (!header) return;
  var btn = header.querySelector('.oc-burger');
  function set(open) {
    header.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    document.body.style.overflow = open ? 'hidden' : '';
  }
  btn.addEventListener('click', function () { set(!header.classList.contains('is-open')); });
  header.querySelectorAll('.oc-nav a').forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
})();

// Menu tabs. Each tab is also a real link (?tab=lunch), so they work without JS.
(function () {
  var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-oc-tab]'));
  if (!tabs.length) return;
  function show(key, push) {
    tabs.forEach(function (t) {
      var on = t.dataset.ocTab === key;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      var panel = document.getElementById('panel-' + t.dataset.ocTab);
      if (panel) panel.hidden = !on;
    });
    if (push && history.replaceState) history.replaceState(null, '', '?tab=' + key);
  }
  tabs.forEach(function (t) {
    t.addEventListener('click', function (e) { e.preventDefault(); show(t.dataset.ocTab, true); });
  });
  // A link like menu.php#dumplings opens the tab that holds that section.
  if (location.hash) {
    var target = document.querySelector(location.hash);
    var panel = target && target.closest('.oc-panel');
    if (panel) show(panel.id.replace('panel-', ''), false);
  }
})();

// Fade sections in as they scroll into view.
(function () {
  var els = document.querySelectorAll('.oc-reveal');
  if (!('IntersectionObserver' in window)) {
    els.forEach(function (el) { el.classList.add('is-in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px' });
  els.forEach(function (el) { io.observe(el); });
})();
