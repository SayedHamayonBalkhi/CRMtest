<?php
declare(strict_types=1);

// Loaded first by every page and API file: config, HTTPS, security headers, session, database, helpers.

define('APP_ROOT', dirname(__DIR__));
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/calc.php';

function cfg(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/config.php';
}

date_default_timezone_set(cfg()['timezone']);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// EB_ALLOW_HTTP=1 is only for local automated tests; never set it on a live host.
function https_required(): bool
{
    return cfg()['force_https'] && getenv('EB_ALLOW_HTTP') !== '1';
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// URL path the app lives under ('' when it owns the domain). Lets the panel work in a sub-folder too.
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (str_ends_with($dir, '/api')) {
        $dir = substr($dir, 0, -4);
    }
    // When the whole project sits in public_html and the root .htaccess forwards to /public, hide that segment.
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (str_ends_with($dir, '/public') && !str_starts_with($uriPath, $dir . '/')) {
        $dir = substr($dir, 0, -7);
    }
    return rtrim($dir, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_security_headers(): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    if (https_required()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function start_session(): void
{
    $dir = APP_ROOT . '/storage/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800'); // 8 hours idle
    session_name(cfg()['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') {
    if (https_required() && !is_https()) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        } else {
            http_response_code(400);
        }
        exit;
    }
    send_security_headers();
    start_session();
}

// ---- Database ----

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $c = cfg()['db'];
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    if ($c['driver'] === 'mysql') {
        $dsn = "mysql:host={$c['mysql_host']};port={$c['mysql_port']};dbname={$c['mysql_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['mysql_user'], $c['mysql_pass'], $opts);
    } else {
        $pdo = new PDO('sqlite:' . (getenv('EB_SQLITE_PATH') ?: $c['sqlite_path']), null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

// Creates the tables if they do not exist yet. Safe to run repeatedly.
function db_init(PDO $pdo): void
{
    $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

function db_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ---- CSRF ----

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(mixed $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// ---- Auth ----

function current_user(): ?string
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!db_ready() || !has_users()) {
        header('Location: ' . url('setup'));
        exit;
    }
    if (!current_user()) {
        header('Location: ' . url('login'));
        exit;
    }
}

function has_users(): bool
{
    return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// Returns minutes to wait when the IP has too many recent failed logins, otherwise 0.
function login_blocked_minutes(): int
{
    $window = cfg()['login_window_minutes'] * 60;
    $pdo = db();
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - $window]);
    $st = $pdo->prepare('SELECT COUNT(*), MIN(attempted_at) FROM login_attempts WHERE ip = ?');
    $st->execute([client_ip()]);
    [$count, $oldest] = array_values($st->fetch());
    if ((int)$count < cfg()['login_max_attempts']) {
        return 0;
    }
    return max(1, (int)ceil(((int)$oldest + $window - time()) / 60));
}

function try_login(string $username, string $password): bool
{
    $pdo = db();
    $st = $pdo->prepare('SELECT username, password_hash FROM users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();
    // Always run password_verify so a wrong username takes as long as a wrong password.
    $hash = $user['password_hash'] ?? '$2y$10$wPdTpojXXZ5xg6Qf.xAqsepgHgwuy0r8IdgJnA.X441mL78421Cu2';
    $ok = password_verify($password, $hash) && $user;
    if (!$ok) {
        $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([client_ip(), time()]);
        return false;
    }
    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
    session_regenerate_id(true);
    $_SESSION['user'] = $user['username'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    }
    session_destroy();
}

// ---- JSON API helpers ----

function json_out(int $status, mixed $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// Common checks for every API file. Returns [method, body]. Writes need a valid CSRF header and a JSON body.
function api_start(array $allowed): array
{
    set_exception_handler(function (Throwable $e): void {
        error_log('API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        json_out(500, ['error' => 'Something went wrong on the server.']);
    });
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        json_out(405, ['error' => 'Method not allowed.']);
    }
    if (!db_ready() || !current_user()) {
        json_out(401, ['error' => 'Please sign in again.']);
    }
    $body = [];
    if ($method !== 'GET') {
        if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            json_out(403, ['error' => 'Security token missing or expired. Reload the page and try again.']);
        }
        if ($method !== 'DELETE') {
            if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
                json_out(415, ['error' => 'Send the data as JSON.']);
            }
            $raw = file_get_contents('php://input', false, null, 0, 65537);
            if (strlen($raw) > 65536) {
                json_out(413, ['error' => 'Request is too large.']);
            }
            $body = json_decode($raw, true);
            if (!is_array($body)) {
                json_out(400, ['error' => 'Invalid JSON.']);
            }
        }
    }
    return [$method, $body];
}
