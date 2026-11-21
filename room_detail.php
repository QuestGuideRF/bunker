<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    http_response_code(401);
    bunker_fail('Не авторизован');
}
bunker_session_close();
$roomId = (int) ($_GET['room_id'] ?? 0);
if (!$roomId) bunker_fail('Нужен room_id');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT r.*, c.type AS catastrophe_type FROM rooms r LEFT JOIN catastrophes c ON c.id = r.catastrophe_id WHERE r.id = ?');
$st->execute([$roomId]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) bunker_fail('Комната не найдена');
$st = $pdo->prepare('SELECT 1 FROM room_players WHERE room_id = ? AND user_id = ?');
$st->execute([$roomId, $uid]);
if (!$st->fetch()) bunker_fail('Вы не в этой комнате');
$st = $pdo->prepare("
    SELECT rp.user_id, rp.is_creator, u.nickname, u.avatar_url
    FROM room_players rp
    JOIN users u ON u.id = rp.user_id
    WHERE rp.room_id = ?
    ORDER BY rp.is_creator DESC, rp.joined_at
");
$st->execute([$roomId]);
$players = [];
while ($p = $st->fetch(PDO::FETCH_ASSOC)) {
    $players[] = [
        'id' => (int) $p['user_id'],
        'nickname' => $p['nickname'],
        'avatar_url' => $p['avatar_url'],
        'is_creator' => (bool) $p['is_creator'],
        'is_me' => (int) $p['user_id'] === $uid,
    ];
}
$activeGame = null;
if ($room['status'] === 'playing') {
    $st = $pdo->prepare('SELECT id, phase, round_num FROM games WHERE room_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$roomId]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if ($g) {
        $activeGame = [
            'id' => (int) $g['id'],
            'phase' => $g['phase'],
            'round_num' => (int) $g['round_num'],
        ];
    }
}
$cardsPerRound = $room['cards_per_round'];
if (is_string($cardsPerRound)) $cardsPerRound = json_decode($cardsPerRound, true);
bunker_ok([
    'id' => (int) $room['id'],
    'name' => $room['name'],
    'code' => $room['code'],
    'is_public' => (bool) $room['is_public'],
    'max_players' => (int) $room['max_players'],
    'bunker_slots' => (int) $room['bunker_slots'],
    'discuss_time' => (int) $room['discuss_time'],
    'vote_time' => (int) $room['vote_time'],
    'cards_per_round' => $cardsPerRound ?: [2, 1, 1, 1],
    'status' => $room['status'],
    'creator_id' => (int) $room['creator_id'],
    'catastrophe' => $room['catastrophe_type'],
    'players' => $players,
    'player_count' => count($players),
    'active_game' => $activeGame,
    'is_creator' => (int) $room['creator_id'] === $uid,
]);