<?php
// auth/logout.php - Page de déconnexion

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('/defi_friends/auth/login.php');
}

// Récupérer les informations de l'utilisateur avant déconnexion
$currentUser = getCurrentUser();
$userName = $currentUser ? $currentUser['pseudo'] : 'Utilisateur';

// Traitement de la déconnexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['confirm'])) {
    // Vérifier le token CSRF pour les requêtes POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCSRF();
    }
    
    // Supprimer les tokens "se souvenir de moi" si ils existent
    if (isset($_COOKIE['remember_token'])) {
        $remember_token = $_COOKIE['remember_token'];
        
        // Supprimer le token de la base de données
        executeQuery(
            "DELETE FROM remember_tokens WHERE token = ?",
            [hash('sha256', $remember_token)]
        );
        
        // Supprimer le cookie
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    // Journaliser la déconnexion
    logError('Déconnexion utilisateur', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'user_pseudo' => $userName,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Détruire la session
    session_unset();
    session_destroy();
    
    // Démarrer une nouvelle session pour les messages flash
    session_start();
    session_regenerate_id(true);
    
    // Message de confirmation
    $_SESSION['flash_message'] = "Vous avez été déconnecté avec succès. À bientôt " . htmlspecialchars($userName) . " !";
    $_SESSION['flash_type'] = "success";
    
    // Redirection vers la page d'accueil
    redirect('/defi_friends/index.php');
}

// Si on arrive ici, c'est une demande de confirmation de déconnexion (GET)
$pageTitle = "Déconnexion";

include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <!-- Confirmation de déconnexion -->
            <div class="text-center mb-4">
                <div class="mb-4">
                    <i class="fas fa-sign-out-alt fa-3x text-warning"></i>
                </div>
                <h1 class="h3 fw-bold">Déconnexion</h1>
                <p class="text-muted">Voulez-vous vraiment vous déconnecter de votre session ?</p>
            </div>

            <!-- Card de confirmation -->
            <div class="card border-0 shadow-lg rounded-3">
                <div class="card-body p-4 text-center">
                    
                    <!-- Informations utilisateur -->
                    <div class="mb-4">
                        <img src="<?= getUserAvatar($currentUser['photo_profil'], $currentUser['pseudo'], 80) ?>" 
                             alt="Avatar" class="rounded-circle mb-3" width="80" height="80">
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($currentUser['pseudo']) ?></h5>
                        <?php if ($currentUser['nom'] || $currentUser['prenom']): ?>
                            <p class="text-muted mb-0"><?= htmlspecialchars(trim($currentUser['prenom'] . ' ' . $currentUser['nom'])) ?></p>
                        <?php endif; ?>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Connecté depuis <?= formatDate($currentUser['derniere_connexion'] ?? $currentUser['date_inscription']) ?>
                        </small>
                    </div>

                    <!-- Message d'information -->
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Votre session sera fermée et vous devrez vous reconnecter pour accéder à votre compte.
                    </div>

                    <!-- Boutons d'action -->
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="/defi_friends/index.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>
                                Annuler
                            </a>
                        </div>
                        <div class="col-6">
                            <form method="POST" class="d-inline w-100">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Se déconnecter
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Lien direct pour déconnexion rapide -->
                    <div class="mt-3">
                        <a href="?confirm=1" class="small text-muted text-decoration-none">
                            Déconnexion rapide (sans confirmation)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Informations utiles -->
            <div class="mt-4">
                <div class="card border-0 bg-light">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-shield-alt me-2 text-primary"></i>
                            Pour votre sécurité
                        </h6>
                        <ul class="mb-0 small text-muted">
                            <li>Déconnectez-vous toujours après utilisation sur un ordinateur partagé</li>
                            <li>Vos données personnelles restent protégées</li>
                            <li>Vous pouvez vous reconnecter à tout moment</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Raccourcis rapides -->
            <div class="text-center mt-4">
                <div class="d-flex justify-content-center gap-3">
                    <a href="/defi_friends/profile.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user me-1"></i>
                        Mon profil
                    </a>
                    <a href="/defi_friends/defis/index.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-trophy me-1"></i>
                        Mes défis
                    </a>
                    <a href="/defi_friends/notifications.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-bell me-1"></i>
                        Notifications
                        <?php
                        $unreadCount = countUnreadNotifications($currentUser['id']);
                        if ($unreadCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Scripts spécifiques à la page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus sur le bouton d'annulation pour permettre une navigation au clavier
    const cancelButton = document.querySelector('.btn-outline-secondary');
    if (cancelButton) {
        cancelButton.focus();
    }
    
    // Confirmation supplémentaire avant déconnexion
    const logoutForm = document.querySelector('form');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function(event) {
            // Optionnel: demander une confirmation supplémentaire
            const confirmLogout = confirm('Êtes-vous sûr de vouloir vous déconnecter ?');
            if (!confirmLogout) {
                event.preventDefault();
                return false;
            }
            
            // Désactiver le bouton pour éviter les clics multiples
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Déconnexion...';
            }
        });
    }
    
    // Raccourci clavier pour déconnexion rapide (Ctrl+Shift+L)
    document.addEventListener('keydown', function(event) {
        if (event.ctrlKey && event.shiftKey && event.key === 'L') {
            event.preventDefault();
            if (confirm('Déconnexion rapide - Êtes-vous sûr ?')) {
                window.location.href = '?confirm=1';
            }
        }
    });
    
    // Gestion de l'historique du navigateur
    if (window.history && window.history.pushState) {
        // Empêcher le retour en arrière après déconnexion
        window.history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function() {
            window.history.pushState(null, null, window.location.href);
        });
    }
    
    // Nettoyage des données sensibles en mémoire (basique)
    window.addEventListener('beforeunload', function() {
        // Nettoyer les variables sensibles si elles existent
        if (window.APP_CONFIG) {
            window.APP_CONFIG.USER_ID = null;
            window.APP_CONFIG.CSRF_TOKEN = null;
        }
    });
});

// Timer de déconnexion automatique après inactivité (optionnel)
let inactivityTimer;
function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(function() {
        if (confirm('Vous êtes inactif depuis longtemps. Voulez-vous rester connecté ?')) {
            resetInactivityTimer();
        } else {
            window.location.href = '?confirm=1';
        }
    }, 30 * 60 * 1000); // 30 minutes
}

// Événements qui réinitialisent le timer d'inactivité
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
    document.addEventListener(event, resetInactivityTimer, { passive: true });
});

// Démarrer le timer
resetInactivityTimer();
</script>

<style>
/* Styles spécifiques à la page de déconnexion */
.card {
    border-radius: 1rem !important;
}

/* Animation d'entrée */
.card {
    animation: fadeInScale 0.5s ease-out;
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Style pour l'avatar */
.rounded-circle {
    border: 3px solid var(--bs-light);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Animation pour le bouton de déconnexion */
.btn-danger {
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

/* Style pour l'alerte */
.alert-info {
    border: none;
    background-color: rgba(13, 202, 240, 0.1);
    color: var(--bs-info);
}

/* Amélioration de l'accessibilité */
.btn:focus {
    box-shadow: 0 0 0 0.25rem rgba(99, 27, 255, 0.25);
}

/* Style pour les raccourcis */
.btn-sm {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}

/* Responsive */
@media (max-width: 576px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .card-body {
        padding: 2rem 1.5rem !important;
    }
    
    .d-flex.gap-3 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        width: 100%;
    }
}

/* État de chargement */
.btn:disabled {
    opacity: 0.8;
    cursor: not-allowed;
}

/* Animation de pulsation pour attirer l'attention */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.btn-danger:not(:disabled):not(.disabled):active {
    animation: pulse 0.3s ease;
}
</style>

<?php include '../includes/footer.php'; ?>