-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 25, 2026 at 06:36 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mini_gram`
--

-- --------------------------------------------------------

--
-- Table structure for table `astuces`
--

CREATE TABLE `astuces` (
  `astuces` bigint UNSIGNED NOT NULL,
  `titre` varchar(500) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `astuces`
--

INSERT INTO `astuces` (`astuces`, `titre`, `description`) VALUES
(1, 'Distinction a / à', '\'a\''),
(2, 'Orthographe : Cauchemar', 'On écrit \'cauchemar\' sans \'d\' à la fin, même si on dit \'cauchemardesque\'.'),
(3, 'Parmi vs Hormis', '\'Parmi\' ne prend jamais de \'s\', contrairement à \'hormis\'.'),
(4, 'Mourir vs Nourrir', 'Le verbe \'mourir\' ne prend qu\'un seul \'r\', mais \'nourrir\' en prend deux. On meurt une fois, on se nourrit plusieurs fois.'),
(5, 'Règle des \'l\' : Appeler', '\'Appeler\' prend deux \'l\' devant un \'e\' muet (j\'appelle), mais un seul sinon (nous appelons).'),
(6, 'Invariabilité de Mille', '\'Mille\' est invariable, sauf s\'il s\'agit de l\'unité de mesure (des milles marins).'),
(7, 'Quelque ou Quel que', '\'Quelque\' s\'écrit en un seul mot devant un nom (quelque temps) et en deux mots devant un verbe (quel que soit).'),
(8, 'Davantage vs D\'avantage', '\'Davantage\' (plus de) s\'écrit en un mot. \'D\'avantage\' (profit, bénéfice) s\'écrit en deux mots (ex: Je n\'y vois pas d\'avantage).'),
(9, 'Objectifs concrets', 'Fixez des objectifs mesurables — par exemple, 5 nouveaux mots par semaine (plus de 250 par an).'),
(10, 'Tableau de suivi', 'Tenez un suivi hebdomadaire : nouveaux mots, grammaire, heures d\'écoute et pratique orale.'),
(11, 'Immersion numérique', 'Passez la langue de vos appareils (téléphone, ordinateur) en français.'),
(12, 'Consommation média', 'Regardez des séries ou films sur Netflix avec des sous-titres en français.'),
(13, 'Écoute active', 'Écoutez des podcasts comme RFI Français Facile pour la compréhension.'),
(14, 'Étiquetage', 'Étiquetez les objets de votre maison avec leur nom en français.'),
(15, 'Shadowing', 'Répétez à haute voix des phrases entendues dans des podcasts ou vidéos.'),
(16, 'Phonétique spécifique', 'Concentrez-vous sur les sons : voyelles nasales (on, an, in), le \'r\' guttural et les liaisons.'),
(17, 'Auto-correction', 'Enregistrez-vous et comparez votre prononciation à celle d\'un locuteur natif.'),
(18, 'Flashcards', 'Utilisez des outils comme Anki ou Quizlet pour mémoriser les mots fréquents.'),
(19, 'Loi de Pareto (80/20)', 'Apprenez d\'abord les 2000 mots les plus courants — ils couvrent 80% de la langue.'),
(20, 'Journal de bord', 'Rédigez un journal quotidien en français, même avec des phrases très simples.'),
(21, 'Structure simple', 'Structurez vos phrases avec la formule de base : Sujet – Verbe – Complément.'),
(22, 'Vérification', 'Relisez-vous systématiquement et utilisez un conjugueur en ligne.'),
(23, 'Dictées', 'Entraînez-vous avec des dictées en ligne pour améliorer votre orthographe.'),
(24, 'Simulation IA', 'Utilisez ChatGPT pour simuler des conversations réelles en français.'),
(25, 'État d\'esprit', 'Acceptez de faire des erreurs — c\'est une étape obligatoire pour progresser.');

-- --------------------------------------------------------

--
-- Table structure for table `favoris`
--

CREATE TABLE `favoris` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `astuces_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `favoris`
--

INSERT INTO `favoris` (`id`, `user_id`, `astuces_id`) VALUES
(12, 1, 8),
(13, 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` text NOT NULL,
  `create_at` date NOT NULL,
  `username` varchar(100) NOT NULL,
  `image` varchar(1000) DEFAULT NULL,
  `verified` int NOT NULL DEFAULT '0',
  `role` varchar(50) NOT NULL DEFAULT 'etudiant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `password`, `email`, `create_at`, `username`, `image`, `verified`, `role`) VALUES
(1, 'test', 'test', '$2y$10$Dy.ceS8eLuDjX6Yv1qTipexGNEN.zIZbK8FGV9I5nrgsfXViayCPa', 'test@test', '2025-11-25', 'test', NULL, 1, 'admin'),
(15, 'LAWSON HETCHELY TEYI DIDIER SAMUEL', 'LAWSON', '$2y$10$GbL1.BtE3nFdbynyC7uwEeMnaMk.RoWImXFnUnzmNpxu2rYLjk6VW', 'lawsonsamuel9196@gmail.com', '2026-01-07', 'SAMUEL', NULL, 0, 'enseignant'),
(16, 'Dupont', 'Jean', '$2y$10$vvzvj2qY7osneTJWPr8AIuxu.ZZwg4Yt4gOJqeH/GcScK/cvH1qv.', 'jean.dupont@email.com', '2026-02-23', 'JD', NULL, 0, 'etudiant');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `astuces`
--
ALTER TABLE `astuces`
  ADD PRIMARY KEY (`astuces`),
  ADD UNIQUE KEY `astuces` (`astuces`);

--
-- Indexes for table `favoris`
--
ALTER TABLE `favoris`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `astuces_id` (`astuces_id`) USING BTREE;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `astuces`
--
ALTER TABLE `astuces`
  MODIFY `astuces` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `favoris`
--
ALTER TABLE `favoris`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
