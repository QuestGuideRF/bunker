<?php
if (!defined('BUNKER_ENV_LOADED')) {
    define('BUNKER_ENV_LOADED', true);
    $envPath = __DIR__ . '/.env';
    if (is_file($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
                $key = $m[1];
                $val = trim($m[2], " \t\"'");
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $val;
                    putenv("$key=$val");
                }
            }
        }
    }
}
function bunker_env($key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return $default ?? $_ENV[$key] ?? null;
}
function bunker_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $name = bunker_env('SESSION_NAME', 'bunker_sess');
    $lifetime = (int) bunker_env('SESSION_LIFETIME', 7776000);
    if ($lifetime <= 0) $lifetime = 7776000;
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_name($name);
    session_start();
}
function bunker_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $host = bunker_env('DB_HOST', 'localhost');
    $dbname = bunker_env('DB_NAME', '');
    $user = bunker_env('DB_USER', '');
    $pass = bunker_env('DB_PASS', '');
    $charset = bunker_env('DB_CHARSET', 'utf8mb4');
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        if (bunker_env('APP_DEBUG') === 'true') {
            throw $e;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        exit;
    }
    return $pdo;
}
function bunker_uid() {
    bunker_session_start();
    return $_SESSION['user_id'] ?? null;
}
function bunker_ok($data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $data !== null ? ['data' => $data] : []));
    exit;
}
function bunker_fail($error) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}
function bunker_body() {
    $raw = file_get_contents('php://input');
    if ($raw === '') return [];
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : [];
}
function bunker_session_close() {
    if (function_exists('session_write_close')) {
        session_write_close();
    }
}
function logGameAction(PDO $pdo, $gameId, $action, $data = null, $round = null) {
    try {
        $st = $pdo->prepare('INSERT INTO game_log (game_id, round_num, action_type, data) VALUES (?, ?, ?, ?)');
        $st->execute([$gameId, $round, $action, $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null]);
    } catch (Exception $e) {
    }
}