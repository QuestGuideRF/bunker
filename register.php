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
if (strlen($nickname) < 2 || strlen($nickname) > 20) {
    echo json_encode(['success' => false, 'error' => 'Никнейм от 2 до 20 символов']);
    exit;
}
if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ0-9_\-]+$/u', $nickname)) {
    echo json_encode(['success' => false, 'error' => 'Никнейм: буквы, цифры, дефис, подчёркивание']);
    exit;
}
if (strlen($password) < 4) {
    echo json_encode(['success' => false, 'error' => 'Пароль минимум 4 символа']);
    exit;
}
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT id FROM users WHERE nickname = ?');
$st->execute([$nickname]);
if ($st->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Никнейм уже занят']);
    exit;
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO users (nickname, password_hash, theme) VALUES (?, ?, ?)')->execute([$nickname, $hash, 'dark']);
$userId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO user_stats (user_id, games_played, wins) VALUES (?, 0, 0)')->execute([$userId]);
$_SESSION['user_id'] = $userId;
$st = $pdo->prepare('SELECT nickname, theme FROM users WHERE id = ?');
$st->execute([$userId]);
$u = $st->fetch();
echo json_encode([
    'success' => true,
    'data' => [
        'id' => $userId,
        'nickname' => $u['nickname'],
        'theme' => $u['theme'] ?? 'dark',
    ]
]);