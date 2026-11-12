/* Wyuka Lot Manager - ADMIN script. Loaded only on the Lot Manager dashboard screens.
   Sections: add/edit form, list details panel. Each part does nothing when its screen is not showing. */

/* ===== add / edit form ===== */
/* Wyuka Lot Manager - add/edit form. Plain JS, kept small on purpose.
   Required, maxlength, pattern, min/max and the date pickers are native HTML; the server (mccm_validate_entry_clean) is the real check.
   JS only does what HTML cannot: the type-or-select lists, the "Other" box, age from dates, Lot required by section, death-before-birth. */
(function () {
  'use strict';
  var form = document.getElementById('mccm-entry-form');
  if (!form) return;

  // comboboxes (Section, Vault, Marker, Funeral home, Director): type to filter, or open the list and click.
  // Choices come from the page itself (data-options), no request is made. Anything typed is kept as typed.
  function initCombo(inp) {
    var opts = [];
    try { opts = JSON.parse(inp.getAttribute('data-options') || '[]'); } catch (e) {}
    if (!opts.length) return;
    var wrap = document.createElement('div'); wrap.className = 'mccm-combo';
    inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'mccm-combo-btn'; btn.tabIndex = -1; btn.setAttribute('aria-label', 'Show choices');
    var list = document.createElement('ul'); list.className = 'mccm-combo-list'; list.id = inp.id + '_list'; list.setAttribute('role', 'listbox'); list.hidden = true;
    wrap.appendChild(btn); wrap.appendChild(list);
    inp.setAttribute('role', 'combobox'); inp.setAttribute('aria-autocomplete', 'list'); inp.setAttribute('aria-controls', list.id); inp.setAttribute('aria-expanded', 'false');
    var top = parseInt(inp.getAttribute('data-top') || '0', 10) || opts.length;
    var active = -1, shown = [];
    function render(filter) {
      var f = (filter || '').toLowerCase();
      shown = f ? opts.filter(function (o) { return o.toLowerCase().indexOf(f) > -1; }).slice(0, 80) : opts.slice(0, top);
      list.innerHTML = ''; active = -1;
      shown.forEach(function (o) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.textContent = o;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(o); });
        list.appendChild(li);
      });
      if (!shown.length) { var e = document.createElement('li'); e.className = 'mccm-combo-empty'; e.textContent = 'No match. What you type will be saved as typed.'; list.appendChild(e); }
    }
    function open(filter) { render(filter); list.hidden = false; inp.setAttribute('aria-expanded', 'true'); }
    function close() { list.hidden = true; inp.setAttribute('aria-expanded', 'false'); active = -1; }
    function choose(v) { inp.value = v; close(); inp.dispatchEvent(new Event('input', { bubbles: true })); }
    function move(d) {
      if (list.hidden) open(inp.value);
      var items = list.querySelectorAll('li[role=option]'); if (!items.length) return;
      if (active > -1) items[active].classList.remove('is-active');
      active = (active + d + items.length) % items.length;
      items[active].classList.add('is-active'); items[active].scrollIntoView({ block: 'nearest' });
    }
    inp.addEventListener('focus', function () { open(inp.value); });
    inp.addEventListener('input', function () { open(inp.value); });
    inp.addEventListener('blur', function () { setTimeout(close, 120); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter' && !list.hidden && active > -1) { e.preventDefault(); choose(shown[active]); }
      else if (e.key === 'Escape' && !list.hidden) { e.preventDefault(); close(); }
    });
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); if (list.hidden) { inp.focus(); open(''); } else close(); });
  }
  Array.prototype.forEach.call(form.querySelectorAll('input[data-combo]'), initCombo);

  // "Other..." choice: show the text box and make it required
  Array.prototype.forEach.call(form.querySelectorAll('select[data-other]'), function (sel) {
    var box = document.getElementById(sel.id + '__other');
    if (!box) return;
    box.required = sel.value === '__other';
    sel.addEventListener('change', function () {
      var on = sel.value === '__other';
      box.hidden = !on; box.required = on;
      if (on) box.focus();
    });
  });

  // age from birth/death, until someone types an age by hand
  var ageEl = form.elements.AGE, bEl = form.elements.BIRTH, dEl = form.elements.DEATH;
  var ageManual = !!(ageEl && ageEl.value !== '');
  function calcAge() {
    if (!ageEl || ageManual || !bEl.value || !dEl.value) return;
    var b = bEl.value.split('-').map(Number), d = dEl.value.split('-').map(Number);
    var a = d[0] - b[0];
    if (d[1] < b[1] || (d[1] === b[1] && d[2] < b[2])) a--;
    if (a >= 0 && a <= 130) ageEl.value = String(a);
  }
  // death must not be before birth (shown as the browser's own message on the Death field)
  var b0 = bEl ? bEl.value : '', d0 = dEl ? dEl.value : ''; // an old record that already had odd dates can still be saved untouched
  function checkDates() { dEl.setCustomValidity((bEl.value !== b0 || dEl.value !== d0) && bEl.value && dEl.value && dEl.value < bEl.value ? 'Death date is before the birth date.' : ''); }
  if (ageEl && bEl && dEl) {
    ageEl.addEventListener('input', function () { ageManual = ageEl.value !== ''; });
    [bEl, dEl].forEach(function (el) { el.addEventListener('change', function () { calcAge(); checkDates(); }); });
    calcAge(); checkDates();
  }

  // a field the server flagged goes back to normal once it is edited
  form.addEventListener('input', function (e) {
    var box = e.target.closest('.mccm-field.has-error');
    if (box) { box.classList.remove('has-error'); var m = box.querySelector('.mccm-err'); if (m) m.hidden = true; }
  });

  // one click only
  form.addEventListener('submit', function () {
    var b = document.getElementById('mccm-save');
    b.disabled = true; b.querySelector('span').textContent = 'Saving…';
  });
})();

/* ===== list details panel ===== */
/* Lot Information: load one record into the details panel when its Interment # is clicked.
   The record starts loading as soon as the pointer rests on the button (or a finger touches it), so it is usually ready at the click. */
(function () {
  var pop = document.getElementById('mccm-pop'), cache = {}, timer = 0;
  if (!pop) return;
  function load(id) {
    if (!cache[id]) {
      cache[id] = fetch(pop.dataset.url + '?action=mccm_row&id=' + id + '&_wpnonce=' + pop.dataset.nonce, { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw 0; return r.text(); })
        .catch(function () { delete cache[id]; return null; });
    }
    return cache[id];
  }
  function btn(e) { return e.target.closest && e.target.closest('.mccm-int'); }
  document.addEventListener('mouseover', function (e) {
    var b = btn(e); clearTimeout(timer);
    if (b) timer = setTimeout(function () { load(b.dataset.uid); }, 120);
  });
  document.addEventListener('touchstart', function (e) { var b = btn(e); if (b) load(b.dataset.uid); }, { passive: true });
  document.addEventListener('focusin', function (e) { var b = btn(e); if (b) load(b.dataset.uid); });
  document.addEventListener('click', function (e) {
    var b = btn(e);
    if (!b) return;
    if (!pop.showPopover) { location.href = b.dataset.edit; return; } // very old browsers: go straight to the record
    pop.innerHTML = '<div class="mccm-pop-body"><p class="mccm-pop-wait">Loading…</p></div>';
    pop.showPopover();
    load(b.dataset.uid).then(function (h) {
      pop.innerHTML = h || '<div class="mccm-pop-body"><p class="mccm-pop-wait">Could not load this record. Close and try again.</p></div>';
    });
  });
})();

/* import form: button label follows the test switch; confirm before a real run */
document.querySelectorAll('.mccm-imp-form').forEach(function (f) {
    var t = f.querySelector('[name=dryrun]'), l = f.querySelector('.mccm-imp-go span'), b = l.parentNode;
    function sync() { l.textContent = t.checked ? l.dataset.test : l.dataset.live; }
    t.addEventListener('change', sync); sync();
    f.addEventListener('submit', function (e) {
        if (!t.checked && !f.dataset.ok) {
            e.preventDefault();
            mccmConfirm({
                title: 'Run the real import?',
                message: 'This changes your data permanently. Did you run a test first?',
                button: 'Yes, import', danger: true
            }, function () { f.dataset.ok = '1'; f.requestSubmit(); });
            return;
        }
        setTimeout(function () { b.disabled = true; }, 0);
    });
});
