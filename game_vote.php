<?php
require_once __DIR__ . '/_env_session.php';
require_once __DIR__ . '/api/game/helpers.php';
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
$targetId = (int) ($body['target_id'] ?? 0);
if (!$gameId || !$targetId) bunker_fail('Нужны game_id и target_id');
if ($targetId === $uid) bunker_fail('Нельзя голосовать за себя');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT phase, round_num FROM games WHERE id = ?');
$st->execute([$gameId]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game || $game['phase'] !== 'voting') bunker_fail('Не фаза голосования');
$roundNum = (int) $game['round_num'];
$st = $pdo->prepare('SELECT 1 FROM game_players WHERE game_id = ? AND user_id = ? AND is_eliminated = 0');
$st->execute([$gameId, $uid]);
if (!$st->fetch()) bunker_fail('Вы исключены и не можете голосовать');
$st = $pdo->prepare('SELECT 1 FROM game_players WHERE game_id = ? AND user_id = ? AND is_eliminated = 0');
$st->execute([$gameId, $targetId]);
if (!$st->fetch()) bunker_fail('Нельзя голосовать за этого игрока');
$tieCandidates = null;
try {
    $st = $pdo->prepare('SELECT tie_candidates FROM games WHERE id = ?');
    $st->execute([$gameId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['tie_candidates']) && $row['tie_candidates']) {
        $tieCandidates = is_string($row['tie_candidates']) ? json_decode($row['tie_candidates'], true) : $row['tie_candidates'];
    }
} catch (Throwable $e) {
}
if (is_array($tieCandidates) && count($tieCandidates) > 0 && !in_array($targetId, $tieCandidates)) {
    bunker_fail('При переголосовании можно голосовать только за указанных игроков');
}
$st = $pdo->prepare('SELECT 1 FROM votes WHERE game_id = ? AND round_num = ? AND voter_id = ?');
$st->execute([$gameId, $roundNum, $uid]);
if ($st->fetch()) bunker_fail('Вы уже голосовали');
$pdo->prepare('INSERT INTO votes (game_id, round_num, voter_id, target_id) VALUES (?, ?, ?, ?)')->execute([$gameId, $roundNum, $uid, $targetId]);
$st = $pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id = ? AND is_eliminated = 0');
$st->execute([$gameId]);
$total = (int) $st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE game_id = ? AND round_num = ?');
$st->execute([$gameId, $roundNum]);
$voted = (int) $st->fetchColumn();
if ($voted >= $total) {
    eliminateAndAdvance($pdo, $gameId);
}
bunker_ok(['all_voted' => $voted >= $total]);