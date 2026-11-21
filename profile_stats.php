<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}
bunker_session_close();
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT games_played, wins FROM user_stats WHERE user_id = ?');
$st->execute([$uid]);
$row = $st->fetch();
$games = (int) ($row['games_played'] ?? 0);
$wins = (int) ($row['wins'] ?? 0);
$winRate = $games > 0 ? round($wins / $games * 100, 1) : 0;
$history = [];
$st = $pdo->prepare("
    SELECT g.id, g.room_id, g.started_at, r.name AS room_name, c.type AS catastrophe,
           (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) AS total_players,
           (SELECT COUNT(*) FROM game_players WHERE game_id = g.id AND is_eliminated = 0) AS survivors,
           gp.is_eliminated, gp.eliminated_round
    FROM game_players gp
    JOIN games g ON g.id = gp.game_id
    JOIN rooms r ON r.id = g.room_id
    LEFT JOIN catastrophes c ON c.id = g.catastrophe_id
    WHERE gp.user_id = ? AND g.phase = 'finished'
    ORDER BY g.finished_at DESC
    LIMIT 20
");
$st->execute([$uid]);
while ($h = $st->fetch(PDO::FETCH_ASSOC)) {
    $survived = empty($h['is_eliminated']);
    $history[] = [
        'room_name' => $h['room_name'],
        'started_at' => $h['started_at'],
        'catastrophe' => $h['catastrophe'],
        'total_players' => (int) $h['total_players'],
        'survivors' => (int) $h['survivors'],
        'survived' => $survived,
        'eliminated_round' => $survived ? null : (int) $h['eliminated_round'],
    ];
}
bunker_ok([
    'games_played' => $games,
    'wins' => $wins,
    'win_rate' => $winRate,
    'history' => $history,
]);