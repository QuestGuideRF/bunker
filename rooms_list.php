<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    http_response_code(401);
    bunker_fail('Не авторизован');
}
bunker_session_close();
$type = $_GET['type'] ?? 'open';
$pdo = bunker_pdo();
if ($type === 'open') {
    $st = $pdo->query("
        SELECT r.id, r.name, r.code, r.max_players, r.creator_id,
               (SELECT COUNT(*) FROM room_players WHERE room_id = r.id) AS player_count,
               u.nickname AS creator_name
        FROM rooms r
        JOIN users u ON u.id = r.creator_id
        WHERE r.is_public = 1 AND r.status = 'waiting'
        ORDER BY r.id DESC
        LIMIT 50
    ");
    $list = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $list[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'max_players' => (int) $row['max_players'],
            'player_count' => (int) $row['player_count'],
            'creator_name' => $row['creator_name'],
        ];
    }
    bunker_ok($list);
}
if ($type === 'my') {
    $st = $pdo->prepare("
        SELECT r.id, r.name, r.code, r.status, r.catastrophe_id,
               (SELECT COUNT(*) FROM room_players WHERE room_id = r.id) AS player_count,
               c.type AS catastrophe_type
        FROM room_players rp
        JOIN rooms r ON r.id = rp.room_id
        LEFT JOIN catastrophes c ON c.id = r.catastrophe_id
        WHERE rp.user_id = ? AND r.status != 'finished'
        ORDER BY rp.joined_at DESC
    ");
    $st->execute([$uid]);
    $list = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $list[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'status' => $row['status'],
            'player_count' => (int) $row['player_count'],
            'catastrophe_type' => $row['catastrophe_type'],
        ];
    }
    bunker_ok($list);
}
bunker_fail('Неверный type');