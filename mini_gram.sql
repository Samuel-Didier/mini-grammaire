-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 23, 2026 at 02:12 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.26

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
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` text NOT NULL,
  `telephone` bigint NOT NULL,
  `create_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `username` varchar(100) NOT NULL,
  `image` varchar(1000) DEFAULT NULL,
  `verified` int NOT NULL DEFAULT '0',
  `google_id` varchar(255) DEFAULT NULL,
  `bio` text,
  `role` enum('locataire','proprietaire','admin') NOT NULL DEFAULT 'locataire'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `password`, `email`, `telephone`, `create_at`, `username`, `image`, `verified`, `google_id`, `bio`, `role`) VALUES
(1, 'test', 'test', '$2y$10$Dy.ceS8eLuDjX6Yv1qTipexGNEN.zIZbK8FGV9I5nrgsfXViayCPa', 'test@test', 4184807031, '2025-11-25 14:52:27', 'test', NULL, 1, NULL, 'test', 'admin'),
(15, 'LAWSON HETCHELY TEYI DIDIER SAMUEL', 'LAWSON', '$2y$10$GbL1.BtE3nFdbynyC7uwEeMnaMk.RoWImXFnUnzmNpxu2rYLjk6VW', 'lawsonsamuel9196@gmail.com', 4184807031, '2026-01-07 11:52:20', 'SAMUEL', NULL, 0, NULL, NULL, 'locataire'),
(16, 'Dupont', 'Jean', '$2y$10$vvzvj2qY7osneTJWPr8AIuxu.ZZwg4Yt4gOJqeH/GcScK/cvH1qv.', 'jean.dupont@email.com', 4184807031, '2026-02-23 08:53:54', 'JD', NULL, 0, NULL, NULL, 'locataire');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
