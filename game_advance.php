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
$gameId = (int) ($body['game_id'] ?? 0);
if (!$gameId) bunker_fail('Нужен game_id');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT g.phase, g.room_id FROM games g WHERE g.id = ?');
$st->execute([$gameId]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game) bunker_fail('Игра не найдена');
$roomId = (int) $game['room_id'];
$st = $pdo->prepare('SELECT creator_id FROM rooms WHERE id = ?');
$st->execute([$roomId]);
$creatorId = (int) $st->fetchColumn();
if ($creatorId !== $uid) bunker_fail('Только создатель может переводить фазу');
$phase = $game['phase'];
if ($phase === 'discussing') {
    $st = $pdo->prepare('SELECT vote_time FROM rooms WHERE id = ?');
    $st->execute([$roomId]);
    $voteTime = (int) $st->fetchColumn();
    $pdo->prepare("UPDATE games SET phase = 'voting', phase_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?")->execute([$voteTime, $gameId]);
    bunker_ok(['phase' => 'voting']);
} elseif ($phase === 'revealing') {
    $st = $pdo->prepare('SELECT discuss_time FROM rooms WHERE id = ?');
    $st->execute([$roomId]);
    $discussTime = (int) $st->fetchColumn();
    $pdo->prepare("UPDATE games SET phase = 'discussing', phase_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?")->execute([$discussTime, $gameId]);
    bunker_ok(['phase' => 'discussing']);
} else {
    bunker_fail('Сейчас нельзя перевести фазу');
}