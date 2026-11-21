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
$name = trim((string) ($body['name'] ?? 'Бункер'));
if ($name === '') $name = 'Бункер';
$maxPlayers = (int) ($body['max_players'] ?? 8);
$maxPlayers = max(4, min(16, $maxPlayers));
$bunkerSlots = (int) ($body['bunker_slots'] ?? 4);
$bunkerSlots = max(2, min($maxPlayers - 1, $bunkerSlots));
$discussTime = (int) ($body['discuss_time'] ?? 300);
$discussTime = max(30, min(600, $discussTime));
$voteTime = (int) ($body['vote_time'] ?? 60);
$voteTime = max(15, min(300, $voteTime));
$cardsPerRound = $body['cards_per_round'] ?? [2, 1, 1, 1];
if (is_string($cardsPerRound)) $cardsPerRound = json_decode($cardsPerRound, true);
if (!is_array($cardsPerRound)) $cardsPerRound = [2, 1, 1, 1];
$superpowersOn = !empty($body['superpowers_on']);
$isPublic = empty($body['is_public']) ? false : (bool) $body['is_public'];
$password = isset($body['password']) ? trim((string) $body['password']) : null;
$passwordHash = ($password !== null && $password !== '') ? password_hash($password, PASSWORD_DEFAULT) : null;
$pdo = bunker_pdo();
$code = '';
for ($i = 0; $i < 20; $i++) {
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $st = $pdo->prepare('SELECT id FROM rooms WHERE code = ?');
    $st->execute([$code]);
    if (!$st->fetch()) break;
}
$st = $pdo->prepare('INSERT INTO rooms (creator_id, name, code, password_hash, is_public, max_players, bunker_slots, superpowers_on, discuss_time, vote_time, cards_per_round) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$st->execute([$uid, $name, $code, $passwordHash, $isPublic ? 1 : 0, $maxPlayers, $bunkerSlots, $superpowersOn ? 1 : 0, $discussTime, $voteTime, json_encode($cardsPerRound)]);
$roomId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO room_players (room_id, user_id, is_creator) VALUES (?, ?, 1)')->execute([$roomId, $uid]);
bunker_ok(['id' => $roomId, 'code' => $code]);