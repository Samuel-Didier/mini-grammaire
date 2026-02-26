CREATE TABLE `progression` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `niveau_global` varchar(10) DEFAULT NULL,
  `score_test_initial` int DEFAULT NULL,
  `date_test` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_progression_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;