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
$attributeKey = (string) ($body['attribute_key'] ?? '');
$allowed = ['health', 'phobia', 'bio', 'extra', 'baggage', 'superpower', 'special_condition'];
if (!in_array($attributeKey, $allowed, true)) bunker_fail('Неверный атрибут');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT g.phase, g.round_num, g.room_id FROM games g WHERE g.id = ?');
$st->execute([$gameId]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game || $game['phase'] !== 'revealing') bunker_fail('Не фаза раскрытия');
$roomId = (int) $game['room_id'];
$roundNum = (int) $game['round_num'];
$st = $pdo->prepare('SELECT cards_per_round FROM rooms WHERE id = ?');
$st->execute([$roomId]);
$cp = $st->fetchColumn();
$cardsPerRound = is_string($cp) ? json_decode($cp, true) : $cp;
$limit = (int) ($cardsPerRound[$roundNum - 1] ?? 1);
$st = $pdo->prepare('SELECT COUNT(*) FROM card_reveals WHERE game_id = ? AND user_id = ? AND round_num = ?');
$st->execute([$gameId, $uid, $roundNum]);
$myCount = (int) $st->fetchColumn();
if ($myCount >= $limit) bunker_fail('Лимит раскрытий за раунд');
$st = $pdo->prepare('SELECT 1 FROM card_reveals WHERE game_id = ? AND user_id = ? AND round_num = ? AND attribute_key = ?');
$st->execute([$gameId, $uid, $roundNum, $attributeKey]);
if ($st->fetch()) bunker_fail('Уже раскрыто');
$st = $pdo->prepare('SELECT gp.card_id FROM game_players gp WHERE gp.game_id = ? AND gp.user_id = ?');
$st->execute([$gameId, $uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) bunker_fail('Игрок не найден');
$st = $pdo->prepare('SELECT * FROM player_cards WHERE id = ?');
$st->execute([$row['card_id']]);
$card = $st->fetch(PDO::FETCH_ASSOC);
if (!$card) bunker_fail('Карта не найдена');
$value = null;
switch ($attributeKey) {
    case 'health': $value = $card['health']; break;
    case 'phobia': $value = $card['phobia']; break;
    case 'bio': $value = $card['bio']; break;
    case 'extra': $value = $card['extra']; break;
    case 'baggage': $value = $card['baggage']; break;
    case 'superpower': $value = $card['superpower'] ?? ''; break;
    case 'special_condition': $value = $card['special_condition'] ?? ''; break;
}
if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
$value = (string) $value;
$pdo->prepare('INSERT INTO card_reveals (game_id, user_id, round_num, attribute_key, attribute_value) VALUES (?, ?, ?, ?, ?)')->execute([$gameId, $uid, $roundNum, $attributeKey, $value]);
$myCount++;
$allDone = true;
$st = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ? AND is_eliminated = 0');
$st->execute([$gameId]);
while ($p = $st->fetch(PDO::FETCH_ASSOC)) {
    $st2 = $pdo->prepare('SELECT COUNT(*) FROM card_reveals WHERE game_id = ? AND user_id = ? AND round_num = ?');
    $st2->execute([$gameId, $p['user_id'], $roundNum]);
    $c = (int) $st2->fetchColumn();
    if ($c < $limit) { $allDone = false; break; }
}
$newPhase = $game['phase'];
if ($allDone) {
    $st = $pdo->prepare('SELECT discuss_time FROM rooms WHERE id = ?');
    $st->execute([$roomId]);
    $discussTime = (int) $st->fetchColumn();
    $pdo->prepare("UPDATE games SET phase = 'discussing', phase_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?")->execute([$discussTime, $gameId]);
    $newPhase = 'discussing';
}
bunker_ok([
    'attribute_key' => $attributeKey,
    'my_reveal_count' => $myCount,
    'limit' => $limit,
    'all_done' => $allDone,
    'phase' => $newPhase,
]);