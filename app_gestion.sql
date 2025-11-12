-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 11 nov. 2025 à 22:47
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `app_gestion`
--

-- --------------------------------------------------------

--
-- Structure de la table `activites`
--

CREATE TABLE `activites` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `lieu` varchar(255) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `date_activite` datetime NOT NULL,
  `duree` int(11) NOT NULL,
  `participants_max` int(11) NOT NULL,
  `prix` decimal(10,2) DEFAULT 0.00,
  `organisateur_id` int(11) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `statut` enum('active','inactive','annulee') DEFAULT 'active',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `activites`
--

INSERT INTO `activites` (`id`, `titre`, `description`, `categorie_id`, `lieu`, `ville`, `date_activite`, `duree`, `participants_max`, `prix`, `organisateur_id`, `image_url`, `statut`, `date_creation`) VALUES
(1, 'Randonnée dans l\'Atlas', 'Découverte des paysages magnifiques de l\'Atlas marocain avec un guide local expérimenté. Parcours adapté à tous les niveaux.', 1, 'Vallée d\'Ourika', 'Marrakech', '2025-11-18 09:15:01', 240, 15, 150.00, 2, NULL, 'active', '2025-11-11 09:15:01'),
(2, 'Atelier de cuisine marocaine', 'Apprenez à préparer un tajine authentique et des pâtisseries marocaines traditionnelles avec une chef locale.', 2, 'Dar Chef Fatima', 'Fès', '2025-11-14 09:15:01', 180, 8, 200.00, 3, NULL, 'active', '2025-11-11 09:15:01'),
(3, 'Concert de musique andalouse', 'Soirée musicale exceptionnelle avec le groupe Al-Andalus. Découverte de la riche tradition musicale marocaine.', 3, 'Théâtre Royal', 'Rabat', '2025-11-21 09:15:01', 120, 50, 80.00, 2, NULL, 'active', '2025-11-11 09:15:01'),
(4, 'Initiation à la poterie', 'Atelier créatif pour apprendre les techniques traditionnelles de poterie marocaine avec un artisan local.', 4, 'Atelier d\'Art Tamesna', 'Tamesna', '2025-11-16 09:15:01', 150, 12, 100.00, 1, NULL, 'active', '2025-11-11 09:15:01'),
(5, 'Match de football amical', 'Tournoi amical de football ouvert à tous les niveaux. Rencontres et échanges sportifs dans la bonne humeur.', 5, 'Stade Municipal', 'Casablanca', '2025-11-13 09:15:01', 120, 20, 0.00, 1, NULL, 'active', '2025-11-11 09:15:01');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `icone` varchar(10) NOT NULL,
  `couleur` varchar(7) NOT NULL,
  `statut` enum('active','inactive') DEFAULT 'active',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `icone`, `couleur`, `statut`, `date_creation`) VALUES
(1, 'Randonnée', '🥾', '#10B981', 'active', '2025-11-11 09:15:01'),
(2, 'Cuisine', '👨‍🍳', '#EF4444', 'active', '2025-11-11 09:15:01'),
(3, 'Musique', '🎵', '#3B82F6', 'active', '2025-11-11 09:15:01'),
(4, 'Art', '🎨', '#8B5CF6', 'active', '2025-11-11 09:15:01'),
(5, 'Sport', '⚽', '#F59E0B', 'active', '2025-11-11 09:15:01'),
(6, 'Culture', '🏛️', '#6366F1', 'active', '2025-11-11 09:15:01'),
(7, 'Bien-être', '🧘', '#EC4899', 'active', '2025-11-11 09:15:01'),
(8, 'Aventure', '🧗', '#F97316', 'active', '2025-11-11 09:15:01');

-- --------------------------------------------------------

--
-- Structure de la table `commentaires`
--

CREATE TABLE `commentaires` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `activite_id` int(11) NOT NULL,
  `commentaire` text NOT NULL,
  `note` int(11) DEFAULT NULL CHECK (`note` >= 1 and `note` <= 5),
  `statut` enum('en_attente','approuve','rejete') DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `commentaires`
--

INSERT INTO `commentaires` (`id`, `utilisateur_id`, `activite_id`, `commentaire`, `note`, `statut`, `date_creation`) VALUES
(1, 4, 1, 'Superbe randonnée ! Le guide était très compétent et les paysages à couper le souffle. Je recommande vivement !', 5, 'approuve', '2025-11-11 09:15:01'),
(2, 5, 1, 'Expérience incroyable, organisation parfaite. Un peu difficile pour les débutants mais ça vaut le coup !', 4, 'approuve', '2025-11-11 09:15:01'),
(3, 4, 2, 'Fatima est une excellente enseignante. J\'ai appris à faire un tajine délicieux. Merci pour cette expérience culinaire !', 5, 'approuve', '2025-11-11 09:15:01');

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

CREATE TABLE `favoris` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `activite_id` int(11) NOT NULL,
  `date_ajout` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `favoris`
--

INSERT INTO `favoris` (`id`, `utilisateur_id`, `activite_id`, `date_ajout`) VALUES
(1, 4, 3, '2025-11-11 09:15:01'),
(2, 4, 5, '2025-11-11 09:15:01'),
(3, 5, 2, '2025-11-11 09:15:01'),
(4, 5, 4, '2025-11-11 09:15:01'),
(5, 1, 5, '2025-11-11 21:23:38');

-- --------------------------------------------------------

--
-- Structure de la table `participations`
--

CREATE TABLE `participations` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `activite_id` int(11) NOT NULL,
  `date_inscription` datetime DEFAULT current_timestamp(),
  `statut` enum('confirme','annule') DEFAULT 'confirme'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `participations`
--

INSERT INTO `participations` (`id`, `utilisateur_id`, `activite_id`, `date_inscription`, `statut`) VALUES
(1, 4, 1, '2025-11-11 09:15:01', 'confirme'),
(2, 4, 2, '2025-11-11 09:15:01', 'confirme'),
(3, 5, 1, '2025-11-11 09:15:01', 'confirme'),
(4, 5, 3, '2025-11-11 09:15:01', 'confirme'),
(5, 5, 4, '2025-11-11 09:15:01', 'confirme'),
(6, 1, 5, '2025-11-11 21:23:31', 'confirme');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `ville` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `est_organisateur` tinyint(1) DEFAULT 0,
  `est_admin` tinyint(1) DEFAULT 0,
  `date_inscription` datetime DEFAULT current_timestamp(),
  `date_derniere_connexion` datetime DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `prenom`, `nom`, `email`, `telephone`, `ville`, `mot_de_passe`, `est_organisateur`, `est_admin`, `date_inscription`, `date_derniere_connexion`, `statut`) VALUES
(1, 'HANANE', 'HARROU', 'admin@gmail.ma', NULL, 'Casablanca', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-11-11 09:15:01', '2025-11-11 21:20:00', 'actif'),
(2, 'Mehdi', 'Benjelloun', 'mehdi@gmail.com', '+212612345678', 'Casablanca', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0, '2025-11-11 09:15:01', '2025-11-11 09:24:49', 'actif'),
(3, 'Fatima', 'Alaoui', 'fatima@gmail.com', '+212612345679', 'Marrakech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0, '2025-11-11 09:15:01', '2025-11-11 21:30:56', 'actif'),
(4, 'Karim', 'Mansouri', 'karim@gmail.com', '+212612345680', 'Rabat', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 0, '2025-11-11 09:15:01', '2025-11-11 10:32:55', 'actif'),
(5, 'Amina', 'Berrada', 'amina@gmail.com', '+212612345681', 'Fès', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 0, '2025-11-11 09:15:01', NULL, 'actif');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activites`
--
ALTER TABLE `activites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activites_date` (`date_activite`),
  ADD KEY `idx_activites_ville` (`ville`),
  ADD KEY `idx_activites_categorie` (`categorie_id`),
  ADD KEY `idx_activites_organisateur` (`organisateur_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Index pour la table `commentaires`
--
ALTER TABLE `commentaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_commentaire` (`utilisateur_id`,`activite_id`),
  ADD KEY `idx_commentaires_activite` (`activite_id`),
  ADD KEY `idx_commentaires_utilisateur` (`utilisateur_id`);

--
-- Index pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favori` (`utilisateur_id`,`activite_id`),
  ADD KEY `activite_id` (`activite_id`);

--
-- Index pour la table `participations`
--
ALTER TABLE `participations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participation` (`utilisateur_id`,`activite_id`),
  ADD KEY `idx_participations_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_participations_activite` (`activite_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activites`
--
ALTER TABLE `activites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `commentaires`
--
ALTER TABLE `commentaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `favoris`
--
ALTER TABLE `favoris`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `participations`
--
ALTER TABLE `participations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activites`
--
ALTER TABLE `activites`
  ADD CONSTRAINT `activites_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `activites_ibfk_2` FOREIGN KEY (`organisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `commentaires`
--
ALTER TABLE `commentaires`
  ADD CONSTRAINT `commentaires_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commentaires_ibfk_2` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD CONSTRAINT `favoris_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoris_ibfk_2` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `participations`
--
ALTER TABLE `participations`
  ADD CONSTRAINT `participations_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `participations_ibfk_2` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
