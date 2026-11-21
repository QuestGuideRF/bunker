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
$targetId = (int) ($body['user_id'] ?? 0);
if (!$roomId || !$targetId) bunker_fail('Нужны room_id и user_id');
if ($targetId === $uid) bunker_fail('Нельзя кикнуть себя');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT creator_id, status FROM rooms WHERE id = ?');
$st->execute([$roomId]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room || (int) $room['creator_id'] !== $uid) bunker_fail('Только создатель может кикать');
if ($room['status'] !== 'waiting') bunker_fail('Кик только в лобби (ожидание), не во время игры');
$pdo->prepare('DELETE FROM room_players WHERE room_id = ? AND user_id = ?')->execute([$roomId, $targetId]);
$st = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
$st->execute([$roomId]);
if ((int) $st->fetchColumn() === 0) {
    $pdo->prepare('DELETE FROM messages WHERE room_id = ?')->execute([$roomId]);
    $pdo->prepare('DELETE FROM rooms WHERE id = ?')->execute([$roomId]);
}
bunker_ok(['message' => 'Игрок исключён']);