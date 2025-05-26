<?php
// config/config.php - Configuration principale de l'application

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    // Configuration de session sécurisée AVANT de démarrer la session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600 * 24); // 24 heures
    
    session_start();
}

// Configuration de l'environnement
define('APP_NAME', 'Défi Friends');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/defi_friends');
define('APP_DEBUG', true); // Mettre à false en production

// Configuration des chemins
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', APP_URL . '/uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Configuration de sécurité
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600 * 24); // 24 heures
define('BCRYPT_COST', 12);

// Configuration des emails (pour les notifications)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@defi-friends.local');
define('FROM_NAME', 'Défi Friends');

// Types de fichiers autorisés pour les uploads
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'txt']);

// Configuration de cache simple
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 300); // 5 minutes

// Gestion des erreurs
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// Timezone
date_default_timezone_set('Europe/Paris');

// Inclure les fichiers nécessaires
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/security.php';
require_once ROOT_PATH . '/includes/functions.php';

// Fonction pour logger les erreurs
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    error_log($logMessage);
}

// Fonction pour rediriger
function redirect($url, $permanent = false) {
    if (!headers_sent()) {
        if ($permanent) {
            header('HTTP/1.1 301 Moved Permanently');
        }
        header('Location: ' . $url);
        exit();
    } else {
        echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
        exit();
    }
}

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Fonction pour obtenir l'utilisateur connecté
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    if ($user === null) {
        $user = fetchOne(
            "SELECT id, pseudo, email, nom, prenom, photo_profil, theme_preference, date_inscription 
             FROM utilisateurs WHERE id = ? AND statut = 'actif'",
            [$_SESSION['user_id']]
        );
    }
    
    return $user;
}

// Fonction pour protéger une page (redirection si pas connecté)
function requireLogin($redirectUrl = '/defi_friends/auth/login.php') {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect($redirectUrl);
    }
}

// Fonction pour empêcher l'accès aux utilisateurs connectés (pages de login/register)
function requireGuest($redirectUrl = '/defi_friends/index.php') {
    if (isLoggedIn()) {
        redirect($redirectUrl);
    }
}

// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y à H:i') {
    if (!$date) return 'Non défini';
    
    $dateObj = is_string($date) ? new DateTime($date) : $date;
    
    // Calcul du temps écoulé pour les dates récentes
    $now = new DateTime();
    $diff = $now->diff($dateObj);
    
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) {
                return 'À l\'instant';
            } else {
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
            }
        } else {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        }
    } elseif ($diff->days == 1) {
        return 'Hier à ' . $dateObj->format('H:i');
    } elseif ($diff->days < 7) {
        return $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
    } else {
        return $dateObj->format($format);
    }
}

// Fonction pour obtenir l'avatar d'un utilisateur
function getUserAvatar($photoProfile = null, $pseudo = '', $size = 40) {
    if ($photoProfile && file_exists(UPLOAD_PATH . '/' . $photoProfile)) {
        return UPLOAD_URL . '/' . htmlspecialchars($photoProfile);
    }
    
    // Générer un avatar par défaut basé sur les initiales
    $initials = '';
    if (!empty($pseudo)) {
        $words = explode(' ', trim($pseudo));
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    
    if (empty($initials)) {
        $initials = '?';
    }
    
    // Générer une couleur basée sur le pseudo
    $colors = ['#631bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'];
    $colorIndex = crc32($pseudo) % count($colors);
    $color = $colors[$colorIndex];
    
    // Retourner une URL pour générer l'avatar SVG
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}'%3E%3Crect width='{$size}' height='{$size}' fill='{$color}'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='0.35em' font-family='Arial, sans-serif' font-size='" . ($size * 0.4) . "' fill='white'%3E{$initials}%3C/text%3E%3C/svg%3E";
}

// Fonction pour nettoyer les données d'entrée
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Fonction pour valider un email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Fonction pour créer le dossier uploads s'il n'existe pas
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        logError('Impossible de créer le dossier uploads: ' . UPLOAD_PATH);
    }
}

// Créer le dossier logs s'il n'existe pas
$logsPath = ROOT_PATH . '/logs';
if (!is_dir($logsPath)) {
    if (!mkdir($logsPath, 0755, true)) {
        error_log('Impossible de créer le dossier logs: ' . $logsPath);
    }
}

// Régénérer l'ID de session périodiquement pour la sécurité
if (isset($_SESSION['last_regeneration'])) {
    if (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
} else {
    $_SESSION['last_regeneration'] = time();
}
?>