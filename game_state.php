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
$st = $pdo->prepare('SELECT 1 FROM room_players WHERE room_id = ? AND user_id = ?');
$st->execute([$roomId, $uid]);
if (!$st->fetch()) bunker_fail('Вы не в этой комнате');
$st = $pdo->prepare('SELECT g.*, r.name AS room_name, r.cards_per_round AS room_cards_per_round, c.type AS catastrophe_type, c.description AS catastrophe_desc FROM games g JOIN rooms r ON r.id = g.room_id LEFT JOIN catastrophes c ON c.id = g.catastrophe_id WHERE g.room_id = ? ORDER BY g.id DESC LIMIT 1');
$st->execute([$roomId]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game) bunker_fail('Игра не найдена');
$phase = $game['phase'];
$roundNum = (int) $game['round_num'];
$deadline = $game['phase_deadline'];
$timeLeft = null;
if ($deadline && in_array($phase, ['discussing', 'voting'], true)) {
    $dl = strtotime($deadline);
    $timeLeft = max(0, $dl - time());
}
$cardsPerRoundJson = $game['room_cards_per_round'] ?? null;
$st = $pdo->prepare("
    SELECT gp.user_id, gp.is_ready, gp.is_eliminated, gp.eliminated_round, u.nickname, u.avatar_url, pc.id AS card_id
    FROM game_players gp
    JOIN users u ON u.id = gp.user_id
    JOIN player_cards pc ON pc.id = gp.card_id
    WHERE gp.game_id = ?
    ORDER BY gp.is_eliminated, gp.id
");
$st->execute([$game['id']]);
$players = [];
$myCard = null;
if (is_string($cardsPerRoundJson)) $cardsPerRoundJson = json_decode($cardsPerRoundJson, true);
$cardsPerRound = is_array($cardsPerRoundJson) ? $cardsPerRoundJson : [2, 1, 1, 1];
$revealLimit = $cardsPerRound[$roundNum - 1] ?? 1;
$stReveals = $pdo->prepare('SELECT attribute_key, attribute_value, round_num FROM card_reveals WHERE game_id = ? AND user_id = ? AND round_num <= ?');
$stMyReveals = $pdo->prepare('SELECT COUNT(*) FROM card_reveals WHERE game_id = ? AND user_id = ? AND round_num = ?');
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $stReveals->execute([$game['id'], $row['user_id'], $roundNum]);
    $reveals = [];
    $readyThisRound = false;
    while ($r = $stReveals->fetch(PDO::FETCH_ASSOC)) {
        $rn = (int) $r['round_num'];
        if ($phase === 'revealing' && $rn === $roundNum && (int) $row['user_id'] !== $uid && $r['attribute_key'] !== 'profession') continue;
        $reveals[$r['attribute_key']] = $r['attribute_value'];
    }
    if ($phase === 'revealing' && (int) $row['user_id'] !== $uid) {
        $stCount = $pdo->prepare('SELECT COUNT(*) FROM card_reveals WHERE game_id = ? AND user_id = ? AND round_num = ?');
        $stCount->execute([$game['id'], $row['user_id'], $roundNum]);
        $readyThisRound = (int) $stCount->fetchColumn() >= $revealLimit;
    }
    $pl = [
        'user_id' => (int) $row['user_id'],
        'nickname' => $row['nickname'],
        'avatar_url' => $row['avatar_url'],
        'is_ready' => (bool) $row['is_ready'],
        'is_eliminated' => (bool) $row['is_eliminated'],
        'eliminated_round' => $row['eliminated_round'] ? (int) $row['eliminated_round'] : null,
        'reveals' => $reveals,
    ];
    if ($phase === 'revealing') $pl['ready_this_round'] = $readyThisRound;
    if ($row['is_eliminated']) {
        $stCard = $pdo->prepare('SELECT * FROM player_cards WHERE id = ?');
        $stCard->execute([$row['card_id']]);
        $cardRow = $stCard->fetch(PDO::FETCH_ASSOC);
        if ($cardRow) {
            $bio = $cardRow['bio'];
            if (is_string($bio)) $bio = json_decode($bio, true) ?: [];
            $extra = $cardRow['extra'];
            if (is_string($extra)) $extra = json_decode($extra, true) ?: [];
            $pl['full_card'] = [
                'profession' => $cardRow['profession'],
                'health' => $cardRow['health'],
                'phobia' => $cardRow['phobia'],
                'bio' => $bio,
                'extra' => $extra,
                'baggage' => $cardRow['baggage'],
                'superpower' => $cardRow['superpower'],
                'special_condition' => $cardRow['special_condition'],
            ];
        }
    }
    $players[] = $pl;
    if ((int) $row['user_id'] === $uid) {
        $stCard = $pdo->prepare('SELECT * FROM player_cards WHERE id = ?');
        $stCard->execute([$row['card_id']]);
        $cardRow = $stCard->fetch(PDO::FETCH_ASSOC);
        if ($cardRow) {
            $bio = $cardRow['bio'];
            if (is_string($bio)) $bio = json_decode($bio, true) ?: [];
            $extra = $cardRow['extra'];
            if (is_string($extra)) $extra = json_decode($extra, true) ?: [];
            $myCard = [
                'profession' => $cardRow['profession'],
                'health' => $cardRow['health'],
                'phobia' => $cardRow['phobia'],
                'bio' => $bio,
                'extra' => $extra,
                'baggage' => $cardRow['baggage'],
                'superpower' => $cardRow['superpower'],
                'special_condition' => $cardRow['special_condition'],
            ];
        }
    }
}
$myRevealCount = 0;
if ($phase === 'revealing') {
    $stMyReveals->execute([$game['id'], $uid, $roundNum]);
    $myRevealCount = (int) $stMyReveals->fetchColumn();
}
$myVote = null;
$st = $pdo->prepare('SELECT target_id FROM votes WHERE game_id = ? AND round_num = ? AND voter_id = ?');
$st->execute([$game['id'], $roundNum, $uid]);
$v = $st->fetch(PDO::FETCH_ASSOC);
if ($v) $myVote = (int) $v['target_id'];
$st = $pdo->prepare('SELECT target_id, COUNT(*) AS cnt FROM votes WHERE game_id = ? AND round_num = ? GROUP BY target_id');
$st->execute([$game['id'], $roundNum]);
$voteCounts = [];
$totalVotes = 0;
while ($v = $st->fetch(PDO::FETCH_ASSOC)) {
    $voteCounts[(int) $v['target_id']] = (int) $v['cnt'];
    $totalVotes += (int) $v['cnt'];
}
$tieCandidates = null;
if (isset($game['tie_candidates']) && $game['tie_candidates']) {
    $tc = is_string($game['tie_candidates']) ? json_decode($game['tie_candidates'], true) : $game['tie_candidates'];
    if (is_array($tc)) $tieCandidates = $tc;
}
$lastEliminated = null;
if (in_array($phase, ['revealing', 'finished'], true)) {
    $elimRound = ($phase === 'revealing') ? $roundNum - 1 : $roundNum;
    if ($elimRound >= 1) {
        $st = $pdo->prepare('SELECT u.nickname FROM game_players gp JOIN users u ON u.id = gp.user_id WHERE gp.game_id = ? AND gp.eliminated_round = ? LIMIT 1');
        $st->execute([$game['id'], $elimRound]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) $lastEliminated = $row['nickname'];
    }
}
bunker_ok([
    'game' => [
        'id' => (int) $game['id'],
        'phase' => $phase,
        'round_num' => $roundNum,
        'time_left' => $timeLeft,
        'phase_deadline' => $deadline,
        'tie_candidates' => $tieCandidates,
    ],
    'room' => ['name' => $game['room_name']],
    'catastrophe' => ['type' => $game['catastrophe_type'], 'description' => $game['catastrophe_desc']],
    'players' => $players,
    'my_card' => $myCard,
    'my_vote' => $myVote,
    'votes' => ['total' => $totalVotes, 'counts' => $voteCounts, 'i_voted' => $myVote !== null],
    'reveal_limit' => $revealLimit,
    'my_reveal_count' => $myRevealCount,
    'server_time' => time(),
    'last_eliminated' => $lastEliminated,
]);