<?php
// amis/requests.php - Page de gestion des demandes d'amitié

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$pageTitle = "Demandes d'amitié";
$currentUser = getCurrentUser();
$user_id = $currentUser['id'];
$errors = [];

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $action = $_POST['action'] ?? '';
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    
    if ($action === 'accept' && $request_id) {
        // Accepter une demande d'amitié
        $request = fetchOne(
            "SELECT a.*, u.pseudo 
             FROM amis a 
             JOIN utilisateurs u ON a.utilisateur_id = u.id 
             WHERE a.id = ? AND a.ami_id = ? AND a.statut = 'en_attente'",
            [$request_id, $user_id]
        );
        
        if ($request) {
            try {
                // Mettre à jour le statut de la demande
                $result = executeQuery(
                    "UPDATE amis SET statut = 'accepte', date_reponse = NOW() WHERE id = ?",
                    [$request_id]
                );
                
                if ($result > 0) {
                    // Créer la relation inverse
                    executeQuery(
                        "INSERT IGNORE INTO amis (utilisateur_id, ami_id, statut, date_demande, date_reponse, demandeur_id) 
                         VALUES (?, ?, 'accepte', NOW(), NOW(), ?)",
                        [$user_id, $request['utilisateur_id'], $request['utilisateur_id']]
                    );
                    
                    // Notification d'acceptation
                    createNotification(
                        $request['utilisateur_id'],
                        'amitie',
                        'Demande d\'amitié acceptée',
                        $currentUser['pseudo'] . ' a accepté votre demande d\'amitié !',
                        ['url' => '/defi_friends/amis/index.php'],
                        $user_id
                    );
                    
                    $_SESSION['flash_message'] = "Demande d'amitié de " . htmlspecialchars($request['pseudo']) . " acceptée !";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $errors[] = "Erreur lors de l'acceptation de la demande.";
                }
            } catch (Exception $e) {
                logError('Erreur lors de l\'acceptation de demande d\'amitié', [
                    'user_id' => $user_id,
                    'request_id' => $request_id,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "Une erreur inattendue s'est produite.";
            }
        } else {
            $errors[] = "Demande d'amitié non trouvée ou déjà traitée.";
        }
    } elseif ($action === 'reject' && $request_id) {
        // Refuser une demande d'amitié
        $request = fetchOne(
            "SELECT a.*, u.pseudo 
             FROM amis a 
             JOIN utilisateurs u ON a.utilisateur_id = u.id 
             WHERE a.id = ? AND a.ami_id = ? AND a.statut = 'en_attente'",
            [$request_id, $user_id]
        );
        
        if ($request) {
            try {
                $result = executeQuery(
                    "UPDATE amis SET statut = 'refuse', date_reponse = NOW() WHERE id = ?",
                    [$request_id]
                );
                
                if ($result > 0) {
                    $_SESSION['flash_message'] = "Demande d'amitié de " . htmlspecialchars($request['pseudo']) . " refusée.";
                    $_SESSION['flash_type'] = "info";
                } else {
                    $errors[] = "Erreur lors du refus de la demande.";
                }
            } catch (Exception $e) {
                logError('Erreur lors du refus de demande d\'amitié', [
                    'user_id' => $user_id,
                    'request_id' => $request_id,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "Une erreur inattendue s'est produite.";
            }
        } else {
            $errors[] = "Demande d'amitié non trouvée ou déjà traitée.";
        }
    } elseif ($action === 'accept_all') {
        // Accepter toutes les demandes en attente
        try {
            $requests = executeQuery(
                "SELECT id, utilisateur_id FROM amis WHERE ami_id = ? AND statut = 'en_attente'",
                [$user_id]
            );
            
            $accepted_count = 0;
            foreach ($requests as $request) {
                // Accepter la demande
                $result = executeQuery(
                    "UPDATE amis SET statut = 'accepte', date_reponse = NOW() WHERE id = ?",
                    [$request['id']]
                );
                
                if ($result > 0) {
                    // Créer la relation inverse
                    executeQuery(
                        "INSERT IGNORE INTO amis (utilisateur_id, ami_id, statut, date_demande, date_reponse, demandeur_id) 
                         VALUES (?, ?, 'accepte', NOW(), NOW(), ?)",
                        [$user_id, $request['utilisateur_id'], $request['utilisateur_id']]
                    );
                    
                    // Notification
                    createNotification(
                        $request['utilisateur_id'],
                        'amitie',
                        'Demande d\'amitié acceptée',
                        $currentUser['pseudo'] . ' a accepté votre demande d\'amitié !',
                        ['url' => '/defi_friends/amis/index.php'],
                        $user_id
                    );
                    
                    $accepted_count++;
                }
            }
            
            $_SESSION['flash_message'] = "$accepted_count demande(s) d'amitié acceptée(s) !";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            logError('Erreur lors de l\'acceptation de toutes les demandes', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            $errors[] = "Erreur lors de l'acceptation de toutes les demandes.";
        }
    } elseif ($action === 'reject_all') {
        // Refuser toutes les demandes en attente
        try {
            $result = executeQuery(
                "UPDATE amis SET statut = 'refuse', date_reponse = NOW() 
                 WHERE ami_id = ? AND statut = 'en_attente'",
                [$user_id]
            );
            
            $_SESSION['flash_message'] = "$result demande(s) d'amitié refusée(s).";
            $_SESSION['flash_type'] = "info";
        } catch (Exception $e) {
            logError('Erreur lors du refus de toutes les demandes', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            $errors[] = "Erreur lors du refus de toutes les demandes.";
        }
    }
    
    redirect('/defi_friends/amis/requests.php');
}

// Récupérer les demandes d'amitié en attente
$demandes_recues = executeQuery(
    "SELECT a.id as request_id, a.date_demande, a.demandeur_id,
            u.id, u.pseudo, u.photo_profil, u.date_inscription,
            (SELECT COUNT(*) FROM defis WHERE createur_id = u.id AND statut = 'actif') as nb_defis,
            (SELECT COUNT(*) FROM participations WHERE utilisateur_id = u.id AND statut = 'complete') as nb_defis_termines,
            (SELECT COUNT(*) FROM amis WHERE (utilisateur_id = u.id OR ami_id = u.id) AND statut = 'accepte') as nb_amis
     FROM amis a
     JOIN utilisateurs u ON a.utilisateur_id = u.id
     WHERE a.ami_id = ? AND a.statut = 'en_attente'
     ORDER BY a.date_demande DESC",
    [$user_id]
);

// Récupérer les demandes envoyées
$demandes_envoyees = executeQuery(
    "SELECT a.id as request_id, a.date_demande,
            u.id, u.pseudo, u.photo_profil
     FROM amis a
     JOIN utilisateurs u ON a.ami_id = u.id
     WHERE a.utilisateur_id = ? AND a.statut = 'en_attente'
     ORDER BY a.date_demande DESC",
    [$user_id]
);

// Statistiques
$stats = [
    'demandes_recues' => count($demandes_recues),
    'demandes_envoyees' => count($demandes_envoyees)
];

// Breadcrumb
$breadcrumb = [
    ['label' => 'Mes amis', 'url' => '/defi_friends/amis/index.php'],
    ['label' => 'Demandes d\'amitié']
];

include '../includes/header.php';
?>

<div class="container">
    
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-inbox me-2 text-primary"></i>
                Demandes d'amitié
            </h1>
            <p class="text-muted">Gérez vos demandes d'amitié reçues et envoyées.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="/defi_friends/amis/add.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>
                Ajouter un ami
            </a>
        </div>
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

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-success"><?= $stats['demandes_recues'] ?></div>
                    <small class="text-muted">Demandes reçues</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-1 text-warning"><?= $stats['demandes_envoyees'] ?></div>
                    <small class="text-muted">Demandes envoyées</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation par onglets -->
    <ul class="nav nav-tabs mb-4" id="requestsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" 
                    type="button" role="tab">
                <i class="fas fa-inbox me-2"></i>
                Demandes reçues 
                <?php if ($stats['demandes_recues'] > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $stats['demandes_recues'] ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" 
                    type="button" role="tab">
                <i class="fas fa-paper-plane me-2"></i>
                Demandes envoyées (<?= $stats['demandes_envoyees'] ?>)
            </button>
        </li>
    </ul>

    <!-- Contenu des onglets -->
    <div class="tab-content" id="requestsTabsContent">
        
        <!-- Onglet Demandes reçues -->
        <div class="tab-pane fade show active" id="received" role="tabpanel" aria-labelledby="received-tab">
            
            <?php if (empty($demandes_recues)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h4>Aucune demande d'amitié</h4>
                    <p class="text-muted">Vous n'avez pas de nouvelles demandes d'amitié en attente.</p>
                    <a href="/defi_friends/amis/add.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Rechercher des amis
                    </a>
                </div>
            <?php else: ?>
                <!-- Actions en lot -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <?= count($demandes_recues) ?> demande<?= count($demandes_recues) > 1 ? 's' : '' ?> en attente
                    </h5>
                    <div class="btn-group">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Accepter toutes les demandes ?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="accept_all">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-check-double me-1"></i>
                                Tout accepter
                            </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Refuser toutes les demandes ?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="reject_all">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times me-1"></i>
                                Tout refuser
                            </button>
                        </form>
                    </div>
                </div>

                <div class="row g-4">
                    <?php foreach ($demandes_recues as $demande): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <img src="<?= getUserAvatar($demande['photo_profil'], $demande['pseudo'], 60) ?>" 
                                             alt="Avatar" class="rounded-circle me-3" width="60" height="60">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($demande['pseudo']) ?></h5>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Demande envoyée <?= formatDate($demande['date_demande']) ?>
                                            </small>
                                            <div class="mt-1">
                                                <small class="text-muted">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    Membre depuis <?= formatDate($demande['date_inscription']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Statistiques du demandeur -->
                                    <div class="row g-2 mb-3 small text-muted">
                                        <div class="col-4 text-center">
                                            <div class="fw-bold"><?= $demande['nb_defis'] ?></div>
                                            <div>Défis</div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <div class="fw-bold"><?= $demande['nb_defis_termines'] ?></div>
                                            <div>Terminés</div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <div class="fw-bold"><?= $demande['nb_amis'] ?></div>
                                            <div>Amis</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="d-flex gap-2 mb-2">
                                        <form method="POST" class="flex-fill">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="request_id" value="<?= $demande['request_id'] ?>">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fas fa-check me-1"></i>
                                                Accepter
                                            </button>
                                        </form>
                                        <form method="POST" class="flex-fill">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?= $demande['request_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger w-100">
                                                <i class="fas fa-times me-1"></i>
                                                Refuser
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <a href="/defi_friends/profile.php?user_id=<?= $demande['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-user me-1"></i>
                                            Voir le profil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Onglet Demandes envoyées -->
        <div class="tab-pane fade" id="sent" role="tabpanel" aria-labelledby="sent-tab">
            
            <?php if (empty($demandes_envoyees)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                    <h4>Aucune demande en attente</h4>
                    <p class="text-muted">Vous n'avez pas de demandes d'amitié en attente de réponse.</p>
                    <a href="/defi_friends/amis/add.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Envoyer des demandes
                    </a>
                </div>
            <?php else: ?>
                <h5 class="mb-4">
                    <?= count($demandes_envoyees) ?> demande<?= count($demandes_envoyees) > 1 ? 's' : '' ?> en attente de réponse
                </h5>
                
                <div class="row g-4">
                    <?php foreach ($demandes_envoyees as $demande): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= getUserAvatar($demande['photo_profil'], $demande['pseudo'], 60) ?>" 
                                             alt="Avatar" class="rounded-circle me-3" width="60" height="60">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($demande['pseudo']) ?></h5>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Envoyée <?= formatDate($demande['date_demande']) ?>
                                            </small>
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-hourglass-half me-1"></i>
                                                    En attente de réponse
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <a href="/defi_friends/profile.php?user_id=<?= $demande['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary w-100">
                                            <i class="fas fa-user me-1"></i>
                                            Voir le profil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des formulaires d'action
    const actionForms = document.querySelectorAll('form[method="POST"]');
    
    actionForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const action = form.querySelector('input[name="action"]').value;
            const button = form.querySelector('button[type="submit"]');
            
            // Personnaliser les messages de confirmation selon l'action
            let confirmMessage = '';
            switch (action) {
                case 'accept':
                    confirmMessage = 'Accepter cette demande d\'amitié ?';
                    break;
                case 'reject':
                    confirmMessage = 'Refuser cette demande d\'amitié ?';
                    break;
                case 'accept_all':
                    confirmMessage = 'Accepter toutes les demandes d\'amitié ?';
                    break;
                case 'reject_all':
                    confirmMessage = 'Refuser toutes les demandes d\'amitié ?';
                    break;
            }
            
            if (confirmMessage && !confirm(confirmMessage)) {
                event.preventDefault();
                return;
            }
            
            // Désactiver le bouton et afficher un indicateur de chargement
            if (button) {
                button.disabled = true;
                const originalText = button.innerHTML;
                
                if (action.includes('accept')) {
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Acceptation...';
                } else if (action.includes('reject')) {
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Refus...';
                }
            }
        });
    });
    
    // Animation d'entrée pour les cartes
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<style>
/* Styles pour la page de demandes d'amitié */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

/* Style pour les badges de statut */
.badge.bg-warning {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
    }
}

/* Animation pour les onglets */
.tab-pane {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Amélioration des boutons d'action en lot */
.btn-group .btn {
    transition: all 0.3s ease;
}

.btn-group .btn:hover {
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .d-flex.gap-2 {
        flex-direction: column;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin-bottom: 0.5rem;
    }
}

/* Style pour les statistiques */
.row.g-2 .col-4 {
    padding: 0.5rem;
    text-align: center;
}

.row.g-2 .fw-bold {
    color: var(--bs-primary);
    font-size: 1.1rem;
}
</style>

<?php include '../includes/footer.php'; ?>