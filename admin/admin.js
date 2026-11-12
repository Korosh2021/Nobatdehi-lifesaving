(function () {
  var $ = C.$, h = C.h, toFa = C.toFa, toEn = C.toEn;
  var csrf = $('#app').dataset.csrf;
  var WD = [[6, 'شنبه'], [0, 'یکشنبه'], [1, 'دوشنبه'], [2, 'سه‌شنبه'], [3, 'چهارشنبه'], [4, 'پنجشنبه'], [5, 'جمعه']];
  var bookings = [], today = '', stats = null, scope = 'upcoming', q = '', cfgLoaded = false;

  function get(a, extra) { return C.api('api.php?a=' + a + (extra || '')); }
  function post(a, body) { return C.api('api.php?a=' + a, body, csrf); }
  function onErr(e) {
    if (e.status === 401 || e.status === 403) { C.toast('نشست شما تمام شده است. دوباره وارد شوید.'); setTimeout(function () { location.reload(); }, 1500); return; }
    C.toast(e.message || 'انجام نشد.');
  }

  /* bookings */
  function loadBookings() {
    return get('list').then(function (j) { bookings = j.bookings; today = j.today; stats = j.stats; renderBookings(); }).catch(onErr);
  }
  function renderBookings() {
    var qq = toEn(q).trim().toLowerCase();
    var rows = bookings.filter(function (b) {
      if (scope === 'upcoming' && b.d < today) return false;
      if (scope === 'today' && b.d !== today) return false;
      if (!qq) return true;
      return (b.first + ' ' + b.last + ' ' + b.nid + ' ' + b.phone + ' ' + b.code).toLowerCase().indexOf(qq) > -1;
    });
    if (stats) $('#a_sum').textContent = toFa(stats.today) + ' نوبت امروز در انتظار · ' + toFa(stats.upcoming) + ' نوبت از امروز به بعد · ' + toFa(stats.all) + ' نوبت در کل';
    var tb = $('#a_rows'); tb.replaceChildren();
    if (!rows.length) {
      tb.append(h('tr', null, h('td', { colspan: '8', style: 'text-align:center;color:var(--muted);padding:22px' },
        bookings.length ? 'نتیجه‌ای پیدا نشد.' : 'هنوز نوبتی ثبت نشده است. وقتی مراجعه‌کننده‌ای نوبت بگیرد، اینجا نمایش داده می‌شود.')));
      return;
    }
    rows.forEach(function (b) {
      var done = b.status === 'done';
      tb.append(h('tr', null,
        h('td', null, C.wk.format(C.fromKey(b.d)) + ' ' + C.short.format(C.fromKey(b.d))),
        h('td', null, toFa(String(parseInt(b.t, 10)))),
        h('td', null, b.first + ' ' + b.last),
        h('td', { class: 'num' }, toFa(b.nid)),
        h('td', { class: 'num' }, toFa(b.phone)),
        h('td', { class: 'num' }, toFa(b.code)),
        h('td', null, h('span', { class: 'chip ' + (done ? 'ok' : 'info') }, done ? 'انجام شد' : 'در انتظار')),
        h('td', { class: 'acts' },
          h('button', { class: 'btn sm', type: 'button', onclick: function () { post('status', { id: b.id, status: done ? 'active' : 'done' }).then(loadBookings).catch(onErr); } }, done ? 'بازگردانی' : 'انجام شد'),
          C.confirmBtn('لغو', function () { return post('cancel', { id: b.id }).then(function () { C.toast('نوبت لغو شد.'); return loadBookings(); }); }))));
    });
  }

  /* settings */
  function loadCfg() {
    return get('cfg').then(function (j) {
      var s = j.settings; cfgLoaded = true;
      $('#c_title').value = s.title;
      $('#c_cap').value = String(s.capacity); $('#c_hor').value = String(s.horizon);
      WD.forEach(function (w) { $('#c_d' + w[0]).checked = s.days.map(Number).indexOf(w[0]) > -1; });
    }).catch(onErr);
  }

  function showTab(name) {
    var map = { bookings: ['#vBookings', '#tabBookings'], cfg: ['#vCfg', '#tabCfg'] };
    Object.keys(map).forEach(function (k) {
      $(map[k][0]).hidden = k !== name;
      $(map[k][1]).setAttribute('aria-selected', String(k === name));
    });
    if (name === 'bookings') loadBookings();
    if (name === 'cfg' && !cfgLoaded) loadCfg();
  }

  /* wiring */
  [1, 2, 3, 5, 8, 10, 15, 20, 25, 30, 40, 50, 75, 100, 150, 200].forEach(function (n) { $('#c_cap').append(h('option', { value: n }, toFa(n) + ' نوبت')); });
  [7, 14, 21, 30, 60, 90].forEach(function (n) { $('#c_hor').append(h('option', { value: n }, toFa(n) + ' روز')); });
  WD.forEach(function (w) { $('#c_days').append(h('label', null, h('input', { type: 'checkbox', id: 'c_d' + w[0] }), w[1])); });

  $('#tabBookings').addEventListener('click', function () { showTab('bookings'); });
  $('#tabCfg').addEventListener('click', function () { showTab('cfg'); });
  $('#a_scope').addEventListener('change', function () { scope = this.value; renderBookings(); });
  $('#a_q').addEventListener('input', function () { q = this.value; renderBookings(); });

  $('#cfgForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var days = WD.map(function (w) { return w[0]; }).filter(function (d) { return $('#c_d' + d).checked; });
    $('#c_err').textContent = '';
    post('cfg', { title: $('#c_title').value, days: days, capacity: +$('#c_cap').value, horizon: +$('#c_hor').value })
      .then(function () { C.toast('تنظیمات ذخیره شد.'); }).catch(function (err) { $('#c_err').textContent = err.message; });
  });
  $('#pwForm').addEventListener('submit', function (e) {
    e.preventDefault(); $('#p_err').textContent = '';
    post('password', { current: $('#p_cur').value, new: $('#p_new').value })
      .then(function () { $('#p_cur').value = ''; $('#p_new').value = ''; C.toast('رمز تغییر کرد.'); })
      .catch(function (err) { $('#p_err').textContent = err.message; });
  });

  loadBookings();
})();
