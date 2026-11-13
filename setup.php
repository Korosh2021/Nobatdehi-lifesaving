<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
security_headers();

$installed = is_file(CONFIG_FILE);
$error = '';
$done = false;

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');
    $title = trim((string) ($_POST['title'] ?? '')) ?: 'نوبت‌یار';
    if (!extension_loaded('pdo_sqlite')) {
        $error = 'افزونهٔ pdo_sqlite روی هاست فعال نیست. از پشتیبانی هاست بخواهید آن را فعال کند.';
    } elseif (strlen($pass) < 8) {
        $error = 'رمز باید دست‌کم ۸ نویسه باشد.';
    } elseif ($pass !== $pass2) {
        $error = 'رمز و تکرار آن یکسان نیست.';
    } else {
        $dir = APP_ROOT . '/data';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $error = 'ساخت پوشهٔ data ممکن نشد. اجازهٔ نوشتن روی پوشهٔ برنامه را بررسی کنید.';
        } else {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
            @file_put_contents($dir . '/index.html', '');
            $cfg = [
                'admin_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'db' => $dir . '/nobat-' . bin2hex(random_bytes(8)) . '.sqlite',
                'timezone' => 'Asia/Tehran',
            ];
            $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) {
                $error = 'نوشتن config.php ممکن نشد. اجازهٔ نوشتن روی پوشهٔ برنامه را بررسی کنید.';
            } else {
                @chmod(CONFIG_FILE, 0640);
                try {
                    app_config();
                    $s = default_settings();
                    $s['title'] = mb_substr($title, 0, 50);
                    save_settings($s);
                    $done = true;
                    $installed = true;
                } catch (Throwable $e) {
                    @unlink(CONFIG_FILE);
                    $error = 'ساخت پایگاه داده ناموفق بود: ' . $e->getMessage();
                }
            }
        }
    }
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>نصب نوبت‌یار</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="narrow">
<main class="wrap">
  <header class="top"><div class="brand"><b>نوبت‌یار</b><small>نصب برنامه</small></div></header>
<?php if ($done): ?>
  <div class="card">
    <h2>نصب انجام شد</h2>
    <p>فایل <b dir="ltr">setup.php</b> را همین حالا از هاست پاک کنید. بعد از آن:</p>
    <ul>
      <li>صفحهٔ رزرو مراجعه‌کننده: <a href="./">صفحهٔ اصلی سایت</a></li>
      <li>پنل مدیریت (با رمزی که ساختید): <a href="admin/">admin/</a></li>
    </ul>
  </div>
<?php elseif ($installed): ?>
  <div class="card"><p>برنامه قبلاً نصب شده است. برای نصب دوباره، فایل <b dir="ltr">config.php</b> و پوشهٔ <b dir="ltr">data</b> را پاک کنید.</p>
  <p>اگر هنوز <b dir="ltr">setup.php</b> روی هاست است، آن را پاک کنید.</p></div>
<?php else: ?>
  <form method="post" class="card form-stack" autocomplete="off">
    <h2>تنظیمات اولیه</h2>
    <?php if ($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?>
    <div class="field"><label for="title">نام مجموعه</label><input id="title" name="title" maxlength="50" value="<?= h((string) ($_POST['title'] ?? '')) ?>" placeholder="مثلاً مطب دکتر احمدی"></div>
    <div class="field"><label for="password">رمز پنل مدیریت</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"></div>
    <div class="field"><label for="password2">تکرار رمز</label><input id="password2" name="password2" type="password" minlength="8" required autocomplete="new-password"></div>
    <p class="hint">رمز دست‌کم ۸ نویسه باشد. این رمز فقط برای ورود به پنل مدیریت است.</p>
    <button class="btn primary" type="submit">نصب</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
