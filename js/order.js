// Click and collect: the basket, the dish pop-up and the checkout step.
// Prices here are only for show; the server works the real total out again.
(function () {
  'use strict';

  var dataEl = document.getElementById('order-data');
  if (!dataEl) return;
  var DATA = JSON.parse(dataEl.textContent);
  var ITEMS = DATA.items;
  var STORE = 'october-basket';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var money = function (n) { return '€' + n.toFixed(2); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

  // ---------- basket state ----------
  var basket = [];
  function load() {
    var raw = DATA.restore;
    if (!raw) { try { raw = localStorage.getItem(STORE); } catch (e) {} }
    try { basket = JSON.parse(raw || '[]') || []; } catch (e) { basket = []; }
    basket = basket.filter(function (l) { return l && ITEMS[l.key]; });
  }
  function save() { try { localStorage.setItem(STORE, JSON.stringify(basket)); } catch (e) {} }

  function unitPrice(line) {
    var it = ITEMS[line.key];
    var p = (it.variants[line.variant] || it.variants[0]).price;
    (line.addons || []).forEach(function (a) { if (it.addons[a]) p += it.addons[a].price; });
    return p;
  }
  function lineLabel(line) {
    var it = ITEMS[line.key], bits = [];
    var v = it.variants[line.variant] || it.variants[0];
    if (v.label) bits.push(v.label);
    if (line.choice) bits.push(line.choice);
    (line.addons || []).forEach(function (a) { if (it.addons[a]) bits.push(it.addons[a].label); });
    return bits.join(', ');
  }
  function sameLine(a, b) {
    return a.key === b.key && a.variant === b.variant && (a.choice || '') === (b.choice || '') &&
      (a.note || '') === (b.note || '') && JSON.stringify(a.addons || []) === JSON.stringify(b.addons || []);
  }
  function add(line) {
    var hit = basket.filter(function (l) { return sameLine(l, line); })[0];
    if (hit) hit.qty = Math.min(DATA.maxQty, hit.qty + line.qty);
    else basket.push(line);
    save(); render();
  }
  function totals() {
    var t = 0, c = 0;
    basket.forEach(function (l) { t += unitPrice(l) * l.qty; c += l.qty; });
    return { total: t, count: c };
  }

  // ---------- rendering ----------
  var linesEl = $('[data-basket-lines]');
  var checkoutStarted = !!window.orderHadErrors;

  function render() {
    var t = totals();
    linesEl.innerHTML = basket.map(function (l, i) {
      var it = ITEMS[l.key], label = lineLabel(l);
      return '<li class="ob-line">' +
        '<div class="ob-line-main"><span class="ob-line-name">' + esc(it.name) + '</span>' +
        (label ? '<span class="ob-line-opt">' + esc(label) + '</span>' : '') +
        (l.note ? '<span class="ob-line-opt">“' + esc(l.note) + '”</span>' : '') + '</div>' +
        '<div class="qty qty-s" role="group" aria-label="Quantity of ' + esc(it.name) + '">' +
        '<button type="button" data-line="' + i + '" data-step="-1" aria-label="One fewer">' + (l.qty === 1 ? '×' : '−') + '</button>' +
        '<output>' + l.qty + '</output>' +
        '<button type="button" data-line="' + i + '" data-step="1" aria-label="One more">+</button></div>' +
        '<span class="ob-line-price">' + money(unitPrice(l) * l.qty) + '</span></li>';
    }).join('');

    var empty = basket.length === 0;
    $('[data-basket-empty]').hidden = !empty;
    $('[data-basket-total]').hidden = empty;
    $$('[data-total]').forEach(function (el) { el.textContent = money(t.total); });
    $$('[data-count]').forEach(function (el) { el.textContent = t.count; });

    // Badges on the dishes already in the basket
    var counts = {};
    basket.forEach(function (l) { counts[l.key] = (counts[l.key] || 0) + l.qty; });
    $$('[data-count-for]').forEach(function (el) {
      var n = counts[el.getAttribute('data-count-for')] || 0;
      el.hidden = !n; el.textContent = n ? n + ' in order' : '';
    });

    var bar = $('[data-basket-open]');
    bar.hidden = empty;

    var goBtn = $('[data-go-checkout]'), placeBtn = $('[data-place]'), fields = $('[data-checkout-fields]');
    var belowMin = DATA.minOrder > 0 && t.total < DATA.minOrder;
    goBtn.disabled = empty || !DATA.open || belowMin;
    goBtn.textContent = belowMin ? 'Minimum order ' + money(DATA.minOrder) : 'Go to checkout';
    if (empty) checkoutStarted = false;
    fields.hidden = !checkoutStarted || empty;
    goBtn.hidden = checkoutStarted && !empty;
    placeBtn.hidden = !checkoutStarted || empty || !DATA.open;

    checkLunch();
    $('[data-basket-field]').value = JSON.stringify(basket);
  }

  // Lunch dishes can only be collected before lunchUntil
  function checkLunch() {
    var warn = $('[data-lunch-warn]'), sel = $('[data-time]');
    var hasLunch = basket.some(function (l) { return ITEMS[l.key].menu === 'lunch'; });
    var time = sel && sel.value === 'asap' ? (sel.options[1] ? sel.options[1].value : '') : (sel ? sel.value : '');
    var bad = hasLunch && time && time >= DATA.lunchUntil;
    warn.hidden = !bad;
    if (bad) warn.textContent = 'Lunch menu dishes are for collection before ' + DATA.lunchUntil + '. Choose an earlier time or remove them.';
    var place = $('[data-place]');
    if (place) place.disabled = !!bad;
    // Mark lunch times that would not work
    if (sel) $$('option', sel).forEach(function (o) {
      var v = o.value === 'asap' ? (sel.options[1] ? sel.options[1].value : '') : o.value;
      o.disabled = hasLunch && v >= DATA.lunchUntil;
    });
  }

  linesEl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-line]');
    if (!b) return;
    var i = +b.getAttribute('data-line'), step = +b.getAttribute('data-step');
    basket[i].qty += step;
    if (basket[i].qty < 1) basket.splice(i, 1);
    else basket[i].qty = Math.min(DATA.maxQty, basket[i].qty);
    save(); render();
  });

  var timeSel = $('[data-time]');
  if (timeSel) timeSel.addEventListener('change', checkLunch);

  $('[data-go-checkout]').addEventListener('click', function () {
    checkoutStarted = true; render();
    var f = $('#name'); if (f) f.focus();
  });

  $('[data-checkout]').addEventListener('submit', function (e) {
    $('[data-basket-field]').value = JSON.stringify(basket);
    var ok = true;
    ['name', 'phone', 'email'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el.value.trim() || (id === 'email' && el.value.indexOf('@') < 1)) { el.setAttribute('aria-invalid', 'true'); ok = false; }
      else el.removeAttribute('aria-invalid');
    });
    if (!ok) { e.preventDefault(); var bad = $('[aria-invalid="true"]', this); if (bad) bad.focus(); return; }
    var btn = $('[data-place]'); btn.disabled = true; btn.textContent = 'Sending your order…';
    try { localStorage.removeItem(STORE); } catch (err) {}
  });

  // ---------- basket drawer on phones ----------
  var aside = $('#basket');
  function openBasket() { aside.classList.add('is-open'); document.body.classList.add('basket-open'); }
  function closeBasket() { aside.classList.remove('is-open'); document.body.classList.remove('basket-open'); }
  $('[data-basket-open]').addEventListener('click', openBasket);
  $('[data-basket-close]').addEventListener('click', closeBasket);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeBasket(); });

  // ---------- dish pop-up ----------
  var dlg = $('[data-item-dialog]');
  var cur = null;

  function radios(name, opts, checked) {
    return opts.map(function (o, i) {
      return '<label class="od-opt"><input type="radio" name="' + name + '" value="' + i + '"' + (i === checked ? ' checked' : '') + '>' +
        '<span>' + esc(o.text) + '</span>' + (o.price ? '<span class="od-opt-price">' + o.price + '</span>' : '') + '</label>';
    }).join('');
  }

  function dialogPrice() {
    if (!cur) return;
    var p = unitPrice(cur) * cur.qty;
    $('[data-od-price]').textContent = money(p);
    $('[data-od-qty]').textContent = cur.qty;
  }

  function openItem(key) {
    var it = ITEMS[key];
    cur = { key: key, variant: 0, choice: '', addons: [], note: '', qty: 1 };
    var card = $('[data-add="' + key + '"]');
    $('[data-od-name]').textContent = it.name;
    var desc = card ? card.querySelector('.oi-desc') : null;
    $('[data-od-desc]').textContent = desc ? desc.textContent : '';

    var v = $('[data-od-variants]'), c = $('[data-od-choices]'), a = $('[data-od-addons]');
    v.innerHTML = it.variants.length > 1 ? '<p class="od-label">Size</p>' + radios('variant', it.variants.map(function (x) {
      return { text: x.label || 'Regular', price: money(x.price) };
    }), 0) : '';
    c.innerHTML = it.choices.length ? '<p class="od-label">Choose one <span class="od-req">Required</span></p>' + radios('choice', it.choices.map(function (x) {
      return { text: x };
    }), -1) : '';
    var ak = Object.keys(it.addons);
    a.innerHTML = ak.length ? '<p class="od-label">Extras</p>' + ak.map(function (k) {
      return '<label class="od-opt"><input type="checkbox" name="addon" value="' + k + '"><span>' + esc(it.addons[k].label) + '</span><span class="od-opt-price">+' + money(it.addons[k].price) + '</span></label>';
    }).join('') : '';

    $('[data-od-error]').hidden = true;
    dialogPrice();
    if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
  }

  dlg.addEventListener('change', function (e) {
    if (!cur) return;
    var t = e.target, it = ITEMS[cur.key];
    if (t.name === 'variant') cur.variant = +t.value;
    if (t.name === 'choice') { cur.choice = it.choices[+t.value]; $('[data-od-error]').hidden = true; }
    if (t.name === 'addon') cur.addons = $$('input[name="addon"]:checked', dlg).map(function (x) { return x.value; });
    dialogPrice();
  });
  $$('[data-qty]', dlg).forEach(function (b) {
    b.addEventListener('click', function () {
      cur.qty = Math.max(1, Math.min(DATA.maxQty, cur.qty + (+b.getAttribute('data-qty'))));
      dialogPrice();
    });
  });
  $('[data-dialog-close]').addEventListener('click', function () { dlg.close(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });

  $('[data-item-form]').addEventListener('submit', function (e) {
    var it = ITEMS[cur.key];
    if (it.choices.length && !cur.choice) {
      e.preventDefault();
      var err = $('[data-od-error]'); err.hidden = false; err.textContent = 'Please choose an option.';
      return;
    }
    add(cur);
    flash(it.name);
  });

  function flash(name) {
    var bar = $('[data-basket-open]');
    bar.classList.remove('is-bump'); void bar.offsetWidth; bar.classList.add('is-bump');
    var live = $('#order-live');
    if (live) live.textContent = name + ' added to your order.';
  }

  // Dishes with nothing to choose go straight in; the rest open the pop-up.
  $$('[data-add]').forEach(function (b) {
    b.addEventListener('click', function () {
      var key = b.getAttribute('data-add'), it = ITEMS[key];
      if (it.variants.length > 1 || it.choices.length || Object.keys(it.addons).length) { openItem(key); return; }
      add({ key: key, variant: 0, choice: '', addons: [], note: '', qty: 1 });
      flash(it.name);
      b.classList.remove('is-added'); void b.offsetWidth; b.classList.add('is-added');
    });
  });

  // Highlight the section in view in the category bar
  var cats = $$('.order-cats a');
  if ('IntersectionObserver' in window && cats.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        cats.forEach(function (a) {
          var on = a.getAttribute('href') === '#' + en.target.id;
          a.classList.toggle('is-on', on);
          if (on && a.scrollIntoView && window.innerWidth < 900) a.parentNode.scrollLeft = a.offsetLeft - 20;
        });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    $$('.order-sec').forEach(function (s) { io.observe(s); });
  }

  var live = document.createElement('p');
  live.id = 'order-live'; live.className = 'visually-hidden'; live.setAttribute('aria-live', 'polite');
  document.body.appendChild(live);

  load(); render();
  if (window.orderHadErrors) openBasket();
})();
