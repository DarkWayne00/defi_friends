<?php
// defis/participate.php - Participer à un défi

require_once '../config/config.php';

// Vérifier que l'utilisateur est connecté
requireLogin();

$defi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$currentUser = getCurrentUser();
$errors = [];

if (!$defi_id) {
    redirect('/defi_friends/defis/index.php');
}

// Récupérer les détails du défi
$defi = fetchOne(
    "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
            u.pseudo as createur_pseudo, u.id as createur_id
     FROM defis d
     LEFT JOIN categories c ON d.categorie_id = c.id
     LEFT JOIN utilisateurs u ON d.createur_id = u.id
     WHERE d.id = ? AND d.statut = 'actif'",
    [$defi_id]
);

if (!$defi) {
    $_SESSION['flash_message'] = "Défi non trouvé ou non disponible.";
    $_SESSION['flash_type'] = "error";
    redirect('/defi_friends/defis/index.php');
}

// Vérifier si l'utilisateur peut participer
if ($currentUser['id'] == $defi['createur_id']) {
    $_SESSION['flash_message'] = "Vous ne pouvez pas participer à votre propre défi.";
    $_SESSION['flash_type'] = "warning";
    redirect('/defi_friends/defis/view.php?id=' . $defi_id);
}

// Vérifier si l'utilisateur participe déjà
$existingParticipation = fetchOne(
    "SELECT * FROM participations WHERE defi_id = ? AND utilisateur_id = ?",
    [$defi_id, $currentUser['id']]
);

if ($existingParticipation) {
    $_SESSION['flash_message'] = "Vous participez déjà à ce défi.";
    $_SESSION['flash_type'] = "info";
    redirect('/defi_friends/defis/view.php?id=' . $defi_id);
}

// Vérifier la date limite
if ($defi['date_limite'] && new DateTime($defi['date_limite']) < new DateTime()) {
    $_SESSION['flash_message'] = "La date limite pour participer à ce défi est dépassée.";
    $_SESSION['flash_type'] = "warning";
    redirect('/defi_friends/defis/view.php?id=' . $defi_id);
}

// Vérifier les limites de participants pour les défis collectifs
if ($defi['collectif'] && $defi['max_participants']) {
    $currentParticipants = fetchValue(
        "SELECT COUNT(*) FROM participations WHERE defi_id = ?",
        [$defi_id]
    );
    
    if ($currentParticipants >= $defi['max_participants']) {
        $_SESSION['flash_message'] = "Ce défi collectif a atteint le nombre maximum de participants.";
        $_SESSION['flash_type'] = "warning";
        redirect('/defi_friends/defis/view.php?id=' . $defi_id);
    }
}

// Traitement de la participation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $motivation = cleanInput($_POST['motivation'] ?? '');
    $accept_terms = isset($_POST['accept_terms']);
    
    // Validation
    if (!$accept_terms) {
        $errors[] = "Vous devez accepter les conditions de participation.";
    }
    
    if (strlen($motivation) > 500) {
        $errors[] = "La motivation ne peut pas dépasser 500 caractères.";
    }
    
    // Rate limiting
    if (!rateLimitCheck('participate_defi', 10, 3600)) {
        $errors[] = "Trop de participations récentes. Veuillez attendre avant de participer à un nouveau défi.";
    }
    
    if (empty($errors)) {
        try {
            // Créer la participation
            $result = executeQuery(
                "INSERT INTO participations (utilisateur_id, defi_id, date_participation, statut, preuve_texte) 
                 VALUES (?, ?, NOW(), 'en_cours', ?)",
                [$currentUser['id'], $defi_id, $motivation]
            );
            
            if ($result) {
                // Créer une notification pour le créateur du défi
                createNotification(
                    $defi['createur_id'],
                    'participation',
                    'Nouvelle participation !',
                    $currentUser['pseudo'] . ' participe à votre défi "' . $defi['titre'] . '"',
                    ['url' => '/defi_friends/defis/view.php?id=' . $defi_id],
                    $currentUser['id']
                );
                
                $_SESSION['flash_message'] = "Félicitations ! Vous participez maintenant à ce défi. Bonne chance !";
                $_SESSION['flash_type'] = "success";
                
                redirect('/defi_friends/defis/view.php?id=' . $defi_id);
            } else {
                $errors[] = "Erreur lors de l'inscription au défi. Veuillez réessayer.";
            }
        } catch (Exception $e) {
            logError('Erreur lors de la participation au défi', [
                'user_id' => $currentUser['id'],
                'defi_id' => $defi_id,
                'error' => $e->getMessage()
            ]);
            $errors[] = "Une erreur inattendue s'est produite. Veuillez réessayer.";
        }
    }
}

// Obtenir quelques participants existants
$existingParticipants = executeQuery(
    "SELECT u.pseudo, u.photo_profil, p.date_participation
     FROM participations p
     JOIN utilisateurs u ON p.utilisateur_id = u.id
     WHERE p.defi_id = ?
     ORDER BY p.date_participation DESC
     LIMIT 5",
    [$defi_id]
);

$pageTitle = "Participer au défi : " . htmlspecialchars($defi['titre']);

// Breadcrumb
$breadcrumb = [
    ['label' => 'Défis', 'url' => '/defi_friends/defis/index.php'],
    ['label' => htmlspecialchars($defi['titre']), 'url' => '/defi_friends/defis/view.php?id=' . $defi_id],
    ['label' => 'Participer']
];

include '../includes/header.php';
?>

<div class="container">
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- En-tête -->
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="fas fa-play-circle fa-3x text-primary"></i>
                </div>
                <h1 class="h2 fw-bold">Participer au défi</h1>
                <p class="text-muted">Vous êtes sur le point de rejoindre ce défi passionnant !</p>
            </div>

            <!-- Aperçu du défi -->
            <div class="card border-0 shadow-lg rounded-3 mb-4">
                <div class="row g-0">
                    <div class="col-md-4">
                        <?php if ($defi['image_presentation']): ?>
                            <img src="/defi_friends/uploads/<?= htmlspecialchars($defi['image_presentation']) ?>" 
                                 class="img-fluid h-100 w-100 rounded-start" alt="<?= htmlspecialchars($defi['titre']) ?>" 
                                 style="object-fit: cover; min-height: 200px;">
                        <?php else: ?>
                            <div class="h-100 d-flex justify-content-center align-items-center rounded-start" 
                                 style="background: linear-gradient(135deg, <?= $defi['categorie_couleur'] ?>30, <?= $defi['categorie_couleur'] ?>10); min-height: 200px;">
                                <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> fa-4x" 
                                   style="color: <?= $defi['categorie_couleur'] ?>; opacity: 0.7;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <div class="card-body p-4">
                            <!-- Badges -->
                            <div class="d-flex gap-2 mb-3">
                                <span class="badge rounded-pill text-bg-primary">
                                    <i class="fas <?= htmlspecialchars($defi['categorie_icone']) ?> me-1"></i>
                                    <?= htmlspecialchars($defi['categorie_nom']) ?>
                                </span>
                                <span class="badge rounded-pill <?php 
                                    echo match($defi['difficulte']) {
                                        'facile' => 'text-bg-success',
                                        'moyen' => 'text-bg-warning',
                                        'difficile' => 'text-bg-danger',
                                        'extreme' => 'text-bg-dark',
                                        default => 'text-bg-secondary'
                                    }; 
                                ?>">
                                    <?= ucfirst(htmlspecialchars($defi['difficulte'])) ?>
                                </span>
                                <?php if ($defi['collectif']): ?>
                                    <span class="badge rounded-pill text-bg-info">
                                        <i class="fas fa-users me-1"></i>
                                        Collectif
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h3 class="fw-bold mb-3"><?= htmlspecialchars($defi['titre']) ?></h3>
                            
                            <p class="text-muted mb-3">
                                <?= htmlspecialchars(substr($defi['description'], 0, 200)) ?>
                                <?= strlen($defi['description']) > 200 ? '...' : '' ?>
                            </p>
                            
                            <div class="row g-3">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        Par <?= htmlspecialchars($defi['createur_pseudo']) ?>
                                    </small>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= formatDate($defi['date_creation']) ?>
                                    </small>
                                </div>
                                <?php if ($defi['date_limite']): ?>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Limite: <?= formatDate($defi['date_limite']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($defi['recompense']): ?>
                                    <div class="col-6">
                                        <small class="text-success">
                                            <i class="fas fa-gift me-1"></i>
                                            <?= htmlspecialchars($defi['recompense']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations importantes -->
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        Avant de participer
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                <div>
                                    <strong>Lisez attentivement</strong><br>
                                    <small class="text-muted">Assurez-vous de comprendre les objectifs du défi</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-clock text-warning me-2 mt-1"></i>
                                <div>
                                    <strong>Gérez votre temps</strong><br>
                                    <small class="text-muted">Planifiez selon vos disponibilités</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-heart text-danger me-2 mt-1"></i>
                                <div>
                                    <strong>Amusez-vous</strong><br>
                                    <small class="text-muted">L'objectif principal est de prendre du plaisir</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-users text-info me-2 mt-1"></i>
                                <div>
                                    <strong>Respectez les autres</strong><br>
                                    <small class="text-muted">Gardez un esprit fair-play et bienveillant</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participants existants -->
            <?php if (!empty($existingParticipants)): ?>
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Ils participent déjà (<?= count($existingParticipants) ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($existingParticipants as $participant): ?>
                            <div class="d-flex align-items-center bg-light rounded-pill px-3 py-2">
                                <img src="<?= getUserAvatar($participant['photo_profil'], $participant['pseudo'], 24) ?>" 
                                     alt="Avatar" class="rounded-circle me-2" width="24" height="24">
                                <span class="small fw-medium"><?= htmlspecialchars($participant['pseudo']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulaire de participation -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-rocket me-2"></i>
                        Confirmer ma participation
                    </h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Motivation (optionnelle) -->
                        <div class="mb-4">
                            <label for="motivation" class="form-label fw-bold">
                                <i class="fas fa-lightbulb me-2"></i>
                                Votre motivation (optionnel)
                            </label>
                            <textarea class="form-control" id="motivation" name="motivation" rows="4" 
                                      maxlength="500" placeholder="Pourquoi voulez-vous participer à ce défi ? Quels sont vos objectifs ?"><?= htmlspecialchars($_POST['motivation'] ?? '') ?></textarea>
                            <div class="form-text">
                                <span id="motivation-count">0</span>/500 caractères
                            </div>
                        </div>

                        <!-- Rappel des conditions du défi -->
                        <div class="alert alert-info">
                            <h6 class="fw-bold mb-2">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Résumé du défi
                            </h6>
                            <ul class="mb-0">
                                <li><strong>Difficulté :</strong> <?= ucfirst(htmlspecialchars($defi['difficulte'])) ?></li>
                                <li><strong>Catégorie :</strong> <?= htmlspecialchars($defi['categorie_nom']) ?></li>
                                <?php if ($defi['date_limite']): ?>
                                    <li><strong>Date limite :</strong> <?= formatDate($defi['date_limite']) ?></li>
                                <?php endif; ?>
                                <?php if ($defi['collectif']): ?>
                                    <li><strong>Type :</strong> Défi collectif (<?= $defi['min_participants'] ?> participants minimum)</li>
                                <?php endif; ?>
                                <?php if ($defi['recompense']): ?>
                                    <li><strong>Récompense :</strong> <?= htmlspecialchars($defi['recompense']) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Conditions d'acceptation -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" required>
                                <label class="form-check-label" for="accept_terms">
                                    <strong>J'accepte les conditions de participation</strong>
                                </label>
                                <div class="invalid-feedback">
                                    Vous devez accepter les conditions pour participer.
                                </div>
                            </div>
                            <div class="mt-2 small text-muted">
                                En participant, je m'engage à :
                                <ul class="mt-1">
                                    <li>Respecter l'esprit du défi et les autres participants</li>
                                    <li>Fournir des preuves honnêtes de ma réalisation</li>
                                    <li>Maintenir un comportement respectueux dans les interactions</li>
                                    <li>Ne pas tricher ou utiliser de moyens déloyaux</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary btn-lg flex-fill">
                                <i class="fas fa-rocket me-2"></i>
                                Participer maintenant !
                            </button>
                            <a href="/defi_friends/defis/view.php?id=<?= $defi_id ?>" 
                               class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>
                                Retour
                            </a>
                        </div>

                        <!-- Avertissement final -->
                        <div class="mt-4 p-3 bg-warning bg-opacity-10 border border-warning rounded">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                                <div class="small">
                                    <strong>Important :</strong> Une fois votre participation confirmée, vous pourrez commencer le défi immédiatement. 
                                    Vous pourrez marquer le défi comme terminé à tout moment en fournissant une preuve de votre réalisation.
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Compteur de caractères pour la motivation
    const motivationTextarea = document.getElementById('motivation');
    const motivationCount = document.getElementById('motivation-count');
    
    if (motivationTextarea && motivationCount) {
        function updateMotivationCount() {
            const length = motivationTextarea.value.length;
            motivationCount.textContent = length;
            
            if (length > 450) {
                motivationCount.classList.add('text-warning');
            } else if (length >= 500) {
                motivationCount.classList.remove('text-warning');
                motivationCount.classList.add('text-danger');
            } else {
                motivationCount.classList.remove('text-warning', 'text-danger');
            }
        }
        
        motivationTextarea.addEventListener('input', updateMotivationCount);
        updateMotivationCount(); // Initialiser
    }
    
    // Validation du formulaire
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
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Participation en cours...';
                }
            }
            form.classList.add('was-validated');
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
    
    // Confirmation avant participation
    const acceptTerms = document.getElementById('accept_terms');
    if (acceptTerms) {
        acceptTerms.addEventListener('change', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            if (this.checked) {
                submitBtn.classList.remove('btn-outline-primary');
                submitBtn.classList.add('btn-primary');
                submitBtn.disabled = false;
            } else {
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-outline-primary');
                submitBtn.disabled = true;
            }
        });
        
        // État initial
        acceptTerms.dispatchEvent(new Event('change'));
    }
});

// Confirmation de participation avec plus de détails
function confirmParticipation() {
    const defiTitle = <?= escapeJs($defi['titre']) ?>;
    const message = `Êtes-vous sûr de vouloir participer au défi "${defiTitle}" ?\n\nUne fois votre participation confirmée, vous pourrez commencer immédiatement.`;
    
    return confirm(message);
}
</script>

<style>
/* Styles pour la page de participation */
.card {
    transition: all 0.3s ease;
}

/* Animation pour les badges de participants */
.bg-light.rounded-pill {
    transition: all 0.3s ease;
}

.bg-light.rounded-pill:hover {
    background-color: var(--bs-primary) !important;
    color: white;
}

/* Style pour la validation du formulaire */
.form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}

/* Animation pour le bouton de soumission */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 27, 255, 0.3);
}

.btn-primary:disabled {
    opacity: 0.8;
    transform: none;
}

/* Responsive améliorations */
@media (max-width: 768px) {
    .d-flex.gap-3 {
        flex-direction: column;
    }
    
    .btn-lg {
        padding: 0.75rem 1rem;
    }
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

/* Style pour les conditions */
.form-check-label ul {
    margin-left: 1rem;
}

/* Amélioration visuelle des icônes */
.fas {
    transition: color 0.3s ease;
}

.text-success .fas {
    color: var(--bs-success) !important;
}

.text-warning .fas {
    color: var(--bs-warning) !important;
}

.text-danger .fas {
    color: var(--bs-danger) !important;
}

.text-info .fas {
    color: var(--bs-info) !important;
}
</style>

<?php include '../includes/footer.php'; ?>