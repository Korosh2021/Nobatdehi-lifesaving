(function () {
  var $ = C.$, h = C.h, toFa = C.toFa, toEn = C.toEn;
  var days = [], times = null, selDate = null, selTime = null, lastDone = null, busy = false, loadFail = false;

  function vals() {
    return {
      first: $('#f_first').value.trim(), last: $('#f_last').value.trim(),
      nid: toEn($('#f_nid').value).replace(/\D/g, ''), phone: toEn($('#f_phone').value).replace(/\D/g, '')
    };
  }
  function validNid(s) {
    if (!/^\d{10}$/.test(s) || /^(\d)\1{9}$/.test(s)) return false;
    var sum = 0, i;
    for (i = 0; i < 9; i++) sum += (+s[i]) * (10 - i);
    var r = sum % 11, c = +s[9];
    return r < 2 ? c === r : c === 11 - r;
  }
  function setErr(id, msg) {
    var i = $('#f_' + id);
    $('#e_' + id).textContent = msg || '';
    if (msg) i.setAttribute('aria-invalid', 'true'); else i.removeAttribute('aria-invalid');
  }
  function validate() {
    var v = vals(), ok = true;
    function chk(id, bad, msg) { setErr(id, bad ? msg : ''); if (bad) ok = false; }
    chk('first', v.first.length < 2, 'نام را وارد کنید.');
    chk('last', v.last.length < 2, 'نام خانوادگی را وارد کنید.');
    chk('nid', !validNid(v.nid), 'کد ملی باید ۱۰ رقم و معتبر باشد.');
    chk('phone', !/^0\d{10}$/.test(v.phone), 'شماره تماس را ۱۱ رقمی و با صفر وارد کنید، مثل ۰۹۱۲۳۴۵۶۷۸۹.');
    return ok ? v : null;
  }

  function loadDays() {
    return C.api('api.php?a=init').then(function (j) {
      days = j.days; loadFail = false;
      if (j.title) $('#brandName').textContent = j.title;
      var cur = days.filter(function (x) { return x.d === selDate && x.free > 0; })[0];
      if (!cur) { var f = days.filter(function (x) { return x.free > 0; })[0] || days[0]; selDate = f ? f.d : null; selTime = null; }
    }).catch(function () { loadFail = true; });
  }
  function loadTimes() { return Promise.resolve(); }

  function renderDays() {
    var box = $('#days'); box.replaceChildren();
    if (loadFail) { box.append(h('div', { class: 'empty' }, 'اتصال به سرور برقرار نشد. صفحه را دوباره باز کنید.')); return; }
    if (!days.length) { box.append(h('div', { class: 'empty' }, 'در حال حاضر روزی برای نوبت‌دهی تعریف نشده است.')); return; }
    days.forEach(function (x) {
      var d = C.fromKey(x.d);
      box.append(h('button', {
        type: 'button', class: 'day' + (x.d === selDate ? ' on' : ''), 'aria-pressed': x.d === selDate ? 'true' : 'false', disabled: x.free === 0,
        onclick: function () { selDate = x.d; renderDays(); renderTicket(); }
      }, h('span', { class: 'wk' }, C.wkShort.format(d)), h('span', { class: 'dn' }, C.dn.format(d)), h('span', { class: 'mo' }, C.mo.format(d)),
        h('span', { class: 'fc' }, x.free ? toFa(x.free) + ' جای خالی' : 'پر')));
    });
  }
  function renderTimes() {}
  function renderTicket() {
    var box = $('#ticket'); box.replaceChildren();
    if (lastDone) {
      box.append(h('div', { class: 'ticket done' },
        h('div', { class: 'head' }, h('small', null, 'نوبت شما ثبت شد'), h('div', { class: 'big' }, C.wk.format(C.fromKey(lastDone.d)) + ' · نوبت شمارهٔ ' + toFa(lastDone.n))),
        h('dl', null,
          h('dt', null, 'تاریخ'), h('dd', null, C.full.format(C.fromKey(lastDone.d))),
          h('dt', null, 'به نام'), h('dd', null, lastDone.first + ' ' + lastDone.last),
          h('dt', null, 'کد پیگیری'), h('dd', { class: 'code' }, toFa(lastDone.code))),
        h('p', { class: 'hint', style: 'margin-top:10px' }, 'کد پیگیری را نگه دارید. برای لغو نوبت لازم است.'),
        h('button', { class: 'btn block', type: 'button', onclick: function () { lastDone = null; renderTicket(); } }, 'ثبت نوبت دیگر')));
      return;
    }
    var v = vals(), nm = (v.first + ' ' + v.last).trim();
    box.append(h('div', { class: 'ticket' },
      h('div', { class: 'head' }, h('small', null, 'خلاصه نوبت'),
        h('div', { class: 'big' }, selDate ? C.wk.format(C.fromKey(selDate)) : 'روز را انتخاب کنید')),
      h('dl', null,
        h('dt', null, 'تاریخ'), h('dd', null, selDate ? C.full.format(C.fromKey(selDate)) : '—'),
        h('dt', null, 'به نام'), h('dd', null, nm || '—'),
        h('dt', null, 'کد ملی'), h('dd', null, v.nid ? toFa(v.nid) : '—'),
        h('dt', null, 'تماس'), h('dd', null, v.phone ? toFa(v.phone) : '—')),
      h('button', { class: 'btn primary block', type: 'button', disabled: busy || !selDate, onclick: submit }, busy ? 'در حال ثبت…' : 'ثبت نوبت')));
  }

  function submit() {
    if (busy || !selDate) return;
    var v = validate();
    if (!v) { C.toast('مشخصات را کامل و درست وارد کنید.'); return; }
    busy = true; renderTicket();
    C.api('api.php?a=book', { d: selDate, first: v.first, last: v.last, nid: v.nid, phone: v.phone }).then(function (j) {
      lastDone = j;
      ['first', 'last', 'nid', 'phone'].forEach(function (k) { $('#f_' + k).value = ''; setErr(k, ''); });
    }).catch(function (e) {
      C.toast(e.message || 'ثبت نوبت انجام نشد.');
    }).then(function () {
      busy = false;
      return loadDays();
    }).then(function () { renderDays(); renderTicket(); return loadTimes(); });
  }

  /* lookup / cancel */
  function showLookup(b) {
    var box = $('#lookupRes'); box.replaceChildren();
    if (!b) return;
    var past = b.d < new Date().toISOString().slice(0, 10);
    var chip = h('span', { class: 'chip ' + (b.status === 'done' ? 'ok' : 'info') }, b.status === 'done' ? 'انجام شد' : 'فعال');
    box.append(h('div', { class: 'card' },
      h('div', null, h('b', null, C.wk.format(C.fromKey(b.d)) + ' · نوبت شمارهٔ ' + toFa(b.n)), ' ', chip),
      h('div', { class: 'hint' }, C.full.format(C.fromKey(b.d)) + ' · ' + b.first + ' ' + b.last),
      past || b.status === 'done' ? null : h('div', { style: 'margin-top:10px' }, C.confirmBtn('لغو نوبت', function () {
        return C.api('api.php?a=cancel', { code: $('#l_code').value, nid: $('#l_nid').value }).then(function () {
          box.replaceChildren(); C.toast('نوبت لغو شد.');
          return loadDays();
        }).then(function () { renderDays(); return loadTimes(); });
      }))));
  }

  function init() {
    ['f_first', 'f_last', 'f_nid', 'f_phone'].forEach(function (id) {
      $('#' + id).addEventListener('input', function () {
        if (id === 'f_nid' || id === 'f_phone') this.value = toFa(toEn(this.value).replace(/\D/g, ''));
        if (this.getAttribute('aria-invalid')) setErr(id.slice(2), '');
        renderTicket();
      });
    });
    ['l_code', 'l_nid'].forEach(function (id) {
      $('#' + id).addEventListener('input', function () { this.value = toFa(toEn(this.value).replace(/\D/g, '')); });
    });
    $('#form').addEventListener('submit', function (e) { e.preventDefault(); submit(); });
    $('#lookupForm').addEventListener('submit', function (e) {
      e.preventDefault(); showLookup(null);
      C.api('api.php?a=lookup', { code: $('#l_code').value, nid: $('#l_nid').value }).then(function (j) { showLookup(j.booking); })
        .catch(function (err) { C.toast(err.message); });
    });
    renderDays(); renderTimes(); renderTicket();
    loadDays().then(function () { renderDays(); return loadTimes(); }).then(renderTicket);
    setInterval(function () { if (!busy && !document.hidden) loadDays().then(function () { renderDays(); }); }, 60000);
  }
  init();
})();
