-- Gestion des quiz depuis le tableau de bord (creation / modification / suppression).
-- Une question = une ligne dans quiz_questions, ses propositions dans
-- quiz_propositions. Le nombre de propositions est libre (2 a 6) : il est choisi
-- au moment de la creation de la question, il n'est donc pas fige dans le schema.
--
-- `categorie` est un slug technique (utilise dans l'URL et le filtre) alors que
-- `libelle_categorie` est le libelle affiche dans le menu et le tableau de bord.
-- Un quiz = le couple (categorie, niveau) : 'niveau'/'tous' correspond au test de
-- niveau, dont les questions melangent les niveaux CECRL (colonne `difficulte`,
-- seule colonne utilisee par l'algorithme de determination du niveau).
--
-- La suppression d'une question supprime ses propositions (ON DELETE CASCADE).

CREATE TABLE `quiz_questions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `categorie` varchar(60) NOT NULL,
  `libelle_categorie` varchar(100) NOT NULL,
  `niveau` varchar(5) NOT NULL DEFAULT 'tous',
  `difficulte` varchar(5) NOT NULL DEFAULT 'a1',
  `question` text NOT NULL,
  `explication` varchar(500) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_questions_quiz` (`categorie`,`niveau`),
  KEY `idx_quiz_questions_categorie` (`categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `quiz_propositions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` bigint UNSIGNED NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `est_correcte` tinyint(1) NOT NULL DEFAULT 0,
  `ordre` tinyint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_propositions_question` (`question_id`),
  CONSTRAINT `fk_quiz_propositions_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
