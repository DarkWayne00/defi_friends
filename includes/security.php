<?php
// includes/security.php - Fonctions de sécurité

// Générer un token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Vérifier un token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Fonction pour valider et traiter un token CSRF depuis POST
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        if (!verifyCSRFToken($token)) {
            logError('Token CSRF invalide', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'user_id' => $_SESSION['user_id'] ?? 'guest'
            ]);
            die('Token de sécurité invalide. Veuillez recharger la page.');
        }
    }
}

// Hacher un mot de passe de manière sécurisée
function hashPassword($password) {
    // Utiliser Argon2ID si disponible, sinon bcrypt
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 1024,
            'time_cost' => 2,
            'threads' => 2
        ]);
    } else {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
}

// Vérifier un mot de passe
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Valider la force d'un mot de passe
function isPasswordSecure($password) {
    // Au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           preg_match('/[^A-Za-z0-9]/', $password);
}

// Générer un token sécurisé pour la réinitialisation de mot de passe
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Nettoyer une chaîne pour éviter les attaques XSS
function sanitizeString($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

// Valider et nettoyer un nom d'utilisateur/pseudo
function validateUsername($username) {
    $username = trim($username);
    
    // Vérifier la longueur
    if (strlen($username) < 3 || strlen($username) > 30) {
        return false;
    }
    
    // Vérifier les caractères autorisés (lettres, chiffres, underscore, tiret)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        return false;
    }
    
    // Vérifier que ce n'est pas uniquement des chiffres
    if (is_numeric($username)) {
        return false;
    }
    
    return true;
}

// Valider un email
function validateEmail($email) {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Limiter le taux de requêtes (rate limiting basique)
function rateLimitCheck($action, $maxAttempts = 5, $timeWindow = 300) {
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $rateData = $_SESSION[$key];
    
    // Réinitialiser si la fenêtre de temps est passée
    if (time() - $rateData['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Vérifier si la limite est atteinte
    if ($rateData['count'] >= $maxAttempts) {
        logError('Rate limit dépassé', [
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'attempts' => $rateData['count']
        ]);
        return false;
    }
    
    // Incrémenter le compteur
    $_SESSION[$key]['count']++;
    return true;
}

// Vérifier si un fichier uploadé est sécurisé
function validateUploadedFile($file, $allowedTypes = ALLOWED_IMAGE_TYPES) {
    // Vérifier qu'il n'y a pas d'erreur
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors du téléchargement du fichier.'];
    }
    
    // Vérifier la taille
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Le fichier est trop volumineux. Taille maximale : ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.'];
    }
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé. Types autorisés : ' . implode(', ', $allowedTypes)];
    }
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain'
    ];
    
    if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
        return ['success' => false, 'message' => 'Le contenu du fichier ne correspond pas à son extension.'];
    }
    
    return ['success' => true, 'extension' => $extension, 'mime_type' => $mimeType];
}

// Générer un nom de fichier sécurisé
function generateSecureFilename($originalName, $userId = null) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $userPrefix = $userId ? $userId . '_' : '';
    
    return $userPrefix . $timestamp . '_' . $random . '.' . $extension;
}

// Nettoyer et valider une URL
function validateAndCleanUrl($url) {
    $url = trim($url);
    
    // Ajouter http:// si aucun protocole n'est spécifié
    if (!preg_match('/^https?:\/\//', $url)) {
        $url = 'http://' . $url;
    }
    
    // Valider l'URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    
    // Vérifier que ce n'est pas un protocole dangereux
    $parsed = parse_url($url);
    if (!in_array($parsed['scheme'], ['http', 'https'])) {
        return false;
    }
    
    return $url;
}

// Échapper les données pour les attributs HTML
function escapeHtmlAttribute($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Échapper les données pour JavaScript
function escapeJs($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// Vérifier si l'utilisateur a le droit d'accéder à une ressource
function hasPermission($userId, $resourceType, $resourceId = null, $permission = 'read') {
    // Système basique de permissions
    // Peut être étendu selon les besoins
    
    switch ($resourceType) {
        case 'defi':
            if ($permission === 'edit' || $permission === 'delete') {
                // Seul le créateur peut modifier/supprimer
                $defi = fetchOne("SELECT createur_id FROM defis WHERE id = ?", [$resourceId]);
                return $defi && $defi['createur_id'] == $userId;
            }
            return true; // Tout le monde peut voir les défis
            
        case 'comment':
            if ($permission === 'delete') {
                // L'auteur ou le créateur du défi peut supprimer
                $comment = fetchOne("
                    SELECT c.utilisateur_id, d.createur_id 
                    FROM commentaires c 
                    JOIN defis d ON c.defi_id = d.id 
                    WHERE c.id = ?
                ", [$resourceId]);
                return $comment && ($comment['utilisateur_id'] == $userId || $comment['createur_id'] == $userId);
            }
            return true;
            
        case 'participation':
            if ($permission === 'edit') {
                // Seul le participant peut modifier sa participation
                $participation = fetchOne("SELECT utilisateur_id FROM participations WHERE id = ?", [$resourceId]);
                return $participation && $participation['utilisateur_id'] == $userId;
            }
            return true;
            
        default:
            return false;
    }
}

// Journaliser les tentatives de connexion
function logLoginAttempt($username, $success, $ip = null) {
    $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    logError('Tentative de connexion', [
        'username' => $username,
        'success' => $success,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Fonction pour détecter les tentatives d'injection SQL basiques
function detectSQLInjection($input) {
    $patterns = [
        '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
        '/(\-\-|\#|\/\*|\*\/)/i',
        '/(\bOR\b|\bAND\b).*?(\=|\<|\>)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            logError('Tentative d\'injection SQL détectée', [
                'input' => substr($input, 0, 200),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            return true;
        }
    }
    
    return false;
}

// Middleware de sécurité à appeler sur chaque page
function securityMiddleware() {
    // Vérifier les en-têtes de sécurité
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy basique
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https:;";
        header("Content-Security-Policy: $csp");
    }
    
    // Nettoyer les données GET et POST
    foreach ($_GET as $key => $value) {
        if (is_string($value) && detectSQLInjection($value)) {
            die('Requête non autorisée.');
        }
    }
    
    foreach ($_POST as $key => $value) {
        if (is_string($value) && detectSQLInjection($value)) {
            die('Requête non autorisée.');
        }
    }
}

// Appeler le middleware de sécurité
securityMiddleware();
?>