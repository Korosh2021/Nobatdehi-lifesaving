<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
require_installed('../setup.php');
$cfg = app_config();
security_headers();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$error = '';
$action = $_POST['do'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    if (csrf_ok($_POST['csrf'] ?? null)) {
        $_SESSION = [];
        session_destroy();
    }
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    admin_session();
    if (login_blocked()) {
        $error = 'تعداد تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید.';
    } elseif (password_verify((string) ($_POST['password'] ?? ''), $cfg['admin_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['seen'] = time();
        header('Location: ./');
        exit;
    } else {
        login_failed();
        usleep(400000);
        $error = 'رمز درست نیست.';
    }
}

$in = admin_logged_in();
$title = get_settings()['title'];
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>پنل مدیریت | <?= h($title) ?></title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<?php if (!$in): ?>
<body class="narrow">
<main class="wrap">
  <header class="top"><div class="brand"><b><?= h($title) ?></b><small>ورود به پنل مدیریت</small></div></header>
  <form method="post" class="card form-stack" autocomplete="off">
    <input type="hidden" name="do" value="login">
    <?php if ($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?>
    <div class="field"><label for="password">رمز عبور</label><input id="password" name="password" type="password" required autofocus autocomplete="current-password"></div>
    <button class="btn primary" type="submit">ورود</button>
  </form>
  <p class="hint" style="margin-top:12px"><a href="../">بازگشت به صفحهٔ رزرو</a></p>
</main>
</body>
<?php else: ?>
<body>
<div class="wrap" id="app" data-csrf="<?= h(csrf_token()) ?>">
  <header class="top">
    <div class="brand"><b><?= h($title) ?></b><small>پنل مدیریت</small></div>
    <nav class="tabs" role="tablist" aria-label="بخش‌ها">
      <button id="tabBookings" role="tab" aria-selected="true" type="button">نوبت‌ها</button>
      <button id="tabCfg" role="tab" aria-selected="false" type="button">تنظیمات</button>
      <form method="post" class="logout"><input type="hidden" name="do" value="logout"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button type="submit">خروج</button></form>
    </nav>
  </header>

  <section id="vBookings" class="admin">
    <div>
      <div class="bar">
        <select id="a_scope" aria-label="بازه نمایش"><option value="upcoming">از امروز به بعد</option><option value="today">فقط امروز</option><option value="all">همه</option></select>
        <input id="a_q" class="grow" type="search" placeholder="جستجو با نام، کد ملی، تلفن یا کد پیگیری">
        <a class="btn" href="api.php?a=export">دانلود CSV</a>
      </div>
      <p id="a_sum" class="hint" style="margin-bottom:8px"></p>
      <div class="tblwrap"><table><thead><tr><th>روز</th><th>شماره نوبت</th><th>نام و نام خانوادگی</th><th>کد ملی</th><th>شماره تماس</th><th>کد پیگیری</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="a_rows"></tbody></table></div>
    </div>
  </section>

  <section id="vCfg" class="admin" hidden>
    <form id="cfgForm" class="card" novalidate>
      <h2 style="margin-bottom:12px">تنظیمات زمان‌بندی</h2>
      <div class="fields">
        <div class="field" style="grid-column:1/-1"><label for="c_title">نام مجموعه</label><input id="c_title" maxlength="50"></div>
        <div class="field" style="grid-column:1/-1"><label>روزهای کاری</label><div class="days7" id="c_days"></div></div>
        <div class="field"><label for="c_cap">ظرفیت هر روز (تعداد نوبت)</label><select id="c_cap"></select></div>
        <div class="field"><label for="c_hor">نوبت‌دهی تا چند روز آینده</label><select id="c_hor"></select></div>
      </div>
      <p class="err" id="c_err" style="margin-top:8px"></p>
      <button class="btn primary" type="submit" style="margin-top:6px">ذخیره تنظیمات</button>
    </form>

    <form id="pwForm" class="card form-stack" novalidate autocomplete="off">
      <h2>تغییر رمز پنل</h2>
      <div class="fields">
        <div class="field"><label for="p_cur">رمز فعلی</label><input id="p_cur" type="password" autocomplete="current-password"></div>
        <div class="field"><label for="p_new">رمز جدید (حداقل ۸ نویسه)</label><input id="p_new" type="password" autocomplete="new-password"></div>
      </div>
      <p class="err" id="p_err"></p>
      <div><button class="btn primary" type="submit">تغییر رمز</button></div>
    </form>
  </section>
</div>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
<script src="../assets/common.js"></script>
<script src="admin.js"></script>
</body>
<?php endif; ?>
</html>
