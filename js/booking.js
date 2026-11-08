// Booking widget, OpenTable style: party size, date and preferred time at the
// top; the free times nearest to that time as buttons; then the details.
// The server checks everything again, so this is only about convenience.
(function () {
  'use strict';
  var form = document.querySelector('[data-ot]');
  var dataEl = document.getElementById('ot-data');
  if (!form || !dataEl) return;

  var DATA = JSON.parse(dataEl.textContent);
  var $ = function (s) { return form.querySelector(s); };
  var guests = $('[data-ot-guests]'), date = $('[data-ot-date]'), pref = $('[data-ot-pref]');
  var chips = $('[data-ot-chips]'), all = $('[data-ot-all]'), none = $('[data-ot-none]');
  var more = $('[data-ot-more]'), left = $('[data-ot-left]'), leftN = $('[data-ot-left-n]');
  var details = $('[data-ot-details]'), times = $('[data-ot-times]'), summary = $('[data-ot-summary-text]');
  var slots = DATA.slots || {};
  var inFlight = null;

  var mins = function (t) { return parseInt(t.slice(0, 2), 10) * 60 + parseInt(t.slice(3, 5), 10); };

  function chip(t, free, checked) {
    return '<label class="ot-chip' + (free ? '' : ' is-full') + '"><input type="radio" name="time" value="' + t + '"' +
      (checked ? ' checked' : '') + (free ? '' : ' disabled') + ' required><span>' + t + '</span></label>';
  }

  function chosen() { var c = form.querySelector('input[name="time"]:checked'); return c ? c.value : ''; }

  function render(keep) {
    var current = keep ? chosen() : '';
    var free = Object.keys(slots).filter(function (t) { return slots[t]; });
    var p = mins(pref.value);
    var near = free.slice().sort(function (a, b) {
      return Math.abs(mins(a) - p) - Math.abs(mins(b) - p) || (a < b ? -1 : 1);
    }).slice(0, 5).sort();
    if (current && near.indexOf(current) === -1 && slots[current]) { near.push(current); near.sort(); }

    chips.innerHTML = near.map(function (t) { return chip(t, true, t === current); }).join('');
    all.innerHTML = Object.keys(slots).map(function (t) { return chip(t, slots[t], false); }).join('');
    all.hidden = true;

    none.hidden = free.length > 0;
    more.hidden = free.length <= near.length;
    more.textContent = 'View full availability';
    left.hidden = !(free.length > 0 && free.length <= 6);
    if (leftN) leftN.textContent = free.length;
  }

  function closedMessage(msg) {
    var phone = DATA.phone ? ', or ring us on <a href="' + DATA.tel + '">' + DATA.phone + '</a>' : '';
    none.innerHTML = (msg || 'No tables left that day. Try another date') + phone + '.';
  }

  function refresh() {
    var url = 'slots.php?date=' + encodeURIComponent(date.value) + '&guests=' + encodeURIComponent(guests.value);
    if (inFlight) inFlight.abort();
    inFlight = new AbortController();
    form.classList.add('is-loading');
    fetch(url, { signal: inFlight.signal })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        form.classList.remove('is-loading');
        if (!data) return;
        slots = data.closed ? {} : data.slots;
        closedMessage(data.closed ? (data.message || 'We are closed that day').replace(/\.$/, '') + '. Try another date'
          : 'No tables left that day for ' + guests.value + '. Try another date');
        showStep(1);
        render(false);
      })
      .catch(function () { form.classList.remove('is-loading'); });
  }

  // Step 2 (details) appears once a time is picked.
  function showStep(n) {
    var open = n === 2 && chosen();
    details.classList.toggle('is-open', !!open);
    times.classList.toggle('is-picked', !!open);
    if (open) {
      var d = date.options[date.selectedIndex].text;
      var g = guests.options[guests.selectedIndex].text;
      summary.innerHTML = '<strong>' + d + '</strong> · <strong>' + chosen() + '</strong> · ' + g;
    }
  }

  form.addEventListener('change', function (e) {
    if (e.target === date || e.target === guests) refresh();
    else if (e.target === pref) { showStep(1); render(false); }
    else if (e.target.name === 'time') {
      // A time picked from the full list joins the shortlist.
      render(true);
      showStep(2);
      var first = form.querySelector('#name');
      if (first && window.innerWidth < 900) details.scrollIntoView({ behavior: 'smooth', block: 'start' });
      else if (first) first.focus({ preventScroll: true });
    }
  });

  more.addEventListener('click', function () {
    all.hidden = !all.hidden;
    more.textContent = all.hidden ? 'View full availability' : 'Show fewer times';
  });

  $('[data-ot-change]').addEventListener('click', function () {
    showStep(1);
    times.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  form.classList.add('is-enhanced');
  render(true);
  if (DATA.open && chosen()) showStep(2);
})();
