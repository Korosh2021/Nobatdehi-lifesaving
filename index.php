<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_installed('setup.php');
app_config();
security_headers();
$title = get_settings()['title'];
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> | رزرو نوبت</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
  <header class="top">
    <div class="brand"><b id="brandName"><?= h($title) ?></b><small>رزرو نوبت آنلاین</small></div>
  </header>

  <div class="grid">
    <div class="steps">
      <section class="step"><h2><span class="n">۱</span>روز مراجعه</h2><div id="days" class="days"></div></section>
      <section class="step">
        <h2><span class="n">۲</span>مشخصات شما</h2>
        <form id="form" class="card" novalidate autocomplete="off">
          <div class="fields">
            <div class="field"><label for="f_first">نام</label><input id="f_first" maxlength="40" autocomplete="given-name"><p class="err" id="e_first"></p></div>
            <div class="field"><label for="f_last">نام خانوادگی</label><input id="f_last" maxlength="60" autocomplete="family-name"><p class="err" id="e_last"></p></div>
            <div class="field"><label for="f_nid">کد ملی</label><input id="f_nid" class="num" inputmode="numeric" maxlength="10" placeholder="۱۰ رقم"><p class="err" id="e_nid"></p></div>
            <div class="field"><label for="f_phone">شماره تماس</label><input id="f_phone" class="num" inputmode="tel" maxlength="11" placeholder="۰۹۱۲۳۴۵۶۷۸۹" autocomplete="tel"><p class="err" id="e_phone"></p></div>
          </div>
          <p class="hint" style="margin-top:12px">اطلاعات شما فقط برای مدیر این مجموعه قابل مشاهده است.</p>
        </form>
      </section>
    </div>

    <aside class="ticketCol">
      <div id="ticket"></div>
      <section class="card lookup">
        <h3>پیگیری یا لغو نوبت</h3>
        <p class="hint">کد پیگیری و کد ملی خود را وارد کنید.</p>
        <form id="lookupForm" novalidate autocomplete="off">
          <div class="fields">
            <div class="field"><label for="l_code">کد پیگیری</label><input id="l_code" class="num" inputmode="numeric" maxlength="6"></div>
            <div class="field"><label for="l_nid">کد ملی</label><input id="l_nid" class="num" inputmode="numeric" maxlength="10"></div>
          </div>
          <button class="btn" type="submit">جستجوی نوبت</button>
        </form>
        <div id="lookupRes" class="res"></div>
      </section>
    </aside>
  </div>
</div>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
<script src="assets/common.js"></script>
<script src="assets/public.js"></script>
</body>
</html>
