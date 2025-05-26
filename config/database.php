<?php
// config/database.php - Configuration de la base de données avec optimisations

// Informations de connexion à la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'plateforme_defis');
define('DB_USER', 'root');
define('DB_PASS', ''); // Par défaut, le mot de passe est vide sur XAMPP
define('DB_CHARSET', 'utf8mb4');

// Connexion à la base de données - Singleton pattern
function connectDB() {
    static $db = null; // Cache de connexion
    
    if ($db === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::ATTR_PERSISTENT => true, // Connexions persistantes pour optimisation
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $db;
        } catch(PDOException $e) {
            // Journaliser l'erreur plutôt que d'afficher le message
            error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
            die('Une erreur est survenue lors de la connexion à la base de données.');
        }
    }
    
    return $db;
}

// Fonction optimisée pour requêtes paramétrées avec cache simple
function executeQuery($sql, $params = [], $fetchMode = PDO::FETCH_ASSOC) {
    static $queryCache = [];
    
    // Créer une clé de cache simple pour les requêtes SELECT
    $cacheKey = md5($sql . serialize($params));
    $isSelect = stripos(trim($sql), 'SELECT') === 0;
    
    // Vérifier si la requête est en cache (pour les requêtes SELECT uniquement)
    if ($isSelect && isset($queryCache[$cacheKey])) {
        return $queryCache[$cacheKey];
    }
    
    $db = connectDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($isSelect) {
        $result = $stmt->fetchAll($fetchMode);
        // Stocker en cache si c'est une requête SELECT (limite le cache à 100 requêtes)
        if (count($queryCache) < 100) {
            $queryCache[$cacheKey] = $result;
        }
        return $result;
    } else {
        // Pour INSERT, UPDATE, DELETE - retourner le nombre de lignes affectées
        return $stmt->rowCount();
    }
}

// Fonction pour obtenir le dernier ID inséré
function getLastInsertId() {
    $db = connectDB();
    return $db->lastInsertId();
}

// Fonction pour exécuter une requête et récupérer un seul résultat
function fetchOne($sql, $params = []) {
    $db = connectDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fonction pour exécuter une requête et récupérer une seule valeur
function fetchValue($sql, $params = []) {
    $db = connectDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// Script SQL pour créer les tables si elles n'existent pas
function createTables() {
    $db = connectDB();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `utilisateurs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pseudo` varchar(50) NOT NULL UNIQUE,
        `email` varchar(100) NOT NULL UNIQUE,
        `mot_de_passe` varchar(255) NOT NULL,
        `nom` varchar(50) DEFAULT NULL,
        `prenom` varchar(50) DEFAULT NULL,
        `bio` text DEFAULT NULL,
        `photo_profil` varchar(255) DEFAULT NULL,
        `theme_preference` enum('light','dark') DEFAULT 'light',
        `date_inscription` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `derniere_connexion` timestamp NULL DEFAULT NULL,
        `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
        `token_reset` varchar(64) DEFAULT NULL,
        `token_reset_expiry` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_email` (`email`),
        KEY `idx_pseudo` (`pseudo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nom` varchar(50) NOT NULL,
        `description` text DEFAULT NULL,
        `icone` varchar(50) DEFAULT 'fa-star',
        `couleur` varchar(7) DEFAULT '#631bff',
        `ordre` int(11) DEFAULT 0,
        `actif` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `defis` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `titre` varchar(200) NOT NULL,
        `description` text NOT NULL,
        `difficulte` enum('facile','moyen','difficile','extreme') NOT NULL,
        `categorie_id` int(11) NOT NULL,
        `createur_id` int(11) NOT NULL,
        `image_presentation` varchar(255) DEFAULT NULL,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_limite` date DEFAULT NULL,
        `statut` enum('actif','termine','suspendu') DEFAULT 'actif',
        `collectif` tinyint(1) DEFAULT 0,
        `min_participants` int(11) DEFAULT 1,
        `max_participants` int(11) DEFAULT NULL,
        `recompense` varchar(255) DEFAULT NULL,
        `vues` int(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_categorie` (`categorie_id`),
        KEY `idx_createur` (`createur_id`),
        KEY `idx_difficulte` (`difficulte`),
        KEY `idx_statut` (`statut`),
        KEY `idx_date_creation` (`date_creation`),
        FOREIGN KEY (`categorie_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`createur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `participations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `utilisateur_id` int(11) NOT NULL,
        `defi_id` int(11) NOT NULL,
        `date_participation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_completion` timestamp NULL DEFAULT NULL,
        `statut` enum('en_cours','complete','abandonne') DEFAULT 'en_cours',
        `preuve_texte` text DEFAULT NULL,
        `preuve_image` varchar(255) DEFAULT NULL,
        `note_auto_evaluation` int(11) DEFAULT NULL,
        `commentaire_completion` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_participation` (`utilisateur_id`, `defi_id`),
        KEY `idx_utilisateur` (`utilisateur_id`),
        KEY `idx_defi` (`defi_id`),
        KEY `idx_statut` (`statut`),
        FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`defi_id`) REFERENCES `defis`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `commentaires` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `utilisateur_id` int(11) NOT NULL,
        `defi_id` int(11) NOT NULL,
        `participation_id` int(11) DEFAULT NULL,
        `contenu` text NOT NULL,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `modifie` tinyint(1) DEFAULT 0,
        `date_modification` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_utilisateur` (`utilisateur_id`),
        KEY `idx_defi` (`defi_id`),
        KEY `idx_participation` (`participation_id`),
        KEY `idx_date_creation` (`date_creation`),
        FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`defi_id`) REFERENCES `defis`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`participation_id`) REFERENCES `participations`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `amis` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `utilisateur_id` int(11) NOT NULL,
        `ami_id` int(11) NOT NULL,
        `statut` enum('en_attente','accepte','refuse','bloque') DEFAULT 'en_attente',
        `date_demande` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_reponse` timestamp NULL DEFAULT NULL,
        `demandeur_id` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_amitie` (`utilisateur_id`, `ami_id`),
        KEY `idx_utilisateur` (`utilisateur_id`),
        KEY `idx_ami` (`ami_id`),
        KEY `idx_statut` (`statut`),
        KEY `idx_demandeur` (`demandeur_id`),
        FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`ami_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`demandeur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `utilisateur_id` int(11) NOT NULL,
        `type` enum('nouveau_defi','participation','commentaire','amitie','completion','mention') NOT NULL,
        `titre` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `data` json DEFAULT NULL,
        `lu` tinyint(1) DEFAULT 0,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expediteur_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_utilisateur` (`utilisateur_id`),
        KEY `idx_type` (`type`),
        KEY `idx_lu` (`lu`),
        KEY `idx_date_creation` (`date_creation`),
        KEY `idx_expediteur` (`expediteur_id`),
        FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `votes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `utilisateur_id` int(11) NOT NULL,
        `participation_id` int(11) NOT NULL,
        `type_vote` enum('like','dislike','love','wow','haha') NOT NULL,
        `date_vote` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_vote` (`utilisateur_id`, `participation_id`),
        KEY `idx_participation` (`participation_id`),
        KEY `idx_type_vote` (`type_vote`),
        FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`participation_id`) REFERENCES `participations`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Exécuter les requêtes de création de tables
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->exec($statement);
        }
    }

    // Insérer des catégories par défaut si la table est vide
    $count = fetchValue("SELECT COUNT(*) FROM categories");
    if ($count == 0) {
        $categories = [
            ['Sport', 'Défis sportifs et d\'activité physique', 'fa-dumbbell', '#28a745'],
            ['Cuisine', 'Défis culinaires et gastronomiques', 'fa-utensils', '#fd7e14'],
            ['Art', 'Défis créatifs et artistiques', 'fa-palette', '#e83e8c'],
            ['Gaming', 'Défis de jeux vidéo et esport', 'fa-gamepad', '#6f42c1'],
            ['Humour', 'Défis drôles et divertissants', 'fa-laugh', '#ffc107'],
            ['Culture', 'Défis culturels et intellectuels', 'fa-book', '#20c997'],
            ['Technologie', 'Défis tech et programmation', 'fa-laptop-code', '#17a2b8'],
            ['Nature', 'Défis en plein air et écologiques', 'fa-leaf', '#198754']
        ];

        foreach ($categories as $index => $cat) {
            executeQuery(
                "INSERT INTO categories (nom, description, icone, couleur, ordre) VALUES (?, ?, ?, ?, ?)",
                [$cat[0], $cat[1], $cat[2], $cat[3], $index + 1]
            );
        }
    }
}

// Vérifier si les tables existent, sinon les créer
try {
    $db = connectDB();
    $tables = fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'utilisateurs'", [DB_NAME]);
    
    if ($tables == 0) {
        createTables();
        error_log("Tables de la base de données créées avec succès.");
    }
} catch (Exception $e) {
    error_log("Erreur lors de la vérification/création des tables: " . $e->getMessage());
}
?>