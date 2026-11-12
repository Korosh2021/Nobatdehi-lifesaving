/* Shared helpers for the public page and the admin panel. */
(function (w) {
  var FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩';
  var P = 'fa-IR-u-ca-persian';
  var C = {
    $: function (s) { return document.querySelector(s); },
    toFa: function (s) { return String(s).replace(/\d/g, function (d) { return FA[d]; }); },
    toEn: function (s) {
      return String(s).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return AR.indexOf(d); });
    },
    pad: function (n) { return String(n).padStart(2, '0'); },
    fromKey: function (k) { var p = k.split('-').map(Number); return new Date(p[0], p[1] - 1, p[2], 12); },
    wkShort: new Intl.DateTimeFormat(P, { weekday: 'short' }),
    wk: new Intl.DateTimeFormat(P, { weekday: 'long' }),
    dn: new Intl.DateTimeFormat(P, { day: 'numeric' }),
    mo: new Intl.DateTimeFormat(P, { month: 'long' }),
    full: new Intl.DateTimeFormat(P, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    short: new Intl.DateTimeFormat(P, { year: 'numeric', month: '2-digit', day: '2-digit' }),
    h: function (tag, props) {
      var e = document.createElement(tag), k, v, kids = Array.prototype.slice.call(arguments, 2);
      for (k in (props || {})) {
        v = props[k];
        if (v === false || v == null) continue;
        if (k === 'class') e.className = v;
        else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), v);
        else e.setAttribute(k, v === true ? '' : v);
      }
      (function add(list) {
        list.forEach(function (c) {
          if (c == null || c === false) return;
          if (Array.isArray(c)) return add(c);
          e.append(c.nodeType ? c : document.createTextNode(c));
        });
      })(kids);
      return e;
    },
    toast: function (msg) {
      var t = document.getElementById('toast');
      t.textContent = msg; t.hidden = false;
      clearTimeout(C.toast.t);
      C.toast.t = setTimeout(function () { t.hidden = true; }, 3200);
    },
    api: function (url, body, csrf) {
      var opt = { credentials: 'same-origin', headers: {} };
      if (body !== undefined) {
        opt.method = 'POST';
        opt.headers['Content-Type'] = 'application/json';
        if (csrf) opt.headers['X-CSRF'] = csrf;
        opt.body = JSON.stringify(body);
      }
      return fetch(url, opt).then(function (r) {
        return r.json().catch(function () { return { error: 'پاسخ نامعتبر از سرور.' }; }).then(function (j) {
          if (!r.ok) { var e = new Error(j.error || 'خطا'); e.status = r.status; throw e; }
          return j;
        });
      });
    },
    confirmBtn: function (label, fn) {
      var b = C.h('button', { class: 'btn sm danger', type: 'button' }, label), tm;
      b.addEventListener('click', function () {
        if (!b.dataset.arm) {
          b.dataset.arm = '1'; b.textContent = 'تأیید ' + label;
          tm = setTimeout(function () { delete b.dataset.arm; b.textContent = label; }, 3000);
          return;
        }
        clearTimeout(tm); b.disabled = true;
        Promise.resolve(fn()).catch(function (e) {
          C.toast(e.message || 'انجام نشد.'); b.disabled = false; delete b.dataset.arm; b.textContent = label;
        });
      });
      return b;
    }
  };
  w.C = C;
})(window);
