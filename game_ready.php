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
$st = $pdo->prepare('SELECT phase FROM games WHERE id = ?');
$st->execute([$gameId]);
$g = $st->fetch(PDO::FETCH_ASSOC);
if (!$g || $g['phase'] !== 'cards') bunker_fail('Не фаза карточек');
$pdo->prepare('UPDATE game_players SET is_ready = 1 WHERE game_id = ? AND user_id = ?')->execute([$gameId, $uid]);
$st = $pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id = ?');
$st->execute([$gameId]);
$total = (int) $st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id = ? AND is_ready = 1');
$st->execute([$gameId]);
$ready = (int) $st->fetchColumn();
$allReady = ($total > 0 && $ready >= $total);
if ($allReady) {
    $st = $pdo->prepare('SELECT room_id FROM games WHERE id = ?');
    $st->execute([$gameId]);
    $roomId = (int) $st->fetchColumn();
    $st = $pdo->prepare('SELECT cards_per_round FROM rooms WHERE id = ?');
    $st->execute([$roomId]);
    $cp = $st->fetchColumn();
    $arr = is_string($cp) ? json_decode($cp, true) : $cp;
    $firstLimit = (int) ($arr[0] ?? 1);
    $pdo->prepare("UPDATE games SET phase = 'revealing', round_num = 1 WHERE id = ?")->execute([$gameId]);
}
bunker_ok(['all_ready' => $allReady]);