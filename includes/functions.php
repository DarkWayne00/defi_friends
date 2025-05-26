<?php
// includes/functions.php - Fonctions utilitaires de l'application

/**
 * Créer une notification pour un utilisateur
 * 
 * @param int $userId ID de l'utilisateur destinataire
 * @param string $type Type de notification
 * @param string $titre Titre de la notification
 * @param string $message Message de la notification
 * @param array $data Données supplémentaires (optionnel)
 * @param int $expediteurId ID de l'expéditeur (optionnel)
 * @return bool Succès de l'opération
 */
function createNotification($userId, $type, $titre, $message, $data = null, $expediteurId = null) {
    try {
        $dataJson = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
        
        executeQuery(
            "INSERT INTO notifications (utilisateur_id, type, titre, message, data, expediteur_id) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $type, $titre, $message, $dataJson, $expediteurId]
        );
        
        return true;
    } catch (Exception $e) {
        logError('Erreur lors de la création de la notification', [
            'user_id' => $userId,
            'type' => $type,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Obtenir les notifications non lues d'un utilisateur
 * 
 * @param int $userId ID de l'utilisateur
 * @param int $limit Nombre maximum de notifications à récupérer
 * @return array Liste des notifications
 */
function getUnreadNotifications($userId, $limit = 10) {
    return executeQuery(
        "SELECT n.*, u.pseudo as expediteur_pseudo 
         FROM notifications n 
         LEFT JOIN utilisateurs u ON n.expediteur_id = u.id 
         WHERE n.utilisateur_id = ? AND n.lu = 0 
         ORDER BY n.date_creation DESC 
         LIMIT ?",
        [$userId, $limit]
    );
}

/**
 * Marquer une notification comme lue
 * 
 * @param int $notificationId ID de la notification
 * @param int $userId ID de l'utilisateur (pour vérification de sécurité)
 * @return bool Succès de l'opération
 */
function markNotificationAsRead($notificationId, $userId) {
    $result = executeQuery(
        "UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?",
        [$notificationId, $userId]
    );
    
    return $result > 0;
}

/**
 * Compter les notifications non lues d'un utilisateur
 * 
 * @param int $userId ID de l'utilisateur
 * @return int Nombre de notifications non lues
 */
function countUnreadNotifications($userId) {
    return fetchValue(
        "SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu = 0",
        [$userId]
    );
}

/**
 * Obtenir les statistiques des notifications par type
 *
 * @param int $userId ID de l'utilisateur
 * @return array Nombre de notifications par type
 */
function getNotificationStats($userId) {
    $stats = executeQuery(
        "SELECT type, COUNT(*) as count FROM notifications WHERE utilisateur_id = ? AND lu = 0 GROUP BY type",
        [$userId]
    );
    
    $result = [];
    foreach ($stats as $stat) {
        $result[$stat['type']] = $stat['count'];
    }
    
    return $result;
}

/**
 * Obtenir les badges d'un utilisateur
 * 
 * @param int $userId ID de l'utilisateur
 * @return array Liste des badges obtenus
 */
function getUserBadges($userId) {
    $badges = [];
    
    // Badge de créateur
    $nbDefis = fetchValue('SELECT COUNT(*) FROM defis WHERE createur_id = ?', [$userId]);
    
    if ($nbDefis >= 5) {
        $badges[] = [
            'nom' => 'Créateur Bronze',
            'description' => 'A créé au moins 5 défis',
            'icone' => 'fa-medal',
            'couleur' => '#CD7F32',
            'niveau' => 'bronze'
        ];
    }
    
    if ($nbDefis >= 10) {
        $badges[] = [
            'nom' => 'Créateur Argent',
            'description' => 'A créé au moins 10 défis',
            'icone' => 'fa-medal',
            'couleur' => '#C0C0C0',
            'niveau' => 'argent'
        ];
    }
    
    if ($nbDefis >= 20) {
        $badges[] = [
            'nom' => 'Créateur Or',
            'description' => 'A créé au moins 20 défis',
            'icone' => 'fa-medal',
            'couleur' => '#FFD700',
            'niveau' => 'or'
        ];
    }
    
    // Badge de participation
    $nbParticipations = fetchValue('SELECT COUNT(*) FROM participations WHERE utilisateur_id = ?', [$userId]);
    
    if ($nbParticipations >= 5) {
        $badges[] = [
            'nom' => 'Participant Bronze',
            'description' => 'A participé à au moins 5 défis',
            'icone' => 'fa-award',
            'couleur' => '#CD7F32',
            'niveau' => 'bronze'
        ];
    }
    
    if ($nbParticipations >= 10) {
        $badges[] = [
            'nom' => 'Participant Argent',
            'description' => 'A participé à au moins 10 défis',
            'icone' => 'fa-award',
            'couleur' => '#C0C0C0',
            'niveau' => 'argent'
        ];
    }
    
    if ($nbParticipations >= 20) {
        $badges[] = [
            'nom' => 'Participant Or',
            'description' => 'A participé à au moins 20 défis',
            'icone' => 'fa-award',
            'couleur' => '#FFD700',
            'niveau' => 'or'
        ];
    }
    
    // Badge de complétude
    $nbDefisCompletes = fetchValue(
        'SELECT COUNT(*) FROM participations WHERE utilisateur_id = ? AND statut = "complete"',
        [$userId]
    );
    
    if ($nbDefisCompletes >= 5) {
        $badges[] = [
            'nom' => 'Finisseur Bronze',
            'description' => 'A terminé au moins 5 défis',
            'icone' => 'fa-trophy',
            'couleur' => '#CD7F32',
            'niveau' => 'bronze'
        ];
    }
    
    if ($nbDefisCompletes >= 10) {
        $badges[] = [
            'nom' => 'Finisseur Argent',
            'description' => 'A terminé au moins 10 défis',
            'icone' => 'fa-trophy',
            'couleur' => '#C0C0C0',
            'niveau' => 'argent'
        ];
    }
    
    if ($nbDefisCompletes >= 20) {
        $badges[] = [
            'nom' => 'Finisseur Or',
            'description' => 'A terminé au moins 20 défis',
            'icone' => 'fa-trophy',
            'couleur' => '#FFD700',
            'niveau' => 'or'
        ];
    }
    
    // Badge social (amis)
    $nbAmis = fetchValue(
        'SELECT COUNT(*) FROM amis WHERE (utilisateur_id = ? OR ami_id = ?) AND statut = "accepte"',
        [$userId, $userId]
    );
    
    if ($nbAmis >= 5) {
        $badges[] = [
            'nom' => 'Social Bronze',
            'description' => 'A au moins 5 amis',
            'icone' => 'fa-users',
            'couleur' => '#CD7F32',
            'niveau' => 'bronze'
        ];
    }
    
    if ($nbAmis >= 15) {
        $badges[] = [
            'nom' => 'Social Argent',
            'description' => 'A au moins 15 amis',
            'icone' => 'fa-users',
            'couleur' => '#C0C0C0',
            'niveau' => 'argent'
        ];
    }
    
    if ($nbAmis >= 30) {
        $badges[] = [
            'nom' => 'Social Or',
            'description' => 'A au moins 30 amis',
            'icone' => 'fa-users',
            'couleur' => '#FFD700',
            'niveau' => 'or'
        ];
    }
    
    return $badges;
}

/**
 * Obtenir les statistiques d'un utilisateur
 * 
 * @param int $userId ID de l'utilisateur
 * @return array Statistiques de l'utilisateur
 */
function getUserStats($userId) {
    $stats = [];
    
    // Défis créés
    $stats['defis_crees'] = fetchValue('SELECT COUNT(*) FROM defis WHERE createur_id = ?', [$userId]);
    
    // Participations
    $stats['participations_total'] = fetchValue('SELECT COUNT(*) FROM participations WHERE utilisateur_id = ?', [$userId]);
    $stats['participations_en_cours'] = fetchValue('SELECT COUNT(*) FROM participations WHERE utilisateur_id = ? AND statut = "en_cours"', [$userId]);
    $stats['participations_terminees'] = fetchValue('SELECT COUNT(*) FROM participations WHERE utilisateur_id = ? AND statut = "complete"', [$userId]);
    
    // Amis
    $stats['nb_amis'] = fetchValue('SELECT COUNT(*) FROM amis WHERE (utilisateur_id = ? OR ami_id = ?) AND statut = "accepte"', [$userId, $userId]);
    
    // Commentaires
    $stats['nb_commentaires'] = fetchValue('SELECT COUNT(*) FROM commentaires WHERE utilisateur_id = ?', [$userId]);
    
    // Taux de réussite
    if ($stats['participations_total'] > 0) {
        $stats['taux_reussite'] = round(($stats['participations_terminees'] / $stats['participations_total']) * 100, 1);
    } else {
        $stats['taux_reussite'] = 0;
    }
    
    return $stats;
}

/**
 * Obtenir les défis en tendance
 * 
 * @param int $limit Nombre de défis à récupérer
 * @return array Liste des défis en tendance
 */
function getTrendingDefis($limit = 6) {
    return executeQuery(
        "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, u.pseudo as createur_pseudo,
            (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants,
            (SELECT COUNT(*) FROM participations WHERE defi_id = d.id AND date_participation > DATE_SUB(NOW(), INTERVAL 7 DAY)) as nb_participants_recent,
            (SELECT COUNT(*) FROM commentaires WHERE defi_id = d.id AND date_creation > DATE_SUB(NOW(), INTERVAL 14 DAY)) as nb_commentaires_recent
         FROM defis d
         LEFT JOIN categories c ON d.categorie_id = c.id
         LEFT JOIN utilisateurs u ON d.createur_id = u.id
         WHERE d.statut = 'actif'
         ORDER BY (nb_participants_recent * 3 + nb_commentaires_recent * 2) DESC, d.date_creation DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Obtenir les défis récents
 * 
 * @param int $limit Nombre de défis à récupérer
 * @param int $userId ID de l'utilisateur connecté (optionnel)
 * @return array Liste des défis récents
 */
function getRecentDefis($limit = 10, $userId = null) {
    $sql = "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, u.pseudo as createur_pseudo,
                (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants";
    
    if ($userId) {
        $sql .= ", (SELECT COUNT(*) FROM participations WHERE defi_id = d.id AND utilisateur_id = ?) as user_participe";
    }
    
    $sql .= " FROM defis d
              LEFT JOIN categories c ON d.categorie_id = c.id
              LEFT JOIN utilisateurs u ON d.createur_id = u.id
              WHERE d.statut = 'actif'
              ORDER BY d.date_creation DESC
              LIMIT ?";
    
    $params = $userId ? [$userId, $limit] : [$limit];
    
    return executeQuery($sql, $params);
}

/**
 * Rechercher des défis avec filtres
 * 
 * @param string $query Terme de recherche
 * @param array $filters Filtres supplémentaires
 * @param int $limit Nombre de résultats par page
 * @param int $offset Décalage pour la pagination
 * @return array Résultats de recherche
 */
function searchDefis($query = '', $filters = [], $limit = 10, $offset = 0) {
    $whereConditions = ["d.statut = 'actif'"];
    $params = [];
    
    // Recherche textuelle
    if (!empty($query)) {
        $whereConditions[] = "(d.titre LIKE ? OR d.description LIKE ?)";
        $searchTerm = '%' . $query . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Filtre par catégorie
    if (!empty($filters['categorie_id'])) {
        $whereConditions[] = "d.categorie_id = ?";
        $params[] = $filters['categorie_id'];
    }
    
    // Filtre par difficulté
    if (!empty($filters['difficulte'])) {
        $whereConditions[] = "d.difficulte = ?";
        $params[] = $filters['difficulte'];
    }
    
    // Filtre par date
    if (!empty($filters['date'])) {
        switch ($filters['date']) {
            case 'today':
                $whereConditions[] = "DATE(d.date_creation) = CURDATE()";
                break;
            case 'week':
                $whereConditions[] = "YEARWEEK(d.date_creation, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                $whereConditions[] = "MONTH(d.date_creation) = MONTH(CURDATE()) AND YEAR(d.date_creation) = YEAR(CURDATE())";
                break;
        }
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Compter le total pour la pagination
    $countSql = "SELECT COUNT(*) FROM defis d WHERE " . $whereClause;
    $total = fetchValue($countSql, $params);
    
    // Récupérer les résultats
    $sql = "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, u.pseudo as createur_pseudo,
                (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants
            FROM defis d
            LEFT JOIN categories c ON d.categorie_id = c.id
            LEFT JOIN utilisateurs u ON d.createur_id = u.id
            WHERE " . $whereClause . "
            ORDER BY d.date_creation DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $results = executeQuery($sql, $params);
    
    return [
        'results' => $results,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ];
}

/**
 * Obtenir les catégories actives
 * 
 * @return array Liste des catégories
 */
function getActiveCategories() {
    return executeQuery(
        "SELECT * FROM categories WHERE actif = 1 ORDER BY ordre, nom",
        []
    );
}

/**
 * Vérifier si deux utilisateurs sont amis
 * 
 * @param int $userId1 ID du premier utilisateur
 * @param int $userId2 ID du deuxième utilisateur
 * @return array|false Informations sur l'amitié ou false
 */
function getFriendshipStatus($userId1, $userId2) {
    return fetchOne(
        "SELECT * FROM amis 
         WHERE ((utilisateur_id = ? AND ami_id = ?) OR (utilisateur_id = ? AND ami_id = ?))
         AND statut != 'refuse'",
        [$userId1, $userId2, $userId2, $userId1]
    );
}

/**
 * Envoyer une demande d'amitié
 * 
 * @param int $senderId ID de l'expéditeur
 * @param int $receiverId ID du destinataire
 * @return array Résultat de l'opération
 */
function sendFriendRequest($senderId, $receiverId) {
    // Vérifier qu'il n'y a pas déjà une relation
    $existing = getFriendshipStatus($senderId, $receiverId);
    if ($existing) {
        return ['success' => false, 'message' => 'Une relation existe déjà entre vous.'];
    }
    
    try {
        executeQuery(
            "INSERT INTO amis (utilisateur_id, ami_id, demandeur_id, statut) VALUES (?, ?, ?, 'en_attente')",
            [$senderId, $receiverId, $senderId]
        );
        
        // Créer une notification
        $senderName = fetchValue("SELECT pseudo FROM utilisateurs WHERE id = ?", [$senderId]);
        createNotification(
            $receiverId,
            'amitie',
            'Nouvelle demande d\'amitié',
            $senderName . ' vous a envoyé une demande d\'amitié.',
            ['sender_id' => $senderId, 'url' => '/defi_friends/amis/requests.php'],
            $senderId
        );
        
        return ['success' => true, 'message' => 'Demande d\'amitié envoyée.'];
    } catch (Exception $e) {
        logError('Erreur lors de l\'envoi de la demande d\'amitié', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi de la demande.'];
    }
}

/**
 * Obtenir les activités récentes des amis
 * 
 * @param int $userId ID de l'utilisateur
 * @param int $limit Nombre d'activités à récupérer
 * @return array Liste des activités
 */
function getFriendsActivities($userId, $limit = 20) {
    return executeQuery(
        "SELECT 'defi_cree' as type, d.id as item_id, d.titre as item_title, d.date_creation as activity_date,
                u.id as user_id, u.pseudo as user_pseudo, c.nom as categorie
         FROM defis d
         JOIN utilisateurs u ON d.createur_id = u.id
         LEFT JOIN categories c ON d.categorie_id = c.id
         WHERE u.id IN (
             SELECT CASE WHEN utilisateur_id = ? THEN ami_id ELSE utilisateur_id END
             FROM amis WHERE (utilisateur_id = ? OR ami_id = ?) AND statut = 'accepte'
         )
         
         UNION ALL
         
         SELECT 'participation' as type, p.defi_id as item_id, d.titre as item_title, p.date_participation as activity_date,
                u.id as user_id, u.pseudo as user_pseudo, c.nom as categorie
         FROM participations p
         JOIN defis d ON p.defi_id = d.id
         JOIN utilisateurs u ON p.utilisateur_id = u.id
         LEFT JOIN categories c ON d.categorie_id = c.id
         WHERE u.id IN (
             SELECT CASE WHEN utilisateur_id = ? THEN ami_id ELSE utilisateur_id END
             FROM amis WHERE (utilisateur_id = ? OR ami_id = ?) AND statut = 'accepte'
         )
         
         ORDER BY activity_date DESC
         LIMIT ?",
        [$userId, $userId, $userId, $userId, $userId, $userId, $limit]
    );
}

/**
 * Traiter l'upload d'un fichier
 * 
 * @param array $file Fichier uploadé ($_FILES['name'])
 * @param string $directory Dossier de destination
 * @param int $userId ID de l'utilisateur (pour sécurité)
 * @return array Résultat de l'upload
 */
function processFileUpload($file, $directory = 'images', $userId = null) {
    // Valider le fichier
    $validation = validateUploadedFile($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    // Créer le dossier si nécessaire
    $uploadDir = UPLOAD_PATH . '/' . $directory;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'Impossible de créer le dossier de destination.'];
        }
    }
    
    // Générer un nom de fichier sécurisé
    $filename = generateSecureFilename($file['name'], $userId);
    $fullPath = $uploadDir . '/' . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [
            'success' => true,
            'filename' => $directory . '/' . $filename,
            'full_path' => $fullPath,
            'url' => UPLOAD_URL . '/' . $directory . '/' . $filename
        ];
    } else {
        return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier.'];
    }
}

/**
 * Calculer le score de tendance d'un défi
 * 
 * @param array $defi Données du défi
 * @return float Score de tendance
 */
function calculateTrendingScore($defi) {
    $participationsRecentes = $defi['nb_participants_recent'] ?? 0;
    $commentairesRecents = $defi['nb_commentaires_recent'] ?? 0;
    $vues = $defi['vues'] ?? 0;
    
    // Algorithme simple de score
    $score = ($participationsRecentes * 5) + ($commentairesRecents * 3) + ($vues * 0.1);
    
    // Bonus pour les défis récents (moins de 7 jours)
    if (isset($defi['date_creation'])) {
        $daysSinceCreation = (time() - strtotime($defi['date_creation'])) / (60 * 60 * 24);
        if ($daysSinceCreation < 7) {
            $score *= 1.5;
        }
    }
    
    return $score;
}

/**
 * Enregistrer une vue sur un défi
 * 
 * @param int $defiId ID du défi
 * @param int $userId ID de l'utilisateur (optionnel)
 */
function recordDefiView($defiId, $userId = null) {
    // Utiliser une session pour éviter les vues multiples
    $viewKey = 'viewed_defi_' . $defiId;
    
    if (!isset($_SESSION[$viewKey])) {
        executeQuery("UPDATE defis SET vues = vues + 1 WHERE id = ?", [$defiId]);
        $_SESSION[$viewKey] = true;
    }
}

/**
 * Obtenir des suggestions de défis pour un utilisateur
 * 
 * @param int $userId ID de l'utilisateur
 * @param int $limit Nombre de suggestions
 * @return array Liste de défis suggérés
 */
function getSuggestedDefis($userId, $limit = 5) {
    // Obtenir les catégories préférées de l'utilisateur
    $preferredCategories = executeQuery(
        "SELECT d.categorie_id, COUNT(*) as count
         FROM participations p
         JOIN defis d ON p.defi_id = d.id
         WHERE p.utilisateur_id = ?
         GROUP BY d.categorie_id
         ORDER BY count DESC
         LIMIT 3",
        [$userId]
    );
    
    $categoryIds = array_column($preferredCategories, 'categorie_id');
    
    if (empty($categoryIds)) {
        // Si pas de préférences, retourner les défis populaires
        return executeQuery(
            "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, u.pseudo as createur_pseudo,
                    (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants
             FROM defis d
             LEFT JOIN categories c ON d.categorie_id = c.id
             LEFT JOIN utilisateurs u ON d.createur_id = u.id
             WHERE d.statut = 'actif' AND d.createur_id != ?
             AND d.id NOT IN (SELECT defi_id FROM participations WHERE utilisateur_id = ?)
             ORDER BY nb_participants DESC, d.date_creation DESC
             LIMIT ?",
            [$userId, $userId, $limit]
        );
    } else {
        // Suggérer des défis dans les catégories préférées
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        $params = array_merge($categoryIds, [$userId, $userId, $limit]);
        
        return executeQuery(
            "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, u.pseudo as createur_pseudo,
                    (SELECT COUNT(*) FROM participations WHERE defi_id = d.id) as nb_participants
             FROM defis d
             LEFT JOIN categories c ON d.categorie_id = c.id
             LEFT JOIN utilisateurs u ON d.createur_id = u.id
             WHERE d.statut = 'actif' AND d.categorie_id IN ($placeholders)
             AND d.createur_id != ? AND d.id NOT IN (SELECT defi_id FROM participations WHERE utilisateur_id = ?)
             ORDER BY nb_participants DESC, d.date_creation DESC
             LIMIT ?",
            $params
        );
    }
}
?>