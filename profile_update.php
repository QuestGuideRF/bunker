<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}
$pdo = bunker_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    bunker_session_close();
    $st = $pdo->prepare('SELECT id, nickname, avatar_url, theme, created_at FROM users WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u) bunker_fail('Пользователь не найден');
    bunker_ok([
        'id' => (int) $u['id'],
        'nickname' => $u['nickname'],
        'avatar_url' => $u['avatar_url'],
        'theme' => $u['theme'] ?? 'dark',
        'created_at' => $u['created_at'],
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bunker_fail('Метод не разрешён');
$body = bunker_body();
$updates = [];
$params = [];
if (isset($body['nickname'])) {
    $nickname = trim((string) $body['nickname']);
    if (strlen($nickname) < 2 || strlen($nickname) > 20) bunker_fail('Никнейм от 2 до 20 символов');
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ0-9_\-]+$/u', $nickname)) bunker_fail('Никнейм: буквы, цифры, дефис, подчёркивание');
    $st = $pdo->prepare('SELECT id FROM users WHERE nickname = ? AND id != ?');
    $st->execute([$nickname, $uid]);
    if ($st->fetch()) bunker_fail('Никнейм уже занят');
    $updates[] = 'nickname = ?';
    $params[] = $nickname;
}
if (isset($body['theme']) && in_array($body['theme'], ['dark', 'light'], true)) {
    $updates[] = 'theme = ?';
    $params[] = $body['theme'];
}
if (isset($body['old_password'], $body['new_password'])) {
    $old = (string) $body['old_password'];
    $new = (string) $body['new_password'];
    if (strlen($new) < 4) bunker_fail('Новый пароль минимум 4 символа');
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if (!$row || !password_verify($old, $row['password_hash'])) bunker_fail('Неверный текущий пароль');
    $updates[] = 'password_hash = ?';
    $params[] = password_hash($new, PASSWORD_DEFAULT);
}
if (empty($updates)) {
    $st = $pdo->prepare('SELECT id, nickname, avatar_url, theme FROM users WHERE id = ?');
    $st->execute([$uid]);
    bunker_ok($st->fetch(PDO::FETCH_ASSOC));
}
$params[] = $uid;
$sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
$pdo->prepare($sql)->execute($params);
$st = $pdo->prepare('SELECT id, nickname, avatar_url, theme FROM users WHERE id = ?');
$st->execute([$uid]);
bunker_ok($st->fetch(PDO::FETCH_ASSOC));