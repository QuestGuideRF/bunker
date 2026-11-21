<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_env_session.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён']);
    exit;
}
$lifetime = (int) bunker_env('SESSION_LIFETIME', 7776000);
session_set_cookie_params(['lifetime' => $lifetime, 'path' => '/', 'samesite' => 'Lax']);
session_name(bunker_env('SESSION_NAME', 'bunker_sess'));
session_start();
$body = bunker_body();
$nickname = trim((string) ($body['nickname'] ?? ''));
$password = (string) ($body['password'] ?? '');
if ($nickname === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'Введите никнейм и пароль']);
    exit;
}
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT id, nickname, password_hash, theme FROM users WHERE nickname = ? LIMIT 1');
$st->execute([$nickname]);
$user = $st->fetch();
if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Неверный никнейм или пароль']);
    exit;
}
$_SESSION['user_id'] = (int) $user['id'];
echo json_encode([
    'success' => true,
    'data' => [
        'id' => (int) $user['id'],
        'nickname' => $user['nickname'],
        'theme' => $user['theme'] ?? 'dark',
    ]
]);