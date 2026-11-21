<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    http_response_code(401);
    bunker_fail('Не авторизован');
}
bunker_session_close();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $roomId = (int) ($_GET['room_id'] ?? 0);
} else {
    $body = bunker_body();
    $roomId = (int) ($body['room_id'] ?? 0);
}
if (!$roomId) bunker_fail('Нужен room_id');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT 1 FROM room_players WHERE room_id = ? AND user_id = ?');
$st->execute([$roomId, $uid]);
if (!$st->fetch()) bunker_fail('Вы не в этой комнате');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lastId = (int) ($_GET['last_id'] ?? 0);
    $st = $pdo->prepare("
        SELECT m.id, m.text, m.user_id, m.created_at, u.nickname, u.avatar_url
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.room_id = ? AND m.id > ?
        ORDER BY m.id ASC
        LIMIT 50
    ");
    $st->execute([$roomId, $lastId]);
    $list = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $list[] = [
            'id' => (int) $row['id'],
            'text' => $row['text'],
            'user_id' => (int) $row['user_id'],
            'nickname' => $row['nickname'],
            'avatar_url' => $row['avatar_url'],
            'created_at' => $row['created_at'],
            'is_me' => (int) $row['user_id'] === $uid,
        ];
    }
    bunker_ok($list);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = bunker_body();
    $text = trim((string) ($body['text'] ?? ''));
    if ($text === '' || strlen($text) > 500) bunker_fail('Сообщение 1–500 символов');
    $st = $pdo->prepare('SELECT g.phase FROM games g WHERE g.room_id = ? ORDER BY g.id DESC LIMIT 1');
    $st->execute([$roomId]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if ($g && $g['phase'] === 'voting') bunker_fail('Чат закрыт во время голосования');
    $pdo->prepare('INSERT INTO messages (room_id, user_id, text) VALUES (?, ?, ?)')->execute([$roomId, $uid, $text]);
    $msgId = (int) $pdo->lastInsertId();
    $st = $pdo->prepare('SELECT m.id, m.text, m.user_id, m.created_at, u.nickname, u.avatar_url FROM messages m JOIN users u ON u.id = m.user_id WHERE m.id = ?');
    $st->execute([$msgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    bunker_ok([
        'id' => (int) $row['id'],
        'text' => $row['text'],
        'user_id' => (int) $row['user_id'],
        'nickname' => $row['nickname'],
        'avatar_url' => $row['avatar_url'],
        'created_at' => $row['created_at'],
        'is_me' => true,
    ]);
}
bunker_fail('Метод не разрешён');