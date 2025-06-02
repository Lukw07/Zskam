-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Počítač: 127.0.0.1
-- Vytvořeno: Pon 02. čen 2025, 14:36
-- Verze serveru: 10.4.32-MariaDB
-- Verze PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `zskamenicka_rezv`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `total_quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `devices`
--

INSERT INTO `devices` (`id`, `device_name`, `total_quantity`) VALUES
(1, 'Tablety', 16),
(2, 'Notebooky', 10);

-- --------------------------------------------------------

--
-- Struktura tabulky `hours`
--

CREATE TABLE `hours` (
  `hour_number` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `hours`
--

INSERT INTO `hours` (`hour_number`, `start_time`, `end_time`) VALUES
(1, '07:55:00', '08:40:00'),
(2, '08:50:00', '09:35:00'),
(3, '09:55:00', '10:40:00'),
(4, '10:50:00', '11:35:00'),
(5, '11:45:00', '12:30:00'),
(6, '12:40:00', '13:25:00'),
(7, '13:30:00', '14:15:00'),
(8, '14:20:00', '15:05:00');

-- --------------------------------------------------------

--
-- Struktura tabulky `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `hour` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `timestamp_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `device_id`, `date`, `hour`, `quantity`, `timestamp_created`) VALUES
(1, 2, 1, '2025-05-25', 3, 2, '2025-05-24 07:26:42'),
(2, 2, 2, '2025-05-23', 5, 1, '2025-05-24 07:26:42'),
(13, 2, 1, '2025-05-30', 1, 4, '2025-05-25 20:27:53'),
(14, 2, 1, '2025-05-30', 1, 1, '2025-05-25 20:27:55'),
(16, 2, 1, '2025-05-26', 1, 4, '2025-05-25 20:38:28'),
(17, 2, 1, '2025-05-26', 1, 3, '2025-05-25 20:51:11'),
(18, 2, 1, '2025-05-26', 2, 10, '2025-05-26 05:37:46'),
(20, 2, 1, '2025-05-27', 1, 4, '2025-05-26 08:00:53');

-- --------------------------------------------------------

--
-- Struktura tabulky `technical_issues`
--

CREATE TABLE `technical_issues` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `class` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `urgency` enum('nízká','střední','vysoká') NOT NULL,
  `status` enum('nový','přečteno','v řešení','vyřešeno') NOT NULL DEFAULT 'nový',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `technical_issues`
--

INSERT INTO `technical_issues` (`id`, `user_id`, `class`, `description`, `urgency`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '1c', 'test', 'nízká', 'nový', '2025-05-25 10:34:22', '2025-05-26 08:02:41'),
(2, 2, '1c', 'akak', 'vysoká', 'vyřešeno', '2025-05-25 10:53:23', '2025-05-25 13:58:27'),
(3, 2, '1c', 'akak', 'vysoká', 'vyřešeno', '2025-05-25 10:53:35', '2025-05-26 08:02:52');

-- --------------------------------------------------------

--
-- Struktura tabulky `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`) VALUES
(1, 'Admin', 'admin@skola.cz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Student', 'student@skola.cz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `hours`
--
ALTER TABLE `hours`
  ADD PRIMARY KEY (`hour_number`);

--
-- Indexy pro tabulku `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `hour` (`hour`);

--
-- Indexy pro tabulku `technical_issues`
--
ALTER TABLE `technical_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexy pro tabulku `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pro tabulku `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pro tabulku `technical_issues`
--
ALTER TABLE `technical_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pro tabulku `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`),
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`hour`) REFERENCES `hours` (`hour_number`);

--
-- Omezení pro tabulku `technical_issues`
--
ALTER TABLE `technical_issues`
  ADD CONSTRAINT `technical_issues_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
