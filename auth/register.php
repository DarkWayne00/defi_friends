<?php
// auth/register.php - Page d'inscription

require_once '../config/config.php';

// Rediriger si déjà connecté
requireGuest();

$pageTitle = "Inscription";
$errors = [];
$success = false;

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    // Vérification du rate limiting
    if (!rateLimitCheck('register', 3, 600)) {
        $errors[] = "Trop de tentatives d'inscription. Veuillez attendre 10 minutes.";
    } else {
        $pseudo = cleanInput($_POST['pseudo']);
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $nom = cleanInput($_POST['nom']);
        $prenom = cleanInput($_POST['prenom']);
        $accept_terms = isset($_POST['accept_terms']);
        
        // Validation du pseudo
        if (empty($pseudo)) {
            $errors[] = "Le pseudo est requis.";
        } elseif (strlen($pseudo) < 3 || strlen($pseudo) > 30) {
            $errors[] = "Le pseudo doit contenir entre 3 et 30 caractères.";
        } elseif (!validateUsername($pseudo)) {
            $errors[] = "Le pseudo ne peut contenir que des lettres, des chiffres, des tirets et des underscores.";
        } else {
            // Vérifier l'unicité du pseudo
            $existingPseudo = fetchOne("SELECT id FROM utilisateurs WHERE pseudo = ?", [$pseudo]);
            if ($existingPseudo) {
                $errors[] = "Ce pseudo est déjà utilisé.";
            }
        }
        
        // Validation de l'email
        if (empty($email)) {
            $errors[] = "L'email est requis.";
        } elseif (!isValidEmail($email)) {
            $errors[] = "Format d'email invalide.";
        } else {
            // Vérifier l'unicité de l'email
            $existingEmail = fetchOne("SELECT id FROM utilisateurs WHERE email = ?", [$email]);
            if ($existingEmail) {
                $errors[] = "Cette adresse email est déjà utilisée.";
            }
        }
        
        // Validation du mot de passe
        if (empty($password)) {
            $errors[] = "Le mot de passe est requis.";
        } elseif (!isPasswordSecure($password)) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.";
        }
        
        // Confirmation du mot de passe
        if ($password !== $password_confirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }
        
        // Validation du nom et prénom (optionnels mais avec contraintes)
        if (!empty($nom) && (strlen($nom) > 50 || !preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/', $nom))) {
            $errors[] = "Le nom ne doit contenir que des lettres, espaces, tirets et apostrophes (50 caractères max).";
        }
        
        if (!empty($prenom) && (strlen($prenom) > 50 || !preg_match('/^[a-zA-ZÀ-ÿ\s\-\']+$/', $prenom))) {
            $errors[] = "Le prénom ne doit contenir que des lettres, espaces, tirets et apostrophes (50 caractères max).";
        }
        
        // Acceptation des conditions
        if (!$accept_terms) {
            $errors[] = "Vous devez accepter les conditions d'utilisation.";
        }
        
        // Si pas d'erreurs, créer le compte
        if (empty($errors)) {
            try {
                $hashedPassword = hashPassword($password);
                
                $result = executeQuery(
                    "INSERT INTO utilisateurs (pseudo, email, mot_de_passe, nom, prenom, date_inscription) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$pseudo, $email, $hashedPassword, $nom ?: null, $prenom ?: null]
                );
                
                if ($result) {
                    $user_id = getLastInsertId();
                    
                    // Créer une notification de bienvenue
                    createNotification(
                        $user_id,
                        'nouveau_defi',
                        'Bienvenue sur ' . APP_NAME . ' !',
                        'Votre compte a été créé avec succès. Commencez par découvrir les défis disponibles et créer votre premier défi !',
                        ['url' => '/defi_friends/defis/index.php']
                    );
                    
                    $success = true;
                    
                    // Optionnel : connexion automatique après inscription
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['last_regeneration'] = time();
                    
                    $_SESSION['flash_message'] = "Compte créé avec succès ! Bienvenue sur " . APP_NAME . " !";
                    $_SESSION['flash_type'] = "success";
                    
                    // Redirection après inscription
                    redirect('/defi_friends/index.php');
                } else {
                    $errors[] = "Erreur lors de la création du compte. Veuillez réessayer.";
                }
            } catch (Exception $e) {
                logError('Erreur lors de l\'inscription', [
                    'pseudo' => $pseudo,
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "Une erreur inattendue s'est produite. Veuillez réessayer.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <!-- En-tête -->
            <div class="text-center mb-4">
                <div class="mb-4">
                    <i class="fas fa-user-plus fa-3x text-primary"></i>
                </div>
                <h1 class="h3 fw-bold">Créer un compte</h1>
                <p class="text-muted">Rejoignez la communauté <?= APP_NAME ?> et commencez à relever des défis !</p>
            </div>

            <!-- Formulaire d'inscription -->
            <div class="card border-0 shadow-lg rounded-3">
                <div class="card-body p-4">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php if (count($errors) === 1): ?>
                                <?= htmlspecialchars($errors[0]) ?>
                            <?php else: ?>
                                <strong>Veuillez corriger les erreurs suivantes :</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Pseudo -->
                        <div class="mb-3">
                            <label for="pseudo" class="form-label fw-medium">
                                <i class="fas fa-user me-2"></i>
                                Pseudo *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="pseudo" name="pseudo" 
                                   value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>" 
                                   required minlength="3" maxlength="30" 
                                   pattern="^[a-zA-Z0-9_-]+$" autocomplete="username" autofocus>
                            <div class="form-text">
                                Entre 3 et 30 caractères. Lettres, chiffres, tirets et underscores uniquement.
                            </div>
                            <div class="invalid-feedback">
                                Le pseudo doit contenir entre 3 et 30 caractères (lettres, chiffres, tirets et underscores uniquement).
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">
                                <i class="fas fa-envelope me-2"></i>
                                Adresse email *
                            </label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   required autocomplete="email">
                            <div class="invalid-feedback">
                                Veuillez saisir une adresse email valide.
                            </div>
                        </div>

                        <!-- Nom et Prénom -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prenom" class="form-label fw-medium">
                                        <i class="fas fa-id-card me-2"></i>
                                        Prénom
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="prenom" name="prenom" 
                                           value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" 
                                           maxlength="50" autocomplete="given-name">
                                    <div class="form-text">Optionnel</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label fw-medium">
                                        <i class="fas fa-id-card me-2"></i>
                                        Nom
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="nom" name="nom" 
                                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" 
                                           maxlength="50" autocomplete="family-name">
                                    <div class="form-text">Optionnel</div>
                                </div>
                            </div>
                        </div>

                        <!-- Mot de passe -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">
                                <i class="fas fa-lock me-2"></i>
                                Mot de passe *
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                       required autocomplete="new-password" data-strength="true">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" 
                                        aria-label="Afficher/masquer le mot de passe">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Au moins 8 caractères avec majuscule, minuscule, chiffre et caractère spécial.
                            </div>
                            <div class="invalid-feedback">
                                Le mot de passe ne respecte pas les critères de sécurité.
                            </div>
                        </div>

                        <!-- Confirmation du mot de passe -->
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label fw-medium">
                                <i class="fas fa-lock me-2"></i>
                                Confirmer le mot de passe *
                            </label>
                            <input type="password" class="form-control form-control-lg" id="password_confirm" name="password_confirm" 
                                   required autocomplete="new-password">
                            <div class="invalid-feedback">
                                Les mots de passe ne correspondent pas.
                            </div>
                        </div>

                        <!-- Acceptation des conditions -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" required>
                                <label class="form-check-label" for="accept_terms">
                                    J'accepte les <a href="#" class="text-decoration-none" target="_blank">conditions d'utilisation</a> 
                                    et la <a href="#" class="text-decoration-none" target="_blank">politique de confidentialité</a> *
                                </label>
                                <div class="invalid-feedback">
                                    Vous devez accepter les conditions d'utilisation.
                                </div>
                            </div>
                        </div>

                        <!-- Bouton d'inscription -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>
                                Créer mon compte
                            </button>
                        </div>

                        <!-- Lien vers la connexion -->
                        <div class="text-center">
                            <p class="mb-0">
                                Déjà un compte ? 
                                <a href="/defi_friends/auth/login.php" class="text-decoration-none fw-medium">
                                    Se connecter
                                </a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Avantages de l'inscription -->
            <div class="row g-3 mt-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="mb-2">
                            <i class="fas fa-trophy fa-2x text-primary"></i>
                        </div>
                        <h6 class="fw-bold">Créez des défis</h6>
                        <small class="text-muted">Proposez vos propres défis à la communauté</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="mb-2">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                        <h6 class="fw-bold">Connectez-vous</h6>
                        <small class="text-muted">Ajoutez des amis et suivez leurs activités</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="mb-2">
                            <i class="fas fa-medal fa-2x text-primary"></i>
                        </div>
                        <h6 class="fw-bold">Gagnez des badges</h6>
                        <small class="text-muted">Débloquez des récompenses en participant</small>
                    </div>
                </div>
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
    
    // Validation en temps réel du pseudo
    const pseudoField = document.getElementById('pseudo');
    if (pseudoField) {
        pseudoField.addEventListener('input', function() {
            const pseudo = this.value;
            const isValid = /^[a-zA-Z0-9_-]{3,30}$/.test(pseudo);
            
            if (pseudo.length > 0) {
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
    
    // Validation de la confirmation du mot de passe
    const passwordConfirmField = document.getElementById('password_confirm');
    if (passwordConfirmField && passwordInput) {
        function validatePasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = passwordConfirmField.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    passwordConfirmField.classList.remove('is-invalid');
                    passwordConfirmField.classList.add('is-valid');
                } else {
                    passwordConfirmField.classList.remove('is-valid');
                    passwordConfirmField.classList.add('is-invalid');
                }
            } else {
                passwordConfirmField.classList.remove('is-valid', 'is-invalid');
            }
        }
        
        passwordInput.addEventListener('input', validatePasswordMatch);
        passwordConfirmField.addEventListener('input', validatePasswordMatch);
    }
    
    // Validation du nom et prénom
    const nameFields = ['nom', 'prenom'];
    nameFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('input', function() {
                const value = this.value;
                const isValid = /^[a-zA-ZÀ-ÿ\s\-']*$/.test(value) && value.length <= 50;
                
                if (value.length > 0) {
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
    });
    
    // Soumission du formulaire avec validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Scroll vers le premier champ invalide
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            } else {
                // Désactiver le bouton pour éviter les soumissions multiples
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Création du compte...';
                }
            }
            form.classList.add('was-validated');
        });
    }
    
    // Vérification de la disponibilité du pseudo (optionnel)
    let pseudoCheckTimeout;
    if (pseudoField) {
        pseudoField.addEventListener('input', function() {
            const pseudo = this.value;
            
            clearTimeout(pseudoCheckTimeout);
            
            if (pseudo.length >= 3 && /^[a-zA-Z0-9_-]+$/.test(pseudo)) {
                pseudoCheckTimeout = setTimeout(() => {
                    // Ici vous pourriez ajouter une vérification AJAX de la disponibilité
                    // du pseudo en temps réel
                }, 500);
            }
        });
    }
});

// Amélioration de l'expérience utilisateur
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});
</script>

<style>
/* Styles spécifiques à la page d'inscription */
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

/* Style pour le focus des champs */
.focused {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
}

/* Amélioration visuelle des validations */
.form-control.is-valid {
    border-color: #198754;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.86.86a.9.9 0 0 0 1.28 0l2.5-2.5a.9.9 0 0 0-1.28-1.27L4 5.18l-.69-.69a.9.9 0 0 0-1.28 1.27z'/%3e%3c/svg%3e");
}

.form-control.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 5.8 4.4 4.4M10.2 5.8 5.8 10.2'/%3e%3c/svg%3e");
}

/* Style pour le bouton de basculement du mot de passe */
#togglePassword {
    border-left: none;
}

#togglePassword:hover {
    background-color: var(--bs-light);
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
    
    .col-md-6 {
        margin-bottom: 1rem;
    }
}

/* Amélioration de l'accessibilité */
.form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(99, 27, 255, 0.25);
}

/* Animation d'entrée */
.card {
    animation: slideInUp 0.6s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php include '../includes/footer.php'; ?>