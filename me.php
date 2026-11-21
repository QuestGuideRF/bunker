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
$st = $pdo->prepare('SELECT u.id, u.nickname, u.avatar_url, u.theme, COALESCE(s.games_played,0) AS games_played, COALESCE(s.wins,0) AS wins FROM users u LEFT JOIN user_stats s ON s.user_id = u.id WHERE u.id = ?');
$st->execute([$uid]);
$u = $st->fetch();
if (!$u) {
    http_response_code(401);
    bunker_fail('Пользователь не найден');
}
bunker_ok([
    'id' => (int) $u['id'],
    'nickname' => $u['nickname'],
    'avatar_url' => $u['avatar_url'],
    'theme' => $u['theme'] ?? 'dark',
    'games_played' => (int) $u['games_played'],
    'wins' => (int) $u['wins'],
]);