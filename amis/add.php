<?php
// amis/add.php - Page pour ajouter des amis

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Ajouter un ami";
$currentUser = getCurrentUser();
$user_id = $currentUser['id'];
$errors = [];
$success_message = '';

// Traitement de l'ajout d'ami
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'search') {
        // Recherche d'utilisateurs
        $search_term = cleanInput($_POST['search_term'] ?? '');
        
        if (empty($search_term)) {
            $errors[] = "Veuillez saisir un terme de recherche.";
        } elseif (strlen($search_term) < 2) {
            $errors[] = "Le terme de recherche doit contenir au moins 2 caractères.";
        }
    } elseif ($action === 'add_friend') {
        // Envoi d'une demande d'amitié
        $friend_id = intval($_POST['friend_id'] ?? 0);
        
        if (!$friend_id) {
            $errors[] = "Utilisateur invalide.";
        } elseif ($friend_id === $user_id) {
            $errors[] = "Vous ne pouvez pas vous ajouter vous-même comme ami.";
        } else {
            // Vérifier que l'utilisateur existe
            $friend = fetchOne("SELECT id, pseudo FROM utilisateurs WHERE id = ? AND statut = 'actif'", [$friend_id]);
            
            if (!$friend) {
                $errors[] = "Utilisateur non trouvé.";
            } else {
                // Vérifier s'il n'y a pas déjà une relation
                $existing = getFriendshipStatus($user_id, $friend_id);
                
                if ($existing) {
                    switch ($existing['statut']) {
                        case 'accepte':
                            $errors[] = "Vous êtes déjà amis avec " . htmlspecialchars($friend['pseudo']) . ".";
                            break;
                        case 'en_attente':
                            if ($existing['demandeur_id'] == $user_id) {
                                $errors[] = "Vous avez déjà envoyé une demande d'amitié à " . htmlspecialchars($friend['pseudo']) . ".";
                            } else {
                                $errors[] = htmlspecialchars($friend['pseudo']) . " vous a déjà envoyé une demande d'amitié. Consultez vos demandes reçues.";
                            }
                            break;
                        case 'refuse':
                            $errors[] = "Une demande d'amitié avec " . htmlspecialchars($friend['pseudo']) . " a été refusée.";
                            break;
                    }
                } else {
                    // Envoyer la demande d'amitié
                    $result = sendFriendRequest($user_id, $friend_id);
                    
                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $errors[] = $result['message'];
                    }
                }
            }
        }
    }
}

// Recherche d'utilisateurs
$search_results = [];
if (isset($_POST['search_term']) && !empty($_POST['search_term']) && empty($errors)) {
    $search_term = '%' . cleanInput($_POST['search_term']) . '%';
    
    $search_results = executeQuery(
        "SELECT u.id, u.pseudo, u.photo_profil, u.date_inscription,
                (SELECT COUNT(*) FROM defis WHERE createur_id = u.id AND statut = 'actif') as nb_defis,
                (SELECT COUNT(*) FROM participations WHERE utilisateur_id = u.id AND statut = 'complete') as nb_defis_termines,
                a.statut as friendship_status, a.demandeur_id
         FROM utilisateurs u
         LEFT JOIN amis a ON ((a.utilisateur_id = u.id AND a.ami_id = ?) OR (a.utilisateur_id = ? AND a.ami_id = u.id))
         WHERE (u.pseudo LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?) 
         AND u.id != ? AND u.statut = 'actif'
         ORDER BY u.pseudo ASC
         LIMIT 20",
        [$user_id, $user_id, $search_term, $search_term, $search_term, $user_id]
    );
}

// Suggestions d'amis (utilisateurs populaires ou récents)
$suggestions = executeQuery(
    "SELECT u.id, u.pseudo, u.photo_profil, u.date_inscription,
            (SELECT COUNT(*) FROM defis WHERE createur_id = u.id AND statut = 'actif') as nb_defis,
            (SELECT COUNT(*) FROM participations WHERE utilisateur_id = u.id AND statut = 'complete') as nb_defis_termines
     FROM utilisateurs u
     WHERE u.id != ? AND u.statut = 'actif'
     AND u.id NOT IN (
         SELECT CASE WHEN utilisateur_id = ? THEN ami_id ELSE utilisateur_id END
         FROM amis WHERE (utilisateur_id = ? OR ami_id = ?) AND statut IN ('accepte', 'en_attente')
     )
     ORDER BY (
         SELECT COUNT(*) FROM defis WHERE createur_id = u.id AND statut = 'actif'
     ) DESC, u.date_inscription DESC
     LIMIT 6",
    [$user_id, $user_id, $user_id, $user_id]
);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Mes amis', 'url' => '/defi_friends/amis/index.php'],
    ['label' => 'Ajouter un ami']
];

include '../includes/header.php';
?>

<div class="container">
    
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-user-plus me-2 text-primary"></i>
                Ajouter un ami
            </h1>
            <p class="text-muted">Recherchez des utilisateurs et envoyez-leur des demandes d'amitié.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="/defi_friends/amis/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Retour à mes amis
            </a>
        </div>
    </div>

    <!-- Messages -->
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

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <!-- Formulaire de recherche -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-search me-2"></i>
                Rechercher des utilisateurs
            </h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="search">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="search_term" class="form-label">Nom d'utilisateur, nom ou prénom</label>
                        <input type="text" class="form-control form-control-lg" id="search_term" name="search_term" 
                               value="<?= htmlspecialchars($_POST['search_term'] ?? '') ?>" 
                               placeholder="Tapez un nom d'utilisateur..." 
                               required minlength="2" autofocus>
                        <div class="form-text">
                            Saisissez au moins 2 caractères pour effectuer une recherche.
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-search me-2"></i>
                            Rechercher
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Résultats de recherche -->
    <?php if (!empty($search_results)): ?>
        <div class="mb-4">
            <h4 class="mb-3">
                <i class="fas fa-search me-2"></i>
                Résultats de recherche (<?= count($search_results) ?>)
            </h4>
            
            <div class="row g-4">
                <?php foreach ($search_results as $user): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?= getUserAvatar($user['photo_profil'], $user['pseudo'], 60) ?>" 
                                         alt="Avatar" class="rounded-circle me-3" width="60" height="60">
                                    <div class="flex-grow-1">
                                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['pseudo']) ?></h5>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Membre depuis <?= formatDate($user['date_inscription']) ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Statistiques -->
                                <div class="row g-2 mb-3 small text-muted">
                                    <div class="col-6">
                                        <i class="fas fa-trophy me-1"></i>
                                        <?= $user['nb_defis'] ?> défis créés
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?= $user['nb_defis_termines'] ?> terminés
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="d-flex gap-2">
                                    <a href="/defi_friends/profile.php?user_id=<?= $user['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary flex-fill">
                                        <i class="fas fa-user me-1"></i>
                                        Voir profil
                                    </a>
                                    
                                    <?php if ($user['friendship_status'] === null): ?>
                                        <!-- Pas de relation -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="add_friend">
                                            <input type="hidden" name="friend_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-user-plus me-1"></i>
                                                Ajouter
                                            </button>
                                        </form>
                                    <?php elseif ($user['friendship_status'] === 'en_attente'): ?>
                                        <!-- Demande en attente -->
                                        <?php if ($user['demandeur_id'] == $user_id): ?>
                                            <span class="btn btn-sm btn-warning disabled">
                                                <i class="fas fa-clock me-1"></i>
                                                En attente
                                            </span>
                                        <?php else: ?>
                                            <a href="/defi_friends/amis/requests.php" class="btn btn-sm btn-info">
                                                <i class="fas fa-inbox me-1"></i>
                                                Répondre
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($user['friendship_status'] === 'accepte'): ?>
                                        <!-- Déjà amis -->
                                        <span class="btn btn-sm btn-success disabled">
                                            <i class="fas fa-check me-1"></i>
                                            Amis
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif (isset($_POST['search_term']) && empty($errors)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4>Aucun résultat trouvé</h4>
            <p class="text-muted">Aucun utilisateur ne correspond à votre recherche.</p>
        </div>
    <?php endif; ?>

    <!-- Suggestions d'amis -->
    <?php if (!empty($suggestions) && empty($_POST['search_term'])): ?>
        <div class="mb-4">
            <h4 class="mb-3">
                <i class="fas fa-lightbulb me-2"></i>
                Suggestions d'amis
            </h4>
            <p class="text-muted mb-4">Découvrez des utilisateurs actifs de la communauté</p>
            
            <div class="row g-4">
                <?php foreach ($suggestions as $user): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 text-center">
                            <div class="card-body p-4">
                                <img src="<?= getUserAvatar($user['photo_profil'], $user['pseudo'], 80) ?>" 
                                     alt="Avatar" class="rounded-circle mb-3" width="80" height="80">
                                
                                <h5 class="fw-bold mb-2"><?= htmlspecialchars($user['pseudo']) ?></h5>
                                
                                <!-- Statistiques -->
                                <div class="row g-2 mb-3 small text-muted">
                                    <div class="col-6">
                                        <div class="fw-bold"><?= $user['nb_defis'] ?></div>
                                        <div>Défis</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold"><?= $user['nb_defis_termines'] ?></div>
                                        <div>Terminés</div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="add_friend">
                                        <input type="hidden" name="friend_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-user-plus me-1"></i>
                                            Ajouter
                                        </button>
                                    </form>
                                    <a href="/defi_friends/profile.php?user_id=<?= $user['id'] ?>" 
                                       class="btn btn-outline-secondary">
                                        <i class="fas fa-eye me-1"></i>
                                        Voir profil
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Conseils -->
    <div class="card border-0 bg-light">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3">
                <i class="fas fa-info-circle me-2 text-primary"></i>
                Conseils pour trouver des amis
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-search text-primary me-2 mt-1"></i>
                        <div>
                            <strong>Recherchez précisément</strong><br>
                            <small class="text-muted">Utilisez le pseudo exact ou le nom complet</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-users text-primary me-2 mt-1"></i>
                        <div>
                            <strong>Explorez les défis</strong><br>
                            <small class="text-muted">Découvrez des utilisateurs dans les défis</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-heart text-primary me-2 mt-1"></i>
                        <div>
                            <strong>Soyez actif</strong><br>
                            <small class="text-muted">Participez pour être découvert</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus sur le champ de recherche
    const searchInput = document.getElementById('search_term');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
    
    // Validation en temps réel
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const value = this.value.trim();
            const submitBtn = document.querySelector('button[type="submit"]');
            
            if (value.length >= 2) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                submitBtn.disabled = false;
            } else if (value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                submitBtn.disabled = true;
            } else {
                this.classList.remove('is-valid', 'is-invalid');
                submitBtn.disabled = false;
            }
        });
    }
    
    // Confirmation avant envoi de demande d'amitié
    const addFriendForms = document.querySelectorAll('form input[name="action"][value="add_friend"]');
    addFriendForms.forEach(input => {
        const form = input.closest('form');
        const button = form.querySelector('button[type="submit"]');
        const userName = form.closest('.card').querySelector('h5').textContent;
        
        form.addEventListener('submit', function(e) {
            if (!confirm(`Envoyer une demande d'amitié à ${userName} ?`)) {
                e.preventDefault();
                return false;
            }
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Envoi...';
        });
    });
});
</script>

<style>
/* Styles pour la page d'ajout d'amis */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

/* Animation pour les résultats de recherche */
.row.g-4 .col-md-6,
.row.g-4 .col-md-4 {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Style pour les boutons d'état */
.btn.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Amélioration responsive */
@media (max-width: 768px) {
    .d-flex.gap-2 {
        flex-direction: column;
    }
    
    .card-body {
        padding: 1rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>