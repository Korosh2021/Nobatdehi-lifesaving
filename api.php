<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
if (!is_file(CONFIG_FILE)) {
    fail('برنامه هنوز نصب نشده است.', 503);
}
app_config();
security_headers();

$a = $_GET['a'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

try {
    switch ($a) {
        case 'init': {
            $s = get_settings();
            $days = open_days($s);
            $taken = $days ? taken_map($days[0], end($days)) : [];
            $out = [];
            foreach ($days as $d) {
                $free = 0;
                foreach (bookable_times($s, $d) as $t) {
                    if (!isset($taken[$d . '|' . $t])) {
                        $free++;
                    }
                }
                $out[] = ['d' => $d, 'free' => $free];
            }
            json_out(['title' => $s['title'], 'days' => $out]);
        }

        case 'times': {
            $s = get_settings();
            $d = (string) ($_GET['d'] ?? '');
            if (!valid_date($d) || !in_array($d, open_days($s), true)) {
                json_out(['times' => []]);
            }
            $taken = taken_map($d, $d);
            $times = [];
            foreach (bookable_times($s, $d) as $t) {
                $times[] = ['t' => $t, 'free' => !isset($taken[$d . '|' . $t])];
            }
            json_out(['times' => $times]);
        }

        case 'book': {
            if (!$post) fail('روش درخواست نامعتبر است.', 405);
            $in = input();
            $s = get_settings();
            $d = (string) ($in['d'] ?? '');
            $first = trim((string) ($in['first'] ?? ''));
            $last = trim((string) ($in['last'] ?? ''));
            $nid = preg_replace('/\D/', '', digits_en((string) ($in['nid'] ?? '')));
            $phone = preg_replace('/\D/', '', digits_en((string) ($in['phone'] ?? '')));

            if (mb_strlen($first) < 2 || mb_strlen($first) > 40) fail('نام را درست وارد کنید.');
            if (mb_strlen($last) < 2 || mb_strlen($last) > 60) fail('نام خانوادگی را درست وارد کنید.');
            if (!valid_nid($nid)) fail('کد ملی معتبر نیست.');
            if (!preg_match('/^0\d{10}$/', $phone)) fail('شماره تماس باید ۱۱ رقم و با صفر شروع شود.');
            if (!valid_date($d) || !in_array($d, open_days($s), true)) {
                fail('این روز قابل رزرو نیست. روز دیگری انتخاب کنید.', 409);
            }

            $pdo = db();
            $st = $pdo->prepare('SELECT COUNT(*) c FROM bookings WHERE ip = ? AND created_at > ?');
            $st->execute([client_ip(), time() - 3600]);
            if ((int) $st->fetch()['c'] >= 5) fail('تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.', 429);

            $st = $pdo->prepare('SELECT COUNT(*) c FROM bookings WHERE nid = ? AND d >= ?');
            $st->execute([$nid, date('Y-m-d')]);
            if ((int) $st->fetch()['c'] >= 3) fail('با این کد ملی سه نوبت فعال دارید. ابتدا یکی را لغو کنید.', 409);

            $taken = taken_map($d, $d);
            $free = array_values(array_filter(bookable_times($s, $d), fn($x) => !isset($taken[$d . '|' . $x])));
            if (!$free) fail('ظرفیت این روز پر شده است. روز دیگری انتخاب کنید.', 409);

            // take the first free numbered place; the UNIQUE (d, t) key guards against races
            foreach ($free as $t) {
                for ($try = 0; $try < 5; $try++) {
                    $code = (string) random_int(100000, 999999);
                    try {
                        $pdo->prepare('INSERT INTO bookings (d, t, first, last, nid, phone, code, ip, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
                            ->execute([$d, $t, $first, $last, $nid, $phone, $code, client_ip(), time()]);
                        json_out(['ok' => true, 'code' => $code, 'd' => $d, 'n' => (int) $t, 'first' => $first, 'last' => $last]);
                    } catch (PDOException $e) {
                        $msg = $e->getMessage();
                        if (strpos($msg, 'bookings.d') !== false) continue 2;
                        if (strpos($msg, 'bookings.code') === false) throw $e;
                    }
                }
            }
            fail('ظرفیت این روز همین حالا پر شد. روز دیگری انتخاب کنید.', 409);
        }

        case 'lookup':
        case 'cancel': {
            if (!$post) fail('روش درخواست نامعتبر است.', 405);
            $in = input();
            $code = preg_replace('/\D/', '', digits_en((string) ($in['code'] ?? '')));
            $nid = preg_replace('/\D/', '', digits_en((string) ($in['nid'] ?? '')));
            $pdo = db();
            $st = $pdo->prepare('SELECT id, d, CAST(t AS INTEGER) AS n, first, last, code, status FROM bookings WHERE code = ? AND nid = ?');
            $st->execute([$code, $nid]);
            $b = $st->fetch();
            if (!$b) fail('نوبتی با این کد پیگیری و کد ملی پیدا نشد.', 404);
            if ($a === 'lookup') json_out(['booking' => $b]);
            if ($b['d'] < date('Y-m-d')) fail('این نوبت مربوط به گذشته است.', 409);
            $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$b['id']]);
            json_out(['ok' => true]);
        }

        default:
            fail('درخواست نامعتبر است.', 404);
    }
} catch (Throwable $e) {
    error_log('nobat api: ' . $e->getMessage());
    fail('خطای سرور. دوباره تلاش کنید.', 500);
}
