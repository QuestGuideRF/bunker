SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
;
;
;
;
CREATE TABLE `card_reveals` (
  `id` int UNSIGNED NOT NULL,
  `game_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `round_num` tinyint UNSIGNED NOT NULL,
  `attribute_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'profession|health|phobia|bio|extra|baggage|superpower|special_condition',
  `attribute_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `revealed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `catastrophes` (
  `id` int UNSIGNED NOT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destruction_level` tinyint UNSIGNED NOT NULL COMMENT '1-10',
  `survivors_percent` tinyint UNSIGNED NOT NULL COMMENT 'percent of humanity',
  `bunker_duration` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. "2 года"'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `catastrophes` (`id`, `type`, `description`, `destruction_level`, `survivors_percent`, `bunker_duration`) VALUES
(1, 'Ядерная война', 'Глобальный ядерный конфликт уничтожил 90% населения. Радиация сохранится десятилетия.', 10, 3, '50 лет'),
(2, 'Пандемия вируса', 'Смертоносный вирус с передачей воздушно-капельным путём. Летальность — 97%.', 9, 5, '10 лет'),
(3, 'Зомби-апокалипсис', 'Неизвестный патоген превращает людей в агрессивных зомби. Инфраструктура рухнула.', 8, 8, '20 лет'),
(4, 'Восстание ИИ', 'Сверхинтеллект вышел из-под контроля. Дроны и роботы уничтожают людей.', 9, 7, '15 лет'),
(5, 'Климатическая катастрофа', 'Глобальное потепление +8°C. Океаны поглотили берега, засухи и голод.', 7, 15, '25 лет'),
(6, 'Падение астероида', 'Астероид диаметром 3 км уничтожил полушарие. Ядерная зима неизбежна.', 10, 2, '75 лет'),
(7, 'Вулканическая зима', 'Супервулкан Йеллоустоун извергся. Облако пепла закрыло солнце.', 8, 10, '30 лет'),
(8, 'Биологическое оружие', 'Самовоспроизводящийся вирус-оружие вырвался из лаборатории. Антидота нет.', 9, 4, '12 лет'),
(9, 'Солнечная вспышка', 'Суперфлэр уничтожил всю электронику на Земле. Цивилизация отброшена в каменный век.', 6, 20, '5 лет'),
(10, 'Нашествие инопланетян', 'Агрессивная инопланетная раса начала терраформирование Земли для себя.', 10, 5, '100 лет'),
(11, 'Нанороботы', 'Самореплицирующиеся нанороботы пожирают органику. «Серая слизь» наступает.', 9, 6, '40 лет'),
(12, 'Магнитный апокалипсис', 'Смена магнитных полюсов разрушила атмосферу. Радиация убивает всё живое.', 8, 8, '35 лет');
CREATE TABLE `games` (
  `id` int UNSIGNED NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `catastrophe_id` int UNSIGNED NOT NULL,
  `phase` enum('cards','revealing','discussing','voting','finished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'cards',
  `round_num` tinyint UNSIGNED DEFAULT '1',
  `phase_deadline` datetime DEFAULT NULL COMMENT 'UTC, server timer',
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` timestamp NULL DEFAULT NULL,
  `tie_candidates` json DEFAULT NULL COMMENT 'При равенстве голосов: список user_id для переголосования'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `game_log` (
  `id` int UNSIGNED NOT NULL,
  `game_id` int UNSIGNED NOT NULL,
  `round_num` tinyint UNSIGNED DEFAULT NULL,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `game_players` (
  `id` int UNSIGNED NOT NULL,
  `game_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `card_id` int UNSIGNED NOT NULL,
  `is_ready` tinyint(1) DEFAULT '0',
  `is_eliminated` tinyint(1) DEFAULT '0',
  `eliminated_round` tinyint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `player_cards` (
  `id` int UNSIGNED NOT NULL,
  `profession` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `health` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phobia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` json NOT NULL COMMENT '{"age":34,"gender":"мужской","fertility":"фертилен"}',
  `extra` json NOT NULL COMMENT '{"skills":"...","traits":"...","hobby":"...","experience":"..."}',
  `baggage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `superpower` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `special_condition` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `rooms` (
  `id` int UNSIGNED NOT NULL,
  `creator_id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Бункер',
  `code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT '1',
  `max_players` tinyint UNSIGNED DEFAULT '8',
  `bunker_slots` tinyint UNSIGNED DEFAULT '4',
  `superpowers_on` tinyint(1) DEFAULT '1',
  `discuss_time` smallint UNSIGNED DEFAULT '300' COMMENT 'seconds',
  `vote_time` smallint UNSIGNED DEFAULT '60' COMMENT 'seconds',
  `cards_per_round` json DEFAULT NULL COMMENT 'e.g. [2,1,1,1]',
  `status` enum('waiting','playing','finished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'waiting',
  `catastrophe_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `room_players` (
  `room_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `is_creator` tinyint(1) DEFAULT '0',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `nickname` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme` enum('dark','light') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'dark',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_stats` (
  `user_id` int UNSIGNED NOT NULL,
  `games_played` int UNSIGNED DEFAULT '0',
  `wins` int UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `votes` (
  `id` int UNSIGNED NOT NULL,
  `game_id` int UNSIGNED NOT NULL,
  `round_num` tinyint UNSIGNED NOT NULL,
  `voter_id` int UNSIGNED NOT NULL,
  `target_id` int UNSIGNED NOT NULL,
  `voted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `card_reveals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_game_round` (`game_id`,`round_num`),
  ADD KEY `idx_game_user` (`game_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `catastrophes`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `catastrophe_id` (`catastrophe_id`);
ALTER TABLE `game_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_game_log` (`game_id`);
ALTER TABLE `game_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_game_user` (`game_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `card_id` (`card_id`);
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_msgs` (`room_id`,`created_at`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `player_cards`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_code` (`code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_public_status` (`is_public`,`status`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `catastrophe_id` (`catastrophe_id`);
ALTER TABLE `room_players`
  ADD PRIMARY KEY (`room_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nickname` (`nickname`),
  ADD KEY `idx_nickname` (`nickname`);
ALTER TABLE `user_stats`
  ADD PRIMARY KEY (`user_id`);
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vote` (`game_id`,`round_num`,`voter_id`),
  ADD KEY `idx_game_round_votes` (`game_id`,`round_num`),
  ADD KEY `voter_id` (`voter_id`),
  ADD KEY `target_id` (`target_id`);
ALTER TABLE `card_reveals`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `catastrophes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
ALTER TABLE `games`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `game_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `game_players`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `player_cards`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `rooms`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `votes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_reveals`
  ADD CONSTRAINT `card_reveals_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `card_reveals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `games_ibfk_2` FOREIGN KEY (`catastrophe_id`) REFERENCES `catastrophes` (`id`);
ALTER TABLE `game_log`
  ADD CONSTRAINT `game_log_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE;
ALTER TABLE `game_players`
  ADD CONSTRAINT `game_players_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_players_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_players_ibfk_3` FOREIGN KEY (`card_id`) REFERENCES `player_cards` (`id`);
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rooms_ibfk_2` FOREIGN KEY (`catastrophe_id`) REFERENCES `catastrophes` (`id`) ON DELETE SET NULL;
ALTER TABLE `room_players`
  ADD CONSTRAINT `room_players_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_players_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_stats`
  ADD CONSTRAINT `user_stats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`voter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`target_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
;
;
;