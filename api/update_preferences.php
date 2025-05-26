<?php
// api/update_preferences.php - API pour mettre à jour les préférences utilisateur

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

// Traitement uniquement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

try {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Token de sécurité invalide.');
    }
    
    $preferences = [];
    $updates = [];
    $params = [$currentUser['id']];
    
    // Traitement du thème
    if (isset($_POST['theme'])) {
        $theme = cleanInput($_POST['theme']);
        if (in_array($theme, ['light', 'dark'])) {
            $updates[] = "theme_preference = ?";
            $params[] = $theme;
            $preferences['theme'] = $theme;
        } else {
            throw new Exception('Thème invalide.');
        }
    }
    
    // Traitement des notifications (si ajouté plus tard)
    if (isset($_POST['notifications_email'])) {
        $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
        $updates[] = "notifications_email = ?";
        $params[] = $notifications_email;
        $preferences['notifications_email'] = $notifications_email;
    }
    
    // Traitement de la visibilité du profil
    if (isset($_POST['profil_public'])) {
        $profil_public = isset($_POST['profil_public']) ? 1 : 0;
        $updates[] = "profil_public = ?";
        $params[] = $profil_public;
        $preferences['profil_public'] = $profil_public;
    }
    
    if (empty($updates)) {
        throw new Exception('Aucune préférence à mettre à jour.');
    }
    
    // Construire et exécuter la requête SQL
    $sql = "UPDATE utilisateurs SET " . implode(', ', $updates) . " WHERE id = ?";
    $result = executeQuery($sql, array_slice($params, 1) + [$currentUser['id']]);
    
    if ($result === false) {
        throw new Exception('Erreur lors de la mise à jour des préférences.');
    }
    
    // Log de l'action
    logError('Préférences mises à jour', [
        'user_id' => $currentUser['id'],
        'preferences' => $preferences
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Préférences mises à jour avec succès.',
        'preferences' => $preferences
    ]);

} catch (Exception $e) {
    logError('Erreur API update_preferences', [
        'user_id' => $currentUser['id'],
        'error' => $e->getMessage(),
        'post_data' => $_POST
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>