<?php
require_once __DIR__ . '/_env_session.php';
require_once __DIR__ . '/api/card_generator.php';
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
$st = $pdo->prepare('SELECT r.* FROM rooms r WHERE r.id = ?');
$st->execute([$roomId]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) bunker_fail('Комната не найдена');
if ((int) $room['creator_id'] !== $uid) bunker_fail('Только создатель может начать');
if ($room['status'] !== 'waiting') bunker_fail('Игра уже идёт');
$st = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
$st->execute([$roomId]);
$cnt = (int) $st->fetchColumn();
if ($cnt < 4) bunker_fail('Нужно минимум 4 игрока');
$catastropheId = (int) $room['catastrophe_id'];
if (!$catastropheId) {
    $st = $pdo->query('SELECT id FROM catastrophes ORDER BY RAND() LIMIT 1');
    $catastropheId = (int) $st->fetchColumn();
}
$pdo->prepare('UPDATE rooms SET status = ?, catastrophe_id = ? WHERE id = ?')->execute(['playing', $catastropheId, $roomId]);
$pdo->prepare('INSERT INTO games (room_id, catastrophe_id, phase, round_num) VALUES (?, ?, ?, 1)')->execute([$roomId, $catastropheId, 'cards']);
$gameId = (int) $pdo->lastInsertId();
$gen = new CardGenerator();
$superpowersOn = (bool) $room['superpowers_on'];
$st = $pdo->prepare('SELECT user_id FROM room_players WHERE room_id = ? ORDER BY is_creator DESC, joined_at');
$st->execute([$roomId]);
$playerOrder = $st->fetchAll(PDO::FETCH_COLUMN);
foreach ($playerOrder as $order => $userId) {
    $card = $gen->generateOne($superpowersOn);
    $pdo->prepare('INSERT INTO player_cards (profession, health, phobia, bio, extra, baggage, superpower, special_condition) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        $card['profession'],
        $card['health'],
        $card['phobia'],
        json_encode($card['bio'], JSON_UNESCAPED_UNICODE),
        json_encode($card['extra'], JSON_UNESCAPED_UNICODE),
        $card['baggage'],
        $card['superpower'],
        $card['special_condition'],
    ]);
    $cardId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO game_players (game_id, user_id, card_id, is_ready) VALUES (?, ?, ?, 0)')->execute([$gameId, $userId, $cardId]);
    $pdo->prepare('INSERT INTO card_reveals (game_id, user_id, round_num, attribute_key, attribute_value) VALUES (?, ?, 1, ?, ?)')->execute([$gameId, $userId, 'profession', $card['profession']]);
}
bunker_ok(['game_id' => $gameId, 'phase' => 'cards', 'message' => 'Игра началась']);