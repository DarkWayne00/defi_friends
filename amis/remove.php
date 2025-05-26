<?php
// amis/remove.php - Page pour supprimer un ami

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Supprimer un ami";
$currentUser = getCurrentUser();
$user_id = $currentUser['id'];
$errors = [];

// Récupérer l'ID de l'ami à supprimer
$friend_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$friend_id) {
    $_SESSION['flash_message'] = "Ami non spécifié.";
    $_SESSION['flash_type'] = "danger";
    redirect('/defi_friends/amis/index.php');
}

// Vérifier que l'ami existe et qu'il y a bien une relation d'amitié
$friendship = fetchOne(
    "SELECT a.*, u.pseudo, u.photo_profil 
     FROM amis a
     JOIN utilisateurs u ON (
        CASE 
            WHEN a.utilisateur_id = ? THEN u.id = a.ami_id
            ELSE u.id = a.utilisateur_id
        END
     )
     WHERE ((a.utilisateur_id = ? AND a.ami_id = ?) OR (a.utilisateur_id = ? AND a.ami_id = ?)) 
     AND a.statut = 'accepte'",
    [$user_id, $user_id, $friend_id, $friend_id, $user_id]
);

if (!$friendship) {
    $_SESSION['flash_message'] = "Cette personne n'est pas dans votre liste d'amis.";
    $_SESSION['flash_type'] = "danger";
    redirect('/defi_friends/amis/index.php');
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'remove') {
        try {
            // Supprimer toutes les relations d'amitié entre les deux utilisateurs
            $result = executeQuery(
                "DELETE FROM amis 
                 WHERE ((utilisateur_id = ? AND ami_id = ?) OR (utilisateur_id = ? AND ami_id = ?)) 
                 AND statut = 'accepte'",
                [$user_id, $friend_id, $friend_id, $user_id]
            );
            
            if ($result > 0) {
                // Créer une notification pour l'ancien ami
                createNotification(
                    $friend_id,
                    'amitie',
                    'Suppression d\'amitié',
                    $currentUser['pseudo'] . ' vous a retiré de sa liste d\'amis.',
                    null,
                    $user_id
                );
                
                $_SESSION['flash_message'] = htmlspecialchars($friendship['pseudo']) . " a été supprimé de votre liste d'amis.";
                $_SESSION['flash_type'] = "success";
                
                redirect('/defi_friends/amis/index.php');
            } else {
                $errors[] = "Erreur lors de la suppression de l'ami.";
            }
        } catch (Exception $e) {
            logError('Erreur lors de la suppression d\'ami', [
                'user_id' => $user_id,
                'friend_id' => $friend_id,
                'error' => $e->getMessage()
            ]);
            $errors[] = "Une erreur inattendue s'est produite.";
        }
    }
}

// Récupérer les statistiques de l'ami
$friendStats = getUserStats($friend_id);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Mes amis', 'url' => '/defi_friends/amis/index.php'],
    ['label' => 'Supprimer un ami']
];

include '../includes/header.php';
?>

<div class="container">
    
    <div class="row justify-content-center">
        <div class="col-lg-6">
            
            <!-- En-tête -->
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="fas fa-user-times fa-3x text-danger"></i>
                </div>
                <h1 class="h2 fw-bold">Supprimer un ami</h1>
                <p class="text-muted">Cette action est définitive et ne peut pas être annulée.</p>
            </div>

            <!-- Messages d'erreur -->
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

            <!-- Informations sur l'ami -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body text-center p-4">
                    <img src="<?= getUserAvatar($friendship['photo_profil'], $friendship['pseudo'], 100) ?>" 
                         alt="Avatar" class="rounded-circle mb-3" width="100" height="100">
                    
                    <h4 class="fw-bold mb-3"><?= htmlspecialchars($friendship['pseudo']) ?></h4>
                    
                    <!-- Statistiques de l'ami -->
                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h5 mb-0 text-primary"><?= $friendStats['defis_crees'] ?></div>
                                <small class="text-muted">Défis créés</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h5 mb-0 text-success"><?= $friendStats['participations_terminees'] ?></div>
                                <small class="text-muted">Défis terminés</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h5 mb-0 text-info"><?= $friendStats['nb_amis'] ?></div>
                                <small class="text-muted">Amis</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="badge bg-success rounded-pill">
                        <i class="fas fa-check me-1"></i>
                        Ami depuis <?= formatDate($friendship['date_demande']) ?>
                    </div>
                </div>
            </div>

            <!-- Conséquences de la suppression -->
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-warning border-3">
                <div class="card-body">
                    <h6 class="fw-bold text-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Conséquences de cette action
                    </h6>
                    <ul class="mb-0">
                        <li class="mb-2">
                            <strong><?= htmlspecialchars($friendship['pseudo']) ?></strong> sera retiré de votre liste d'amis
                        </li>
                        <li class="mb-2">
                            Vous n'apparaîtrez plus dans sa liste d'amis
                        </li>
                        <li class="mb-2">
                            Vous ne verrez plus ses activités dans votre fil d'actualité
                        </li>
                        <li class="mb-2">
                            Il/elle ne verra plus vos activités dans son fil
                        </li>
                        <li>
                            Vous pourrez toujours vous renvoyer des demandes d'amitié plus tard
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Formulaire de confirmation -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-trash me-2"></i>
                        Confirmation de suppression
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention :</strong> Cette action est définitive. Êtes-vous sûr de vouloir supprimer 
                        <strong><?= htmlspecialchars($friendship['pseudo']) ?></strong> de votre liste d'amis ?
                    </div>
                    
                    <form method="POST" id="remove-form">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="remove">
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="confirm-removal" required>
                            <label class="form-check-label" for="confirm-removal">
                                Je confirme vouloir supprimer cet ami de ma liste
                            </label>
                            <div class="invalid-feedback">
                                Vous devez confirmer pour continuer.
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-danger btn-lg flex-fill" id="confirm-btn" disabled>
                                <i class="fas fa-user-times me-2"></i>
                                Supprimer définitivement
                            </button>
                            <a href="/defi_friends/amis/index.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirm-removal');
    const confirmBtn = document.getElementById('confirm-btn');
    const form = document.getElementById('remove-form');
    
    // Gestion de l'activation du bouton
    if (confirmCheckbox && confirmBtn) {
        confirmCheckbox.addEventListener('change', function() {
            confirmBtn.disabled = !this.checked;
            
            if (this.checked) {
                confirmBtn.classList.remove('btn-outline-danger');
                confirmBtn.classList.add('btn-danger');
            } else {
                confirmBtn.classList.remove('btn-danger');
                confirmBtn.classList.add('btn-outline-danger');
            }
        });
    }
    
    // Confirmation finale avant soumission
    if (form) {
        form.addEventListener('submit', function(event) {
            const friendName = <?= escapeJs($friendship['pseudo']) ?>;
            
            if (!confirm(`Êtes-vous vraiment sûr de vouloir supprimer ${friendName} de votre liste d'amis ?\n\nCette action est définitive.`)) {
                event.preventDefault();
                return;
            }
            
            // Désactiver le bouton pour éviter les soumissions multiples
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Suppression...';
        });
    }
    
    // Animation d'entrée
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });
});
</script>

<style>
/* Styles pour la page de suppression d'ami */
.border-start.border-warning {
    border-width: 4px !important;
}

/* Animation du bouton de danger */
.btn-danger {
    background: linear-gradient(135deg, var(--bs-danger), #b02a37);
    border: none;
    transition: all 0.3s ease;
}

.btn-danger:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.btn-danger:disabled {
    opacity: 0.6;
    transform: none;
}

/* Style pour la checkbox de confirmation */
.form-check-input:checked {
    background-color: var(--bs-danger);
    border-color: var(--bs-danger);
}

/* Animation pour les alertes */
.alert {
    animation: slideInDown 0.5s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .d-flex.gap-3 {
        flex-direction: column;
    }
    
    .btn-lg {
        padding: 0.75rem 1rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>