<?php
// auth/login.php - Page de connexion

require_once '../config/config.php';

// Rediriger si déjà connecté
requireGuest();

$pageTitle = "Connexion";
$errors = [];

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    // Vérification du rate limiting
    if (!rateLimitCheck('login', 5, 300)) {
        $errors[] = "Trop de tentatives de connexion. Veuillez attendre 5 minutes.";
    } else {
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Validation de base
        if (empty($email)) {
            $errors[] = "L'email est requis.";
        } elseif (!isValidEmail($email)) {
            $errors[] = "Format d'email invalide.";
        }
        
        if (empty($password)) {
            $errors[] = "Le mot de passe est requis.";
        }
        
        if (empty($errors)) {
            // Rechercher l'utilisateur
            $user = fetchOne(
                "SELECT * FROM utilisateurs WHERE email = ? AND statut = 'actif'",
                [$email]
            );
            
            if ($user && verifyPassword($password, $user['mot_de_passe'])) {
                // Connexion réussie
                logLoginAttempt($email, true);
                
                // Mettre à jour la dernière connexion
                executeQuery(
                    "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?",
                    [$user['id']]
                );
                
                // Créer la session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['last_regeneration'] = time();
                
                // Gestion du "se souvenir de moi"
                if ($remember) {
                    $remember_token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 jours
                    
                    // Sauvegarder le token en base (vous devrez créer cette table)
                    executeQuery(
                        "INSERT INTO remember_tokens (utilisateur_id, token, expiry) VALUES (?, ?, ?) 
                         ON DUPLICATE KEY UPDATE token = VALUES(token), expiry = VALUES(expiry)",
                        [$user['id'], hash('sha256', $remember_token), date('Y-m-d H:i:s', $expiry)]
                    );
                    
                    // Créer le cookie
                    setcookie('remember_token', $remember_token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
                }
                
                // Redirection
                $redirect_url = $_SESSION['redirect_after_login'] ?? '/defi_friends/index.php';
                unset($_SESSION['redirect_after_login']);
                
                $_SESSION['flash_message'] = "Connexion réussie ! Bienvenue " . htmlspecialchars($user['pseudo']) . " !";
                $_SESSION['flash_type'] = "success";
                
                redirect($redirect_url);
            } else {
                // Connexion échouée
                logLoginAttempt($email, false);
                $errors[] = "Email ou mot de passe incorrect.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <!-- En-tête -->
            <div class="text-center mb-4">
                <div class="mb-4">
                    <i class="fas fa-sign-in-alt fa-3x text-primary"></i>
                </div>
                <h1 class="h3 fw-bold">Connexion</h1>
                <p class="text-muted">Connectez-vous à votre compte <?= APP_NAME ?></p>
            </div>

            <!-- Formulaire de connexion -->
            <div class="card border-0 shadow-lg rounded-3">
                <div class="card-body p-4">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php if (count($errors) === 1): ?>
                                <?= htmlspecialchars($errors[0]) ?>
                            <?php else: ?>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">
                                <i class="fas fa-envelope me-2"></i>
                                Adresse email
                            </label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   required autocomplete="email" autofocus>
                            <div class="invalid-feedback">
                                Veuillez saisir une adresse email valide.
                            </div>
                        </div>

                        <!-- Mot de passe -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">
                                <i class="fas fa-lock me-2"></i>
                                Mot de passe
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                       required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" 
                                        aria-label="Afficher/masquer le mot de passe">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Le mot de passe est requis.
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="row mb-4">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label small" for="remember">
                                        Se souvenir de moi
                                    </label>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <a href="/defi_friends/auth/forgot-password.php" class="small text-decoration-none">
                                    Mot de passe oublié ?
                                </a>
                            </div>
                        </div>

                        <!-- Bouton de connexion -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Se connecter
                            </button>
                        </div>

                        <!-- Lien vers l'inscription -->
                        <div class="text-center">
                            <p class="mb-0">
                                Pas encore de compte ? 
                                <a href="/defi_friends/auth/register.php" class="text-decoration-none fw-medium">
                                    Créer un compte
                                </a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informations supplémentaires -->
            <div class="text-center mt-4">
                <small class="text-muted">
                    En vous connectant, vous acceptez nos 
                    <a href="#" class="text-decoration-none">conditions d'utilisation</a> et notre 
                    <a href="#" class="text-decoration-none">politique de confidentialité</a>.
                </small>
            </div>

        </div>
    </div>
</div>

<!-- Scripts spécifiques à la page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
    
    // Focus sur le premier champ vide
    const emailInput = document.getElementById('email');
    const passwordInputField = document.getElementById('password');
    
    if (emailInput && emailInput.value === '') {
        emailInput.focus();
    } else if (passwordInputField && passwordInputField.value === '') {
        passwordInputField.focus();
    }
    
    // Validation en temps réel de l'email
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('input', function() {
            const email = this.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            if (email.length > 0) {
                if (isValid) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    }
    
    // Soumission du formulaire avec validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Désactiver le bouton de soumission pour éviter les doublons
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Connexion...';
                }
            }
            form.classList.add('was-validated');
        });
    }
    
    // Préchargement de la page suivante pour une navigation plus fluide
    const links = document.querySelectorAll('a[href*="/defi_friends/"]');
    links.forEach(link => {
        link.addEventListener('mouseenter', function() {
            const linkElement = document.createElement('link');
            linkElement.rel = 'prefetch';
            linkElement.href = this.href;
            document.head.appendChild(linkElement);
        }, { once: true });
    });
});

// Récupération automatique du focus en cas d'erreur
<?php if (!empty($errors)): ?>
setTimeout(function() {
    const firstInvalidField = document.querySelector('.is-invalid, input[value=""]');
    if (firstInvalidField) {
        firstInvalidField.focus();
        firstInvalidField.select();
    }
}, 100);
<?php endif; ?>
</script>

<style>
/* Styles spécifiques à la page de connexion */
.card {
    border-radius: 1rem !important;
}

.form-control-lg {
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Animation pour les erreurs */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.alert-danger {
    animation: shake 0.5s ease-in-out;
}

/* Style pour le bouton de basculement du mot de passe */
#togglePassword {
    border-left: none;
}

#togglePassword:hover {
    background-color: var(--bs-light);
}

/* Amélioration de l'accessibilité */
.form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(99, 27, 255, 0.25);
}

/* Loading state pour le bouton */
.btn:disabled {
    opacity: 0.8;
    cursor: not-allowed;
}

/* Responsive améliorations */
@media (max-width: 576px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .card-body {
        padding: 2rem 1.5rem !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>