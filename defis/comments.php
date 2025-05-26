<?php
// defis/comments.php - API pour la gestion des commentaires

require_once '../config/config.php';

// Définir les en-têtes pour JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
    exit;
}

$currentUser = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Traitement GET - Récupérer les commentaires avec pagination
    if ($method === 'GET') {
        $defi_id = isset($_GET['defi_id']) ? intval($_GET['defi_id']) : 0;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(5, intval($_GET['limit']))) : 10;
        $offset = ($page - 1) * $limit;
        
        if (!$defi_id) {
            throw new Exception('ID du défi requis.');
        }
        
        // Vérifier que le défi existe
        $defi = fetchOne("SELECT id, titre FROM defis WHERE id = ? AND statut = 'actif'", [$defi_id]);
        if (!$defi) {
            throw new Exception('Défi non trouvé.');
        }
        
        // Compter le total des commentaires
        $total = fetchValue("SELECT COUNT(*) FROM commentaires WHERE defi_id = ?", [$defi_id]);
        $pages = ceil($total / $limit);
        
        // Récupérer les commentaires
        $commentaires = executeQuery(
            "SELECT c.*, u.pseudo, u.photo_profil, u.id as user_id
             FROM commentaires c
             JOIN utilisateurs u ON c.utilisateur_id = u.id
             WHERE c.defi_id = ?
             ORDER BY c.date_creation DESC
             LIMIT ? OFFSET ?",
            [$defi_id, $limit, $offset]
        );
        
        // Formater les commentaires
        $formatted_comments = [];
        foreach ($commentaires as $comment) {
            $formatted_comments[] = [
                'id' => $comment['id'],
                'contenu' => $comment['contenu'],
                'pseudo' => $comment['pseudo'],
                'user_id' => $comment['user_id'],
                'photo_profil' => getUserAvatar($comment['photo_profil'], $comment['pseudo'], 40),
                'date_creation' => $comment['date_creation'],
                'formatted_date' => formatDate($comment['date_creation']),
                'can_delete' => ($comment['user_id'] == $currentUser['id'] || hasPermission($currentUser['id'], 'comment', $comment['id'], 'delete'))
            ];
        }
        
        echo json_encode([
            'success' => true,
            'comments' => $formatted_comments,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $pages,
                'total_comments' => $total,
                'per_page' => $limit
            ]
        ]);
        exit;
    }
    
    // Traitement POST - Ajouter ou supprimer des commentaires
    if ($method === 'POST') {
        // Vérification CSRF
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Token de sécurité invalide.');
        }
        
        $action = $_POST['action'] ?? '';
        
        // Ajouter un commentaire
        if ($action === 'add') {
            $defi_id = isset($_POST['defi_id']) ? intval($_POST['defi_id']) : 0;
            $contenu = cleanInput($_POST['contenu'] ?? '');
            
            if (!$defi_id) {
                throw new Exception('ID du défi requis.');
            }
            
            if (empty($contenu)) {
                throw new Exception('Le commentaire ne peut pas être vide.');
            }
            
            if (strlen($contenu) > 500) {
                throw new Exception('Le commentaire ne peut pas dépasser 500 caractères.');
            }
            
            // Vérifier que le défi existe
            $defi = fetchOne("SELECT id, titre, createur_id FROM defis WHERE id = ? AND statut = 'actif'", [$defi_id]);
            if (!$defi) {
                throw new Exception('Défi non trouvé.');
            }
            
            // Rate limiting pour les commentaires
            if (!rateLimitCheck('add_comment', 10, 600)) {
                throw new Exception('Trop de commentaires récents. Veuillez attendre avant de commenter à nouveau.');
            }
            
            // Ajouter le commentaire
            $result = executeQuery(
                "INSERT INTO commentaires (utilisateur_id, defi_id, contenu, date_creation) VALUES (?, ?, ?, NOW())",
                [$currentUser['id'], $defi_id, $contenu]
            );
            
            if (!$result) {
                throw new Exception('Erreur lors de l\'ajout du commentaire.');
            }
            
            $comment_id = getLastInsertId();
            
            // Créer une notification pour le créateur du défi (sauf si c'est lui qui commente)
            if ($currentUser['id'] != $defi['createur_id']) {
                createNotification(
                    $defi['createur_id'],
                    'commentaire',
                    'Nouveau commentaire',
                    $currentUser['pseudo'] . ' a commenté votre défi "' . $defi['titre'] . '"',
                    ['url' => '/defi_friends/defis/view.php?id=' . $defi_id . '#comment-' . $comment_id],
                    $currentUser['id']
                );
            }
            
            // Récupérer le commentaire formaté
            $new_comment = fetchOne(
                "SELECT c.*, u.pseudo, u.photo_profil, u.id as user_id
                 FROM commentaires c
                 JOIN utilisateurs u ON c.utilisateur_id = u.id
                 WHERE c.id = ?",
                [$comment_id]
            );
            
            $formatted_comment = [
                'id' => $new_comment['id'],
                'contenu' => $new_comment['contenu'],
                'pseudo' => $new_comment['pseudo'],
                'user_id' => $new_comment['user_id'],
                'photo_profil' => getUserAvatar($new_comment['photo_profil'], $new_comment['pseudo'], 40),
                'date_creation' => $new_comment['date_creation'],
                'formatted_date' => formatDate($new_comment['date_creation']),
                'can_delete' => true // L'utilisateur peut toujours supprimer son propre commentaire
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Commentaire ajouté avec succès.',
                'comment' => $formatted_comment
            ]);
            exit;
        }
        
        // Supprimer un commentaire
        if ($action === 'delete') {
            $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
            
            if (!$comment_id) {
                throw new Exception('ID du commentaire requis.');
            }
            
            // Récupérer le commentaire avec les informations du défi
            $comment = fetchOne(
                "SELECT c.*, d.createur_id as defi_createur_id
                 FROM commentaires c
                 JOIN defis d ON c.defi_id = d.id
                 WHERE c.id = ?",
                [$comment_id]
            );
            
            if (!$comment) {
                throw new Exception('Commentaire non trouvé.');
            }
            
            // Vérifier les permissions de suppression
            if ($comment['utilisateur_id'] != $currentUser['id'] && $comment['defi_createur_id'] != $currentUser['id']) {
                throw new Exception('Vous n\'avez pas l\'autorisation de supprimer ce commentaire.');
            }
            
            // Supprimer le commentaire
            $result = executeQuery("DELETE FROM commentaires WHERE id = ?", [$comment_id]);
            
            if (!$result) {
                throw new Exception('Erreur lors de la suppression du commentaire.');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Commentaire supprimé avec succès.'
            ]);
            exit;
        }
        
        // Modifier un commentaire
        if ($action === 'edit') {
            $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
            $nouveau_contenu = cleanInput($_POST['contenu'] ?? '');
            
            if (!$comment_id) {
                throw new Exception('ID du commentaire requis.');
            }
            
            if (empty($nouveau_contenu)) {
                throw new Exception('Le commentaire ne peut pas être vide.');
            }
            
            if (strlen($nouveau_contenu) > 500) {
                throw new Exception('Le commentaire ne peut pas dépasser 500 caractères.');
            }
            
            // Récupérer le commentaire
            $comment = fetchOne("SELECT * FROM commentaires WHERE id = ?", [$comment_id]);
            
            if (!$comment) {
                throw new Exception('Commentaire non trouvé.');
            }
            
            // Vérifier que l'utilisateur est le propriétaire du commentaire
            if ($comment['utilisateur_id'] != $currentUser['id']) {
                throw new Exception('Vous ne pouvez modifier que vos propres commentaires.');
            }
            
            // Vérifier la limite de temps pour la modification (ex: 5 minutes)
            $time_limit = 5 * 60; // 5 minutes en secondes
            $comment_age = time() - strtotime($comment['date_creation']);
            
            if ($comment_age > $time_limit) {
                throw new Exception('Vous ne pouvez plus modifier ce commentaire (délai dépassé).');
            }
            
            // Mettre à jour le commentaire
            $result = executeQuery(
                "UPDATE commentaires SET contenu = ?, modifie = 1, date_modification = NOW() WHERE id = ?",
                [$nouveau_contenu, $comment_id]
            );
            
            if (!$result) {
                throw new Exception('Erreur lors de la modification du commentaire.');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Commentaire modifié avec succès.'
            ]);
            exit;
        }
        
        throw new Exception('Action non reconnue.');
    }
    
    // Méthode non supportée
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);

} catch (Exception $e) {
    // Log de l'erreur
    logError('Erreur dans l\'API des commentaires', [
        'user_id' => $currentUser['id'] ?? 'unknown',
        'method' => $method,
        'action' => $_POST['action'] ?? 'N/A',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Déterminer le code de statut HTTP approprié
    $status_code = 400; // Bad Request par défaut
    $error_message = $e->getMessage();
    
    if (strpos($error_message, 'non trouvé') !== false) {
        $status_code = 404; // Not Found
    } elseif (strpos($error_message, 'autorisation') !== false || strpos($error_message, 'permission') !== false) {
        $status_code = 403; // Forbidden
    } elseif (strpos($error_message, 'Token de sécurité') !== false) {
        $status_code = 403; // Forbidden
    } elseif (strpos($error_message, 'Rate limit') !== false || strpos($error_message, 'Trop de') !== false) {
        $status_code = 429; // Too Many Requests
    }
    
    http_response_code($status_code);
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'error_code' => $status_code
    ]);
}
?>