<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);
/** config lives inside data/ so it survives redeploys when data/ is on a persistent disk */
define('CONFIG_FILE', APP_ROOT . '/data/config.php');

/** Redirect to the installer when the app has not been set up yet. */
function require_installed(string $setupUrl): void
{
    if (!is_file(CONFIG_FILE)) {
        header('Location: ' . $setupUrl);
        exit;
    }
}

function app_config(): array
{
    static $c = null;
    if ($c === null) {
        $c = require CONFIG_FILE;
        date_default_timezone_set($c['timezone'] ?? 'Asia/Tehran');
    }
    return $c;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = app_config();
        $pdo = new PDO('sqlite:' . $c['db']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        migrate($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        d TEXT NOT NULL, t TEXT NOT NULL,
        first TEXT NOT NULL, last TEXT NOT NULL, nid TEXT NOT NULL, phone TEXT NOT NULL,
        code TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT "active",
        ip TEXT NOT NULL DEFAULT "",
        created_at INTEGER NOT NULL,
        UNIQUE (d, t)
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS blocks (d TEXT NOT NULL, t TEXT NOT NULL, UNIQUE (d, t))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, at INTEGER NOT NULL)');
}

/* ---------- settings ---------- */

function default_settings(): array
{
    return ['title' => 'نوبت‌یار', 'days' => [6, 1, 3], 'capacity' => 10, 'horizon' => 14];
}

function get_settings(): array
{
    $row = db()->query('SELECT v FROM settings WHERE k = "config"')->fetch();
    $saved = $row ? json_decode($row['v'], true) : [];
    $saved = is_array($saved) ? $saved : [];
    if ($row && !isset($saved['capacity'])) {
        // one-time upgrade from the hourly version: Sat/Mon/Wed, N bookings per day
        $saved['days'] = [6, 1, 3];
        $saved['capacity'] = 10;
        unset($saved['start'], $saved['end'], $saved['duration']);
        save_settings(array_merge(default_settings(), $saved));
    }
    return array_merge(default_settings(), $saved);
}

function save_settings(array $s): void
{
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES ("config", ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    $st->execute([json_encode($s, JSON_UNESCAPED_UNICODE)]);
}

/* ---------- time helpers ---------- */

function to_min(string $t): int
{
    [$h, $m] = array_map('intval', explode(':', $t));
    return $h * 60 + $m;
}

function fmt_min(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

function valid_time(string $t): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
}

function valid_date(string $d): bool
{
    $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

function day_times(array $s): array
{
    // no clock times: each day has `capacity` numbered places ("01", "02", ...)
    $out = [];
    for ($i = 1; $i <= max(1, (int) $s['capacity']); $i++) {
        $out[] = sprintf('%02d', $i);
    }
    return $out;
}

/** Dates (Y-m-d) the public can book, honouring working days and horizon. */
function open_days(array $s): array
{
    $out = [];
    $d = new DateTimeImmutable('today');
    for ($i = 0; $i < (int) $s['horizon']; $i++) {
        if (in_array((int) $d->format('w'), array_map('intval', $s['days']), true)) {
            $out[] = $d->format('Y-m-d');
        }
        $d = $d->modify('+1 day');
    }
    return $out;
}

/** Times of one day that are still in the future (all of them for later days). */
function bookable_times(array $s, string $d): array
{
    return day_times($s);
}

/** Set of "Y-m-d|H:i" keys that are booked or blocked between two dates. */
function taken_map(string $from, string $to): array
{
    $st = db()->prepare('SELECT d, t FROM bookings WHERE d BETWEEN ? AND ? UNION SELECT d, t FROM blocks WHERE d BETWEEN ? AND ?');
    $st->execute([$from, $to, $from, $to]);
    $m = [];
    foreach ($st->fetchAll() as $r) {
        $m[$r['d'] . '|' . $r['t']] = true;
    }
    return $m;
}

/* ---------- validation ---------- */

function digits_en(string $s): string
{
    return strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
}

function valid_nid(string $s): bool
{
    if (!preg_match('/^\d{10}$/', $s) || preg_match('/^(\d)\1{9}$/', $s)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int) $s[$i] * (10 - $i);
    }
    $r = $sum % 11;
    $c = (int) $s[9];
    return $r < 2 ? $c === $r : $c === 11 - $r;
}

/* ---------- HTTP helpers ---------- */

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $status = 400): void
{
    json_out(['error' => $msg], $status);
}

function input(): array
{
    $raw = file_get_contents('php://input');
    $j = json_decode($raw ?: '', true);
    return is_array($j) ? $j : [];
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

/* ---------- admin session ---------- */

function admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nobat_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => is_https()]);
    session_start();
}

function admin_logged_in(): bool
{
    admin_session();
    return !empty($_SESSION['admin']) && (time() - (int) ($_SESSION['seen'] ?? 0)) < 8 * 3600;
}

function csrf_token(): string
{
    admin_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(?string $t): bool
{
    admin_session();
    return $t !== null && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function login_blocked(): bool
{
    $st = db()->prepare('SELECT COUNT(*) c FROM login_attempts WHERE ip = ? AND at > ?');
    $st->execute([client_ip(), time() - 900]);
    return (int) $st->fetch()['c'] >= 5;
}

function login_failed(): void
{
    db()->prepare('INSERT INTO login_attempts (ip, at) VALUES (?, ?)')->execute([client_ip(), time()]);
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 86400]);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
