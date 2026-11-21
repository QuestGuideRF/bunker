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
$code = strtoupper(trim((string) ($body['code'] ?? '')));
$password = isset($body['password']) ? (string) $body['password'] : '';
if ($code === '') bunker_fail('Введите код комнаты');
$pdo = bunker_pdo();
$st = $pdo->prepare('SELECT id, password_hash, max_players, status FROM rooms WHERE code = ?');
$st->execute([$code]);
$room = $st->fetch(PDO::FETCH_ASSOC);
if (!$room) bunker_fail('Комната не найдена');
if ($room['status'] !== 'waiting') bunker_fail('Игра уже идёт');
$st = $pdo->prepare('SELECT 1 FROM room_players WHERE room_id = ? AND user_id = ?');
$st->execute([$room['id'], $uid]);
if ($st->fetch()) {
    bunker_ok(['id' => (int) $room['id']]);
}
$st = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
$st->execute([$room['id']]);
$cnt = (int) $st->fetchColumn();
if ($cnt >= (int) $room['max_players']) bunker_fail('Комната заполнена');
if ($room['password_hash'] !== null && $room['password_hash'] !== '') {
    if (!password_verify($password, $room['password_hash'])) bunker_fail('Неверный пароль');
}
$pdo->prepare('INSERT INTO room_players (room_id, user_id, is_creator) VALUES (?, ?, 0)')->execute([$room['id'], $uid]);
bunker_ok(['id' => (int) $room['id']]);