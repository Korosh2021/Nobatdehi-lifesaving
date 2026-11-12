<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
if (!is_file(CONFIG_FILE)) fail('برنامه نصب نشده است.', 503);
app_config();
security_headers();

if (!admin_logged_in()) fail('نیاز به ورود دارید.', 401);
$_SESSION['seen'] = time();

$a = $_GET['a'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) fail('نشست منقضی شده است. صفحه را دوباره باز کنید.', 403);

function booking_rows(): array
{
    return db()->query('SELECT id, d, t, first, last, nid, phone, code, status, created_at FROM bookings ORDER BY d, t')->fetchAll();
}

function csv_cell(string $v): string
{
    if ($v !== '' && strpos('=+-@', $v[0]) !== false) $v = "'" . $v;
    return '"' . str_replace('"', '""', $v) . '"';
}

try {
    switch ($a) {
        case 'list': {
            $rows = booking_rows();
            $today = date('Y-m-d');
            $todayN = $upN = 0;
            foreach ($rows as $r) {
                if ($r['d'] === $today && $r['status'] !== 'done') $todayN++;
                if ($r['d'] >= $today) $upN++;
            }
            json_out(['bookings' => $rows, 'today' => $today, 'stats' => ['today' => $todayN, 'upcoming' => $upN, 'all' => count($rows)]]);
        }

        case 'status': {
            if (!$post) fail('روش نامعتبر.', 405);
            $in = input();
            $status = ($in['status'] ?? '') === 'done' ? 'done' : 'active';
            db()->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, (int) ($in['id'] ?? 0)]);
            json_out(['ok' => true]);
        }

        case 'cancel': {
            if (!$post) fail('روش نامعتبر.', 405);
            db()->prepare('DELETE FROM bookings WHERE id = ?')->execute([(int) (input()['id'] ?? 0)]);
            json_out(['ok' => true]);
        }

        case 'day': {
            $s = get_settings();
            $days = open_days($s);
            $d = (string) ($_GET['d'] ?? '');
            if ($d === '') $d = $days[0] ?? '';
            if (!valid_date($d)) json_out(['days' => $days, 'd' => '', 'slots' => []]);
            $pdo = db();
            $b = $pdo->prepare('SELECT t, first, last FROM bookings WHERE d = ?');
            $b->execute([$d]);
            $booked = [];
            foreach ($b->fetchAll() as $r) $booked[$r['t']] = $r['first'] . ' ' . $r['last'];
            $k = $pdo->prepare('SELECT t FROM blocks WHERE d = ?');
            $k->execute([$d]);
            $blocked = array_column($k->fetchAll(), 't');
            $slots = [];
            foreach (day_times($s) as $t) {
                $slots[] = ['t' => $t, 'state' => isset($booked[$t]) ? 'booked' : (in_array($t, $blocked, true) ? 'blocked' : 'free'), 'name' => $booked[$t] ?? ''];
            }
            json_out(['days' => $days, 'd' => $d, 'slots' => $slots]);
        }

        case 'block': {
            if (!$post) fail('روش نامعتبر.', 405);
            $in = input();
            $d = (string) ($in['d'] ?? '');
            $t = (string) ($in['t'] ?? '');
            if (!valid_date($d) || !valid_time($t)) fail('زمان نامعتبر است.');
            if (!empty($in['on'])) {
                $st = db()->prepare('SELECT 1 FROM bookings WHERE d = ? AND t = ?');
                $st->execute([$d, $t]);
                if ($st->fetch()) fail('این ساعت رزرو شده است. ابتدا نوبت را لغو کنید.', 409);
                db()->prepare('INSERT OR IGNORE INTO blocks (d, t) VALUES (?, ?)')->execute([$d, $t]);
            } else {
                db()->prepare('DELETE FROM blocks WHERE d = ? AND t = ?')->execute([$d, $t]);
            }
            json_out(['ok' => true]);
        }

        case 'cfg': {
            if (!$post) json_out(['settings' => get_settings()]);
            $in = input();
            $days = array_values(array_unique(array_filter(array_map('intval', (array) ($in['days'] ?? [])), fn($x) => $x >= 0 && $x <= 6)));
            $s = [
                'title' => mb_substr(trim((string) ($in['title'] ?? '')), 0, 50),
                'days' => $days,
                'capacity' => (int) ($in['capacity'] ?? 0),
                'horizon' => (int) ($in['horizon'] ?? 0),
            ];
            if ($s['title'] === '') fail('نام مجموعه را وارد کنید.');
            if (!$days) fail('حداقل یک روز کاری انتخاب کنید.');
            if ($s['capacity'] < 1 || $s['capacity'] > 200) fail('ظرفیت هر روز باید بین ۱ تا ۲۰۰ باشد.');
            if ($s['horizon'] < 1 || $s['horizon'] > 90) fail('تعداد روزهای آینده نامعتبر است.');
            save_settings($s);
            json_out(['ok' => true, 'settings' => $s]);
        }

        case 'password': {
            if (!$post) fail('روش نامعتبر.', 405);
            $in = input();
            $cfg = app_config();
            if (!password_verify((string) ($in['current'] ?? ''), $cfg['admin_hash'])) fail('رمز فعلی درست نیست.', 403);
            $new = (string) ($in['new'] ?? '');
            if (strlen($new) < 8) fail('رمز جدید باید دست‌کم ۸ نویسه باشد.');
            $cfg['admin_hash'] = password_hash($new, PASSWORD_DEFAULT);
            $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) fail('ذخیرهٔ رمز ممکن نشد. اجازهٔ نوشتن روی config.php را بررسی کنید.', 500);
            json_out(['ok' => true]);
        }

        case 'export': {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');
            header('Cache-Control: no-store');
            echo "\xEF\xBB\xBF";
            echo implode(',', array_map('csv_cell', ['تاریخ (میلادی)', 'شماره نوبت', 'نام', 'نام خانوادگی', 'کد ملی', 'شماره تماس', 'کد پیگیری', 'وضعیت'])) . "\r\n";
            foreach (booking_rows() as $r) {
                echo implode(',', [
                    csv_cell($r['d']), csv_cell((string) (int) $r['t']), csv_cell($r['first']), csv_cell($r['last']),
                    '"=""' . $r['nid'] . '"""', '"=""' . $r['phone'] . '"""', csv_cell($r['code']),
                    csv_cell($r['status'] === 'done' ? 'انجام شد' : 'در انتظار'),
                ]) . "\r\n";
            }
            exit;
        }

        default:
            fail('درخواست نامعتبر است.', 404);
    }
} catch (Throwable $e) {
    error_log('nobat admin api: ' . $e->getMessage());
    fail('خطای سرور. دوباره تلاش کنید.', 500);
}
