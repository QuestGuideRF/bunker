<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    http_response_code(401);
    bunker_fail('Не авторизован');
}
bunker_session_close();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bunker_fail('Метод не разрешён');
$body = bunker_body();
$roomId = (int) ($body['room_id'] ?? 0);
if (!$roomId) bunker_fail('Нужен room_id');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT is_creator FROM room_players WHERE room_id = ? AND user_id = ?');
$st->execute([$roomId, $uid]);
$rp = $st->fetch(PDO::FETCH_ASSOC);
if (!$rp) bunker_fail('Вы не в этой комнате');
$pdo->prepare('DELETE FROM room_players WHERE room_id = ? AND user_id = ?')->execute([$roomId, $uid]);
if ($rp['is_creator']) {
    $st = $pdo->prepare('SELECT user_id FROM room_players WHERE room_id = ? ORDER BY joined_at LIMIT 1');
    $st->execute([$roomId]);
    $next = $st->fetch(PDO::FETCH_ASSOC);
    if ($next) {
        $pdo->prepare('UPDATE rooms SET creator_id = ? WHERE id = ?')->execute([$next['user_id'], $roomId]);
        $pdo->prepare('UPDATE room_players SET is_creator = 1 WHERE room_id = ? AND user_id = ?')->execute([$roomId, $next['user_id']]);
    }
}
$st = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
$st->execute([$roomId]);
if ((int) $st->fetchColumn() === 0) {
    $pdo->prepare('DELETE FROM messages WHERE room_id = ?')->execute([$roomId]);
    $pdo->prepare('DELETE FROM rooms WHERE id = ?')->execute([$roomId]);
}
bunker_ok(['message' => 'Вы вышли из комнаты']);