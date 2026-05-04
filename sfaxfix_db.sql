-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 03, 2026 at 01:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sfaxfix_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `avis`
--

CREATE TABLE `avis` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `prestataire_id` int(11) NOT NULL,
  `note` tinyint(4) DEFAULT 5,
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `avis`
--

INSERT INTO `avis` (`id`, `utilisateur_id`, `prestataire_id`, `note`, `commentaire`, `created_at`) VALUES
(1, 16, 3, 4, 'Excellent service ! L’informaticienne a rapidement identifié le problème de mon ordinateur et l’a réparé le jour même. Très professionnelle, ponctuelle et à l’écoute. Mon PC fonctionne maintenant parfaitement. Je recommande vivement !', '2026-05-03 11:23:25');

-- --------------------------------------------------------

--
-- Table structure for table `disponibilites`
--

CREATE TABLE `disponibilites` (
  `id` int(11) NOT NULL,
  `prestataire_id` int(11) NOT NULL,
  `jour_semaine` tinyint(4) NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disponibilites`
--

INSERT INTO `disponibilites` (`id`, `prestataire_id`, `jour_semaine`, `heure_debut`, `heure_fin`) VALUES
(1, 2, 1, '08:00:00', '18:00:00'),
(2, 2, 2, '08:00:00', '18:00:00'),
(3, 2, 3, '08:00:00', '18:00:00'),
(4, 2, 4, '08:00:00', '18:00:00'),
(5, 2, 6, '08:00:00', '18:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `prestataires`
--

CREATE TABLE `prestataires` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prestataires`
--

INSERT INTO `prestataires` (`id`, `utilisateur_id`, `service_id`, `description`, `prix`, `created_at`) VALUES
(1, 1, 4, 'Femme de ménage expérimentée à Sfax, sérieuse et ponctuelle.\r\nJe propose des services de nettoyage pour maisons, appartements et bureaux : ménage complet, lavage des sols, nettoyage cuisine et salle de bain, rangement et repassage.\r\nTravail propre, rapide et avec attention aux détails.', 35.00, '2026-05-02 21:32:47'),
(2, 2, 2, 'Électricien professionnel à Sfax avec plusieurs années d’expérience.\r\nInstallation électrique, réparation de pannes, maintenance, éclairage, prises et tableaux électriques.\r\nIntervention rapide et travail soigné.', 60.00, '2026-05-02 21:39:24'),
(3, 5, 9, 'Technicien informatique spécialisé en dépannage PC, installation Windows, nettoyage de virus et configuration réseaux. Disponible à domicile à Sfax.', 45.00, '2026-05-02 22:28:51'),
(4, 4, 1, 'Plombier professionnel à Sfax. Réparation de fuites, installation sanitaire, débouchage et entretien des canalisations. Intervention rapide et travail propre.', 50.00, '2026-05-02 22:29:39'),
(5, 6, 5, 'Jardinier expérimenté pour entretien des jardins, arrosage, taille des arbres et nettoyage des espaces verts. Travail sérieux et ponctuel.', 35.00, '2026-05-02 22:30:38'),
(6, 7, 3, 'Peintre professionnel pour maisons, appartements et bureaux. Travaux de peinture intérieure et extérieure avec finition propre et moderne.', 55.00, '2026-05-02 22:31:31'),
(7, 8, 8, 'Menuisier spécialisé en fabrication et réparation de meubles, portes et cuisines en bois. Travail précis et matériaux de qualité.', 70.00, '2026-05-02 22:32:26'),
(8, 9, 6, 'Technicien climatisation pour installation, entretien et réparation des climatiseurs. Intervention rapide dans toute la région de Sfax.', 65.00, '2026-05-02 22:33:12'),
(9, 10, 10, 'Service de déménagement rapide et sécurisé à Sfax. Transport de meubles, électroménager et cartons avec équipe professionnelle.', 80.00, '2026-05-02 22:34:01'),
(10, 11, 4, 'Femme de ménage expérimentée à Sfax, sérieuse et ponctuelle. Nettoyage de maisons, appartements et bureaux, avec repassage et rangement. Travail rapide et soigné.', 35.00, '2026-05-02 22:35:21'),
(11, 12, 2, 'Technicien électricien qualifié pour installation et réparation électrique à domicile. Intervention rapide, diagnostic des pannes et maintenance des systèmes électriques.', 55.00, '2026-05-02 22:36:42'),
(12, 13, 7, 'Maçon professionnel spécialisé dans les travaux de construction et rénovation. Réalisation de murs, carrelage, béton, terrasse et petits travaux de bâtiment avec finition propre.', 75.00, '2026-05-02 22:39:19'),
(13, 17, 9, 'Informaticienne spécialisée en maintenance informatique, installation de logiciels, dépannage PC et assistance technique. Service rapide, fiable et adapté aux particuliers et entreprises.', 90.00, '2026-05-03 11:17:01');

-- --------------------------------------------------------

--
-- Table structure for table `rendezvous`
--

CREATE TABLE `rendezvous` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `prestataire_id` int(11) NOT NULL,
  `date_rdv` date NOT NULL,
  `heure_rdv` time NOT NULL,
  `statut` enum('en_attente','accepte','refuse') DEFAULT 'en_attente',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rendezvous`
--

INSERT INTO `rendezvous` (`id`, `utilisateur_id`, `prestataire_id`, `date_rdv`, `heure_rdv`, `statut`, `note`, `created_at`) VALUES
(2, 1, 2, '2026-05-04', '12:30:00', 'accepte', NULL, '2026-05-02 21:43:48'),
(3, 16, 3, '2026-05-07', '08:30:00', 'accepte', 'Mon ordinateur portable devient très lent et affiche parfois un écran bleu au démarrage. J’ai aussi des problèmes avec la connexion Wi-Fi et certains logiciels ne s’ouvrent plus correctement. J’aimerais un diagnostic complet et une réparation si possible.', '2026-05-03 11:19:25'),
(4, 16, 13, '2026-05-07', '08:30:00', 'refuse', 'Mon ordinateur portable devient très lent et affiche parfois un écran bleu au démarrage. J’ai aussi des problèmes avec la connexion Wi-Fi et certains logiciels ne s’ouvrent plus correctement. J’aimerais un diagnostic complet et une réparation si possible.', '2026-05-03 11:21:28');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `nom`) VALUES
(1, 'Plomberie'),
(2, 'Électricité'),
(3, 'Peinture'),
(4, 'Ménage'),
(5, 'Jardinage'),
(6, 'Climatisation'),
(7, 'Maçonnerie'),
(8, 'Menuiserie'),
(9, 'Informatique'),
(10, 'Déménagement');

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `photo_profil` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `email`, `mot_de_passe`, `role`, `photo_profil`, `created_at`) VALUES
(1, 'Emilia Luix', 'emilialuix@gmail.com', '$2y$10$jihzbx6CzBeuBDaHw900iuSJoPMyPCg7yn0x1MU/wP5h77dU8iKdC', 'user', NULL, '2026-05-02 21:28:07'),
(2, 'Ahmed Ben Salah', 'ahmed.elec@gmail.com', '$2y$10$gR8bko88nq/.wYphCQzpoOo9H42gLMx464I0rdt/jJb9IdD9Lp9oq', 'user', 'user_2_69f689379ef06.png', '2026-05-02 21:38:26'),
(4, 'Sami Chahed', 'sami.plombier@gmail.com', '$2y$10$r4Um1Ptj6K0x8sezcdK9Du/720nY8nzIQIbtUMicMKL9WfgHPs6C6', 'user', 'user_4_69f68e0807d58.png', '2026-05-02 22:26:53'),
(5, 'Youssef Triki', 'youssef.tech@gmail.com', '$2y$10$5.l1CKLDpvbXBK5hE3Tbm.3VoqnMZORSpMC96UgvI6C7BYfReMRou', 'user', 'user_5_69f68d27a9cb8.png', '2026-05-02 22:27:30'),
(6, 'Karim Ayadi', 'karim.jardin@gmail.com', '$2y$10$Invatzizk41Sa1q8F7On1eSVRUe21OsM3Z9yWQ8GSXtQ1kKHWiJ.q', 'user', 'user_6_69f68db1cdedb.png', '2026-05-02 22:30:19'),
(7, 'Mehdi Kammoun', 'mehdi.paint@gmail.com', '$2y$10$hRxL2cIPYLfGY1FToaTCLOFTP301Tr2Z8Dap0Aoki8.DruINn5Fha', 'user', 'user_7_69f68cb89f11e.png', '2026-05-02 22:31:09'),
(8, 'Fares Mnif', 'fares.menuisier@gmail.com', '$2y$10$ms7mNTvdgMvmunIvv3kfOOYL3dS3vFxI2PYBnxiY7UmRSBQVmROQ.', 'user', 'user_8_69f68b6465bff.png', '2026-05-02 22:32:08'),
(9, 'Aziz Ben Amor', 'aziz.clima@gmail.com', '$2y$10$tJ4wOHViLSBUrC8VEEgVrOV.uB9Zu8G8ag8DzQoaNmXUaWMdTKPQO', 'user', 'user_9_69f68997af56d.png', '2026-05-02 22:32:52'),
(10, 'Hamza Jallouli', 'hamza.move@gmail.com', '$2y$10$WWQ1tEaRi54lxHEp8bShWOp9mAnSH2rf3xyQLwkerVCQSCU8pt5l2', 'user', 'user_10_69f68c0ac8fc7.png', '2026-05-02 22:33:39'),
(11, 'Hiba Kallel', 'hiba.clean@gmail.com', '$2y$10$7J8jo7gU2oBYYZVDy4zKnOuk49eF.6eMmWYd3zaqX8I8tLskxqFFm', 'user', 'user_11_69f68c753203d.png', '2026-05-02 22:34:55'),
(12, 'Walid Gharbi', 'walid.electric@gmail.com', '$2y$10$tkLD5ckrT5f.7vBftTVnFOPyOmEd5oGkd2VoOoUyJQndlh7FOaz.S', 'user', NULL, '2026-05-02 22:36:21'),
(13, 'Mohamed Ben Ali', 'mohamed.macon@gmail.com', '$2y$10$MdErhySBVqccYjqc7FS1/OgD2ffHqhCqePL5i20/ha1Vab8BIFGJ.', 'user', NULL, '2026-05-02 22:38:55'),
(15, 'Admin🎀', 'anonymoususer@gmail.com', '$2y$10$gvh6bQYGQlSVFTwIlFLs2eK4rZizXabiqsT8KS8BS3AWhHo2dsl0C', 'admin', 'user_15_69f6870787496.jpeg', '2026-05-02 22:47:48'),
(16, 'Salim Ouni', 'salimouni@gmail.com', '$2y$10$2wya1Jy0iUiGNioarQrk0eOyLBhr.io46j7awKcGMYcaTI8ymmLMq', 'user', NULL, '2026-05-03 11:14:08'),
(17, 'Rana Chairi', 'ranachairi@gmail.com', '$2y$10$7x4tOQeEAka8x..0I0Ct/.uFZFt3ypbHKF1FRcc3zJ4lwOy47wP22', 'user', NULL, '2026-05-03 11:15:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `prestataire_id` (`prestataire_id`);

--
-- Indexes for table `disponibilites`
--
ALTER TABLE `disponibilites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prestataire_id` (`prestataire_id`);

--
-- Indexes for table `prestataires`
--
ALTER TABLE `prestataires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `rendezvous`
--
ALTER TABLE `rendezvous`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `prestataire_id` (`prestataire_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `avis`
--
ALTER TABLE `avis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disponibilites`
--
ALTER TABLE `disponibilites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `prestataires`
--
ALTER TABLE `prestataires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `rendezvous`
--
ALTER TABLE `rendezvous`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`prestataire_id`) REFERENCES `prestataires` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `disponibilites`
--
ALTER TABLE `disponibilites`
  ADD CONSTRAINT `disponibilites_ibfk_1` FOREIGN KEY (`prestataire_id`) REFERENCES `prestataires` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prestataires`
--
ALTER TABLE `prestataires`
  ADD CONSTRAINT `prestataires_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prestataires_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rendezvous`
--
ALTER TABLE `rendezvous`
  ADD CONSTRAINT `rendezvous_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rendezvous_ibfk_2` FOREIGN KEY (`prestataire_id`) REFERENCES `prestataires` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
