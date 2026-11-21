<?php
require_once __DIR__ . '/_env_session.php';
require_once __DIR__ . '/api/game/helpers.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['changed' => false]);
    exit;
}
bunker_session_close();
$roomId = (int) ($_GET['room_id'] ?? 0);
$lastPhase = $_GET['last_phase'] ?? '';
$lastRound = (int) ($_GET['last_round'] ?? 0);
$lastMsgId = (int) ($_GET['last_msg_id'] ?? 0);
if (!$roomId) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['changed' => false]);
    exit;
}
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT 1 FROM room_players WHERE room_id = ? AND user_id = ?');
$st->execute([$roomId, $uid]);
if (!$st->fetch()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['changed' => false]);
    exit;
}
$st = $pdo->prepare('SELECT id, phase, round_num, phase_deadline FROM games WHERE room_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$roomId]);
$game = $st->fetch(PDO::FETCH_ASSOC);
if (!$game) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['changed' => false]);
    exit;
}
$phase = $game['phase'];
$roundNum = (int) $game['round_num'];
$deadline = $game['phase_deadline'];
if ($deadline && in_array($phase, ['discussing', 'voting'], true)) {
    $dl = strtotime($deadline);
    if (time() >= $dl) {
        if ($phase === 'voting') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id = ? AND is_eliminated = 0');
            $st->execute([$game['id']]);
            $active = (int) $st->fetchColumn();
            $st = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE game_id = ? AND round_num = ?');
            $st->execute([$game['id'], $roundNum]);
            $voted = (int) $st->fetchColumn();
            if ($voted < $active) {
                $st = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ? AND is_eliminated = 0');
                $st->execute([$game['id']]);
                $votedIds = $pdo->prepare('SELECT voter_id FROM votes WHERE game_id = ? AND round_num = ?');
                $votedIds->execute([$game['id'], $roundNum]);
                $votedSet = [];
                while ($r = $votedIds->fetch(PDO::FETCH_ASSOC)) $votedSet[(int)$r['voter_id']] = true;
                $st2 = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ? AND is_eliminated = 0');
                $st2->execute([$game['id']]);
                $all = [];
                while ($r = $st2->fetch(PDO::FETCH_ASSOC)) $all[] = (int)$r['user_id'];
                $needVote = [];
                foreach ($all as $id) { if (!isset($votedSet[$id])) $needVote[] = $id; }
                foreach ($needVote as $vid) {
                    $others = array_filter($all, fn($x) => $x !== $vid);
                    if (!empty($others)) {
                        $target = $others[array_rand($others)];
                        $pdo->prepare('INSERT INTO votes (game_id, round_num, voter_id, target_id) VALUES (?, ?, ?, ?)')->execute([$game['id'], $roundNum, $vid, $target]);
                    }
                }
            }
            eliminateAndAdvance($pdo, (int) $game['id']);
            $st = $pdo->prepare('SELECT phase, round_num FROM games WHERE id = ?');
            $st->execute([$game['id']]);
            $game = $st->fetch(PDO::FETCH_ASSOC);
            $phase = $game['phase'];
            $roundNum = (int) $game['round_num'];
        } elseif ($phase === 'discussing') {
            $st = $pdo->prepare('SELECT vote_time FROM rooms WHERE id = ?');
            $st->execute([$roomId]);
            $voteTime = (int) $st->fetchColumn();
            $pdo->prepare("UPDATE games SET phase = 'voting', phase_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?")->execute([$voteTime, $game['id']]);
            $phase = 'voting';
        }
    }
}
$changed = ($lastPhase !== $phase || $lastRound !== $roundNum);
$st = $pdo->prepare('SELECT id FROM messages WHERE room_id = ? AND id > ? ORDER BY id ASC LIMIT 1');
$st->execute([$roomId, $lastMsgId]);
$hasNewMessages = (bool) $st->fetch();
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'changed' => $changed,
    'phase' => $phase,
    'round_num' => $roundNum,
    'game_id' => (int) $game['id'],
    'room_status' => $phase === 'finished' ? 'finished' : 'playing',
    'has_new_messages' => $hasNewMessages,
]);