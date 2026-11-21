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
$st = $pdo->prepare('SELECT creator_id, status FROM rooms WHERE id = ?');
$st->execute([$roomId]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) bunker_fail('Комната не найдена');
if ((int) $room['creator_id'] !== $uid) bunker_fail('Только создатель может начать новую игру');
if ($room['status'] !== 'finished') bunker_fail('Игра не завершена');
$pdo->prepare('DELETE FROM messages WHERE room_id = ?')->execute([$roomId]);
$pdo->prepare('UPDATE rooms SET status = ?, catastrophe_id = NULL WHERE id = ?')->execute(['waiting', $roomId]);
bunker_ok(['message' => 'Комната готова к новой игре', 'status' => 'waiting']);