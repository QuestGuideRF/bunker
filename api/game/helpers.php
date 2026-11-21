<?php
function eliminateAndAdvance(PDO $pdo, int $gameId): void
{
    $st = $pdo->prepare('SELECT room_id, round_num, tie_candidates FROM games WHERE id = ?');
    $st->execute([$gameId]);
    $game = $st->fetch(PDO::FETCH_ASSOC);
    if (!$game) return;
    $roomId = (int) $game['room_id'];
    $roundNum = (int) $game['round_num'];
    $tieCandidates = isset($game['tie_candidates']) ? $game['tie_candidates'] : null;
    if ($tieCandidates && is_string($tieCandidates)) {
        $tieCandidates = json_decode($tieCandidates, true);
    }
    $st = $pdo->prepare('SELECT target_id, COUNT(*) AS cnt FROM votes WHERE game_id = ? AND round_num = ? GROUP BY target_id ORDER BY cnt DESC');
    $st->execute([$gameId, $roundNum]);
    $votes = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($votes)) return;
    $maxVotes = (int) $votes[0]['cnt'];
    $candidates = [];
    foreach ($votes as $v) {
        if ((int) $v['cnt'] === $maxVotes) {
            $candidates[] = (int) $v['target_id'];
        }
    }
    if (count($candidates) > 1) {
        if (is_array($tieCandidates) && count($tieCandidates) > 0) {
            $eliminateId = $candidates[array_rand($candidates)];
        } else {
            $pdo->prepare('UPDATE games SET tie_candidates = ? WHERE id = ?')->execute([json_encode($candidates), $gameId]);
            $pdo->prepare('DELETE FROM votes WHERE game_id = ? AND round_num = ?')->execute([$gameId, $roundNum]);
            return;
        }
    } else {
        $eliminateId = $candidates[0];
    }
    $st = $pdo->prepare('SELECT bunker_slots FROM rooms WHERE id = ?');
    $st->execute([$roomId]);
    $bunkerSlots = (int) $st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id = ? AND is_eliminated = 0');
    $st->execute([$gameId]);
    $survivors = (int) $st->fetchColumn();
    if ($survivors <= 2) {
        $pdo->prepare("UPDATE games SET phase = 'finished', round_num = ?, phase_deadline = NULL, finished_at = NOW() WHERE id = ?")
            ->execute([$roundNum, $gameId]);
        $pdo->prepare('UPDATE rooms SET status = ? WHERE id = ?')->execute(['finished', $roomId]);
        $st = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ?');
        $st->execute([$gameId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('UPDATE user_stats SET games_played = games_played + 1 WHERE user_id = ?')->execute([$row['user_id']]);
        }
        $st = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ? AND is_eliminated = 0');
        $st->execute([$gameId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('UPDATE user_stats SET wins = wins + 1 WHERE user_id = ?')->execute([$row['user_id']]);
        }
        return;
    }
    $pdo->prepare('UPDATE game_players SET is_eliminated = 1, eliminated_round = ? WHERE game_id = ? AND user_id = ?')
        ->execute([$roundNum, $gameId, $eliminateId]);
    $pdo->prepare('UPDATE games SET tie_candidates = NULL WHERE id = ?')->execute([$gameId]);
    $st = $pdo->prepare('SELECT bunker_slots FROM rooms WHERE id = ?');
    $st->execute([$roomId]);
    $bunkerSlots = (int) $st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id = ? AND is_eliminated = 0');
    $st->execute([$gameId]);
    $survivors = (int) $st->fetchColumn();
    if ($survivors <= $bunkerSlots) {
        $pdo->prepare("UPDATE games SET phase = 'finished', round_num = ?, phase_deadline = NULL, finished_at = NOW() WHERE id = ?")
            ->execute([$roundNum, $gameId]);
        $pdo->prepare('UPDATE rooms SET status = ? WHERE id = ?')->execute(['finished', $roomId]);
        $st = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ?');
        $st->execute([$gameId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('UPDATE user_stats SET games_played = games_played + 1 WHERE user_id = ?')->execute([$row['user_id']]);
        }
        $st = $pdo->prepare('SELECT user_id FROM game_players WHERE game_id = ? AND is_eliminated = 0');
        $st->execute([$gameId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('UPDATE user_stats SET wins = wins + 1 WHERE user_id = ?')->execute([$row['user_id']]);
        }
        return;
    }
    $nextRound = $roundNum + 1;
    $pdo->prepare("UPDATE games SET phase = 'revealing', round_num = ?, phase_deadline = NULL WHERE id = ?")
        ->execute([$nextRound, $gameId]);
}